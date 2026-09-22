<?php

namespace App\Services\Sales\Booking;

use App\Helpers\CommonHelper;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\Vehicle\VehicleModel;
use App\Services\IdentifierService;
use App\Services\OrgService;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the Booking KYC sub-domain (Pending KYC list ->
 * kycEdit() -> kycUpdate()), extracted from BookingCrudController as part
 * of the Sales-system refactor (Phase 4) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 *
 * Follows this app's current service convention: instance methods, thin
 * constructor, injected via constructor property promotion. Registered as
 * a singleton in AppServiceProvider.
 */
class BookingKycService
{
    public function __construct(private IdentifierService $identifiers) {}

    /**
     * Resolves the display data the KYC edit form needs - customer name
     * and branch/location/segment/model/variant/color names, falling back
     * to the linked Enquiry wherever the Booking's own columns are empty.
     * Mutates $booking in place (fills the same fallback values onto its
     * branch_code/location_code/segment_code/model_code/variant_code/
     * color_code/name attributes) so the edit form's fields pre-fill
     * correctly - this mirrors the exact side effect the original inline
     * controller code had, not a new behavior.
     *
     * @return array<string, mixed>
     */
    public function resolveEditData(Booking $booking): array
    {
        $enquiry = null;

        if (! empty($booking->enq_no)) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        $branchCode = $booking->branch_code ?? $enquiry?->dealer_branch;
        $locationCode = $booking->location_code ?? $enquiry?->dealer_location;
        $segmentCode = $booking->segment_code ?? $enquiry?->segment_code;
        $modelCode = $booking->model_code ?? $enquiry?->model_code;
        $variantCode = $booking->variant_code ?? $enquiry?->variant_code;
        $colorCode = $booking->color_code ?? $enquiry?->color_code;
        $customerName = $booking->name ?? $enquiry?->name ?? '—';

        $branchName = '—';

        if (! empty($branchCode)) {
            $branchName = Branch::where('code', $branchCode)->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $branchCode)->value('name');
            }
        }

        $branchName = $branchName ?: '—';

        $locationName = '—';

        if (! empty($locationCode)) {
            $locationName = Location::where('code', $locationCode)->value('name');
        }

        $locationName = $locationName ?: ($booking->location_other ?? '—');

        $modelName = '—';

        if (! empty($modelCode)) {
            $modelName = VehicleModel::where('code', $modelCode)->value('name');
        }

        $modelName = $modelName ?: ($modelCode ?: '—');

        $variantName = '—';
        $colorName = '—';

        if (! empty($variantCode)) {
            $variantRows = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->get(['custom_name', 'color', 'color_code']);

            if ($variantRows->isNotEmpty()) {
                $variantName = $variantRows->first()->custom_name ?? '—';

                if (! empty($colorCode)) {
                    $colorRow = $variantRows->first(function ($row) use ($colorCode) {
                        return strtoupper((string) $row->color_code) === strtoupper((string) $colorCode);
                    });

                    if ($colorRow) {
                        $colorName = $colorRow->color ?? '—';
                    }
                }

                if ($colorName === '—') {
                    $colorName = $variantRows->first()->color ?? '—';
                }
            }
        }

        $variantName = $variantName ?: ($variantCode ?: '—');
        $colorName = $colorName ?: ($colorCode ?: '—');

        $booking->name = $booking->name ?? $enquiry?->name;
        $booking->branch_code = $branchCode;
        $booking->location_code = $locationCode;
        $booking->segment_code = $segmentCode;
        $booking->model_code = $modelCode;
        $booking->variant_code = $variantCode;
        $booking->color_code = $colorCode;

        return [
            'branches' => Branch::pluck('name', 'id')->toArray(),
            'locations' => Location::pluck('name', 'id')->toArray(),
            'segments' => CommonHelper::getVehicleSegments(),
            'saleConsultants' => OrgService::usersByDesignation('CNS') ?? [],
            'customer_name' => $customerName,
            'branch_name' => $branchName,
            'location_name' => $locationName,
            'model_name' => $modelName,
            'variant_name' => $variantName,
            'color_name' => $colorName,
        ];
    }

    /**
     * Applies validated KYC data to a Booking: normalizes PAN/Aadhaar/GST,
     * saves, and records the "KYC Completed" history entry. Returns the
     * refreshed Booking.
     *
     * @param  array{pan_no: string, adhar_no: string, gst_no?: ?string}  $validated
     */
    public function apply(Booking $booking, array $validated, bool $gstNotRequired): Booking
    {
        $panNo = $this->identifiers->normalizePan($validated['pan_no']);
        $adharNo = $this->identifiers->normalizeAadhaar($validated['adhar_no']);

        $gstValue = $gstNotRequired
            ? '0'
            : ($this->identifiers->normalizeGstin($validated['gst_no'] ?? null) ?? $booking->gstn ?? '0');

        $booking->update([
            'pan_no' => $panNo,
            'adhar_no' => $adharNo,
            'gstn' => $gstValue,
        ]);

        $booking->refresh();

        $booking->addHistory(
            'commented',
            'KYC Completed',
            'Customer KYC details updated successfully',
            [
                'module' => 'Pending KYC',
                'pan_no' => $panNo,
                'adhar_no' => $adharNo,
                'gstn' => $gstValue,
            ],
            null,
            backpack_user()
        );

        return $booking;
    }
}
