<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Specification / feature items get a stable code from their category and name when none is given (DEC-092): upper
 * snake case, max 60 characters, a numeric suffix when the code is taken ("ENGINE_DISPLACEMENT", "…_2").
 */
trait DerivesItemCode
{
    /** @param  class-string<Model>  $model */
    protected function deriveCode(string $model, string $group, string $name): string
    {
        $base = Str::limit(strtoupper(Str::slug($group.' '.$name, '_')), 56, '');
        $base = rtrim($base, '_') ?: 'ITEM';
        $code = $base;
        for ($n = 2; $model::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = $base.'_'.$n;
        }

        return $code;
    }
}
