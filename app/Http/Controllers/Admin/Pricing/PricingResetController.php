<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Services\Vehicle\Pricing\PricingResetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Destructive pricing reset (local environments only). GET shows a dry preview and a confirmation form; only a POST
 * with the typed confirmation runs it (DEC-082 — was a GET with `confirm=1`).
 */
class PricingResetController extends Controller
{
    public const CONFIRMATION = 'RESET';

    public function __construct(
        protected PricingResetService $reset
    ) {}

    public function preview(Request $request): View
    {
        if (! backpack_user()->can('PRC_RESET_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run a pricing reset.');
        }
        $after = $request->validate(['after' => ['nullable', 'date_format:Y-m-d']])['after'] ?? '2026-08-20';

        return view('admin.pricing.reset', [
            'title' => 'Pricing Reset',
            'after' => $after,
            'allowed' => $this->allowed(),
            'confirmation' => self::CONFIRMATION,
        ]);
    }

    public function run(Request $request): Response
    {
        if (! backpack_user()->can('PRC_RESET_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run a pricing reset.');
        }
        if (! $this->allowed()) {
            abort(403, 'The pricing reset runs only in a local environment.');
        }
        $input = $request->validate([
            'after' => ['required', 'date_format:Y-m-d'],
            'confirmation' => ['required', 'in:'.self::CONFIRMATION],
        ], ['confirmation.in' => 'Type '.self::CONFIRMATION.' to confirm.']);

        $lines = $this->reset->run($input['after'], $request->boolean('flush_queue', true));

        return response(implode("\n", $lines)."\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function allowed(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
