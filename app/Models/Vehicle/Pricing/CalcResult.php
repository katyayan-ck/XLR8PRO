<?php

namespace App\Models\Vehicle\Pricing;

use Illuminate\Database\Eloquent\Model;

/**
 * One vehicle's outcome in a Calculate & Publish run (DEC-080): published (with the snapshot count), failed (with the
 * reason — retried by "Retry failed") or skipped (held list, not Active / incomplete). Engine-written.
 *
 * @property int $id
 * @property int $import_session_id
 * @property string $model_code
 * @property string|null $price_list
 * @property string $status
 * @property int $snapshots
 * @property string|null $message
 */
class CalcResult extends Model
{
    public const PUBLISHED = 'published';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    protected $table = 'xlr8_vehicle_pricing_calc_results';

    protected $fillable = ['import_session_id', 'model_code', 'price_list', 'status', 'snapshots', 'message', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['snapshots' => 'integer'];
    }
}
