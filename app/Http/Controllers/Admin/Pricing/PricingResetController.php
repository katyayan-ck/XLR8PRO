<?php

/**
 * Path: app/Http/Controllers/Admin/Pricing/PricingResetController.php
 *
 * GET /admin/pricing/reset?after=2026-08-20
 * GET /admin/pricing/reset?after=2026-08-20&confirm=1
 */

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Services\Vehicle\Pricing\PricingResetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PricingResetController extends Controller
{
    public function __construct(
        protected PricingResetService $reset
    ) {}

    public function __invoke(Request $request): Response
    {
        $after = $request->query('after', '2026-08-20');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $after)) {
            return response("ERROR: after must be YYYY-MM-DD (got {$after})\n", 422)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (! $request->boolean('confirm')) {
            $body = implode("\n", [
                'PRICING RESET — dry preview (nothing deleted)',
                "Cutoff date: {$after} 00:00:00 (created_at >= this is deleted from variants/models)",
                'Will FLUSH: sessions, profile, OEM prices + history, flags, draft, affected, snapshots, holds, CSD, jobs',
                'Will KEEP: sheet_headers, addons, discounts, dealer_charges, RTO, insurance, TCS, synonyms',
                'Will DELETE: xlr8_vehicle_variant + orphan xlr8_vehicle_model created on/after cutoff',
                '',
                'Re-run with confirm=1 to execute:',
                url()->current() . '?after=' . $after . '&confirm=1',
                '',
            ]);

            return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $lines = $this->reset->run($after, $request->boolean('flush_queue', true));

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
