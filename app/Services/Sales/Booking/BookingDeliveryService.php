<?php

namespace App\Services\Sales\Booking;

use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\XlDelivery;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Insurance\XlInsurance;
use App\Services\OrgService;
use Illuminate\Http\UploadedFile;

/**
 * Business logic for the Booking Delivery sub-domain (Pending Deliveries
 * list -> PendDeliveryEdit() -> PendDeliveryUpdate()), extracted from
 * BookingCrudController as part of the Sales-system refactor (Phase 4) -
 * see docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingDeliveryService
{
    /** Media collection names for the delivery-verification photo set. */
    public const PHOTO_COLLECTIONS = [
        'delivery_ceremony_with_customer',
        'bonnet',
        'windshield_glass',
        'vehicle_driver_side',
        'vehicle_co_driver_side',
        'vehicle_rear_side',
        'tire_front_driver_side',
        'tire_front_co_driver_side',
        'tire_rear_driver_side',
        'tire_rear_co_driver_side',
        'stepney',
        'foot_rest_driver_side',
        'foot_rest_co_driver_side',
        'tool_kit',
        'vehicle_chassis_no_photo',
        'chassis_no_screenshot_invoice',
        'chassis_no_screenshot_insurance',
    ];

    /**
     * Resolves the display data the Delivery edit form needs, mirroring
     * the other Booking sub-domain services' shape and the same
     * load-bearing $booking-mutation side effect.
     *
     * @return array{insurance: ?XlInsurance, rto: ?XlRto, data: array<string, mixed>}
     */
    public function resolveEditData(Booking $booking): array
    {
        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);

            if ($linkedEnquiry) {
                $booking->name = $linkedEnquiry->name;
                $booking->care_of = $linkedEnquiry->care_of;
                $booking->branch_code = $linkedEnquiry->dealer_branch ?? $booking->branch_code;
                $booking->location_code = $linkedEnquiry->dealer_location ?? $booking->location_code;
                $booking->location_other = $linkedEnquiry->dealer_location_other ?? $booking->location_other;
                $booking->model_code = $linkedEnquiry->model_code ?? $booking->model_code;
                $booking->variant_code = $linkedEnquiry->variant_code ?? $booking->variant_code;
                $booking->color_code = $linkedEnquiry->color_code ?? $booking->color_code;
            }
        }

        $branch = 'N/A';

        if (! empty($booking->branch_code)) {
            $branch = Branch::where('code', $booking->branch_code)->value('name');

            if (! $branch) {
                $branch = Branch::where('branch_code', $booking->branch_code)->value('name');
            }
        }

        $branch = $branch ?: 'N/A';

        $location = $booking->location_code
            ? (
                Location::where('code', $booking->location_code)->value('name')
                ?? Location::find($booking->location_code)?->name
                ?? $booking->location_other
                ?? 'N/A'
            )
            : ($booking->location_other ?? 'N/A');

        $data = [
            'segments' => OrgService::segments() ?? [],
            'saleconsultants' => OrgService::salesConsultants(),
            'branch' => $branch,
            'location' => $location,
            'financier' => XlFinancier::find($booking->financier)?->name ?? 'N/A',
            'bchasis' => $booking->chassis_no ?? 'N/A',
        ];

        return [
            'insurance' => XlInsurance::where('bid', $booking->id)->first(),
            'rto' => XlRto::where('bid', $booking->id)->first(),
            'data' => $data,
        ];
    }

    /**
     * Applies validated delivery data: creates/updates the XlDelivery row,
     * records the "Delivery Process Completed" history entry, and attaches
     * every provided verification photo to its own media collection.
     * Returns the saved XlDelivery.
     *
     * @param  array<string, ?UploadedFile>  $photos  keyed by one of self::PHOTO_COLLECTIONS
     */
    public function apply(int $bookingId, string $remarks, bool $chassisNoVerified, array $photos): XlDelivery
    {
        $delivery = XlDelivery::updateOrCreate(
            ['bid' => $bookingId],
            [
                'remarks' => $remarks,
                'verification' => $chassisNoVerified,
                'status' => 1,
                'created_by' => backpack_auth()->id() ?? 1,
                'updated_by' => backpack_auth()->id() ?? 1,
            ]
        );

        $booking = Booking::find($bookingId);

        if ($booking) {
            $booking->addHistory(
                'commented',
                'Delivery Process Completed',
                'Vehicle delivery verification completed successfully',
                [
                    'module' => 'Delivery',
                    'remarks' => $remarks,
                    'verification' => $chassisNoVerified,
                    'delivery_status' => 1,
                ],
                null,
                backpack_user()
            );
        }

        foreach (self::PHOTO_COLLECTIONS as $collection) {
            $file = $photos[$collection] ?? null;

            if ($file && $file->isValid()) {
                $delivery->clearMediaCollection($collection);
                $delivery->addMedia($file)->toMediaCollection($collection, 'public');
            }
        }

        return $delivery;
    }
}
