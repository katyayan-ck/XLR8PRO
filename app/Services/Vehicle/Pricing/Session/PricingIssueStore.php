<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

use App\Models\Vehicle\Pricing\ImportSession;
use Illuminate\Support\Facades\Storage;

/**
 * Row issues of a pricing step (Vehicle Info, prices, add-ons, rules, detect code lists) live in a file per session
 * and step — storage/app/pricing/{session}/{step}-issues.json — not in the session's stats JSON. Large workbooks give
 * thousands of issues; rewriting them inside the session row on every update exhausted MySQL memory (DEC-080).
 *
 *   $store->split($session, 'prices', $result)   // writes the issues file, returns the result with
 *                                                //   issues_count + issues (first PREVIEW rows) for the screen
 *   $store->all($session, 'prices')              // every issue (screens' full list, downloads)
 */
class PricingIssueStore
{
    public const PREVIEW = 20;

    /**
     * @param  array<string, mixed>  $result  a step result with a list under $key
     * @return array<string, mixed> the result with the list replaced by a preview + count
     */
    public function split(ImportSession $session, string $step, array $result, string $key = 'issues'): array
    {
        $issues = array_values((array) ($result[$key] ?? []));
        Storage::disk('local')->put($this->path($session, $step.($key === 'issues' ? '' : '-'.$key)), json_encode($issues, JSON_UNESCAPED_UNICODE) ?: '[]');
        $result[$key.'_count'] = count($issues);
        $result[$key] = array_slice($issues, 0, self::PREVIEW);

        return $result;
    }

    /** @param array<array-key, mixed> $data any list kept beside the session (e.g. detect's code lists) */
    public function put(ImportSession $session, string $name, array $data): void
    {
        Storage::disk('local')->put($this->path($session, $name), json_encode($data, JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    /** @return list<array<string, mixed>> */
    public function all(ImportSession $session, string $step, string $key = 'issues'): array
    {
        $path = $this->path($session, $step.($key === 'issues' ? '' : '-'.$key));
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        return array_values((array) json_decode((string) Storage::disk('local')->get($path), true));
    }

    private function path(ImportSession $session, string $name): string
    {
        return "pricing/{$session->id}/{$name}-issues.json";
    }
}
