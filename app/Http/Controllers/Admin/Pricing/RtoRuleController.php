<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\RtoService;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Support\ErrorRef;
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

        // Every field rule (scope synonyms, ANY wheels, amounts) lives in RtoRuleService (DEC-056).
        app(RtoRuleService::class)->create($request->all() + ['is_active' => $request->boolean('is_active', true)]);

        \Alert::success(__('pricing.flash.rto_rule_created_successfully'))->flash();

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

        app(RtoRuleService::class)->update(RtoRule::findOrFail($id), ['is_active' => $request->boolean('is_active', true)] + $request->all());

        \Alert::success(__('pricing.flash.rto_rule_updated_successfully'))->flash();

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
                'message' => ErrorRef::userMessage($e),
            ], 422);
        }
    }
}
