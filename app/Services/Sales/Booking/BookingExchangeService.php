<?php

namespace App\Services\Sales\Booking;

use App\Helpers\CommonHelper;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Stock;
use App\Models\Module\Booking\Xessories;
use App\Models\Module\Booking\XExchange;
use App\Models\Module\Booking\Xl_DSA_Master;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Module\Finance\XFinance;
use App\Models\User;
use App\Models\Vehicle\Segment;
use App\Services\OrgService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the Booking Exchange/Scrappage sub-domain
 * (exchangeEdit()/exchangeUpdate(), covering both "Exchange Buy" and
 * "Scrappage" buyer types), extracted from BookingCrudController as part
 * of the Sales-system refactor (Phase 4) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingExchangeService
{
    private const VERIFICATION_STATUS_MAP = [
        1 => 'Unverified',
        2 => 'Verified (Data Match)',
        3 => 'Verified (Data Mismatch)',
    ];

    private const CASE_STATUS_MAP = [
        1 => 'In-Process',
        2 => 'Exchange Done',
        3 => 'Case Lost',
    ];

    /**
     * Resolves the display data the Exchange/Scrappage edit form needs,
     * merging the linked Enquiry's purchase fields onto the booking (this
     * screen's Enquiry field set is the largest of any Phase 4 sub-domain
     * so far - buyer_type/prices/referee/address fields all live on
     * Enquiry, not Booking).
     *
     * @return array{exchange: ?XExchange, data: array<string, mixed>, dsaname: string, uid: mixed, bookingHistory: Collection}
     */
    public function resolveEditData(Booking $booking): array
    {
        $exchange = XExchange::where('bid', $booking->id)->first();

        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);

            if ($enquiry) {
                $booking->name = $enquiry->name;
                $booking->care_of = $enquiry->care_of;
                $booking->care_of_type = $enquiry->care_of_type;
                $booking->mobile = $enquiry->mobile;
                $booking->alt_mobile = $enquiry->alternate_mobile;
                $booking->gender = $enquiry->gender;
                $booking->occ = $enquiry->occupation_type;
                $booking->c_dob = $enquiry->dob;

                $booking->branch_code = $enquiry->dealer_branch;
                $booking->location_code = $enquiry->dealer_location;

                $booking->segment_code = $enquiry->segment_code;
                $booking->model_code = $enquiry->model_code;
                $booking->variant_code = $enquiry->variant_code;
                $booking->color_code = $enquiry->color_code;

                $booking->buyer_type = $enquiry->purchase_type;
                $booking->exist_oem1 = $enquiry->brand_make;
                $booking->exist_oem2 = $enquiry->consid_brand2;
                $booking->vh1_detail = $enquiry->brand_model;
                $booking->vh2_detail = $enquiry->consid_model2;

                $booking->registration_no = $enquiry->vehicle_no;
                $booking->make_year = $enquiry->make_year;
                $booking->odo_reading = $enquiry->odo_reading;

                $booking->expected_price = $enquiry->expected_price;
                $booking->offered_price = $enquiry->offered_price;
                $booking->exchange_bonus = $enquiry->exchange_bonus;

                $booking->r_name = $enquiry->referee_name;
                $booking->r_mobile = $enquiry->referee_phone;

                $booking->consultant = $enquiry->x8_sc_code ?? $enquiry->sc_code;

                $booking->pincode = $enquiry->zipcode;
                $booking->vpo = $enquiry->vpo;
                $booking->tehsil = $enquiry->tehsil;
                $booking->district = $enquiry->district;
                $booking->city = $enquiry->city;
                $booking->territory = $enquiry->territory;
            }
        }

        $financeRecord = XFinance::where('bid', $booking->id)->first();

        if ($financeRecord) {
            $booking->fin_mode = $financeRecord->fin_mode;
            $booking->financier = $financeRecord->financier;
            $booking->loan_status = $financeRecord->loan_status;
        }

        $data = [];
        $data['financier_name'] = 'N/A';

        if (! empty($booking->financier)) {
            $data['financier_name'] = XlFinancier::where('id', $booking->financier)
                ->value('name')
                ?? XlFinancier::where('short_name', $booking->financier)
                    ->value('name')
                ?? $booking->financier;
        }

        $uid = backpack_user()->id ?? null;

        $branchName = null;

        if (! empty($booking->branch_code)) {
            $branchName = Branch::where('code', $booking->branch_code)->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $booking->branch_code)->value('name');
            }
        }

        $data['branch'] = $branchName ?? 'N/A';
        $data['location'] = ! empty($booking->location_code)
            ? (
                Location::where('code', $booking->location_code)->value('name')
                ?? Location::find($booking->location_code)?->name
                ?? $booking->location_other
                ?? 'N/A'
            )
            : ($booking->location_other ?? 'N/A');

        $acc = explode(',', $booking->accessories ?? '');
        $accessoryNames = [];
        foreach ($acc as $a) {
            $a = trim($a);
            if ($a === '') {
                continue;
            }
            $accessory = Xessories::find($a);
            if ($accessory) {
                $accessoryNames[] = $accessory->item;
            }
        }

        $booking->segment_name = 'N/A';

        if ($booking->segment_code) {
            $booking->segment_name = Segment::where('code', $booking->segment_code)
                ->where('is_active', true)
                ->value('name')
                ?? 'N/A';
        }

        $data['accessories'] = ! empty($accessoryNames) ? implode(', ', $accessoryNames) : 'N/A';

        $chassis = Stock::find($booking->chassis_no);
        $data['bchasis'] = $chassis ? $chassis->chassis_no : 'N/A';
        $data['segments'] = CommonHelper::getVehicleSegments();
        $data['remark'] = 0;
        $data['saleconsultants'] = OrgService::usersByDesignation('CNS') ?? [];

        $consultantCode = trim((string) ($booking->consultant ?? ''));
        $consultant = DB::table('xlr8_admin_person')
            ->where('person_code', $consultantCode)
            ->first();

        $data['consultant_name'] = $consultant?->display_name ?? 'N/A';
        $data['consultant_mile_id'] = $consultant?->employee_code ?? 'N/A';

        $drec = Xl_DSA_Master::find($booking->dsa_id);
        $dsaname = $drec ? $drec->name.'-'.$drec->mobile : 'N/A';

        $user = backpack_user();
        $collector = User::find($booking->col_by);
        $data['collector_name'] = $collector
            ? $collector->name.' - '.$collector->emp_code
            : 'N/A';

        $depts = explode(',', $user->department);
        foreach ($depts as $dept) {
            $deptValue = OrgService::getKeyValueById((int) $dept)?->value;

            if ($deptValue == 'SALES') {
                $data['remark'] = 1;
            }
            if ($deptValue == 'ACCOUNTS') {
                $data['remark'] = 2;
            }
        }

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';
        $data['enum_master'] = OrgService::getKeyValuesByCode('EXISTING_CAR_OEM') ?? collect();
        $data['oem_ids'] = explode(',', $booking->exist_oem);

        $bookingHistory = $booking->commMaster()
            ->with([
                'rootThreads' => function ($q) {
                    $q->orderByDesc('created_at')
                        ->with([
                            'children' => function ($child) {
                                $child->orderByDesc('created_at');
                            },
                            'children.actor',
                            'children.action',
                            'actor',
                            'action',
                            'media',
                        ]);
                },
            ])
            ->first()?->rootThreads ?? collect();

        return compact('exchange', 'data', 'dsaname', 'uid', 'bookingHistory');
    }

    /**
     * Applies validated Exchange/Scrappage data: syncs the purchase-type/
     * price/reg-no fields onto the linked Enquiry (that's where they
     * actually live, not on Booking), upserts the XExchange row, and
     * records booking history. Returns the saved XExchange plus a
     * human-readable list of what changed (for the caller's own log
     * entry - preserves the original method's \Log::info() call, which
     * stays in the controller since it's HTTP-request-flow diagnostics).
     *
     * @param  array<string, mixed>  $validated
     * @return array{exchange: XExchange, changes: array<int, string>}
     */
    public function apply(Booking $booking, array $validated): array
    {
        $linkedEnquiry = null;

        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        $rem = [];

        if (array_key_exists('remark', $validated)) {
            $rem[] = 'Remarks: '.$validated['remark'];
        }

        if ($linkedEnquiry) {
            $enquiryChanges = [];

            $enquiryFieldMap = [
                'purchase_type' => ['buyer_type', 'Buyer Type'],
                'brand_make' => ['enum_master1', 'Brand Make 1'],
                'brand_model' => ['vehicle_details', 'Model Variant 1'],
                'consid_brand2' => ['enum_master2', 'Brand Make 2'],
                'consid_model2' => ['vehicle_details2', 'Model Variant 2'],
                'vehicle_no' => ['registration_no', 'Registration No'],
                'make_year' => ['manufacturing_year', 'Manufacturing Year'],
                'odo_reading' => ['odometer_reading', 'Odometer'],
                'expected_price' => ['expected_price', 'Expected Price'],
                'offered_price' => ['offered_price', 'Offered Price'],
                'exchange_bonus' => ['exchange_bonus', 'Exchange Bonus'],
            ];

            foreach ($enquiryFieldMap as $enquiryField => [$inputField, $label]) {
                $newVal = $validated[$inputField] ?? null;

                if ($newVal != $linkedEnquiry->$enquiryField) {
                    $enquiryChanges[] = "{$label}: '".($linkedEnquiry->$enquiryField ?? 'null')."' → '{$newVal}'";
                    $linkedEnquiry->$enquiryField = $newVal;
                }
            }

            if (! empty($enquiryChanges)) {
                $linkedEnquiry->save();
                $rem = array_merge($rem, $enquiryChanges);
            }
        }

        $booking->save();

        $verificationStatus = $validated['update'];
        $caseStatus = $validated['case_status'];

        $exchangeEntry = XExchange::where('bid', $booking->id)->first();

        $exchangePayload = [
            'vh_id' => $validated['enum_master1'] ?? 0,
            'enum_master1' => $validated['enum_master1'] ?? null,
            'enum_master2' => $validated['enum_master2'] ?? null,
            'vehicle_details' => $validated['vehicle_details'] ?? null,
            'vehicle_details2' => $validated['vehicle_details2'] ?? null,
            'registration_no' => $validated['registration_no'] ?? null,
            'manufacturing_year' => $validated['manufacturing_year'] ?? null,
            'odometer_reading' => $validated['odometer_reading'] ?? null,
            'expected_price' => $validated['expected_price'] ?? null,
            'offered_price' => $validated['offered_price'] ?? null,
            'exchange_bonus' => $validated['exchange_bonus'] ?? null,
            'verification_status' => $verificationStatus,
            'case_status' => $caseStatus,
            'purchase_type' => $validated['buyer_type'],
        ];

        if (! $exchangeEntry) {
            $exchangePayload['bid'] = $booking->id;
            $exchangeEntry = XExchange::create($exchangePayload);
            $rem[] = 'New exchange entry created (Verification: '
                .(self::VERIFICATION_STATUS_MAP[$verificationStatus] ?? 'N/A')
                .', Case: '.(self::CASE_STATUS_MAP[$caseStatus] ?? 'N/A').')';
        } else {
            if ($exchangeEntry->verification_status != $verificationStatus) {
                $rem[] = "Verification Status: '"
                    .(self::VERIFICATION_STATUS_MAP[$exchangeEntry->verification_status] ?? 'null')
                    ."' → '".self::VERIFICATION_STATUS_MAP[$verificationStatus]."'";
            }
            if ($exchangeEntry->case_status != $caseStatus) {
                $rem[] = "Case Status: '"
                    .(self::CASE_STATUS_MAP[$exchangeEntry->case_status] ?? 'null')
                    ."' → '".self::CASE_STATUS_MAP[$caseStatus]."'";
            }
            $exchangeEntry->update($exchangePayload);
        }

        $title = 'Exchange Details Updated';
        $message = 'Exchange / Scrappage details updated.';

        if ($caseStatus == 2) {
            $title = ($validated['buyer_type'] === 'Scrappage') ? 'Scrappage Completed' : 'Exchange Completed';
            $message = ($validated['buyer_type'] === 'Scrappage') ? 'Scrappage process completed.' : 'Exchange process completed.';
        } elseif ($caseStatus == 3) {
            $title = 'Exchange Case Lost';
            $message = 'Exchange case marked as lost.';
        }

        if (! empty(trim($validated['remark'] ?? ''))) {
            $message .= ' Remarks: '.trim($validated['remark']);
        }

        $booking->addHistory(
            'commented',
            $title,
            $message,
            [
                'changes' => $rem,
                'buyer_type' => $validated['buyer_type'],
                'case_status' => $caseStatus,
            ],
            null,
            backpack_user()
        );

        return ['exchange' => $exchangeEntry, 'changes' => $rem];
    }
}
