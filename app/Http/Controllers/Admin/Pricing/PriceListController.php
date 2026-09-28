<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Services\Vehicle\Pricing\Engine\PriceListService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The standalone Price List screens (DEC-081): open to every logged-in admin user (no permission, by request), read-only.
 * The page renders at once; the grid fetches its rows from rows() (projected + cached in PriceListService).
 */
class PriceListController extends Controller
{
    public function __construct(
        private readonly PriceListService $lists,
        private readonly PricingHoldService $holds,
    ) {}

    public function index(Request $request): View
    {
        $date = $this->date($request);

        return view('admin.pricing.price-list.index', [
            'title' => 'Price List',
            'date' => $date,
            'lists' => PriceListService::LISTS,
            'counts' => $this->lists->counts($date),
            'held' => $this->holds->heldLists(),
        ]);
    }

    public function show(Request $request, string $list): View
    {
        $def = PriceListService::LISTS[$list] ?? abort(404);

        return view('admin.pricing.price-list.show', [
            'title' => 'Price List · '.$def['label'],
            'list' => $list,
            'label' => $def['label'],
            'date' => $this->date($request),
            'lists' => PriceListService::LISTS,
        ]);
    }

    public function rows(Request $request, string $list): JsonResponse
    {
        if (! isset(PriceListService::LISTS[$list])) {
            abort(404);
        }

        return response()->json($this->lists->rows($list, $this->date($request)));
    }

    private function date(Request $request): string
    {
        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? null;

        return $date ? date('Y-m-d', (int) strtotime($date)) : now()->toDateString();
    }
}
