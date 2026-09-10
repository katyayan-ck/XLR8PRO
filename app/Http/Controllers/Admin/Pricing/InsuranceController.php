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
        $defaults = InsDefault::query()
            ->orderBy('model_code')
            ->orderBy('permit')
            ->paginate(50);

        return view('admin.pricing.insurance.defaults', [
            'title'    => 'Insurance Defaults',
            'defaults' => $defaults,
        ]);
    }

    public function baseRulesIndex()
    {
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
        $request->validate([
            'model_code'  => 'required|string',
            'ex_showroom' => 'required|numeric|min:0',
            'permit'      => 'nullable|string',
        ]);

        try {
            $result = $this->insuranceService->calculate(
                $request->model_code,
                [
                    'ex_showroom' => (float) $request->ex_showroom,
                    'permits'     => $request->permit
                        ? [$request->permit]
                        : null,
                ]
            );

            return response()->json([
                'success' => true,
                'result'  => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}