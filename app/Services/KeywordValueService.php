<?php

namespace App\Services;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KeywordValueService
{
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'kwv_';

    // ==================== RECOMMENDED NATURAL KEY METHODS ====================
    public static function getCode(string $keywordCode, string $valueCode, bool $activeOnly = true): ?string
    {
        $value = self::findByCode($keywordCode, $valueCode, $activeOnly);
        return $value?->code;
    }

    public static function getByCode(string $keywordCode, string $valueCode, bool $activeOnly = true): ?Keyvalue
    {
        return self::findByCode($keywordCode, $valueCode, $activeOnly);
    }

    public static function getEnum(string $keywordCode, bool $activeOnly = true): array
    {
        $keywordCode = strtoupper(trim($keywordCode));
        $cacheKey = self::CACHE_PREFIX . "enum_{$keywordCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($keywordCode, $activeOnly) {
            $query = Keyvalue::where('keyword_code', $keywordCode);
            if ($activeOnly) $query->where('is_active', true);
            return $query->pluck('value', 'code')->toArray();
        });
    }

    // ==================== BACKWARD COMPATIBILITY ====================
    public static function getValueId(string $keyword, string $key, bool $activeOnly = true): ?int
    {
        return self::findByCode($keyword, $key, $activeOnly)?->id;
    }

    public static function getValue(string $keyword, string $key, bool $activeOnly = true): ?Keyvalue
    {
        return self::findByCode($keyword, $key, $activeOnly);
    }

    // ==================== INTERNAL ====================
    private static function findByCode(string $keywordCode, string $valueCode, bool $activeOnly = true): ?Keyvalue
    {
        $keywordCode = strtoupper(trim($keywordCode));
        $valueCode   = strtoupper(trim($valueCode));

        $cacheKey = self::CACHE_PREFIX . "code_{$keywordCode}_{$valueCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($keywordCode, $valueCode, $activeOnly) {
            $query = Keyvalue::where('keyword_code', $keywordCode)
                ->where('code', $valueCode);

            if ($activeOnly) $query->where('is_active', true);

            return $query->first();
        });
    }

    public static function clearCache(?string $keywordCode = null): void
    {
        if ($keywordCode === null) {
            Cache::flush();
        } else {
            $keywordCode = strtoupper(trim($keywordCode));
            Cache::forget(self::CACHE_PREFIX . "enum_{$keywordCode}");
        }
        Log::info("KeywordValueService cache cleared");
    }
}
