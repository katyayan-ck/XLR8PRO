<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\RtoService;
use Illuminate\Http\Request;

class RtoRuleController extends Controller
{
    public function __construct(
        protected RtoService $rtoService
    ) {}

    public function index()
    {
        if (! backpack_user()->can('PRC_RTOR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view RTO rules.');
        }

        $rules = RtoRule::query()
            ->orderBy('permit')
            ->orderBy('wheels')
            ->orderByDesc('wef_date')
            ->paginate(50);

        return view('admin.pricing.rto.index', [
            'title' => 'RTO Rules',
            'rules' => $rules,
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('PRC_RTOR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view RTO rules.');
        }

        return view('admin.pricing.rto.create', [
            'title' => 'Add RTO Rule',
            'rule' => new RtoRule,
        ]);
    }

    public function store(Request $request)
    {
        if (! backpack_user()->can('PRC_RTOR_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create RTO rules.');
        }

        $data = $this->validateRule($request);
        $data['is_active'] = $request->boolean('is_active', true);

        RtoRule::create($data);

        \Alert::success('RTO Rule created successfully.')->flash();

        return redirect()->route('pricing.rto.index');
    }

    public function edit(int $id)
    {
        if (! backpack_user()->can('PRC_RTOR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view RTO rules.');
        }

        $rule = RtoRule::findOrFail($id);

        return view('admin.pricing.rto.edit', [
            'title' => 'Edit RTO Rule',
            'rule' => $rule,
        ]);
    }

    public function update(Request $request, int $id)
    {
        if (! backpack_user()->can('PRC_RTOR_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit RTO rules.');
        }

        $rule = RtoRule::findOrFail($id);
        $data = $this->validateRule($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $rule->update($data);

        \Alert::success('RTO Rule updated successfully.')->flash();

        return redirect()->route('pricing.rto.index');
    }

    public function testCalculate(Request $request)
    {
        if (! backpack_user()->can('PRC_RTOR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to test RTO calculations.');
        }

        $request->validate([
            'model_code' => 'required|string',
            'ex_showroom' => 'required|numeric|min:0',
            'permit' => 'nullable|string',
        ]);

        try {
            $result = $this->rtoService->calculate(
                $request->model_code,
                [
                    'ex_showroom' => (float) $request->ex_showroom,
                    'permits' => $request->permit
                        ? [$request->permit]
                        : null,
                ]
            );

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    protected function validateRule(Request $request): array
    {
        return $request->validate([
            'code' => 'nullable|string|max:50',
            'permit' => 'required|string|max:30',
            'wheels' => 'nullable|integer|min:2|max:16',
            'reg_type' => 'nullable|string|max:20',
            'body_type' => 'nullable|string|max:30',
            'gvw_range' => 'nullable|string|max:30',
            'seater' => 'nullable|string|max:20',
            'fuel_type' => 'nullable|string|max:30',
            'cc_range' => 'nullable|string|max:30',
            'tax_factor' => 'nullable|numeric|min:0',
            'tax_slab' => 'nullable|string|max:50',
            'surcharge' => 'nullable|numeric|min:0',
            'hypothecation' => 'nullable|numeric|min:0',
            'green_tax' => 'nullable|numeric|min:0',
            'registration_fee' => 'nullable|numeric|min:0',
            'duplicate_tax_card' => 'nullable|numeric|min:0',
            'fitness' => 'nullable|numeric|min:0',
            'penalty' => 'nullable|numeric|min:0',
            'rto_tape' => 'nullable|numeric|min:0',
            'wef_date' => 'nullable|date',
            'expired_on' => 'nullable|date',
        ]);
    }
}
