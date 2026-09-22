<?php

namespace App\Services\Sales\Booking;

use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\User;

/**
 * Business logic for the Booking DMS sub-domain (Pending DMS list ->
 * dmsedit() -> dmsupdate()), extracted from BookingCrudController as part
 * of the Sales-system refactor (Phase 4) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingDmsService
{
    /** Segment codes treated as "BEV or Personal" throughout this sub-domain. */
    private const BEV_OR_PERSONAL_SEGMENTS = [753, 21589];

    private const DMS_PENDING_ITEMS = [
        'DMS Booking no needs to be updated',
        'DMS OTF needs to be updated',
        'DMS OTF Date needs to be updated',
        'DMS SO number needs to be updated',
    ];

    /**
     * Resolves the display data the DMS edit form needs, falling back to
     * the linked Enquiry wherever the Booking's own columns are empty.
     * Mutates $booking in place (same load-bearing side effect as
     * BookingKycService::resolveEditData() - the edit form's fields
     * pre-fill directly off $booking).
     *
     * @return array<string, mixed>
     */
    public function resolveEditData(Booking $booking, bool $fromPending): array
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

        $branchName = 'N/A';

        if (! empty($branchCode)) {
            $branchName = Branch::where('code', $branchCode)->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $branchCode)->value('name');
            }
        }

        $branchName = $branchName ?: 'N/A';

        $locationName = 'N/A';

        if (! empty($locationCode)) {
            $locationName = Location::where('code', $locationCode)->value('name');
        }

        if (! $locationName) {
            $locationName = $booking->location_other ?? 'N/A';
        }

        $collectorName = $booking->col_by
            ? (User::find($booking->col_by)->name ?? 'N/A')
            : 'N/A';

        $booking->booking_date = $booking->booking_date ?? $enquiry?->created_at;
        $booking->name = $booking->name ?? $enquiry?->name;
        $booking->branch_code = $branchCode;
        $booking->location_code = $locationCode;
        $booking->segment_code = $segmentCode;
        $booking->model_code = $modelCode;
        $booking->variant_code = $variantCode;
        $booking->color_code = $colorCode;

        $isBevOrPersonal = $this->isBevOrPersonal($segmentCode);

        return [
            'branch' => $branchName,
            'location' => $locationName,
            'collector_name' => $collectorName,
            'accessories' => $booking->accessories ?? 'N/A',
            'total_amount' => $booking->total_amount ?? 0,
            'is_bev_or_personal' => $isBevOrPersonal,
            'from_pending' => $fromPending,
            'so_required' => $fromPending && $isBevOrPersonal,
        ];
    }

    /**
     * Applies validated DMS data to a Booking: saves the DMS fields,
     * recomputes the pending-items list (dropping DMS items that are now
     * satisfied, re-adding any that still aren't), transitions
     * status/order accordingly, and records the "Pending Order Processed"
     * history entry. Returns the refreshed Booking.
     *
     * $validated['dms_so'] is always the raw submitted value, regardless
     * of $dmsSoApplies - it drives the order=2/3 transition below
     * unconditionally (matching the original inline logic exactly), even
     * though it's only persisted to the booking and included in the
     * pending-items check when $dmsSoApplies is true.
     *
     * @param  array{dms_no: string, dms_otf: string, otf_date: string, dms_so: string}  $validated
     * @param  bool  $dmsSoApplies  whether dms_so is part of this booking's required/saved fields (order == 2)
     */
    public function apply(Booking $booking, array $validated, bool $dmsSoApplies): Booking
    {
        $updateData = [
            'dms_no' => $validated['dms_no'],
            'dms_otf' => $validated['dms_otf'],
            'otf_date' => $validated['otf_date'],
        ];

        if ($dmsSoApplies) {
            $updateData['dms_so'] = $validated['dms_so'];
        }

        $booking->update($updateData);
        $booking->refresh();

        $existingPending = collect(explode(',', $booking->pending_remark ?? ''))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->toArray();

        $remainingPending = array_diff($existingPending, self::DMS_PENDING_ITEMS);

        $newPending = [];

        if (empty($booking->dms_no)) {
            $newPending[] = 'DMS Booking no needs to be updated';
        }
        if (empty($booking->dms_otf)) {
            $newPending[] = 'DMS OTF needs to be updated';
        }
        if (empty($booking->otf_date)) {
            $newPending[] = 'DMS OTF Date needs to be updated';
        }
        if ($dmsSoApplies && empty($booking->dms_so)) {
            $newPending[] = 'DMS SO number needs to be updated';
        }

        $finalPending = array_unique(array_filter(array_merge($remainingPending, $newPending)));

        $booking->pending_remark = ! empty($finalPending)
            ? implode(' , ', array_map('trim', $finalPending))
            : null;

        $booking->pending = count($finalPending);

        $isBevOrPersonal = $this->isBevOrPersonal($booking->segment_code);
        $booking->order = 2;

        if ($isBevOrPersonal && empty($validated['dms_so'])) {
            $booking->order = 3;
        }

        if ($booking->pending === 0) {
            $booking->status = 1;
        }

        $booking->saveQuietly();

        $booking->addHistory(
            'commented',
            'Pending Order Processed',
            'DMS / SO details processed successfully',
            [
                'module' => 'Pending Order Verification',
                'dms_no' => $booking->dms_no,
                'dms_otf' => $booking->dms_otf,
                'dms_so' => $booking->dms_so,
                'status' => $booking->status,
                'order_status' => $booking->order,
            ],
            null,
            backpack_user()
        );

        return $booking;
    }

    private function isBevOrPersonal(mixed $segmentCode): bool
    {
        return in_array((int) ($segmentCode ?? 0), self::BEV_OR_PERSONAL_SEGMENTS);
    }
}
