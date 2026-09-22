<?php

namespace App\Services\Sales\Booking;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\CRM\QuoteAction;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\XExchange;
use App\Models\Module\Booking\XL_DSA_MASTER;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Finance\XFinance;
use App\Models\Module\Insurance\XlInsurance;
use App\Services\OrgService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Business logic for the Booking Core CRUD sub-domain (store()/update()),
 * extracted from BookingCrudController as part of the Sales-system
 * refactor (Phase 4, ninth and final sub-domain) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md. This is the largest and
 * most cross-cutting sub-domain, done last per the plan since every
 * other sub-domain's write path also touches pieces of what store()/
 * update() do inline (XExchange seeding, XFinance creation, etc.).
 *
 * getFullBookingData() (the ~1,480-line private read-only view-data
 * builder used by show()/rejectedView()/etc.) is deliberately NOT
 * covered here - it's shared display infrastructure, not store/update
 * business logic, and out of scope for this specific sub-domain.
 */
class BookingCoreService
{
    /**
     * Applies a validated new-booking submission: creates the Booking row,
     * converts a linked Quotation (status, QuoteAction history, seeded
     * Insurance/RTO rows), syncs the linked/new Enquiry, records history,
     * handles the optional amount-proof upload, creates the initial
     * Bookingamount receipt row, and seeds XExchange/XFinance when
     * applicable. Returns the created Booking.
     *
     * BUG-105 (known-bugs-report.md): fixed as part of this extraction -
     * the original's RTO seed read an undefined $quotationData variable
     * (should have been $quotation->standard_data, matching the adjacent
     * Insurance seed's correct pattern) - see the method body below.
     *
     * @param  array<string, mixed>  $input  the raw request payload (all fields store() reads)
     */
    public function store(array $input, ?UploadedFile $amountProof): Booking
    {
        $pending = 0;
        $pendingFields = [];

        $isDummy = ($input['customertype'] ?? null) === 'Dummy';

        if (! $isDummy) {
            if (empty($input['receiptno'] ?? null)) {
                $pending++;
                $pendingFields[] = 'Receipt number needs to be updated';
            }
            if (empty($input['hiddenreceiptdate'] ?? null)) {
                $pending++;
                $pendingFields[] = 'Receipt date needs to be updated';
            }
            if (($input['bookingmode'] ?? null) === 'Online') {
                if (empty($input['refrenceno'] ?? null)) {
                    $pending++;
                    $pendingFields[] = 'Online booking reference number needs to be updated';
                }
            }
            if (empty($input['panno'] ?? null)) {
                $pending++;
                $pendingFields[] = 'PAN number needs to be updated';
            }
            if (empty($input['adharno'] ?? null)) {
                $pending++;
                $pendingFields[] = 'Aadhar number needs to be updated';
            }
            if (empty($input['dmsno'] ?? null)) {
                $pending++;
                $pendingFields[] = 'Sales force number needs to be updated';
            }
            if (empty($input['dmsotf'] ?? null)) {
                $pending++;
                $pendingFields[] = 'DMS OTF needs to be updated';
            }
            if (empty($input['hiddenotfdate'] ?? null)) {
                $pending++;
                $pendingFields[] = 'DMS OTF Date needs to be updated';
            }
            if (($input['makeorder'] ?? null) == 1) {
                $pending++;
                $pendingFields[] = 'DMS SO number needs to be updated';
            }
        }

        $customerTypeInput = $input['customertype'] ?? null;
        $adharNoNormalized = preg_replace('/[^0-9]/', '', $input['adharno'] ?? '');
        $customerType = in_array($customerTypeInput, ['Actual', 'Active'], true) ? 'Active' : $customerTypeInput;

        $booking = new Booking;
        $quotation = null;

        if (! empty($input['quotation_no'] ?? null)) {
            $quotation = Quotation::where('id', $input['quotation_no'])->first();
        }

        if ($quotation) {
            $booking->enq_no = $quotation->enquiry_id;
            $booking->quotation_id = $quotation->id;
        } elseif (! empty($input['enquiry_id'] ?? null)) {
            $booking->enq_no = $input['enquiry_id'];
        } else {
            $booking->enq_no = null;
        }

        $booking->b_type = $customerType;
        $booking->b_cat = $input['customercat'] ?? null;
        $booking->b_mode = $input['bookingmode'] ?? null;
        $booking->cpd = $input['hiddencpd'] ?? null;
        $booking->col_type = $isDummy ? 1 : ($input['coltype'] ?? 1);
        $booking->col_by = $input['user'] ?? null;
        $booking->b_source = $input['bookingsource'] ?? null;
        $booking->sale_type = $input['sale_type'] ?? null;
        $booking->dsa_id = $input['dsadetails'] ?? null;
        $booking->online_bk_ref_no = $input['refrenceno'] ?? null;
        $booking->booking_date = $input['hiddenbookingdate'] ?? null;
        $booking->receipt_no = $input['receiptno'] ?? null;
        $booking->receipt_date = $input['hiddenreceiptdate'] ?? null;
        $booking->booking_amount = $isDummy ? 0 : ($input['bookingamount'] ?? null);
        $booking->payment_mode = $input['mode'] ?? null;
        $booking->order = $input['makeorder'] ?? null;

        $booking->pan_no = $input['panno'] ?? null;
        $booking->adhar_no = $adharNoNormalized;
        $booking->gstn = $input['gstn'] ?? null;
        $booking->dms_otf = $input['dmsotf'] ?? null;
        $booking->dms_so = $input['dms_so'] ?? null;
        $booking->dms_no = $input['dmsno'] ?? null;
        $booking->otf_date = $input['hiddenotfdate'] ?? null;
        $booking->mapped = 0;
        $booking->chassis_no = $input['chassis'] ?? null;
        $booking->del_type = $input['deliverytype'] ?? null;
        $booking->del_date = $input['hiddenexpecteddeldate'] ?? null;

        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);

            if ($linkedEnquiry) {
                $linkedEnquiry->update([
                    'name' => $input['name'] ?? null,
                    'care_of_type' => $input['careof'] ?? null,
                    'care_of' => $input['careofname'] ?? null,
                    'mobile' => $input['mobile'] ?? null,
                    'alternate_mobile' => $input['altmobile'] ?? null,
                    'gender' => $input['gender'] ?? null,
                    'occupation_type' => $input['occupation'] ?? null,
                    'dob' => $input['hiddencustomerdob'] ?? null,
                    'dealer_branch' => $input['branch'] ?? null,
                    'dealer_location' => $input['location'] ?? null,
                    'segment_code' => $input['segment'] ?? null,
                    'model_code' => $input['model'] ?? null,
                    'variant_code' => $input['variant'] ?? null,
                    'color_code' => $input['color'] ?? null,
                    'purchase_type' => $input['buyertype'] ?? null,
                    'brand_make' => $input['enummaster1'] ?? null,
                    'brand_model' => $input['vehicledetails'] ?? null,
                    'consid_brand2' => $input['enummaster2'] ?? null,
                    'consid_model2' => $input['vehicledetails2'] ?? null,
                    'vehicle_no' => $input['registrationno'] ?? null,
                    'make_year' => $input['manufacturingyear'] ?? null,
                    'odo_reading' => $input['odometerreading'] ?? null,
                    'expected_price' => $input['expectedprice'] ?? null,
                    'offered_price' => $input['offeredprice'] ?? null,
                    'exchange_bonus' => $input['exchangebonus'] ?? null,
                    'fin_mode' => $isDummy ? 'Dummy' : ($input['finmode'] ?? null),
                    'financier' => $isDummy ? null : ($input['financier'] ?? null),
                    'x8_sc_code' => $input['saleconsultant'] ?? null,
                    'referee_name' => $input['refcustomername'] ?? null,
                    'referee_phone' => $input['refmobileno'] ?? null,
                    'referred_by' => $input['referredby'] ?? null,
                    'remarks' => $input['details'] ?? null,
                ]);
            }
        }

        $booking->pending = $pending;
        $booking->pending_remark = implode(' , ', $pendingFields);

        if (! $isDummy && $pending > 0) {
            $booking->status = 8;
            Log::info('⚠️ [BOOKING] Status set to 8 (Pending)');
        } else {
            $booking->status = 1;
        }

        if ($customerType === 'Dummy') {
            $booking->b_mode = 'Dealer';
            $booking->b_source = 'Dealer';
        }

        try {
            $booking->save();

            if ($quotation) {
                $quotation->status = 'booked';
                $quotation->save();

                QuoteAction::create([
                    'quotation_no' => $quotation->quotation_no,
                    'action_by' => backpack_user()->id,
                    'action' => 'BOOKED',
                    'requested' => $quotation->standard_data,
                    'onroad' => $quotation->onroad_price,
                    'status' => 'booked',
                    'remarks' => 'Converted into Booking #'.$booking->id,
                ]);

                XlInsurance::updateOrCreate(
                    ['bid' => $booking->id],
                    ['pol_type' => $quotation->standard_data['policy_type'] ?? null]
                );

                XlRto::updateOrCreate(
                    ['bid' => $booking->id],
                    ['rgn_type' => $quotation->standard_data['registration_type'] ?? null]
                );
            }

            try {
                $booking->addHistory(
                    'commented',
                    'Booking Created',
                    'New booking created successfully',
                    [
                        'booking_amount' => $booking->booking_amount,
                        'customer_name' => $booking->name,
                        'mobile' => $booking->mobile,
                    ],
                    null,
                    backpack_user()
                );

                if ($isDummy) {
                    $booking->addHistory(
                        'commented',
                        'Dummy Entry Created',
                        'Dummy booking created successfully',
                        ['remark' => $input['details'] ?? null],
                        null,
                        backpack_user()
                    );
                }
            } catch (Exception $e) {
                Log::error('💥 [HISTORY] Failed', ['message' => $e->getMessage()]);
            }
        } catch (Exception $e) {
            dd($e->getMessage(), $e->getFile(), $e->getLine());
        }

        $uploadedFilePath = null;

        if ($amountProof && $amountProof->isValid()) {
            try {
                $storedName = $amountProof->store('temp', 'public');
                $uploadedFilePath = public_path('storage/'.$storedName);

                if (! file_exists($uploadedFilePath)) {
                    throw new Exception('Stored file not found at: '.$uploadedFilePath);
                }
            } catch (Exception $e) {
                Log::error('💥 [FILE] File upload block threw exception', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $uploadedFilePath = null;
            }
        }

        $number = $input['receiptno'] ?? $input['voucherno'] ?? null;

        if (
            ! $isDummy
            && in_array($booking->col_type, [1, 4])
            && $booking->booking_amount > 0
            && $number
        ) {
            try {
                $payment = new Bookingamount;
                $payment->bid = $booking->id;
                $payment->date = $input['hiddenreceiptdate'] ?? now();
                $payment->amount = $booking->booking_amount;
                $payment->type_number = $number;
                $payment->mode = $input['mode'] ?? null;
                $payment->voucher = ($booking->col_type == 4) ? 1 : 0;
                $payment->save();

                if ($uploadedFilePath && file_exists($uploadedFilePath)) {
                    $payment->addMedia($uploadedFilePath)->toMediaCollection('amount-proof');
                }
            } catch (Exception $e) {
                Log::error('[STORE] Bookingamount save/media failed', [
                    'booking_id' => $booking->id,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        if (! $isDummy && ($input['buyertype'] ?? null) === 'Exchange Buy') {
            try {
                XExchange::seedForBooking($booking->id, $input['buyertype']);
            } catch (Exception $e) {
                Log::error('[STORE] XExchange save failed', ['booking_id' => $booking->id, 'message' => $e->getMessage()]);
            }
        }

        if (! $isDummy && ($input['finmode'] ?? null) === 'In-house') {
            try {
                $finance = new XFinance;
                $finance->bid = $booking->id;
                $finance->fin_mode = $input['finmode'];
                $finance->financier = $input['financier'] ?? null;
                $finance->loan_status = ($input['loanstatus'] ?? null) ?: 'Pending';
                $finance->verification_status = 1;
                $finance->case_status = 1;
                $finance->save();
            } catch (Exception $e) {
                Log::error('[STORE] XFinance save failed', [
                    'booking_id' => $booking->id ?? null,
                    'finmode' => $input['finmode'] ?? null,
                    'financier' => $input['financier'] ?? null,
                    'loanstatus' => $input['loanstatus'] ?? null,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                throw $e;
            }
        }

        return $booking;
    }

    /**
     * Applies a validated booking-edit submission: diffs every field
     * against its current value to build a human-readable change log,
     * updates the Booking's own columns and the linked Enquiry's fields,
     * seeds an XExchange row on first transition to "Exchange Buy", and
     * upserts the Finance record with the same "don't null out a
     * disabled field" semantics the original preserved. Returns the
     * saved Booking.
     *
     * @param  array<string, mixed>  $input  the raw request payload (all fields update() reads)
     */
    public function update(Booking $booking, array $input): Booking
    {
        $oldColType = $booking->col_type;
        $oldColBy = $booking->col_by;

        $pending = 0;
        $pendingFields = [];

        if (($input['booking_mode'] ?? null) === 'Online' && empty($input['refrence_no'] ?? null)) {
            $pending++;
            $pendingFields[] = 'Online booking reference number needs to be updated';
        }
        if (empty($input['pan_no'] ?? null)) {
            $pending++;
            $pendingFields[] = 'PAN number needs to be updated';
        }
        if (empty($input['adhar_no'] ?? null)) {
            $pending++;
            $pendingFields[] = 'Aadhar number needs to be updated';
        }

        $adharNoNormalized = preg_replace('/[^0-9]/', '', $input['adhar_no'] ?? '');

        $allUsers = OrgService::usersByDepartment('SLS') ?? [];
        $dsaDetails = XL_DSA_MASTER::all()->map(fn ($dsa) => [
            'id' => $dsa->id,
            'name' => $dsa->name,
            'mobile' => $dsa->mobile,
        ])->toArray();
        $saleConsultants = OrgService::usersByDesignation('CNS') ?? [];

        $getFinancierName = function ($fid) {
            if (empty($fid)) {
                return 'Null';
            }
            $f = XlFinancier::find($fid);

            return $f ? $f->name : 'Unknown';
        };

        $rem = [];

        $linkedEnquiry = null;
        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        if ($booking->b_type != ($input['customer_type'] ?? null)) {
            $rem[] = 'Customer Type Changed from '.($booking->b_type ?? 'null').' to '.($input['customer_type'] ?? null);
            $booking->b_type = $input['customer_type'] ?? null;
        }

        if ($booking->booking_date != ($input['booking_date_actual'] ?? null)) {
            $oldDate = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d-M-Y') : 'null';
            $newDate = ($input['booking_date_actual'] ?? null) ? Carbon::parse($input['booking_date_actual'])->format('d-M-Y') : 'null';
            $rem[] = "Booking Date Changed from {$oldDate} to {$newDate}";
            $booking->booking_date = $input['booking_date_actual'] ?? null;
        }

        if ($booking->booking_amount != ($input['booking_amount'] ?? null)) {
            $rem[] = 'Booking Amount Changed from '.($booking->booking_amount ?? '0').' to '.($input['booking_amount'] ?? null);
            $booking->booking_amount = $input['booking_amount'] ?? null;
        }

        if ($booking->receipt_no != ($input['receipt_no'] ?? null)) {
            $rem[] = 'Receipt No. Changed from '.($booking->receipt_no ?? 'null').' to '.($input['receipt_no'] ?? null);
            $booking->receipt_no = $input['receipt_no'] ?? null;
        }

        if ($booking->receipt_date != ($input['receipt_date_actual'] ?? null)) {
            $rem[] = 'Receipt Date Changed';
            $booking->receipt_date = $input['receipt_date_actual'] ?? null;
        }

        if (! empty($input['mode'] ?? null)) {
            if ($booking->payment_mode != $input['mode']) {
                $rem[] = 'Payment Mode Changed from '.($booking->payment_mode ?? 'null').' to '.$input['mode'];
                $booking->payment_mode = $input['mode'];
            }
        }

        if ($oldColType != ($input['col_type'] ?? null)) {
            $colTypeMap = ['1' => 'Receipt', '2' => 'Field Collection By Sales Team', '3' => 'Field Collection By DSA', '4' => 'Used Car Purchase'];
            $rem[] = 'Collection Type Changed from '.($colTypeMap[$oldColType] ?? 'null').' to '.($colTypeMap[$input['col_type'] ?? null] ?? 'null');
        }

        if ($oldColBy != ($input['user'] ?? null)) {
            $oldUser = 'null';
            $newUser = 'null';

            if ($oldColType == 2) {
                $u = collect($allUsers)->firstWhere('id', $oldColBy);
                $oldUser = $u ? ($u['name'] ?? $u->name).' - ('.($u['emp_code'] ?? $u->emp_code ?? '').')' : 'null';
            } elseif ($oldColType == 3) {
                $d = collect($dsaDetails)->firstWhere('id', $oldColBy);
                $oldUser = $d ? $d['name'].' - '.$d['mobile'] : 'null';
            }

            if (($input['col_type'] ?? null) == 2) {
                $u = collect($allUsers)->firstWhere('id', $input['user'] ?? null);
                $newUser = $u ? ($u['name'] ?? $u->name).' - ('.($u['emp_code'] ?? $u->emp_code ?? '').')' : 'null';
            } elseif (($input['col_type'] ?? null) == 3) {
                $d = collect($dsaDetails)->firstWhere('id', $input['user'] ?? null);
                $newUser = $d ? $d['name'].' - '.$d['mobile'] : 'null';
            }

            $rem[] = "Collected By Changed from {$oldUser} to {$newUser}";
        }

        if ($linkedEnquiry && $linkedEnquiry->name != ($input['name'] ?? null)) {
            $rem[] = 'Name Changed from '.$linkedEnquiry->name.' to '.($input['name'] ?? null);
            $linkedEnquiry->name = $input['name'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->care_of_type != ($input['care_of'] ?? null)) {
            $rem[] = 'Care Of Type Changed';
            $linkedEnquiry->care_of_type = $input['care_of'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->care_of != ($input['care_of_name'] ?? null)) {
            $rem[] = 'Care Of Changed from '.($linkedEnquiry->care_of ?? 'None').' to '.(($input['care_of_name'] ?? null) ?? 'None');
            $linkedEnquiry->care_of = $input['care_of_name'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->mobile != ($input['mobile'] ?? null)) {
            $rem[] = 'Mobile Changed from '.$linkedEnquiry->mobile.' to '.($input['mobile'] ?? null);
            $linkedEnquiry->mobile = $input['mobile'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->alternate_mobile != ($input['alt_mobile'] ?? null)) {
            $rem[] = 'Alt Mobile Changed from '.($linkedEnquiry->alternate_mobile ?? '0').' to '.($input['alt_mobile'] ?? null);
            $linkedEnquiry->alternate_mobile = $input['alt_mobile'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->gender != ($input['gender'] ?? null)) {
            $rem[] = 'Gender Changed from '.($linkedEnquiry->gender ?? 'null').' to '.($input['gender'] ?? null);
            $linkedEnquiry->gender = $input['gender'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->occupation_type != ($input['occupation'] ?? null)) {
            $rem[] = 'Occupation Changed from '.($linkedEnquiry->occupation_type ?? 'null').' to '.($input['occupation'] ?? null);
            $linkedEnquiry->occupation_type = $input['occupation'] ?? null;
        }

        if ($booking->pan_no != ($input['pan_no'] ?? null)) {
            $rem[] = 'PAN No. Changed from '.($booking->pan_no ?? '0').' to '.($input['pan_no'] ?? null);
            $booking->pan_no = $input['pan_no'] ?? null;
        }

        if ($booking->adhar_no != $adharNoNormalized) {
            $rem[] = 'Aadhar No. Changed from '.($booking->adhar_no ?? '0').' to '.$adharNoNormalized;
            $booking->adhar_no = $adharNoNormalized;
        }

        $gstValue = array_key_exists('gst_unregistered', $input) ? '0' : ($input['gstn'] ?? null);
        if ($booking->gstn != $gstValue) {
            $rem[] = 'GSTN Changed';
            $booking->gstn = $gstValue;
        }

        if ($booking->sale_type != ($input['sale_type'] ?? null)) {
            $rem[] = 'Sale Type Changed from '.($booking->sale_type ?? 'null').' to '.($input['sale_type'] ?? null);
            $booking->sale_type = $input['sale_type'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->dob != ($input['hidden_customer_dob'] ?? null)) {
            $oldDob = $linkedEnquiry->dob ? Carbon::parse($linkedEnquiry->dob)->format('d-M-Y') : 'null';
            $newDob = ($input['hidden_customer_dob'] ?? null) ? Carbon::parse($input['hidden_customer_dob'])->format('d-M-Y') : 'null';
            $rem[] = "Customer D.O.B. Changed from {$oldDob} to {$newDob}";
            $linkedEnquiry->dob = $input['hidden_customer_dob'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->dealer_branch != ($input['branch'] ?? null)) {
            $rem[] = 'Branch Changed from '.($linkedEnquiry->dealer_branch ?? 'null').' to '.($input['branch'] ?? null);
            $linkedEnquiry->dealer_branch = $input['branch'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->dealer_location != ($input['location_id'] ?? null)) {
            $rem[] = 'Location Changed from '.($linkedEnquiry->dealer_location ?? 'null').' to '.($input['location_id'] ?? null);
            $linkedEnquiry->dealer_location = $input['location_id'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->segment_code != ($input['segment_id'] ?? null)) {
            $rem[] = 'Segment Changed from '.($linkedEnquiry->segment_code ?? 'null').' to '.($input['segment_id'] ?? null);
            $linkedEnquiry->segment_code = $input['segment_id'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->model_code != ($input['model'] ?? null)) {
            $rem[] = 'Model Changed from '.($linkedEnquiry->model_code ?? 'null').' to '.($input['model'] ?? null);
            $linkedEnquiry->model_code = $input['model'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->variant_code != ($input['variant'] ?? null)) {
            $rem[] = 'Variant Changed from '.($linkedEnquiry->variant_code ?? 'null').' to '.($input['variant'] ?? null);
            $linkedEnquiry->variant_code = $input['variant'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->color_code != ($input['color'] ?? null)) {
            $rem[] = 'Color Changed from '.($linkedEnquiry->color_code ?? 'null').' to '.($input['color'] ?? null);
            $linkedEnquiry->color_code = $input['color'] ?? null;
        }

        if ($booking->chassis_no != ($input['chassis'] ?? null)) {
            $rem[] = 'Chassis No. Changed from '.($booking->chassis_no ?? 'null').' to '.(($input['chassis'] ?? null) ?? 'null');
            $booking->chassis_no = $input['chassis'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->purchase_type_crm != ($input['buyer_type'] ?? null)) {
            $rem[] = 'Buyer Type Changed from '.($linkedEnquiry->purchase_type_crm ?? 'null').' to '.($input['buyer_type'] ?? null);
            $linkedEnquiry->purchase_type_crm = $input['buyer_type'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->brand_make != ($input['enummaster1'] ?? null)) {
            $rem[] = 'Brand (Make 1) Changed';
            $linkedEnquiry->brand_make = $input['enummaster1'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->brand_model != ($input['vehicle_details'] ?? null)) {
            $rem[] = 'Model & Variant 1 Changed';
            $linkedEnquiry->brand_model = $input['vehicle_details'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->consid_brand2 != ($input['enummaster2'] ?? null)) {
            $rem[] = 'Brand (Make 2) Changed';
            $linkedEnquiry->consid_brand2 = $input['enummaster2'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->consid_model2 != ($input['vehicle_details2'] ?? null)) {
            $rem[] = 'Model & Variant 2 Changed';
            $linkedEnquiry->consid_model2 = $input['vehicle_details2'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->vehicle_no != ($input['registration_no'] ?? null)) {
            $rem[] = 'Vehicle Registration No. Changed';
            $linkedEnquiry->vehicle_no = $input['registration_no'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->make_year != ($input['manufacturing_year'] ?? null)) {
            $rem[] = 'Manufacturing Year Changed';
            $linkedEnquiry->make_year = $input['manufacturing_year'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->odo_reading != ($input['odometer_reading'] ?? null)) {
            $rem[] = 'Odometer Reading Changed';
            $linkedEnquiry->odo_reading = $input['odometer_reading'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->expected_price != ($input['expected_price'] ?? null)) {
            $rem[] = 'Expected Price Changed';
            $linkedEnquiry->expected_price = $input['expected_price'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->offered_price != ($input['offered_price'] ?? null)) {
            $rem[] = 'Offered Price Changed';
            $linkedEnquiry->offered_price = $input['offered_price'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->exchange_bonus != ($input['exchange_bonus'] ?? null)) {
            $rem[] = 'Exchange Bonus Changed';
            $linkedEnquiry->exchange_bonus = $input['exchange_bonus'] ?? null;
        }

        if ($booking->b_mode != ($input['booking_mode'] ?? null)) {
            $rem[] = 'Booking Mode Changed from '.($booking->b_mode ?? 'null').' to '.($input['booking_mode'] ?? null);
            $booking->b_mode = $input['booking_mode'] ?? null;
        }

        if ($booking->online_bk_ref_no != ($input['refrence_no'] ?? null)) {
            $rem[] = 'Online Ref No. Changed from '.($booking->online_bk_ref_no ?? 'null').' to '.($input['refrence_no'] ?? null);
            $booking->online_bk_ref_no = $input['refrence_no'] ?? null;
        }

        if ($booking->b_source != ($input['booking_source'] ?? null)) {
            $rem[] = 'Booking Source Changed from '.($booking->b_source ?? 'null').' to '.($input['booking_source'] ?? null);
            $booking->b_source = $input['booking_source'] ?? null;
        }

        if ($booking->dsa_id != ($input['dsa_details'] ?? null)) {
            $rem[] = 'DSA Changed';
            $booking->dsa_id = $input['dsa_details'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->x8_sc_code != ($input['saleconsultant'] ?? null)) {
            $oldC = collect($saleConsultants)->firstWhere('id', $linkedEnquiry->x8_sc_code);
            $newC = collect($saleConsultants)->firstWhere('id', $input['saleconsultant'] ?? null);
            $oldName = is_array($oldC) ? ($oldC['name'] ?? 'null') : ($oldC->name ?? 'null');
            $newName = is_array($newC) ? ($newC['name'] ?? 'null') : ($newC->name ?? 'null');
            $rem[] = "Sale Consultant Changed from {$oldName} to {$newName}";
            $linkedEnquiry->x8_sc_code = $input['saleconsultant'] ?? null;
        }

        if ($booking->del_type != ($input['delivery_type'] ?? null)) {
            $rem[] = 'Delivery Type Changed from '.($booking->del_type ?? 'null').' to '.($input['delivery_type'] ?? null);
            $booking->del_type = $input['delivery_type'] ?? null;
        }

        if ($booking->del_date != ($input['expected_del_date_actual'] ?? null)) {
            $oldDate = $booking->del_date ? Carbon::parse($booking->del_date)->format('d-M-Y') : 'null';
            $newDate = ($input['expected_del_date_actual'] ?? null) ? Carbon::parse($input['expected_del_date_actual'])->format('d-M-Y') : 'null';
            $rem[] = "Delivery Date Changed from {$oldDate} to {$newDate}";
            $booking->del_date = $input['expected_del_date_actual'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->fin_mode != ($input['fin_mode'] ?? null)) {
            $rem[] = 'Finance Mode Changed from '.($linkedEnquiry->fin_mode ?? 'null').' to '.($input['fin_mode'] ?? null);
            $linkedEnquiry->fin_mode = $input['fin_mode'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->financier != ($input['financier'] ?? null)) {
            $rem[] = 'Financier Changed from '.$getFinancierName($linkedEnquiry->financier).' to '.$getFinancierName($input['financier'] ?? null);
            $linkedEnquiry->financier = $input['financier'] ?? null;
        }

        $newOrder = ($input['make_order'] ?? null) ? 1 : 0;
        if ($booking->order != $newOrder) {
            $rem[] = $newOrder == 1 ? 'Requested to create Sales Order' : 'Cancelled request for Sales Order';
            $booking->order = $newOrder;
        }

        if ($linkedEnquiry && $linkedEnquiry->referee_name != ($input['ref_customer_name'] ?? null)) {
            $rem[] = 'Referred Name Changed';
            $linkedEnquiry->referee_name = $input['ref_customer_name'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->referee_phone != ($input['ref_mobile_no'] ?? null)) {
            $rem[] = 'Referred Mobile Changed';
            $linkedEnquiry->referee_phone = $input['ref_mobile_no'] ?? null;
        }

        if ($linkedEnquiry && $linkedEnquiry->remarks != ($input['details'] ?? null)) {
            $rem[] = 'Remarks Changed';
            $linkedEnquiry->remarks = $input['details'] ?? null;
        }

        if ($linkedEnquiry) {
            $linkedEnquiry->save();
        }

        $booking->col_type = $input['col_type'] ?? null;
        $booking->col_by = $input['user'] ?? null;
        $booking->pending = $pending;

        if (! empty($pendingFields)) {
            $booking->pending_remark = implode(' , ', $pendingFields);
        }
        if ($pending > 0) {
            $booking->status = 8;
        }

        $booking->save();

        if (($input['buyer_type'] ?? null) === 'Exchange Buy') {
            if (! XExchange::where('bid', $booking->id)->exists()) {
                XExchange::seedForBooking($booking->id, $input['buyer_type']);
                $rem[] = 'New exchange entry created';
            }
        }

        $oldFinance = XFinance::where('bid', $booking->id)->first();
        $oldFinMode = $oldFinance?->fin_mode;
        $oldFinancier = $oldFinance?->financier;
        $oldLoanStatus = $oldFinance?->loan_status;

        $newFinMode = trim((string) ($input['fin_mode'] ?? ''));
        $newFinancier = $input['financier'] ?? null;
        $newLoanStatus = trim((string) ($input['loan_status'] ?? ''));

        if ($newFinMode === 'In-house') {
            $finance = XFinance::firstOrNew(['bid' => $booking->id]);
            $finance->fin_mode = 'In-house';
            $finance->financier = ! empty($newFinancier) ? $newFinancier : null;

            if ($newLoanStatus !== '') {
                $finance->loan_status = $newLoanStatus;
            } elseif (! $finance->exists) {
                $finance->loan_status = 'Pending';
            } elseif (empty($finance->loan_status)) {
                $finance->loan_status = 'Pending';
            }

            if (! $finance->exists) {
                $finance->verification_status = 1;
                $finance->case_status = 1;
            }

            $finance->save();

            if ($oldFinMode !== $finance->fin_mode) {
                $rem[] = 'Finance Mode Changed from '.($oldFinMode ?? 'null').' to '.$finance->fin_mode;
            }

            if ((string) $oldFinancier !== (string) $finance->financier) {
                $rem[] = 'Financier Changed from '.$getFinancierName($oldFinancier).' to '.$getFinancierName($finance->financier);
            }

            if ($oldLoanStatus !== $finance->loan_status) {
                $rem[] = 'Loan File Status Changed from '.($oldLoanStatus ?? 'null').' to '.($finance->loan_status ?? 'null');
            }
        } else {
            if ($oldFinMode === 'In-house') {
                $rem[] = 'Finance Mode Changed from In-house to '.($newFinMode ?: 'null');
            }

            if ($oldFinance) {
                $oldFinance->fin_mode = $newFinMode ?: null;
                $oldFinance->save();
            }
        }

        if (! empty($rem)) {
            $booking->addHistory(
                'commented',
                'Booking Updated',
                ($input['details'] ?? null).' | '.implode(' , ', $rem),
                [],
                null,
                backpack_user()
            );
        }

        return $booking;
    }
}
