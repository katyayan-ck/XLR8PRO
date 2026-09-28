<?php

namespace App\Services\Sales\Booking;

use App\Helpers\CommonHelper;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Stock;
use App\Models\Module\Booking\Xl_DSA_Master;
use App\Models\Module\Insurance\XlInsurance;
use App\Models\Module\Insurance\XlInsurer;
use App\Models\User;
use App\Models\Vehicle\Accessory;
use App\Services\OrgService;
use Illuminate\Http\UploadedFile;

/**
 * Business logic for the Booking Insurance sub-domain (Pending Insurance
 * list -> insEdit() -> insUpdate()), extracted from BookingCrudController
 * as part of the Sales-system refactor (Phase 4) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingInsuranceService
{
    /**
     * Resolves the display data the Insurance edit form needs: pulls
     * customer/vehicle context from the linked Enquiry onto $booking
     * (mutated in place, same load-bearing side effect as the other
     * Booking sub-domain services), and builds the full set of
     * segment/model/variant/color/branch/location/insurer/DSA/chassis/
     * accessory dropdown data the form uses.
     *
     * @return array{insurance: ?XlInsurance, data: array<string, mixed>, dsaname: string}
     */
    public function resolveEditData(Booking $booking): array
    {
        $insurance = XlInsurance::where('bid', $booking->id)->first();

        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);

            if ($enquiry) {
                $booking->name = $enquiry->name;
                $booking->care_of = $enquiry->care_of;
                $booking->care_of_type = $enquiry->care_of_type;
                $booking->mobile = $enquiry->mobile;
                $booking->alt_mobile = $enquiry->alternate_mobile;
                $booking->gender = $enquiry->gender;
                $booking->branch_code = $enquiry->dealer_branch;
                $booking->location_code = $enquiry->dealer_location;
                $booking->segment_code = $enquiry->segment_code;
                $booking->model_code = $enquiry->model_code;
                $booking->variant_code = $enquiry->variant_code;
                $booking->color_code = $enquiry->color_code;
                $booking->location_other = $enquiry->dealer_location_other ?? $booking->location_other;
            }
        }

        $data = [];
        $data['segments'] = CommonHelper::getVehicleSegments() ?? [];
        $data['models'] = CommonHelper::getVehicleModels($booking->segment_code ?? null) ?? [];
        $data['variants'] = CommonHelper::getVehicleVariants($booking->model_code ?? null) ?? [];
        $data['colors'] = CommonHelper::getVehicleColors($booking->variant_code ?? null) ?? [];
        $data['branches'] = CommonHelper::getBranches() ?? [];

        $locations = CommonHelper::getLocations($booking->branch_code) ?? [];
        usort($locations, fn ($a, $b) => strcmp(
            ($a['name'] ?? '').' - '.($a['code'] ?? ''),
            ($b['name'] ?? '').' - '.($b['code'] ?? '')
        ));
        $data['locations'] = $locations;

        $data['branch'] = Branch::where('code', $booking->branch_code)->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code
            ? Location::where('code', $booking->location_code)->value('name')
            : ($booking->location_other ?? 'N/A');
        $data['fbranch'] = $data['branch'];
        $data['flocation'] = $data['location'];

        $data['insurances'] = XlInsurer::select('id', 'name', 'short_name')->get()->toArray();
        $data['insurers'] = $data['insurances'];
        $data['allusers'] = OrgService::getUsers(deptCode: 'SLS');
        $data['saleconsultants'] = OrgService::salesConsultants();

        $stock = Stock::find($booking->chassis_no);

        if ($stock) {
            $data['bchasis'] = $stock->chassis_no;
            $data['chassis'] = Stock::where('model_code', $stock->model_code)
                ->select('chassis_no', 'id')
                ->get()
                ->toArray();
        } else {
            $data['bchasis'] = 'Not Available';
            $data['chassis'] = [];
        }

        $data['dsa_details'] = Xl_DSA_Master::all()
            ->map(fn ($dsa) => [
                'id' => $dsa->id,
                'name' => $dsa->name,
                'mobile' => $dsa->mobile,
                'email' => $dsa->email,
                'location' => $dsa->dlocation,
            ])->toArray();

        $collector = User::find($booking->col_by);
        $data['collector_name'] = $collector
            ? $collector->name.' - ('.($collector->emp_code ?? 'N/A').')'
            : 'N/A';

        $drec = Xl_DSA_Master::find($booking->dsa_id);
        $dsaname = $drec ? $drec->name.' - '.$drec->mobile : 'N/A';

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';
        $data['accessories_dropdown'] = Accessory::getAccessories(
            $booking->segment_code ?? '',
            $booking->model_code ?? '',
            $booking->variant_code ?? ''
        );
        $data['enum_master'] = OrgService::getKeyValuesByCode('EXISTING_CAR_OEM');

        return compact('insurance', 'data', 'dsaname');
    }

    /**
     * Applies validated insurance data: creates/updates the XlInsurance
     * row for the booking, sets status=2 only when every required field
     * plus a policy-copy file are present in this same submission
     * (status=1 otherwise), records the "Insurance Process Completed"
     * history entry on the booking (when it exists), and attaches the
     * policy-copy file if one was uploaded. Returns the saved XlInsurance.
     *
     * @param  array{insurance_category: int, insurance_company: int, policy_no: string, hidden_policy_date: string, policy_type: int}  $validated
     */
    public function apply(int $bookingId, array $validated, ?UploadedFile $policyCopy): XlInsurance
    {
        $status = $policyCopy ? 2 : 1;

        $data = [
            'bid' => $bookingId,
            'source' => $validated['insurance_category'],
            'insurer' => $validated['insurance_company'],
            'pol_no' => strtoupper($validated['policy_no']),
            'pol_date' => $validated['hidden_policy_date'],
            'pol_type' => $validated['policy_type'],
            'status' => $status,
            'updated_by' => backpack_auth()->id() ?? 1,
        ];

        $insurance = XlInsurance::updateOrCreate(['bid' => $bookingId], $data);

        $booking = Booking::find($bookingId);

        if ($booking) {
            $booking->addHistory(
                'commented',
                'Insurance Process Completed',
                'Insurance details updated successfully',
                [
                    'module' => 'Insurance',
                    'insurance_type' => $validated['insurance_category'],
                    'insurance_company' => $validated['insurance_company'],
                    'policy_no' => strtoupper($validated['policy_no']),
                    'policy_date' => $validated['hidden_policy_date'],
                    'policy_type' => $validated['policy_type'],
                    'status' => $status,
                ],
                null,
                backpack_user()
            );
        }

        if ($policyCopy && $policyCopy->isValid()) {
            $insurance->clearMediaCollection('policy_copy');
            $insurance->addMedia($policyCopy)
                ->usingFileName("policy_{$bookingId}_".time().'.pdf')
                ->toMediaCollection('policy_copy');
        }

        return $insurance;
    }
}
