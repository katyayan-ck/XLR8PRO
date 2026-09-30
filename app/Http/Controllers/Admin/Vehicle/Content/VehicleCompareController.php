<?php

namespace App\Http\Controllers\Admin\Vehicle\Content;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Content\CompareService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vehicles → Compare (DEC-092 Phase 4): trims of one model on their features, or models of one segment on their
 * specifications; "only differences" option; printable. VEH_CMPR_VIEW. The comparison itself is CompareService.
 */
class VehicleCompareController extends Controller
{
    public function __construct(private readonly CompareService $compare) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('VEH_CMPR_VIEW')) {
            abort(403);
        }
        $data = $request->validate([
            'mode' => 'nullable|in:variants,models', 'model' => 'nullable|string|max:50', 'segment' => 'nullable|string|max:10',
            'v' => 'nullable|array|max:'.CompareService::MAX, 'v.*' => 'string|max:50',
            'm' => 'nullable|array|max:'.CompareService::MAX, 'm.*' => 'string|max:50', 'diff' => 'nullable|boolean',
        ]);
        $mode = $data['mode'] ?? 'variants';
        $models = VehicleModel::query()->where('is_active', true)->orderBy('segment_code')->orderBy('name')->get(['code', 'name', 'segment_code']);
        $trims = empty($data['model']) ? collect() : Variant::query()->where('model_code', strtoupper($data['model']))
            ->selectRaw('code, MAX(display_name) as label')->groupBy('code')->orderBy('label')->get();

        $result = null;
        if ($mode === 'variants' && ! empty($data['model']) && ! empty($data['v'])) {
            $result = $this->compare->variants($data['model'], $data['v'], (bool) ($data['diff'] ?? false));
        } elseif ($mode === 'models' && ! empty($data['m'])) {
            $result = $this->compare->models($data['m'], (bool) ($data['diff'] ?? false));
        }

        return view('admin.vehicle.content.compare', [
            'title' => 'Compare vehicles', 'mode' => $mode, 'input' => $data, 'models' => $models, 'trims' => $trims,
            'result' => $result, 'max' => CompareService::MAX,
        ]);
    }
}
