<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Services\Vehicle\Pricing\Engine\PricingQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin price lookup (DEC-073 step 11): getPricing for one OEM code with the same selections the API accepts, shown as
 * a breakdown plus the raw fixed-key JSON. Read-only.
 */
class PriceLookupController extends Controller
{
    public function __construct(private readonly PricingQueryService $pricing) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to look up prices.');
        }
        $input = $request->validate([
            'oem_code' => ['nullable', 'string', 'max:40'],
            'permit' => ['nullable', 'string', 'max:30'],
            'vin_type' => ['nullable', Rule::in(['NV', 'OV'])],
            'channel' => ['nullable', Rule::in(['normal', 'csd'])],
            'wef_date' => ['nullable', 'date'],
            'rsa_years' => ['nullable', 'integer', 'min:0', 'max:10'],
            'shield_scheme' => ['nullable', 'integer', 'min:0', 'max:10'],
            'insurance' => ['nullable', 'array'],
            'insurance.company' => ['nullable', 'string', 'max:40'],
            'insurance.plan' => ['nullable', 'string', 'max:20'],
            'reg_type' => ['nullable', Rule::in(['Regular', 'BH'])],
            'outside_state' => ['nullable', 'boolean'],
            'include_cod' => ['nullable', 'boolean'],
            'exchange' => ['nullable', 'string', 'max:60'],
            'corporate' => ['nullable', 'string', 'max:60'],
            'loyalty' => ['nullable', 'string', 'max:60'],
        ]);

        $result = null;
        if (! empty($input['oem_code'])) {
            $options = array_filter($input, fn ($v) => $v !== null && $v !== '' && $v !== []);
            if (isset($options['insurance'])) {
                $options['insurance'] = array_filter($options['insurance'], fn ($v) => $v !== null && $v !== '');
                if ($options['insurance'] === []) {
                    unset($options['insurance']);
                }
            }
            unset($options['oem_code']);
            $result = $this->pricing->getPricing($input['oem_code'], $options);
        }

        return view('admin.pricing.lookup', [
            'title' => 'Price Lookup',
            'input' => $input,
            'result' => $result,
            'pricing' => $result?->data['pricing'] ?? null,
        ]);
    }
}
