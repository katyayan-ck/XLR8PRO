<?php

namespace App\Models\Vehicle\Pricing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A queued pricing-master import (DEC-083), written by ImportPricingMasterJob.
 *
 * @property int $id
 * @property string $master
 * @property string $status queued | running | done | failed
 * @property string|null $file_name
 * @property string $path
 * @property Carbon|null $wef_date
 * @property string|null $message
 * @property array<string, mixed>|null $result
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
class MasterImport extends Model
{
    protected $table = 'xlr8_pricing_master_imports';

    protected $fillable = ['master', 'status', 'file_name', 'path', 'wef_date', 'message', 'result', 'created_by'];

    protected function casts(): array
    {
        return ['result' => 'array', 'wef_date' => 'date'];
    }
}
