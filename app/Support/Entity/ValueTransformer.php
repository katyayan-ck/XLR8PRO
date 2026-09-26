<?php

declare(strict_types=1);

namespace App\Support\Entity;

use App\Models\Traits\HasColumnTransformations;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs a HasColumnTransformations pipeline on a value outside a model, so entity services and
 * the model backstop share one transformation engine (DEC-050).
 *
 * @internal
 */
final class ValueTransformer extends Model
{
    use HasColumnTransformations;

    /** @param list<string>|string $pipeline */
    public function run(string $value, array|string $pipeline): string
    {
        return $this->runTransformation($value, $pipeline);
    }
}
