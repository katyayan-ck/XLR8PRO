<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Services\Vehicle\Pricing\InsuranceService;
use Illuminate\Http\Request;

class InsuranceController extends Controller
{
    public function __construct(
        protected InsuranceService $insuranceService
    ) {}

    public function defaultsIndex()
    {
        if (! backpack_user()->can('PRC_INSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view insurance defaults.');
        }

        $defaults = InsDefault::query()
            ->orderBy('model_code')
            ->orderBy('permit')
            ->paginate(50);

        return view('admin.pricing.insurance.defaults', [
            'title' => 'Insurance Defaults',
            'defaults' => $defaults,
        ]);
    }

    public function baseRulesIndex()
    {
        if (! backpack_user()->can('PRC_INSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view insurance base rules.');
        }

        $rules = InsBaseRule::query()
            ->orderBy('permit')
            ->orderByDesc('wef_date')
            ->paginate(50);

        return view('admin.pricing.insurance.base-rules', [
            'title' => 'Insurance Base Rules',
            'rules' => $rules,
        ]);
    }

    public function addonRatesIndex()
    {
        if (! backpack_user()->can('PRC_INSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view insurance addon rates.');
        }

        $rates = InsAddonRate::query()
            ->orderBy('insurance_company')
            ->orderBy('permit')
            ->orderBy('addon_slug')
            ->paginate(50);

        return view('admin.pricing.insurance.addon-rates', [
            'title' => 'Insurance Addon Rates',
            'rates' => $rates,
        ]);
    }

    public function testCalculate(Request $request)
    {
        if (! backpack_user()->can('PRC_INSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to test insurance calculations.');
        }

        $request->validate([
            'model_code' => 'required|string',
            'ex_showroom' => 'required|numeric|min:0',
            'permit' => 'nullable|string',
        ]);

        try {
            $result = $this->insuranceService->calculate(
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
}
