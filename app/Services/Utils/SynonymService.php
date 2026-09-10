<?php

namespace App\Services\Utils;

use App\Models\Utilities\Synonym;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Project-wide typo / alternate-spelling resolution.
 *
 * setSynonym("Branch", "BKN", "BEEKANER,BIKANER,BINAKER", "ADD");
 * getSynonym("Branch", "BINAKER") → "BKN"
 */
class SynonymService
{
    protected const CACHE_TTL = 3600;

    public function getSynonym(string $entityType, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $map = $this->mapForType($entityType);
        $key = mb_strtoupper($value);

        if (isset($map[$key])) {
            return $map[$key];
        }

        $canonicals = array_unique(array_values($map));
        foreach ($canonicals as $c) {
            if (mb_strtoupper((string) $c) === $key) {
                return $c;
            }
        }

        return null;
    }

    public function resolve(string $entityType, ?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }
        $canonical = $this->getSynonym($entityType, $value);

        return $canonical ?? trim($value);
    }

    /**
     * @param  string  $action  ADD | REPLACE | REMOVE
     */
    public function setSynonym(
        string $entityType,
        string $canonical,
        string $synonymsCsv,
        string $action = 'ADD',
        ?int $userId = null
    ): int {
        $entityType = trim($entityType);
        $canonical  = trim($canonical);
        $action     = strtoupper(trim($action));
        $userId     = $userId ?? Auth::id();
        $synonyms   = $this->parseList($synonymsCsv);

        if ($entityType === '' || $canonical === '') {
            throw new \InvalidArgumentException('entityType and canonical are required');
        }

        $affected = 0;

        DB::transaction(function () use ($entityType, $canonical, $synonyms, $action, $userId, &$affected) {
            if ($action === 'REPLACE') {
                Synonym::query()
                    ->ofType($entityType)
                    ->canonical($canonical)
                    ->each(function (Synonym $row) use ($userId) {
                        $row->deleted_by = $userId;
                        $row->save();
                        $row->delete();
                    });
            }

            if ($action === 'REMOVE') {
                foreach ($synonyms as $syn) {
                    $rows = Synonym::query()
                        ->ofType($entityType)
                        ->where('synonym', $syn)
                        ->get();
                    foreach ($rows as $row) {
                        $row->deleted_by = $userId;
                        $row->save();
                        $row->delete();
                        $affected++;
                    }
                }
                return;
            }

            foreach ($synonyms as $syn) {
                if (mb_strtoupper($syn) === mb_strtoupper($canonical)) {
                    continue;
                }
                $existing = Synonym::withTrashed()
                    ->ofType($entityType)
                    ->where('synonym', $syn)
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->fill([
                        'canonical'  => $canonical,
                        'is_active'  => true,
                        'updated_by' => $userId,
                        'deleted_by' => null,
                    ]);
                    $existing->save();
                } else {
                    Synonym::create([
                        'entity_type' => $entityType,
                        'canonical'   => $canonical,
                        'synonym'     => $syn,
                        'is_active'   => true,
                        'created_by'  => $userId,
                        'updated_by'  => $userId,
                    ]);
                }
                $affected++;
            }
        });

        $this->forgetCache($entityType);

        Log::info('[SynonymService] setSynonym', [
            'entity_type' => $entityType,
            'canonical'   => $canonical,
            'action'      => $action,
            'affected'    => $affected,
        ]);

        return $affected;
    }

    public function mapForType(string $entityType): array
    {
        $entityType = trim($entityType);
        $cacheKey   = 'utils.synonyms.' . mb_strtolower($entityType);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($entityType) {
            $map = [];
            $rows = Synonym::query()
                ->active()
                ->ofType($entityType)
                ->get(['canonical', 'synonym']);

            foreach ($rows as $row) {
                $map[mb_strtoupper($row->synonym)]   = $row->canonical;
                $map[mb_strtoupper($row->canonical)] = $row->canonical;
            }

            return $map;
        });
    }

    public function forgetCache(?string $entityType = null): void
    {
        if ($entityType) {
            Cache::forget('utils.synonyms.' . mb_strtolower(trim($entityType)));
            return;
        }

        $types = Synonym::query()->distinct()->pluck('entity_type');
        foreach ($types as $type) {
            Cache::forget('utils.synonyms.' . mb_strtolower($type));
        }
    }

    protected function parseList(string $csv): array
    {
        $parts = preg_split('/\s*,\s*/', $csv) ?: [];
        $out   = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }
}
