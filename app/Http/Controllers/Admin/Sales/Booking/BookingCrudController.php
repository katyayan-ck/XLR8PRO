<?php

namespace App\Http\Controllers\Admin\Sales\Booking;

use App\Helpers\CommonHelper;
use App\Http\Requests\BookingRequest;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\CRM\QuoteAction;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\Stock;
use App\Models\Module\Booking\Xessories;
use App\Models\Module\Booking\XExchange;
use App\Models\Module\Booking\XL_DSA_MASTER;
use App\Models\Module\Booking\Xl_Refunds;
use App\Models\Module\Booking\XlDelivery;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Booking\XlRtoRules;
use App\Models\Module\Finance\XFinance;
use App\Models\Module\Insurance\XlInsurance;
use App\Models\Module\Insurance\XlInsurer;
use App\Models\PinCodes;
use App\Models\User;
use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Rules\AadhaarNumber;
use App\Rules\ChassisNumber;
use App\Rules\DealerInvoiceNumber;
use App\Rules\DmsNumber;
use App\Rules\Gstin;
use App\Rules\InvoiceNumber;
use App\Rules\OtfNumber;
use App\Rules\PanNumber;
use App\Services\EnquiryReferenceService;
use App\Services\IdentifierService;
use App\Services\OrgService;
use App\Services\SystemSettingService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;

class BookingCrudController extends CrudController
{
    use CreateOperation {
        create as traitCreate;
    }
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use ShowOperation;
    use UpdateOperation {
        edit as traitEdit;
    }

    public function __construct(
        protected IdentifierService $identifiers,
        protected EnquiryReferenceService $enquiryRef,
    ) {
        parent::__construct();
    }

    /**
     * Overrides DeleteOperation/ListOperation/UpdateOperation trait defaults to add permission
     * gates. See known-bugs-report.md BUG-051: these were the only 4 reachable actions (out of
     * ~100) with zero Spatie-permission enforcement, same root cause as BUG-047 on Enquiry.
     */
    public function destroy($id)
    {
        if (! backpack_user()->can('SLS_BKNG_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('delete');

        $id = $this->crud->getCurrentEntryId() ?? $id;

        return $this->crud->delete($id);
    }

    public function edit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->traitEdit($id);
    }

    public function search()
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->traitShowDetailsRow($id);
    }

    public function setup()
    {
        CRUD::setModel(Booking::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/sales/booking');
        CRUD::setEntityNameStrings('booking', 'bookings');
    }

    public function create()
    {
        if (! backpack_user()->can('SLS_BKNG_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        if ($enquiryId = request('enquiry_id')) {
            $enquiry = Enquiry::find($enquiryId);
            if ($enquiry) {
                $existingBooking = Booking::where(function ($q) use ($enquiry) {
                    $q->where('enq_no', $enquiry->id)
                        ->orWhere('enq_no', $this->enquiryRef->toReference($enquiry->id));
                    if ($enquiry->enquiry_no) {
                        $q->orWhere('enq_no', $enquiry->enquiry_no);
                    }
                    if ($enquiry->quick_enquiry_no) {
                        $q->orWhere('enq_no', $enquiry->quick_enquiry_no);
                    }
                })->first();

                if ($existingBooking) {
                    \Alert::warning('Booking already exists with this enquiry.')->flash();

                    return redirect(backpack_url("sales/booking/{$existingBooking->id}/edit"));
                }
            }
        }

        if ($quotationId = request('quotation_id')) {
            $existingBooking = Booking::where('quotation_id', $quotationId)->first();
            if ($existingBooking) {
                \Alert::warning('Booking already exists with this quotation.')->flash();

                return redirect(backpack_url("sales/booking/{$existingBooking->id}/edit"));
            }
        }

        return $this->traitCreate();
    }

    /**
     * Centralized method to prepare complete booking data for show/edit/invoice views
     *
     * @param  int  $id  Booking ID
     * @param  string  $viewName  Blade view name suffix (default: 'view')
     * @return View
     */
    public function store(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $pending = 0;
        $pendingFields = [];

        $finModeRule = $request->customertype === 'Dummy'
            ? 'nullable|string|max:255'
            : 'required|string|max:255';

        $validator = Validator::make($request->all(), [
            'customertype' => 'required|string|max:255',
            'user' => 'nullable',
            'hiddenbookingdate' => 'nullable|date',
            'refrenceno' => 'nullable|string|max:255',
            'dsadetails' => 'nullable|string|max:255',
            'branch' => 'required|string',
            'location' => 'required|string',
            'segment' => 'required|string',
            'model' => 'required|string|max:255',
            'variant' => 'required|string|max:255',
            'color' => 'required|string|max:255',
            'sale_type' => 'required|in:1,2',
            'name' => 'required|string|max:255',
            'careof' => 'nullable|string|max:255',
            'careofname' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:15',
            'altmobile' => 'nullable|string|max:15',
            'panno' => 'nullable|string|max:10',
            'adharno' => 'nullable|string|max:15',
            'dmsotf' => 'nullable|string|max:255',
            'chassis' => 'nullable|string|max:255',
            'deliverytype' => 'required|string|max:255',
            'hiddenexpecteddeldate' => 'nullable|date',
            'finmode' => $finModeRule,
            'financier' => 'nullable|string|max:255',
            'loanstatus' => 'nullable|string|max:255',
            'saleconsultant' => 'required',
            'apackamount' => 'required',
            'details' => 'nullable|string',

            // Customer Address
            'pincode' => 'required|string|max:10',
            'vpo' => 'required|string|max:150',
            'customer_tehsil' => 'required|string|max:100',
            'customer_district' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'territory' => 'required|string|max:100',

            // Referral
            'referredby' => 'nullable|string|max:255',
            'refcustomername' => 'nullable|string|max:255',
            'refmobileno' => 'nullable|string|max:15',
            'refexistingmodel' => 'nullable|string|max:255',
            'refvariant' => 'nullable|string|max:255',
            'refchassisregno' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('[STORE] Base validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        if ($request->customertype != 'Dummy') {
            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->messages()->first());
            }

            $validator = Validator::make($request->all(), [
                'bookingsource' => 'required|string|max:255',
                'hiddenbookingdate' => 'required|date',
                'bookingamount' => 'required|numeric',
                'bookingmode' => 'required|string|max:255',
                'coltype' => 'required',
                'mode' => 'required|in:Cash,Cheque,Bank Transfer,UPI',
            ]);

            if ($validator->fails()) {
                Log::warning('[STORE] Actual-only validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);

                return redirect()->back()->withInput()->with('error', $validator->messages()->first());
            }
        }

        if ($request->coltype === 1) {
            $validator = Validator::make($request->all(), [
                'receiptno' => 'required|string|max:255',
                'hiddenreceiptdate' => 'required|date',
            ]);

            if ($validator->fails()) {
                Log::warning('[STORE] Receipt validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);

                return redirect()->back()->withInput()->with('error', $validator->messages()->first());
            }
        }

        $isDummy = $request->customertype === 'Dummy';

        if (! $isDummy) {
            if (is_null($request->input('receiptno'))) {
                $pending++;
                $pendingFields[] = 'Receipt number needs to be updated';
            }
            if (is_null($request->input('hiddenreceiptdate'))) {
                $pending++;
                $pendingFields[] = 'Receipt date needs to be updated';
            }
            if ($request->input('bookingmode') === 'Online') {
                if (is_null($request->input('refrenceno')) || $request->input('refrenceno') === '') {
                    $pending++;
                    $pendingFields[] = 'Online booking reference number needs to be updated';
                }
            }
            if (is_null($request->input('panno'))) {
                $pending++;
                $pendingFields[] = 'PAN number needs to be updated';
            }
            if (is_null($request->input('adharno'))) {
                $pending++;
                $pendingFields[] = 'Aadhar number needs to be updated';
            }
            if (is_null($request->input('dmsno'))) {
                $pending++;
                $pendingFields[] = 'Sales force number needs to be updated';
            }
            if (is_null($request->input('dmsotf'))) {
                $pending++;
                $pendingFields[] = 'DMS OTF needs to be updated';
            }
            if (is_null($request->input('hiddenotfdate'))) {
                $pending++;
                $pendingFields[] = 'DMS OTF Date needs to be updated';
            }
            if ($request->makeorder == 1) {
                $pending++;
                $pendingFields[] = 'DMS SO number needs to be updated';
            }
        }

        $customerTypeInput = $request->input('customertype');
        $adhar_no_normalized = preg_replace('/[^0-9]/', '', $request->input('adharno', ''));
        $customerType = ($customerTypeInput === 'Actual' || $customerTypeInput === 'Active') ? 'Active' : $customerTypeInput;

        $booking = new Booking;
        $quotation = null;

        if ($request->filled('quotation_no')) {
            $quotation = Quotation::where('id', $request->quotation_no)->first();
        }

        // Store the exact enquiry ID
        if ($quotation) {
            $booking->enq_no = $quotation->enquiry_id;
            $booking->quotation_id = $quotation->id;
        } elseif ($request->filled('enquiry_id')) {
            $booking->enq_no = $request->enquiry_id;
        } else {
            $booking->enq_no = null;
        }

        $booking->b_type = $customerType;
        $booking->b_cat = $request->input('customercat');
        $booking->b_mode = $request->input('bookingmode');
        $booking->cpd = $request->input('hiddencpd');
        $booking->col_type = $isDummy ? 1 : ($request->input('coltype') ?? 1);
        $booking->col_by = $request->input('user');
        $booking->b_source = $request->input('bookingsource');
        $booking->sale_type = $request->input('sale_type');
        $booking->dsa_id = $request->input('dsadetails');
        $booking->online_bk_ref_no = $request->input('refrenceno');
        $booking->booking_date = $request->input('hiddenbookingdate');
        $booking->receipt_no = $request->input('receiptno');
        $booking->receipt_date = $request->input('hiddenreceiptdate');
        $booking->booking_amount = $isDummy ? 0 : $request->input('bookingamount');
        $booking->payment_mode = $request->input('mode');
        $booking->order = $request->input('makeorder');

        // Native Booking Fields
        $booking->pan_no = $request->input('panno');
        $booking->adhar_no = $adhar_no_normalized;
        $booking->gstn = $request->input('gstn');
        $booking->dms_otf = $request->input('dmsotf');
        $booking->dms_so = $request->input('dms_so');
        $booking->dms_no = $request->input('dmsno');
        $booking->otf_date = $request->input('hiddenotfdate');
        $booking->mapped = 0;
        $booking->chassis_no = $request->input('chassis');
        $booking->del_type = $request->input('deliverytype');
        $booking->del_date = $request->input('hiddenexpecteddeldate');

        // NEW: Update the associated Enquiry with all the deleted booking fields
        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);

            if ($linkedEnquiry) {
                $linkedEnquiry->update([
                    'name' => $request->input('name'),
                    'care_of_type' => $request->input('careof'),
                    'care_of' => $request->input('careofname'),
                    'mobile' => $request->input('mobile'),
                    'alternate_mobile' => $request->input('altmobile'),
                    'gender' => $request->input('gender'),
                    'occupation_type' => $request->input('occupation'),
                    'dob' => $request->input('hiddencustomerdob'),
                    'dealer_branch' => $request->input('branch'),
                    'dealer_location' => $request->input('location'),
                    'segment_code' => $request->input('segment'),
                    'model_code' => $request->input('model'),
                    'variant_code' => $request->input('variant'),
                    'color_code' => $request->input('color'),
                    'purchase_type' => $request->input('buyertype'),
                    'brand_make' => $request->input('enummaster1'),
                    'brand_model' => $request->input('vehicledetails'),
                    'consid_brand2' => $request->input('enummaster2'),
                    'consid_model2' => $request->input('vehicledetails2'),
                    'vehicle_no' => $request->input('registrationno'),
                    'make_year' => $request->input('manufacturingyear'),
                    'odo_reading' => $request->input('odometerreading'),
                    'expected_price' => $request->input('expectedprice'),
                    'offered_price' => $request->input('offeredprice'),
                    'exchange_bonus' => $request->input('exchangebonus'),
                    'fin_mode' => $isDummy ? 'Dummy' : $request->input('finmode'),
                    'financier' => $isDummy ? null : $request->input('financier'),
                    'x8_sc_code' => $request->input('saleconsultant'),
                    'referee_name' => $request->input('refcustomername'),
                    'referee_phone' => $request->input('refmobileno'),
                    'referred_by' => $request->input('referredby'),
                    'remarks' => $request->input('details'),
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
            /*
            |--------------------------------------------------------------------------
            | Quotation -> Booking Conversion
            |--------------------------------------------------------------------------
            */

            if ($quotation) {

                /*
            |-------------------------------------------------------
            | Update quotation status
            |-------------------------------------------------------
            */

                $quotation->status = 'booked';
                $quotation->save();

                /*
            |-------------------------------------------------------
            | Create Quote History
            |-------------------------------------------------------
            */

                QuoteAction::create([

                    'quotation_no' => $quotation->quotation_no,
                    'action_by' => backpack_user()->id,
                    'action' => 'BOOKED',
                    'requested' => $quotation->standard_data,
                    'onroad' => $quotation->onroad_price,
                    'status' => 'booked',
                    'remarks' => 'Converted into Booking #'.$booking->id,

                ]);

                /*
            |-------------------------------------------------------
            | Insurance
            |-------------------------------------------------------
            */

                XlInsurance::updateOrCreate(
                    ['bid' => $booking->id],
                    [
                        'pol_type' => $quotation->standard_data['policy_type'] ?? null,
                    ]
                );

                /*
            |-------------------------------------------------------
            | RTO
            |-------------------------------------------------------
            */

                XlRto::updateOrCreate(
                    ['bid' => $booking->id],
                    [
                        'rgn_type' => $quotationData['registration_type'] ?? null,
                    ]
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
                        [
                            'remark' => $request->details,
                        ],
                        null,
                        backpack_user()
                    );
                }

            } catch (Exception $e) {

                Log::error('💥 [HISTORY] Failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        } catch (Exception $e) {

            dd(
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }

        $uploadedFilePath = null;
        $copyPath = null;

        if ($request->hasFile('amountproof') && $request->file('amountproof')->isValid()) {
            try {
                $file = $request->file('amountproof');
                $storedName = $file->store('temp', 'public');
                $uploadedFilePath = public_path('storage/'.$storedName);

                if (! file_exists($uploadedFilePath)) {
                    throw new Exception('Stored file not found at: '.$uploadedFilePath);
                }

                $extension = $file->extension();
                $fn2 = 'tf_ap2_'.date('Ymd_His').'_'.uniqid().'.'.$extension;
                $copyPath = public_path('uploads/temp/'.$fn2);
                $copyDir = dirname($copyPath);

                if (! file_exists($copyDir)) {
                    mkdir($copyDir, 0755, true);
                }

                if (! copy($uploadedFilePath, $copyPath)) {
                    Log::warning('[STORE] amount-proof copy() returned false — ChatHelper may skip file', ['booking_id' => $booking->id]);
                    $copyPath = null;
                }
            } catch (Exception $e) {
                Log::error('💥 [FILE] File upload block threw exception', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $uploadedFilePath = null;
                $copyPath = null;
            }
        }

        $number = $request->input('receiptno') ?? $request->input('voucherno');

        if (
            ! $isDummy &&
            in_array($booking->col_type, [1, 4]) &&
            $booking->booking_amount > 0 &&
            $number
        ) {
            try {
                $payment = new Bookingamount;
                $payment->bid = $booking->id;
                $payment->date = $request->input('hiddenreceiptdate') ?? now();
                $payment->amount = $booking->booking_amount;
                $payment->type_number = $number;
                $payment->mode = $request->input('mode');
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

        if (
            ! $isDummy &&
            $request->has('buyertype') &&
            $request->input('buyertype') === 'Exchange Buy'
        ) {
            try {
                $exchange = new XExchange;
                $exchange->bid = $booking->id;
                $exchange->vehicle_oem_code = $booking->vehicle_oem_code;
                $exchange->verification_status = 1;
                $exchange->case_status = 1;
                $exchange->purchase_type = $request->input('buyertype');
                $exchange->save();
            } catch (Exception $e) {
                Log::error('[STORE] XExchange save failed', ['booking_id' => $booking->id, 'message' => $e->getMessage()]);
            }
        }

        if (
            ! $isDummy &&
            $request->has('finmode') &&
            $request->input('finmode') === 'In-house'
        ) {
            try {
                $finance = new XFinance;
                $finance->bid = $booking->id;
                $finance->fin_mode = $request->input('finmode');
                $finance->financier = $request->input('financier');
                $finance->loan_status = $request->input('loanstatus') ?: 'Pending';
                $finance->verification_status = 1;
                $finance->case_status = 1;
                $finance->save();
            } catch (Exception $e) {
                Log::error('[STORE] XFinance save failed', [
                    'booking_id' => $booking->id ?? null,
                    'finmode' => $request->input('finmode'),
                    'financier' => $request->input('financier'),
                    'loanstatus' => $request->input('loanstatus'),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                throw $e;
            }
        }

        return redirect(backpack_url('sales/booking'))->with('success', 'Booking added successfully!');
    }

    public function update(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $old_col_type = $booking->col_type;
        $old_col_by = $booking->col_by;

        $rules = [
            'sale_type' => 'required|in:1,2',
            'name' => 'required|string|max:255',
            'care_of' => 'nullable|string|max:255',
            'care_of_name' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:15',
            'alt_mobile' => 'nullable|string|max:15',
            'gender' => 'required|string',
            'occupation' => 'required|string',
            'pan_no' => 'nullable|string|max:10',
            'adhar_no' => 'nullable|string|max:20',
            'gstn' => 'nullable|string|max:20',
            'hidden_customer_dob' => 'required|date',

            'branch' => 'required|string',
            'location_id' => 'required|string',
            'location_other' => 'nullable|string|max:255',

            'segment_id' => 'required|string',
            'model' => 'required|string|max:255',
            'variant' => 'required|string|max:255',
            'color' => 'required|string|max:255',
            'accessories' => 'nullable|array',
            'accessories.*' => 'string',
            'apack_amount' => 'required|numeric',
            'chassis' => 'nullable|string|max:255',

            'buyer_type' => 'required|string',
            'enummaster1' => 'nullable|string',
            'vehicle_details' => 'nullable|string|max:255',
            'enummaster2' => 'nullable|string',
            'vehicle_details2' => 'nullable|string|max:255',
            'registration_no' => 'nullable|string|max:255',
            'manufacturing_year' => 'nullable|integer',
            'odometer_reading' => 'nullable|string|max:255',
            'expected_price' => 'nullable|numeric',
            'offered_price' => 'nullable|numeric',
            'exchange_bonus' => 'nullable|numeric',

            'booking_mode' => 'required|string',
            'refrence_no' => 'nullable|string|max:255',
            'booking_source' => 'required|string',
            'dsa_details' => 'nullable|string',
            'saleconsultant' => 'required|string',
            'delivery_type' => 'required|string|in:Expected,Confirmed',
            'expected_del_date_actual' => 'nullable|date',
            'fin_mode' => 'required|string',
            'financier' => 'nullable|integer',
            'loan_status' => 'nullable|string',
            'make_order' => 'nullable|integer',
            'details' => 'nullable|string',

            'ref_customer_name' => 'nullable|string|max:255',
            'ref_mobile_no' => 'nullable|string|max:15',
            'ref_existing_model' => 'nullable|string|max:255',
            'ref_variant' => 'nullable|string|max:255',
            'ref_chassis_reg_no' => 'nullable|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $pending = 0;
        $pendingFields = [];

        if ($request->input('booking_mode') === 'Online' && empty($request->input('refrence_no'))) {
            $pending++;
            $pendingFields[] = 'Online booking reference number needs to be updated';
        }
        if (empty($request->input('pan_no'))) {
            $pending++;
            $pendingFields[] = 'PAN number needs to be updated';
        }
        if (empty($request->input('adhar_no'))) {
            $pending++;
            $pendingFields[] = 'Aadhar number needs to be updated';
        }

        $adhar_no_normalized = preg_replace('/[^0-9]/', '', $request->input('adhar_no', ''));

        $data['saleconsultants'] = OrgService::usersByDesignation('CNS') ?? [];
        $data['allusers'] = OrgService::usersByDepartment('SLS') ?? [];
        $data['dsa_details'] = XL_DSA_MASTER::all()->map(fn ($dsa) => [
            'id' => $dsa->id,
            'name' => $dsa->name,
            'mobile' => $dsa->mobile,
        ])->toArray();

        $getFinancierName = function ($fid) {
            if (empty($fid)) {
                return 'Null';
            }
            $f = XlFinancier::find($fid);

            return $f ? $f->name : 'Unknown';
        };

        $rem = [];

        // Fetch Linked Enquiry for updates
        $linkedEnquiry = null;
        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        if ($booking->b_type != $request->input('customer_type')) {
            $rem[] = 'Customer Type Changed from '.($booking->b_type ?? 'null').' to '.$request->input('customer_type');
            $booking->b_type = $request->input('customer_type');
        }

        if ($booking->booking_date != $request->input('booking_date_actual')) {
            $oldDate = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d-M-Y') : 'null';
            $newDate = $request->input('booking_date_actual') ? Carbon::parse($request->input('booking_date_actual'))->format('d-M-Y') : 'null';
            $rem[] = "Booking Date Changed from {$oldDate} to {$newDate}";
            $booking->booking_date = $request->input('booking_date_actual');
        }

        if ($booking->booking_amount != $request->input('booking_amount')) {
            $rem[] = 'Booking Amount Changed from '.($booking->booking_amount ?? '0').' to '.$request->input('booking_amount');
            $booking->booking_amount = $request->input('booking_amount');
        }

        if ($booking->receipt_no != $request->input('receipt_no')) {
            $rem[] = 'Receipt No. Changed from '.($booking->receipt_no ?? 'null').' to '.$request->input('receipt_no');
            $booking->receipt_no = $request->input('receipt_no');
        }

        if ($booking->receipt_date != $request->input('receipt_date_actual')) {
            $rem[] = 'Receipt Date Changed';
            $booking->receipt_date = $request->input('receipt_date_actual');
        }

        if ($request->filled('mode')) {
            if ($booking->payment_mode != $request->input('mode')) {
                $rem[] = 'Payment Mode Changed from '
                    .($booking->payment_mode ?? 'null')
                    .' to '
                    .$request->input('mode');

                $booking->payment_mode = $request->input('mode');
            }
        }

        if ($old_col_type != $request->input('col_type')) {
            $colTypeMap = ['1' => 'Receipt', '2' => 'Field Collection By Sales Team', '3' => 'Field Collection By DSA', '4' => 'Used Car Purchase'];
            $rem[] = 'Collection Type Changed from '.($colTypeMap[$old_col_type] ?? 'null').' to '.($colTypeMap[$request->input('col_type')] ?? 'null');
        }

        if ($old_col_by != $request->input('user')) {
            $oldUser = 'null';
            $newUser = 'null';
            if ($old_col_type == 2) {
                $u = collect($data['allusers'])->firstWhere('id', $old_col_by);
                $oldUser = $u ? ($u['name'] ?? $u->name).' - ('.($u['emp_code'] ?? $u->emp_code ?? '').')' : 'null';
            } elseif ($old_col_type == 3) {
                $d = collect($data['dsa_details'])->firstWhere('id', $old_col_by);
                $oldUser = $d ? $d['name'].' - '.$d['mobile'] : 'null';
            }
            if ($request->input('col_type') == 2) {
                $u = collect($data['allusers'])->firstWhere('id', $request->input('user'));
                $newUser = $u ? ($u['name'] ?? $u->name).' - ('.($u['emp_code'] ?? $u->emp_code ?? '').')' : 'null';
            } elseif ($request->input('col_type') == 3) {
                $d = collect($data['dsa_details'])->firstWhere('id', $request->input('user'));
                $newUser = $d ? $d['name'].' - '.$d['mobile'] : 'null';
            }
            $rem[] = "Collected By Changed from {$oldUser} to {$newUser}";
        }

        if ($linkedEnquiry && $linkedEnquiry->name != $request->input('name')) {
            $rem[] = 'Name Changed from '.$linkedEnquiry->name.' to '.$request->input('name');
            $linkedEnquiry->name = $request->input('name');
        }

        if ($linkedEnquiry && $linkedEnquiry->care_of_type != $request->input('care_of')) {
            $rem[] = 'Care Of Type Changed';
            $linkedEnquiry->care_of_type = $request->input('care_of');
        }

        if ($linkedEnquiry && $linkedEnquiry->care_of != $request->input('care_of_name')) {
            $rem[] = 'Care Of Changed from '.($linkedEnquiry->care_of ?? 'None').' to '.($request->input('care_of_name') ?? 'None');
            $linkedEnquiry->care_of = $request->input('care_of_name');
        }

        if ($linkedEnquiry && $linkedEnquiry->mobile != $request->input('mobile')) {
            $rem[] = 'Mobile Changed from '.$linkedEnquiry->mobile.' to '.$request->input('mobile');
            $linkedEnquiry->mobile = $request->input('mobile');
        }

        if ($linkedEnquiry && $linkedEnquiry->alternate_mobile != $request->input('alt_mobile')) {
            $rem[] = 'Alt Mobile Changed from '.($linkedEnquiry->alternate_mobile ?? '0').' to '.$request->input('alt_mobile');
            $linkedEnquiry->alternate_mobile = $request->input('alt_mobile');
        }

        if ($linkedEnquiry && $linkedEnquiry->gender != $request->input('gender')) {
            $rem[] = 'Gender Changed from '.($linkedEnquiry->gender ?? 'null').' to '.$request->input('gender');
            $linkedEnquiry->gender = $request->input('gender');
        }

        if ($linkedEnquiry && $linkedEnquiry->occupation_type != $request->input('occupation')) {
            $rem[] = 'Occupation Changed from '.($linkedEnquiry->occupation_type ?? 'null').' to '.$request->input('occupation');
            $linkedEnquiry->occupation_type = $request->input('occupation');
        }

        if ($booking->pan_no != $request->input('pan_no')) {
            $rem[] = 'PAN No. Changed from '.($booking->pan_no ?? '0').' to '.$request->input('pan_no');
            $booking->pan_no = $request->input('pan_no');
        }

        if ($booking->adhar_no != $adhar_no_normalized) {
            $rem[] = 'Aadhar No. Changed from '.($booking->adhar_no ?? '0').' to '.$adhar_no_normalized;
            $booking->adhar_no = $adhar_no_normalized;
        }

        $gstValue = $request->has('gst_unregistered') ? '0' : $request->input('gstn');
        if ($booking->gstn != $gstValue) {
            $rem[] = 'GSTN Changed';
            $booking->gstn = $gstValue;
        }
        if ($booking->sale_type != $request->input('sale_type')) {
            $rem[] = 'Sale Type Changed from '
                .($booking->sale_type ?? 'null')
                .' to '
                .$request->input('sale_type');

            $booking->sale_type = $request->input('sale_type');
        }

        if ($linkedEnquiry && $linkedEnquiry->dob != $request->input('hidden_customer_dob')) {
            $oldDob = $linkedEnquiry->dob ? Carbon::parse($linkedEnquiry->dob)->format('d-M-Y') : 'null';
            $newDob = $request->input('hidden_customer_dob') ? Carbon::parse($request->input('hidden_customer_dob'))->format('d-M-Y') : 'null';
            $rem[] = "Customer D.O.B. Changed from {$oldDob} to {$newDob}";
            $linkedEnquiry->dob = $request->input('hidden_customer_dob');
        }

        if ($linkedEnquiry && $linkedEnquiry->dealer_branch != $request->input('branch')) {
            $rem[] = 'Branch Changed from '.($linkedEnquiry->dealer_branch ?? 'null').' to '.$request->input('branch');
            $linkedEnquiry->dealer_branch = $request->input('branch');
        }

        if ($linkedEnquiry && $linkedEnquiry->dealer_location != $request->input('location_id')) {
            $rem[] = 'Location Changed from '.($linkedEnquiry->dealer_location ?? 'null').' to '.$request->input('location_id');
            $linkedEnquiry->dealer_location = $request->input('location_id');
        }

        if ($linkedEnquiry && $linkedEnquiry->segment_code != $request->input('segment_id')) {
            $rem[] = 'Segment Changed from '.($linkedEnquiry->segment_code ?? 'null').' to '.$request->input('segment_id');
            $linkedEnquiry->segment_code = $request->input('segment_id');
        }

        if ($linkedEnquiry && $linkedEnquiry->model_code != $request->input('model')) {
            $rem[] = 'Model Changed from '.($linkedEnquiry->model_code ?? 'null').' to '.$request->input('model');
            $linkedEnquiry->model_code = $request->input('model');
        }

        if ($linkedEnquiry && $linkedEnquiry->variant_code != $request->input('variant')) {
            $rem[] = 'Variant Changed from '.($linkedEnquiry->variant_code ?? 'null').' to '.$request->input('variant');
            $linkedEnquiry->variant_code = $request->input('variant');
        }

        if ($linkedEnquiry && $linkedEnquiry->color_code != $request->input('color')) {
            $rem[] = 'Color Changed from '.($linkedEnquiry->color_code ?? 'null').' to '.$request->input('color');
            $linkedEnquiry->color_code = $request->input('color');
        }

        if ($booking->chassis_no != $request->input('chassis')) {
            $rem[] = 'Chassis No. Changed from '.($booking->chassis_no ?? 'null').' to '.($request->input('chassis') ?? 'null');
            $booking->chassis_no = $request->input('chassis');
        }

        if ($linkedEnquiry && $linkedEnquiry->purchase_type_crm != $request->input('buyer_type')) {
            $rem[] = 'Buyer Type Changed from '.($linkedEnquiry->purchase_type_crm ?? 'null').' to '.$request->input('buyer_type');
            $linkedEnquiry->purchase_type_crm = $request->input('buyer_type');
        }

        if ($linkedEnquiry && $linkedEnquiry->brand_make != $request->input('enummaster1')) {
            $rem[] = 'Brand (Make 1) Changed';
            $linkedEnquiry->brand_make = $request->input('enummaster1');
        }

        if ($linkedEnquiry && $linkedEnquiry->brand_model != $request->input('vehicle_details')) {
            $rem[] = 'Model & Variant 1 Changed';
            $linkedEnquiry->brand_model = $request->input('vehicle_details');
        }

        if ($linkedEnquiry && $linkedEnquiry->consid_brand2 != $request->input('enummaster2')) {
            $rem[] = 'Brand (Make 2) Changed';
            $linkedEnquiry->consid_brand2 = $request->input('enummaster2');
        }

        if ($linkedEnquiry && $linkedEnquiry->consid_model2 != $request->input('vehicle_details2')) {
            $rem[] = 'Model & Variant 2 Changed';
            $linkedEnquiry->consid_model2 = $request->input('vehicle_details2');
        }

        if ($linkedEnquiry && $linkedEnquiry->vehicle_no != $request->input('registration_no')) {
            $rem[] = 'Vehicle Registration No. Changed';
            $linkedEnquiry->vehicle_no = $request->input('registration_no');
        }

        if ($linkedEnquiry && $linkedEnquiry->make_year != $request->input('manufacturing_year')) {
            $rem[] = 'Manufacturing Year Changed';
            $linkedEnquiry->make_year = $request->input('manufacturing_year');
        }

        if ($linkedEnquiry && $linkedEnquiry->odo_reading != $request->input('odometer_reading')) {
            $rem[] = 'Odometer Reading Changed';
            $linkedEnquiry->odo_reading = $request->input('odometer_reading');
        }

        if ($linkedEnquiry && $linkedEnquiry->expected_price != $request->input('expected_price')) {
            $rem[] = 'Expected Price Changed';
            $linkedEnquiry->expected_price = $request->input('expected_price');
        }

        if ($linkedEnquiry && $linkedEnquiry->offered_price != $request->input('offered_price')) {
            $rem[] = 'Offered Price Changed';
            $linkedEnquiry->offered_price = $request->input('offered_price');
        }

        if ($linkedEnquiry && $linkedEnquiry->exchange_bonus != $request->input('exchange_bonus')) {
            $rem[] = 'Exchange Bonus Changed';
            $linkedEnquiry->exchange_bonus = $request->input('exchange_bonus');
        }

        if ($booking->b_mode != $request->input('booking_mode')) {
            $rem[] = 'Booking Mode Changed from '.($booking->b_mode ?? 'null').' to '.$request->input('booking_mode');
            $booking->b_mode = $request->input('booking_mode');
        }

        if ($booking->online_bk_ref_no != $request->input('refrence_no')) {
            $rem[] = 'Online Ref No. Changed from '.($booking->online_bk_ref_no ?? 'null').' to '.$request->input('refrence_no');
            $booking->online_bk_ref_no = $request->input('refrence_no');
        }

        if ($booking->b_source != $request->input('booking_source')) {
            $rem[] = 'Booking Source Changed from '.($booking->b_source ?? 'null').' to '.$request->input('booking_source');
            $booking->b_source = $request->input('booking_source');
        }

        if ($booking->dsa_id != $request->input('dsa_details')) {
            $rem[] = 'DSA Changed';
            $booking->dsa_id = $request->input('dsa_details');
        }

        if ($linkedEnquiry && $linkedEnquiry->x8_sc_code != $request->input('saleconsultant')) {
            $oldC = collect($data['saleconsultants'])->firstWhere('id', $linkedEnquiry->x8_sc_code);
            $newC = collect($data['saleconsultants'])->firstWhere('id', $request->input('saleconsultant'));
            $oldName = is_array($oldC) ? ($oldC['name'] ?? 'null') : ($oldC->name ?? 'null');
            $newName = is_array($newC) ? ($newC['name'] ?? 'null') : ($newC->name ?? 'null');
            $rem[] = "Sale Consultant Changed from {$oldName} to {$newName}";
            $linkedEnquiry->x8_sc_code = $request->input('saleconsultant');
        }

        if ($booking->del_type != $request->input('delivery_type')) {
            $rem[] = 'Delivery Type Changed from '.($booking->del_type ?? 'null').' to '.$request->input('delivery_type');
            $booking->del_type = $request->input('delivery_type');
        }

        if ($booking->del_date != $request->input('expected_del_date_actual')) {
            $oldDate = $booking->del_date ? Carbon::parse($booking->del_date)->format('d-M-Y') : 'null';
            $newDate = $request->input('expected_del_date_actual') ? Carbon::parse($request->input('expected_del_date_actual'))->format('d-M-Y') : 'null';
            $rem[] = "Delivery Date Changed from {$oldDate} to {$newDate}";
            $booking->del_date = $request->input('expected_del_date_actual');
        }

        if ($linkedEnquiry && $linkedEnquiry->fin_mode != $request->input('fin_mode')) {
            $rem[] = 'Finance Mode Changed from '.($linkedEnquiry->fin_mode ?? 'null').' to '.$request->input('fin_mode');
            $linkedEnquiry->fin_mode = $request->input('fin_mode');
        }

        if ($linkedEnquiry && $linkedEnquiry->financier != $request->input('financier')) {
            $rem[] = 'Financier Changed from '.$getFinancierName($linkedEnquiry->financier).' to '.$getFinancierName($request->input('financier'));
            $linkedEnquiry->financier = $request->input('financier');
        }

        $newOrder = $request->input('make_order') ? 1 : 0;
        if ($booking->order != $newOrder) {
            $rem[] = $newOrder == 1 ? 'Requested to create Sales Order' : 'Cancelled request for Sales Order';
            $booking->order = $newOrder;
        }

        if ($linkedEnquiry && $linkedEnquiry->referee_name != $request->input('ref_customer_name')) {
            $rem[] = 'Referred Name Changed';
            $linkedEnquiry->referee_name = $request->input('ref_customer_name');
        }

        if ($linkedEnquiry && $linkedEnquiry->referee_phone != $request->input('ref_mobile_no')) {
            $rem[] = 'Referred Mobile Changed';
            $linkedEnquiry->referee_phone = $request->input('ref_mobile_no');
        }

        if ($linkedEnquiry && $linkedEnquiry->remarks != $request->input('details')) {
            $rem[] = 'Remarks Changed';
            $linkedEnquiry->remarks = $request->input('details');
        }

        // Save linked enquiry if changes were made
        if ($linkedEnquiry) {
            $linkedEnquiry->save();
        }

        $booking->col_type = $request->input('col_type');
        $booking->col_by = $request->input('user');

        $booking->pending = $pending;

        if (! empty($pendingFields)) {
            $booking->pending_remark = implode(' , ', $pendingFields);
        }
        if ($pending > 0) {
            $booking->status = 8;
        }

        $booking->save();

        if ($request->input('buyer_type') === 'Exchange Buy') {
            if (! XExchange::where('bid', $booking->id)->exists()) {
                XExchange::create([
                    'bid' => $booking->id,
                    'verification_status' => 1,
                    'case_status' => 1,
                    'purchase_type' => $request->input('buyer_type'),
                ]);
                $rem[] = 'New exchange entry created';
            }
        }

        // ==========================================================
        // FINANCE DETAILS
        // ==========================================================

        $oldFinance = XFinance::where('bid', $booking->id)->first();

        $oldFinMode = $oldFinance?->fin_mode;
        $oldFinancier = $oldFinance?->financier;
        $oldLoanStatus = $oldFinance?->loan_status;

        $newFinMode = trim((string) $request->input('fin_mode', ''));
        $newFinancier = $request->input('financier');
        $newLoanStatus = trim((string) $request->input('loan_status', ''));

        Log::debug('[UPDATE] Finance update debug', [
            'booking_id' => $booking->id,
            'old_fin_mode' => $oldFinMode,
            'new_fin_mode' => $newFinMode,
            'old_financier' => $oldFinancier,
            'new_financier' => $newFinancier,
            'old_loan_status' => $oldLoanStatus,
            'new_loan_status' => $newLoanStatus,
        ]);

        if ($newFinMode === 'In-house') {

            // Get existing finance record or create a new one
            $finance = XFinance::firstOrNew([
                'bid' => $booking->id,
            ]);

            // ------------------------------------------------------
            // Finance Mode
            // ------------------------------------------------------
            $finance->fin_mode = 'In-house';

            // ------------------------------------------------------
            // Financier
            // ------------------------------------------------------
            $finance->financier = ! empty($newFinancier)
                ? $newFinancier
                : null;

            // ------------------------------------------------------
            // Loan File Status
            //
            // IMPORTANT:
            // Do NOT save NULL when the disabled field is not
            // submitted by the browser.
            // ------------------------------------------------------
            if ($newLoanStatus !== '') {

                $finance->loan_status = $newLoanStatus;

            } elseif (! $finance->exists) {

                // New finance record
                $finance->loan_status = 'Pending';

            } elseif (empty($finance->loan_status)) {

                // Existing finance record but status was never saved
                $finance->loan_status = 'Pending';
            }

            // ------------------------------------------------------
            // Default values for new finance record
            // ------------------------------------------------------
            if (! $finance->exists) {
                $finance->verification_status = 1;
                $finance->case_status = 1;
            }

            $finance->save();

            // ------------------------------------------------------
            // History
            // ------------------------------------------------------

            if ($oldFinMode !== $finance->fin_mode) {
                $rem[] = 'Finance Mode Changed from '
                    .($oldFinMode ?? 'null')
                    .' to '
                    .$finance->fin_mode;
            }

            if ((string) $oldFinancier !== (string) $finance->financier) {
                $rem[] = 'Financier Changed from '
                    .$getFinancierName($oldFinancier)
                    .' to '
                    .$getFinancierName($finance->financier);
            }

            if ($oldLoanStatus !== $finance->loan_status) {
                $rem[] = 'Loan File Status Changed from '
                    .($oldLoanStatus ?? 'null')
                    .' to '
                    .($finance->loan_status ?? 'null');
            }

            Log::debug('[UPDATE] Finance update success', [
                'booking_id' => $booking->id,
                'finance_id' => $finance->id,
                'fin_mode' => $finance->fin_mode,
                'financier' => $finance->financier,
                'loan_status' => $finance->loan_status,
            ]);

        } else {

            // ------------------------------------------------------
            // Finance mode changed from In-house to another mode
            // ------------------------------------------------------

            if ($oldFinMode === 'In-house') {

                $rem[] = 'Finance Mode Changed from In-house to '
                    .($newFinMode ?: 'null');
            }

            // We don't delete the finance record.
            // Just update its mode.
            if ($oldFinance) {

                $oldFinance->fin_mode = $newFinMode ?: null;
                $oldFinance->save();

                Log::info('FINANCE MODE UPDATED', [
                    'booking_id' => $booking->id,
                    'finance_id' => $oldFinance->id,
                    'fin_mode' => $oldFinance->fin_mode,
                ]);
            }
        }

        if (! empty($rem)) {
            $booking->addHistory(
                'commented',
                'Booking Updated',
                $request->input('details').' | '.implode(' , ', $rem),
                [],
                null,
                backpack_user()
            );
        }

        return redirect(backpack_url('sales/booking'))->with('success', 'Booking updated successfully!');
    }

    private function getFullBookingData(int $id, string $viewName = 'view'): View
    {
        Log::info('getFullBookingData STARTED', ['booking_id' => $id, 'view' => $viewName]);

        $booking = Booking::findOrFail($id);

        /* ============================================================
        | MERGE ENQUIRY FIELDS INTO BOOKING
        | (Because xlr8_booking_master no longer stores name/mobile/
        |  gender/dob/segment_code/model_code/buyer_type etc. — they
        |  live in xlr8_crm_enquiries. Without this, Blade shows N/A.)
        ============================================================ */
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
                $purchaseTypeMap = collect(
                    OrgService::keywordValueByCode('PURCHASE_TYPE')
                )->pluck('value', 'code')->toArray();

                $booking->buyer_type =
                    ! empty($enquiry->purchase_type_crm)
                        ? ($purchaseTypeMap[$enquiry->purchase_type_crm]
                            ?? $enquiry->purchase_type_crm)
                        : ($enquiry->purchase_type ?? null);
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
                $booking->details = $enquiry->remarks;
                $booking->pincode = $enquiry->zipcode ?? null;
                $booking->vpo = $enquiry->vpo ?? null;
                $booking->tehsil = $enquiry->tehsil ?? null;
                $booking->district = $enquiry->district ?? null;
                $booking->city = $enquiry->city ?? null;
                $booking->territory = $enquiry->territory ?? null;
            }
        }

        /* ============================================================
        | MERGE FINANCE FIELDS INTO BOOKING
        ============================================================ */
        $financeRecord = XFinance::where('bid', $id)->first();
        if ($financeRecord) {
            $booking->fin_mode = $financeRecord->fin_mode;
            $booking->financier = $financeRecord->financier;
            $booking->loan_status = $financeRecord->loan_status;
        }

        $booking->segment_name = 'N/A';

        if ($booking->segment_code) {
            $segment = Segment::where('code', $booking->segment_code)
                ->where('is_active', true)
                ->value('name');

            $booking->segment_name = $segment ?? 'N/A (Code not found)';
        }
        $data = [
            'booking' => $booking,
            'otf_processed' => ! empty($booking->final_data),
            'uid' => Auth::id(),
            'dsaname' => 'N/A',
            'receiptLogs' => Bookingamount::where('bid', $id)
                ->select('id', 'date', 'type_number', 'mode', 'amount')
                ->orderBy('date', 'desc')
                ->get(),
            'total_amount' => 0,
            'finance' => XFinance::where('bid', $id)->first(),
            'delivery' => XlDelivery::where('bid', $id)->first(),
            'insurance' => XlInsurance::where('bid', $id)->first(),
            'rto' => XlRto::where('bid', $id)->first(),
            'refund' => null,
            'deduction' => 0,
            'acc_proof' => '',
            'aadhar' => '',
            'pan' => '',
            'pay_proof' => '',
            'amount' => $booking->booking_amount ?? 0,
            'accessories' => 'N/A',
            'bchasis' => 'Not Available',
            'chassis' => [],
            'collector_name' => 'N/A',
            'make1' => 'N/A',
            'make2' => 'N/A',
            'branch' => 'N/A',
            'fbranch' => 'N/A',
            'location' => 'N/A',
            'flocation' => 'N/A',

            'segments' => CommonHelper::getVehicleSegments() ?? [],
            'models' => CommonHelper::getVehicleModels($booking->segment_code ?? null) ?? [],
            'variants' => CommonHelper::getVehicleVariants($booking->model_code ?? null) ?? [],
            'colors' => CommonHelper::getVehicleColors($booking->variant_code ?? null) ?? [],
            'saleconsultants' => OrgService::usersByDesignation('CNS'),
            'allusers' => OrgService::usersByDepartment('SLS'),

            'financiers' => [],
            'insurances' => [],
            'rto_rules' => [],
            'dsa_details' => [],
            'branches' => CommonHelper::getBranches() ?? [],
            'locations' => [],
            'accessories_dropdown' => [],
            'enum_master' => [],
            'oem_ids' => array_filter(explode(',', $booking->exist_oem ?? '')),

            'trade_used_map' => [
                '1' => 'BKN AD User 1 (RJ0730024TC)',
                '2' => 'BKN AD User 2 (RJ0730024TC)',
                '3' => 'BKN AD User 3 (RJ0730024TC)',
                '4' => 'SUJ AD (RJ44C0012TC)',
                '5' => 'BKN LMM L5 (RJ07C0056TC)',
                '6' => 'BKN LMM L3 (RJ07TC0322)',
            ],
            'sale_type_map' => [
                '1' => 'Within State',
                '2' => 'Outside State',
            ],
            'permit_map' => [
                '1' => 'Private - U/C (4 Wheeler)',
                '2' => 'Private - BH (4 Wheeler)',
                '3' => 'Private - EV (4 Wheeler)',
                '4' => 'Goods - G (4 Wheeler)',
                '5' => 'Goods - G 3 Ton+ (4 Wheeler)',
                '6' => 'Goods - G (3 Wheeler)',
                '7' => 'Goods - G EV (3 Wheeler)',
                '8' => 'Taxi - T (4 Wheeler)',
                '9' => 'Passenger - P (3 Wheeler)',
                '10' => 'Passenger - P EV (3 Wheeler)',
                '11' => 'Ambulance (Misc.)',
            ],
            'body_type_map' => [
                '1' => 'Complete',
                '2' => 'CBC',
            ],
            'reg_no_type_map' => [
                '1' => 'Regular',
                '2' => 'BH',
                '3' => 'Special',
            ],
            'registration_type_map' => [
                '1' => 'Type 1',
                '2' => 'Type 2',
                '3' => 'Type 3',
            ],
        ];

        $data['total_amount'] = $data['receiptLogs']->sum('amount');

        $data['branch'] = Branch::where('branch_code', $booking->branch_code)
            ->value('name') ?? 'N/A';
        $data['fbranch'] = $data['branch'];
        $data['location'] = $booking->location_code
            ? (Location::where('code', $booking->location_code)
                ->value('name') ?? 'N/A')
            : ($booking->location_other ?? 'N/A');
        $data['flocation'] = $data['location'];

        $accIds = array_filter(array_map('trim', explode(',', $booking->accessories ?? '')));
        $accNames = [];
        foreach ($accIds as $accId) {
            if ($accessory = Xessories::where('part_no', $accId)->first()) {
                $accNames[] = $accessory->item;
            }
        }
        $data['accessories'] = $accNames ? implode(', ', $accNames) : 'N/A';

        $data['chassis'] = Stock::where('model_code', 'code')
            ->select('chasis_no', 'id')
            ->get()
            ->toArray();

        $cr = Stock::find($booking->chassis_no);

        if ($cr) {
            $data['bchasis'] = $cr->chasis_no;

            $data['chassis'] = Stock::where('model_code', $cr->model_code)
                ->select('chasis_no', 'id')
                ->get()
                ->toArray();
        }

        $data['collector_name'] = match ((int) $booking->col_type) {
            1 => 'N/A',
            2 => 'TEST SALES',
            3 => 'TEST DSA',
            default => 'N/A',
        };

        $drec = XL_DSA_MASTER::find($booking->dsa_id);
        $data['dsaname'] = $drec ? $drec->name.' - '.$drec->mobile : 'N/A';

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';

        $data['financiers'] = XlFinancier::select('id', 'name', 'short_name')
            ->get()
            ->toArray() ?? [];

        $data['insurances'] = XlInsurer::select('id', 'name', 'short_name')
            ->get()
            ->toArray() ?? [];

        $data['rto_rules'] = XlRtoRules::select(
            'sale_type',
            'permit',
            'body_type',
            'reg_no_type',
            'trc_number',
            'trc_pay',
            'trc_copy',
            'app_no',
            'tax_pay',
            'veh_reg',
            'tax_copy'
        )->get()->toArray() ?? [];

        $data['dsa_details'] = XL_DSA_MASTER::all()
            ->map(fn ($dsa) => [
                'id' => $dsa->id,
                'name' => $dsa->name,
                'mobile' => $dsa->mobile,
                'email' => $dsa->email,
                'location' => $dsa->dlocation,
            ])->toArray() ?? [];

        $locations = CommonHelper::getLocations($booking->branch_code) ?? [];
        usort($locations, fn ($a, $b) => strcmp(
            ($a['name'] ?? '').' - '.($a['code'] ?? ''),
            ($b['name'] ?? '').' - '.($b['code'] ?? '')
        ));
        $data['locations'] = $locations;

        $data['accessories_dropdown'] = Accessory::getAccessories(
            $booking->segment_code ?? '',
            $booking->model_code ?? '',
            $booking->variant_code ?? ''
        );

        $data['enum_master'] = OrgService::getKeyValuesByCode('EXISTING_CAR_OEM');

        $refund = Xl_Refunds::where('entity_type', 'booking')
            ->where('entity_id', $id)
            ->latest('id')
            ->first();

        if ($refund) {

            $data['amount'] = $booking->booking_amount ?? 0;
            $data['deduction'] = $data['amount'] - ($refund->amount ?? 0);

            $data['refund'] = [
                'remaining_amount' => $refund->amount ?? 0,
                'bank_name' => $refund->bank_name ?? 'N/A',
                'branch_name' => $refund->branch_name ?? 'N/A',
                'account_type' => $refund->account_type ?? 'N/A',
                'account_number' => $refund->account_number ?? 'N/A',
                'holder_name' => $refund->holder_name ?? 'N/A',
                'ifsc_code' => $refund->ifsc_code ?? 'N/A',
                'details' => $refund->details ?? 'N/A',
                'req_date' => $refund->req_date ? Carbon::parse($refund->req_date)->format('d-M-Y') : 'N/A',
                'ref_date' => $refund->ref_date ? Carbon::parse($refund->ref_date)->format('d-M-Y') : 'N/A',
                'mode' => $refund->mode ?? 'N/A',
                'transaction_details' => $refund->transaction_details ?? 'N/A',
                'remark' => $refund->remark ?? 'N/A',
            ];

            $data['acc_proof'] = $refund->getFirstMediaUrl('acc-proof')
                ?: $refund->getFirstMediaUrl('acc_proof')
                ?: '';

            $data['aadhar'] = $refund->getFirstMediaUrl('aadhar')
                ?: $refund->getFirstMediaUrl('aadhaar')
                ?: '';

            $data['pan'] = $refund->getFirstMediaUrl('pan') ?: '';

            $data['pay_proof'] = $refund->getFirstMediaUrl('pay-proof')
                ?: $refund->getFirstMediaUrl('pay_proof')
                ?: '';
        }

        $data['bookingHistory'] = $booking->commMaster()
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

        $receiptLogs = $data['receiptLogs'];

        return view("admin.booking.{$viewName}", $data + get_defined_vars());
    }

    // NOTE (optimisation): an older, fully commented-out duplicate of
    // getBaseQuery() used to live here (~150 lines). It was dead code —
    // never executed — and has been removed; see changelog.md.
    private function getBaseQuery(array $options = [])
    {
        $query = Booking::withoutGlobalScope(SoftDeletingScope::class)
            ->from('xlr8_booking_master as bookings')
            ->leftJoin('xlr8_crm_enquiries as enq', function ($join) {
                // Raw-SQL equivalent of EnquiryReferenceService::toReference() - see the same note
                // on Enquiry.php's whereRaw() CONCAT() for why this can't call the PHP service.
                $join->on('enq.id', '=', 'bookings.enq_no')
                    ->orOn(DB::raw("BINARY CONCAT('XENQ-', enq.id)"), '=', DB::raw('BINARY bookings.enq_no'))
                    ->orOn(DB::raw('BINARY enq.enquiry_no'), '=', DB::raw('BINARY bookings.enq_no'))
                    ->orOn(DB::raw('BINARY enq.quick_enquiry_no'), '=', DB::raw('BINARY bookings.enq_no'));
            })
            ->select([
                // 1. Native Booking Master Fields
                'bookings.id',
                'bookings.enq_no',
                'bookings.b_type',
                'bookings.b_cat',
                'bookings.b_mode',
                'bookings.col_type',
                'bookings.col_by',
                'bookings.quotation_id',
                'bookings.sap_no',
                'bookings.dms_no',
                'bookings.b_source',
                'bookings.dsa_id',
                'bookings.online_bk_ref_no',
                'bookings.booking_date',
                'bookings.receipt_no',
                'bookings.receipt_date',
                'bookings.booking_amount',
                'bookings.pan_no',
                'bookings.adhar_no',
                'bookings.gstn',
                'bookings.dms_otf',
                'bookings.order',
                'bookings.otf_date',
                'bookings.dms_so',
                'bookings.cpd',
                'bookings.mapped',
                'bookings.chassis_no',
                'bookings.del_type',
                'bookings.del_date',
                'bookings.inv_no',
                'bookings.inv_date',
                'bookings.dealer_inv_no',
                'bookings.dealer_inv_date',
                'bookings.cancel_date',
                'bookings.refund_request_date',
                'bookings.refund_date',
                'bookings.refund_rejection_date',
                'bookings.dealer_status',
                'bookings.pending',
                'bookings.pending_remark',
                'bookings.retail',
                'bookings.payout',
                'bookings.status',
                'bookings.created_at',
                'bookings.created_by',
                'bookings.updated_at',
                'bookings.updated_by',

                // 2. Joined Fields from Enquiry Table
                'enq.dealer_branch as branch_code',
                'enq.dealer_location as location_code',
                'enq.dob as c_dob',
                'enq.gender',
                'enq.occupation_type as occ',
                'enq.purchase_type as buyer_type',
                'enq.brand_make as exist_oem1',
                'enq.consid_brand2 as exist_oem2',
                'enq.brand_model as vh1_detail',
                'enq.consid_model2 as vh2_detail',
                'enq.vehicle_no as registration_no',
                'enq.make_year',
                'enq.odo_reading',
                'enq.expected_price',
                'enq.offered_price',
                'enq.exchange_bonus',
                'enq.segment_code',
                'enq.model_code',
                'enq.variant_code',
                'enq.color_code',

                'enq.name',
                'enq.care_of',
                'enq.care_of_type',
                'enq.mobile',
                'enq.alternate_mobile as alt_mobile',
                'enq.referee_name as r_name',
                'enq.referee_phone as r_mobile',
                DB::raw('NULL as accessories'),
                DB::raw('NULL as apack_amount'),
                'enq.x8_sc_code as consultant',
                'enq.remarks as details',

                // 3. Fallbacks
                DB::raw('NULL as location_other'),
                DB::raw('NULL as vehicle_oem_code'),
                DB::raw('NULL as r_model'),
                DB::raw('NULL as r_variant'),
                DB::raw('NULL as r_chassis'),
            ]);

        $query->leftJoin('xlr8_booking_refund as ref', function ($join) {
            $join->on('bookings.id', '=', DB::raw('CAST(ref.entity_id AS UNSIGNED)'))
                ->where('ref.entity_type', 'booking');
        })->addSelect([
            'ref.amount as refund_amount',
            'ref.status as refund_status',
            'ref.req_date as refund_req_date',
            'ref.ref_date as refund_ref_date',
        ]);

        $query->leftJoin('xlr8_booking_insurance as ins', 'bookings.id', '=', 'ins.bid')
            ->leftJoin('xlr8_booking_rto as rto', 'bookings.id', '=', 'rto.bid')
            ->leftJoin('xlr8_booking_finance as f', 'bookings.id', '=', 'f.bid');

        $query->addSelect([
            'ins.source as insurance_source',
            'ins.insurer as insurance_insurer_id',
            'ins.pol_no as policy_no',
            'ins.pol_date as policy_date',
            'ins.pol_type as policy_type',

            'rto.sale_type as sale_type',
            'rto.permit as permit',
            'rto.body_type as body_type',
            'rto.rgn_type as registration_type',
            'rto.rgn_no_type as registration_no_type',
            'rto.trc_no as trc_number',
            'rto.trc_payment_no as trc_payment_bank_ref_no',
            'rto.app_no as application_no',
            'rto.tax_payment_bank_ref_no as tax_payment_bank_ref_no',
            'rto.vh_rgn_no as vehicle_registration_no',

            'f.instrument_type as instrument_type',
            'f.loan_amount as loan_amount_dealer_entry',
            'f.margin as margin_money',
            'f.file_charge as file_charge',
            'f.fin_loan_amount as net_payment_amount',
            'f.payout_category as payout_category',
            'f.instrument_ref_no as do_number',
            'f.loan_amount',
            'f.expected_payout_pct',
            'f.fin_loan_amount',
            'f.gst_included',
            'f.inv1_prov_gst',
            'f.inv2_prov_gst',
            'f.inv1_no',
            'f.inv1_name',
            'f.inv2_no',
            'f.inv2_name',
            'f.consideration_no_gst',
            'f.difference',

            DB::raw('COALESCE(f.fin_mode, enq.fin_mode) as fin_mode'),
            DB::raw('COALESCE(f.financier, enq.financier) as financier'),
            'f.loan_status',
        ]);

        return $query->orderBy('bookings.id', 'DESC');
    }

    /**
     * Batch-fetch the per-page reference data that mapBookingForGrid()
     * needs, instead of letting it run 5-8 individual queries PER ROW
     * (a classic N+1: with pagination at 50 rows/page that was up to
     * ~400 extra queries on every grid page load).
     *
     * Deliberately NOT batched here (left as-is inside mapBookingForGrid,
     * flagged in changelog.md as a follow-up rather than guessed at):
     *   - the refund "latest record per booking" lookup (needs a
     *     per-group MAX/latest query to batch correctly)
     *   - the "live order" count (re-runs the same fragile multi-OR
     *     enquiry-matching join used in getBaseQuery(), grouped by
     *     model/variant/color — batching it changes a business-critical
     *     stock-availability number and deserves its own reviewed change)
     *   - OrgService::getUserNameByCode() (delegates to OrgService, which
     *     already caches its own lookups — see OrgService.php)
     *
     * @param  Collection  $bookings  the current page's raw booking rows
     * @return array<string, mixed>
     */
    private function preloadGridLookups($bookings): array
    {
        $consultantCodes = $bookings->pluck('consultant')->filter()->unique()->values();
        $dsaIds = $bookings->pluck('dsa_id')->filter()->unique()->values();
        $financierIds = $bookings->pluck('financier')->filter()->unique()->values();
        $insurerIds = $bookings->pluck('insurance_insurer_id')->filter()->unique()->values();
        $modelCodes = $bookings->pluck('model_code')->filter()->unique()->values();

        return [
            'consultants' => DB::table('xlr8_admin_person')
                ->whereIn('person_code', $consultantCodes)
                ->pluck('display_name', 'person_code'),

            'dsas' => XL_DSA_MASTER::whereIn('id', $dsaIds)->pluck('name', 'id'),

            'financiers' => XlFinancier::whereIn('id', $financierIds)->get()->keyBy('id'),

            'insurers' => XlInsurer::whereIn('id', $insurerIds)->get()->keyBy('id'),

            'stockCounts' => Stock::where('status', 'available')
                ->whereIn('model_code', $modelCodes)
                ->select('model_code', DB::raw('COUNT(*) as cnt'))
                ->groupBy('model_code')
                ->pluck('cnt', 'model_code'),

            // Static keyword-value reference data (RTO permit labels) — identical
            // for every row on every page, so it is resolved once per request here
            // instead of once per row inside mapBookingForGrid().
            'permitMap' => OrgService::getKeyValuesByCode('RTO_PERMIT')
                ->sortBy('id')
                ->values()
                ->mapWithKeys(fn ($permit, $index) => [(string) ($index + 1) => $permit->value])
                ->toArray(),
        ];
    }

    /**
     * Transforms one raw base-query row into the flat object the ag-Grid
     * list view (list.blade.php) and getAgGridColumns() expect — same ~90
     * keys as before. $lookups is the batch-fetched data from
     * preloadGridLookups(); pass [] to fall back to per-row lookups for the
     * fields sourced from it (kept optional so this method still works if
     * ever called for a single booking outside a list page).
     */
    private function mapBookingForGrid($booking, array $lookups = [])
    {
        $consultantName = $lookups['consultants'][$booking->consultant]
            ?? (DB::table('xlr8_admin_person')->where('person_code', $booking->consultant)->value('display_name') ?? 'N/A');

        $collectedByName = OrgService::getUserNameByCode($booking->col_by, $booking->col_type);

        $branchName = $booking->branch?->name ?? 'N/A';
        $locationName = $booking->location?->name ?? ($booking->location_other ?? 'N/A');

        $statusBadge = $this->getStatusBadge($booking->status ?? 8);

        $bookingNo = $booking->id;

        $invoiceDate = $booking->inv_date ? Carbon::parse($booking->inv_date)->format('d-M-Y')
            : ($booking->dealer_inv_date ? Carbon::parse($booking->dealer_inv_date)->format('d-M-Y') : 'N/A');

        $invoiceNo = $booking->inv_no ?? $booking->dealer_inv_no ?? 'N/A';

        $dsaName = $booking->dsa_id
            ? ($lookups['dsas'][$booking->dsa_id] ?? (XL_DSA_MASTER::find($booking->dsa_id)?->name ?? 'N/A'))
            : 'N/A';

        $daysOld = $booking->booking_date
            ? Carbon::parse($booking->booking_date)->diffInDays(now())
            : Carbon::parse($booking->created_at)->diffInDays(now());

        $financierRecord = null;
        if ($booking->financier) {
            $financierRecord = array_key_exists('financiers', $lookups)
                ? ($lookups['financiers'][$booking->financier] ?? null)
                : XlFinancier::find($booking->financier);
        }
        $financierName = $financierRecord ? ($financierRecord->name ?? 'N/A') : 'N/A';

        // NOT batched — see preloadGridLookups() docblock: needs a
        // per-booking "latest" record, left as a per-row query for now.
        $refundRecord = Xl_Refunds::where('entity_id', $booking->id)
            ->where('entity_type', 'booking')
            ->latest('created_at')
            ->first();

        $refundAmount = $refundRecord ? (float) $refundRecord->amount : 0;

        // NOT batched — see preloadGridLookups() docblock: re-derives
        // "live orders for this exact model/variant/color" via the same
        // fragile enquiry-matching join used in getBaseQuery(); batching
        // this changes a business-critical number and deserves its own
        // reviewed change rather than being folded in here.
        $liveCount = DB::table('xlr8_booking_master as b')
            ->join('xlr8_crm_enquiries as e', function ($join) {
                // Same raw-SQL XENQ- pattern as getBaseQuery() above - see its comment.
                $join->on('e.id', '=', 'b.enq_no')
                    ->orOn(DB::raw("BINARY CONCAT('XENQ-', e.id)"), '=', DB::raw('BINARY b.enq_no'))
                    ->orOn(DB::raw('BINARY e.enquiry_no'), '=', DB::raw('BINARY b.enq_no'))
                    ->orOn(DB::raw('BINARY e.quick_enquiry_no'), '=', DB::raw('BINARY b.enq_no'));
            })
            ->where('e.model_code', $booking->model_code)
            ->where('e.variant_code', $booking->variant_code)
            ->where('e.color_code', $booking->color_code)
            ->whereIn('b.status', [1, 8])
            ->whereNull('b.deleted_at')
            ->count();

        $accessoriesAmount = $booking->apack_amount ?? 0;

        $stockCount = array_key_exists('stockCounts', $lookups)
            ? ($lookups['stockCounts'][$booking->model_code] ?? 0)
            : Stock::where('status', 'available')->where('model_code', $booking->model_code)->count();

        $insurance_source = match ((int) ($booking->insurance_source ?? 0)) {
            1 => 'By Dealer (OEM Portal)',
            2 => 'By Dealer (Agency)',
            3 => 'By Owner (Self)',
            default => 'N/A'
        };

        $insurance_company = 'N/A';
        $insurance_short_name = 'N/A';

        if (! empty($booking->insurance_insurer_id)) {
            // FIX: previously `XlInsurer::find($id) ?? XlInsurer::find($id)` —
            // the fallback was a literal repeat of the same call and could
            // never do anything different. Now resolved once, from the
            // batch-preloaded map when available.
            $insurer = array_key_exists('insurers', $lookups)
                ? ($lookups['insurers'][$booking->insurance_insurer_id] ?? null)
                : XlInsurer::find($booking->insurance_insurer_id);

            if ($insurer) {
                $insurance_company = $insurer->name ?? 'N/A';
                $insurance_short_name = $insurer->short_name ?? 'N/A';
            }
        }

        $policy_no = $booking->policy_no ?? 'N/A';
        $policy_date = $booking->policy_date
            ? Carbon::parse($booking->policy_date)->format('d-M-Y')
            : 'N/A';

        $policy_type = match ((int) ($booking->policy_type ?? 0)) {
            1 => 'Standard',
            2 => 'Nil Dep',
            3 => 'Base(Nil Dep + Consumables)',
            4 => 'Higher(Nil Dep + Consumables + Add Ons)',
            default => 'N/A'
        };

        $rto_sale_type = match ((int) ($booking->sale_type ?? 0)) {
            1 => 'Within State',
            2 => 'Outside State',
            default => 'N/A'
        };

        // Was refetched from OrgService on every single row before; now
        // resolved once per page in preloadGridLookups() and passed in.
        $permit_map = $lookups['permitMap'] ?? OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(fn ($permit, $index) => [(string) ($index + 1) => $permit->value])
            ->toArray();

        $rto_permit = $permit_map[(string) ($booking->permit ?? '')] ?? 'N/A';

        $rto_body_type = match ((int) ($booking->body_type ?? 0)) {
            1 => 'Complete',
            2 => 'CBC',
            default => 'N/A'
        };

        $registration_type = match ((int) ($booking->registration_type ?? 0)) {
            0 => 'Exempted (Reg & Hypo Fee Only)',
            1 => 'TRC Only',
            2 => 'Tax Only',
            3 => 'TRC + Tax',
            default => 'N/A'
        };

        $registration_no_type = match ((int) ($booking->registration_no_type ?? 0)) {
            1 => 'Regular',
            2 => 'BH',
            3 => 'Special',
            default => 'N/A'
        };

        $trc_number = $booking->trc_number ?? 'N/A';

        $trc_payment_bank_ref_no = $booking->trc_payment_bank_ref_no ?? 'N/A';

        $application_no = $booking->application_no ?? 'N/A';

        $tax_payment_bank_ref_no = $booking->tax_payment_bank_ref_no ?? 'N/A';

        $vehicle_registration_no = $booking->vehicle_registration_no ?? 'N/A';
        $instrument_type = match ((int) ($booking->instrument_type ?? 0)) {
            1 => 'Financier Payment',
            2 => 'Delivery Order',
            3 => 'Sanction Letter',
            4 => 'Mail Communication',
            5 => 'Whatsapp Communication',
            6 => 'Banker Cheque',
            7 => 'Demand Graph',
            8 => 'Customer Cheque',
            default => 'N/A'
        };

        $loan_amount_dealer_entry = (float) ($booking->loan_amount_dealer_entry ?? 0);
        $margin_money = (float) ($booking->margin_money ?? 0);
        $file_charge = (float) ($booking->file_charge ?? 0);

        $net_payment_amount = $loan_amount_dealer_entry + $margin_money - $file_charge;
        $payoutCategory = 'N/A';

        if (! empty($booking->payout_category)) {
            $payoutCategory = match ((int) $booking->payout_category) {
                1 => 'Payout',
                2 => 'No Payout',
                4 => 'Cash',
                default => 'N/A'
            };
        }
        $donumber = $booking->do_number ?? 'N/A';

        $loan_amount = (float) ($booking->loan_amount ?? 0);
        $expected_payout_pct = (float) ($booking->expected_payout_pct ?? 0);
        $fin_loan_amount = (float) ($booking->fin_loan_amount ?? 0);
        $gst_included = (float) ($booking->gst_included ?? 0);
        $inv1_prov_gst = (float) ($booking->inv1_prov_gst ?? 0);
        $inv2_prov_gst = (float) ($booking->inv2_prov_gst ?? 0);
        $consideration_no_gst = (float) ($booking->consideration_no_gst ?? 0);
        $difference = (float) ($booking->difference ?? 0);

        // FIX: was a hardcoded `0.18`. Now reads the org's configured GST
        // rate (SystemSettingService::getGSTRate() returns a percentage,
        // e.g. 18.0, so it's divided by 100 to keep the exact same
        // decimal-fraction semantics every formula below already expects).
        $GST_RATE = app(SystemSettingService::class)->getGSTRate() / 100;

        $gst_rate_formatted = ($GST_RATE * 100).'%';

        $gst_included_display = match ((float) ($booking->gst_included ?? 0)) {
            0.0 => '0%',
            0.5 => '50%',
            1.0 => '100%',
            default => 'N/A'
        };

        $expected_payout_pct_decimal = $expected_payout_pct / 100;

        $expected_payout_pct_without_gst = ($gst_included > 0)
            ? $expected_payout_pct_decimal / (1 + $GST_RATE * $gst_included)
            : $expected_payout_pct_decimal;

        $expected_payout_amount_without_gst = $loan_amount * $expected_payout_pct_without_gst;
        $gst_amount = $expected_payout_amount_without_gst * $GST_RATE;

        $sugg_inv_amt = $expected_payout_amount_without_gst * (1 + $GST_RATE);

        $total_prov_with_gst = $inv1_prov_gst + $inv2_prov_gst;

        $total_prov_without_gst = ($total_prov_with_gst > 0)
            ? $total_prov_with_gst / (1 + $GST_RATE)
            : 0;

        $prov_prc_without_gst = ($loan_amount > 0)
            ? ($total_prov_without_gst / $loan_amount) * 100
            : 0;

        $diff_without_gst = $total_prov_without_gst - $expected_payout_amount_without_gst + $consideration_no_gst;

        $expected_payout_pct_formatted = number_format($expected_payout_pct, 4).'%';

        $expected_payout_pct_without_gst_formatted = number_format($expected_payout_pct_without_gst * 100, 4).'%';

        $prov_prc_without_gst_formatted = number_format($prov_prc_without_gst, 4).'%';

        $diff_without_gst_formatted = '₹ '.number_format($diff_without_gst, 2, '.', ',');

        return (object) [
            'id' => $booking->id,
            'serial_no' => null,
            'booking_no' => $bookingNo,
            'created_at' => Carbon::parse($booking->created_at)->format('d-M-Y'),
            'booking_date' => $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d-M-Y') : 'N/A',
            'cancel_date' => $booking->cancel_date ? Carbon::parse($booking->cancel_date)->format('d-M-Y') : 'N/A',
            'refund_request_date' => $booking->refund_request_date ? Carbon::parse($booking->refund_request_date)->format('d-M-Y') : 'N/A',
            'refund_date' => $booking->refund_date ? Carbon::parse($booking->refund_date)->format('d-M-Y') : 'N/A',
            'refund_rejection_date' => $booking->refund_rejection_date ? Carbon::parse($booking->refund_rejection_date)->format('d-M-Y') : 'N/A',
            'receipt_date' => $booking->receipt_date ? Carbon::parse($booking->receipt_date)->format('d-M-Y') : 'N/A',
            'invoice_date' => $invoiceDate,
            'cpd' => $booking->cpd ? Carbon::parse($booking->cpd)->format('d-M-Y') : 'N/A',
            'del_date' => $booking->del_date ? Carbon::parse($booking->del_date)->format('d-M-Y') : 'N/A',
            'otf_date' => $booking->otf_date ? Carbon::parse($booking->otf_date)->format('d-M-Y') : 'N/A',
            'inv_date' => $booking->inv_date ? Carbon::parse($booking->inv_date)->format('d-M-Y') : 'N/A',

            'name' => $booking->name ?? 'N/A',
            'care_of' => $booking->care_of ?? 'N/A',
            'care_of_type' => match ((int) $booking->care_of_type) {
                1 => 'Son of',
                2 => 'Daughter of',
                3 => 'Married to',
                4 => 'Guardian Name',
                5 => 'Owned By',
                default => 'N/A',
            },
            'customer_age' => $booking->c_dob ? $this->calculateAgeFromDob($booking->c_dob) : 'N/A',
            'mobile' => $booking->mobile ?? 'N/A',
            'alt_mobile' => $booking->alt_mobile ?? 'N/A',
            'gender' => $booking->gender ?? 'N/A',
            'occ' => $booking->occ ?? 'N/A',
            'c_dob' => $booking->c_dob ?? 'N/A',

            'pan_no' => $booking->pan_no ?? 'N/A',
            'adhar_no' => ! empty(trim($booking->adhar_no ?? '')) && strlen(trim($booking->adhar_no ?? '')) > 3
                ? trim($booking->adhar_no)
                : 'N/A',
            'gstn' => ! empty($booking->gstn) && $booking->gstn !== '0' && $booking->gstn !== 0
                ? $booking->gstn
                : 'N/A',
            'segment' => $booking->segment_code ?? 'N/A',
            'model' => $booking->model_code ?? 'N/A',
            'variant' => $booking->variant_code ?? 'N/A',
            'color' => $booking->color_code ?? 'N/A',
            'booking_amount' => $booking->booking_amount,
            'accessories_amount' => $accessoriesAmount,

            'consultant' => $consultantName,
            'branch_name' => $branchName,
            'location_name' => $locationName,
            'days_count' => (int) round($daysOld),
            'b_type' => $booking->b_type ?? 'N/A',
            'buyer_type' => $booking->buyer_type ?? 'N/A',
            'b_cat' => $booking->b_cat ?? 'N/A',
            'b_mode' => $booking->b_mode ?? 'N/A',
            'status' => $statusBadge,
            'b_source' => $booking->b_source ?? 'N/A',
            'exist_oem1' => $booking->exist_oem1 ?? 'N/A',
            'exist_oem2' => $booking->exist_oem2 ?? 'N/A',
            'vh1_detail' => $booking->vh1_detail ?? 'N/A',
            'vh2_detail' => $booking->vh2_detail ?? 'N/A',
            'col_type' => match ((int) $booking->col_type) {
                1 => 'Receipt',
                2 => 'Field (Sales)',
                3 => 'Field (DSA)',
                default => 'Unknown'
            },
            'registration_no' => $booking->registration_no ?? 'N/A',
            'vehicle_reg_no' => $booking->registration_no ?? 'N/A',
            'make_year' => $booking->make_year ?? 'N/A',
            'odo_reading' => $booking->odo_reading ?? 'N/A',
            // FIX: was `$exchange_purchase_type ?? 'N/A'` referencing a
            // variable that was never assigned anywhere in this method —
            // PHP emitted an "undefined variable" warning on every row and
            // the value was always 'N/A' regardless. No source column for
            // this exists yet, so the (now-warning-free) result is
            // unchanged; flagged in changelog.md if a real source field is
            // identified later.
            'exchange_purchase_type' => 'N/A',
            'expected_price' => $booking->expected_price ?? 'N/A',
            'offered_price' => $booking->offered_price ?? 'N/A',
            'exchange_bonus' => $booking->exchange_bonus ?? 'N/A',
            'price_gap' => ($booking->expected_price ?? 0)
                - (($booking->offered_price ?? 0) + ($booking->exchange_bonus ?? 0)),
            'col_by' => $collectedByName,
            'dsa_name' => $dsaName,
            'r_name' => $booking->r_name ?? 'N/A',
            'r_mobile' => $booking->r_mobile ?? 'N/A',
            'r_model' => $booking->r_model ?? 'N/A',
            'r_variant' => $booking->r_variant ?? 'N/A',
            'r_chassis' => $booking->r_chassis ?? 'N/A',
            'fin_mode' => $booking->fin_mode ?? 'N/A',
            'financier' => $financierName,
            'financier_short_name' => $financierRecord ? ($financierRecord->short_name ?? 'N/A') : 'N/A',
            'loan_status' => $booking->loan_status ?? 'N/A',
            'insurance_source' => $insurance_source,
            'insurance_company' => $insurance_company,
            'insurance_short_name' => $insurance_short_name,
            'policy_no' => $policy_no,
            'policy_date' => $policy_date,
            'policy_type' => $policy_type,
            'rto_sale_type' => $rto_sale_type,
            'rto_permit' => $rto_permit,
            'rto_body_type' => $rto_body_type,
            'registration_type' => $registration_type,
            'registration_no_type' => $registration_no_type,
            'trc_number' => $trc_number,
            'trc_payment_bank_ref_no' => $trc_payment_bank_ref_no,
            'application_no' => $application_no,
            'tax_payment_bank_ref_no' => $tax_payment_bank_ref_no,
            'vehicle_registration_no' => $vehicle_registration_no,
            'instrument_type' => $instrument_type,
            'loan_amount_dealer_entry' => $loan_amount_dealer_entry,
            'margin_money' => $margin_money,
            'file_charge' => $file_charge,
            'net_payment_amount' => $net_payment_amount,
            'sap_no' => $booking->sap_no ?? 'N/A',
            // FIX: same undefined-variable issue as exchange_purchase_type above.
            'used_vehicle_exp_price' => 'N/A',
            'dealer_inv_no' => $booking->dealer_inv_no ?? 'N/A',
            'inv_no' => $booking->inv_no ?? 'N/A',
            'dealer_inv_date' => $booking->dealer_inv_date ?? 'N/A',
            'dms_no' => $booking->dms_no ?? 'N/A',
            'dms_otf' => $booking->dms_otf ?? 'N/A',
            'dms_so' => $booking->dms_so ?? 'N/A',
            'online_bk_ref_no' => $booking->online_bk_ref_no ?? 'N/A',
            'receipt_no' => $booking->receipt_no ?? 'N/A',
            'receipt_date' => $booking->receipt_date ? Carbon::parse($booking->receipt_date)->format('d-M-Y') : 'N/A',
            'chassis_no' => $booking->chassis_no ?? 'N/A',
            'del_type' => $booking->del_type ?? 'N/A',
            'invoice_no' => $invoiceNo,
            'refund_amount' => $refundAmount,
            'payout_category' => $payoutCategory,
            'do_number' => $donumber,

            'loan_amount_dealer' => $loan_amount,

            'expected_payout_pct' => $expected_payout_pct_formatted,
            'gst_rate' => $gst_rate_formatted,
            'gst_included' => $gst_included_display,
            'expected_payout_pct_without_gst' => $expected_payout_pct_without_gst_formatted,
            'expected_payout_amount_without_gst' => $expected_payout_amount_without_gst,
            'gst_amount' => round($gst_amount, 2),
            'sugg_inv_amt' => $sugg_inv_amt,

            'loan_amount_fin_payout_sheet' => $fin_loan_amount,

            'inv1_no' => $booking->inv1_no ?? 'N/A',
            'inv1_name' => $booking->inv1_name ?? 'N/A',
            'inv1_prov_gst' => $booking->inv1_prov_gst ?? 0,

            'inv2_no' => $booking->inv2_no ?? 'N/A',
            'inv2_name' => $booking->inv2_name ?? 'N/A',
            'inv2_prov_gst' => $booking->inv2_prov_gst ?? 0,

            'total_prov_with_gst' => $total_prov_with_gst,

            'total_prov_without_gst' => round($total_prov_without_gst, 2),
            'consideration_no_gst' => $consideration_no_gst,
            'prov_prc_without_gst' => $prov_prc_without_gst_formatted,
            'diff_without_gst' => $diff_without_gst_formatted,

            'pan_no' => $booking->pan_no ?? 'N/A',
            'care_of' => $booking->care_of ?? 'N/A',
            'livecount' => $liveCount ?? 'N/A',
            'stockcount' => $stockCount ?? 'N/A',
            'action' => '',
        ];
    }

    private function getAgGridColumns(array $extraColumns = []): array
    {
        $columns = [

            ['headerName' => 'S.No.',       'field' => 'serial_no',     'width' => 80,  'sortable' => false, 'filter' => false],
            ['headerName' => 'XB No.',      'field' => 'booking_no',     'width' => 140,  'sortable' => true],
            ['headerName' => 'Entry Date',         'field' => 'created_at',            'width' => 110, 'type' => 'date'],
            ['headerName' => 'Booking Date',       'field' => 'booking_date',          'width' => 120, 'type' => 'date'],
            ['headerName' => 'Booking Age',       'field' => 'days_count',     'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Invoice No.',       'field' => 'inv_no',          'width' => 120],
            ['headerName' => 'Invoice Date',       'field' => 'inv_date',          'width' => 120, 'type' => 'date'],
            ['headerName' => 'Dealer Invoice No.',       'field' => 'dealer_inv_no',          'width' => 120],
            ['headerName' => 'Dealer Invoice Date',       'field' => 'dealer_inv_date',          'width' => 120, 'type' => 'date'],
            ['headerName' => 'Cancellation Date',        'field' => 'cancel_date',           'width' => 110, 'type' => 'date'],
            ['headerName' => 'Refund Request Date',    'field' => 'refund_request_date',   'width' => 130, 'type' => 'date'],
            ['headerName' => 'Refund Date',      'field' => 'refund_date',           'width' => 120, 'type' => 'date'],
            ['headerName' => 'Reject Date', 'field' => 'refund_rejection_date', 'width' => 140, 'type' => 'date'],
            ['headerName' => 'Customer Type',   'field' => 'b_type',         'width' => 110],
            ['headerName' => 'Customer Category',  'field' => 'b_cat',         'width' => 180, 'filter' => true],
            ['headerName' => 'Collection Type', 'field' => 'col_type',       'width' => 150],
            ['headerName' => 'Collected By',   'field' => 'col_by',         'width' => 140],
            ['headerName' => 'Booking Amount',         'field' => 'booking_amount', 'width' => 120, 'type' => 'number'],

            ['headerName' => 'Amount to Refund / Refunded Amount', 'field' => 'refund_amount', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Receipt No.',       'field' => 'receipt_no',          'width' => 120],
            ['headerName' => 'Receipt Date',       'field' => 'receipt_date',          'width' => 120, 'type' => 'date'],
            ['headerName' => 'Customer Name',  'field' => 'name',         'width' => 180, 'filter' => true],
            ['headerName' => 'Care Of',        'field' => 'care_of_type',      'width' => 140],
            ['headerName' => 'Care Of Name',        'field' => 'care_of',      'width' => 140],
            ['headerName' => 'Contact No.',         'field' => 'mobile',       'width' => 120],
            ['headerName' => 'Alternate Contact No.',         'field' => 'alt_mobile',       'width' => 120],
            ['headerName' => 'Gender',  'field' => 'gender',         'width' => 180, 'filter' => true],
            ['headerName' => 'Occupation',        'field' => 'occ',      'width' => 140],
            ['headerName' => 'PAN Card No.',         'field' => 'pan_no',       'width' => 110],
            ['headerName' => 'Aadhaar No.',     'field' => 'adhar_no',     'width' => 130],
            ['headerName' => 'GSTIN',           'field' => 'gstn',         'width' => 120],
            ['headerName' => 'Customer D.O.B.',       'field' => 'c_dob',          'width' => 120, 'type' => 'date'],
            ['headerName' => 'Customer Age',      'field' => 'customer_age',  'width' => 110, 'cellClass' => 'text-center'],

            ['headerName' => 'Branch',         'field' => 'branch_name',    'width' => 140, 'filter' => true],
            ['headerName' => 'Location',       'field' => 'location_name',  'width' => 160, 'filter' => true],
            ['headerName' => 'Segment',          'field' => 'segment',          'width' => 140],
            ['headerName' => 'Model',          'field' => 'model',          'width' => 140],
            ['headerName' => 'Variant',        'field' => 'variant',        'width' => 150],
            ['headerName' => 'Color',          'field' => 'color',          'width' => 100],
            ['headerName' => 'Seating',        'field' => 'seating',      'width' => 130],
            [
                'headerName' => 'Accessories Amount',
                'field' => 'accessories_amount',
                'filter' => 'agNumberColumnFilter',
                'sortable' => true,
                'width' => 160,
            ],
            ['headerName' => 'Allotted Chassis No.',        'field' => 'chassis_no',      'width' => 130],
            ['headerName' => 'Booking Status',    'field' => 'status',    'width' => 130],
            ['headerName' => 'Booking Mode',        'field' => 'b_mode',      'width' => 140],
            ['headerName' => 'Online Book Ref No.',     'field' => 'online_bk_ref_no', 'width' => 130],
            ['headerName' => 'Booking Source',  'field' => 'b_source',         'width' => 180, 'filter' => true],
            ['headerName' => 'DSA Name',  'field' => 'dsa_name',         'width' => 180, 'filter' => true],

            ['headerName' => 'Sales Consultant',     'field' => 'consultant',     'width' => 140],
            ['headerName' => 'Delivery Date Type',     'field' => 'del_type',     'width' => 140],
            ['headerName' => 'Delivery Date',      'field' => 'del_date',              'width' => 120, 'type' => 'date'],
            ['headerName' => 'Finance Mode',   'field' => 'fin_mode',         'width' => 140],
            ['headerName' => 'Financier',          'field' => 'financier',        'width' => 180, 'filter' => true],
            ['headerName' => 'Financier Short Name',    'field' => 'financier_short_name', 'width' => 150],
            ['headerName' => 'Loan File Status',        'field' => 'loan_status',      'width' => 140, 'cellClass' => 'text-center'],
            ['headerName' => 'Purchase Type',   'field' => 'buyer_type',         'width' => 140],
            ['headerName' => 'Brand Make 1',        'field' => 'exist_oem1',      'width' => 130],
            ['headerName' => 'Model Variant 1',        'field' => 'vh1_detail',      'width' => 130],
            ['headerName' => 'Brand Make 2',        'field' => 'exist_oem2',      'width' => 130],
            ['headerName' => 'Model Variant 2',        'field' => 'vh2_detail',      'width' => 130],
            ['headerName' => 'Vehicle Registration No.',        'field' => 'registration_no',      'width' => 130],
            ['headerName' => 'Vehicle Manufacturing Year',        'field' => 'make_year',      'width' => 130],
            ['headerName' => 'Vehicle Odometer Reading',        'field' => 'odo_reading',      'width' => 130],
            ['headerName' => 'Used Vehicle Expected Price',       'field' => 'expected_price',     'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Used Vehicle Offered Price',       'field' => 'offered_price',     'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'New Vehicle Exchange Bonus',       'field' => 'exchange_bonus',     'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            [
                'headerName' => 'Price Gap',
                'field' => 'price_gap',
                'width' => 140,
                'type' => 'numericColumn',
                'cellClass' => 'text-right fw-bold',
                'valueFormatter' => "params.value != null ? '₹ ' + Math.round(params.value).toLocaleString('en-IN') : 'N/A'",
            ],

            ['headerName' => 'Customer Name',                'field' => 'r_name',                   'width' => 100, 'type' => 'date'],
            ['headerName' => 'Mobile No.',     'field' => 'r_mobile',          'width' => 130],
            ['headerName' => 'Existing Model',      'field' => 'r_model',           'width' => 140],
            ['headerName' => 'Variant',    'field' => 'r_variant',         'width' => 150],
            ['headerName' => 'Chassis No.',    'field' => 'r_chassis',         'width' => 140],

            ['headerName' => 'DMS Booking No.',         'field' => 'dms_no',         'width' => 110],
            ['headerName' => 'DMS OTF No.',        'field' => 'dms_otf',        'width' => 110],
            ['headerName' => 'DMS OTF Date',      'field' => 'otf_date',              'width' => 120, 'type' => 'date'],
            ['headerName' => 'DMS SO No.',         'field' => 'dms_so',         'width' => 110],

            ['headerName' => 'Live Order',     'field' => 'livecount',   'width' => 130, 'type' => 'number'],
            ['headerName' => 'Stock In Hand',    'field' => 'stockcount',  'width' => 130, 'type' => 'number'],

            ['headerName' => 'Insurance Source',   'field' => 'insurance_source',  'width' => 160, 'filter' => true],
            ['headerName' => 'Insurance Company',  'field' => 'insurance_company', 'width' => 180, 'filter' => true],
            ['headerName' => 'Insurance Short Name',    'field' => 'insurance_short_name', 'width' => 140, 'filter' => true],

            ['headerName' => 'Policy No.',           'field' => 'policy_no',         'width' => 160, 'filter' => true],
            ['headerName' => 'Policy Date',          'field' => 'policy_date',       'width' => 130, 'type' => 'date'],
            ['headerName' => 'Policy Type',          'field' => 'policy_type',       'width' => 180, 'filter' => true],
            ['headerName' => 'Sale Type',        'field' => 'rto_sale_type',     'width' => 160, 'filter' => true],
            ['headerName' => 'Permit',           'field' => 'rto_permit',        'width' => 220, 'filter' => true],
            ['headerName' => 'Body Type',        'field' => 'rto_body_type',     'width' => 160, 'filter' => true],
            ['headerName' => 'Registration Type',          'field' => 'registration_type',     'width' => 140],
            ['headerName' => 'Registration No. Type',      'field' => 'registration_no_type',  'width' => 160],
            ['headerName' => 'TRC Number',                 'field' => 'trc_number',            'width' => 140],
            ['headerName' => 'TRC Payment Bank Ref No.',   'field' => 'trc_payment_bank_ref_no', 'width' => 180],
            ['headerName' => 'Application No.',            'field' => 'application_no',        'width' => 140],
            ['headerName' => 'Tax Payment Bank Ref No.',   'field' => 'tax_payment_bank_ref_no', 'width' => 180],
            ['headerName' => 'Vehicle Registration No.',   'field' => 'vehicle_registration_no', 'width' => 160],
            ['headerName' => 'Instrument Type',            'field' => 'instrument_type',         'width' => 180, 'filter' => true],

            ['headerName' => 'Margin Money',               'field' => 'margin_money',            'width' => 140, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'File Charge',                'field' => 'file_charge',             'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            [
                'headerName' => 'Net Payment Amount',
                'field' => 'net_payment_amount',
                'width' => 170,
                'type' => 'number',
                'cellClass' => 'text-right fw-bold',
                'valueFormatter' => "params.value != null ? '₹ ' + parseFloat(params.value).toLocaleString('en-IN', {minimumFractionDigits: 2}) : 'N/A'",
            ],
            ['headerName' => 'CPD',                'field' => 'cpd',                   'width' => 100, 'type' => 'date'],

            ['headerName' => 'Care Of Name',        'field' => 'care_of_name',      'width' => 140],

            ['headerName' => 'Payout Category',    'field' => 'payout_category',    'width' => 130],
            ['headerName' => 'SAP Booking No.',         'field' => 'sap_no',         'width' => 110],
            ['headerName' => 'DO Number',     'field' => 'do_number', 'width' => 130],
            ['headerName' => 'Loan Amount (Dealer Entry)', 'field' => 'loan_amount_dealer', 'width' => 170, 'type' => 'number', 'cellClass' => 'text-right'],

            ['headerName' => 'Expected Payout %',     'field' => 'expected_payout_pct', 'width' => 130],
            [
                'headerName' => 'GST Rate',
                'field' => 'gst_rate',
                'width' => 100,
            ],
            [
                'headerName' => 'GST Included in Payout',
                'field' => 'gst_included',
                'width' => 170,
            ],
            ['headerName' => 'Expected Payout % without GST',     'field' => 'expected_payout_pct_without_gst', 'width' => 130],

            ['headerName' => 'Expected Payout Amount without GST',     'field' => 'expected_payout_amount_without_gst', 'width' => 130],
            [
                'headerName' => 'GST Amount',
                'field' => 'gst_amount',
                'width' => 140,
            ],
            ['headerName' => 'Suggested Invoice Amount',     'field' => 'sugg_inv_amt', 'width' => 130],
            ['headerName' => 'Loan Amount(Fin Payout Sheet)',     'field' => 'loan_amount_fin_payout_sheet', 'width' => 130],
            [
                'headerName' => '1st Invoice No.',
                'field' => 'inv1_no',
                'width' => 150,
            ],
            [
                'headerName' => '1st Invoice Name',
                'field' => 'inv1_name',
                'width' => 200,
            ],
            [
                'headerName' => '1st Provisioning (GST)',
                'field' => 'inv1_prov_gst',
                'width' => 180,
            ],
            [
                'headerName' => '2nd Invoice No.',
                'field' => 'inv2_no',
                'width' => 150,
            ],
            [
                'headerName' => '2nd Invoice Name',
                'field' => 'inv2_name',
                'width' => 200,
            ],
            [
                'headerName' => '2nd Provisioning (GST)',
                'field' => 'inv2_prov_gst',
                'width' => 180,
            ],
            ['headerName' => 'Total Provisioning (with GST)',     'field' => 'total_prov_with_gst', 'width' => 130],
            [
                'headerName' => 'Total Provisioning (without GST)',
                'field' => 'total_prov_without_gst',
                'width' => 200,
            ],
            [
                'headerName' => 'Consideration (without GST)',
                'field' => 'consideration_no_gst',
                'width' => 200,
            ],
            ['headerName' => 'Difference (without GST)',     'field' => 'diff_without_gst', 'width' => 130],

            ['headerName' => 'Provisioning % (without GST)',     'field' => 'prov_prc_without_gst', 'width' => 130],

        ];

        return array_merge($columns, $extraColumns);
    }

    private function getTransactionGridColumns(): array
    {
        return [
            // ----- BASIC INFO -----
            ['headerName' => 'S.No.', 'field' => 'serial_no', 'width' => 70, 'sortable' => false, 'filter' => false],
            ['headerName' => 'VOTF No.', 'field' => 'votf_no', 'width' => 130],
            ['headerName' => 'Chassis No.', 'field' => 'chassis_no', 'width' => 140],
            ['headerName' => 'Invoice Type', 'field' => 'invoice_type', 'width' => 120, 'filter' => true],
            ['headerName' => 'Invoice No.', 'field' => 'inv_no', 'width' => 120],
            ['headerName' => 'Invoice Date', 'field' => 'inv_date', 'width' => 120, 'type' => 'date'],

            // ----- X8 Fields -----
            ['headerName' => 'X8 Enq Date', 'field' => 'x8_enq_date', 'width' => 120, 'type' => 'date'],
            ['headerName' => 'X8 Booking No.', 'field' => 'x8_booking_no', 'width' => 120],
            ['headerName' => 'X8 Booking Date', 'field' => 'x8_booking_date', 'width' => 130, 'type' => 'date'],

            // ----- DMS -----
            ['headerName' => 'DMS OTF No.', 'field' => 'dms_otf', 'width' => 130],
            ['headerName' => 'DMS Enq No.', 'field' => 'dms_no', 'width' => 130],

            // ----- Customer -----
            ['headerName' => 'Customer Name', 'field' => 'customer_name', 'width' => 160, 'filter' => true],
            ['headerName' => 'Contact No.', 'field' => 'mobile', 'width' => 120],
            ['headerName' => 'Tehsil', 'field' => 'customer_tehsil', 'width' => 120],
            ['headerName' => 'District', 'field' => 'customer_district', 'width' => 120],
            ['headerName' => 'Customer Category', 'field' => 'customer_category', 'width' => 150, 'filter' => true],
            ['headerName' => 'Retail Category', 'field' => 'retail_category', 'width' => 130, 'filter' => true],
            ['headerName' => 'Customer GSTIN', 'field' => 'gstn', 'width' => 140],
            ['headerName' => 'Customer PAN', 'field' => 'pan_no', 'width' => 120],
            ['headerName' => 'Customer Adhaar', 'field' => 'adhar_no', 'width' => 140],

            // ----- Vehicle -----
            ['headerName' => 'Segment', 'field' => 'segment', 'width' => 120, 'filter' => true],
            ['headerName' => 'Model', 'field' => 'model', 'width' => 140, 'filter' => true],
            ['headerName' => 'Variant', 'field' => 'variant', 'width' => 140, 'filter' => true],
            ['headerName' => 'Color', 'field' => 'color', 'width' => 100],
            ['headerName' => 'Body Type', 'field' => 'body_type', 'width' => 120, 'filter' => true],
            ['headerName' => 'Sale Type', 'field' => 'sale_type', 'width' => 130, 'filter' => true],
            ['headerName' => 'Registration Type', 'field' => 'registration_type', 'width' => 140, 'filter' => true],
            ['headerName' => 'Registration Category', 'field' => 'registration_category', 'width' => 160, 'filter' => true],
            ['headerName' => 'Permit', 'field' => 'permit', 'width' => 200, 'filter' => true],

            // ----- SC (Sales Consultant) -----
            ['headerName' => 'SC Name', 'field' => 'fsc_name', 'width' => 150, 'filter' => true],
            ['headerName' => 'SC Mile ID', 'field' => 'fsc_mile_id', 'width' => 130],
            ['headerName' => 'SC Branch', 'field' => 'sc_branch', 'width' => 130],
            ['headerName' => 'SC Location', 'field' => 'sc_location', 'width' => 140],

            // ----- PMS SC -----
            ['headerName' => 'PMS SC Name', 'field' => 'pms_sc_name', 'width' => 150, 'filter' => true],
            ['headerName' => 'PMS SC Mile ID', 'field' => 'pms_sc_mile_id', 'width' => 140],

            // ----- DSA -----
            ['headerName' => 'DSA Retail', 'field' => 'dsa_retail', 'width' => 110, 'filter' => true],
            ['headerName' => 'DSA Name', 'field' => 'dsa_name', 'width' => 160, 'filter' => true],
            ['headerName' => 'DSA Location', 'field' => 'dsa_location', 'width' => 150],
            ['headerName' => 'Exchange', 'field' => 'exchange', 'width' => 120, 'filter' => true],
            ['headerName' => 'In-House RTO', 'field' => 'in_house_rto', 'width' => 120, 'filter' => true],
            ['headerName' => 'In-House Insurance', 'field' => 'in_house_insurance', 'width' => 140, 'filter' => true],

            // ----- PRICE DETAILS -----
            ['headerName' => 'Ex SR Price', 'field' => 'ex_showroom_price', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Insurance', 'field' => 'insurance_amount', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Registration', 'field' => 'registration_amount', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Accessories', 'field' => 'accessories_amount', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Maxicare', 'field' => 'maxicare', 'width' => 110, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'VLTD Device (GPS)', 'field' => 'vltd_device', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Ceramic Coating', 'field' => 'coating_price', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'PPF', 'field' => 'ppf', 'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'RTO Yellow Tape', 'field' => 'rto_yellow_tape', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Total Receivable', 'field' => 'total_receivable', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right fw-bold'],

            // ----- DISCOUNTS -----
            ['headerName' => 'Disc 1', 'field' => 'disc_1', 'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Disc 2', 'field' => 'disc_2', 'width' => 100, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Total Disc', 'field' => 'total_discount', 'width' => 120, 'type' => 'number', 'cellClass' => 'text-right fw-bold'],
            ['headerName' => 'Net Receivable', 'field' => 'net_receivable', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right fw-bold'],

            // ----- FINANCE -----
            ['headerName' => 'Financier Name', 'field' => 'financier', 'width' => 160, 'filter' => true],
            ['headerName' => 'Loan Amount', 'field' => 'loan_amount', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'File Charge', 'field' => 'file_charge', 'width' => 120, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Payment Made to Financier by Customer', 'field' => 'margin_money', 'width' => 220, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Financier Subvention Amount', 'field' => 'financier_subvention', 'width' => 190, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'DO Amount', 'field' => 'do_amount', 'width' => 130, 'type' => 'number', 'cellClass' => 'text-right'],

            // ----- RECEIPTS -----
            ['headerName' => 'Receipt No. 1', 'field' => 'receipt_no_1', 'width' => 130],
            ['headerName' => 'Receipt Date 1', 'field' => 'receipt_date_1', 'width' => 130, 'type' => 'date'],
            ['headerName' => 'Receipt Amount 1', 'field' => 'receipt_amount_1', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Receipt No. 2', 'field' => 'receipt_no_2', 'width' => 130],
            ['headerName' => 'Receipt Date 2', 'field' => 'receipt_date_2', 'width' => 130, 'type' => 'date'],
            ['headerName' => 'Receipt Amount 3', 'field' => 'receipt_amount_3', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Total Receipt Amount', 'field' => 'receipt_total', 'width' => 160, 'type' => 'number', 'cellClass' => 'text-right fw-bold'],

            // ----- BALANCE -----
            ['headerName' => 'Expected Balance', 'field' => 'expected_balance', 'width' => 150, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'DO Settlement Difference', 'field' => 'do_settlement_difference', 'width' => 180, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Discount Through JV', 'field' => 'discount_through_jv', 'width' => 160, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Final Balance', 'field' => 'final_balance', 'width' => 140, 'type' => 'number', 'cellClass' => 'text-right fw-bold'],

            // ----- DELIVERY -----
            ['headerName' => 'DO Number (Delivery Time)', 'field' => 'do_number_delivery', 'width' => 180],
            ['headerName' => 'DO Number (TA Statement)', 'field' => 'do_number_ta', 'width' => 180],
            ['headerName' => 'DO Amount (TA Statement)', 'field' => 'do_amount_ta', 'width' => 180, 'type' => 'number', 'cellClass' => 'text-right'],

            // ----- OTHER RECEIVABLES -----
            ['headerName' => 'Brokerage Amount', 'field' => 'brokerage_amount', 'width' => 150, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Other Receivable', 'field' => 'other_discount_receivable', 'width' => 160, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'M&M Support Receivable', 'field' => 'mm_support_receivable', 'width' => 180, 'type' => 'number', 'cellClass' => 'text-right'],
            ['headerName' => 'Liquidation Scheme Receivable', 'field' => 'liquidation_scheme_receivable', 'width' => 190, 'type' => 'number', 'cellClass' => 'text-right'],

            // ----- ACTION -----
            ['headerName' => 'Action', 'field' => 'action', 'width' => 150, 'sortable' => false, 'filter' => false, 'cellRenderer' => 'htmlRenderer', 'pinned' => 'right'],
        ];
    }

    private function getBodyTypeLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = ['1' => 'Complete', '2' => 'CBC'];

        return $map[$value] ?? 'N/A';
    }

    private function getSaleTypeLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = ['1' => 'Within State', '2' => 'Outside State'];

        return $map[$value] ?? 'N/A';
    }

    private function getPermitLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = [
            '1' => 'Private - U/C (4 Wheeler)',
            '2' => 'Private - BH (4 Wheeler)',
            '3' => 'Private - EV (4 Wheeler)',
            '4' => 'Goods - G (4 Wheeler)',
            '5' => 'Goods - G 3 Ton+ (4 Wheeler)',
            '6' => 'Goods - G (3 Wheeler)',
            '7' => 'Goods - G EV (3 Wheeler)',
            '8' => 'Taxi - T (4 Wheeler)',
            '9' => 'Passenger - P (3 Wheeler)',
            '10' => 'Passenger - P EV (3 Wheeler)',
            '11' => 'Ambulance (Misc.)',
        ];

        return $map[$value] ?? 'N/A';
    }

    private function getRegNoTypeLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = ['1' => 'Regular', '2' => 'BH', '3' => 'Special'];

        return $map[$value] ?? 'N/A';
    }

    private function getInsuranceTypeLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = [
            '1' => 'Standard',
            '2' => 'Nil Dep',
            '3' => 'Base (Nil Dep + Consumables)',
            '4' => 'Higher (Nil Dep + Consumables + Add Ons)',
        ];

        return $map[$value] ?? 'N/A';
    }

    private function getRegistrationTypeLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

        return $map[$value] ?? 'N/A';
    }

    private function getDeliveryOptionLabel($value)
    {
        if (empty($value)) {
            return 'N/A';
        }
        $map = [
            '1' => 'Payment',
            '2' => 'DO',
            '3' => 'Sanction Letter',
            '4' => 'Mail',
            '5' => 'Whatsapp',
        ];

        return $map[$value] ?? 'N/A';
    }

    private function getAccessoriesList($accessories)
    {
        if (empty($accessories)) {
            return 'N/A';
        }

        $accIds = [];
        if (is_array($accessories)) {
            $accIds = $accessories;
        } elseif (is_string($accessories)) {
            $accIds = array_filter(array_map('trim', explode(',', $accessories)));
        }

        if (empty($accIds)) {
            return 'N/A';
        }

        $names = [];
        foreach ($accIds as $accId) {
            $accessory = DB::table('xlr8_vehicle_accessories')
                ->where('part_no', trim($accId))
                ->first();
            if ($accessory) {
                $names[] = $accessory->item;
            }
        }

        return ! empty($names) ? implode(', ', $names) : 'N/A';
    }

    private function formatInsuranceCovers($insuranceCovers)
    {
        if (empty($insuranceCovers) || ! is_array($insuranceCovers)) {
            return 'N/A';
        }

        $formatted = [];
        foreach ($insuranceCovers as $cover) {
            if (is_array($cover)) {
                $name = trim($cover['name'] ?? '');
                $price = (float) ($cover['price'] ?? 0);
                if ($name) {
                    $formatted[] = $name.($price > 0 ? ' (₹'.number_format($price, 2).')' : '');
                }
            } elseif (is_string($cover)) {
                $formatted[] = $cover;
            }
        }

        return ! empty($formatted) ? implode(', ', $formatted) : 'N/A';
    }

    private function getConsultantDetails($consultantCode)
    {
        if (empty($consultantCode)) {
            return ['name' => 'N/A', 'mile_id' => 'N/A'];
        }

        $consultant = DB::table('xlr8_admin_person')
            ->where('person_code', $consultantCode)
            ->first();

        if (! $consultant) {
            return ['name' => 'N/A', 'mile_id' => 'N/A'];
        }

        return [
            'name' => $consultant->display_name ?? 'N/A',
            'mile_id' => $consultant->employee_code ?? 'N/A',
        ];
    }

    private function getStatusBadge($status)
    {
        return match ((int) $status) {
            1 => 'Live',
            2 => 'Invoiced',
            3 => 'Cancelled',
            4 => '<span class="badge badge-warning">Refund Queued</span>',
            5 => '<span class="badge badge-info">Refunded</span>',
            6 => 'On Hold',
            7 => '<span class="badge badge-dark">Refund Rejected</span>',
            8 => 'Pending',
            default => '<span class="badge badge-light">Unknown</span>',
        };
    }

    public function showInvoiced($id)
    {
        if (! backpack_user()->can('SLS_BKNG_INVOICE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('show');

        $entry = $this->crud->getEntry($id);
        if ((int) $entry->status !== 2) {
            abort(404, 'Yeh booking invoiced nahi hai.');
        }

        return $this->getFullBookingData($id, 'show-invoiced');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.booking.list');
    }

    /**
     * Builds the "Action" column's HTML for one booking-list row.
     *   - $fullActions = true  → the Live-bookings action set (View / Add
     *     Amount / Edit / VOTF), including the col_type-based redirect of
     *     "Add Amount" to the pending-edit flow when the booking isn't
     *     fully paid yet.
     *   - $fullActions = false → a single "View" button, used by the
     *     Hold / Invoiced / Cancelled views (those statuses are read-only
     *     from this screen).
     * $showRouteSuffix lets the "View" link point at a different route
     * per status (plain "show" vs "invoiced-show" for Invoiced bookings).
     */
    private function buildBookingRowActions($booking, string $showRouteSuffix, bool $fullActions): string
    {
        $showUrl = backpack_url("sales/booking/{$booking->id}/{$showRouteSuffix}");

        if (! $fullActions) {
            return '
        <div class="d-flex justify-content-center gap-2" role="group" aria-label="Actions">
            <a href="'.$showUrl.'"
               class="btn btn-sm btn-primary" title="View Details">
                View
            </a>
        </div>';
        }

        $editUrl = backpack_url("sales/booking/{$booking->id}/edit");
        $amountUrl = backpack_url("sales/booking/{$booking->id}/add-amount");

        if (in_array($booking->col_type, [2, 3])) {
            $totalPaid = $booking->totalReceivedAmount();

            if ($booking->booking_amount > $totalPaid) {
                $amountUrl = backpack_url("sales/booking/{$booking->id}/pending-edit");
            }
        }

        $quotationAction = '
                <a href="javascript:void(0);"
                    onclick="openVOTF('.$booking->id.')"
                    class="btn btn-sm btn-success py-1 px-2"
                    title="VOTF">
                        VOTF
                    </a>';

        return '
                <div class="d-flex gap-2">

                    <a href="'.$showUrl.'"
                    class="btn btn-sm btn-primary py-1 px-2"
                    title="View">
                        View
                    </a>

                    <a href="'.$amountUrl.'"
                    class="btn btn-sm btn-success py-1 px-2"
                    title="Add Amount">
                        Add ₹
                    </a>

                    <a href="'.$editUrl.'"
                    class="btn btn-sm btn-info py-1 px-2"
                    title="Edit">
                        Edit
                    </a>

                    '.$quotationAction.'

                </div>
            ';
    }

    /**
     * Shared implementation behind index()/hold()/invoiced()/cancelled().
     * ---------------------------------------------------------------------
     * These four screens used to be ~90 lines each with ~95% identical
     * code (only the status filter, page title, action-button set and
     * "View" route differed) — over 300 lines of copy-paste that has now
     * collapsed into this one method plus four 3-line wrappers below.
     * Behaviour is unchanged: same base query, same pagination size (50),
     * same grid/column/pagination payload shape passed to the view.
     *
     * @param  array  $statusFilter  values matched against bookings.status (whereIn)
     * @param  string  $title  page title shown in the card header
     * @param  string  $showRouteSuffix  'show' or 'invoiced-show' — the "View" link's route
     * @param  bool  $fullActions  true only for the Live-bookings screen (index)
     */
    private function renderBookingListing(array $statusFilter, string $title, string $showRouteSuffix, bool $fullActions): View
    {
        $this->crud->hasAccessOrFail('list');
        $this->crud->setListView('admin.booking.list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = $title;

        $query = $this->getBaseQuery()
            ->whereIn('bookings.status', $statusFilter)
            ->orderBy('bookings.id', 'desc');

        $paginatedBookings = $query->paginate(50);

        // Batch-preload per-page reference data once, instead of paying
        // for it inside mapBookingForGrid() on every one of the 50 rows.
        $lookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($booking, $index) use ($paginatedBookings, $lookups, $showRouteSuffix, $fullActions) {
            $mapped = $this->mapBookingForGrid($booking, $lookups);

            $mapped->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;
            $mapped->action = $this->buildBookingRowActions($booking, $showRouteSuffix, $fullActions);

            return $mapped;
        })->values();

        $columns = $this->getAgGridColumns();
        $columns[] = [
            'headerName' => 'Action',
            'field' => 'action',
            'width' => $fullActions ? 170 : 160,
            'minWidth' => 140,
            'sortable' => false,
            'filter' => false,
            'resizable' => false,
            'cellRenderer' => 'htmlRenderer',
            'pinned' => 'right',
            'cellClass' => 'text-center p-0',
            'suppressSizeToFit' => true,
        ];

        $this->data['gridConfig'] = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['pagination'] = [
            'total' => $paginatedBookings->total(),
            'perPage' => $paginatedBookings->perPage(),
            'currentPage' => $paginatedBookings->currentPage(),
            'lastPage' => $paginatedBookings->lastPage(),
        ];

        return view('admin.booking.list', $this->data);
    }

    /** All Live Bookings (status: 1 = live, 8 = pending) — full action set. */
    public function index()
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->renderBookingListing([1, 8], 'All Live Bookings', 'show', true);
    }

    private function calculateAgeFromDob($dob)
    {
        if (! $dob) {
            return 'N/A';
        }

        try {
            $birthDate = Carbon::parse($dob);
            $age = $birthDate->diffInYears(Carbon::now());

            return (int) $age;
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    /** On-Hold Bookings (status: 6) — view-only action set. */
    public function hold()
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->renderBookingListing([6], 'On-Hold Bookings', 'show', false);
    }

    /** Invoiced Bookings (status: 2) — view-only action set, routes to invoiced-show. */
    public function invoiced()
    {
        if (! backpack_user()->can('SLS_BKNG_INVOICE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->renderBookingListing([2], 'Invoiced Bookings', 'invoiced-show', false);
    }

    /** Cancelled Bookings (status: 3) — view-only action set. */
    public function cancelled()
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        return $this->renderBookingListing([3], 'Cancelled Bookings', 'show', false);
    }

    protected function setupCreateOperation()
    {
        $quotation = null;
        if ($quotationId = request('quotation_id')) {
            $quotation = Quotation::with([
                'enquiry',
                'vehicleModel',
                'variant',
                'color',
            ])->findOrFail($quotationId);
        }

        // Fetch Enquiry directly if Process button was clicked or enquiry_id is passed
        $enquiry = null;
        if ($enquiryId = request('enquiry_id')) {
            $enquiry = Enquiry::find($enquiryId);
        } elseif ($quotation && $quotation->enquiry) {
            $enquiry = $quotation->enquiry;
        }

        // ===== MAXIMUM DATA ENRICHMENT & ALIASING FOR PRE-FILL =====
        if ($enquiry) {
            // Map alternate mobile correctly
            $enquiry->alt_mobile = $enquiry->alternate_mobile ?? null;

            $purchaseTypes = collect(
                OrgService::keywordValueByCode('PURCHASE_TYPE')
            );

            $crmPurchaseType = trim((string) ($enquiry->purchase_type_crm ?? ''));

            $matchedPurchaseType = null;

            if ($crmPurchaseType !== '') {
                $matchedPurchaseType = $purchaseTypes->first(function ($item) use ($crmPurchaseType) {
                    // Safely extract the code from array or object
                    $code = '';
                    if (is_array($item)) {
                        $code = $item['code'] ?? '';
                    } elseif (is_object($item)) {
                        $code = $item->code ?? '';
                    }

                    // Use a case-insensitive comparison
                    return strtoupper(trim((string) $code)) === strtoupper($crmPurchaseType);
                });
            }

            if ($matchedPurchaseType) {
                // Safely extract the value
                if (is_array($matchedPurchaseType)) {
                    $enquiry->purchase_type = $matchedPurchaseType['value'] ?? null;
                } elseif (is_object($matchedPurchaseType)) {
                    $enquiry->purchase_type = $matchedPurchaseType->value ?? null;
                }
            } else {
                // Fallback: If purchase_type_crm is already a text value, use it.
                // Otherwise, use the standard purchase_type field.
                $enquiry->purchase_type = $enquiry->purchase_type_crm ?: ($enquiry->purchase_type ?? null);
            }

            Log::info('🔄 [BOOKING CREATE] Purchase Type resolved', [
                'enquiry_id' => $enquiry->id,
                'purchase_type_crm' => $enquiry->purchase_type_crm,
                'resolved_type' => $enquiry->purchase_type,
            ]);

            // Prefer X8 SC over OEM SC to map into the Sales Consultant dropdown
            $enquiry->sc_code =
                $enquiry->x8_sc_code
                ?? $enquiry->sc_code
                ?? null;

            // If dealer branch/location is missing on enquiry,
            // dynamically resolve it from assigned Sales Consultant
            if (empty($enquiry->dealer_branch) && ! empty($enquiry->sc_code)) {
                $scUsers = OrgService::getUsers(desigCode: 'CNS');

                $matchedSc = collect($scUsers)
                    ->firstWhere('person_code', $enquiry->sc_code);

                if ($matchedSc) {
                    $enquiry->dealer_branch =
                        $matchedSc['primary_branch_code'] ?? null;

                    $enquiry->dealer_location =
                        $matchedSc['primary_loc_code'] ?? null;
                }
            }
        }
        // ==========================================================

        CRUD::setValidation(BookingRequest::class);
        $this->crud->setCreateView('admin.booking.add');

        $data = [];

        // ✅ ADD: Models, Variants, Colors for dropdowns
        $data['segments'] = CommonHelper::getVehicleSegments();
        $data['models'] = CommonHelper::getVehicleModels($quotation?->segment_code ?? $enquiry?->segment_code ?? null) ?? [];
        $data['variants'] = CommonHelper::getVehicleVariants($quotation?->model_code ?? $enquiry?->model_code ?? null) ?? [];
        $data['colors'] = CommonHelper::getVehicleColors($quotation?->variant_code ?? $enquiry?->variant_code ?? null) ?? [];
        $data['accessories_dropdown'] = Accessory::getAccessories(
            $quotation?->segment_code ?? $enquiry?->segment_code ?? null,
            $quotation?->model_code ?? $enquiry?->model_code ?? null,
            $quotation?->variant_code ?? $enquiry?->variant_code ?? null
        );

        $data['branches'] = collect(CommonHelper::getBranches())->map(fn ($b) => (object) $b);
        $data['location'] = collect(CommonHelper::getLocations())->map(fn ($l) => (object) $l);
        $data['allusers'] = OrgService::getUsers(deptCode: 'SLS');
        $data['financiers'] = collect(XlFinancier::select('id', 'name', 'short_name')->get()->toArray())->map(fn ($f) => (object) $f);
        $data['salesconsultants'] = OrgService::getUsers(desigCode: 'CNS');

        $data['person_id'] = backpack_auth()->id();

        $data['dsa_details'] = XL_DSA_MASTER::all()->map(function ($dsa) {
            return (object) [
                'id' => $dsa->id,
                'name' => $dsa->name,
                'mobile' => $dsa->mobile,
                'email' => $dsa->email,
                'location' => $dsa->dlocation,
            ];
        });

        $data['enum_master'] = OrgService::keywordValueByCode('EXISTING_CAR_OEM');

        $data['quotation'] = $quotation;
        $data['enquiry'] = $enquiry; // Pass enquiry object to view
        $data['care_of_type'] = $enquiry?->care_of_type;
        $data['care_of'] = $enquiry?->care_of;

        $data['saleconsultant'] = $enquiry?->x8_sc_code
            ?? $enquiry?->sc_code
            ?? '';

        // ========== QUOTATION / ENQUIRY DATA ARRAY ==========
        $data['q'] = [];

        if ($quotation) {
            $quotationData = $quotation->standard_data ?? [];
            if (is_string($quotationData)) {
                $quotationData = json_decode($quotationData, true) ?? [];
            }
            $data['q'] = is_array($quotationData) ? $quotationData : [];
        }

        // --- NEW: INJECT ALL ENQUIRY DATA SO THE FORM PRE-FILLS CORRECTLY ---
        $data['segment_code'] = $data['q']['segment_code'] ?? $quotation?->segment_code ?? $enquiry?->segment_code;
        $data['model_code'] = $data['q']['model_code'] ?? $quotation?->model_code ?? $enquiry?->model_code;
        $data['variant_code'] = $data['q']['variant_code'] ?? $quotation?->variant_code ?? $enquiry?->variant_code;
        $data['color_code'] = $data['q']['color_code'] ?? $quotation?->color_code ?? $enquiry?->color_code;

        if ($enquiry) {
            $data['q']['name'] = $enquiry->name;
            $data['q']['care_of_type'] = $enquiry->care_of_type;
            $data['q']['care_of'] = $enquiry->care_of;
            $data['q']['email'] = $enquiry->email;
            $data['q']['mobile'] = $enquiry->mobile;
            $data['q']['alt_mobile'] = $enquiry->alternate_mobile;
            $data['q']['gender'] = $enquiry->gender;
            $data['q']['occ'] = $enquiry->occupation_type;
            $data['q']['c_dob'] = $enquiry->dob;
            $data['q']['buyertype'] = $enquiry->purchase_type;
            $data['q']['exist_oem1'] = $enquiry->brand_make;
            $data['q']['vh1_detail'] = $enquiry->brand_model;
            $data['q']['exist_oem2'] = $enquiry->consid_brand2;
            $data['q']['vh2_detail'] = $enquiry->consid_model2;
            $data['q']['panno'] = $enquiry->pan_no;
            $data['q']['adharno'] = $enquiry->adhar_no;
        }
        // ======================================

        $this->data['data'] = $data;
        $this->data['enquiry'] = $enquiry;
    }

    protected function setupUpdateOperation()
    {
        $id = $this->crud->getCurrentEntryId() ?? request()->id;

        // ==========================================================
        // 1. GET ACTUAL BOOKING RECORD
        // ==========================================================
        $entry = $this->crud->getEntry($id);
        $latestPayment = Bookingamount::where('bid', $id)
            ->latest('id')
            ->first();

        $finance = XFinance::where('bid', $id)->first();
        $data['finance'] = $finance;
        $data['loan_status'] = $finance?->loan_status;

        $linkedEnquiry = null;

        if ($entry && $entry->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($entry->enq_no);
        }

        if ($linkedEnquiry) {
            // Map alternate mobile correctly
            $linkedEnquiry->alt_mobile = $linkedEnquiry->alternate_mobile ?? null;

            // ==========================================================
            // PURCHASE TYPE
            // CRE Input stores PURCHASE_TYPE CODE.
            // Booking dropdown expects exact text values.
            // ==========================================================

            $purchaseTypeMap = [
                'FIRST_TIME_BUY' => 'First Time Buy',
                'ADDITIONAL_BUY' => 'Additional Buy',
                'EXCHANGE_BUY' => 'Exchange Buy',
                'SCRAPPAGE' => 'Scrappage',
                'NO_CONSIDERATION' => 'No Consideration',
            ];

            $crmPurchaseType = strtoupper(
                trim((string) ($linkedEnquiry->purchase_type_crm ?? ''))
            );

            if (! empty($crmPurchaseType)) {

                $linkedEnquiry->purchase_type =
                    $purchaseTypeMap[$crmPurchaseType]
                    ?? $linkedEnquiry->purchase_type_crm;

            } else {

                $linkedEnquiry->purchase_type =
                    $linkedEnquiry->purchase_type ?? null;
            }

            Log::info('🔄 [BOOKING EDIT] Purchase Type resolved', [
                'enquiry_id' => $linkedEnquiry->id,
                'purchase_type_crm' => $linkedEnquiry->purchase_type_crm,
                'resolved_type' => $linkedEnquiry->purchase_type,
            ]);

            // Prefer X8 SC over OEM SC to map into the Sales Consultant dropdown
            $linkedEnquiry->sc_code = $linkedEnquiry->x8_sc_code
                ?? $linkedEnquiry->sc_code
                ?? null;

            // If dealer branch/location is missing on enquiry, dynamically resolve it from the assigned Sales Consultant
            if (empty($linkedEnquiry->dealer_branch) && ! empty($linkedEnquiry->sc_code)) {
                $scUsers = OrgService::getUsers(desigCode: 'CNS');
                $matchedSc = collect($scUsers)->firstWhere('person_code', $linkedEnquiry->sc_code);
                if ($matchedSc) {
                    $linkedEnquiry->dealer_branch = $matchedSc['primary_branch_code'] ?? null;
                    $linkedEnquiry->dealer_location = $matchedSc['primary_loc_code'] ?? null;
                }
            }
        }

        $data['saleconsultant'] = $linkedEnquiry?->x8_sc_code
            ?? $linkedEnquiry?->sc_code
            ?? '';

        CRUD::setValidation(BookingRequest::class);
        $this->crud->setEditView('admin.booking.add');

        \Log::info('EDIT FORM PREFILL DEBUG', [
            'booking_id' => $id,
            'enq_no' => $entry->enq_no,
            'linked_enquiry_id' => $linkedEnquiry?->id,
            'x8_sc_code' => $linkedEnquiry?->x8_sc_code,
            'sc_code_after_alias' => $linkedEnquiry?->sc_code,
            'saleconsultant_data' => $data['saleconsultant'],
        ]);

        // ==========================================================
        // 3. DROPDOWN DATA
        // ==========================================================
        $data = [];
        $data['payment'] = $latestPayment;
        $data['payment_mode'] = $latestPayment?->mode;

        $data['finance'] = $finance;
        $data['loan_status'] = $finance?->loan_status;

        $data['branches'] = collect(CommonHelper::getBranches())
            ->map(fn ($b) => (object) $b);

        $data['locations'] = collect(CommonHelper::getLocations())
            ->map(fn ($l) => (object) $l);

        $data['allusers'] = OrgService::getUsers(deptCode: 'SLS');

        $data['financiers'] = collect(
            XlFinancier::select('id', 'name', 'short_name')
                ->get()
                ->toArray()
        )->map(fn ($f) => (object) $f);

        // ==========================================================
        // SALES CONSULTANTS
        // ==========================================================

        $data['salesconsultants'] = collect(
            OrgService::usersByDesignation('CNS') ?? []
        )->map(function ($s) {
            return is_array($s) ? (object) $s : $s;
        });

        // Get saved consultant code from enquiry
        $savedScCode = trim((string) (
            $linkedEnquiry?->x8_sc_code
                ?? $linkedEnquiry?->sc_code
                ?? ''
        ));

        Log::info('SALES CONSULTANT EDIT DEBUG', [
            'booking_id' => $id,
            'enq_no' => $entry->enq_no,
            'linked_enquiry' => $linkedEnquiry?->id,
            'saved_sc_code' => $savedScCode,
            'consultant_count' => $data['salesconsultants']->count(),
        ]);

        if ($savedScCode !== '') {

            $existsInList = collect($data['salesconsultants'])->contains(function ($c) use ($savedScCode) {
                $code = is_object($c)
                    ? ($c->person_code ?? '')
                    : ($c['person_code'] ?? '');

                return strtoupper(trim((string) $code)) ===
                    strtoupper(trim((string) $savedScCode));
            });

            Log::info('SALES CONSULTANT LIST CHECK', [
                'saved_code' => $savedScCode,
                'exists_in_list' => $existsInList,
            ]);

            // If OrgService did not return the saved consultant,
            // add that consultant manually.
            if (! $existsInList) {

                $person = DB::table('xlr8_admin_person')
                    ->where('person_code', $savedScCode)
                    ->first();

                $employee = DB::table('xlr8_admin_employee')
                    ->where('person_code', $savedScCode)
                    ->first();

                Log::info('SALES CONSULTANT FALLBACK LOOKUP', [
                    'person_found' => $person ? true : false,
                    'employee_found' => $employee ? true : false,
                    'person_code' => $savedScCode,
                ]);

                if ($person) {

                    $data['salesconsultants']->push((object) [
                        'person_code' => $person->person_code,
                        'display_name' => $person->display_name ?? $savedScCode,
                        'employee_code' => $employee?->code ?? 'N/A',
                        'is_fallback' => true,
                    ]);
                }
            }
        }

        // IMPORTANT:
        // Explicitly pass the selected consultant to Blade.
        $data['saleconsultant'] = $savedScCode;

        Log::info('FINAL SALES CONSULTANT DATA', [
            'selected' => $data['saleconsultant'],
            'count' => $data['salesconsultants']->count(),
        ]);

        $data['dsa_details'] = XL_DSA_MASTER::all()
            ->map(fn ($dsa) => (object) [
                'id' => $dsa->id,
                'name' => $dsa->name,
                'mobile' => $dsa->mobile,
                'location' => $dsa->dlocation,
            ]);

        // ==========================================================
        // 4. VEHICLE DROPDOWNS
        // USE BOOKING VALUES
        // ==========================================================
        $data['segments'] = CommonHelper::getVehicleSegments();

        $data['models'] = CommonHelper::getVehicleModels(
            $entry->segment_code ?? null
        ) ?? [];

        $data['variants'] = CommonHelper::getVehicleVariants(
            $entry->model_code ?? null
        ) ?? [];

        $data['colors'] = CommonHelper::getVehicleColors(
            $entry->variant_code ?? null
        ) ?? [];

        // ==========================================================
        // 5. ACCESSORIES
        // ==========================================================
        $data['accessories_dropdown'] = Accessory::getAccessories(
            $entry->segment_code ?? null,
            $entry->model_code ?? null,
            $entry->variant_code ?? null
        );

        // ==========================================================
        // 6. EXISTING CAR OEM
        // ==========================================================
        $data['enum_master'] =
            OrgService::getKeyValuesByCode('EXISTING_CAR_OEM');

        // ==========================================================
        // 7. COLLECTOR NAME
        // ==========================================================
        $data['collector_name'] = OrgService::getUserNameByCode(
            $entry->col_by,
            $entry->col_type
        ) ?? 'N/A';

        // ==========================================================
        // 8. PASS ENQUIRY SEPARATELY
        // DO NOT MERGE IT INTO $entry
        // ==========================================================
        $data['enquiry'] = $linkedEnquiry;

        // ==========================================================
        // 9. QUOTATION
        // ==========================================================
        $quotation = null;

        if (! empty($entry->quotation_id)) {
            $quotation = Quotation::find($entry->quotation_id);
        }

        $data['quotation'] = $quotation;

        // ==========================================================
        // 10. QUOTATION DATA
        // ONLY FOR OPTIONAL FALLBACK
        // ==========================================================
        $data['q'] = [];

        if ($quotation) {
            $quotationData = $quotation->standard_data ?? [];

            if (is_string($quotationData)) {
                $quotationData = json_decode($quotationData, true) ?? [];
            }

            $data['q'] = is_array($quotationData)
                ? $quotationData
                : [];
        }

        // ==========================================================
        // 11. STORE DATA
        // ==========================================================
        $this->data['entry'] = $entry;
        $this->data['data'] = $data;

        // Also expose enquiry to view
        $this->data['enquiry'] = $linkedEnquiry;

        $this->crud->set('data', $data);

        return $this->data;
    }

    public function addAmountForm($id)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $receipts = Bookingamount::where('bid', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.booking.amount', compact('booking', 'receipts'));
    }

    public function addAmount(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        try {
            $booking = Booking::findOrFail($id);

            if ($request->filled('bid') && $request->bid != $id) {
                throw new Exception('Booking ID mismatch.');
            }

            $validator = Validator::make($request->all(), [
                'receipt_date' => 'required',
                'reciept_no' => [
                    'required',
                    'string',
                    'max:255',

                ],
                'amount' => 'required|numeric|min:0.01',
                'amount_proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
                'mode' => 'required|in:Cash,Cheque,Bank Transfer,UPI',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors($validator)
                    ->with('error', $validator->errors()->first());
            }

            return DB::transaction(function () use ($request, $booking) {

                $tempDir = public_path('Uploads/temp/');
                if (! File::exists($tempDir)) {
                    File::makeDirectory($tempDir, 0755, true);
                }

                $file = $request->file('amount_proof');
                $ext = $file->extension();
                $fileName = 'tf_ap_'.date('d-m-Y_His').'.'.$ext;

                $file->move($tempDir, $fileName);

                $amountRecord = new Bookingamount;
                $amountRecord->bid = $booking->id;
                $amountRecord->date = Carbon::parse($request->receipt_date)->format('Y-m-d');
                $amountRecord->amount = $request->amount;
                $amountRecord->type_number = $request->reciept_no;
                $amountRecord->mode = $request->mode;
                $amountRecord->save();

                $amountRecord->addMedia($tempDir.$fileName)
                    ->toMediaCollection('amount-proof');

                $remarks = [];

                $oldReceipt = $booking->receipt_no;
                $oldDate = $booking->receipt_date;
                $newAmount = $request->amount;
                $newReceipt = $request->reciept_no;
                $newDate = Carbon::parse($request->receipt_date)->format('Y-m-d');

                if ($oldReceipt !== $newReceipt) {
                    $remarks[] = 'Receipt No. changed from '.($oldReceipt ?? 'N/A')." to $newReceipt";
                }
                if ($oldDate !== $newDate) {
                    $remarks[] = 'Receipt Date changed from '.($oldDate ?? 'N/A')." to $newDate";
                }
                if ($newAmount > 0) {
                    $remarks[] = "Amount received: $newAmount";
                }

                $wasDummy = strtolower($booking->b_type ?? '') === 'dummy';
                if ($wasDummy) {
                    $remarks[] = 'Booking activated from Dummy to Active';
                }

                $oldBookingAmount = $booking->booking_amount ?? 0;
                $booking->receipt_no = $newReceipt;
                $booking->receipt_date = $newDate;
                $booking->booking_amount = $oldBookingAmount + $newAmount;
                $booking->b_type = 'Active';

                $remarks[] = "Booking amount updated from $oldBookingAmount to {$booking->booking_amount}";

                if ($wasDummy) {
                    $pending = 0;
                    $pendingFields = [];

                    if ($booking->b_mode === 'Online' && empty($booking->online_bk_ref_no)) {
                        $pending++;
                        $pendingFields[] = 'Online booking reference number';
                    }
                    if (empty($booking->pan_no)) {
                        $pending++;
                        $pendingFields[] = 'PAN number';
                    }
                    if (empty($booking->adhar_no)) {
                        $pending++;
                        $pendingFields[] = 'Aadhar number';
                    }
                    if (empty($booking->dms_no)) {
                        $pending++;
                        $pendingFields[] = 'Sales force number';
                    }
                    if (empty($booking->dms_otf)) {
                        $pending++;
                        $pendingFields[] = 'DMS OTF';
                    }
                    if (empty($booking->otf_date)) {
                        $pending++;
                        $pendingFields[] = 'DMS OTF Date';
                    }
                    if (empty($booking->dms_so)) {
                        $pending++;
                        $pendingFields[] = 'DMS SO number';
                    }

                    $booking->pending = $pending;
                    $booking->pending_remark = implode(', ', $pendingFields);
                    $booking->status = $pending > 0 ? 8 : 0;
                }

                $booking->save();

                $history = $booking->addHistory(
                    'commented',
                    'Additional Amount Added',
                    'Additional amount of ₹'.number_format($newAmount, 2).
                        ' added. Booking amount changed from ₹'.
                        number_format($oldBookingAmount, 2).
                        ' to ₹'.
                        number_format($booking->booking_amount, 2),
                    [
                        'receipt_no' => $newReceipt,
                        'receipt_date' => $newDate,
                        'amount_added' => $newAmount,
                        'mode' => $request->mode,
                        'old_amount' => $oldBookingAmount,
                        'new_total_amount' => $booking->booking_amount,
                    ],
                    null,
                    backpack_user()
                );
                if ($wasDummy) {

                    $booking->addHistory(
                        'commented',
                        'Dummy Entry Changed To Active Entry',
                        $request->remark ?: 'Booking activated from Dummy to Active.',
                        [
                            'receipt_no' => $newReceipt,
                            'receipt_date' => $newDate,
                            'amount' => $newAmount,
                            'old_type' => 'Dummy',
                            'new_type' => 'Active',
                        ],
                        null,
                        backpack_user()
                    );
                }

                if (File::exists($tempDir.$fileName)) {
                    File::delete($tempDir.$fileName);
                }

                return redirect(backpack_url('sales/booking'))
                    ->with('success', 'Amount & receipt added successfully!');
            });
        } catch (Exception $e) {
            Log::error('addAmount failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function addReceipt(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'hidden_receipt_date' => 'required|date_format:Y-m-d',
            'reciept_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('xlr8_booking_amount', 'type_number')
                    ->whereNull('deleted_at')
                    ->ignore($request->input('receipt_id')),
            ],
            'amount' => 'required|numeric|min:0.01',
            'amount_proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'mode' => 'required|in:Cash,Cheque,Bank Transfer,UPI',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', $validator->errors()->first());
        }

        try {
            return DB::transaction(function () use ($request, $booking) {

                $receiptDate = $request->input('hidden_receipt_date');
                $receiptNo = $request->input('reciept_no');
                $amount = (float) $request->input('amount');

                $receipt = new Bookingamount;
                $receipt->bid = $booking->id;
                $receipt->date = $receiptDate;
                $receipt->amount = $amount;
                $receipt->type_number = $receiptNo;
                $receipt->mode = $request->input('mode');
                $receipt->save();

                $receipt->addMediaFromRequest('amount_proof')
                    ->toMediaCollection('amount-proof');

                $remarks = [];
                if ($booking->receipt_no !== $receiptNo) {
                    $remarks[] = "Receipt No. updated to {$receiptNo}";
                }
                if ($booking->receipt_date !== $receiptDate) {
                    $remarks[] = "Receipt Date updated to {$receiptDate}";
                }
                $remarks[] = "Amount received: {$amount}";

                $booking->receipt_no = $receiptNo;
                $booking->receipt_date = $receiptDate;

                $totalReceived = $booking->totalReceivedAmount();

                if ($totalReceived >= ($booking->booking_amount ?? 0) && $booking->booking_amount > 0) {
                    if (strtolower($booking->b_type) === 'dummy') {
                        $remarks[] = 'Booking activated from Dummy to Active (Full amount received)';
                    }
                    $booking->b_type = 'Active';
                }

                if (strtolower($booking->b_type) === 'dummy') {
                    $pendingFields = [];
                    if ($booking->b_mode === 'Online' && empty($booking->online_bk_ref_no)) {
                        $pendingFields[] = 'Online booking reference number';
                    }
                    if (empty($booking->pan_no)) {
                        $pendingFields[] = 'PAN number';
                    }
                    if (empty($booking->adhar_no)) {
                        $pendingFields[] = 'Aadhar number';
                    }
                    if (empty($booking->dms_no)) {
                        $pendingFields[] = 'Sales force number';
                    }
                    if (empty($booking->dms_otf)) {
                        $pendingFields[] = 'DMS OTF';
                    }
                    if (empty($booking->otf_date)) {
                        $pendingFields[] = 'DMS OTF Date';
                    }
                    if (empty($booking->dms_so)) {
                        $pendingFields[] = 'DMS SO number';
                    }

                    $booking->pending = count($pendingFields);
                    $booking->pending_remark = implode(', ', $pendingFields);
                    $booking->status = count($pendingFields) > 0 ? 8 : 0;
                }

                $booking->save();

                $oldTotalReceived = $booking->totalReceivedAmount();

                $totalReceived = $oldTotalReceived + $amount;
                $history = $booking->addHistory(
                    'commented',
                    'Receipt Added',
                    'Receipt of ₹'.number_format($amount, 2).
                        ' added. Total collection changed from ₹'.
                        number_format($oldTotalReceived, 2).
                        ' to ₹'.
                        number_format($totalReceived, 2),
                    [
                        'receipt_no' => $receiptNo,
                        'receipt_date' => $receiptDate,
                        'receipt_amount' => $amount,
                        'old_total' => $oldTotalReceived,
                        'new_total' => $totalReceived,
                        'booking_type' => $booking->b_type,
                    ],
                    null,
                    backpack_user()
                );

                $redirectUrl = route('sales.booking.pending-edit', $booking->id);

                if ($request->boolean('pending_flag') || $request->has('pending_flag')) {
                    $redirectUrl .= '?pending_flag=1';
                }

                return redirect($redirectUrl)
                    ->with('success', 'Receipt added successfully!');
            });
        } catch (Exception $e) {
            Log::error('addReceipt failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'booking_id' => $id,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to add receipt. Please try again.');
        }
    }

    public function storeFollowup(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FOLLOWUP')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $user = backpack_auth()->user();
        $userId = $user?->id ?? 'guest/unknown';
        $userName = $user?->name ?? 'system/unknown';

        Log::info('BOOKING_FOLLOWUP_START', [
            'user_id' => $userId,
        ]);

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'remark' => 'required|string|min:3|max:1500',
            'status' => 'nullable|in:0,1,2,3,4,5,6,7,8',
            'fdoc' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'dept' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            Log::warning('BOOKING_FOLLOWUP_VALIDATION_FAILED', [
                'user_id' => $userId,
                'booking_id' => $request->input('id', 'missing'),
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['_token', 'fdoc']),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fill required fields correctly.');
        }

        try {
            $id = (int) $request->input('id');
            Log::info('BOOKING_FOLLOWUP_LOOKUP', ['booking_id' => $id, 'user_id' => $userId]);

            $booking = Booking::findOrFail($id);

            Log::debug('BOOKING_FOUND', [
                'id' => $booking->id,
                'current_status' => $booking->status,
                'current_inv_no' => $booking->inv_no,
                'dealer_inv_no' => $booking->dealer_inv_no,
                'dealer_status' => $booking->dealer_status ?? 'null',
            ]);

            $newStatus = (int) $request->input('status', 0);

            if ($newStatus === 2) {
                Log::info('INVOICE_FIELDS_PROCESSING_START', ['booking_id' => $id]);

                $normalInvoiceNumber = trim($request->invoice_number ?? '');
                $normalInvoiceDate = trim($request->invoice_date ?? '');
                $dealerInvoiceNumber = trim($request->dealer_invoice_number ?? '');
                $dealerInvoiceDate = trim($request->dealer_invoice_date ?? '');

                $normalFilled = ! empty($normalInvoiceNumber) && ! empty($normalInvoiceDate);
                $dealerFilled = ! empty($dealerInvoiceNumber) && ! empty($dealerInvoiceDate);

                Log::debug('INVOICE_FIELDS_STATUS', [
                    'normal_filled' => $normalFilled,
                    'dealer_filled' => $dealerFilled,
                    'normal_number' => $normalInvoiceNumber ?: null,
                    'normal_date' => $normalInvoiceDate ?: null,
                    'dealer_number' => $dealerInvoiceNumber ?: null,
                    'dealer_date' => $dealerInvoiceDate ?: null,
                ]);

                if ($normalFilled && empty($dealerInvoiceNumber) && empty($dealerInvoiceDate)) {
                    Log::notice('AUTO_COPY_INVOICE_TO_DEALER', [
                        'booking_id' => $id,
                        'from_number' => $normalInvoiceNumber,
                        'from_date' => $normalInvoiceDate,
                    ]);

                    $request->merge([
                        'dealer_invoice_number' => $normalInvoiceNumber,
                        'dealer_invoice_date' => $normalInvoiceDate,
                    ]);
                }

                $finalDealerStatus = 0;
                if ($normalFilled && $dealerFilled) {
                    $finalDealerStatus = 2;
                } elseif (! $normalFilled && $dealerFilled) {
                    $finalDealerStatus = 1;
                }

                $booking->inv_no = $normalInvoiceNumber ?: null;
                $booking->inv_date = $normalInvoiceDate ?: null;
                $booking->dealer_inv_no = $request->dealer_invoice_number ?: null;
                $booking->dealer_inv_date = $request->dealer_invoice_date ?: null;
                $booking->dealer_status = $finalDealerStatus;

                Log::info('INVOICE_FIELDS_UPDATED', [
                    'booking_id' => $id,
                    'inv_no' => $booking->inv_no,
                    'inv_date' => $booking->inv_date,
                    'dealer_inv_no' => $booking->dealer_inv_no,
                    'dealer_inv_date' => $booking->dealer_inv_date,
                    'dealer_status' => $booking->dealer_status,
                ]);
            }

            $oldStatus = $booking->status;
            $oldStatusName = $this->getStatusName($oldStatus);

            if ($newStatus !== 0 && $newStatus !== $oldStatus) {
                Log::notice('STATUS_CHANGE_ATTEMPT', [
                    'booking_id' => $id,
                    'from' => $oldStatus,
                    'from_name' => $oldStatusName,
                    'to' => $newStatus,
                    'to_name' => $this->getStatusName($newStatus),
                    'user_id' => $userId,
                ]);

                $booking->status = $newStatus;

                if ($newStatus == 3) {
                    $booking->cancel_date = Carbon::now()->format('Y-m-d');
                    Log::info('CANCEL_DATE_SET', ['booking_id' => $id, 'date' => $booking->cancel_date]);
                }

                if ($newStatus == 7) {
                    $booking->refund_rejection_date = Carbon::now()->format('Y-m-d');
                    Log::info('REFUND_REJECTION_DATE_SET', ['booking_id' => $id, 'date' => $booking->refund_rejection_date]);
                }
            }

            $booking->save();
            Log::info('BOOKING_SAVED_SUCCESSFULLY', [
                'booking_id' => $id,
                'new_status' => $booking->status,
                'dealer_status' => $booking->dealer_status ?? 'null',
                'changes' => $booking->getChanges(),
            ]);

            if ($newStatus !== 0 && $newStatus !== $oldStatus) {

                $historyTitle = 'Booking Restored';
                $historyBody = 'Booking restored successfully';

                if ($newStatus == 6) {

                    $historyTitle = 'Booking Marked On-Hold';
                    $historyBody = 'Booking moved to on-hold successfully';
                } elseif ($newStatus == 2) {

                    $historyTitle = 'Booking Invoiced';
                    $historyBody = 'Booking invoiced successfully';
                } elseif ($newStatus == 3) {

                    $historyTitle = 'Booking Cancelled';
                    $historyBody = 'Booking cancelled successfully';
                }

                if (! empty(trim($request->remark ?? ''))) {
                    $historyBody .= ' ,Remarks: '.trim($request->remark);
                }

                $booking->addHistory(
                    'commented',
                    $historyTitle,
                    $historyBody,
                    [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'status_name' => $this->getStatusName($newStatus),
                    ],
                    null,
                    backpack_user()
                );
            }

            return redirect(backpack_url('sales/booking'))
                ->with('success', 'Booking update aur followup successfully save ho gaya.');
        } catch (ModelNotFoundException $e) {
            Log::error('BOOKING_NOT_FOUND', [
                'requested_id' => $request->input('id'),
                'user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Booking record nahi mila.');
        } catch (Exception $e) {
            Log::critical('BOOKING_FOLLOWUP_CRASH', [
                'booking_id' => $request->input('id', 'N/A'),
                'user_id' => $userId,
                'user_name' => $userName,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['_token', 'fdoc']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Kuch technical issue aa gaya. Technical team ko inform kar diya gaya hai.');
        }
    }

    private function getStatusName($status)
    {
        return match ((int) $status) {
            1 => 'Active',
            2 => 'Invoiced',
            3 => 'Cancelled',
            4 => 'In Refund Queue',
            5 => 'Closed',
            6 => 'On-Hold',
            7 => 'Refund Rejected',
            8 => 'Active (Pending)',
            0 => 'No Change',
            default => 'Unknown ('.$status.')'
        };
    }

    public function show($id)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('show');

        $this->crud->getEntry($id);

        return $this->getFullBookingData($id, 'show');
    }

    public function getModels($segmentCode)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $models = CommonHelper::getVehicleModels($segmentCode);

        return response()->json($models);
    }

    public function getVariants($modelCode)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $variants = CommonHelper::getVehicleVariants($modelCode);

        return response()->json($variants);
    }

    public function getColors($variantCode)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $colors = CommonHelper::getVehicleColors($variantCode);

        return response()->json($colors);
    }

    public function getChassisNumbers($modelCode)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $chassisNumbers = Stock::select('chassis_no', 'id')
            ->where('model_code', $modelCode)
            ->where('status', 'available')
            ->get()
            ->toArray();

        return response()->json($chassisNumbers);
    }

    public function getBranchLocation($bids)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $data = CommonHelper::getLocations($bids);

        return $data;
    }

    public function getAccessories(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        try {
            $segmentCode = $request->segment;
            $modelCode = $request->model;
            $variantCode = $request->variant;

            Log::info('Fetching accessories', [
                'segment' => $segmentCode,
                'model' => $modelCode,
                'variant' => $variantCode,
            ]);

            $accessories = Accessory::getAccessories(
                $segmentCode,
                $modelCode,
                $variantCode
            );

            return response()->json($accessories);
        } catch (\Throwable $e) {

            Log::error('Error fetching accessories', [
                'segment' => $request->segment,
                'model' => $request->model,
                'variant' => $request->variant,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch accessories.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLocations($state_id)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $locations = XCommonHelper::getLocationsByState($state_id);

        return response()->json($locations);
    }

    public function getLocationsByPincode($pincode)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $locations = PinCodes::where('pincode', $pincode)->get(['id', 'name']);

        if ($locations->isNotEmpty()) {
            return response()->json($locations);
        } else {
            return response()->json([]);
        }
    }

    public function getStateByLocation($location_code)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $location = PinCodes::where('id', $location_code)->first(['id', 'parent', 'level']);

        if (! $location) {
            return response()->json(null);
        }

        while ($location && $location->level !== 'STATE') {
            $location = PinCodes::where('id', $location->parent)->first(['id', 'parent', 'level']);
        }

        return response()->json([
            'state_id' => $location ? $location->id : null,
        ]);
    }

    public function orderVerification(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_ORDER_VERIFY')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Order Verification';

        $query = $this->getBaseQuery();

        $status_filter = $request->input('status_filter', '1');

        if ($status_filter === 'all') {
            $query->whereIn('bookings.order', [0, 1, 2]);
        } else {
            $query->where('bookings.order', $status_filter);
        }

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $user = backpack_user();
        $allowedUsers = [5, 23, 123];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $user,
            $allowedUsers,
            $gridLookups
        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            if (in_array($user->id, $allowedUsers)) {
                $action = '<div style="display:flex;gap:8px;justify-content:center;">';
                if ($t->order == 1) {
                    $action .= '<a href="'.route('sales.booking.order-update', ['id' => $t->id, 'status' => 2]).'"
                            class="btn btn-success btn-sm">Accept</a>';
                    $action .= '<a href="'.route('sales.booking.order-update', ['id' => $t->id, 'status' => 0]).'"
                            class="btn btn-danger btn-sm">Reject</a>';
                } elseif ($t->order == 2) {
                    $action .= '<a href="'.route('sales.booking.order-update', ['id' => $t->id, 'status' => 0]).'"
                            class="btn btn-danger btn-sm">Reject</a>';
                } elseif ($t->order == 0) {
                    $action .= '<a href="'.route('sales.booking.order-update', ['id' => $t->id, 'status' => 2]).'"
                            class="btn btn-success btn-sm">Accept</a>';
                }
                $action .= '</div>';
                $row->action = $action;
            } else {
                $row->action = '<div class="text-center text-muted">---</div>';
            }

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 180,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.order-verification', $this->data);
    }

    private function getCommonLookups()
    {
        return [
            'segments' => CommonHelper::getVehicleSegments(),
            'saleConsultants' => OrgService::usersByDesignation('CNS') ?? [],
            'financiers' => XlFinancier::select('id', 'name')->get()->keyBy('id')->toArray(),
        ];
    }

    public function orderUpdate(Request $request, $id, $status)
    {
        if (! backpack_user()->can('SLS_BKNG_ORDER_VERIFY')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $allowedStatuses = [0, 1];
        if (! in_array($status, $allowedStatuses)) {
            return redirect()->back()->with('error', 'Invalid status value. Only hold (1) or release (0) allowed.');
        }

        $booking = Booking::findOrFail($id);

        $remark = '';
        if ($status == 1) {
            $remark = 'Booking put on hold by verifier';
        } elseif ($status == 0) {
            $remark = 'Hold released, booking activated';
        }

        $booking->order = (int) $status;
        $booking->saveQuietly();

        if ($status == 1) {

            $booking->addHistory(
                'commented',
                'Booking Put On Hold',
                'Booking put on hold by verifier',
                [
                    'hold_status' => 1,
                    'module' => 'Pending Order Verification',
                ],
                null,
                backpack_user()
            );
        } elseif ($status == 0) {

            $booking->addHistory(
                'commented',
                'Booking Resumed',
                'Hold released and booking activated',
                [
                    'hold_status' => 0,
                    'module' => 'Pending Order Verification',
                ],
                null,
                backpack_user()
            );
        }

        $messages = [
            0 => 'Hold released . Booking is now active.',
            1 => 'Booking successfully put on hold.',
        ];

        $message = $messages[$status] ?? 'Booking status updated successfully';

        return redirect()->back()->with('success', $message);
    }

    public function pendingorder(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_ORDER_VERIFY')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Ordered Verification';

        $query = $this->getBaseQuery()
            ->withoutGlobalScopes()
            ->withoutGlobalScope(SoftDeletingScope::class);

        $query->whereIn('enq.segment_code', ['BEV', 'PERSL'])
            ->where(function ($q) {
                $q->whereNull('bookings.order')
                    ->orWhereIn('bookings.order', [0, 1]);
            });

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $user = backpack_user();
        $allowedUsers = [5, 23, 123, $user->id];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $user,
            $allowedUsers,
            $gridLookups
        ) {

            $row = $this->mapBookingForGrid($t, $gridLookups);
            if (empty((array) $row)) {
                \Log::info('mapBookingForGrid returned empty for booking ID: '.$t->id);
            }
            \Log::debug("Mapped row for ID {$t->id}", (array) $row);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            if (in_array($user->id, $allowedUsers)) {
                $row->action = '<div class="d-flex justify-content-center gap-2">
                    <a class="btn btn-sm btn-primary" href="'.route('sales.booking.dms-edit', $t->id).'?from=pending" title="Edit DMS / SO">
                        Process
                    </a>
                </div>';
            } else {
                $row->action = '<div class="table-actions text-center text-muted">---</div>';
            }

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-order', $this->data);
    }

    // public function dmsedit($id, Request $request)
    // {
    //     $booking = Booking::findOrFail($id);

    //     $branchName = Branch::where('code', $booking->branch_code)
    //         ->value('name') ?? 'N/A';
    //     $locationName = $booking->location_code
    //         ? Location::where('code', $booking->location_code)->value('name') ?? 'N/A'
    //         : ($booking->location_other ?? 'N/A');
    //     $collectorName = $booking->col_by
    //         ? (User::find($booking->col_by)->name ?? 'N/A')
    //         : 'N/A';
    //     $fromPending = $request->query('from') === 'pending';

    //     $isBevOrPersonal = in_array($booking->segment_code ?? 0, [753, 21589]);
    //     $data = [
    //         'branch'         => $branchName,
    //         'location'       => $locationName,
    //         'collector_name' => $collectorName,
    //         'accessories'    => $booking->accessories ?? 'N/A',
    //         'total_amount'   => $booking->total_amount ?? 0,
    //         'is_bev_or_personal' => in_array($booking->segment_code, [753, 21589]),
    //         'from_pending'       => $fromPending,
    //         'so_required'        => $fromPending && $isBevOrPersonal,
    //     ];

    //     return view('admin.booking.dms-edit', compact('booking', 'data'));
    // }
    public function dmsedit($id, Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_DMS')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $enquiry = null;

        if (! empty($booking->enq_no)) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        $branchCode = $booking->branch_code
            ?? $enquiry?->dealer_branch;

        $locationCode = $booking->location_code
            ?? $enquiry?->dealer_location;

        $segmentCode = $booking->segment_code
            ?? $enquiry?->segment_code;

        $modelCode = $booking->model_code
            ?? $enquiry?->model_code;

        $variantCode = $booking->variant_code
            ?? $enquiry?->variant_code;

        $colorCode = $booking->color_code
            ?? $enquiry?->color_code;

        $branchName = 'N/A';

        if (! empty($branchCode)) {
            $branchName = Branch::where('code', $branchCode)
                ->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $branchCode)
                    ->value('name');
            }
        }

        $branchName = $branchName ?: 'N/A';

        $locationName = 'N/A';

        if (! empty($locationCode)) {
            $locationName = Location::where('code', $locationCode)
                ->value('name');
        }

        if (! $locationName) {
            $locationName = $booking->location_other
                ?? 'N/A';
        }

        $collectorName = $booking->col_by
            ? (User::find($booking->col_by)->name ?? 'N/A')
            : 'N/A';

        $booking->booking_date = $booking->booking_date
            ?? $enquiry?->created_at;

        $booking->name = $booking->name
            ?? $enquiry?->name;

        $booking->branch_code = $branchCode;
        $booking->location_code = $locationCode;
        $booking->segment_code = $segmentCode;
        $booking->model_code = $modelCode;
        $booking->variant_code = $variantCode;
        $booking->color_code = $colorCode;

        $fromPending = $request->query('from') === 'pending';

        $isBevOrPersonal = in_array(
            (int) ($segmentCode ?? 0),
            [753, 21589]
        );

        $data = [
            'branch' => $branchName,
            'location' => $locationName,
            'collector_name' => $collectorName,
            'accessories' => $booking->accessories ?? 'N/A',
            'total_amount' => $booking->total_amount ?? 0,
            'is_bev_or_personal' => $isBevOrPersonal,
            'from_pending' => $fromPending,
            'so_required' => $fromPending && $isBevOrPersonal,
        ];

        return view(
            'admin.booking.dms-edit',
            compact('booking', 'data')
        );
    }

    public function dmsupdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_DMS')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        Log::info('Starting dmsupdate', [
            'booking_id' => $id,
            'user_id' => backpack_auth()->id(),
            'pending_flag' => $request->has('pending_flag'),
            'ip' => $request->ip(),
        ]);

        $booking = Booking::findOrFail($id);

        $request->merge([
            'dms_no' => strtoupper(trim($request->input('dms_no', ''))),
            'dms_otf' => strtoupper(trim($request->input('dms_otf', ''))),
            'dms_so' => strtoupper(trim($request->input('dms_so', ''))),
        ]);

        $rules = [
            'dms_no' => ['required', 'regex:/^B-\d{8}$/'],
            'dms_otf' => ['required', 'regex:/^OTF\d{2}[A-Z]\d{6}$/'],
            'otf_date' => ['required', 'date_format:d-m-Y'],
            'hidden_otf_date' => ['required', 'date:Y-m-d'],
        ];

        if ($booking->order == 2) {
            $rules['dms_so'] = ['required', 'regex:/^\d{10}$/'];
        }

        $messages = [
            'dms_no.required' => 'DMSBooking Number is required.',
            'dms_no.regex' => 'Please enter a valid DMSBooking number (e.g., B-12345678).',
            'dms_otf.required' => 'DMS OTF Number is required.',
            'dms_otf.regex' => 'Please enter a valid OTF number (e.g., OTF00A123456).',
            'dms_so.required' => 'DMS SO Number is required.',
            'dms_so.regex' => 'Please enter a valid SO number (exactly 10 digits).',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::warning('Validation failed in dmsupdate', [
                'booking_id' => $id,
                'errors' => $validator->errors()->toArray(),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $remarks = [];

        if ($booking->dms_no !== $request->dms_no) {
            $remarks[] = "DMS Booking No. updated to {$request->dms_no}";
        }
        if ($booking->dms_otf !== $request->dms_otf) {
            $remarks[] = "DMS OTF updated to {$request->dms_otf}";
        }
        if ($booking->otf_date !== $request->hidden_otf_date) {
            $remarks[] = "DMS OTF Date updated to {$request->hidden_otf_date}";
        }
        if ($booking->order == 2 && $booking->dms_so !== $request->dms_so) {
            $remarks[] = "DMS SO Number updated to {$request->dms_so}";
        }

        $updateData = [
            'dms_no' => $request->dms_no,
            'dms_otf' => $request->dms_otf,
            'otf_date' => $request->hidden_otf_date,
        ];

        if ($booking->order == 2) {
            $updateData['dms_so'] = $request->dms_so;
        }

        $booking->update($updateData);
        $booking->refresh();

        $existingPending = collect(explode(',', $booking->pending_remark ?? ''))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->toArray();

        $dmsPendingItems = [
            'DMS Booking no needs to be updated',
            'DMS OTF needs to be updated',
            'DMS OTF Date needs to be updated',
            'DMS SO number needs to be updated',
        ];

        $remainingPending = array_diff($existingPending, $dmsPendingItems);

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
        if ($booking->order == 2 && empty($booking->dms_so)) {
            $newPending[] = 'DMS SO number needs to be updated';
        }

        $finalPending = array_merge($remainingPending, $newPending);
        $finalPending = array_unique(array_filter($finalPending));

        $booking->pending_remark = ! empty($finalPending)
            ? implode(' , ', array_map('trim', $finalPending))
            : null;

        $booking->pending = count($finalPending);

        if ($booking->pending === 0) {
            $booking->status = 1;
            Log::info('Booking status set to 1 (no pending fields left)', ['booking_id' => $id]);
        }

        $isPersonalOrBev = in_array($booking->segment_code ?? 0, [753, 21589]);
        $booking->order = 2;
        if ($isPersonalOrBev) {
            if (empty(trim($request->input('dms_so', '')))) {
                $booking->order = 3;
                Log::info('Order set to 3 - Personal/BEV from pending DMS, SO missing in this submit', [
                    'booking_id' => $id,
                    'segment_code' => $booking->segment_code ?? 'N/A',
                ]);
            } else {
                Log::info('Order remains 2 - Personal/BEV but SO provided in this submit', ['booking_id' => $id]);
            }
        }

        if ($booking->pending === 0) {
            $booking->status = 1;
            Log::info('Booking status set to 1 (no pending fields left)', ['booking_id' => $id]);
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

        $message = 'DMS details updated successfully!';

        $message = 'DMS details updated successfully!';
        $fromPending = $request->input('from') === 'pending';

        $message = 'DMS details updated successfully!';

        if ($fromPending) {
            return redirect()->route('sales.booking.pending-order')
                ->with('success', $message);
        }

        return redirect()->route('sales.booking.pending-dms')
            ->with('success', $message);
    }

    public function pendingKyc(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_KYC')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending KYC';

        $query = $this->getBaseQuery();

        $query->whereIn('bookings.status', [1, 8]);

        $query->where(function ($q) {
            $q->whereNull('bookings.pan_no')
                ->orWhere('bookings.pan_no', '')
                ->orWhereNull('bookings.adhar_no')
                ->orWhere('bookings.adhar_no', '');
        });

        $query->orderBy('bookings.id', 'desc');

        $paginatedBookings = $query->paginate(50);
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use ($paginatedBookings, $gridLookups) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
            <a href="'.route('sales.booking.kyc.edit', $t->id).'"
                        class="btn btn-sm btn-primary" title="Complete KYC">
                            Process
                       </a>
                       </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'autoWidth' => true,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No pending KYC bookings found.');
        }

        return view('admin.booking.pending-kyc', $this->data);
    }

    // public function kycEdit($id)
    // {
    //     $this->crud->hasAccessOrFail('update');

    //     $booking = Booking::findOrFail($id);

    //     $data = [
    //         'branches'       => Branch::pluck('name', 'id')->toArray(),
    //         'locations'      => Location::pluck('name', 'id')->toArray(),
    //         'segments' => CommonHelper::getVehicleSegments(),
    //         'saleConsultants' => OrgService::usersByDesignation('CNS') ?? [],
    //     ];

    //     return view('admin.booking.kyc-edit', compact('booking', 'data'));
    // }
    public function kycEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_KYC')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('update');

        $booking = Booking::findOrFail($id);

        $enquiry = null;

        if (! empty($booking->enq_no)) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        $branchCode = $booking->branch_code
            ?? $enquiry?->dealer_branch;

        $locationCode = $booking->location_code
            ?? $enquiry?->dealer_location;

        $segmentCode = $booking->segment_code
            ?? $enquiry?->segment_code;

        $modelCode = $booking->model_code
            ?? $enquiry?->model_code;

        $variantCode = $booking->variant_code
            ?? $enquiry?->variant_code;

        $colorCode = $booking->color_code
            ?? $enquiry?->color_code;

        $customerName = $booking->name
            ?? $enquiry?->name
            ?? '—';

        $branchName = '—';

        if (! empty($branchCode)) {
            $branchName = Branch::where('code', $branchCode)
                ->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $branchCode)
                    ->value('name');
            }
        }

        $branchName = $branchName ?: '—';

        $locationName = '—';

        if (! empty($locationCode)) {
            $locationName = Location::where('code', $locationCode)
                ->value('name');
        }

        $locationName = $locationName
            ?: ($booking->location_other ?? '—');

        $modelName = '—';

        if (! empty($modelCode)) {
            $modelName = VehicleModel::where('code', $modelCode)
                ->value('name');
        }

        $modelName = $modelName ?: ($modelCode ?: '—');

        $variantName = '—';
        $colorName = '—';

        if (! empty($variantCode)) {

            $variantRows = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->get([
                    'custom_name',
                    'color',
                    'color_code',
                ]);

            if ($variantRows->isNotEmpty()) {

                // Variant name
                $variantName = $variantRows->first()->custom_name ?? '—';

                // Find color using color_code
                if (! empty($colorCode)) {
                    $colorRow = $variantRows->first(function ($row) use ($colorCode) {
                        return strtoupper((string) $row->color_code) === strtoupper((string) $colorCode);
                    });

                    if ($colorRow) {
                        $colorName = $colorRow->color ?? '—';
                    }
                }

                // Fallback to first row's color
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

        $data = [
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

        return view(
            'admin.booking.kyc-edit',
            compact('booking', 'data')
        );
    }

    public function kycUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_KYC')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'pan_no' => ['required', 'string', new PanNumber],
            'adhar_no' => ['required', 'string', new AadhaarNumber],
            'gst_no' => ['nullable', 'string', new Gstin],
        ]);

        $panNo = $this->identifiers->normalizePan($validated['pan_no']);
        $adharNo = $this->identifiers->normalizeAadhaar($validated['adhar_no']);

        $gstValue = $request->has('gst_not_required') && $request->gst_not_required
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

        return redirect()
            ->route('sales.booking.pending-kyc')
            ->with('success', "Booking #{$booking->id} की KYC successfully complete हो गई है!");
    }

    public function pendingDms(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_DMS')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');
        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending DMS';

        $query = $this->getBaseQuery();

        $query->whereIn('bookings.status', [1, 8]);
        $query->where('bookings.b_type', 'Active');

        $query->where(function ($q) {
            $q->whereNull('bookings.dms_no')
                ->orWhere('bookings.dms_no', '')
                ->orWhereNull('bookings.dms_otf')
                ->orWhere('bookings.dms_otf', '')
                ->orWhereNull('bookings.otf_date')
                ->orWhere('bookings.otf_date', '0000-00-00');
        });

        $status_filter = $request->input('status_filter', 'active');

        if ($status_filter === 'active') {
            $query->where(function ($q) {
                $q->whereNull('bookings.order')
                    ->orWhere('bookings.order', 0);
            });
        } elseif ($status_filter === 'hold') {
            $query->where('bookings.order', 1);
        } else {
            $query->where(function ($q) {
                $q->whereNull('bookings.order')
                    ->orWhere('bookings.order', 0);
            });
        }

        $query->orderBy('bookings.id', 'desc');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $user = backpack_user();
        $allowedUsers = [5, 23, 123];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);
            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $actionHtml = '<div class="d-flex gap-2 justify-content-center flex-wrap align-items-center">';

            if ($t->order == 1) {
                $actionHtml .= '
                <a href="'.route('sales.booking.order-update', ['id' => $t->id, 'status' => 0]).'"
                   class="btn btn-sm btn-success">Resume</a>';
            } else {
                $actionHtml .= '
                <a href="'.route('sales.booking.order-update', ['id' => $t->id, 'status' => 1]).'"
                   class="btn btn-sm btn-danger">Hold</a>';

                $actionHtml .= '
                <a href="'.route('sales.booking.dms-edit', $t->id).'"
                   class="btn btn-sm btn-primary py-1 px-2">Process</a>';
            }

            $actionHtml .= '</div>';
            $row->action = $actionHtml;

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();
        $hasAction = collect($columns)->contains('field', 'action');

        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 140,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-dms', $this->data);
    }

    public function Exchange(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_EXCHANGE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Int in Exchange';

        $query = $this->getBaseQuery();

        $query->where('enq.purchase_type', 'Exchange Buy'); // DB column is purchase_type

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $price_gap = ($t->expected_price ?? 0) - ($t->offered_price ?? 0);
            $row->price_gap = number_format($price_gap);

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '<div class="d-flex justify-content-center gap-2">
            <a href="'.route('sales.booking.exchange.edit', $t->id).'#exch"
               class="btn btn-primary btn-sm">
                Process
            </a>
        </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        foreach ($columns as &$col) {
            if (in_array($col['field'], ['expected_price', 'offered_price', 'exchange_bonus', 'price_gap'])) {
                $col['type'] = 'rightAligned';
                $col['cellClass'] = 'text-right';
            }
        }
        unset($col);

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No exchange interested bookings found.');
        }

        return view('admin.booking.exchange', $this->data);
    }

    public function Scrappage(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_EXCHANGE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Int in Scrappage';

        $query = $this->getBaseQuery();

        $query->where('enq.purchase_type', 'Scrappage');

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $price_gap = ($t->expected_price ?? 0) - (($t->offered_price ?? 0) + ($t->exchange_bonus ?? 0));
            $row->price_gap = number_format($price_gap);

            $row->expected_price = '₹ '.number_format($t->expected_price ?? 0);
            $row->offered_price = '₹ '.number_format($t->offered_price ?? 0);
            $row->exchange_bonus = '₹ '.number_format($t->exchange_bonus ?? 0);

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.exchange.edit', $t->id).'#scrappage"
                   class="btn btn-primary btn-sm"
                   >
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        foreach ($columns as &$col) {
            if (in_array($col['field'], ['expected_price', 'offered_price', 'exchange_bonus', 'price_gap'])) {
                $col['type'] = 'rightAligned';
                $col['cellClass'] = 'text-right';
            }
        }
        unset($col);

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.scrappage', $this->data);
    }

    public function exchnotInterested(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_EXCHANGE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Not Interested';

        $query = $this->getBaseQuery();

        // Not Interested = First Time Buy + Additional Buy only
        $query->whereIn('enq.purchase_type', [
            'First Time Buy',
            'Additional Buy',
        ]);

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $price_gap = ($t->used_vehicle_exp_price ?? 0) - ($t->used_vehicle_off_price ?? 0);
            $row->price_gap = number_format($price_gap);

            $row->used_vehicle_exp_price = '₹ '.number_format($t->used_vehicle_exp_price ?? 0);
            $row->used_vehicle_off_price = '₹ '.number_format($t->used_vehicle_off_price ?? 0);
            $row->new_vehicle_exc_bonus = '₹ '.number_format($t->new_vehicle_exc_bonus ?? 0);

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->brand_make_1 = $t->brand_make_1 ?? null;

            $row->action = '
                <div class="d-flex justify-content-center gap-2">
                    <a href="'.route('sales.booking.exchange.edit', $t->id).'#exch"
                    class="btn btn-primary btn-sm"
                    >
                        Process
                    </a>
                </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        foreach ($columns as &$col) {
            if (in_array($col['field'], ['used_vehicle_exp_price', 'used_vehicle_off_price', 'new_vehicle_exc_bonus', 'price_gap'])) {
                $col['type'] = 'rightAligned';
                $col['cellClass'] = 'text-right';
            }
        }
        unset($col);

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No not-interested bookings found.');
        }

        return view('admin.booking.exchange-not-interested', $this->data);
    }

    public function intInFinance(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Int in Finance';

        $query = $this->getBaseQuery();

        $query->leftJoin('xlr8_booking_finance as xf', 'bookings.id', '=', 'xf.bid');

        $query->where('bookings.status', '!=', 2)
            ->where(function ($q) {
                $q->whereNull('xf.fin_mode')
                    ->orWhere('xf.fin_mode', 'In-house');
            })
            ->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->finance_status = $t->finance_status == 1 ? 'Pending' : ($t->finance_status == 2 ? 'Complete' : 'N/A');

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.finance.edit', $t->id).'"
                class="btn btn-primary btn-sm">
                    Update
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.int-in-finance', $this->data);
    }

    // public function finnotInterested(Request $request)
    // {
    //     $this->crud->hasAccessOrFail('list');

    //     $this->data['crud'] = $this->crud;
    //     $this->data['title'] = 'Not Interested';

    //     $query = $this->getBaseQuery();

    //     $query->whereIn('f.fin_mode', [
    //         'Customer Self',
    //         'Cash',
    //         'Yet To Decide'
    //     ]);

    //     $query->orderBy('bookings.id', 'DESC');

    //     $paginatedBookings = $query->paginate(50);

    //     $lookups = $this->getCommonLookups();
    //     extract($lookups);

    //     $saleConsultants = $lookups['saleConsultants'] ?? [];

    //     $gridData = $paginatedBookings->map(function ($t, $index) use (
    //         $paginatedBookings,
    //         $segments,
    //         $saleConsultants
    //     ) {
    //         $row = $this->mapBookingForGrid($t);

    //         $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

    //         $price_gap = ($t->used_vehicle_exp_price ?? 0) - ($t->used_vehicle_off_price ?? 0);
    //         $row->price_gap = number_format($price_gap);

    //         $row->used_vehicle_exp_price = '₹ ' . number_format($t->used_vehicle_exp_price ?? 0);
    //         $row->used_vehicle_off_price = '₹ ' . number_format($t->used_vehicle_off_price ?? 0);
    //         $row->new_vehicle_exc_bonus  = '₹ ' . number_format($t->new_vehicle_exc_bonus ?? 0);
    //         $location = $t->location_code && $t->location_code > 0
    //             ? (Location::find($t->location_code)->name ?? 'N/A')
    //             : ($t->location_other ?? 'N/A');

    //         $row->location = $location;

    //         $row->brand_make_1 = $t->brand_make_1 ?? null;

    //         $row->action = '
    //         <div class="d-flex justify-content-center gap-2">
    //             <a href="' . route('sales.booking.finance.edit', $t->id) . '"
    //                class="btn btn-primary btn-sm"
    //                >
    //                 Process
    //             </a>
    //         </div>';

    //         return $row;
    //     })->values();

    //     $columns = $this->getAgGridColumns();

    //     $hasAction = collect($columns)->contains('field', 'action');
    //     if (!$hasAction) {
    //         $columns[] = [
    //             'field'         => 'action',
    //             'headerName'    => 'Actions',
    //             'width'         => 150,
    //             'pinned'        => 'right',
    //             'sortable'      => false,
    //             'filter'        => false,
    //             'cellRenderer'  => 'htmlRenderer',
    //             'cellClass'     => 'text-center p-0',
    //             'autoHeight'    => true,
    //         ];
    //     }

    //     foreach ($columns as &$col) {
    //         if (in_array($col['field'], ['used_vehicle_exp_price', 'used_vehicle_off_price', 'new_vehicle_exc_bonus', 'price_gap'])) {
    //             $col['type'] = 'rightAligned';
    //             $col['cellClass'] = 'text-right';
    //         }
    //     }
    //     unset($col);

    //     $gridConfig = [
    //         'columns' => $columns,
    //         'data'    => $gridData,
    //     ];

    //     $this->data['gridConfig'] = $gridConfig;

    //     if ($gridData->isEmpty()) {
    //         session()->flash('info', 'No not-interested finance bookings found.');
    //     }

    //     return view('admin.booking.finance-not-interested', $this->data);
    // }
    public function finnotInterested(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Not Interested';

        $query = $this->getBaseQuery();

        $query->where(function ($q) {
            $q->whereIn('f.fin_mode', [
                'Customer Self',
                'Cash',
                'Yet To Decide',
            ])
                ->orWhere(function ($q2) {
                    $q2->whereNull('f.fin_mode')
                        ->whereIn('enq.fin_mode', [
                            'Customer Self',
                            'Cash',
                            'Yet To Decide',
                        ]);
                });
        });

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no =
                ($paginatedBookings->currentPage() - 1)
                * $paginatedBookings->perPage()
                + $index + 1;

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
                <div class="d-flex justify-content-center gap-2">
                    <a href="'.route('sales.booking.finance.edit', $t->id).'"
                    class="btn btn-primary btn-sm">
                        Process
                    </a>
                </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');

        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.finance-not-interested', $this->data);
    }

    public function finRetail(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Finance Retail';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 2);
        $query->where('bookings.retail', 0);

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.finance.retail-edit', $t->id).'"
                   class="btn btn-primary btn-sm"
                    >
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.finance-retail', $this->data);
    }

    public function finPayout(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Finance Payout - Pending';

        $query = $this->getBaseQuery();

        $query->where('bookings.payout', 1);
        $query->where('bookings.retail', 1);
        $query->where('bookings.status', 2);
        $query->where('f.fin_mode', 'In-house');
        $query->where('f.case_status', 2);

        $query->orderBy('bookings.id', 'DESC');

        $financierFilter = $request->get('financier');

        if (! empty($financierFilter)) {
            $query->where('enq.financier', $financierFilter);
        }

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.finance.payout-edit', $t->id).'"
                   class="btn btn-primary btn-sm"
                   >
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 140,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
            'type' => 'pending',
        ];

        $this->data['gridConfig'] = $gridConfig;
        $this->data['financiers'] = $financiers;

        return view('admin.booking.finance-payout', $this->data);
    }

    public function finPayoutCompleted(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Finance Payout - Completed';

        $query = $this->getBaseQuery();

        $query->where('bookings.payout', 2);
        $query->where('bookings.retail', 1);
        $query->where('bookings.status', 2);

        $filter = $request->query('status_filter', 'all');

        if ($filter === 'short') {
            $query->where('f.difference', '<', -100);
        } elseif ($filter === 'excess') {
            $query->where('f.difference', '>', 100);
        } elseif ($filter === 'reconciled') {
            $query->whereBetween('f.difference', [-100, 100]);
        }

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.finance.view', $t->id).'"
                   class="btn btn-info btn-sm"
                   title="View Finance">
                    <i class="fas fa-eye"></i> View
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 140,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
            'type' => 'completed',
        ];

        $this->data['gridConfig'] = $gridConfig;
        $this->data['financiers'] = $financiers;

        return view('admin.booking.finance-payout-completed', $this->data);
    }

    public function fetchPendBkData()
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $now = Carbon::now();
        $mtdStart = $now->copy()->startOfMonth();
        $ytdStart = $now->copy()->startOfYear();

        $data = Cache::remember('cbr_data_'.$now->format('YmdH'), 3600, function () {
            $bookings = DB::table('xlr8_booking_master as bm')
                ->join('xlr8_vehicle_master as vm', 'bm.vehicle_oem_code', '=', 'vm.id')
                ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
                ->whereIn('bm.status', [1, 4, 6, 8])
                ->select(
                    'bm.id',
                    'bm.status',
                    'bm.b_type',
                    'bm.fin_mode',
                    'bm.buyer_type',
                    'bm.pending',
                    'bm.order',
                    'bm.dms_so',
                    'bm.booking_amount',
                    'bm.created_at',
                    DB::raw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key'),
                    'em.value as seg',
                    'vm.oem_model as model',
                    'vm.oem_variant as variant',
                    'vm.color as clr',
                    'vm.code'
                )
                ->get()
                ->groupBy('group_key');

            $bookingAmounts = DB::table('xlr8_booking_amount')
                ->where('status', 1)
                ->select('bid', DB::raw('SUM(amount) as total_amount'))
                ->groupBy('bid')
                ->pluck('total_amount', 'bid');

            $exchanges = DB::table('xlr8_exchange')
                ->whereIn('verification_status', [0, null])
                ->select('bid', 'purchase_type')
                ->get()
                ->groupBy('bid')
                ->map(function ($group) {
                    return [
                        'exchange_pending' => $group->where('purchase_type', 'Exchange')->count() > 0 ? 1 : 0,
                        'scrappage_pending' => $group->where('purchase_type', 'Scrappage')->count() > 0 ? 1 : 0,
                    ];
                });

            $finances = DB::table('xlr8_booking_finance')
                ->whereIn('verification_status', [0, null])
                ->pluck('bid')
                ->mapWithKeys(fn ($bid) => [$bid => 1]);

            $data = collect();
            $index = 1;

            foreach ($bookings as $groupKey => $groupBookings) {
                [$seg, $model, $variant, $clr] = explode('|', $groupKey);

                $liveGroup = $groupBookings->whereIn('status', [1, 6, 8])->where('b_type', '!=', 'dummy');

                $total_bookings = $liveGroup->count();
                if ($total_bookings === 0) {
                    continue;
                }

                $bkn_bookings = $liveGroup->where('b_type', 'Individual')->count();
                $chr_bookings = $liveGroup->where('b_type', 'Dealer')->count();

                $on_hold = $liveGroup->where('status', 6)->count();

                $verify = $liveGroup->where('order', 1)->count();

                $orders = $liveGroup->where('order', 2)->whereNull('dms_so')->count();

                $payments = $liveGroup->filter(function ($booking) use ($bookingAmounts) {
                    $total_amount = $bookingAmounts->get($booking->id, 0);

                    return $total_amount < $booking->booking_amount;
                })->count();

                $data_pending = $liveGroup->where('pending', '>', 0)->count();

                $refunds = $groupBookings->where('status', 4)->count();

                $data->push([
                    'sno' => $index++,
                    'seg' => $seg,
                    'model' => $model,
                    'variant' => $variant,
                    'clr' => $clr,
                    'total_bookings' => $total_bookings,
                    'bkn_bookings' => $bkn_bookings,
                    'chr_bookings' => $chr_bookings,
                    'verify' => $verify,
                    'orders' => $orders,
                    'payments' => $payments,
                    'data' => $data_pending,
                    'refund' => $refunds,
                ]);
            }

            return $data;
        });

        $title = 'Pending Data Report';
        $filename = 'PndngDataRprt_'.$now->format('Y-m-d-H-i-s').'.xlsx';
        $stkbr = $tbr = null;
        $header = [
            ['title' => 'S.No.', 'field' => 'sno', 'hozAlign' => 'center', 'formatter' => 'plaintext'],
            [
                'title' => 'Vehicle Info',
                'columns' => [
                    ['title' => 'Segment', 'field' => 'seg', 'headerFilter' => 'select'],
                    ['title' => 'Model', 'field' => 'model', 'headerFilter' => 'select'],
                    ['title' => 'Variant', 'field' => 'variant', 'headerFilter' => 'select'],
                    ['title' => 'Color', 'field' => 'clr', 'headerFilter' => 'select'],
                ],
            ],

            [
                'title' => 'Bookings',
                'columns' => [
                    ['title' => 'Total', 'field' => 'total_bookings', 'bottomCalc' => 'sum'],
                    ['title' => 'BKN', 'field' => 'bkn_bookings', 'bottomCalc' => 'sum'],
                    ['title' => 'CHR', 'field' => 'chr_bookings', 'bottomCalc' => 'sum'],
                ],
            ],

            [
                'title' => 'Pending Actions',
                'columns' => [
                    ['title' => 'Verify', 'field' => 'verify', 'bottomCalc' => 'sum'],
                    ['title' => 'Orders', 'field' => 'orders', 'bottomCalc' => 'sum'],
                    ['title' => 'Payments', 'field' => 'payments', 'bottomCalc' => 'sum'],
                    ['title' => 'Data', 'field' => 'data', 'bottomCalc' => 'sum'],
                    ['title' => 'Refund', 'field' => 'refund', 'bottomCalc' => 'sum'],
                ],
            ],

        ];

        return [$header, $data, $tbr, $stkbr, $filename, $title];
    }

    public function pendingPayment(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Payment';

        $bookingAmountTable = (new Bookingamount)->getTable();

        $query = $this->getBaseQuery();

        $query->whereIn('bookings.status', [1, 8]);
        $query->where('bookings.b_type', 'Active');
        $query->whereIn('bookings.col_type', [2, 3]);

        $query->where(function ($q) use ($bookingAmountTable) {
            $q->whereRaw("bookings.booking_amount > COALESCE((
                SELECT SUM(amount)
                FROM {$bookingAmountTable}
                WHERE {$bookingAmountTable}.bid = bookings.id
                AND {$bookingAmountTable}.deleted_at IS NULL
            ), 0)")
                ->orWhereNull('bookings.receipt_no')
                ->orWhere('bookings.receipt_no', '');
        });

        $query->orderBy('bookings.id', 'desc');

        $paginatedBookings = $query->paginate(50);
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use ($paginatedBookings, $gridLookups) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $paid = $t->totalReceivedAmount();
            $balance = $t->booking_amount - $paid;

            $row->booking_amount = number_format($t->booking_amount ?? 0);
            $row->paid_amount = number_format($paid);
            $row->balance = number_format($balance);
            $row->receipt_no = $t->receipt_no ?? 'Missing';

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
            <a href="'.route('sales.booking.pending-edit', $t->id).'#pending"
                            class="btn btn-primary btn-sm" title="Add/Edit Payment">
                                Process
                        </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 140,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-payment', $this->data);
    }

    public function pendingInsurance(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_INSURANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Insurance';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 2);

        $query->whereNull('ins.bid');

        $status_filter = $request->input('status_filter', 'all');
        $now = Carbon::now();

        if ($status_filter === 'this_month') {
            $query->whereMonth('booking_date', $now->month)
                ->whereYear('booking_date', $now->year);
        } elseif ($status_filter === 'last_month') {
            $query->whereMonth('booking_date', $now->subMonth()->month)
                ->whereYear('booking_date', $now->subMonth()->year);
        } elseif ($status_filter === 'this_year') {
            $query->whereYear('booking_date', $now->year);
        }

        $paginatedBookings = $query->orderBy('booking_date', 'DESC')->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.insurance.edit', $t->id).'"
                   class="btn btn-primary btn-sm"
                   >
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-insurance', $this->data);
    }

    public function pendingRto(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_RTO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending RTO';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 2);

        $rtoDoneIds = DB::table('xlr8_booking_rto')
            ->where('status', 2)
            ->pluck('bid')
            ->toArray();

        $query->whereNotIn('bookings.id', $rtoDoneIds);

        $status_filter = $request->input('status_filter', 'all');
        $now = Carbon::now();

        if ($status_filter === 'this_month') {
            $query->whereMonth('booking_date', $now->month)
                ->whereYear('booking_date', $now->year);
        } elseif ($status_filter === 'last_month') {
            $query->whereMonth('booking_date', $now->subMonth()->month)
                ->whereYear('booking_date', $now->subMonth()->year);
        } elseif ($status_filter === 'this_year') {
            $query->whereYear('booking_date', $now->year);
        }

        $paginatedBookings = $query->orderBy('booking_date', 'DESC')->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.rto.edit', $t->id).'"
                   class="btn btn-primary btn-sm"
                   >
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-rto', $this->data);
    }

    public function pendingDeliveries(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_DELIVERY')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Deliveries';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 2);

        $deliveredIds = DB::table('xlr8_booking_delivered')
            ->where('status', 1)
            ->pluck('bid')
            ->toArray();

        $query->whereNotIn('bookings.id', $deliveredIds);

        $paginatedBookings = $query->orderBy('booking_date', 'DESC')->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.backpack_url("sales/booking/{$t->id}/delivery-edit").'#delivery"
                   class="btn btn-primary btn-sm">
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-deliveries', $this->data);
    }

    public function pendingRegistration(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REGISTRATION')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Registration';

        $query = $this->getBaseQuery();

        $query->join('xlr8_booking_rto', function ($join) {
            $join->on('xlr8_booking_rto.bid', '=', 'bookings.id')
                ->where('xlr8_booking_rto.status', 1)
                ->whereNull('xlr8_booking_rto.vh_rgn_no');
        });

        if ($request->has('customer_type') && $request->customer_type !== 'all') {
            $filterType = $request->customer_type === 'actual' ? 'active' : $request->customer_type;
            $query->where('bookings.b_type', $filterType);
        }

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use ($paginatedBookings, $gridLookups) {
            $row = $this->mapBookingForGrid($t, $gridLookups);
            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->action = '
        <div class="d-flex justify-content-center gap-2">
            <a href="'.route('sales.booking.rto.edit', $t->id).'"
               class="btn btn-primary btn-sm">
                Process
            </a>
        </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        if (! collect($columns)->contains('field', 'action')) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 130,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-registration', $this->data);
    }

    public function pendingDO(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_DO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');
        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending DO';

        $query = $this->getBaseQuery();

        $query->whereNotIn('f.instrument_type', [1, 2])
            ->where(function ($q) {
                $q->whereNull('f.instrument_ref_no')
                    ->orWhere('f.instrument_ref_no', '');
            });

        $paginatedBookings = $query->orderBy('bookings.booking_date', 'DESC')
            ->paginate(50);
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($booking, $index) use ($paginatedBookings, $gridLookups) {
            $row = $this->mapBookingForGrid($booking, $gridLookups);
            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->action = '<div class="d-flex justify-content-center gap-2">
            <a href="'.route('sales.booking.do.edit', $booking->id).'"
               class="btn btn-primary btn-sm">
                </i> Process
            </a>
        </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();
        if (! collect($columns)->pluck('field')->contains('action')) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 160,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
            ];
        }

        $this->data['gridConfig'] = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No pending Delivery Order found.');
        }

        return view('admin.booking.pending-do', $this->data);
    }

    public function doEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_DO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $booking = Booking::findOrFail($id);

        $finance = XFinance::firstOrCreate(
            ['bid' => $id],
            [
                'vehicle_oem_code' => $booking->vehicle_oem_code,
                'fin_mode' => $booking->fin_mode ?? 'In-house',
                'verification_status' => 1,
                'case_status' => 1,
                'created_by' => Auth::id(),
            ]
        );

        $data = $this->getFullBookingData($id, 'doedit');

        return view('admin.booking.doedit', array_merge($data->getData(), [
            'booking' => $booking,
            'finance' => $finance,
        ]));
    }

    public function doUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_DO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $request->validate([
            'instrument_ref_no' => 'required|string|max:50|min:3',
        ]);

        $finance = XFinance::where('bid', $id)->firstOrFail();

        $finance->update([
            'instrument_ref_no' => trim($request->instrument_ref_no),
            'retail' => 1,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);
        $booking = Booking::findOrFail($id);

        $booking->addHistory(
            'commented',
            'Delivery Order Completed',
            'Delivery Order processed .',
            [
                'instrument_ref_no' => trim($request->instrument_ref_no),
            ],
            null,
            backpack_user()
        );

        return redirect()->route('sales.booking.pending-do')
            ->with('success', "Delivery Order #{$request->instrument_ref_no} saved successfully!");
    }

    public function pendingInvoices(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_INVOICE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Invoices';

        $query = $this->getBaseQuery()
            ->whereIn('dealer_status', [1]);

        if ($request->has('customer_type') && $request->customer_type !== 'all') {
            $filterType = $request->customer_type === 'actual' ? 'active' : $request->customer_type;
            $query->where('bookings.b_type', $filterType);
        }

        $paginatedBookings = $query->orderBy('bookings.id', 'DESC')->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->inv_date = '---';
            $row->inv_no = '<span class="text-danger">Pending</span>';

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.backpack_url("sales/booking/{$t->id}/dealer-invoice").'"
                   class="btn btn-primary btn-sm"
                   title="Edit Dealer Invoice">
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-invoices', $this->data);
    }

    // public function pendingEdit($id)
    // {
    //     $this->crud->hasAccessOrFail('update');

    //     $booking = Booking::findOrFail($id);

    //     $totalPaid = Bookingamount::where('bid', $booking->id)
    //         ->sum('amount') ?? 0;

    //     $receiptLogs = Bookingamount::where('bid', $booking->id)
    //         ->orderBy('date', 'desc')
    //         ->get();

    //     $data = [
    //         'total_amount'    => $totalPaid,
    //         'collector_name'  => User::find($booking->col_by)?->name ?? 'N/A',
    //         'branch'          => $booking->branch?->name ?? 'N/A',
    //         'location'        => $booking->location?->name ?? 'N/A',
    //     ];

    //     return view('admin.booking.pendedit', compact('booking', 'data', 'receiptLogs'));
    // }
    public function pendingEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('update');

        $booking = Booking::findOrFail($id);

        $totalPaid = $booking->totalReceivedAmount();

        $receiptLogs = Bookingamount::where('bid', $booking->id)
            ->orderBy('date', 'desc')
            ->get();

        $enquiry = null;

        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        $customerName = $booking->name
            ?? $enquiry?->name
            ?? '—';

        $branchCode = $booking->branch_code
            ?? $enquiry?->dealer_branch;

        $locationCode = $booking->location_code
            ?? $enquiry?->dealer_location;

        $modelCode = $booking->model_code
            ?? $enquiry?->model_code;

        $variantCode = $booking->variant_code
            ?? $enquiry?->variant_code;

        $colorCode = $booking->color_code
            ?? $enquiry?->color_code;

        $branchName = '—';

        if (! empty($branchCode)) {

            $branchName = Branch::where('code', $branchCode)
                ->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $branchCode)
                    ->value('name');
            }
        }

        $branchName = $branchName ?: '—';

        $locationName = '—';

        if (! empty($locationCode)) {

            $locationName = Location::where('code', $locationCode)
                ->value('name');
        }

        $locationName = $locationName
            ?: ($booking->location_other ?? '—');

        $modelName = '—';

        if (! empty($modelCode)) {

            $modelName = VehicleModel::where('code', $modelCode)
                ->value('name');
        }

        $modelName = $modelName ?: ($modelCode ?: '—');

        $variantName = '—';
        $colorName = '—';

        if (! empty($variantCode)) {

            $variantRows = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->get([
                    'custom_name',
                    'color',
                    'color_code',
                ]);

            if ($variantRows->isNotEmpty()) {

                $variantName = $variantRows->first()->custom_name ?? '—';

                if (! empty($colorCode)) {

                    $colorRow = $variantRows->first(function ($row) use ($colorCode) {

                        return strtoupper((string) $row->color_code)
                            === strtoupper((string) $colorCode);

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

        $data = [
            'total_amount' => $totalPaid,

            'collector_name' => User::find($booking->col_by)?->name ?? 'N/A',

            'customer_name' => $customerName,

            'branch_name' => $branchName,

            'location_name' => $locationName,

            'model_name' => $modelName,

            'variant_name' => $variantName,

            'color_name' => $colorName,
        ];

        return view(
            'admin.booking.pendedit',
            compact('booking', 'data', 'receiptLogs')
        );
    }

    public function pendingUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        Log::info('=== PENDING UPDATE STARTED ===', [
            'booking_id' => $id,
            'user_id' => backpack_auth()->id(),
            'ip' => $request->ip(),
            'pending_flag' => $request->has('pending_flag'),
            'user_agent' => $request->userAgent(),
        ]);

        $booking = Booking::findOrFail($id);

        $request->merge([
            'pan_no' => strtoupper($request->input('pan_no', '')),
            'adhar_no' => strtoupper($request->input('adhar_no', '')),
            'online_bk_ref_no' => strtoupper($request->input('online_bk_ref_no', '')),
            'dms_no' => strtoupper($request->input('dms_no', '')),
            'dms_otf' => strtoupper($request->input('dms_otf', '')),
            'dms_so' => strtoupper($request->input('dms_so', '')),
            'chassis' => strtoupper($request->input('chassis', '')),
            'invoice_number' => strtoupper($request->input('invoice_number', '')),
            'dealer_invoice_number' => strtoupper($request->input('dealer_invoice_number', '')),
        ]);

        Log::info('Form data after uppercase', $request->except(['_token']));

        // Canonical formats centralized in App\Rules\* (see docs/refactor/ai-findings-22-09-2026.md
        // for the audit that found this flow's Aadhaar/Chassis rules disagreeing with kycUpdate()'s
        // and with real stock data respectively — reconciled here to the one canonical rule).
        $validator = Validator::make($request->all(), [
            'pan_no' => ['required', new PanNumber],
            'adhar_no' => ['required', new AadhaarNumber],
            'dms_no' => ['required', new DmsNumber],
            'dms_otf' => ['required', new OtfNumber],
            'hidden_otf_date' => ['required', 'date'],
            'online_bk_ref_no' => ['required_if:b_mode,Online', 'nullable'],
            'chassis' => [$request->has('pending_flag') ? 'required' : 'nullable', new ChassisNumber],
            'invoice_number' => ['nullable', new InvoiceNumber],
            'dealer_invoice_number' => ['nullable', new DealerInvoiceNumber],
        ]);

        if ($request->has('pending_flag')) {

            $booking->inv_no = $request->input('invoice_number');
            $booking->inv_date = $request->filled('hidden_invoice_date')
                ? $request->hidden_invoice_date
                : null;

            if (
                $request->filled('dealer_invoice_number') &&
                $request->filled('dealer_invoice_date')
            ) {
                $booking->dealer_inv_no = $request->dealer_invoice_number;
                $booking->dealer_inv_date = $request->dealer_invoice_date;
                $booking->dealer_status = 1;

                Log::info('Dealer invoice details updated', [
                    'dealer_inv_no' => $booking->dealer_inv_no,
                    'dealer_inv_date' => $booking->dealer_inv_date,
                ]);
            }

            $booking->status = 2;
        }

        if ($validator->fails()) {
            Log::warning('Validation FAILED', [
                'booking_id' => $id,
                'errors' => $validator->errors()->toArray(),
            ]);

            return redirect()->back()->withErrors($validator)->withInput();
        }

        Log::info('Validation PASSED');
        // Aadhaar is validated with optional space/dash separators (AadhaarNumber rule) but
        // stored as plain digits, matching kycUpdate()'s storage shape.
        $request->merge(['adhar_no' => $this->identifiers->normalizeAadhaar($request->input('adhar_no'))]);

        $changes = [];
        $this->logChange($booking, 'online_bk_ref_no', $request->online_bk_ref_no, $changes);
        $this->logChange($booking, 'pan_no', $request->pan_no, $changes);
        $this->logChange($booking, 'adhar_no', $request->adhar_no, $changes);
        $this->logChange($booking, 'dms_no', $request->dms_no, $changes);
        $this->logChange($booking, 'dms_otf', $request->dms_otf, $changes);
        $this->logChange($booking, 'otf_date', $request->hidden_otf_date, $changes);
        $this->logChange($booking, 'chassis_no', $request->chassis, $changes);

        if ($request->has('not_required')) {
            $booking->dms_so = 0;
            $changes[] = 'DMS SO marked as Not Required';
        } else {
            $this->logChange($booking, 'dms_so', $request->dms_so, $changes);
        }

        try {
            $booking->save();

            Log::info('Booking saved successfully', [
                'booking_id' => $id,
                'changes_count' => count($changes),
            ]);

            if ($request->has('pending_flag')) {

                $booking->addHistory(
                    'commented',
                    'Pending Invoice Processed',
                    'Invoice and chassis details updated successfully',
                    [
                        'module' => 'Pending Invoice',
                        'invoice_number' => $booking->inv_no,
                        'invoice_date' => $booking->inv_date,
                        'dealer_invoice_no' => $booking->dealer_inv_no,
                        'dealer_invoice_date' => $booking->dealer_inv_date,
                        'chassis_no' => $booking->chassis_no,
                        'status' => $booking->status,
                    ],
                    null,
                    backpack_user()
                );
            } else {

                $booking->addHistory(
                    'commented',
                    'Pending Details Updated',
                    'Pending booking details updated successfully',
                    [
                        'module' => 'Pending Update',
                    ],
                    null,
                    backpack_user()
                );
            }
        } catch (Exception $e) {
            Log::error('FAILED to save booking', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['save' => 'Database error.'])->withInput();
        }

        $msg = $request->has('pending_flag')
            ? 'Booking successfully marked as INVOICED! Chassis: '.$request->chassis
            : 'Pending data updated successfully!';

        Log::info('=== PENDING UPDATE SUCCESS ===', [
            'booking_id' => $id,
            'final_status' => $booking->status,
            'message' => $msg,
        ]);

        return redirect()->route('sales.booking.pending-payment')->with('success', $msg);
    }

    private function logChange($model, $field, $newValue, &$changes)
    {
        if ($newValue != $model->$field) {
            $old = $model->$field ?? '(empty)';
            $new = $newValue ?? '(empty)';
            $changes[] = ucfirst(str_replace('_', ' ', $field))." changed from '{$old}' → '{$new}'";
            $model->$field = $newValue;
            Log::info("Field updated: {$field}", ['old' => $old, 'new' => $new]);
        }
    }

    private function updateIfDifferent($model, $field, $newValue, &$changes)
    {
        $current = $model->$field;
        $new = $newValue ?? null;

        if ($current != $new) {
            $model->$field = $new;
            if (! empty($new)) {
                $changes[] = ucfirst(str_replace('_', ' ', $field)).' updated to '.$new;
            }
        }
    }

    public function requestRefund(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $userId = backpack_auth()->id() ?? 'unknown';
        $userName = backpack_auth()->user()?->name ?? 'system';

        Log::info('REFUND_REQUEST_START', [
            'user_id' => $userId,
            'user_name' => $userName,
            'booking_id' => $id,
            'ip' => $request->ip(),
            'input_keys' => array_keys($request->all()),
        ]);

        $booking = Booking::find($id);

        if (! $booking) {
            Log::warning('REFUND_BOOKING_NOT_FOUND', [
                'requested_id' => $id,
                'user_id' => $userId,
            ]);

            return redirect()->back()->with('error', 'Booking not found.');
        }

        Log::info('REFUND_BOOKING_FOUND', [
            'booking_id' => $booking->id,
            'current_status' => $booking->status,
            'booking_amount' => $booking->booking_amount ?? 'MISSING_FIELD',
            'user_id' => $userId,
        ]);

        $validator = Validator::make($request->all(), [
            'deduction' => 'required|numeric|min:0|lte:booking_amount',
            'remaining_amount' => 'required|numeric|min:0',
            'bank_name' => 'required|string|max:255',
            'branch_name' => [
                'required',
                'regex:/^[A-Za-z0-9\s\-\.\,\/]{3,255}$/',
            ],
            'account_type' => 'required|in:savings,current',
            'account_number' => [
                'required',
                'regex:/^[0-9]{9,18}$/',
            ],
            'holder_name' => [
                'required',
                'regex:/^[A-Za-z\s\.]{3,255}$/',
            ],
            'ifsc_code' => [
                'required',
                'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            ],
            'deduction_reason' => 'required|string|max:500',
            'acc_proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'aadhar' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'pan' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ], [
            'deduction.lte' => 'Deduction cannot exceed booking amount.',
            'acc_proof.required' => 'Account proof is mandatory.',
            'acc_proof.max' => 'File size max 2MB.',

            'branch_name.regex' => 'Enter a valid branch name.',
            'holder_name.regex' => 'Enter a valid account holder name.',
            'account_number.regex' => 'Account number must contain only 9-18 digits.',
            'ifsc_code.regex' => 'Enter a valid IFSC code.',

            'aadhar.required' => 'Aadhaar document is mandatory.',
            'pan.required' => 'PAN document is mandatory.',
        ]);

        if ($validator->fails()) {
            Log::warning('REFUND_VALIDATION_FAILED', [
                'booking_id' => $id,
                'user_id' => $userId,
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['_token', 'acc_proof', 'aadhar', 'pan']),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        Log::info('REFUND_VALIDATION_PASSED', ['booking_id' => $id]);

        $calculated = (float) ($booking->booking_amount ?? 0) - (float) ($request->deduction ?? 0);
        $submitted = (float) ($request->remaining_amount ?? 0);

        Log::debug('REFUND_AMOUNT_CALCULATION', [
            'booking_amount' => $booking->booking_amount ?? 'null',
            'deduction' => $request->deduction,
            'calculated' => $calculated,
            'submitted' => $submitted,
            'difference' => abs($calculated - $submitted),
        ]);

        if (abs($calculated - $submitted) > 0.01) {
            Log::warning('REFUND_AMOUNT_MISMATCH', [
                'booking_id' => $id,
                'calculated' => $calculated,
                'submitted' => $submitted,
                'diff' => abs($calculated - $submitted),
                'user_id' => $userId,
            ]);

            return redirect()->back()
                ->with('error', 'Remaining amount does not match calculation.')
                ->withInput();
        }

        try {
            Log::info('REFUND_CREATE_START', [
                'booking_id' => $id,
                'amount' => $submitted,
            ]);

            $refund = Xl_Refunds::create([
                'entity_type' => 'booking',
                'entity_id' => $booking->id,
                'bank_name' => strtoupper(trim($request->bank_name ?? '')),
                'branch_name' => strtoupper(trim($request->branch_name ?? '')),
                'account_type' => $request->account_type,
                'account_number' => trim($request->account_number ?? ''),
                'holder_name' => trim($request->holder_name ?? ''),
                'ifsc_code' => strtoupper(trim($request->ifsc_code ?? '')),
                'req_date' => now()->format('Y-m-d'),
                'req_by' => $userId,
                'amount' => $submitted,
                'details' => trim($request->deduction_reason ?? ''),
            ]);

            Log::notice('REFUND_RECORD_CREATED', [
                'refund_id' => $refund->id,
                'booking_id' => $booking->id,
                'amount' => $refund->amount,
                'req_by' => $userId,
            ]);

            $mediaCollectionMap = [
                'acc_proof' => 'acc-proof',
                'aadhar' => 'aadhar',
                'pan' => 'pan',
            ];

            foreach ($mediaCollectionMap as $field => $collection) {
                if ($request->hasFile($field) && $request->file($field)->isValid()) {
                    try {

                        $media = $refund->addMediaFromRequest($field)
                            ->withCustomProperties([
                                'document_type' => $field,
                            ])
                            ->toMediaCollection($collection, 'public');
                        Log::info('REFUND_MEDIA_ADDED', [
                            'refund_id' => $refund->id,
                            'field' => $field,
                            'collection' => $collection,
                            'media_id' => $media->id ?? 'unknown',
                            'file_name' => $media->file_name ?? 'unknown',
                        ]);
                    } catch (Exception $mediaEx) {
                        Log::error('REFUND_MEDIA_UPLOAD_FAILED', [
                            'refund_id' => $refund->id,
                            'field' => $field,
                            'collection' => $collection,
                            'message' => $mediaEx->getMessage(),
                        ]);
                    }
                }
            }

            $oldStatus = $booking->status;

            $booking->update([
                'status' => 4,
                'refund_request_date' => now(),
            ]);

            if ($booking->status == 7) {

                $booking->addHistory(
                    'commented',
                    'Refund Requested Again',
                    'Refund requested again after rejection.',
                    [
                        'old_status' => 'Refund Rejected',
                        'new_status' => 'Refund Queued',
                    ],
                    null,
                    backpack_user()
                );
            }

            $booking->addHistory(
                'commented',
                'Refund Requested',
                'Customer refund request has been submitted .',
                [
                    'refund_amount' => $refund->amount ?? 0,
                    'booking_amount' => $booking->booking_amount ?? 0,
                    'deduction_amount' => $request->deduction ?? 0,
                    'bank_name' => $request->bank_name ?? 'N/A',
                    'account_holder' => $request->holder_name ?? 'N/A',
                    'account_number' => $request->account_number ?? 'N/A',
                    'ifsc_code' => $request->ifsc_code ?? 'N/A',
                    'deduction_reason' => $request->deduction_reason ?? 'N/A',
                    'status' => 'refund_requested',
                ],
                null,
                backpack_user()
            );

            Log::notice('REFUND_BOOKING_STATUS_UPDATED', [
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => $booking->status,
                'refund_id' => $refund->id,
                'user_id' => $userId,
            ]);

            Log::info('REFUND_REQUEST_COMPLETED_SUCCESS', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
            ]);

            return redirect(backpack_url('sales/booking/cancelled'))
                ->with('success', 'Refund request submitted successfully for Booking #'.$booking->id);
        } catch (QueryException $dbEx) {
            Log::critical('REFUND_DATABASE_ERROR', [
                'booking_id' => $id,
                'sql_error' => $dbEx->getMessage(),
                'sql_code' => $dbEx->getCode(),
                'input' => $request->except(['_token', 'acc_proof', 'aadhar', 'pan']),
            ]);

            return redirect()->back()
                ->with('error', 'Database error while processing refund.')
                ->withInput();
        } catch (Exception $e) {
            Log::error('REFUND_PROCESS_UNEXPECTED_ERROR', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['_token', 'acc_proof', 'aadhar', 'pan']),
            ]);

            return redirect()->back()
                ->with('error', 'Something went wrong while processing refund.')
                ->withInput();
        }
    }

    public function statusave(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        \Log::debug('statusave called', [
            'booking_id' => $id,
            'request_all' => $request->all(),
            'ip' => $request->ip(),
        ]);

        try {
            $booking = Booking::findOrFail($id);
            \Log::info('Booking found', ['id' => $id, 'current_status' => $booking->status]);

            $oldStatus = $booking->status;
            $newStatus = $request->input('status');

            \Log::info('Status change requested', [
                'old' => $oldStatus,
                'new' => $newStatus,
                'remark' => $request->input('remark'),
            ]);

            $statusNames = [
                1 => 'Live',
                2 => 'Invoiced',
                3 => 'Cancelled',
                4 => 'Refund Queued',
                5 => 'Refunded',
                6 => 'On Hold',
                7 => 'Refund Rejected',
                8 => 'Pending',
            ];

            $oldName = $statusNames[$oldStatus] ?? 'Unknown';
            $newName = $statusNames[$newStatus] ?? 'Unknown';

            $statusRemark = ($oldStatus != $newStatus)
                ? "Booking status changed from {$oldName} to {$newName}"
                : null;

            $adminRemark = $request->input('remark', 'Restored from cancelled');

            \Log::info('Preparing followup log', [
                'status_remark' => $statusRemark,
                'admin_remark' => $adminRemark,
            ]);

            $booking->update([
                'status' => $newStatus,
                'refund_request_date' => null,
            ]);

            if ($oldStatus == 3 && in_array($newStatus, [1, 8])) {

                $booking->addHistory(
                    'commented',
                    'Booking Restored',
                    "Booking restored from {$oldName} to {$newName}.",
                    [
                        'old_status' => $oldName,
                        'new_status' => $newName,
                        'remark' => $request->remark,
                    ],
                    null,
                    backpack_user()
                );
            }

            if ($oldStatus == 6 && in_array($newStatus, [1, 8])) {

                $booking->addHistory(
                    'commented',
                    'On Hold Removed',
                    "Booking restored from {$oldName} to {$newName}.",
                    [
                        'old_status' => $oldName,
                        'new_status' => $newName,
                        'remark' => $request->remark,
                    ],
                    null,
                    backpack_user()
                );
            }

            if ($newStatus == 7) {

                $booking->addHistory(
                    'commented',
                    'Refund Rejected',
                    'Refund request rejected.',
                    [
                        'old_status' => $oldName,
                        'new_status' => $newName,
                        'remark' => $request->remark ?? null,
                    ],
                    null,
                    backpack_user()
                );
            }
            if ($oldStatus == 7 && $newStatus == 4) {

                $booking->addHistory(
                    'commented',
                    'Refund Requested Again',
                    'Customer requested refund again after rejection.',
                    [
                        'remark' => $request->remark ?? null,
                    ],
                    null,
                    backpack_user()
                );
            }
            \Log::info('Booking updated successfully', ['new_status' => $newStatus]);

            return redirect(backpack_url('sales/booking'))
                ->with('success', 'Booking successfully restored!');
        } catch (Exception $e) {
            \Log::error('Error in statusave', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'booking_id' => $id,
            ]);

            return redirect()->back()->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    public function receiptEdit($bookingId, $receiptId)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('update');
        $receipt = Bookingamount::findOrFail($receiptId);

        if ($receipt->bid != $bookingId) {
            abort(403, 'This receipt does not belong to the specified booking.');
        }

        $booking = $this->crud->getEntry($bookingId);

        $this->data['entry'] = $receipt;
        $this->data['crud'] = $this->crud;
        $this->data['saveAction'] = $this->crud->getSaveAction();
        $this->data['title'] = 'Edit Receipt #'.($receipt->type_number ?? $receiptId);
        $this->data['booking'] = $booking;
        $this->data['booking_id'] = $bookingId;
        $this->data['receipt_id'] = $receiptId;

        return view('admin.booking.recedit', $this->data);
    }

    public function receiptUpdate(Request $request, $bookingId, $receiptId)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $receipt = Bookingamount::findOrFail($receiptId);

        if ($receipt->bid != $bookingId) {
            abort(403, 'Receipt does not belong to this booking.');
        }

        if ($request->has('action') && $request->action === 'delete') {
            $booking = Booking::findOrFail($bookingId);

            $booking->booking_amount = max(0, $booking->booking_amount - $receipt->amount);

            $booking->save();

            $booking->addHistory(
                'commented',
                'Receipt Deleted',
                "Receipt No. {$receipt->type_number} deleted. Amount ₹".number_format($receipt->amount, 2).
                    ' deducted from booking. New Booking Amount: ₹'.number_format($booking->booking_amount, 2),
                [
                    'receipt_no' => $receipt->type_number,
                    'deleted_amount' => $receipt->amount,
                    'new_booking_amount' => $booking->booking_amount,
                ],
                null,
                backpack_user()
            );

            $receipt->clearMediaCollection('amount-proof');
            $receipt->delete();

            \Alert::warning('Receipt deleted and amount deducted from booking .')->flash();

            return redirect(backpack_url("sales/booking/{$bookingId}/pending-edit"));
        }

        $validated = $request->validate([
            'reciept' => 'required|string|max:100',
            'mode' => 'required|in:Cash,Cheque,Bank Transfer,UPI',
            'date' => 'required|date_format:d-M-Y',
            'amount' => 'required|numeric|min:0',
            'amount_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $receipt->type_number = $request->reciept;
        $receipt->mode = $request->mode;
        $receipt->date = Carbon::createFromFormat(
            'd-M-Y',
            $request->date
        )->format('Y-m-d');
        $receipt->amount = $request->amount;

        if ($request->hasFile('amount_proof') && $request->file('amount_proof')->isValid()) {
            $receipt->clearMediaCollection('amount-proof');

            $receipt->addMediaFromRequest('amount_proof')
                ->toMediaCollection('amount-proof');
        }

        $receipt->save();
        $booking = Booking::findOrFail($bookingId);

        $oldReceiptNo = $receipt->type_number;
        $oldReceiptDate = $receipt->date;
        $oldAmount = $receipt->amount;

        $booking->addHistory(
            'commented',
            'Receipt Updated',
            "Receipt updated successfully. Amount changed from ₹{$oldAmount} to ₹{$request->amount}.",
            [
                'old_receipt_no' => $oldReceiptNo,
                'new_receipt_no' => $request->reciept_no,
                'old_date' => $oldReceiptDate,
                'new_date' => $request->receipt_date,
                'old_amount' => $oldAmount,
                'new_amount' => $request->amount,
            ],
            null,
            backpack_user()
        );

        \Alert::success('Receipt updated .')->flash();

        return redirect(backpack_url('sales/booking/'.$bookingId.'/pending-edit'));
    }

    // public function dealerInvoice($id)
    // {
    //     $this->crud->hasAccessOrFail('list');

    //     $booking = Booking::findOrFail($id);

    //     if ($booking->dealer_status != 1) {
    //         return redirect()->back()->with('error', 'This booking is not pending for dealer invoice.');
    //     }

    //     $this->data['crud'] = $this->crud;
    //     $this->data['title'] = 'Dealer Invoice Details - Booking #' . $booking->id;
    //     $this->data['booking'] = $booking;
    //     $this->data['saveAction'] = backpack_url("sales/booking/{$id}/dealer-invoice");

    //     $this->data['lookups'] = $this->getCommonLookups();

    //     return view('admin.booking.dealer-edit', $this->data);
    // }
    public function dealerInvoice($id)
    {
        if (! backpack_user()->can('SLS_BKNG_INVOICE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $booking = Booking::findOrFail($id);

        if ($booking->dealer_status != 1) {
            return redirect()->back()->with(
                'error',
                'This booking is not pending for dealer invoice.'
            );
        }

        $enquiry = null;

        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        $customerName = $booking->name
            ?? $enquiry?->name
            ?? null;

        $branchCode = $booking->branch_code
            ?? $enquiry?->dealer_branch;

        $locationCode = $booking->location_code
            ?? $enquiry?->dealer_location;

        $modelCode = $booking->model_code
            ?? $enquiry?->model_code;

        $variantCode = $booking->variant_code
            ?? $enquiry?->variant_code;

        $colorCode = $booking->color_code
            ?? $enquiry?->color_code;

        $branchName = '—';

        if (! empty($branchCode)) {

            $branchName = Branch::where('code', $branchCode)
                ->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $branchCode)
                    ->value('name');
            }
        }

        $branchName = $branchName ?: '—';

        $locationName = '—';

        if (! empty($locationCode)) {

            $locationName = Location::where('code', $locationCode)
                ->value('name');
        }

        $locationName = $locationName
            ?: ($booking->location_other ?? '—');

        $modelName = '—';

        if (! empty($modelCode)) {

            $modelName = VehicleModel::where('code', $modelCode)
                ->value('name');
        }

        $modelName = $modelName ?: ($modelCode ?: '—');

        $variantName = '—';
        $colorName = '—';

        if (! empty($variantCode)) {

            $variantRows = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->get([
                    'custom_name',
                    'color',
                    'color_code',
                ]);

            if ($variantRows->isNotEmpty()) {
                $variantName = $variantRows->first()->custom_name ?? '—';
                if (! empty($colorCode)) {
                    $colorRow = $variantRows->first(function ($row) use ($colorCode) {
                        return strtoupper((string) $row->color_code)
                            === strtoupper((string) $colorCode);
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

        $this->data['crud'] = $this->crud;
        $this->data['title'] =
            'Dealer Invoice Details - Booking #'.$booking->id;

        $this->data['booking'] = $booking;

        $this->data['customer_name'] = $customerName;
        $this->data['branch_name'] = $branchName;
        $this->data['location_name'] = $locationName;
        $this->data['model_name'] = $modelName;
        $this->data['variant_name'] = $variantName;
        $this->data['color_name'] = $colorName;

        $this->data['saveAction'] =
            backpack_url("sales/booking/{$id}/dealer-invoice");

        $this->data['lookups'] = $this->getCommonLookups();

        return view('admin.booking.dealer-edit', $this->data);
    }

    public function dealerInvoiceUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_INVOICE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $this->crud->hasAccessOrFail('update');

        if ($booking->dealer_status != 1) {
            return redirect()->back()->with('error', 'This booking is not pending for dealer invoice.')->withInput();
        }

        $validator = Validator::make($request->all(), [
            'dms_invoice_number' => 'required|string|regex:/^INV\d{2}[A-Z]\d{6}$/',
            'dms_invoice_date' => 'required|date|before_or_equal:today',
            'hidden_dealer_invoice_number' => 'nullable|string',
            'hidden_dealer_inv_date' => 'nullable|date',
        ], [
            'dms_invoice_number.required' => 'DMS Invoice Number is required.',
            'dms_invoice_number.regex' => 'DMS Invoice Number must be in format INV00A123456.',
            'dms_invoice_date.required' => 'DMS Invoice Date is required.',
            'dms_invoice_date.before_or_equal' => 'DMS Invoice Date cannot be in the future.',
        ]);

        if ($validator->fails()) {
            Log::warning('Dealer Invoice Validation Failed', [
                'booking_id' => $id,
                'errors' => $validator->errors()->toArray(),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $booking->inv_no = $request->input('dms_invoice_number');
            $booking->inv_date = $request->input('dms_invoice_date');
            $booking->dealer_status = 2;

            if ($request->filled('hidden_dealer_invoice_number')) {
                $booking->dealer_inv_no = $request->input('hidden_dealer_invoice_number');
            }
            if ($request->filled('hidden_dealer_inv_date')) {
                $booking->dealer_inv_date = $request->input('hidden_dealer_inv_date');
            }

            Log::info('Dealer Invoice Update - Before Save', [
                'booking_id' => $id,
                'inv_no' => $booking->inv_no,
                'inv_date' => $booking->inv_date,
                'dealer_status' => $booking->dealer_status,
                'dealer_inv_no' => $booking->dealer_inv_no,
                'dealer_inv_date' => $booking->dealer_inv_date,
            ]);

            $booking->save();

            Log::info('Dealer Invoice Updated Successfully', [
                'booking_id' => $id,
                'inv_no' => $booking->inv_no,
                'status' => 'success',
            ]);

            return redirect()->route('sales.booking.pending-invoices')
                ->with('success', 'Dealer invoice details updated successfully for Booking #'.$booking->id);
        } catch (Exception $e) {
            Log::error('Dealer Invoice Update Failed', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update dealer invoice details. Please try again.')
                ->withInput();
        }
    }

    public function insEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_INSURANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $insurance = XlInsurance::where('bid', $id)->first();
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
                $booking->location_other = $enquiry->dealer_location_other
                    ?? $booking->location_other;
            }
        }
        $data = [];
        $data['segments'] = CommonHelper::getVehicleSegments() ?? [];
        $data['models'] = CommonHelper::getVehicleModels(
            $booking->segment_code ?? null
        ) ?? [];
        $data['variants'] = CommonHelper::getVehicleVariants(
            $booking->model_code ?? null
        ) ?? [];
        $data['colors'] = CommonHelper::getVehicleColors(
            $booking->variant_code ?? null
        ) ?? [];
        $data['branches'] = CommonHelper::getBranches() ?? [];
        $locations = CommonHelper::getLocations(
            $booking->branch_code
        ) ?? [];
        usort($locations, fn ($a, $b) => strcmp(
            ($a['name'] ?? '').' - '.($a['code'] ?? ''),
            ($b['name'] ?? '').' - '.($b['code'] ?? '')
        ));
        $data['locations'] = $locations;
        $data['branch'] = Branch::where(
            'code',
            $booking->branch_code
        )->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code
            ? Location::where('code', $booking->location_code)->value('name')
            : ($booking->location_other ?? 'N/A');
        $data['fbranch'] = $data['branch'];
        $data['flocation'] = $data['location'];
        $data['insurances'] = XlInsurer::select(
            'id',
            'name',
            'short_name'
        )->get()->toArray();
        $data['insurers'] = $data['insurances'];
        $data['allusers'] = OrgService::getUsers(deptCode: 'SLS');
        $data['saleconsultants'] = OrgService::salesConsultants();
        $stock = Stock::find($booking->chassis_no);
        if ($stock) {
            $data['bchasis'] = $stock->chassis_no;
            $data['chassis'] = Stock::where(
                'model_code',
                $stock->model_code
            )
                ->select('chassis_no', 'id')
                ->get()
                ->toArray();
        } else {
            $data['bchasis'] = 'Not Available';
            $data['chassis'] = [];
        }
        $data['dsa_details'] = XL_DSA_MASTER::all()
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
        $drec = XL_DSA_MASTER::find($booking->dsa_id);
        $dsaname = $drec
            ? $drec->name.' - '.$drec->mobile
            : 'N/A';
        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';
        $data['accessories_dropdown'] = Accessory::getAccessories(
            $booking->segment_code ?? '',
            $booking->model_code ?? '',
            $booking->variant_code ?? ''
        );
        $data['enum_master'] = OrgService::getKeyValuesByCode(
            'EXISTING_CAR_OEM'
        );
        $uid = backpack_auth()->id();

        return view('admin.booking.insurance-edit', compact(
            'booking',
            'insurance',
            'data',
            'dsaname',
            'uid'
        ));
    }

    public function insUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_INSURANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        Log::info('insUpdate called', [
            'booking_id' => $request->booking_id ?? 'missing',
            'user_id' => backpack_auth()->id() ?? 'guest',
            'ip' => $request->ip(),
            'all_input' => $request->except(['policy_copy']),
        ]);

        try {
            $validated = $request->validate([
                'booking_id' => 'required',
                'insurance_category' => 'required|integer|in:1,2,3',
                'insurance_company' => 'required|integer',
                'policy_no' => 'required|string|min:10|max:20|regex:/^[A-Z0-9]+$/i',
                'hidden_policy_date' => 'required|date_format:Y-m-d',
                'policy_type' => 'required|integer|in:1,2,3,4',
                'policy_copy' => 'nullable|file|mimes:pdf|max:5120',
            ]);

            Log::info('Validation passed successfully', [
                'booking_id' => $request->booking_id,
                'policy_no' => $request->policy_no,
            ]);

            $data = [
                'bid' => $request->booking_id,
                'source' => $request->insurance_category,
                'insurer' => $request->insurance_company,
                'pol_no' => strtoupper($request->policy_no),
                'pol_date' => $request->hidden_policy_date,
                'pol_type' => $request->policy_type,
                'status' => 1,
                'updated_by' => backpack_auth()->id() ?? 1,
            ];

            $allFieldsFilled = $request->filled([
                'booking_id',
                'insurance_category',
                'insurance_company',
                'policy_no',
                'hidden_policy_date',
                'policy_type',
            ]) && $request->hasFile('policy_copy');

            if ($allFieldsFilled) {
                $data['status'] = 2;
                Log::info('All required fields filled + file uploaded → status set to 2');
            } else {
                Log::info('Status remains 1 - missing some required field or file');
            }

            Log::info('Attempting to update/create insurance record', ['bid' => $request->booking_id]);

            $insurance = XlInsurance::updateOrCreate(
                ['bid' => $request->booking_id],
                $data
            );
            $booking = Booking::find($request->booking_id);

            if ($booking) {

                $booking->addHistory(
                    'commented',
                    'Insurance Process Completed',
                    'Insurance details updated successfully',
                    [
                        'module' => 'Insurance',
                        'insurance_type' => $request->insurance_category,
                        'insurance_company' => $request->insurance_company,
                        'policy_no' => strtoupper($request->policy_no),
                        'policy_date' => $request->hidden_policy_date,
                        'policy_type' => $request->policy_type,
                        'status' => $data['status'],
                    ],
                    null,
                    backpack_user()
                );
            }

            Log::info('Insurance record saved/updated', [
                'insurance_id' => $insurance->id,
                'bid' => $insurance->bid,
                'status' => $insurance->status,
            ]);

            if ($request->hasFile('policy_copy') && $request->file('policy_copy')->isValid()) {
                Log::info('Policy copy file detected', [
                    'original_name' => $request->file('policy_copy')->getClientOriginalName(),
                    'size' => $request->file('policy_copy')->getSize().' bytes',
                ]);

                $insurance->clearMediaCollection('policy_copy');
                Log::info('Cleared old policy_copy media collection');

                $insurance->addMediaFromRequest('policy_copy')
                    ->usingFileName("policy_{$request->booking_id}_".time().'.pdf')
                    ->toMediaCollection('policy_copy');

                Log::info('New policy copy file uploaded successfully');
            } else {
                Log::info('No valid policy_copy file uploaded or file invalid');
            }

            Log::info('insUpdate completed successfully', ['booking_id' => $request->booking_id]);

            return redirect()->route('sales.booking.pending-insurance')
                ->with('success', 'Insurance details saved successfully for Booking #'.$request->booking_id);
        } catch (ValidationException $e) {
            Log::warning('Validation failed in insUpdate', [
                'booking_id' => $request->booking_id ?? 'unknown',
                'errors' => $e->errors(),
            ]);

            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (Exception $e) {
            Log::error('Critical error in insUpdate', [
                'booking_id' => $request->booking_id ?? 'unknown',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save insurance data. Please check logs or try again.')
                ->withInput();
        }
    }

    public function rtoEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_RTO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);
        $rto = XlRto::where('bid', $id)->first();
        if ($booking->enq_no) {

            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
            if ($enquiry) {
                $booking->name = $enquiry->name;
                $booking->care_of = $enquiry->care_of;
                $booking->care_of_type = $enquiry->care_of_type;
                $booking->mobile = $enquiry->mobile;
                $booking->branch_code = $enquiry->dealer_branch;
                $booking->location_code = $enquiry->dealer_location;
                $booking->segment_code = $enquiry->segment_code;
                $booking->model_code = $enquiry->model_code;
                $booking->variant_code = $enquiry->variant_code;
                $booking->color_code = $enquiry->color_code;
                $booking->location_other = $enquiry->dealer_location_other
                    ?? $booking->location_other;
            }
        }
        $data = [];
        $data['permit_map'] = OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(function ($permit, $index) {
                return [
                    (string) ($index + 1) => $permit->value,
                ];
            })
            ->toArray();
        $data['segments'] = CommonHelper::getVehicleSegments() ?? [];
        $data['models'] = CommonHelper::getVehicleModels(
            $booking->segment_code ?? null
        ) ?? [];

        $data['variants'] = CommonHelper::getVehicleVariants(
            $booking->model_code ?? null
        ) ?? [];

        $data['colors'] = CommonHelper::getVehicleColors(
            $booking->variant_code ?? null
        ) ?? [];

        $data['branch'] = Branch::where('code', $booking->branch_code)
            ->value('name') ?? 'N/A';

        $data['location'] = $booking->location_code
            ? (Location::where('code', $booking->location_code)->value('name') ?? 'N/A')
            : ($booking->location_other ?? 'N/A');

        $data['fbranch'] = $data['branch'];
        $data['flocation'] = $data['location'];

        $data['rto_rules'] = XlRtoRules::select(
            'sale_type',
            'permit',
            'body_type',
            'reg_no_type',
            'trc_number',
            'trc_pay',
            'trc_copy',
            'app_no',
            'tax_pay',
            'veh_reg',
            'tax_copy'
        )->get()->toArray();

        $data['allusers'] = OrgService::usersByDepartment('SLS');

        $data['saleconsultants'] = OrgService::usersByDesignation('CNS');

        $stock = Stock::find($booking->chassis_no);

        if ($stock) {

            $data['bchasis'] = $stock->chassis_no;

            $data['chassis'] = Stock::where(
                'model_code',
                $stock->model_code
            )
                ->select('chassis_no', 'id')
                ->get()
                ->toArray();
        } else {

            $data['bchasis'] = 'Not Available';
            $data['chassis'] = [];
        }

        $data['dsa_details'] = XL_DSA_MASTER::all()
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

        $drec = XL_DSA_MASTER::find($booking->dsa_id);

        $dsaname = $drec
            ? $drec->name.' - '.$drec->mobile
            : 'N/A';

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';

        $uid = backpack_auth()->id();

        return view(
            'admin.booking.rto-edit',
            compact(
                'booking',
                'rto',
                'data',
                'dsaname',
                'uid'
            )
        );
    }

    // public function rtoUpdate(Request $request, $id)
    // {
    //     $permit_map = OrgService::getKeyValuesByCode('RTO_PERMIT')
    //         ->sortBy('id')
    //         ->values()
    //         ->mapWithKeys(function ($permit, $index) {
    //             return [
    //                 (string) ($index + 1) => $permit->value
    //             ];
    //         })
    //         ->toArray();

    //     $validated = $request->validate([
    //         'trade_used' => 'required|in:1,2,3,4,5,6',
    //         'sale_type' => 'required|in:1,2',

    //         'permit' => [
    //             'required',
    //             Rule::in(array_keys($permit_map)),
    //         ],

    //         'body_type' => 'required|in:1,2',
    //         'registration_type' => 'required|in:0,1,2,3',
    //         'reg_no_type' => 'required|in:1,2,3',

    //         'trc_number' => 'nullable|string|max:15|regex:/^[A-Z0-9]{10,15}$/',
    //         'bank_ref_no' => 'nullable|string|max:20|regex:/^[A-Z0-9]{10,20}$/',
    //         'trc_copy' => 'nullable|file|mimes:pdf|max:5120',
    //         'application_no' => 'nullable|string|max:15|regex:/^[A-Z0-9]{10,15}$/',
    //         'tax_payment_ref_no' => 'nullable|string|max:20|regex:/^[A-Z0-9]{10,20}$/',
    //         'vehicle_reg_no' => 'nullable|string',
    //         'tax_receipt_copy' => 'nullable|file|mimes:pdf|max:5120',
    //     ]);

    //     try {
    //         $rto_rules = XlRtoRules::select(
    //             'sale_type',
    //             'permit',
    //             'body_type',
    //             'reg_no_type',
    //             'trc_number',
    //             'trc_pay',
    //             'trc_copy',
    //             'app_no',
    //             'tax_pay',
    //             'veh_reg',
    //             'tax_copy'
    //         )->get()->toArray();

    //         $saleTypeMap = [
    //             '1' => 'Within State',
    //             '2' => 'Outside State',
    //         ];

    //         $bodyTypeMap = [
    //             '1' => 'Complete',
    //             '2' => 'CBC',
    //         ];

    //         $regNoTypeMap = [
    //             '1' => 'Regular',
    //             '2' => 'BH',
    //             '3' => 'Special',
    //         ];

    //         $saleText       = $saleTypeMap[$request->sale_type]       ?? '';
    //         $permitText = $permit_map[$request->permit] ?? '';            $bodyText       = $bodyTypeMap[$request->body_type]      ?? '';
    //         $regNoTypeText  = $regNoTypeMap[$request->reg_no_type]   ?? '';

    //         $matchingRule = null;
    //         foreach ($rto_rules as $rule) {
    //             if (
    //                 $rule['sale_type']    === $saleText &&
    //                 $rule['permit']       === $permitText &&
    //                 $rule['body_type']    === $bodyText &&
    //                 $rule['reg_no_type']  === $regNoTypeText
    //             ) {
    //                 $matchingRule = $rule;
    //                 break;
    //             }
    //         }

    //         $allRequiredFilled = true;

    //         if ($matchingRule) {
    //             $fieldMap = [
    //                 'trc_number'         => 'trc_number',
    //                 'bank_ref_no'        => 'trc_pay',
    //                 'trc_copy'           => 'trc_copy',
    //                 'application_no'     => 'app_no',
    //                 'tax_payment_ref_no' => 'tax_pay',
    //                 'vehicle_reg_no'     => 'veh_reg',
    //                 'tax_receipt_copy'   => 'tax_copy',
    //             ];

    //             foreach ($fieldMap as $formField => $ruleKey) {
    //                 if ($matchingRule[$ruleKey] === 'Yes') {
    //                     if (in_array($formField, ['trc_copy', 'tax_receipt_copy'])) {

    //                         if (!$request->hasFile($formField) || !$request->file($formField)->isValid()) {
    //                             $allRequiredFilled = false;
    //                             break;
    //                         }
    //                     } else {
    //                         if (!$request->filled($formField)) {
    //                             $allRequiredFilled = false;
    //                             break;
    //                         }
    //                     }
    //                 }
    //             }
    //         } else {
    //             $allRequiredFilled = false;
    //         }

    //         $data = [
    //             'bid'                      => $id,
    //             'trade_used'               => $request->trade_used,
    //             'sale_type'                => $request->sale_type,
    //             'permit'                   => $request->permit,
    //             'body_type'                => $request->body_type,
    //             'rgn_type'                 => $request->registration_type,
    //             'rgn_no_type'              => $request->reg_no_type,
    //             'trc_no'                   => $request->trc_number,
    //             'trc_payment_no'           => $request->bank_ref_no,
    //             'app_no'                   => $request->application_no,
    //             'tax_payment_bank_ref_no'  => $request->tax_payment_ref_no,
    //             'vh_rgn_no'                => $request->vehicle_reg_no,
    //             'status'                   => $allRequiredFilled ? 2 : 1,
    //             'updated_by'               => backpack_auth()->id() ?? 1,
    //         ];

    //         $rto = XlRto::updateOrCreate(
    //             ['bid' => $id],
    //             $data
    //         );

    //         $booking = Booking::find($id);

    //         if ($booking) {
    //             $finalData = json_decode($booking->final_data ?? '{}', true) ?: [];

    //             $finalData['registration_category'] = $request->registration_category;

    //             $booking->final_data = json_encode($finalData);
    //             $booking->save();
    //         }

    //         if ($booking) {

    //             $booking->addHistory(
    //                 'commented',
    //                 'RTO Process Completed',
    //                 'RTO details updated successfully',
    //                 [
    //                     'module'             => 'RTO',
    //                     'trade_used'         => $request->trade_used,
    //                     'sale_type'          => $request->sale_type,
    //                     'permit'             => $request->permit,
    //                     'body_type'          => $request->body_type,
    //                     'registration_type'  => $request->registration_type,
    //                     'reg_no_type'        => $request->reg_no_type,
    //                     'trc_number'         => $request->trc_no,
    //                     'application_no' => $request->application_no,
    //                     'vehicle_reg_no'     => $request->vh_rgn_no,
    //                     'status'             => $data['status'],
    //                 ],
    //                 null,
    //                 backpack_user()
    //             );
    //         }

    //         if ($request->hasFile('trc_copy') && $request->file('trc_copy')->isValid()) {
    //             $rto->clearMediaCollection('trc_copy');
    //             $rto->addMediaFromRequest('trc_copy')
    //                 ->toMediaCollection('trc_copy');
    //         }

    //         if ($request->hasFile('tax_receipt_copy') && $request->file('tax_receipt_copy')->isValid()) {
    //             $rto->clearMediaCollection('tax_receipt_copy');
    //             $rto->addMediaFromRequest('tax_receipt_copy')
    //                 ->toMediaCollection('tax_receipt_copy');
    //         }

    //         return redirect()
    //             ->route('sales.booking.pending-rto')
    //             ->with('success', 'RTO data saved successfully for Booking #' . $id);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return redirect()->back()
    //             ->withErrors($e->validator)
    //             ->withInput();
    //     } catch (\Exception $e) {
    //         \Log::error('RTO Update Failed', [
    //             'booking_id' => $id,
    //             'error'      => $e->getMessage(),
    //             'trace'      => $e->getTraceAsString()
    //         ]);

    //         return redirect()->back()
    //             ->with('error', 'Failed to save RTO data. Please try again or contact support.')
    //             ->withInput();
    //     }
    // }

    private function hasExistingRtoMedia($id, $collection)
    {
        $rto = XlRto::where('bid', $id)->first();

        if (! $rto) {
            return false;
        }

        return $rto->getFirstMedia($collection) !== null;
    }

    public function rtoUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_RTO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $permit_map = OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(function ($permit, $index) {
                return [
                    (string) ($index + 1) => $permit->value,
                ];
            })
            ->toArray();

        $validated = $request->validate([
            'trade_used' => 'required|in:1,2,3,4,5,6',

            'sale_type' => 'required|in:1,2',

            'permit' => [
                'required',
                Rule::in(array_keys($permit_map)),
            ],

            'body_type' => 'required|in:1,2',

            // IMPORTANT: 0 is valid
            'registration_type' => 'required|in:0,1,2,3',

            'reg_no_type' => 'required|in:1,2,3',

            'trc_number' => 'nullable|string|max:15|regex:/^[A-Z0-9]{10,15}$/',
            'bank_ref_no' => 'nullable|string|max:20|regex:/^[A-Z0-9]{10,20}$/',

            'trc_copy' => 'nullable|file|mimes:pdf|max:5120',

            'application_no' => 'nullable|string|max:15|regex:/^[A-Z0-9]{10,15}$/',

            'tax_payment_ref_no' => 'nullable|string|max:20|regex:/^[A-Z0-9]{10,20}$/',

            'vehicle_reg_no' => 'nullable|string',

            'tax_receipt_copy' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        try {
            $rto_rules = XlRtoRules::select(
                'sale_type',
                'permit',
                'body_type',
                'reg_no_type',
                'trc_number',
                'trc_pay',
                'trc_copy',
                'app_no',
                'tax_pay',
                'veh_reg',
                'tax_copy'
            )->get()->toArray();

            $saleTypeMap = [
                '1' => 'Within State',
                '2' => 'Outside State',
            ];

            $bodyTypeMap = [
                '1' => 'Complete',
                '2' => 'CBC',
            ];

            $regNoTypeMap = [
                '1' => 'Regular',
                '2' => 'BH',
                '3' => 'Special',
            ];

            $saleText = $saleTypeMap[$request->sale_type] ?? '';

            // IMPORTANT: use $permit_map, not $permitMap
            $permitText = $permit_map[$request->permit] ?? '';

            $bodyText = $bodyTypeMap[$request->body_type] ?? '';

            $regNoTypeText = $regNoTypeMap[$request->reg_no_type] ?? '';

            $matchingRule = null;

            foreach ($rto_rules as $rule) {
                if (
                    trim(strtoupper($rule['sale_type'])) === trim(strtoupper($saleText)) &&
                    trim(strtoupper($rule['permit'])) === trim(strtoupper($permitText)) &&
                    trim(strtoupper($rule['body_type'])) === trim(strtoupper($bodyText)) &&
                    trim(strtoupper($rule['reg_no_type'])) === trim(strtoupper($regNoTypeText))
                ) {
                    $matchingRule = $rule;
                    break;
                }
            }

            $allRequiredFilled = true;

            if ($matchingRule) {
                $fieldMap = [
                    'trc_number' => 'trc_number',
                    'bank_ref_no' => 'trc_pay',
                    'trc_copy' => 'trc_copy',
                    'application_no' => 'app_no',
                    'tax_payment_ref_no' => 'tax_pay',
                    'vehicle_reg_no' => 'veh_reg',
                    'tax_receipt_copy' => 'tax_copy',
                ];

                foreach ($fieldMap as $formField => $ruleKey) {

                    if (($matchingRule[$ruleKey] ?? '') === 'Yes') {

                        if (in_array($formField, ['trc_copy', 'tax_receipt_copy'])) {

                            // Existing file can satisfy requirement
                            $hasExistingFile = false;

                            if ($formField === 'trc_copy') {
                                $hasExistingFile = $this->hasExistingRtoMedia(
                                    $id,
                                    'trc_copy'
                                );
                            }

                            if ($formField === 'tax_receipt_copy') {
                                $hasExistingFile = $this->hasExistingRtoMedia(
                                    $id,
                                    'tax_receipt_copy'
                                );
                            }

                            if (
                                ! $hasExistingFile &&
                                (! $request->hasFile($formField) ||
                                    ! $request->file($formField)->isValid())
                            ) {
                                $allRequiredFilled = false;
                                break;
                            }

                        } else {

                            if (! $request->filled($formField)) {
                                $allRequiredFilled = false;
                                break;
                            }
                        }
                    }
                }
            } else {
                $allRequiredFilled = false;
            }

            $data = [
                'bid' => $id,
                'trade_used' => $request->trade_used,
                'sale_type' => $request->sale_type,
                'permit' => $request->permit,
                'body_type' => $request->body_type,
                'rgn_type' => $request->registration_type,
                'rgn_no_type' => $request->reg_no_type,
                'trc_no' => $request->trc_number,
                'trc_payment_no' => $request->bank_ref_no,
                'app_no' => $request->application_no,
                'tax_payment_bank_ref_no' => $request->tax_payment_ref_no,
                'vh_rgn_no' => $request->vehicle_reg_no,
                'status' => $allRequiredFilled ? 2 : 1,
                'updated_by' => backpack_auth()->id() ?? 1,
            ];

            $rto = XlRto::updateOrCreate(
                ['bid' => $id],
                $data
            );

            if (
                $request->hasFile('trc_copy') &&
                $request->file('trc_copy')->isValid()
            ) {
                $rto->clearMediaCollection('trc_copy');

                $rto->addMediaFromRequest('trc_copy')
                    ->toMediaCollection('trc_copy');
            }

            // Upload Tax receipt copy
            if (
                $request->hasFile('tax_receipt_copy') &&
                $request->file('tax_receipt_copy')->isValid()
            ) {
                $rto->clearMediaCollection('tax_receipt_copy');

                $rto->addMediaFromRequest('tax_receipt_copy')
                    ->toMediaCollection('tax_receipt_copy');
            }

            return redirect()
                ->route('sales.booking.pending-rto')
                ->with(
                    'success',
                    'RTO data saved successfully for Booking #'.$id
                );

        } catch (Exception $e) {

            \Log::error('RTO Update Failed', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to save RTO data: '.$e->getMessage())
                ->withInput();
        }
    }

    // public function PendDeliveryEdit($id)
    // {
    //     $booking = Booking::findOrFail($id);
    //     $segments = OrgService::segments() ?? [];
    //     $saleconsultants = OrgService::salesConsultants();
    //     $branch = Branch::where('branch_code', $booking->branch_code)
    //         ->value('name') ?? 'N/A';
    //     $location = $booking->location_code
    //         ? (Location::find($booking->location_code)?->name ?? 'N/A')
    //         : ($booking->location_other ?? 'N/A');
    //     $financier = XlFinancier::find($booking->financier)?->name ?? 'N/A';
    //     $insurance = XlInsurance::where('bid', $id)->first();
    //     $rto       = XlRto::where('bid', $id)->first();
    //     $bchasis = $booking->chassis_no ?? 'N/A';

    //     $data = [
    //         'segments'         => $segments,
    //         'saleconsultants'  => $saleconsultants,
    //         'branch'           => $branch,
    //         'location'         => $location,
    //         'financier'        => $financier,
    //         'bchasis'          => $bchasis,

    //     ];

    //     return view(
    //         'admin.booking.delivery-edit',
    //         compact('booking', 'data', 'insurance', 'rto')
    //     );
    // }

    public function PendDeliveryEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_DELIVERY')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $linkedEnquiry = null;

        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }
        if ($linkedEnquiry) {
            $booking->name = $linkedEnquiry->name;
            $booking->care_of = $linkedEnquiry->care_of;

            $booking->branch_code =
                $linkedEnquiry->dealer_branch ?? $booking->branch_code;

            $booking->location_code =
                $linkedEnquiry->dealer_location ?? $booking->location_code;

            $booking->location_other =
                $linkedEnquiry->dealer_location_other ?? $booking->location_other;

            $booking->model_code =
                $linkedEnquiry->model_code ?? $booking->model_code;

            $booking->variant_code =
                $linkedEnquiry->variant_code ?? $booking->variant_code;

            $booking->color_code =
                $linkedEnquiry->color_code ?? $booking->color_code;
        }
        $segments = OrgService::segments() ?? [];

        $saleconsultants = OrgService::salesConsultants();

        $branch = 'N/A';

        if (! empty($booking->branch_code)) {
            $branch = Branch::where('code', $booking->branch_code)
                ->value('name');

            if (! $branch) {
                $branch = Branch::where('branch_code', $booking->branch_code)
                    ->value('name');
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

        $financier = XlFinancier::find($booking->financier)?->name ?? 'N/A';

        $insurance = XlInsurance::where('bid', $id)->first();

        $rto = XlRto::where('bid', $id)->first();

        $bchasis = $booking->chassis_no ?? 'N/A';

        $data = [
            'segments' => $segments,
            'saleconsultants' => $saleconsultants,
            'branch' => $branch,
            'location' => $location,
            'financier' => $financier,
            'bchasis' => $bchasis,
        ];

        return view(
            'admin.booking.delivery-edit',
            compact(
                'booking',
                'data',
                'insurance',
                'rto'
            )
        );
    }

    public function PendDeliveryUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_DELIVERY')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        \Log::debug('PendDeliveryUpdate started', [
            'booking_id' => $id,
            'user_id' => backpack_auth()->id() ?? 'unknown',
            'ip' => $request->ip(),
        ]);

        \Log::debug('Request input (non-file)', $request->except(['photos']));

        $filesInfo = [];
        $photos = $request->file('photos') ?? [];
        foreach ($photos as $key => $file) {
            if ($file instanceof UploadedFile) {
                $filesInfo[$key] = [
                    'original_name' => $file->getClientOriginalName(),
                    'size_kb' => round($file->getSize() / 1024, 2),
                    'mime' => $file->getMimeType(),
                    'error' => $file->getError(),
                ];
            } else {
                $filesInfo[$key] = 'invalid-file-object';
            }
        }
        \Log::debug('Uploaded photos (nested structure)', $filesInfo);

        $rules = [
            'remarks' => 'required|string|max:1000',
            'photos.delivery_ceremony_with_customer' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.bonnet' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.windshield_glass' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.vehicle_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.vehicle_co_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.vehicle_rear_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.tire_front_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.tire_front_co_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.tire_rear_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.tire_rear_co_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.stepney' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.foot_rest_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.foot_rest_co_driver_side' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.tool_kit' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.vehicle_chassis_no_photo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.chassis_no_screenshot_invoice' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'photos.chassis_no_screenshot_insurance' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'chassis_no_verified' => 'nullable|boolean',
        ];

        try {
            $validated = $request->validate($rules);
            \Log::info('Validation passed', ['booking_id' => $id]);
        } catch (ValidationException $e) {
            \Log::warning('Validation failed', [
                'booking_id' => $id,
                'errors' => $e->errors(),
            ]);

            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }

        try {

            $delivery = XlDelivery::updateOrCreate(
                ['bid' => $id],
                [
                    'remarks' => $request->remarks,
                    'verification' => $request->boolean('chassis_no_verified', false),
                    'status' => 1,
                    'created_by' => backpack_auth()->id() ?? 1,
                    'updated_by' => backpack_auth()->id() ?? 1,
                ]
            );

            $booking = Booking::find($id);

            if ($booking) {

                $history = $booking->addHistory(
                    'commented',
                    'Delivery Process Completed',
                    'Vehicle delivery verification completed successfully',
                    [
                        'module' => 'Delivery',
                        'remarks' => $request->remarks,
                        'verification' => $request->boolean('chassis_no_verified', false),
                        'delivery_status' => 1,
                    ],
                    null,
                    backpack_user()
                );
            }

            \Log::info('Delivery record updated/created', [
                'delivery_id' => $delivery->id,
                'bid' => $id,
            ]);

            $collections = [
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

            foreach ($collections as $collection) {
                $photoKey = "photos.{$collection}";

                if ($request->hasFile($photoKey) && $request->file($photoKey)->isValid()) {
                    $file = $request->file($photoKey);

                    \Log::info("Processing photo: {$collection}", [
                        'original_name' => $file->getClientOriginalName(),
                        'size_kb' => round($file->getSize() / 1024, 2),
                    ]);

                    $delivery->clearMediaCollection($collection);
                    \Log::debug("Cleared old media: {$collection}");

                    $media = $delivery->addMedia($file)
                        ->toMediaCollection($collection, 'public');

                    \Log::info('Media added', [
                        'collection' => $collection,
                        'media_id' => $media->id,
                        'filename' => $media->file_name,
                    ]);
                } else {
                    \Log::debug("No valid file for: {$collection} (key: {$photoKey})");
                }
            }

            return redirect()
                ->route('sales.booking.pending-deliveries')
                ->with('success', 'Delivery updated successfully with photos! Booking #'.$id);
        } catch (FileCannotBeAdded $e) {
            \Log::error('Media upload error', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Photo upload failed: '.$e->getMessage())
                ->withInput();
        } catch (Exception $e) {
            \Log::critical('PendDeliveryUpdate failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Something went wrong while saving delivery. Check logs.')
                ->withInput();
        }
    }

    public function exchangeEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_EXCHANGE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);
        $exchange = XExchange::where('bid', $id)->first();

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

        $financeRecord = XFinance::where('bid', $id)->first();

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
            $branchName = Branch::where('code', $booking->branch_code)
                ->value('name');

            if (! $branchName) {
                $branchName = Branch::where('branch_code', $booking->branch_code)
                    ->value('name');
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
            $booking->segment_name =
                Segment::where('code', $booking->segment_code)
                    ->where('is_active', true)
                    ->value('name')
                ?? 'N/A';
        }

        $data['accessories'] = ! empty($accessoryNames)
            ? implode(', ', $accessoryNames)
            : 'N/A';
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
        $drec = XL_DSA_MASTER::find($booking->dsa_id);
        $dsaname = $drec ? $drec->name.'-'.$drec->mobile : 'N/A';
        $user = backpack_user();
        $collector = User::find($booking->col_by);
        if ($collector) {
            $data['collector_name'] = $collector->name.' - '.$collector->emp_code;
        } else {
            $data['collector_name'] = 'N/A';
        }
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
        $enumMasterIds = explode(',', $booking->exist_oem);
        $data['oem_ids'] = $enumMasterIds;
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

        return view(
            'admin.booking.exch-edit',
            compact(
                'booking',
                'exchange',
                'data',
                'dsaname',
                'uid',
                'bookingHistory'
            )
        );
    }

    // public function exchangeUpdate(Request $request, $id)
    // {
    //     $booking = Booking::findOrFail($id);
    //     $validator = Validator::make($request->all(), [
    //         'buyer_type' => 'required|string|in:First Time Buy,Additional Buy,Exchange Buy,Scrappage',
    //         'enum_master1' => 'nullable|integer',
    //         'vehicle_details' => 'nullable|string|max:255',
    //         'enum_master2' => 'nullable|integer',
    //         'vehicle_details2' => 'nullable|string|max:255',
    //         'registration_no' => 'required_if:buyer_type,Exchange Buy,Scrappage|nullable|string|max:255',
    //         'manufacturing_year' => 'required_if:buyer_type,Exchange Buy,Scrappage'. '|nullable|integer|min:1900|max:' . date('Y'),
    //         'odometer_reading' => 'required_if:buyer_type,Exchange Buy|nullable|numeric|min:0',
    //         'expected_price' => 'required_if:buyer_type,Exchange Buy|nullable|numeric|min:0',
    //         'offered_price' => 'required_if:buyer_type,Exchange Buy|nullable|numeric|min:0',
    //         'exchange_bonus' => 'required_if:buyer_type,Exchange Buy|nullable|numeric|min:0',
    //         'update' => 'required|integer|in:1,2,3',
    //         'case_status' => 'required|integer|in:1,2,3',
    //         'remark' => 'required|string',
    //     ]);
    //     if ($validator->fails()) {
    //         return redirect()->back()->withInput()->with('error', $validator->messages()->first());
    //     }
    //     $rem = [];
    //     $verificationStatusMap = [
    //         1 => 'Unverified',
    //         2 => 'Verified (Data Match)',
    //         3 => 'Verified (Data Mismatch)',
    //     ];
    //     $caseStatusMap = [
    //         1 => 'In-Process',
    //         2 => 'Exchange Done',
    //         3 => 'Case Lost',
    //     ];
    //     if ($booking->buyer_type != $request->buyer_type) {
    //         $tvl = empty($booking->buyer_type) ? 'null' : $booking->buyer_type;

    //         $rem[] = "Buyer Type Changed from " . $tvl . " to " . $request->buyer_type;

    //         $booking->buyer_type = $request->input('buyer_type');
    //     }

    //     if ($booking->exist_oem1 != $request->enum_master1) {
    //         $tvl = empty($booking->exist_oem1) ? 'null' : $booking->exist_oem1;
    //         $rem[] = "Brand (Make 1) Changed from " . $tvl . " to " . $request->enum_master1;
    //         $booking->exist_oem1 = $request->input('enum_master1');
    //     }
    //     if ($booking->vh1_detail != $request->vehicle_details) {
    //         $tvl = empty($booking->vh1_detail) ? 'null' : $booking->vh1_detail;
    //         $rem[] = "Model & Variant 1 Changed from " . $tvl . " to " . $request->vehicle_details;
    //         $booking->vh1_detail = $request->input('vehicle_details');
    //     }
    //     if ($booking->exist_oem2 != $request->enum_master2) {
    //         $tvl = empty($booking->exist_oem2) ? 'null' : $booking->exist_oem2;
    //         $rem[] = "Brand (Make 2) Changed from " . $tvl . " to " . $request->enum_master2;
    //         $booking->exist_oem2 = $request->input('enum_master2');
    //     }
    //     if ($booking->vh2_detail != $request->vehicle_details2) {
    //         $tvl = empty($booking->vh2_detail) ? 'null' : $booking->vh2_detail;
    //         $rem[] = "Model & Variant 2 Changed from " . $tvl . " to " . $request->vehicle_details2;
    //         $booking->vh2_detail = $request->input('vehicle_details2');
    //     }
    //     if ($booking->registration_no != $request->registration_no) {
    //         $tvl = empty($booking->registration_no) ? 'null' : $booking->registration_no;
    //         $rem[] = "Vehicle Registration No. Changed from " . $tvl . " to " . $request->registration_no;
    //         $booking->registration_no = $request->input('registration_no');
    //     }
    //     if ($booking->make_year != $request->manufacturing_year) {
    //         $tvl = empty($booking->make_year) ? 'null' : $booking->make_year;
    //         $rem[] = "Manufacturing Year Changed from " . $tvl . " to " . $request->manufacturing_year;
    //         $booking->make_year = $request->input('manufacturing_year');
    //     }
    //     if ($booking->odo_reading != $request->odometer_reading) {
    //         $tvl = empty($booking->odo_reading) ? 'null' : $booking->odo_reading;
    //         $rem[] = "Odometer Reading Changed from " . $tvl . " to " . $request->odometer_reading;
    //         $booking->odo_reading = $request->input('odometer_reading');
    //     }
    //     if ($booking->expected_price != $request->expected_price) {
    //         $tvl = empty($booking->expected_price) ? 'null' : $booking->expected_price;
    //         $rem[] = "Expected Price Changed from " . $tvl . " to " . $request->expected_price;
    //         $booking->expected_price = $request->input('expected_price');
    //     }
    //     if ($booking->offered_price != $request->offered_price) {
    //         $tvl = empty($booking->offered_price) ? 'null' : $booking->offered_price;
    //         $rem[] = "Offered Price Changed from " . $tvl . " to " . $request->offered_price;
    //         $booking->offered_price = $request->input('offered_price');
    //     }
    //     if ($booking->exchange_bonus != $request->exchange_bonus) {
    //         $tvl = empty($booking->exchange_bonus) ? 'null' : $booking->exchange_bonus;
    //         $rem[] = "Exchange Bonus Changed from " . $tvl . " to " . $request->exchange_bonus;
    //         $booking->exchange_bonus = $request->input('exchange_bonus');
    //     }
    //     if ($request->has('remark')) {
    //         $booking->pending_remark = $request->input('remark');
    //         $rem[] = "Remarks updated: " . $request->input('remark');
    //     }
    //     $booking->save();
    //     $verificationStatus = $request->input('update');
    //     $caseStatus = $request->input('case_status');
    //     $purchaseType = $request->input('buyer_type');
    //     $exchangeEntry = XExchange::where('bid', $booking->id)->first();
    //     if (!$exchangeEntry) {

    //         $defaultVerificationStatus = $verificationStatus ?? 1;
    //         $defaultCaseStatus = $caseStatus ?? 1;
    //         $defaultPurchaseType = $purchaseType;

    //         XExchange::create([
    //             'bid' => $booking->id,

    //             'vh_id' => $request->enum_master1 ?? 0,

    //             'enum_master1' => $request->enum_master1,
    //             'enum_master2' => $request->enum_master2,
    //             'vehicle_details' => $request->vehicle_details,
    //             'vehicle_details2' => $request->vehicle_details2,
    //             'registration_no' => $request->registration_no,
    //             'manufacturing_year' => $request->manufacturing_year,
    //             'odometer_reading' => $request->odometer_reading,
    //             'expected_price' => $request->expected_price,
    //             'offered_price' => $request->offered_price,
    //             'exchange_bonus' => $request->exchange_bonus,

    //             'verification_status' => $defaultVerificationStatus,
    //             'case_status' => $defaultCaseStatus,
    //             'purchase_type' => $defaultPurchaseType,
    //         ]);

    //         $rem[] = "New exchange entry created with Verification Status: " . $verificationStatusMap[$defaultVerificationStatus] .
    //             " and Case Status: " . $caseStatusMap[$defaultCaseStatus];
    //     } else {
    //         $changes = [];
    //         if ($exchangeEntry->verification_status != $verificationStatus) {
    //             $oldVerification = $verificationStatusMap[$exchangeEntry->verification_status] ?? 'null';
    //             $newVerification = $verificationStatusMap[$verificationStatus];
    //             $changes[] = "Verification Status changed from " . $oldVerification . " to " . $newVerification;
    //         }
    //         if ($exchangeEntry->case_status != $caseStatus) {
    //             $oldCase = $caseStatusMap[$exchangeEntry->case_status] ?? 'null';
    //             $newCase = $caseStatusMap[$caseStatus];
    //             $changes[] = "Case Status changed from " . $oldCase . " to " . $newCase;
    //         }
    //         if (!empty($changes)) {
    //             $rem = array_merge($rem, $changes);
    //         }
    //         $exchangeEntry->update([
    //             'vh_id'                 => $request->enum_master1 ?? 0,
    //             'enum_master1'          => $request->enum_master1,
    //             'enum_master2'          => $request->enum_master2,
    //             'vehicle_details'      => $request->vehicle_details,
    //             'vehicle_details2'     => $request->vehicle_details2,
    //             'registration_no'      => $request->registration_no,
    //             'manufacturing_year'   => $request->manufacturing_year,
    //             'odometer_reading'     => $request->odometer_reading,
    //             'expected_price'       => $request->expected_price,
    //             'offered_price'        => $request->offered_price,
    //             'exchange_bonus'       => $request->exchange_bonus,
    //             'verification_status'  => $verificationStatus,
    //             'case_status'          => $caseStatus,
    //             'purchase_type'        => $purchaseType,
    //         ]);
    //     }

    //     if ($caseStatus == 2) {

    //         $title = $booking->buyer_type === 'Scrappage'
    //             ? 'Scrappage Completed'
    //             : 'Exchange Completed';

    //         $message = $booking->buyer_type === 'Scrappage'
    //             ? 'Scrappage process completed .'
    //             : 'Exchange process completed .';

    //         $booking->addHistory(
    //             'commented',
    //             $title,
    //             $message,
    //             [
    //                 'buyer_type'      => $booking->buyer_type,
    //                 'registration_no' => $booking->registration_no,
    //                 'expected_price'  => $booking->expected_price,
    //                 'offered_price'   => $booking->offered_price,
    //                 'exchange_bonus'  => $booking->exchange_bonus,
    //                 'remark'          => $request->remark,
    //             ],
    //             null,
    //             backpack_user()
    //         );
    //     }

    //     return redirect()->route('sales.booking.exchange')->with('success', 'Exchange purchase details updated successfully!');
    // }
    public function exchangeUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_EXCHANGE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        // ============================================================
        // 1. FIND LINKED ENQUIRY (fields like buyer_type, prices live here)
        // ============================================================
        $linkedEnquiry = null;

        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        // ============================================================
        // 2. VALIDATION (fixed required_if using Rule::requiredIf)
        // ============================================================
        $isExchange = $request->buyer_type === 'Exchange Buy';
        $isScrappage = $request->buyer_type === 'Scrappage';
        $isExchangeOrScrap = $isExchange || $isScrappage;

        $validator = Validator::make($request->all(), [
            'buyer_type' => 'required|string|in:First Time Buy,Additional Buy,Exchange Buy,Scrappage',
            'enum_master1' => 'nullable|integer',
            'vehicle_details' => 'nullable|string|max:255',
            'enum_master2' => 'nullable|integer',
            'vehicle_details2' => 'nullable|string|max:255',

            'registration_no' => [
                Rule::requiredIf($isExchangeOrScrap),
                'nullable', 'string', 'max:255',
            ],
            'manufacturing_year' => [
                Rule::requiredIf($isExchangeOrScrap),
                'nullable', 'integer', 'min:1900', 'max:'.date('Y'),
            ],
            'odometer_reading' => [
                Rule::requiredIf($isExchange),
                'nullable', 'numeric', 'min:0',
            ],
            'expected_price' => [
                Rule::requiredIf($isExchange),
                'nullable', 'numeric', 'min:0',
            ],
            'offered_price' => [
                Rule::requiredIf($isExchange),
                'nullable', 'numeric', 'min:0',
            ],
            'exchange_bonus' => [
                Rule::requiredIf($isExchange),
                'nullable', 'numeric', 'min:0',
            ],

            'update' => 'required|integer|in:1,2,3',
            'case_status' => 'required|integer|in:1,2,3',
            'remark' => 'required|string',
        ]);

        if ($validator->fails()) {
            \Log::warning('EXCHANGE UPDATE VALIDATION FAILED', [
                'booking_id' => $id,
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['_token']),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $validator->messages()->first());
        }

        // ============================================================
        // 3. PREPARE STATUS MAPS
        // ============================================================
        $verificationStatusMap = [
            1 => 'Unverified',
            2 => 'Verified (Data Match)',
            3 => 'Verified (Data Mismatch)',
        ];
        $caseStatusMap = [
            1 => 'In-Process',
            2 => 'Exchange Done',
            3 => 'Case Lost',
        ];

        $rem = [];

        // ============================================================
        // 4. UPDATE BOOKING FIELDS (only those that actually exist)
        // ============================================================
        // NOTE: For your schema, buyer_type / prices / reg_no / etc.
        // live on the ENQUIRY table. We update enquiry below.

        if ($request->has('remark')) {
            $rem[] = 'Remarks: '.$request->input('remark');
        }

        // ============================================================
        // 5. UPDATE LINKED ENQUIRY (this is where buyer_type lives!)
        // ============================================================
        if ($linkedEnquiry) {
            $enquiryChanges = [];

            if ($linkedEnquiry->purchase_type != $request->buyer_type) {
                $enquiryChanges[] = "Buyer Type: '".($linkedEnquiry->purchase_type ?? 'null')."' → '".$request->buyer_type."'";
                $linkedEnquiry->purchase_type = $request->buyer_type;
            }

            if ($linkedEnquiry->brand_make != $request->enum_master1) {
                $enquiryChanges[] = "Brand Make 1: '".($linkedEnquiry->brand_make ?? 'null')."' → '".$request->enum_master1."'";
                $linkedEnquiry->brand_make = $request->enum_master1;
            }

            if ($linkedEnquiry->brand_model != $request->vehicle_details) {
                $enquiryChanges[] = "Model Variant 1: '".($linkedEnquiry->brand_model ?? 'null')."' → '".$request->vehicle_details."'";
                $linkedEnquiry->brand_model = $request->vehicle_details;
            }

            if ($linkedEnquiry->consid_brand2 != $request->enum_master2) {
                $enquiryChanges[] = "Brand Make 2: '".($linkedEnquiry->consid_brand2 ?? 'null')."' → '".$request->enum_master2."'";
                $linkedEnquiry->consid_brand2 = $request->enum_master2;
            }

            if ($linkedEnquiry->consid_model2 != $request->vehicle_details2) {
                $enquiryChanges[] = "Model Variant 2: '".($linkedEnquiry->consid_model2 ?? 'null')."' → '".$request->vehicle_details2."'";
                $linkedEnquiry->consid_model2 = $request->vehicle_details2;
            }

            if ($linkedEnquiry->vehicle_no != $request->registration_no) {
                $enquiryChanges[] = "Registration No: '".($linkedEnquiry->vehicle_no ?? 'null')."' → '".$request->registration_no."'";
                $linkedEnquiry->vehicle_no = $request->registration_no;
            }

            if ($linkedEnquiry->make_year != $request->manufacturing_year) {
                $enquiryChanges[] = "Manufacturing Year: '".($linkedEnquiry->make_year ?? 'null')."' → '".$request->manufacturing_year."'";
                $linkedEnquiry->make_year = $request->manufacturing_year;
            }

            if ($linkedEnquiry->odo_reading != $request->odometer_reading) {
                $enquiryChanges[] = "Odometer: '".($linkedEnquiry->odo_reading ?? 'null')."' → '".$request->odometer_reading."'";
                $linkedEnquiry->odo_reading = $request->odometer_reading;
            }

            if ($linkedEnquiry->expected_price != $request->expected_price) {
                $enquiryChanges[] = "Expected Price: '".($linkedEnquiry->expected_price ?? 'null')."' → '".$request->expected_price."'";
                $linkedEnquiry->expected_price = $request->expected_price;
            }

            if ($linkedEnquiry->offered_price != $request->offered_price) {
                $enquiryChanges[] = "Offered Price: '".($linkedEnquiry->offered_price ?? 'null')."' → '".$request->offered_price."'";
                $linkedEnquiry->offered_price = $request->offered_price;
            }

            if ($linkedEnquiry->exchange_bonus != $request->exchange_bonus) {
                $enquiryChanges[] = "Exchange Bonus: '".($linkedEnquiry->exchange_bonus ?? 'null')."' → '".$request->exchange_bonus."'";
                $linkedEnquiry->exchange_bonus = $request->exchange_bonus;
            }

            if (! empty($enquiryChanges)) {
                $linkedEnquiry->save();
                $rem = array_merge($rem, $enquiryChanges);
            }
        }

        // ============================================================
        // 6. UPDATE BOOKING (only remark if needed)
        // ============================================================
        // NOTE: pending_remark was incorrectly being overwritten with
        // user remarks — that corrupted the pending system. Removed.

        $booking->save();

        // ============================================================
        // 7. UPSERT XExchange RECORD
        // ============================================================
        $verificationStatus = $request->input('update');
        $caseStatus = $request->input('case_status');

        $exchangeEntry = XExchange::where('bid', $booking->id)->first();

        $exchangePayload = [
            'vh_id' => $request->enum_master1 ?? 0,
            'enum_master1' => $request->enum_master1,
            'enum_master2' => $request->enum_master2,
            'vehicle_details' => $request->vehicle_details,
            'vehicle_details2' => $request->vehicle_details2,
            'registration_no' => $request->registration_no,
            'manufacturing_year' => $request->manufacturing_year,
            'odometer_reading' => $request->odometer_reading,
            'expected_price' => $request->expected_price,
            'offered_price' => $request->offered_price,
            'exchange_bonus' => $request->exchange_bonus,
            'verification_status' => $verificationStatus,
            'case_status' => $caseStatus,
            'purchase_type' => $request->buyer_type,
        ];

        if (! $exchangeEntry) {
            $exchangePayload['bid'] = $booking->id;
            XExchange::create($exchangePayload);
            $rem[] = 'New exchange entry created (Verification: '.($verificationStatusMap[$verificationStatus] ?? 'N/A').', Case: '.($caseStatusMap[$caseStatus] ?? 'N/A').')';
        } else {
            if ($exchangeEntry->verification_status != $verificationStatus) {
                $rem[] = "Verification Status: '".($verificationStatusMap[$exchangeEntry->verification_status] ?? 'null')."' → '".$verificationStatusMap[$verificationStatus]."'";
            }
            if ($exchangeEntry->case_status != $caseStatus) {
                $rem[] = "Case Status: '".($caseStatusMap[$exchangeEntry->case_status] ?? 'null')."' → '".$caseStatusMap[$caseStatus]."'";
            }
            $exchangeEntry->update($exchangePayload);
        }

        // ============================================================
        // 8. ALWAYS WRITE HISTORY (not just when caseStatus == 2)
        // ============================================================
        $title = 'Exchange Details Updated';
        $message = 'Exchange / Scrappage details updated.';

        if ($caseStatus == 2) {
            $title = ($request->buyer_type === 'Scrappage') ? 'Scrappage Completed' : 'Exchange Completed';
            $message = ($request->buyer_type === 'Scrappage') ? 'Scrappage process completed.' : 'Exchange process completed.';
        } elseif ($caseStatus == 3) {
            $title = 'Exchange Case Lost';
            $message = 'Exchange case marked as lost.';
        }

        if (! empty(trim($request->remark ?? ''))) {
            $message .= ' Remarks: '.trim($request->remark);
        }

        $booking->addHistory(
            'commented',
            $title,
            $message,
            [
                'changes' => $rem,
                'buyer_type' => $request->buyer_type,
                'case_status' => $caseStatus,
            ],
            null,
            backpack_user()
        );

        \Log::info('EXCHANGE UPDATE SUCCESS', [
            'booking_id' => $id,
            'changes' => $rem,
            'case_status' => $caseStatus,
            'verify_status' => $verificationStatus,
            'buyer_type' => $request->buyer_type,
        ]);

        // ============================================================
        // REDIRECT TO THE CORRECT LISTING BASED ON BUYER TYPE
        // ============================================================
        if ($request->buyer_type === 'Scrappage') {
            return redirect()
                ->route('sales.booking.scrappage')
                ->with('success', 'Scrappage details updated successfully!');
        }

        if ($request->buyer_type === 'Exchange Buy') {
            return redirect()
                ->route('sales.booking.exchange')
                ->with('success', 'Exchange purchase details updated successfully!');
        }

        // For any other buyer type, go back to the main booking list
        return redirect()
            ->route('sales.booking.index')
            ->with('success', 'Purchase type details updated successfully!');
    }

    public function finEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $linkedEnquiry = null;

        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        if ($linkedEnquiry) {
            $booking->name = $booking->name ?? $linkedEnquiry->name;

            $booking->model_code =
                $booking->model_code ?? $linkedEnquiry->model_code;

            $booking->variant_code =
                $booking->variant_code ?? $linkedEnquiry->variant_code;

            $booking->color_code =
                $booking->color_code ?? $linkedEnquiry->color_code;
        }

        $user = backpack_user();
        $uid = $user?->id ?? null;

        $data = [];

        $data['branch'] = Branch::where('branch_code', $booking->branch_code)
            ->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code > 0
            ? (Location::find($booking->location_code)?->name ?? 'N/A')
            : ($booking->location_other ?? 'N/A');

        $acc = explode(',', $booking->accessories ?? '');
        $accessoryNames = [];
        foreach ($acc as $a) {
            if ($a = trim($a)) {
                $accessory = Xessories::find($a);
                if ($accessory) {
                    $accessoryNames[] = $accessory->item;
                }
            }
        }
        $data['accessories'] = $accessoryNames ? implode(', ', $accessoryNames) : 'N/A';

        $chassis = Stock::find($booking->chassis_no);
        $data['bchasis'] = $chassis?->chassis_no ?? 'N/A';

        $data['segments'] = OrgService::segments();

        $data['saleconsultants'] = OrgService::salesConsultants();
        $data['financiers'] = XlFinancier::select('id', 'name', 'short_name')->get()->toArray();
        $data['enum_master'] = OrgService::keywordValueByCode('EXISTING_CAR_OEM');

        $drec = XL_DSA_MASTER::find($booking->dsa_id);
        $dsaname = $drec ? $drec->name.' - '.$drec->mobile : 'N/A';

        $collector = User::find($booking->col_by);
        $data['collector_name'] = $collector
            ? $collector->name.' - '.$collector->emp_code
            : 'N/A';

        $data['remark'] = 0;
        $depts = explode(',', $user->department ?? '');

        foreach ($depts as $deptId) {

            $dept = OrgService::getKeyValueById((int) trim($deptId));

            $deptName = $dept?->value;

            if ($deptName === 'SALES') {
                $data['remark'] = 1;
            }

            if ($deptName === 'ACCOUNTS') {
                $data['remark'] = 2;
            }
        }

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';

        $data['oem_ids'] = explode(',', $booking->exist_oem ?? '');

        $finance = XFinance::where('bid', $id)->first();

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

        return view('admin.booking.finance-edit', compact(
            'booking',
            'finance',
            'data',
            'dsaname',
            'uid',
            'user',
            'bookingHistory'
        ));
    }

    public function finUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $rules = [
            'fin_mode' => 'required',
            'loan_status' => 'nullable',
            'case_status' => 'nullable',
            'instrument_type' => 'nullable',
            'instrument_ref_no' => 'nullable',
            'loan_amount' => 'nullable',
            'margin_money' => 'nullable',
            'file_charge' => 'nullable',
            'financier_subvention' => 'nullable|numeric|min:0',
            'remark' => 'required',
            'verification_status' => 'required',
            'case_lost_reason' => 'nullable',
            'instrument_proof' => 'nullable|file',
            'retail' => 'nullable|in:1',
            'bid' => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', $validator->messages()->first());
        }

        $booking = Booking::findOrFail($id);

        $finance = XFinance::firstOrNew(['bid' => $id]);
        $isNew = ! $finance->exists;

        $old = $finance->toArray();
        $changes = [];

        $labels = [
            'fin_mode' => 'Finance Mode',
            'loan_status' => 'Loan Status',
            'case_status' => 'Case Status',
            'instrument_type' => 'Instrument Type',
            'instrument_ref_no' => 'Reference No.',
            'loan_amount' => 'Loan Amount',
            'margin' => 'Margin Money',
            'file_charge' => 'File Charge',
            'case_lost_reason' => 'Case Lost Reason',
            'verification_status' => 'Verification Status',
            'remark' => 'Remarks',
        ];

        $instrumentTypes = [
            1 => 'Financier Payment',
            2 => 'Delivery Order',
            3 => 'Sanction Letter',
            4 => 'Mail Communication',
            5 => 'Whatsapp Communication',
            6 => 'Banker Cheque',
            7 => 'Demand Graph',
            8 => 'Customer Cheque',
        ];

        $caseLostReasons = [
            1 => 'Cash Purchase',
            2 => 'Customer Self Finance',
        ];

        $verifyLabels = [
            1 => 'Not Selected',
            2 => 'Verified (Match)',
            3 => 'Verified (Mismatch)',
            4 => 'Plan Cancelled',
        ];

        $format = function ($val, $field) use ($instrumentTypes, $caseLostReasons, $verifyLabels) {
            if (is_null($val)) {
                return 'N/A';
            }
            if ($field === 'instrument_type') {
                return $instrumentTypes[$val] ?? $val;
            }
            if ($field === 'case_lost_reason') {
                return $caseLostReasons[$val] ?? 'Unknown';
            }
            if ($field === 'verification_status') {
                return $verifyLabels[$val] ?? $val;
            }
            if (in_array($field, ['loan_amount', 'margin', 'file_charge'])) {
                return 'Rs. '.number_format($val);
            }

            return $val;
        };

        $fields = [
            'fin_mode',
            'loan_status',
            'case_status',
            'instrument_type',
            'instrument_ref_no',
            'loan_amount',
            'margin',
            'file_charge',
            'case_lost_reason',
            'verification_status',
        ];

        foreach ($fields as $field) {
            $oldVal = $old[$field] ?? null;
            $inputKey = $field === 'margin' ? 'margin_money' : $field;
            $newVal = $request->input($inputKey);
            $oldVal = $oldVal === '' ? null : $oldVal;
            $newVal = $newVal === '' ? null : $newVal;

            if ($oldVal != $newVal) {
                $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                $changes[] = "$label: '{$format($oldVal, $field)}' to '{$format($newVal, $field)}'";
            }
        }

        if ($isNew) {
            $changes[] = "New Finance Record Created for Booking ID {$id}";
        }

        if ($request->hasFile('instrument_proof')) {
            $finance->clearMediaCollection('instrument_proof');

            $finance->addMediaFromRequest('instrument_proof')
                ->usingFileName('instrument_proof_'.$id.'_'.time().'.'.$request->file('instrument_proof')->extension())
                ->toMediaCollection('instrument_proof');

            $changes[] = 'Instrument Proof: New file uploaded (replaced previous if any)';
        }

        if ($request->has('delete_instrument_proof') && $request->delete_instrument_proof == '1') {
            $finance->clearMediaCollection('instrument_proof');
            $changes[] = 'Instrument Proof: File removed';
        }

        $finance->fin_mode = $request->fin_mode;
        $finance->loan_status = $request->loan_status;
        $finance->financier = $request->financier;
        $finance->verification_status = $request->verification_status;
        $finance->case_status = $request->case_status;

        if (in_array($request->fin_mode, ['Cash', 'Customer Self'])) {

            $finance->instrument_type = null;
            $finance->instrument_ref_no = null;
            $finance->loan_amount = null;
            $finance->margin = null;
            $finance->file_charge = null;
            $finance->case_lost_reason = $request->fin_mode === 'Cash' ? 1 : 2;
        } else {

            $finance->instrument_type = $request->instrument_type;
            $finance->instrument_ref_no = $request->instrument_ref_no;
            $finance->loan_amount = $request->loan_amount;
            $finance->margin = $request->margin_money;
            $finance->file_charge = $request->file_charge;
            $finance->subvention_amount = $request->financier_subvention;
            $finance->case_lost_reason = $request->case_lost_reason;
        }

        if ($isNew && $request->retail == 1) {
            $finance->verification_status = 2;
            $finance->case_status = 2;

            if (trim($request->remark ?? '') === '') {
                $request->merge(['remark' => 'Retail booking finance auto-completed']);
            }
        }

        if ($isNew) {
            $finance->bid = $id;
            $finance->created_by = backpack_auth()->id();

            if (empty($finance->verification_status)) {
                $finance->verification_status = 2;
            }
        }

        $finance->updated_by = backpack_auth()->id();
        $finance->status = ($finance->fin_mode === 'In-house' && $finance->case_status == 2) ? 2 : 1;
        $finance->vh_id = $booking->vh_id
            ?? $booking->vehicle_oem_code
            ?? $booking->variant_code
            ?? 1;

        $finance->save();

        if (
            ($finance->fin_mode === 'In-house' && $finance->case_status == 2)
            ||
            in_array($finance->fin_mode, [
                'Customer Self',
                'Cash',
                'Yet To Decide',
                'Purchase Plan Cancelled',
            ])
        ) {

            $title = in_array($finance->fin_mode, [
                'Customer Self',
                'Cash',
                'Yet To Decide',
            ])
                ? 'Finance Not Interested Process Completed'
                : 'Finance Process Completed';

            $message = in_array($finance->fin_mode, [
                'Customer Self',
                'Cash',
                'Yet To Decide',
            ])
                ? 'Finance not interested case processed .'
                : 'Finance process completed .';

            if (! empty(trim($request->remark))) {
                $message .= "\n\n,Remarks: ".$request->remark;
            }

            $booking->addHistory(
                'commented',
                $title,
                $message,
                [
                    'finance_mode' => $finance->fin_mode,
                    'financier' => $finance->financier,
                    'loan_amount' => $finance->loan_amount,
                ],
                null,
                backpack_user()
            );
        }

        if ($request->retail == 1) {
            $booking->retail = 1;
            $booking->save();
            $changes[] = 'Booking Retailed';
        }

        if ($request->retail == 1) {

            $booking->retail = 1;
            $booking->save();

            $changes[] = 'Booking Retailed';

            $booking->addHistory(
                'commented',
                'Retail Process Completed',
                'Finance retail process completed .',
                [
                    'finance_mode' => $finance->fin_mode,
                    'remark' => $request->remark,
                ],
                null,
                backpack_user()
            );
        }

        if ($request->payout == 1) {
            $booking->payout = 1;
            $booking->save();
        }

        $successMessage = 'Finance details updated successfully!';

        if ($request->query('from') === 'payout' || $request->input('from') === 'payout') {
            return redirect()
                ->route('sales.booking.finance.payout-edit', $id)
                ->with('success', $successMessage);
        }

        if ($request->filled('retail') && $request->retail == 1) {
            return redirect()
                ->route('sales.booking.finance.retail')
                ->with('success', $successMessage);
        }

        return redirect()
            ->route('sales.booking.finance')
            ->with('success', $successMessage);
    }

    public function RetailEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $user = backpack_user();
        $uid = $user?->id ?? null;

        $data = [];

        $data['branch'] = Branch::where('branch_code', $booking->branch_code)
            ->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code > 0
            ? (Location::find($booking->location_code)?->name ?? 'N/A')
            : ($booking->location_other ?? 'N/A');

        $acc = explode(',', $booking->accessories ?? '');
        $accessoryNames = [];
        foreach ($acc as $a) {
            if ($a = trim($a)) {
                $accessory = Xessories::find($a);
                if ($accessory) {
                    $accessoryNames[] = $accessory->item;
                }
            }
        }
        $data['accessories'] = $accessoryNames ? implode(', ', $accessoryNames) : 'N/A';

        $chassis = Stock::find($booking->chassis_no);
        $data['bchasis'] = $chassis?->chassis_no ?? 'N/A';

        $data['segments'] = OrgService::segments();
        $data['saleconsultants'] = OrgService::salesConsultants();
        $data['financiers'] = XlFinancier::select('id', 'name', 'short_name')->get()->toArray();
        $data['enum_master'] = OrgService::keywordValueByCode('EXISTING_CAR_OEM');

        $drec = XL_DSA_MASTER::find($booking->dsa_id);
        $dsaname = $drec ? $drec->name.' - '.$drec->mobile : 'N/A';

        $collector = User::find($booking->col_by);
        $data['collector_name'] = $collector
            ? $collector->name.' - '.$collector->emp_code
            : 'N/A';

        $data['remark'] = 0;

        $departments = OrgService::departments();

        $depts = explode(',', $user->department ?? '');

        foreach ($depts as $deptId) {

            $deptId = trim($deptId);

            $dept = collect($departments)->firstWhere('code', $deptId);

            $deptName = strtoupper($dept['name'] ?? '');

            if ($deptName == 'SALES') {
                $data['remark'] = 1;
            }

            if ($deptName == 'ACCOUNTS') {
                $data['remark'] = 2;
            }
        }

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';

        $data['oem_ids'] = explode(',', $booking->exist_oem ?? '');

        $enquiry = null;
        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }
        if ($enquiry) {
            $booking->name = $enquiry->name;
            $booking->model_code = $enquiry->model_code;
            $booking->variant_code = $enquiry->variant_code;
            $booking->color_code = $enquiry->color_code;
            $booking->mobile = $enquiry->mobile;
            $booking->branch_code = $enquiry->dealer_branch;
            $booking->location_code = $enquiry->dealer_location;
        }

        $finance = XFinance::where('bid', $id)->first();

        if ($finance) {
            $booking->fin_mode = $finance->fin_mode;
            $booking->financier = $finance->financier;
            $booking->loan_status = $finance->loan_status;
        }

        return view('admin.booking.retail-edit', compact(
            'booking',
            'finance',
            'data',
            'dsaname',
            'uid',
            'user'
        ));
    }

    public function PayoutEdit($id)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);
        /*
        |--------------------------------------------------------------------------
        | Merge Enquiry Customer Details into Booking
        |--------------------------------------------------------------------------
        */
        if ($booking->enq_no) {

            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);

            if ($enquiry) {

                $booking->name = $enquiry->name;
                $booking->model_code = $enquiry->model_code;
                $booking->variant_code = $enquiry->variant_code;

                $booking->pincode = $enquiry->zipcode;
                $booking->vpo = $enquiry->vpo;
                $booking->tehsil = $enquiry->tehsil;
                $booking->district = $enquiry->district;
                $booking->city = $enquiry->city;
                $booking->territory = $enquiry->territory;
            }
        }
        $comm = [];
        $user = backpack_user();
        $uid = $user?->id ?? null;

        $data = [];

        $data['branch'] = Branch::where('branch_code', $booking->branch_code)
            ->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code > 0
            ? (Location::find($booking->location_code)?->name ?? 'N/A')
            : ($booking->location_other ?? 'N/A');

        $acc = explode(',', $booking->accessories ?? '');
        $accessoryNames = [];
        foreach ($acc as $a) {
            if ($a = trim($a)) {
                $accessory = Xessories::find($a);
                if ($accessory) {
                    $accessoryNames[] = $accessory->item;
                }
            }
        }
        $data['accessories'] = $accessoryNames ? implode(', ', $accessoryNames) : 'N/A';

        $chassis = Stock::find($booking->chassis_no);
        $data['bchasis'] = $chassis?->chassis_no ?? 'N/A';

        $data['segments'] = CommonHelper::getVehicleSegments();
        $data['saleconsultants'] = OrgService::usersByDesignation('CNS') ?? [];
        $data['financiers'] = XlFinancier::select('id', 'name', 'short_name')->get()->toArray();
        $data['enum_master'] = OrgService::keywordValueByCode('EXISTING_CAR_OEM');

        $drec = XL_DSA_MASTER::find($booking->dsa_id);
        $dsaname = $drec ? $drec->name.' - '.$drec->mobile : 'N/A';

        $collector = User::find($booking->col_by);
        $data['collector_name'] = $collector
            ? $collector->name.' - '.$collector->emp_code
            : 'N/A';

        $data['remark'] = 0;

        $departments = OrgService::departments();

        $depts = explode(',', $user->department ?? '');

        foreach ($depts as $deptId) {

            $deptId = trim($deptId);

            $dept = collect($departments)->firstWhere('code', $deptId);

            $deptName = strtoupper($dept['name'] ?? '');

            if ($deptName == 'SALES') {
                $data['remark'] = 1;
            }

            if ($deptName == 'ACCOUNTS') {
                $data['remark'] = 2;
            }
        }

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';

        $data['oem_ids'] = explode(',', $booking->exist_oem ?? '');

        $finance = XFinance::where('bid', $id)->first();

        $bookingHistory = $booking->commMaster()
            ->with([
                'rootThreads' => function ($q) {
                    $q->with([
                        'children.actor',
                        'children.action',
                        'actor',
                        'action',
                        'media',
                    ]);
                },
            ])
            ->first()?->rootThreads ?? collect();

        return view('admin.booking.payout-edit', compact(
            'booking',
            'finance',
            'data',
            'dsaname',
            'uid',
            'user',
            'bookingHistory'
        ));
    }

    public function PayoutUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $finance = XFinance::where('bid', $id)->firstOrFail();
        $booking = Booking::findOrFail($id);

        $old = $finance->toArray();
        $changes = [];

        $rules = [];
        $payout_category = $request->payout_category;

        if ($payout_category == 1) {
            $rules = [
                'loan_amount' => 'required|numeric|min:0',
                'do_number' => 'nullable|string|max:50',
                'expected_payout_pct' => 'required|numeric|min:0',
                'gst_included' => 'required|in:0,0.5,1',
                'inv1_no' => 'required|string|max:50',
                'inv1_name' => 'required|string|max:100',
                'inv1_prov_gst' => 'required|numeric|min:0',
                'inv2_no' => 'nullable|string|max:50',
                'inv2_name' => 'nullable|string|max:100',
                'inv2_prov_gst' => 'nullable|numeric|min:0',
                'consideration_no_gst' => 'required|numeric|min:0',
                'difference_no_gst' => 'required|numeric',
                'payout_remarks' => 'required|string',
            ];
        } else {
            $rules = [
                'payout_category' => 'required|in:2,4',
                'payout_remarks' => 'required|string',
            ];
            if ($payout_category == 2) {
                $rules['no_payout_reason'] = 'required|in:1,2,3,4,5,6';
            }
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', $validator->messages()->first());
        }

        $payoutFields = [
            'instrument_ref_no',
            'loan_amount',
            'expected_payout_pct',
            'gst_included',
            'inv1_no',
            'inv1_name',
            'inv1_prov_gst',
            'inv2_no',
            'inv2_name',
            'inv2_prov_gst',
            'consideration_no_gst',
            'difference',
        ];

        if ($payout_category != 1) {
            foreach ($payoutFields as $field) {
                $finance->$field = null;
            }
            if ($payout_category == 4) {
                $finance->nopayout_reason = null;
            }
        }

        $finance->payout_category = $payout_category;

        if ($payout_category == 1) {
            $finance->loan_amount = $request->loan_amount;
            $finance->instrument_ref_no = $request->do_number;
            $finance->expected_payout_pct = $request->expected_payout_pct;
            $finance->gst_included = $request->gst_included;
            $finance->inv1_no = $request->inv1_no;
            $finance->inv1_name = $request->inv1_name;
            $finance->inv1_prov_gst = $request->inv1_prov_gst;
            $finance->inv2_no = $request->inv2_no;
            $finance->inv2_name = $request->inv2_name;
            $finance->inv2_prov_gst = $request->inv2_prov_gst;
            $finance->consideration_no_gst = $request->consideration_no_gst;
            $finance->difference = $request->difference_no_gst;

            $booking->payout = 2;
            $booking->save();
        } else {
            $finance->nopayout_reason = $request->no_payout_reason;
        }

        $finance->updated_by = backpack_auth()->id();

        if (in_array($finance->fin_mode, ['In-house', 'Customer_self']) && $finance->case_status == 2) {
            $finance->status = ($payout_category == 1) ? 3 : 2;
        }

        $finance->save();

        $booking->addHistory(
            'commented',
            'Payout Completed',
            'Finance payout process completed .',
            [
                'payout_category' => $request->payout_category,
                'loan_amount' => $request->loan_amount,
                'do_number' => $request->do_number,
                'expected_payout' => $request->expected_payout_pct,
                'difference' => $request->difference_no_gst,
                'remarks' => $request->payout_remarks,
            ],
            null,
            backpack_user()
        );

        $fieldsToLog = [
            'payout_category',
            'nopayout_reason',
            'loan_amount',
            'instrument_ref_no',
            'expected_payout_pct',
            'gst_included',
            'inv1_no',
            'inv1_name',
            'inv1_prov_gst',
            'inv2_no',
            'inv2_name',
            'inv2_prov_gst',
            'consideration_no_gst',
            'difference',
        ];

        $labels = [
            'payout_category' => 'Payout Category',
            'nopayout_reason' => 'No Payout Reason',
            'loan_amount' => 'Loan Amount',
            'instrument_ref_no' => 'DO Number',
            'expected_payout_pct' => 'Expected Payout %',
            'gst_included' => 'GST Included',
            'inv1_no' => '1st Invoice No.',
            'inv1_name' => '1st Invoice Name',
            'inv1_prov_gst' => '1st Provisioning (GST)',
            'inv2_no' => '2nd Invoice No.',
            'inv2_name' => '2nd Invoice Name',
            'inv2_prov_gst' => '2nd Provisioning (GST)',
            'consideration_no_gst' => 'Consideration (w/o GST)',
            'difference' => 'Difference (w/o GST)',
            'payout_remarks' => 'Payout Remarks',
        ];

        $payoutCats = [1 => 'Payout', 2 => 'No Payout', 4 => 'Cash'];
        $noPayoutReasons = [
            1 => 'Low Interest Rate',
            2 => 'Low Tenure Funding',
            3 => 'Nil Payout Model',
            4 => 'Out Of Territory',
            5 => 'Financier Sourcing',
            6 => 'Other',
        ];
        $gstOpts = [0 => '0%', 0.5 => '50%', 1 => '100%'];

        $formatValue = function ($value, $field) use ($payoutCats, $noPayoutReasons, $gstOpts) {
            if (is_null($value)) {
                return 'N/A';
            }

            return match ($field) {
                'payout_category' => $payoutCats[$value] ?? $value,
                'no_payout_reason' => $noPayoutReasons[$value] ?? $value,
                'gst_included' => $gstOpts[$value] ?? $value,
                'expected_payout_pct' => number_format($value, 4).'%',
                'loan_amount_payout',
                'inv1_prov_gst',
                'inv2_prov_gst',
                'consideration_no_gst',
                'difference_no_gst' => '₹'.number_format($value, 2),
                default => $value,
            };
        };

        foreach ($fieldsToLog as $field) {
            $oldVal = $old[$field] ?? null;
            $newVal = $finance->$field;

            if ($oldVal != $newVal) {
                $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                $changes[] = "$label: '{$formatValue($oldVal, $field)}' to '{$formatValue($newVal, $field)}'";
            }
        }

        $logMessage = $request->payout_remarks ?: 'Payout updated';
        if (! empty($changes)) {
            $logMessage .= "\n\nChanges:\n".implode("\n", $changes);
        }

        return redirect()
            ->route('sales.booking.finance.payout')
            ->with('success', 'Payout details saved successfully!');
    }

    public function financeView($id)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | Get Customer Details from Enquiry
        |--------------------------------------------------------------------------
        */
        $enquiry = null;

        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        /*
        |--------------------------------------------------------------------------
        | Merge Enquiry Customer Details into Booking
        |--------------------------------------------------------------------------
        */
        if ($enquiry) {

            $booking->name = $enquiry->name;
            $booking->model_code = $enquiry->model_code;
            $booking->variant_code = $enquiry->variant_code;

            $booking->pincode = $enquiry->zipcode;
            $booking->vpo = $enquiry->vpo;
            $booking->tehsil = $enquiry->tehsil;
            $booking->district = $enquiry->district;
            $booking->city = $enquiry->city;
        }

        /*
        |--------------------------------------------------------------------------
        | Get Finance Details
        |--------------------------------------------------------------------------
        */
        $finance = XFinance::where('bid', $id)->first();

        /*
        |--------------------------------------------------------------------------
        | Get Financiers
        |--------------------------------------------------------------------------
        */
        $data['financiers'] = XlFinancier::select(
            'id',
            'name',
            'short_name'
        )->get()->toArray();

        return view(
            'admin.booking.finance-view',
            compact('booking', 'finance', 'data')
        );
    }

    public function refundRequested(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Refunds';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 4);

        $status_filter = $request->input('status_filter', '');
        if ($status_filter !== '' && $status_filter !== 'all') {
            $query->where('bookings.status', $status_filter);
        }

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->refund_request_date = $t->refund_request_date
                ? Carbon::parse($t->refund_request_date)->format('d-M-Y')
                : 'N/A';

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.backpack_url("sales/booking/{$t->id}/refund-view").'"
                   class="btn btn-primary btn-sm">
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No pending refund requests found.');
        }

        return view('admin.booking.pending-refund', $this->data);
    }

    public function refundView($id)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $refund = Xl_Refunds::where('entity_type', 'booking')
            ->where('entity_id', $id)
            ->latest('id')
            ->first();

        $data = [
            'booking' => $booking,
            'refund' => null,
            'amount' => $booking->booking_amount ?? 0,
            'deduction' => 0,
            'acc_proof' => '',
            'aadhar' => '',
            'pan' => '',
            'pay_proof' => '',
            'receiptLogs' => Bookingamount::where('bid', $id)
                ->select('id', 'date', 'type_number', 'mode', 'amount')
                ->orderBy('date', 'desc')
                ->get(),
        ];

        if ($refund) {
            $data['deduction'] = ($booking->booking_amount ?? 0) - ($refund->amount ?? 0);

            $data['acc_proof'] = $refund->getFirstMediaUrl('acc-proof')
                ?: $refund->getFirstMediaUrl('acc_proof')
                ?: '';

            $data['aadhar'] = $refund->getFirstMediaUrl('aadhar')
                ?: $refund->getFirstMediaUrl('aadhaar')
                ?: '';

            $data['pan'] = $refund->getFirstMediaUrl('pan') ?: '';

            $data['pay_proof'] = $refund->getFirstMediaUrl('pay-proof')
                ?: $refund->getFirstMediaUrl('pay_proof')
                ?: '';

            $data['refund'] = [
                'remaining_amount' => $refund->amount ?? 0,
                'bank_name' => $refund->bank_name ?? 'N/A',
                'branch_name' => $refund->branch_name ?? 'N/A',
                'account_type' => $refund->account_type ?? 'N/A',
                'account_number' => $refund->account_number ?? 'N/A',
                'holder_name' => $refund->holder_name ?? 'N/A',
                'ifsc_code' => $refund->ifsc_code ?? 'N/A',
                'details' => $refund->details ?? 'N/A',
                'req_date' => $refund->req_date ? Carbon::parse($refund->req_date)->format('d-M-Y') : 'N/A',
                'ref_date' => $refund->ref_date ? Carbon::parse($refund->ref_date)->format('d-M-Y') : 'N/A',
                'mode' => $refund->mode ?? 'N/A',
                'transaction_details' => $refund->transaction_details ?? 'N/A',
                'remark' => $refund->remark ?? 'N/A',
            ];
        }
        $bookingHistory = $booking->commMaster()
            ->with([
                'rootThreads' => function ($q) {
                    $q->with([
                        'children.actor',
                        'children.action',
                        'actor',
                        'action',
                        'media',
                    ]);
                },
            ])
            ->first()?->rootThreads ?? collect();

        return view('admin.booking.show', compact('booking', 'data', 'bookingHistory'));
    }

    public function rejected(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Refund Rejected Bookings';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 7);

        $status_filter = $request->input('status_filter', '');
        if ($status_filter !== '' && $status_filter !== 'all') {
            $query->where('bookings.status', $status_filter);
        }

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->refund_request_date = $t->refund_request_date
                ? Carbon::parse($t->refund_request_date)->format('d-M-Y')
                : 'N/A';

            $row->refund_rejection_date = $t->refund_rejection_date
                ? Carbon::parse($t->refund_rejection_date)->format('d-M-Y')
                : 'N/A';

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.rejected-view', $t->id).'"
                   class="btn btn-primary btn-sm"
                   >
                    <i class="fas fa-eye"></i> Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.rejected', $this->data);
    }

    public function rejectedView($id)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        if (! in_array($booking->status, [4, 5, 7])) {
            abort(404, 'This booking is not in refund flow.');
        }

        $view = $this->getFullBookingData($id, 'show');

        $data = $view->getData()['data'] ?? [];

        $refund = Xl_Refunds::where('entity_type', 'booking')
            ->where('entity_id', $id)
            ->latest('id')
            ->first();

        if ($refund) {

            $data['acc_proof'] =
                optional($refund->getFirstMedia('acc-proof'))->getUrl()
                ?: optional($refund->getFirstMedia('acc_proof'))->getUrl()
                ?: '';

            $data['aadhar'] =
                optional($refund->getFirstMedia('aadhar'))->getUrl()
                ?: optional($refund->getFirstMedia('aadhaar'))->getUrl()
                ?: '';

            $data['pan'] =
                optional($refund->getFirstMedia('pan'))->getUrl()
                ?: '';

            $data['pay_proof'] =
                optional($refund->getFirstMedia('pay-proof'))->getUrl()
                ?: optional($refund->getFirstMedia('pay_proof'))->getUrl()
                ?: '';

            $data['amount'] = $booking->booking_amount ?? 0;

            $data['deduction'] =
                ($booking->booking_amount ?? 0)
                - ($refund->amount ?? 0);

            $data['refund'] = [
                'remaining_amount' => $refund->amount ?? 0,

                'bank_name' => $refund->bank_name ?? 'N/A',

                'branch_name' => $refund->branch_name ?? 'N/A',

                'account_type' => $refund->account_type ?? 'N/A',

                'account_number' => $refund->account_number ?? 'N/A',

                'holder_name' => $refund->holder_name ?? 'N/A',

                'ifsc_code' => $refund->ifsc_code ?? 'N/A',

                'details' => $refund->details ?? 'N/A',

                'req_date' => $refund->req_date
                    ? Carbon::parse($refund->req_date)->format('d-M-Y')
                    : 'N/A',

                'ref_date' => $refund->ref_date
                    ? Carbon::parse($refund->ref_date)->format('d-M-Y')
                    : 'N/A',

                'mode' => $refund->mode ?? 'N/A',

                'transaction_details' => $refund->transaction_details ?? 'N/A',

                'remark' => $refund->remark ?? 'N/A',
            ];
        }

        $receiptLogs = Bookingamount::where('bid', $booking->id)
            ->orderBy('date', 'desc')
            ->get();

        $data['receiptLogs'] = $receiptLogs;

        return view('admin.booking.show', [
            'booking' => $booking,
            'entry' => $booking,
            'data' => $data,
            'receiptLogs' => $receiptLogs,
        ]);
    }

    public function refundUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'ref_date' => 'required|date',
            'mode' => 'required|string',
            'transaction_details' => 'required|string',
            'remark' => 'required|string',
            'pay_proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the errors below.');
        }

        $refund = Xl_Refunds::where('entity_type', 'booking')
            ->where('entity_id', $id)
            ->first();

        if (! $refund) {
            return redirect()->back()->with('error', 'Refund record not found for this booking.');
        }

        $refund->update([
            'ref_date' => $request->hidden_ref ?? $request->ref_date,
            'ref_by' => backpack_auth()->id(),
            'mode' => $request->mode,
            'transaction_details' => $request->transaction_details,
            'remark' => $request->remark,
        ]);

        if ($request->hasFile('pay_proof') && $request->file('pay_proof')->isValid()) {
            $refund->clearMediaCollection('pay-proof');
            $refund->addMedia($request->file('pay_proof'))
                ->toMediaCollection('pay-proof');
        }

        $oldStatus = $booking->status;
        $newStatus = 5;

        $statusNames = [
            1 => 'Live',
            2 => 'Invoiced',
            3 => 'Cancelled',
            4 => 'Refund Queued',
            5 => 'Refunded',
            6 => 'On Hold',
            7 => 'Refund Rejected',
            8 => 'Pending',
        ];

        $oldName = $statusNames[$oldStatus] ?? 'Unknown';
        $newName = $statusNames[$newStatus] ?? 'Unknown';

        $statusRemark = ($oldStatus != $newStatus)
            ? "Booking status changed from {$oldName} to {$newName}"
            : null;

        $adminRemark = trim($request->remark) ?: 'Refund processed';

        $booking->update([
            'status' => $newStatus,
            'refund_date' => now()->format('Y-m-d'),
        ]);
        $booking->addHistory(
            'commented',
            'Refund Completed',
            'Refund processed successfully.',
            [
                'old_status' => $oldName,
                'new_status' => $newName,
                'refund_date' => $request->hidden_ref ?? $request->ref_date,
                'mode' => $request->mode,
                'transaction_details' => $request->transaction_details,
                'remark' => $request->remark,
            ],
            null,
            backpack_user()
        );

        return redirect()->route('sales.booking.refund.requested')
            ->with('success', 'Refund details updated successfully and booking marked as Refunded.');
    }

    public function refunded(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Refunded Bookings';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 5);

        $status_filter = $request->input('status_filter', '');
        if ($status_filter !== '' && $status_filter !== 'all') {
            $query->where('bookings.status', $status_filter);
        }

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {
            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            $row->refund_date = $t->refund_date
                ? Carbon::parse($t->refund_date)->format('d-M-Y')
                : 'N/A';

            $row->refund_request_date = $t->refund_request_date
                ? Carbon::parse($t->refund_request_date)->format('d-M-Y')
                : 'N/A';

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.route('sales.booking.show', $t->id).'"
                   class="btn btn-primary btn-sm"
                   >
                    Process
                </a>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');
        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No refunded bookings found.');
        }

        return view('admin.booking.refunded', $this->data);
    }

    public function refundedUpdate(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_REFUND')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        if ($booking->status != 5) {
            return redirect()->back()->with('error', 'This booking is not in Refunded status.');
        }

        $validator = Validator::make($request->all(), [
            'ref_date' => 'required|date',
            'mode' => 'required|in:Cash,Online,Cheque',
            'transaction_details' => 'nullable|string|max:255',
            'remark' => 'nullable|string|max:1000',
            'pay_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'booking_id' => 'required|integer|exists:xlr8_booking_master,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', $validator->messages()->first());
        }

        $refund = Xl_Refunds::where('entity_type', 'booking')
            ->where('entity_id', $id)
            ->first();

        if (! $refund) {
            return redirect()->back()->with('error', 'Refund record not found for this booking.');
        }

        $changes = [];

        $newRefDate = $request->hidden_ref ?? $request->ref_date;
        if ($refund->ref_date != $newRefDate) {
            $changes[] = 'Refund Date changed from '.
                ($refund->ref_date ? Carbon::parse($refund->ref_date)->format('d-M-Y') : 'N/A').
                ' to '.Carbon::parse($newRefDate)->format('d-M-Y');
            $refund->ref_date = $newRefDate;
        }

        if ($refund->mode != $request->mode) {
            $changes[] = "Mode of Payment changed from {$refund->mode} to {$request->mode}";
            $refund->mode = $request->mode;
        }

        if ($refund->transaction_details != $request->transaction_details) {
            $changes[] = 'Transaction Details updated';
            $refund->transaction_details = $request->transaction_details;
        }

        if ($refund->remark != $request->remark) {
            $changes[] = 'Remarks updated';
            $refund->remark = $request->remark;
        }

        if ($request->hasFile('pay_proof') && $request->file('pay_proof')->isValid()) {
            $refund->clearMediaCollection('pay-proof');

            $refund->addMedia($request->file('pay_proof'))
                ->toMediaCollection('pay-proof');

            $changes[] = 'Payment Proof updated';
        }

        $refund->ref_by = backpack_auth()->id();

        $refund->save();
        $booking->addHistory(
            'commented',
            'Refund Details Updated',
            'Refund details modified .',
            [
                'changes' => $changes,
            ],
            null,
            backpack_user()
        );

        return redirect()->route('sales.booking.refunded')
            ->with('success', 'Refund details updated successfully!');
    }

    public function erroneousBookings(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Erroneous Booking Entries';

        $query = $this->getBaseQuery()
            ->withoutGlobalScopes()
            ->withoutGlobalScope(SoftDeletingScope::class);

        $query->where('bookings.status', 1);

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use ($paginatedBookings, $gridLookups) {

            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no =
                ($paginatedBookings->currentPage() - 1)
                * $paginatedBookings->perPage()
                + $index + 1;

            $row->action = '
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-sm btn-primary" disabled>
                    Process
                </button>
            </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        $hasAction = collect($columns)->contains('field', 'action');

        if (! $hasAction) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.erroneousBookings', $this->data);
    }

    public function erroneousFinance(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_FINANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Erroneous Finance Entries';

        $query = $this->getBaseQuery();

        $query->leftJoin('xlr8_booking_finance as xf', 'bookings.id', '=', 'xf.bid');

        $query->where('xf.status', 1);

        $query->orderBy('bookings.id', 'DESC');

        $paginatedBookings = $query->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {

            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no =
                ($paginatedBookings->currentPage() - 1)
                * $paginatedBookings->perPage()
                + $index + 1;

            $row->finance_status =
                $t->finance_status == 1
                ? 'Pending'
                : ($t->finance_status == 2
                    ? 'Complete'
                    : 'N/A');

            $location = $t->location_code && $t->location_code > 0
                ? (Location::find($t->location_code)->name ?? 'N/A')
                : ($t->location_other ?? 'N/A');

            $row->location = $location;

            $row->action = '
        <div class="d-flex justify-content-center">
            <button class="btn btn-secondary btn-sm" disabled>
                Process
            </button>
        </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        if (! collect($columns)->contains('field', 'action')) {

            $columns[] = [
                'field' => 'action',
                'headerName' => 'Actions',
                'width' => 150,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $this->data['gridConfig'] = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        return view('admin.booking.erroneousFinance', $this->data);
    }

    public function erroneousInsurance(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_INSURANCE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Erroneous Insurance';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 1);

        $paginatedBookings = $query->orderBy('booking_date', 'DESC')
            ->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {

            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no =
                ($paginatedBookings->currentPage() - 1)
                * $paginatedBookings->perPage()
                + $index + 1;

            $row->action = '
        <div class="d-flex justify-content-center gap-2">
            <button class="btn btn-secondary btn-sm" disabled>
                Process
            </button>
        </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        if (! collect($columns)->contains('field', 'action')) {

            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $this->data['gridConfig'] = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        return view('admin.booking.erroneousInsurance', $this->data);
    }

    public function erroneousRTO(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_RTO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Erroneous RTO';

        $query = $this->getBaseQuery();

        $query->where('bookings.status', 1);

        $paginatedBookings = $query->orderBy('booking_date', 'DESC')
            ->paginate(50);

        $lookups = $this->getCommonLookups();
        extract($lookups);

        $saleConsultants = $lookups['saleConsultants'] ?? [];
        $financiers = $lookups['financiers'] ?? [];
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($t, $index) use (
            $paginatedBookings,
            $gridLookups

        ) {

            $row = $this->mapBookingForGrid($t, $gridLookups);

            $row->serial_no =
                ($paginatedBookings->currentPage() - 1)
                * $paginatedBookings->perPage()
                + $index + 1;

            $row->action = '
        <div class="d-flex justify-content-center gap-2">
            <button class="btn btn-secondary btn-sm" disabled>
                Process
            </button>
        </div>';

            return $row;
        })->values();

        $columns = $this->getAgGridColumns();

        if (! collect($columns)->contains('field', 'action')) {
            $columns[] = [
                'field' => 'action',
                'headerName' => 'Action',
                'width' => 120,
                'pinned' => 'right',
                'sortable' => false,
                'filter' => false,
                'cellRenderer' => 'htmlRenderer',
                'cellClass' => 'text-center p-0',
                'autoHeight' => true,
            ];
        }

        $this->data['gridConfig'] = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        return view('admin.booking.erroneousRTO', $this->data);
    }

    public function stockReport(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REPORT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Stock Report';

        $now = Carbon::now();
        $py = $now->format('Y') - 1;
        $cy = $now->format('Y');
        $ovin = 'STOCK VIN-'.$py;
        $cvin = 'STOCK VIN-'.$cy;

        $locbr = DB::table('xlr8_us_location')
            ->whereNotNull('abbr')
            ->where('status', 1)
            ->pluck('abbr')
            ->unique()
            ->sort()
            ->values()
            ->toArray() ?: ['BKN', 'CHR'];

        $stocks = DB::table('xlr8_stock_master as stock')
            ->leftJoin('xlr8_vehicle_master as vm', 'stock.vehicle_oem_code', '=', 'vm.id')

            ->leftJoin('bmpl_enum_master as seg', 'vm.segment_code', '=', 'seg.id')
            ->leftJoin('xlr8_us_location as loc', 'stock.location_code', '=', 'loc.id')

            ->selectRaw("
                stock.id,
                stock.chassis_no,
                stock.oem_invoice_date,
                stock.damage,
                stock.v_status,
                COALESCE(seg.value, 'Unknown') as seg,
                COALESCE(vm.oem_model, 'Unknown') as mdl,         
                COALESCE(vm.oem_variant, 'Unknown') as vrnt,
                COALESCE(vm.color, 'Unknown') as clr,
                COALESCE(loc.abbr, 'UNK') as loc_abbr,
                vm.id as vehicle_oem_code                                     
        ")

            ->whereNull('stock.inv_id')
            ->whereNull('stock.inv_date')
            ->where('stock.status', 1)
            ->whereIn('stock.v_status', ['Received', 'In Transit', 'Dealer Stock', 'Alloted'])

            ->get();

        $grouped = $stocks->groupBy(function ($stock) {
            return implode('|', [
                $stock->seg ?? 'Unknown',
                $stock->mdl ?? 'Unknown',
                $stock->vrnt ?? 'Unknown',
                $stock->clr ?? 'Unknown',
            ]);
        });

        $data = [];
        $sno = 1;

        foreach ($grouped as $key => $groupStocks) {
            if ($groupStocks->isEmpty()) {
                continue;
            }

            [$seg, $mdl, $vrnt, $clr] = explode('|', $key);

            $branchCounts = $groupStocks->countBy('loc_abbr')->toArray();

            $row = [
                'sno' => $sno++,
                'seg' => $seg,
                'mdl' => $mdl,
                'vrnt' => $vrnt,
                'clr' => $clr,
                'total' => $groupStocks->count(),
                'bkn' => $branchCounts['BKN'] ?? 0,
                'chr' => $branchCounts['CHR'] ?? 0,
                'tst_max_age' => '0 D',
                'stock_max_age' => '0 D',
                'stock_gt_60' => 0,
                'bkng' => (int) Booking::where('model', $mdl)->count(),
                'enq' => (int) Booking::where('model', $mdl)->where('status', 1)->count(),
                'lordr' => 0,
            ];

            $ovin_stats = array_fill_keys($locbr, 0) + ['damage' => 0, 'dlr_transit' => 0, 'oem_transit' => 0];
            $cvin_stats = array_fill_keys($locbr, 0) + ['damage' => 0, 'dlr_transit' => 0, 'oem_transit' => 0];

            $tst_max_age = 0;
            $stock_max_age = 0;
            $stock_gt_60 = 0;

            foreach ($groupStocks as $stock) {
                if (empty($stock->oem_invoice_date) || empty($stock->chassis_no) || strlen($stock->chassis_no) < 10) {
                    continue;
                }

                $age = $now->diffInDays(Carbon::parse($stock->oem_invoice_date));
                $is_current_year = str_starts_with($stock->chassis_no, 'S');

                $stats = $is_current_year ? $cvin_stats : $ovin_stats;

                $loc = $stock->loc_abbr ?? 'UNK';

                if (array_key_exists($loc, $stats)) {
                    $stats[$loc]++;
                }

                if ($stock->damage == 1) {
                    $stats['damage']++;
                }

                if (strtolower($stock->v_status) === 'in transit') {
                    $stats['oem_transit']++;
                    $tst_max_age = max($tst_max_age, $age);
                } else {
                    $stats['dlr_transit']++;
                    $stock_max_age = max($stock_max_age, $age);
                    if ($age >= 60) {
                        $stock_gt_60++;
                    }
                }
            }

            foreach ($locbr as $loc) {
                $row['ovin_'.strtolower($loc)] = $ovin_stats[$loc] ?? 0;
                $row['cvin_'.strtolower($loc)] = $cvin_stats[$loc] ?? 0;
            }

            $row['ovin_damage'] = $ovin_stats['damage'];
            $row['ovin_dlr_transit'] = $ovin_stats['dlr_transit'];
            $row['ovin_oem_transit'] = $ovin_stats['oem_transit'];
            $row['cvin_damage'] = $cvin_stats['damage'];
            $row['cvin_dlr_transit'] = $cvin_stats['dlr_transit'];
            $row['cvin_oem_transit'] = $cvin_stats['oem_transit'];

            $row['tst_max_age'] = $tst_max_age ? $tst_max_age.' D' : '0 D';
            $row['stock_max_age'] = $stock_max_age ? $stock_max_age.' D' : '0 D';
            $row['stock_gt_60'] = $stock_gt_60;

            $data[] = $row;
        }

        $columns = [
            ['field' => 'sno', 'headerName' => 'S.No.', 'width' => 80, 'pinned' => 'left', 'filter' => false],

            [
                'headerName' => 'VEHICLE INFO',
                'children' => [
                    ['field' => 'seg',  'headerName' => 'SEGMENT', 'width' => 140],
                    ['field' => 'mdl',  'headerName' => 'MODEL',   'width' => 160],
                    ['field' => 'vrnt', 'headerName' => 'VARIANT', 'width' => 220],
                    ['field' => 'clr',  'headerName' => 'COLOR',   'width' => 130],
                ],
            ],

            [
                'headerName' => 'TOTAL STOCK',
                'children' => [
                    ['field' => 'total', 'headerName' => 'TOTAL', 'width' => 100, 'cellClass' => 'text-right'],
                    ['field' => 'bkn',   'headerName' => 'BKN',   'width' => 80,  'cellClass' => 'text-right'],
                    ['field' => 'chr',   'headerName' => 'CHR',   'width' => 80,  'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => $ovin,
                'children' => array_merge(
                    array_map(fn ($loc) => ['field' => 'ovin_'.strtolower($loc), 'headerName' => $loc, 'width' => 80, 'cellClass' => 'text-right'], $locbr),
                    [
                        ['field' => 'ovin_damage',      'headerName' => 'DAMAGE',     'width' => 100, 'cellClass' => 'text-right'],
                        ['field' => 'ovin_dlr_transit', 'headerName' => 'DLR TST',    'width' => 110, 'cellClass' => 'text-right'],
                        ['field' => 'ovin_oem_transit', 'headerName' => 'OEM TST',    'width' => 110, 'cellClass' => 'text-right'],
                    ]
                ),
            ],

            [
                'headerName' => $cvin,
                'children' => array_merge(
                    array_map(fn ($loc) => ['field' => 'cvin_'.strtolower($loc), 'headerName' => $loc, 'width' => 80, 'cellClass' => 'text-right'], $locbr),
                    [
                        ['field' => 'cvin_damage',      'headerName' => 'DAMAGE',     'width' => 100, 'cellClass' => 'text-right'],
                        ['field' => 'cvin_dlr_transit', 'headerName' => 'DLR TST',    'width' => 110, 'cellClass' => 'text-right'],
                        ['field' => 'cvin_oem_transit', 'headerName' => 'OEM TST',    'width' => 110, 'cellClass' => 'text-right'],
                    ]
                ),
            ],

            [
                'headerName' => 'GLOBAL DATA',
                'children' => [
                    ['field' => 'tst_max_age',  'headerName' => 'TST MAX AGE', 'width' => 140],
                    ['field' => 'stock_max_age', 'headerName' => 'PHY MAX AGE', 'width' => 140],
                    ['field' => 'stock_gt_60',  'headerName' => 'AGE > 60D',   'width' => 120, 'cellClass' => 'text-right'],
                    ['field' => 'bkng',         'headerName' => 'BOOKED',      'width' => 120, 'cellClass' => 'text-right'],
                    ['field' => 'enq',          'headerName' => 'HOT ENQ',     'width' => 120, 'cellClass' => 'text-right'],
                    ['field' => 'lordr',        'headerName' => 'LIVE ORDERS', 'width' => 130, 'cellClass' => 'text-right'],
                ],
            ],
        ];

        $gridConfig = [
            'columns' => $columns,
            'data' => $data,
            'ovin' => $ovin,
            'cvin' => $cvin,
            'locbr' => $locbr,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.stock', $this->data);
    }

    public function liveOrderReport(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REPORT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Live Order Report';

        $vehicles = DB::table('xlr8_vehicle_master as vm')
            ->where('vm.lorder', '>', 0)
            ->leftJoin('xlr8_stock_master as stock', 'vm.id', '=', 'stock.vehicle_oem_code')
            ->leftJoin('xlr8_us_location as loc', 'stock.location_code', '=', 'loc.id')
            ->select(
                'vm.segment_code',
                'vm.custom_model',
                'vm.custom_variant',
                'vm.color',
                'vm.lorder',
                DB::raw("COALESCE(MAX(loc.abbr), 'Not Allocated') as branch")
            )
            ->groupBy('vm.id', 'vm.segment_code', 'vm.custom_model', 'vm.custom_variant', 'vm.color', 'vm.lorder')
            ->get();

        $segments = CommonHelper::getVehicleSegments();

        $gridData = $vehicles->map(function ($vh, $index) {
            $seg = $vh->segment_code ?? 'N/A';

            return [
                'sno' => $index + 1,
                'seg' => $seg,
                'mdl' => $vh->custom_model ?? 'N/A',
                'vrnt' => $vh->custom_variant ?? 'N/A',
                'clr' => $vh->color ?? 'N/A',
                'branch' => $vh->branch ?? 'Not Allocated',
                'lordr' => $vh->lorder ?? 0,
            ];
        })->values();

        $columns = [
            ['field' => 'sno',   'headerName' => 'S.No.', 'width' => 100,  'pinned' => 'left'],
            ['field' => 'seg',   'headerName' => 'Segment', 'width' => 200],
            ['field' => 'mdl',   'headerName' => 'Model',   'width' => 240],
            ['field' => 'vrnt',  'headerName' => 'Variant', 'width' => 280],
            ['field' => 'clr',   'headerName' => 'Color',   'width' => 250],
            ['field' => 'branch', 'headerName' => 'Branch',  'width' => 200],
            ['field' => 'lordr', 'headerName' => 'Live Orders', 'width' => 230, 'cellClass' => 'text-right'],
        ];

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if ($gridData->isEmpty()) {
            session()->flash('info', 'No live orders found.');
        }

        return view('admin.booking.live-order', $this->data);
    }

    public function fetchCbrData()
    {
        if (! backpack_user()->can('SLS_BKNG_REPORT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $now = Carbon::now();
        $mtdStart = $now->copy()->startOfMonth();
        $ytdStart = $now->copy()->startOfYear();

        $data = Cache::remember('cbr_data_'.$now->format('YmdH'), 3600, function () use ($mtdStart, $ytdStart) {
            $bookings = DB::table('xlr8_booking_master as bm')
                ->join('xlr8_vehicle_master as vm', 'bm.vehicle_oem_code', '=', 'vm.id')
                ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
                ->whereIn('bm.status', [1, 4, 6, 8])
                ->select(
                    'bm.id',
                    'bm.status',
                    'bm.b_type',
                    'bm.fin_mode',
                    'bm.buyer_type',
                    'bm.pending',
                    'bm.order',
                    'bm.dms_so',
                    'bm.booking_amount',
                    'bm.created_at',
                    DB::raw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key'),
                    'em.value as seg',
                    'vm.oem_model as model',
                    'vm.oem_variant as variant',
                    'vm.color as clr',
                    'vm.code'
                )
                ->get()
                ->groupBy('group_key');

            $bookingAmounts = DB::table('xlr8_booking_amount')
                ->where('status', 1)
                ->select('bid', DB::raw('SUM(amount) as total_amount'))
                ->groupBy('bid')
                ->pluck('total_amount', 'bid');

            $liveOrders = DB::table('xlr8_vehicle_master as vm')
                ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
                ->selectRaw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key, SUM(vm.lorder) as lorder')
                ->groupBy('group_key')
                ->pluck('lorder', 'group_key');

            $stocksRaw = DB::table('xlr8_stock_master as sm')
                ->join('xlr8_vehicle_master as vm', 'sm.model_code', '=', 'vm.code')
                ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
                ->join('xlr8_us_location as ul', 'sm.location_code', '=', 'ul.id')
                ->selectRaw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key, ul.branch_code, COUNT(sm.id) as quantity')
                ->groupBy('group_key', 'ul.branch_code')
                ->get();

            $stocks = $stocksRaw->groupBy('group_key')->map(function ($group) {
                return $group->groupBy('branch_code')->map(fn ($bg) => $bg->sum('quantity'));
            });

            $exchanges = DB::table('xlr8_exchange')
                ->whereIn('verification_status', [0, null])
                ->select('bid', 'purchase_type')
                ->get()
                ->groupBy('bid')
                ->map(function ($group) {
                    return [
                        'exchange_pending' => $group->where('purchase_type', 'Exchange')->count() > 0 ? 1 : 0,
                        'scrappage_pending' => $group->where('purchase_type', 'Scrappage')->count() > 0 ? 1 : 0,
                    ];
                });

            $finances = DB::table('xlr8_booking_finance')
                ->whereIn('verification_status', [0, null])
                ->pluck('bid')
                ->mapWithKeys(fn ($bid) => [$bid => 1]);

            $data = collect();
            $index = 1;

            foreach ($bookings as $groupKey => $groupBookings) {
                [$seg, $model, $variant, $clr] = explode('|', $groupKey);

                $liveGroup = $groupBookings->whereIn('status', [1, 6, 8])->where('b_type', '!=', 'dummy');

                $total_bookings = $liveGroup->count();
                if ($total_bookings === 0) {
                    continue;
                }

                $bkn_bookings = $liveGroup->where('b_type', 'Individual')->count();
                $chr_bookings = $liveGroup->where('b_type', 'Dealer')->count();

                $max_age_days = $liveGroup->max(
                    fn ($booking) => abs(Carbon::parse($booking->created_at)->diffInDays(now()))
                );

                $age_gt_60 = $liveGroup->filter(fn ($booking) => abs(Carbon::parse($booking->created_at)->diffInDays(now()))
                    > 60)->count();

                $live_orders = $liveOrders->get($groupKey, 0);

                $dummy_bookings = $groupBookings->whereIn('status', [1, 6, 8])->where('b_type', 'dummy')->count();

                $on_hold = $liveGroup->where('status', 6)->count();

                $verify = $liveGroup->where('order', 1)->count();

                $orders = $liveGroup->where('order', 2)->whereNull('dms_so')->count();

                $payments = $liveGroup->filter(function ($booking) use ($bookingAmounts) {
                    $total_amount = $bookingAmounts->get($booking->id, 0);

                    return $total_amount < $booking->booking_amount;
                })->count();

                $data_pending = $liveGroup->where('pending', '>', 0)->count();

                $refunds = $groupBookings->where('status', 4)->count();

                $cash = $liveGroup->where('fin_mode', 'Cash')->count();
                $cash_pct = $total_bookings > 0 ? round(($cash / $total_bookings) * 100, 2) : 0;

                $inhouse = $liveGroup->where('fin_mode', 'In-house')->count();
                $inhouse_pct = $total_bookings > 0 ? round(($inhouse / $total_bookings) * 100, 2) : 0;

                $self = $liveGroup->where('fin_mode', 'Customer-Self')->count();
                $self_pct = $total_bookings > 0 ? round(($self / $total_bookings) * 100, 2) : 0;

                $finance_pending = $liveGroup->filter(fn ($booking) => $finances->get($booking->id, 0) > 0)->count();

                $mtd_live = $liveGroup->where('created_at', '>=', $mtdStart);
                $mtd_total = $mtd_live->count();
                $mtd_inhouse = $mtd_live->where('fin_mode', 'In-house')->count();
                $mtd_finance = $mtd_total > 0 ? round(($mtd_inhouse / $mtd_total) * 100, 2) : 0;

                $ytd_live = $liveGroup->where('created_at', '>=', $ytdStart);
                $ytd_total = $ytd_live->count();
                $ytd_inhouse = $ytd_live->where('fin_mode', 'In-house')->count();
                $ytd_finance = $ytd_total > 0 ? round(($ytd_inhouse / $ytd_total) * 100, 2) : 0;

                $exchange_inhouse = $liveGroup->where('buyer_type', 'Exchange')->count();
                $exchange_pct = $total_bookings > 0 ? round(($exchange_inhouse / $total_bookings) * 100, 2) : 0;
                $exchange_pending = $liveGroup->filter(fn ($booking) => ($exchanges->get($booking->id)['exchange_pending'] ?? 0) > 0)->count();

                $mtd_exchange_inhouse = $mtd_live->where('buyer_type', 'Exchange')->count();
                $mtd_exchange = $mtd_total > 0 ? round(($mtd_exchange_inhouse / $mtd_total) * 100, 2) : 0;

                $ytd_exchange_inhouse = $ytd_live->where('buyer_type', 'Exchange')->count();
                $ytd_exchange = $ytd_total > 0 ? round(($ytd_exchange_inhouse / $ytd_total) * 100, 2) : 0;

                $scrappage_inhouse = $liveGroup->where('buyer_type', 'Scrappage')->count();
                $scrappage_pct = $total_bookings > 0 ? round(($scrappage_inhouse / $total_bookings) * 100, 2) : 0;
                $scrappage_pending = $liveGroup->filter(fn ($booking) => ($exchanges->get($booking->id)['scrappage_pending'] ?? 0) > 0)->count();

                $mtd_scrappage_inhouse = $mtd_live->where('buyer_type', 'Scrappage')->count();
                $mtd_scrappage = $mtd_total > 0 ? round(($mtd_scrappage_inhouse / $mtd_total) * 100, 2) : 0;

                $ytd_scrappage_inhouse = $ytd_live->where('buyer_type', 'Scrappage')->count();
                $ytd_scrappage = $ytd_total > 0 ? round(($ytd_scrappage_inhouse / $ytd_total) * 100, 2) : 0;

                $stock_group = $stocks->get($groupKey, collect());
                $stock_total = $stock_group->values()->sum();
                $stock_bkn = $stock_group->get(1, 0);
                $stock_chr = $stock_group->get(2, 0);

                $data->push([
                    'sno' => $index++,
                    'seg' => $seg,
                    'model' => $model,
                    'variant' => $variant,
                    'clr' => $clr,
                    'stock_total' => $stock_total,
                    'stock_bkn' => $stock_bkn,
                    'stock_chr' => $stock_chr,
                    'total_bookings' => $total_bookings,
                    'bkn_bookings' => $bkn_bookings,
                    'chr_bookings' => $chr_bookings,
                    'max_age' => $max_age_days ? ceil($max_age_days).' D' : '0 D',
                    'age_gt_60d' => $age_gt_60,
                    'live_orders' => $live_orders,
                    'dummy_bookings' => $dummy_bookings,
                    'on_hold' => $on_hold,
                    'verify' => $verify,
                    'orders' => $orders,
                    'payments' => $payments,
                    'data' => $data_pending,
                    'refund' => $refunds,
                    'cash' => $cash,
                    'cash_pct' => number_format($cash_pct, 2).'%',
                    'inhouse' => $inhouse,
                    'inhouse_pct' => number_format($inhouse_pct, 2).'%',
                    'self' => $self,
                    'self_pct' => number_format($self_pct, 2).'%',
                    'finance_pending' => $finance_pending,
                    'mtd' => number_format($mtd_finance, 2).'%',
                    'ytd' => number_format($ytd_finance, 2).'%',
                    'exchange_inhouse' => $exchange_inhouse,
                    'exchange_inhouse_pct' => number_format($exchange_pct, 2).'%',
                    'exchange_pending' => $exchange_pending,
                    'exchange_mtd' => number_format($mtd_exchange, 2).'%',
                    'exchange_ytd' => number_format($ytd_exchange, 2).'%',
                    'scrappage_inhouse' => $scrappage_inhouse,
                    'scrappage_inhouse_pct' => number_format($scrappage_pct, 2).'%',
                    'scrappage_pending' => $scrappage_pending,
                    'scrappage_mtd' => number_format($mtd_scrappage, 2).'%',
                    'scrappage_ytd' => number_format($ytd_scrappage, 2).'%',
                ]);
            }

            return $data;
        });

        $tbr = [
            'seg' => 'Total',
            'total_bookings' => $data->sum('total_bookings'),
            'bkn_bookings' => $data->sum('bkn_bookings'),
            'chr_bookings' => $data->sum('chr_bookings'),
            'stock_total' => $data->sum('stock_total'),
            'stock_bkn' => $data->sum('stock_bkn'),
            'stock_chr' => $data->sum('stock_chr'),
            'max_age' => $data->max('max_age') ? str_replace(' D', '', $data->max('max_age')).' D' : '',
            'age_gt_60d' => $data->sum('age_gt_60d'),
            'live_orders' => $data->sum('live_orders'),
            'dummy_bookings' => $data->sum('dummy_bookings'),
            'on_hold' => $data->sum('on_hold'),
            'verify' => $data->sum('verify'),
            'orders' => $data->sum('orders'),
            'payments' => $data->sum('payments'),
            'data' => $data->sum('data'),
            'refund' => $data->sum('refund'),
            'cash' => $data->sum('cash'),
            'cash_pct' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['cash_pct'])), 2).'%' : '0.00%',
            'inhouse' => $data->sum('inhouse'),
            'inhouse_pct' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['inhouse_pct'])), 2).'%' : '0.00%',
            'self' => $data->sum('self'),
            'self_pct' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['self_pct'])), 2).'%' : '0.00%',
            'finance_pending' => $data->sum('finance_pending'),
            'mtd' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['mtd'])), 2).'%' : '0.00%',
            'ytd' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['ytd'])), 2).'%' : '0.00%',
            'exchange_inhouse' => $data->sum('exchange_inhouse'),
            'exchange_inhouse_pct' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['exchange_inhouse_pct'])), 2).'%' : '0.00%',
            'exchange_pending' => $data->sum('exchange_pending'),
            'exchange_mtd' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['exchange_mtd'])), 2).'%' : '0.00%',
            'exchange_ytd' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['exchange_ytd'])), 2).'%' : '0.00%',
            'scrappage_inhouse' => $data->sum('scrappage_inhouse'),
            'scrappage_inhouse_pct' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['scrappage_inhouse_pct'])), 2).'%' : '0.00%',
            'scrappage_pending' => $data->sum('scrappage_pending'),
            'scrappage_mtd' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['scrappage_mtd'])), 2).'%' : '0.00%',
            'scrappage_ytd' => $data->count() > 0 ? number_format($data->avg(fn ($r) => (float) str_replace('%', '', $r['scrappage_ytd'])), 2).'%' : '0.00%',
        ];

        $stkbr = [
            'stock_total' => DB::table('xlr8_stock_master')->count('id'),
            'stock_bkn' => DB::table('xlr8_stock_master')
                ->join('xlr8_us_location', 'xlr8_stock_master.location_code', '=', 'xlr8_us_location.id')
                ->where('xlr8_us_location.branch_code', 1)
                ->count('xlr8_stock_master.id'),
            'stock_chr' => DB::table('xlr8_stock_master')
                ->join('xlr8_us_location', 'xlr8_stock_master.location_code', '=', 'xlr8_us_location.id')
                ->where('xlr8_us_location.branch_code', 2)
                ->count('xlr8_stock_master.id'),
        ];

        $title = 'Consolidated Booking Report';
        $filename = 'CnsldtBkngRprt_'.$now->format('Y-m-d-H-i-s').'.xlsx';

        $header = [
            ['title' => 'S.No.', 'field' => 'sno', 'hozAlign' => 'center', 'formatter' => 'plaintext'],
            [
                'title' => 'Vehicle Info',
                'columns' => [
                    ['title' => 'Segment', 'field' => 'seg', 'headerFilter' => 'select'],
                    ['title' => 'Model', 'field' => 'model', 'headerFilter' => 'select'],
                    ['title' => 'Variant', 'field' => 'variant', 'headerFilter' => 'select'],
                    ['title' => 'Color', 'field' => 'clr', 'headerFilter' => 'select'],
                ],
            ],
            [
                'title' => 'Stock',
                'columns' => [
                    ['title' => 'Total', 'field' => 'stock_total', 'bottomCalc' => 'sum'],
                    ['title' => 'BKN', 'field' => 'stock_bkn', 'bottomCalc' => 'sum'],
                    ['title' => 'CHR', 'field' => 'stock_chr', 'bottomCalc' => 'sum'],
                ],
            ],
            [
                'title' => 'Bookings',
                'columns' => [
                    ['title' => 'Total', 'field' => 'total_bookings', 'bottomCalc' => 'sum'],
                    ['title' => 'BKN', 'field' => 'bkn_bookings', 'bottomCalc' => 'sum'],
                    ['title' => 'CHR', 'field' => 'chr_bookings', 'bottomCalc' => 'sum'],
                ],
            ],
            [
                'title' => 'Global Info',
                'columns' => [
                    ['title' => 'Max Age', 'field' => 'max_age', 'bottomCalc' => function ($values) {
                        $max = collect($values)->map(fn ($val) => (int) str_replace(' D', '', $val))->max();

                        return $max.' D';
                    }],
                    ['title' => 'Age > 60D', 'field' => 'age_gt_60d', 'bottomCalc' => function ($values) {
                        $max = collect($values)->map(fn ($val) => (int) str_replace(' D', '', $val))->max();

                        return $max.' D';
                    }],
                    ['title' => 'Live Orders', 'field' => 'live_orders', 'bottomCalc' => 'sum'],
                    ['title' => 'Dummy Bookings', 'field' => 'dummy_bookings', 'bottomCalc' => 'sum'],
                    ['title' => 'On Hold', 'field' => 'on_hold', 'bottomCalc' => 'sum'],
                ],
            ],
            [
                'title' => 'Pending Actions',
                'columns' => [
                    ['title' => 'Verify', 'field' => 'verify', 'bottomCalc' => 'sum'],
                    ['title' => 'Orders', 'field' => 'orders', 'bottomCalc' => 'sum'],
                    ['title' => 'Payments', 'field' => 'payments', 'bottomCalc' => 'sum'],
                    ['title' => 'Data', 'field' => 'data', 'bottomCalc' => 'sum'],
                    ['title' => 'Refund', 'field' => 'refund', 'bottomCalc' => 'sum'],
                ],
            ],
            [
                'title' => 'Finance',
                'columns' => [
                    ['title' => 'Cash', 'field' => 'cash'],
                    ['title' => 'Cash %', 'field' => 'cash_pct', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                    ['title' => 'In-house', 'field' => 'inhouse'],
                    ['title' => 'In-house %', 'field' => 'inhouse_pct', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                    ['title' => 'Self', 'field' => 'self'],
                    ['title' => 'Self %', 'field' => 'self_pct', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                    ['title' => 'Pending', 'field' => 'finance_pending'],
                    ['title' => 'MTD', 'field' => 'mtd', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                    ['title' => 'YTD', 'field' => 'ytd', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                ],
            ],
            [
                'title' => 'Exchange',
                'columns' => [
                    ['title' => 'In-house', 'field' => 'exchange_inhouse'],
                    ['title' => 'In-house %', 'field' => 'exchange_inhouse_pct', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                    ['title' => 'Pending', 'field' => 'exchange_pending'],
                    ['title' => 'MTD', 'field' => 'exchange_mtd', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                    ['title' => 'YTD', 'field' => 'exchange_ytd', 'bottomCalc' => function ($values) {
                        $avg = collect($values)->map(fn ($val) => (float) str_replace('%', '', $val))->avg();

                        return number_format($avg, 2).'%';
                    }],
                ],
            ],
            [
                'title' => 'Scrappage',
                'columns' => [
                    ['title' => 'In-house', 'field' => 'scrappage_inhouse'],
                    ['title' => 'In-house %', 'field' => 'scrappage_inhouse_pct'],
                    ['title' => 'Pending', 'field' => 'scrappage_pending'],
                    ['title' => 'MTD', 'field' => 'scrappage_mtd'],
                    ['title' => 'YTD', 'field' => 'scrappage_ytd'],
                ],
            ],
        ];

        return [$header, $data, $tbr, $stkbr, $filename, $title];
    }

    public function consolidatedBookingReport(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REPORT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Consolidated Booking Report';

        $now = Carbon::now();

        $bookings = DB::table('xlr8_booking_master as bm')
            ->join('xlr8_vehicle_master as vm', 'bm.vehicle_oem_code', '=', 'vm.id')
            ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
            ->whereIn('bm.status', [1, 4, 6, 8])
            ->select(
                'bm.id',
                'bm.status',
                'bm.b_type',
                'bm.fin_mode',
                'bm.buyer_type',
                'bm.pending',
                'bm.order',
                'bm.dms_so',
                'bm.booking_amount',
                'bm.created_at',
                DB::raw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key'),
                'em.value as seg',
                'vm.oem_model as model',
                'vm.oem_variant as variant',
                'vm.color as clr',
                'vm.code'
            )
            ->get()
            ->groupBy('group_key');

        $bookingAmounts = DB::table('xlr8_booking_amount')
            ->where('status', 1)
            ->select('bid', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('bid')
            ->pluck('total_amount', 'bid');

        $liveOrders = DB::table('xlr8_vehicle_master as vm')
            ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
            ->selectRaw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key, SUM(vm.lorder) as lorder')
            ->groupBy('group_key')
            ->pluck('lorder', 'group_key');

        $stocksRaw = DB::table('xlr8_stock_master as sm')
            ->join('xlr8_vehicle_master as vm', 'sm.model_code', '=', 'vm.code')
            ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
            ->join('xlr8_us_location as ul', 'sm.location_code', '=', 'ul.id')
            ->selectRaw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key, ul.branch_code, COUNT(sm.id) as quantity')
            ->groupBy('group_key', 'ul.branch_code')
            ->get();

        $stocks = $stocksRaw->groupBy('group_key')->map(function ($group) {
            return $group->groupBy('branch_code')->map(fn ($bg) => $bg->sum('quantity'));
        });

        $exchanges = DB::table('xlr8_exchange')
            ->whereIn('verification_status', [0, null])
            ->select('bid', 'purchase_type')
            ->get()
            ->groupBy('bid')
            ->map(function ($group) {
                return [
                    'exchange_pending' => $group->where('purchase_type', 'Exchange')->count() > 0 ? 1 : 0,
                    'scrappage_pending' => $group->where('purchase_type', 'Scrappage')->count() > 0 ? 1 : 0,
                ];
            });

        $finances = DB::table('xlr8_booking_finance')
            ->whereIn('verification_status', [0, null])
            ->pluck('bid')
            ->mapWithKeys(fn ($bid) => [$bid => 1]);

        $gridData = [];
        $sno = 1;

        foreach ($bookings as $groupKey => $groupBookings) {
            [$seg, $model, $variant, $clr] = explode('|', $groupKey);

            $liveGroup = $groupBookings->whereIn('status', [1, 6, 8])->where('b_type', '!=', 'dummy');

            $total_bookings = $liveGroup->count();
            if ($total_bookings === 0) {
                continue;
            }

            $bkn_bookings = $liveGroup->where('b_type', 'Individual')->count();
            $churu_bookings = $liveGroup->where('b_type', 'Dealer')->count();

            $max_age_days = $liveGroup->max(fn ($b) => abs(Carbon::parse($b->created_at)->diffInDays($now)));

            $age_gt_60 = $liveGroup->filter(fn ($b) => abs(Carbon::parse($b->created_at)->diffInDays($now)) > 60)->count();

            $live_orders = $liveOrders->get($groupKey, 0);

            $dummy_bookings = $groupBookings->whereIn('status', [1, 6, 8])->where('b_type', 'dummy')->count();

            $on_hold = $liveGroup->where('status', 6)->count();

            $verify = $liveGroup->where('order', 1)->count();

            $orders = $liveGroup->where('order', 2)->whereNull('dms_so')->count();

            $payments = $liveGroup->filter(function ($b) use ($bookingAmounts) {
                return ($bookingAmounts->get($b->id, 0) ?? 0) < $b->booking_amount;
            })->count();

            $data_pending = $liveGroup->where('pending', '>', 0)->count();

            $refund = $groupBookings->where('status', 4)->count();

            $cash = $liveGroup->where('fin_mode', 'Cash')->count();
            $cash_pct = $total_bookings ? round($cash / $total_bookings * 100, 2) : 0;

            $inhouse = $liveGroup->where('fin_mode', 'In-house')->count();
            $inhouse_pct = $total_bookings ? round($inhouse / $total_bookings * 100, 2) : 0;

            $self = $liveGroup->where('fin_mode', 'Customer-Self')->count();
            $self_pct = $total_bookings ? round($self / $total_bookings * 100, 2) : 0;

            $finance_pending = $liveGroup->filter(fn ($b) => $finances->get($b->id, 0))->count();

            $stock_group = $stocks->get($groupKey, collect());
            $stock_total = $stock_group->values()->sum();
            $stock_bkn = $stock_group->get(1, 0);
            $stock_churu = $stock_group->get(2, 0);

            $gridData[] = [
                'sno' => $sno++,
                'seg' => $seg,
                'model' => $model,
                'variant' => $variant,
                'clr' => $clr,
                'stock_total' => $stock_total,
                'stock_bikaner' => $stock_bkn,
                'stock_churu' => $stock_churu,
                'booking_total' => $total_bookings,
                'booking_bikaner' => $bkn_bookings,
                'booking_churu' => $churu_bookings,
                'hot_enq_total' => 0,
                'hot_enq_bikaner' => 0,
                'hot_enq_churu' => 0,
                'finance_total' => $cash + $inhouse + $self,
                'finance_bikaner' => 0,
                'finance_churu' => 0,
                'finance_pending' => $finance_pending,
                'exchange_total' => $liveGroup->where('buyer_type', 'Exchange')->count(),
                'exchange_bikaner' => 0,
                'exchange_churu' => 0,
                'exchange_pending' => $liveGroup->filter(fn ($b) => $exchanges->get($b->id)['exchange_pending'] ?? 0)->count(),
                'max_age' => $max_age_days ? ceil($max_age_days).' D' : '0 D',
                'age_gt_60d' => $age_gt_60,
                'live_orders' => $live_orders,
                'dummy_bookings' => $dummy_bookings,
                'on_hold' => $on_hold,
                'order_verification' => $verify,
                'order_creation' => $orders,
                'booking_creation' => $total_bookings,
                'customer_data' => $data_pending,
                'book_canc' => 0,
                'refund' => $refund,
            ];
        }

        $columns = [
            [
                'field' => 'sno',
                'headerName' => 'S.No.',
                'width' => 80,
                'pinned' => 'left',
                'filter' => false,
                'sortable' => false,
            ],

            [
                'headerName' => 'Vehicle Info',
                'headerClass' => 'group-vehicle-info',
                'children' => [
                    ['field' => 'seg', 'headerName' => 'Segment', 'width' => 140],
                    ['field' => 'model', 'headerName' => 'Model', 'width' => 180],
                    ['field' => 'variant', 'headerName' => 'Variant', 'width' => 240],
                    ['field' => 'clr', 'headerName' => 'Color', 'width' => 140],
                ],
            ],

            [
                'headerName' => 'STOCK',
                'headerClass' => 'group-stock',
                'children' => [
                    ['field' => 'stock_total', 'headerName' => 'TOTAL', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'BOOKING',
                'headerClass' => 'group-booking',
                'children' => [
                    ['field' => 'booking_total', 'headerName' => 'TOTAL', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'booking_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'booking_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'HOT ENQ',
                'headerClass' => 'group-hot-enq',
                'children' => [
                    ['field' => 'hot_enq_total', 'headerName' => 'TOTAL', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'hot_enq_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'hot_enq_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'INT IN FINANCE',
                'headerClass' => 'group-finance',
                'children' => [
                    ['field' => 'finance_total', 'headerName' => 'TOTAL', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'finance_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'finance_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'finance_pending', 'headerName' => 'PENDING', 'width' => 110, 'cellClass' => 'text-right fw-bold'],
                ],
            ],

            [
                'headerName' => 'INT IN EXCHANGE',
                'headerClass' => 'group-exchange',
                'children' => [
                    ['field' => 'exchange_total', 'headerName' => 'TOTAL', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'exchange_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'exchange_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'exchange_pending', 'headerName' => 'PENDING', 'width' => 110, 'cellClass' => 'text-right fw-bold'],
                ],
            ],

            [
                'headerName' => 'GLOBAL INFO',
                'headerClass' => 'group-global',
                'children' => [
                    ['field' => 'max_age', 'headerName' => 'MAX AGE', 'width' => 100],
                    ['field' => 'age_gt_60d', 'headerName' => 'AGE > 60D', 'width' => 110, 'cellClass' => 'text-right'],
                    ['field' => 'live_orders', 'headerName' => 'LIVE ORDERS', 'width' => 120, 'cellClass' => 'text-right'],
                    ['field' => 'dummy_bookings', 'headerName' => 'DUMMY BOOKINGS', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'on_hold', 'headerName' => 'ON HOLD', 'width' => 100, 'cellClass' => 'text-right'],
                    ['field' => 'order_verification', 'headerName' => 'ORDER VERIFICATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'order_creation', 'headerName' => 'ORDER CREATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'booking_creation', 'headerName' => 'BOOKING CREATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'customer_data', 'headerName' => 'CUSTOMER DATA', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'book_canc', 'headerName' => 'BOOK CANC', 'width' => 120, 'cellClass' => 'text-right'],
                    ['field' => 'refund', 'headerName' => 'REFUND', 'width' => 100, 'cellClass' => 'text-right'],
                ],
            ],
        ];

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if (empty($gridData)) {
            session()->flash('info', 'No consolidated booking data found.');
        }

        return view('admin.booking.consolidated-booking', $this->data);
    }

    public function branchBookingReport(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REPORT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Branch Booking Report';

        $now = Carbon::now();

        $bookings = DB::table('xlr8_booking_master as bm')
            ->join('xlr8_vehicle_master as vm', 'bm.vehicle_oem_code', '=', 'vm.id')
            ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
            ->whereIn('bm.status', [1, 4, 6, 8])
            ->select(
                'bm.id',
                'bm.status',
                'bm.b_type',
                'bm.created_at',
                'bm.pending',
                'bm.order',
                'bm.dms_so',
                'bm.booking_amount',
                DB::raw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key'),
                'em.value as seg',
                'vm.oem_model as model',
                'vm.oem_variant as variant',
                'vm.color as clr'
            )
            ->get()
            ->groupBy('group_key');

        $stocksRaw = DB::table('xlr8_stock_master as sm')
            ->join('xlr8_vehicle_master as vm', 'sm.model_code', '=', 'vm.code')
            ->join('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
            ->join('xlr8_us_location as ul', 'sm.location_code', '=', 'ul.id')
            ->selectRaw('CONCAT(em.value, "|", COALESCE(vm.oem_model, ""), "|", COALESCE(vm.oem_variant, ""), "|", COALESCE(vm.color, "")) as group_key, ul.name as branch_name, COUNT(sm.id) as quantity')
            ->groupBy('group_key', 'ul.name')
            ->get();

        $stocks = $stocksRaw->groupBy('group_key')->map(fn ($g) => $g->pluck('quantity', 'branch_name')->toArray());

        $gridData = [];
        $sno = 1;

        foreach ($bookings as $groupKey => $groupBookings) {
            [$seg, $model, $variant, $clr] = explode('|', $groupKey);

            $liveGroup = $groupBookings->whereIn('status', [1, 6, 8])->where('b_type', '!=', 'dummy');

            $total_bookings = $liveGroup->count();
            if ($total_bookings === 0) {
                continue;
            }

            $bkn_bookings = $liveGroup->where('b_type', 'Individual')->count();
            $churu_bookings = $liveGroup->where('b_type', 'Dealer')->count();

            $max_age_days = $liveGroup->max(fn ($b) => abs(Carbon::parse($b->created_at)->diffInDays($now)));

            $age_gt_60 = $liveGroup->filter(fn ($b) => abs(Carbon::parse($b->created_at)->diffInDays($now)) > 60)->count();

            $on_hold = $liveGroup->where('status', 6)->count();

            $verify = $liveGroup->where('order', 1)->count();

            $orders = $liveGroup->where('order', 2)->whereNull('dms_so')->count();

            $payments = $liveGroup->filter(function ($b) {
                $paid = DB::table('xlr8_booking_amount')
                    ->where('bid', $b->id)
                    ->where('status', 1)
                    ->sum('amount');

                return $paid < ($b->booking_amount ?? 0);
            })->count();

            $customer_data = $liveGroup->where('pending', '>', 0)->count();

            $refund = $groupBookings->where('status', 4)->count();

            $dummy_bookings = $groupBookings->where('b_type', 'dummy')->count();

            $stock_group = $stocks->get($groupKey, []);

            $gridData[] = [
                'sno' => $sno++,
                'seg' => $seg,
                'model' => $model,
                'variant' => $variant,
                'clr' => $clr,

                'stock_total' => array_sum($stock_group),
                'stock_bikaner' => $stock_group['BIKANER'] ?? 0,
                'stock_churu' => $stock_group['CHURU'] ?? 0,
                'stock_khajuwala' => $stock_group['KHAJUWALA'] ?? 0,
                'stock_kolayat' => $stock_group['KOLAYAT'] ?? 0,
                'stock_lunkaransar' => $stock_group['LUNKARANSAR'] ?? 0,
                'stock_other' => array_sum(array_diff_key($stock_group, array_flip(['BIKANER', 'CHURU', 'KHAJUWALA', 'KOLAYAT', 'LUNKARANSAR']))),

                'total_bookings' => $total_bookings,

                'max_age' => $max_age_days ? ceil($max_age_days).' D' : '0 D',
                'age_gt_60d' => $age_gt_60,
                'dummy_bookings' => $dummy_bookings,
                'on_hold' => $on_hold,
                'refund' => $refund,

                'order_verification' => $verify,
                'order_creation' => $orders,
                'booking_creation' => $total_bookings,
                'customer_payment' => $payments,
                'customer_data' => $customer_data,
                'book_canc' => 0,

                'hot_enq_total' => 0,
                'hot_enq_bikaner' => 0,
                'hot_enq_churu' => 0,

                'finance_total' => 0,
                'finance_bikaner' => 0,
                'finance_churu' => 0,
                'finance_pending' => $payments,

                'exchange_total' => 0,
                'exchange_bikaner' => 0,
                'exchange_churu' => 0,
                'exchange_pending' => 0,

                'lie_orders' => 0,
            ];
        }

        $columns = [
            ['field' => 'sno', 'headerName' => 'S.No.', 'width' => 70, 'pinned' => 'left'],

            [
                'headerName' => 'Vehicle Info',
                'headerClass' => 'group-vehicle-info',
                'children' => [
                    ['field' => 'seg', 'headerName' => 'Segment', 'width' => 140],
                    ['field' => 'model', 'headerName' => 'Model', 'width' => 180],
                    ['field' => 'variant', 'headerName' => 'Variant', 'width' => 240],
                    ['field' => 'clr', 'headerName' => 'Color', 'width' => 140],
                ],
            ],

            [
                'headerName' => 'STOCK',
                'headerClass' => 'group-stock',
                'children' => [
                    ['field' => 'stock_total', 'headerName' => 'TOTAL', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'SELECTED BRANCH LOCATIONS',
                'headerClass' => 'group-selected-branch',
                'children' => [
                    ['field' => 'stock_bikaner', 'headerName' => 'BIKANER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_khajuwala', 'headerName' => 'KHAJUWALA', 'width' => 100, 'cellClass' => 'text-right'],
                    ['field' => 'stock_kolayat', 'headerName' => 'KOLAYAT', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_lunkaransar', 'headerName' => 'LUNKARANSAR', 'width' => 110, 'cellClass' => 'text-right'],
                    ['field' => 'stock_other', 'headerName' => 'OTHER', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'stock_churu', 'headerName' => 'CHURU', 'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'HOT ENQ',
                'headerClass' => 'group-hot-enq',
                'children' => [
                    ['field' => 'hot_enq_total',   'headerName' => 'TOTAL',    'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'hot_enq_bikaner', 'headerName' => 'BIKANER',  'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'hot_enq_churu',   'headerName' => 'CHURU',    'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'INT IN FINANCE',
                'headerClass' => 'group-finance',
                'children' => [
                    ['field' => 'finance_total',     'headerName' => 'TOTAL',         'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'finance_bikaner',   'headerName' => 'BIKANER',       'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'finance_churu',     'headerName' => 'CHURU',         'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'finance_pending',   'headerName' => 'PENDING ACTION', 'width' => 130, 'cellClass' => 'text-right fw-bold'],
                ],
            ],

            [
                'headerName' => 'INT IN EXCH',
                'headerClass' => 'group-exchange',
                'children' => [
                    ['field' => 'exchange_total',    'headerName' => 'TOTAL',         'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'exchange_bikaner',  'headerName' => 'BIKANER',       'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'exchange_churu',    'headerName' => 'CHURU',         'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'exchange_pending',  'headerName' => 'PENDING ACTION', 'width' => 130, 'cellClass' => 'text-right fw-bold'],
                ],
            ],

            [
                'headerName' => 'GLOBAL INFO',
                'headerClass' => 'group-global',
                'children' => [
                    ['field' => 'max_age', 'headerName' => 'MAX AGE', 'width' => 100],
                    ['field' => 'age_gt_60d', 'headerName' => 'AGE > 60D', 'width' => 110, 'cellClass' => 'text-right'],
                    ['field' => 'lie_orders', 'headerName' => 'LIE ORDERS', 'width' => 100, 'cellClass' => 'text-right'],
                    ['field' => 'dummy_bookings', 'headerName' => 'DUMMY BOOKINGS', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'on_hold', 'headerName' => 'ON HOLD', 'width' => 100, 'cellClass' => 'text-right'],
                    ['field' => 'refund', 'headerName' => 'REFUND', 'width' => 90, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'PENDING ACTIONS',
                'headerClass' => 'group-pending',
                'children' => [
                    ['field' => 'order_verification', 'headerName' => 'ORDER VERIFICATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'order_creation', 'headerName' => 'ORDER CREATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'booking_creation', 'headerName' => 'BOOKING CREATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'customer_payment', 'headerName' => 'CUSTOMER PAMENT', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'customer_data', 'headerName' => 'CUSTOMER DATA', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'book_canc', 'headerName' => 'BOOK CANC.', 'width' => 120, 'cellClass' => 'text-right'],
                ],
            ],
        ];

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        if (empty($gridData)) {
            session()->flash('info', 'No branch booking data found.');
        }

        return view('admin.booking.branch-booking', $this->data);
    }

    public function pendingActionsReport(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_REPORT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Pending Actions Report';

        $pendingCount = DB::table('xlr8_booking_master')->whereIn('status', [1, 6, 8])->count();
        if ($pendingCount === 0) {
            session()->flash('info', 'No pending bookings found (status 1,6,8 not present in table).');
        }

        $query = DB::table('xlr8_booking_master as b')
            ->leftJoin('xlr8_vehicle_master as vm', 'b.vehicle_oem_code', '=', 'vm.id')
            ->leftJoin('bmpl_enum_master as em', 'vm.segment_code', '=', 'em.id')
            ->leftJoin('xlr8_us_location as loc', 'b.location_code', '=', 'loc.id')
            ->whereIn('b.status', [1, 6, 8])
            ->selectRaw('
            COALESCE(em.value, "Unknown") as segment,
            COALESCE(vm.oem_model, "Unknown") as model,
            COALESCE(vm.oem_variant, "Unknown") as variant,
            COALESCE(vm.color, "Unknown") as color,
            COUNT(b.id) as total_bookings,
            SUM(CASE WHEN b.order = 1 THEN 1 ELSE 0 END) as order_verif,
            SUM(CASE WHEN b.order = 2 AND b.dms_so IS NULL THEN 1 ELSE 0 END) as order_creation,
            SUM(CASE WHEN b.pending > 0 THEN 1 ELSE 0 END) as customer_data,
            SUM(CASE WHEN b.status = 4 THEN 1 ELSE 0 END) as refund,
            SUM(CASE WHEN b.status = 4 THEN 1 ELSE 0 END) as book_canc,
            loc.abbr as branch_abbr
        ')
            ->groupByRaw('em.value, vm.oem_model, vm.oem_variant, vm.color, loc.abbr');

        $pendings = $query->get();

        $gridData = [];
        $grouped = $pendings->groupBy(function ($item) {
            return $item->segment.'|'.$item->model.'|'.$item->variant.'|'.$item->color;
        });

        $sno = 1;

        foreach ($grouped as $groupKey => $rows) {
            [$seg, $model, $variant, $clr] = explode('|', $groupKey);

            $total_bookings = $rows->sum('total_bookings');

            if ($total_bookings == 0) {
                continue;
            }

            $bkn = $rows->where('branch_abbr', 'BKN')->sum('total_bookings');
            $chr = $rows->where('branch_abbr', 'CHR')->sum('total_bookings');

            $gridData[] = [
                'sno' => $sno++,
                'segment' => $seg,
                'model' => $model,
                'variant' => $variant,
                'color' => $clr,
                'total_bookings' => (int) $total_bookings,
                'bkn' => (int) $bkn,
                'chr' => (int) $chr,
                'exchange' => 0,
                'finance' => 0,
                'order_verif' => (int) $rows->sum('order_verif'),
                'order_creation' => (int) $rows->sum('order_creation'),
                'booking_creation' => (int) $total_bookings,
                'customer_payment' => 0,
                'kyc_data' => (int) $rows->sum('customer_data'),
                'book_canc' => (int) $rows->sum('book_canc'),
                'refund' => (int) $rows->sum('refund'),
            ];
        }

        $columns = [
            ['field' => 'sno', 'headerName' => 'S.No.', 'width' => 70, 'pinned' => 'left'],

            [
                'headerName' => 'Vehicle Info',
                'headerClass' => 'group-vehicle-info',
                'children' => [
                    ['field' => 'segment', 'headerName' => 'Segment', 'width' => 140],
                    ['field' => 'model', 'headerName' => 'Model', 'width' => 180],
                    ['field' => 'variant', 'headerName' => 'Variant', 'width' => 240],
                    ['field' => 'color', 'headerName' => 'Color', 'width' => 140],
                ],
            ],

            [
                'headerName' => 'Bookings',
                'headerClass' => 'group-booking',
                'children' => [
                    ['field' => 'total_bookings', 'headerName' => 'Total', 'width' => 90, 'cellClass' => 'text-right'],
                    ['field' => 'bkn', 'headerName' => 'BKN', 'width' => 70, 'cellClass' => 'text-right'],
                    ['field' => 'chr', 'headerName' => 'CHR', 'width' => 70, 'cellClass' => 'text-right'],
                    ['field' => 'exchange', 'headerName' => 'EXCHANGE', 'width' => 110, 'cellClass' => 'text-right'],
                    ['field' => 'finance', 'headerName' => 'FINANCE', 'width' => 110, 'cellClass' => 'text-right'],
                ],
            ],

            [
                'headerName' => 'PENDING ACTIONS',
                'headerClass' => 'group-pending',
                'children' => [
                    ['field' => 'order_verif', 'headerName' => 'ORDER VERIFICATION', 'width' => 160, 'cellClass' => 'text-right'],
                    ['field' => 'order_creation', 'headerName' => 'ORDER CREATION', 'width' => 140, 'cellClass' => 'text-right'],
                    ['field' => 'booking_creation', 'headerName' => 'BOOKING CREATION', 'width' => 160, 'cellClass' => 'text-right'],
                    ['field' => 'customer_payment', 'headerName' => 'CUSTOMER PAYMENT', 'width' => 160, 'cellClass' => 'text-right'],
                    ['field' => 'kyc_data', 'headerName' => 'KYC DATA', 'width' => 110, 'cellClass' => 'text-right'],
                    ['field' => 'book_canc', 'headerName' => 'BOOK CANC.', 'width' => 110, 'cellClass' => 'text-right'],
                    ['field' => 'refund', 'headerName' => 'REFUND', 'width' => 110, 'cellClass' => 'text-right'],
                ],
            ],
        ];

        $gridConfig = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;

        return view('admin.booking.pending-actions', $this->data);
    }

    public function checkFieldPayment($id)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        if (! in_array($booking->col_type, [2, 3])) {
            return response()->json(['success' => true]);
        }

        $totalPaid = $booking->totalReceivedAmount();

        if ($booking->booking_amount > $totalPaid) {
            return response()->json([
                'success' => false,
                'total_paid' => (float) $totalPaid,
                'message' => 'Insufficient payment for field collection booking',
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getLocationsByBranch($branchCode = null)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $typeMap = [
            'sales' => 'is_sales_location',
            'workshop' => 'is_workshop',
            'parts' => 'is_parts_location',
            'stock' => 'is_stock_location',
            'office' => 'is_office_only',
            'mwh' => 'is_mwh',
            'lmmws' => 'is_lmmws',
        ];

        $query = Location::where('is_active', 1)
            ->select('id', 'code', 'name')
            ->orderBy('name');

        if (! empty($branchCode) && $branchCode !== '0') {
            $query->where('branch_code', $branchCode);
        }

        $type = request('type');
        if ($type && isset($typeMap[$type])) {
            $query->where($typeMap[$type], 1);
        }

        return response()->json($query->get());
    }

    public function CheckReceipt($rn)
    {
        if (! backpack_user()->can('SLS_BKNG_PAYMENT')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $count = OrgService::checkReceiptX($rn);

        return response()->json((int) $count > 0 ? 1 : 0);
    }

    public function preview($id)
    {
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        return view(
            'admin.booking.preview',
            compact('booking')
        );
    }

    // public function liveNotInvoiced()
    // {
    //     $this->crud->hasAccessOrFail('list');

    //     // Use the new transaction-list Blade
    //     $this->crud->setListView('admin.booking.transaction-list');

    //     $this->data['crud'] = $this->crud;
    //     $this->data['title'] = 'Transaction / OTF Listings';

    //     $query = $this->getBaseQuery();

    //     // Only status 1 (Live) and 8 (Pending)
    //     $query->whereIn('bookings.status', [1, 8]);

    //     $query->orderBy('bookings.id', 'desc');

    //     $paginatedBookings = $query->paginate(50);

    //     $gridData = $paginatedBookings->map(function ($booking, $index) use ($paginatedBookings) {
    //         // Start with basic mapped data
    //         $mapped = $this->mapBookingForGrid($booking);

    //         $mapped->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

    //         // ============================================================
    //         // 1. FETCH FINAL_DATA (OTF saved data)
    //         // ============================================================
    //         $finalData = [];
    //         if (!empty($booking->final_data)) {
    //             $finalData = json_decode($booking->final_data, true) ?? [];
    //             // Add logging for debugging
    //             Log::info('OTF FINAL DATA FETCHED', [
    //                 'booking_id' => $booking->id,
    //                 'final_data_keys' => array_keys($finalData),
    //                 'ex_showroom_price' => $finalData['ex_showroom_price'] ?? 'NOT FOUND',
    //                 'insurance_amount' => $finalData['insurance_amount'] ?? 'NOT FOUND',
    //             ]);
    //         } else {
    //             Log::warning('NO FINAL DATA FOR BOOKING', ['booking_id' => $booking->id]);
    //         }

    //         // ============================================================
    //         // 2. FETCH QUOTATION DATA (if available)
    //         // ============================================================
    //         $quotationData = [];
    //         if (!empty($booking->quotation_id)) {
    //             $quotation = Quotation::find($booking->quotation_id);
    //             if ($quotation && !empty($quotation->standard_data)) {
    //                 if (is_array($quotation->standard_data)) {
    //                     $quotationData = $quotation->standard_data;
    //                 } elseif (is_string($quotation->standard_data)) {
    //                     $quotationData = json_decode($quotation->standard_data, true) ?? [];
    //                 }
    //             }
    //         }

    //         // ============================================================
    //         // 3. MERGE DATA (final_data overrides quotation_data)
    //         // ============================================================
    //         $otfData = $quotationData;

    //         foreach ($finalData as $key => $value) {
    //             if ($value !== null && $value !== '') {
    //                 $otfData[$key] = $value;
    //             }
    //         }

    //         // ============================================================
    //         // 4. FETCH RELATED MODELS
    //         // ============================================================
    //         $rto = XlRto::where('bid', $booking->id)->first();
    //         $finance = XFinance::where('bid', $booking->id)->first();

    //         // ============================================================
    //         // 5. MAP ALL OTF FIELDS (COMPREHENSIVE FIX)
    //         // ============================================================

    //         // ----- BASIC INFO -----
    //         $mapped->branch_name = $booking->branch?->name ?? 'N/A';
    //         $mapped->location_name = $booking->location?->name ?? ($booking->location_other ?? 'N/A');
    //         $mapped->customer_name = $booking->name ?? 'N/A';
    //         $mapped->customer_address = $otfData['registration_address'] ?? $booking->address ?? 'N/A';
    //         $mapped->customer_tehsil = $otfData['customer_tehsil'] ?? 'N/A';
    //         $mapped->customer_district = $otfData['customer_district'] ?? 'N/A';
    //         $mapped->segment = $booking->segment_code ?? 'N/A';
    //         $mapped->model = $booking->model_code ?? 'N/A';
    //         $mapped->variant = $booking->variant_code ?? 'N/A';
    //         $mapped->chassis_no = $booking->chassis_no ?? 'N/A';

    //         // ----- VEHICLE DETAILS -----
    //         $mapped->body_type = $this->getBodyTypeLabel($rto->body_type ?? $otfData['body_type'] ?? null);
    //         $mapped->sale_type = $this->getSaleTypeLabel($rto->sale_type ?? $otfData['sale_type'] ?? null);
    //         $mapped->permit = $this->getPermitLabel($rto->permit ?? $otfData['permit'] ?? null);
    //         $mapped->registration_no_type = $this->getRegNoTypeLabel($rto->rgn_no_type ?? $otfData['registration_no_type'] ?? null);
    //         $mapped->registration_category = $otfData['registration_category'] ?? 'N/A';

    //         // ----- OTF / DMS -----
    //         $mapped->votf_no = $otfData['votf_no'] ?? 'N/A';
    //         $consultant = $this->getConsultantDetails($booking->consultant);
    //         $mapped->fsc_name = $consultant['name'] ?? 'N/A';
    //         $mapped->fsc_mile_id = $consultant['mile_id'] ?? 'N/A';
    //         $mapped->dms_no = $booking->dms_no ?? 'N/A';
    //         $mapped->dms_otf = $booking->dms_otf ?? 'N/A';
    //         $mapped->booking_no = $booking->id;

    //         // ----- PRICE DETAILS (CRITICAL FIX) -----
    //         $mapped->ex_showroom_price = $otfData['ex_showroom_price'] ?? 'N/A';
    //         $mapped->insurance_type = $this->getInsuranceTypeLabel($otfData['policy_type'] ?? null);
    //         $mapped->insurance_amount = $otfData['insurance_amount'] ?? 'N/A';
    //         $mapped->insurance_company = $otfData['insurance_company'] ?? 'N/A';
    //         $mapped->insurance_covers = $this->formatInsuranceCovers($otfData['insurance_covers'] ?? []);
    //         $mapped->registration_type = $this->getRegistrationTypeLabel($rto->rgn_type ?? $otfData['registration_type'] ?? null);
    //         $mapped->registration_amount = $otfData['registration_amount'] ?? 'N/A';
    //         $mapped->accessories = $this->getAccessoriesList($booking->accessories ?? $otfData['accessories'] ?? null);
    //         $mapped->accessories_amount = $otfData['accessories_amount'] ?? '0.00';
    //         $mapped->maxicare = $otfData['maxicare'] ?? 'N/A';
    //         $mapped->vltd_device = $otfData['vltd_device'] ?? 'N/A';
    //         $mapped->coating = $otfData['coating'] ?? 'N/A';
    //         $mapped->coating_price = $otfData['coating_price'] ?? 'N/A';
    //         $mapped->ppf = $otfData['ppf'] ?? 'N/A';
    //         $mapped->rto_yellow_tape = $otfData['rto_yellow_tape'] ?? 'N/A';
    //         $mapped->kazam = $otfData['kazam_charging_kit'] ?? 'N/A';
    //         $mapped->incidental_charges = $otfData['incidental_charges'] ?? 'N/A';
    //         $mapped->shield = $otfData['shield'] ?? 'N/A';
    //         $mapped->shield_price = $otfData['shield_price'] ?? 'N/A';
    //         $mapped->rsa = $otfData['rsa'] ?? 'N/A';
    //         $mapped->rsa_amount = $otfData['rsa_amount'] ?? 'N/A';
    //         $mapped->fastag = $otfData['fastag'] ?? 'N/A';
    //         $mapped->cod_charges = $otfData['cod_charges'] ?? 'N/A';
    //         $mapped->charger_swapping = $otfData['charger_swapping'] ?? 'N/A';
    //         $mapped->charger_swapping_amount = $otfData['charger_swapping_amount'] ?? 'N/A';
    //         $mapped->charger_swapping_option = $otfData['charger_swapping_option'] ?? 'N/A';
    //         $mapped->tcs = $otfData['tcs'] ?? 'N/A';
    //         $mapped->total_receivable = $otfData['total_receivable'] ?? 'N/A';

    //         // ----- DISCOUNTS -----
    //         $mapped->cash_scheme_oem = $otfData['cash_scheme_oem'] ?? 'N/A';
    //         $mapped->csd_discount = $otfData['csd_discount'] ?? 'N/A';
    //         $mapped->fame_subsidy = $otfData['fame_subsidy'] ?? 'N/A';
    //         $mapped->dealer_discount = $otfData['dealer_discount'] ?? 'N/A';
    //         $mapped->accessories_discount = $otfData['accessories_discount'] ?? 'N/A';
    //         $mapped->shield_scheme = $otfData['shield_scheme'] ?? 'N/A';
    //         $mapped->corporate_discount = $otfData['corporate_discount'] ?? 'N/A';
    //         $mapped->loyalty_bonus = $otfData['loyalty_bonus'] ?? 'N/A';
    //         $mapped->exchange_bonus = $otfData['exchange_bonus'] ?? 'N/A';
    //         $mapped->green_bonus = $otfData['green_bonus'] ?? 'N/A';
    //         $mapped->welcome_bonus = $otfData['welcome_bonus'] ?? 'N/A';
    //         $mapped->accessories_spl_disc = $otfData['accessories_spl_disc'] ?? 'N/A';
    //         $mapped->ceramic_discount = $otfData['ceramic_discount'] ?? 'N/A';
    //         $mapped->ppf_discount = $otfData['ppf_discount'] ?? 'N/A';
    //         $mapped->charger_swapping_discount = $otfData['charger_swapping_discount'] ?? 'N/A';
    //         $mapped->charger_swapping_discount_type = $otfData['charger_swapping_discount_type'] ?? 'N/A';
    //         $mapped->other_cash_discount = $otfData['other_cash_discount'] ?? 'N/A';
    //         $mapped->special_cash_discount = $otfData['special_cash_discount'] ?? 'N/A';
    //         $mapped->total_discount = $otfData['total_discount'] ?? 'N/A';
    //         $mapped->net_receivable = $otfData['net_receivable_summary'] ?? 'N/A';

    //         // ----- FINANCE -----

    //         $financierValue = $otfData['financier']
    //             ?? $booking->financier
    //             ?? $finance?->financier
    //             ?? null;

    //         if (is_numeric($financierValue)) {
    //             $mapped->financier = XlFinancier::find($financierValue)?->name ?? '';
    //         } else {
    //             $mapped->financier = $financierValue ?? '';
    //         }

    //         $mapped->financier_branch = $otfData['financier_branch'] ?? '';

    //         $mapped->loan_amount = $otfData['loan_amount']
    //             ?? $finance?->loan_amount
    //             ?? '';

    //         $mapped->file_charge = $otfData['file_charge']
    //             ?? $finance?->file_charge
    //             ?? '';

    //         $mapped->margin_money = $otfData['margin_money']
    //             ?? $finance?->margin
    //             ?? '';

    //         $mapped->financier_subvention = $otfData['financier_subvention']
    //             ?? $finance?->subvention_amount
    //             ?? '';

    //         $mapped->do_amount = $otfData['net_settlement_amount'] ?? 'N/A';

    //         $mapped->do_settlement_difference = $otfData['do_settlement_difference'] ?? '';

    //         $mapped->expected_balance = $otfData['expected_balance'] ?? '';

    //         $mapped->discount_through_jv = $otfData['discount_through_jv'] ?? '';

    //         $mapped->final_balance = $otfData['final_balance'] ?? '';

    //         // ----- DELIVERY -----
    //         $mapped->financier_verified = $otfData['financier_verified'] ?? 'N/A';
    //         $mapped->vehicle_delivery_on = $this->getDeliveryOptionLabel($otfData['vehicle_delivery_on'] ?? $finance?->instrument_type ?? null);
    //         $mapped->do_number_delivery = $otfData['do_number'] ?? $finance?->instrument_ref_no ?? 'N/A';
    //         $mapped->do_number_ta = $otfData['do_number_ta'] ?? 'N/A';
    //         $mapped->do_amount_ta = $otfData['do_amount_ta'] ?? 'N/A';
    //         $mapped->do_voucher_date = $otfData['do_voucher_date'] ?? 'N/A';

    //         // ----- OTHER -----
    //         $mapped->brokerage_amount = $otfData['brokerage_amount'] ?? 'N/A';
    //         $mapped->mm_support_receivable = $otfData['mm_support_receivable'] ?? 'N/A';
    //         $mapped->liquidation_scheme_receivable = $otfData['liquidation_scheme_receivable'] ?? 'N/A';
    //         $mapped->other_discount_receivable = $otfData['other_discount_receivable'] ?? 'N/A';
    //         $mapped->registration_service_charge_receivable = $otfData['registration_service_charge_receivable'] ?? 'N/A';
    //         $mapped->registration_service_charge_received = $otfData['registration_service_charge_received'] ?? 'N/A';
    //         $mapped->gst_slab = $otfData['gst_slab'] ?? 'N/A';
    //         $mapped->oem_model_code = $otfData['oem_model_code'] ?? 'N/A';

    //         // ----- RECEIPT DETAILS -----
    //         $receiptLogs = Bookingamount::where('bid', $booking->id)
    //             ->whereNull('deleted_at')
    //             ->orderBy('date')
    //             ->get();

    //         $receiptDisplay = [];
    //         $receiptTotal = 0;
    //         foreach ($receiptLogs as $receipt) {
    //             $receiptDisplay[] = "{$receipt->type_number} / " . \Carbon\Carbon::parse($receipt->date)->format('d-M-Y') . " / ₹" . number_format($receipt->amount, 2);
    //             $receiptTotal += (float) $receipt->amount;
    //         }
    //         $mapped->receipt_details = !empty($receiptDisplay) ? implode(' | ', $receiptDisplay) : 'N/A';
    //         $mapped->receipt_total = number_format($receiptTotal, 2);

    //         // ----- OTF Action Button -----
    //         $otfUrl = backpack_url("sales/booking/otf-form/{$booking->id}");

    //         $mapped->action = '
    //         <div class="d-flex justify-content-center gap-2">
    //             <a href="' . $otfUrl . '" class="btn btn-sm btn-success">OTF Form</a>
    //         </div>';

    //         return $mapped;
    //     })->values();

    //     // Build columns for Transaction listing
    //     $columns = $this->getTransactionGridColumns();

    //     $this->data['gridConfig'] = [
    //         'columns' => $columns,
    //         'data'    => $gridData,
    //     ];

    //     $this->data['pagination'] = [
    //         'total'       => $paginatedBookings->total(),
    //         'perPage'     => $paginatedBookings->perPage(),
    //         'currentPage' => $paginatedBookings->currentPage(),
    //         'lastPage'    => $paginatedBookings->lastPage(),
    //     ];

    //     return view('admin.booking.transaction-list', $this->data);
    // }

    public function liveNotInvoiced()
    {
        if (! backpack_user()->can('SLS_BKNG_INVOICE')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('list');

        $this->crud->setListView('admin.booking.transaction-list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Transaction / OTF Listings';

        $query = $this->getBaseQuery();
        $query->whereIn('bookings.status', [1, 8]);
        $query->orderBy('bookings.id', 'desc');

        $paginatedBookings = $query->paginate(50);
        $gridLookups = $this->preloadGridLookups($paginatedBookings->getCollection());

        $gridData = $paginatedBookings->map(function ($booking, $index) use ($paginatedBookings, $gridLookups) {
            $mapped = $this->mapBookingForGrid($booking, $gridLookups);
            $mapped->serial_no = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;

            // Fetch OTF Data
            $finalData = [];
            if (! empty($booking->final_data)) {
                $finalData = json_decode($booking->final_data, true) ?? [];
            }

            $quotationData = [];
            if (! empty($booking->quotation_id)) {
                $quotation = Quotation::find($booking->quotation_id);
                if ($quotation && ! empty($quotation->standard_data)) {
                    if (is_array($quotation->standard_data)) {
                        $quotationData = $quotation->standard_data;
                    } elseif (is_string($quotation->standard_data)) {
                        $quotationData = json_decode($quotation->standard_data, true) ?? [];
                    }
                }
            }

            $otfData = $quotationData;
            foreach ($finalData as $key => $value) {
                if ($value !== null && $value !== '') {
                    $otfData[$key] = $value;
                }
            }

            $rto = XlRto::where('bid', $booking->id)->first();
            $finance = XFinance::where('bid', $booking->id)->first();
            $insurance = XlInsurance::where('bid', $booking->id)->first();
            $consultant = $this->getConsultantDetails($booking->consultant);

            // ----- BASIC INFO -----
            $mapped->branch_name = $booking->branch?->name ?? 'N/A';
            $mapped->location_name = $booking->location?->name ?? ($booking->location_other ?? 'N/A');
            $mapped->customer_name = $booking->name ?? 'N/A';
            $mapped->customer_address = $otfData['registration_address'] ?? $booking->address ?? 'N/A';
            $mapped->customer_tehsil = $otfData['customer_tehsil'] ?? 'N/A';
            $mapped->customer_district = $otfData['customer_district'] ?? 'N/A';
            $mapped->segment = $booking->segment_code ?? 'N/A';
            $mapped->model = $booking->model_code ?? 'N/A';
            $mapped->variant = $booking->variant_code ?? 'N/A';
            $mapped->chassis_no = $booking->chassis_no ?? 'N/A';
            $mapped->mobile = $booking->mobile ?? 'N/A';
            $mapped->gstn = $booking->gstn ?? 'N/A';
            $mapped->pan_no = $booking->pan_no ?? 'N/A';
            $mapped->adhar_no = $booking->adhar_no ?? 'N/A';

            // ----- VEHICLE DETAILS -----
            $mapped->body_type = $this->getBodyTypeLabel($rto->body_type ?? $otfData['body_type'] ?? null);
            $mapped->sale_type = $this->getSaleTypeLabel($rto->sale_type ?? $otfData['sale_type'] ?? null);
            $mapped->permit = $this->getPermitLabel($rto->permit ?? $otfData['permit'] ?? null);
            $mapped->registration_no_type = $this->getRegNoTypeLabel($rto->rgn_no_type ?? $otfData['registration_no_type'] ?? null);
            $mapped->registration_category = $otfData['registration_category'] ?? 'N/A';
            $mapped->registration_type = $this->getRegistrationTypeLabel($rto->rgn_type ?? $otfData['registration_type'] ?? null);
            $mapped->color = $booking->color_code ?? 'N/A';

            // ----- OTF / DMS -----
            $mapped->votf_no = $otfData['votf_no'] ?? 'N/A';
            $mapped->fsc_name = $consultant['name'] ?? 'N/A';
            $mapped->fsc_mile_id = $consultant['mile_id'] ?? 'N/A';
            $mapped->dms_no = $booking->dms_no ?? 'N/A';
            $mapped->dms_otf = $booking->dms_otf ?? 'N/A';
            $mapped->booking_no = $booking->id;

            // ----- X8 Fields -----
            $mapped->x8_enq_date = $booking->created_at ? Carbon::parse($booking->created_at)->format('d-M-Y') : 'N/A';
            $mapped->x8_booking_no = $booking->id;
            $mapped->x8_booking_date = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('d-M-Y') : 'N/A';

            // ----- Invoice Type -----
            $mapped->invoice_type = $booking->dealer_status == 2 ? 'Dealer Invoice' : 'Normal Invoice';
            $mapped->inv_no = $booking->inv_no ?? 'N/A';
            $mapped->inv_date = $booking->inv_date ? Carbon::parse($booking->inv_date)->format('d-M-Y') : 'N/A';

            // ----- Customer Category -----
            $mapped->customer_category = $booking->b_cat ?? 'N/A';
            $mapped->retail_category = $otfData['retail_category'] ?? 'N/A';

            // ----- SC Branch & Location -----
            $mapped->sc_branch = $consultant['branch_name'] ?? 'N/A';
            $mapped->sc_location = $consultant['location_name'] ?? 'N/A';

            // ----- PMS SC -----
            $pmsConsultant = $this->getConsultantDetails($booking->consultant);
            $mapped->pms_sc_name = $pmsConsultant['name'] ?? 'N/A';
            $mapped->pms_sc_mile_id = $pmsConsultant['mile_id'] ?? 'N/A';

            // ----- DSA -----
            $mapped->dsa_retail = ! empty($otfData['dsa_id'] ?? $booking->dsa_id ?? '') ? 'Yes' : 'No';
            $dsaName = $booking->dsa_id ? (XL_DSA_MASTER::find($booking->dsa_id)?->name ?? 'N/A') : 'N/A';
            $mapped->dsa_name = $dsaName;
            $mapped->dsa_location = $otfData['dsa_location'] ?? 'N/A';
            $mapped->exchange = $otfData['exchange'] ?? $booking->buyer_type ?? 'NA';

            // ----- In-House -----
            $mapped->in_house_rto = ($otfData['in_house_rto'] ?? $rto?->in_house_rto ?? 0) == 1 ? 'Yes' : 'No';
            $mapped->in_house_insurance = $insurance ? 'Yes' : 'No';

            // ----- PRICE DETAILS -----
            $mapped->ex_showroom_price = $otfData['ex_showroom_price'] ?? 'N/A';
            $mapped->insurance_amount = $otfData['insurance_amount'] ?? 'N/A';
            $mapped->registration_amount = $otfData['registration_amount'] ?? 'N/A';
            $mapped->accessories_amount = $otfData['accessories_amount'] ?? '0.00';
            $mapped->maxicare = $otfData['maxicare'] ?? 'N/A';
            $mapped->vltd_device = $otfData['vltd_device'] ?? 'N/A';
            $mapped->coating_price = $otfData['coating_price'] ?? 'N/A';
            $mapped->ppf = $otfData['ppf'] ?? 'N/A';
            $mapped->rto_yellow_tape = $otfData['rto_yellow_tape'] ?? 'N/A';
            $mapped->total_receivable = $otfData['total_receivable'] ?? 'N/A';

            // ----- DISCOUNTS -----
            $disc1 = 0;
            $disc2 = 0;

            $disc1 += (float) ($otfData['cash_scheme_oem'] ?? 0);
            $disc1 += (float) ($otfData['csd_discount'] ?? 0);
            $disc1 += (float) ($otfData['fame_subsidy'] ?? 0);
            $disc1 += (float) ($otfData['dealer_discount'] ?? 0);
            $disc1 += (float) ($otfData['accessories_discount'] ?? 0);
            $disc1 += (float) ($otfData['shield_scheme'] ?? 0);
            $disc1 += (float) ($otfData['corporate_discount'] ?? 0);

            $disc2 += (float) ($otfData['loyalty_bonus'] ?? 0);
            $disc2 += (float) ($otfData['exchange_bonus'] ?? 0);
            $disc2 += (float) ($otfData['green_bonus'] ?? 0);
            $disc2 += (float) ($otfData['welcome_bonus'] ?? 0);
            $disc2 += (float) ($otfData['accessories_spl_disc'] ?? 0);
            $disc2 += (float) ($otfData['ceramic_discount'] ?? 0);
            $disc2 += (float) ($otfData['ppf_discount'] ?? 0);
            $disc2 += (float) ($otfData['charger_swapping_discount'] ?? 0);
            $disc2 += (float) ($otfData['other_cash_discount'] ?? 0);
            $disc2 += (float) ($otfData['special_cash_discount'] ?? 0);

            $mapped->disc_1 = $disc1 > 0 ? number_format($disc1, 2) : 'N/A';
            $mapped->disc_2 = $disc2 > 0 ? number_format($disc2, 2) : 'N/A';
            $mapped->total_discount = $otfData['total_discount'] ?? 'N/A';
            $mapped->net_receivable = $otfData['net_receivable_summary'] ?? 'N/A';

            // ----- FINANCE -----
            $financierValue = $otfData['financier'] ?? $booking->financier ?? $finance?->financier ?? null;
            if (is_numeric($financierValue)) {
                $mapped->financier = XlFinancier::find($financierValue)?->name ?? '';
            } else {
                $mapped->financier = $financierValue ?? '';
            }
            $mapped->loan_amount = $otfData['loan_amount'] ?? $finance?->loan_amount ?? '';
            $mapped->file_charge = $otfData['file_charge'] ?? $finance?->file_charge ?? '';
            $mapped->margin_money = $otfData['margin_money'] ?? $finance?->margin ?? '';
            $mapped->financier_subvention = $otfData['financier_subvention'] ?? $finance?->subvention_amount ?? '';
            $mapped->do_amount = $otfData['net_settlement_amount'] ?? 'N/A';

            // ----- RECEIPTS -----
            $receiptLogs = Bookingamount::where('bid', $booking->id)
                ->whereNull('deleted_at')
                ->orderBy('date')
                ->get();

            $receiptDisplay = [];
            $receiptTotal = 0;
            foreach ($receiptLogs as $receipt) {
                $receiptDisplay[] = "{$receipt->type_number} / ".Carbon::parse($receipt->date)->format('d-M-Y').' / ₹'.number_format($receipt->amount, 2);
                $receiptTotal += (float) $receipt->amount;
            }
            $mapped->receipt_details = ! empty($receiptDisplay) ? implode(' | ', $receiptDisplay) : 'N/A';
            $mapped->receipt_total = number_format($receiptTotal, 2);

            $receipts = $receiptLogs->values();
            $mapped->receipt_no_1 = isset($receipts[0]) ? $receipts[0]->reciept : 'N/A';
            $mapped->receipt_date_1 = isset($receipts[0]) ? Carbon::parse($receipts[0]->date)->format('d-M-Y') : 'N/A';
            $mapped->receipt_amount_1 = isset($receipts[0]) ? number_format($receipts[0]->amount, 2) : 'N/A';
            $mapped->receipt_no_2 = isset($receipts[1]) ? $receipts[1]->reciept : 'N/A';
            $mapped->receipt_date_2 = isset($receipts[1]) ? Carbon::parse($receipts[1]->date)->format('d-M-Y') : 'N/A';
            $mapped->receipt_amount_3 = isset($receipts[2]) ? number_format($receipts[2]->amount, 2) : 'N/A';

            // ----- BALANCE -----
            $mapped->expected_balance = $otfData['expected_balance'] ?? '';
            $mapped->do_settlement_difference = $otfData['do_settlement_difference'] ?? '';
            $mapped->discount_through_jv = $otfData['discount_through_jv'] ?? '';
            $mapped->final_balance = $otfData['final_balance'] ?? '';

            // ----- DELIVERY -----
            $mapped->do_number_delivery = $otfData['do_number'] ?? $finance?->instrument_ref_no ?? 'N/A';
            $mapped->do_number_ta = $otfData['do_number_ta'] ?? 'N/A';
            $mapped->do_amount_ta = $otfData['do_amount_ta'] ?? 'N/A';

            // ----- OTHER RECEIVABLES -----
            $mapped->brokerage_amount = $otfData['brokerage_amount'] ?? 'N/A';
            $mapped->other_discount_receivable = $otfData['other_discount_receivable'] ?? 'N/A';
            $mapped->mm_support_receivable = $otfData['mm_support_receivable'] ?? 'N/A';
            $mapped->liquidation_scheme_receivable = $otfData['liquidation_scheme_receivable'] ?? 'N/A';

            // ----- OTF Action Button -----
            $otfUrl = backpack_url("sales/booking/otf-form/{$booking->id}");
            $mapped->action = '<div class="d-flex justify-content-center gap-2">
                <a href="'.$otfUrl.'" class="btn btn-sm btn-success">OTF Form</a>
            </div>';

            return $mapped;
        })->values();

        $columns = $this->getTransactionGridColumns();

        $this->data['gridConfig'] = [
            'columns' => $columns,
            'data' => $gridData,
        ];

        $this->data['pagination'] = [
            'total' => $paginatedBookings->total(),
            'perPage' => $paginatedBookings->perPage(),
            'currentPage' => $paginatedBookings->currentPage(),
            'lastPage' => $paginatedBookings->lastPage(),
        ];

        return view('admin.booking.transaction-list', $this->data);
    }

    public function otfProcess($id)
    {
        if (! backpack_user()->can('SLS_BKNG_OTF')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        // ==========================================
        // MANDATORY QUOTATION CHECK
        // ==========================================

        if (empty($booking->quotation_id)) {

            return response()->json([
                'status' => 'quotation_missing',
                'message' => 'This booking has no quotation.',
                'booking_id' => $booking->id,
                'enquiry_no' => $booking->enq_no,
                'quotation_url' => route('sales.quotation.create', [
                    'id' => $booking->enq_no,
                    'booking_id' => $booking->id,
                ]),
            ]);
        }

        // ==========================================
        // QUOTATION
        // ==========================================

        $quotation = Quotation::find($booking->quotation_id);

        if (! $quotation) {
            return response()->json([
                'status' => 'quotation_missing',
                'message' => 'Quotation linked to this booking was not found.',
                'booking_id' => $booking->id,
                'enquiry_no' => $booking->enq_no,
                'quotation_url' => route('sales.quotation.create', [
                    'id' => $booking->enq_no,
                    'booking_id' => $booking->id,
                ]),
            ]);
        }

        $quotationData = $quotation?->standard_data ?? [];

        $finalData = [];

        $otfData = array_merge(
            $quotationData,
            $finalData
        );

        if (! empty($otfData['net_settlement_amount'])) {
            $otfData['net_settlement_amount'] = $otfData['net_settlement_amount'];
        } else {
            // Calculate it from available data if not saved
            $loanAmount = (float) ($otfData['loan_amount'] ?? 0);
            $fileCharge = (float) ($otfData['file_charge'] ?? 0);
            $marginMoney = (float) ($otfData['margin_money'] ?? 0);
            $financierSubvention = (float) ($otfData['financier_subvention'] ?? 0);

            $otfData['net_settlement_amount'] = number_format(
                $loanAmount - $fileCharge + $marginMoney - $financierSubvention,
                2
            );
        }

        // Debug log
        \Log::info('OTF Quotation fetch', [
            'booking_id' => $booking->id,
            'quotation_id' => $booking->quotation_id,
            'enq_no' => $booking->enq_no,
            'found' => $quotation?->id,
        ]);

        $salesconsultants = OrgService::getUsers(
            desigCode: 'SLS_CONS'
        );

        $salesconsultants = array_map(function ($consultant) {
            $consultant['branch_name'] = OrgService::branchName(
                $consultant['primary_branch_code'] ?? ''
            );

            $consultant['location_name'] = OrgService::locationName(
                $consultant['primary_loc_code'] ?? ''
            );

            // Mile ID should be the employee code
            $consultant['mile_id'] = $consultant['employee_code'] ?? '';

            return $consultant;
        }, $salesconsultants);

        $dsaList = XL_DSA_MASTER::orderBy('name')
            ->get(['id', 'name', 'dlocation']);
        $finance = XFinance::where('bid', $id)->first();

        \Log::info('OTF FINANCE DEBUG', [
            'booking_id' => $id,
            'finance_id' => $finance?->id,
            'subvention_amount' => $finance?->subvention_amount,
        ]);

        $insurance = XlInsurance::where('bid', $id)->first();

        $rto = XlRto::where('bid', $id)->first();

        $quotationData = $quotation?->standard_data ?? [];

        $finalData = [];

        if (! empty($booking->final_data)) {
            $finalData = json_decode($booking->final_data, true) ?? [];
        }

        \Log::info('OTF Quotation', [
            'booking_id' => $booking->id,
            'booking_quoteid' => $booking->quotation_id,
            'quotation_found' => $quotation?->id,
            'quotation_no' => $quotation?->quotation_no,

            'coating' => $quotationData['coating'] ?? null,
            'coating_price' => $quotationData['coating_price'] ?? null,
            'ceramic_discount' => $quotationData['ceramic_discount'] ?? null,

            'final_coating_price' => $finalData['coating_price'] ?? null,
            'final_ceramic_discount' => $finalData['ceramic_discount'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Final OTF Data
        |--------------------------------------------------------------------------
        | Quotation data + Saved OTF data
        | final_data will override quotation values if already saved.
        */
        $otfData = array_merge(
            $quotationData,
            $finalData
        );

        if (! empty($quotationData['insurance_covers'])) {
            $otfData['insurance_covers'] = $quotationData['insurance_covers'];
        }

        \Log::info('OTF Data - Insurance Covers', [
            'insurance_covers' => $otfData['insurance_covers'] ?? 'NOT SET',
            'insurance_amount' => $otfData['insurance_amount'] ?? 'NOT SET',
            'policy_type' => $otfData['policy_type'] ?? 'NOT SET',
            'quotationData_insurance_covers' => $quotationData['insurance_covers'] ?? 'NOT SET',
            'finalData_insurance_covers' => $finalData['insurance_covers'] ?? 'NOT SET',
        ]);

        // ================= GROUP A SELECTED =================
        $groupASelected = 'cash_scheme_oem';

        if (! empty($otfData['csd_discount'])) {
            $groupASelected = 'csd_discount';
        }

        if (! empty($otfData['fame_subsidy'])) {
            $groupASelected = 'fame_subsidy';
        }

        // ================= GROUP B SELECTED =================
        $groupBSelected = 'corporate_discount';

        if (! empty($otfData['loyalty_bonus'])) {
            $groupBSelected = 'loyalty_bonus';
        }

        // ================= GROUP C SELECTED =================
        $groupCSelected = 'exchange_bonus';

        if (! empty($otfData['green_bonus'])) {
            $groupCSelected = 'green_bonus';
        }

        if (! empty($otfData['welcome_bonus'])) {
            $groupCSelected = 'welcome_bonus';
        }

        $selectedExShowroomPrice = $otfData['ex_showroom_price'] ?? null;

        $selectedPolicyType = $insurance?->policy_type
            ?? ($otfData['policy_type'] ?? null);

        $selectedRegistrationType = $rto?->rgn_type
            ?? ($otfData['registration_type'] ?? null);

        $dsa = null;

        if (! empty($booking->dsa_id)) {
            $dsa = XL_DSA_MASTER::find($booking->dsa_id);
        }

        $segment = Segment::where(
            'code',
            $booking->segment_code
        )->first();

        $model = VehicleModel::where(
            'code',
            $booking->model_code
        )->first();

        $variant = Variant::with([
            'permit',
            'fuelType',
            'bodyType',
            'bodyMake',
        ])
            ->where(
                'code',
                $booking->variant_code
            )
            ->first();

        $color = Color::where(
            'code',
            $booking->color_code
        )->first();

        $accessories = 'N/A';

        if (! empty($booking->accessories)) {

            $accIds = array_filter(array_map('trim', explode(',', $booking->accessories)));

            $accNames = [];

            foreach ($accIds as $accId) {

                $accessory = DB::table('xlr8_vehicle_accessories')
                    ->where('part_no', trim($accId))
                    ->first();

                if ($accessory) {

                    $accNames[] = $accessory->item.' (₹'.number_format((float) $accessory->ndp, 2).')';
                }
            }

            if (! empty($accNames)) {
                $accessories = ''.implode(', ', $accNames);
            }
        }
        $selectedAccessories = [];

        if (! empty($booking->accessories)) {
            $selectedAccessories = array_filter(
                array_map('trim', explode(',', $booking->accessories))
            );
        }

        $accessoryList = DB::table('xlr8_vehicle_accessories')
            ->orderBy('item')
            ->get();

        $permit_map = OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(function ($permit, $index) {
                return [
                    (string) ($index + 1) => $permit->value,
                ];
            })
            ->toArray();

        $reg_no_type_map = [
            '1' => 'Regular',
            '2' => 'BH Series',
            '3' => 'Special Number',
        ];

        $registration_category_map = [
            '1' => 'Exempted',
            '2' => 'Standard',
        ];
        $customer_category_map = [
            '1' => 'Individual',
            '2' => 'Corporate',
            '3' => 'CSD - CPC',
        ];

        $body_type_map = [
            '1' => 'Complete',
            '2' => 'CBC',
        ];

        $sale_type_map = [
            '1' => 'Within State',
            '2' => 'Outside State',
        ];

        $insurance_type_map = [
            '1' => 'Standard',
            '2' => 'Nil Dep',
            '3' => 'Base (Nil Dep + Consumables)',
            '4' => 'Higher (Nil Dep + Consumables + Add Ons)',
        ];
        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',

        ];

        $deliveryOptions = [
            1 => 'Payment',
            2 => 'DO',
            3 => 'Sanction Letter',
            4 => 'Mail',
            5 => 'Whatsapp',
        ];

        $financierName = XlFinancier::find($booking->financier)?->name ?? 'N/A';
        $receiptLogs = Bookingamount::where('bid', $booking->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->get();

        $receiptTotal = $receiptLogs->sum(function ($receipt) {
            return (float) $receipt->amount;
        });

        $chassisImage = $booking->getFirstMediaUrl('chassis_image') ?: '';

        $enquiry = null;

        if (! empty($booking->enq_no)) {
            $enquiry = Enquiry::find($booking->enq_no);
        }

        $taStatement = null;

        if (
            $finance &&
            $finance->instrument_type == 2 &&
            ! empty($finance->instrument_ref_no)
        ) {
            $taStatement = DB::table('xlr8_financer_statement')
                ->where('do_no', trim($finance->instrument_ref_no))
                ->first();
        }

        // ================= INSURANCE PRINT DATA =================
        $insurancePrintData = [];
        $insuranceCovers = $otfData['insurance_covers'] ?? [];

        if (! empty($insuranceCovers) && is_array($insuranceCovers)) {
            foreach ($insuranceCovers as $cover) {
                if (is_string($cover)) {
                    $price = 0;
                    $name = $cover;
                    if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                        $price = floatval(str_replace(',', '', $matches[1]));
                        $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                    }
                    $insurancePrintData[] = ['name' => $name, 'price' => $price];
                } elseif (is_array($cover)) {
                    $insurancePrintData[] = [
                        'name' => $cover['name'] ?? '',
                        'price' => floatval($cover['price'] ?? 0),
                    ];
                }
            }
        }

        // ✅ FIX: Agar insurance_amount hai aur insurance_covers empty hai
        if (empty($insurancePrintData)) {
            $insAmount = floatval($otfData['insurance_amount'] ?? 0);
            if ($insAmount > 0) {
                $policyLabel = $insurance_type_map[$otfData['policy_type'] ?? ''] ?? '';
                $insurancePrintData[] = [
                    'name' => $policyLabel ?: 'Insurance Amount',
                    'price' => $insAmount,
                ];
            }
        }

        // ================= ACCESSORIES PRINT DATA =================
        $accessoriesPrintData = [];
        $accessories = $otfData['accessories'] ?? [];

        if (! empty($accessories) && is_array($accessories)) {
            foreach ($accessories as $accCode) {
                $accessory = DB::table('xlr8_vehicle_accessories')
                    ->where('part_no', trim($accCode))
                    ->first();
                if ($accessory) {
                    $accessoriesPrintData[] = [
                        'name' => $accessory->item,
                        'price' => (float) $accessory->ndp,
                    ];
                }
            }
        }

        // ✅ FIX: Agar accessories_amount hai aur accessories empty hai
        if (empty($accessoriesPrintData)) {
            $accAmount = floatval($otfData['accessories_amount'] ?? 0);
            if ($accAmount > 0) {
                $accessoriesPrintData[] = [
                    'name' => 'Accessories',
                    'price' => $accAmount,
                ];
            }
        }

        return view(
            'admin.booking.otf-form',
            compact(
                'booking',
                'finance',
                'salesconsultants',
                'taStatement',
                'enquiry',
                'quotationData',
                'finalData',
                'otfData',
                'insurance',
                'rto',
                'dsa',
                'segment',
                'model',
                'variant',
                'color',
                'accessories',
                'permit_map',
                'sale_type_map',
                'reg_no_type_map',
                'registration_category_map',
                'registration_type_map',
                'customer_category_map',
                'body_type_map',
                'insurance_type_map',
                'salesconsultants',
                'dsa',
                'dsaList',
                'accessoryList',
                'selectedAccessories',
                'financierName',
                'selectedPolicyType',
                'selectedRegistrationType',
                'selectedExShowroomPrice',
                'receiptLogs',
                'receiptTotal',
                'chassisImage',
                'groupASelected',
                'groupBSelected',
                'groupCSelected',
                'deliveryOptions',
                'insurancePrintData',
                'accessoriesPrintData'
            )
        );
    }

    public function getDoAmount(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_DO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $doNo = $request->input('do_no');

        if (empty($doNo)) {
            return response()->json(['amount' => '', 'date' => '']);
        }

        // Search for exact match in do_no column
        $statement = DB::table('xlr8_financer_statement')
            ->where('do_no', $doNo)
            ->where('trans_type', 'C') // Credit transactions only
            ->first();

        if ($statement) {
            return response()->json([
                'amount' => number_format($statement->credit_amount ?? 0, 2),
                'date' => $statement->trans_date ? Carbon::parse($statement->trans_date)->format('d-M-Y') : '',
            ]);
        }

        return response()->json(['amount' => '', 'date' => '']);
    }

    public function getTAStatement(Request $request)
    {
        if (! backpack_user()->can('SLS_BKNG_DO')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $statement = DB::table('xlr8_financer_statement')
            ->where('do_no', trim($request->do_no))
            ->whereNull('deleted_at')
            ->first();

        return response()->json([
            'do_no' => $statement->do_no ?? '',
            'credit_amount' => $statement->credit_amount ?? '',
            'trans_date' => ! empty($statement->trans_date)
                ? Carbon::parse($statement->trans_date)->format('Y-m-d')
                : '',
        ]);
    }

    public function otfSave(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_BKNG_OTF')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        \Log::info('🔥🔥🔥 OTF SAVE CALLED 🔥🔥🔥', [
            'booking_id' => $id,
            'method' => $request->method(),
            'user_id' => backpack_auth()->id(),
            'all_inputs' => $request->except(['_token', 'chassis_image']),
        ]);

        $booking = Booking::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | 1. SAVE CHASSIS IMAGE
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('chassis_image')) {
            $booking->addMedia($request->file('chassis_image'))
                ->toMediaCollection('chassis_image');
        }

        /*
        |--------------------------------------------------------------------------
        | 2. GET EXISTING FINAL JSON
        |--------------------------------------------------------------------------
        */
        $existingFinalData = [];

        if (! empty($booking->final_data)) {
            $decoded = json_decode($booking->final_data, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $existingFinalData = $decoded;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. GET QUOTATION DATA
        |--------------------------------------------------------------------------
        | Quotation data will act as initial/default data.
        |--------------------------------------------------------------------------
        */
        $quotationData = [];

        if (! empty($booking->quotation_id)) {

            $quotation = Quotation::find($booking->quotation_id);

            if ($quotation && ! empty($quotation->standard_data)) {

                if (is_array($quotation->standard_data)) {
                    $quotationData = $quotation->standard_data;
                } elseif (is_string($quotation->standard_data)) {

                    $decodedQuotation = json_decode(
                        $quotation->standard_data,
                        true
                    );

                    if (
                        json_last_error() === JSON_ERROR_NONE &&
                        is_array($decodedQuotation)
                    ) {
                        $quotationData = $decodedQuotation;
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. GET CURRENT FORM DATA
        |--------------------------------------------------------------------------
        */
        $formData = $request->except([
            '_token',
            '_method',
            'chassis_image',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. CONVERT REQUEST DATA TO NORMAL ARRAY
        |--------------------------------------------------------------------------
        */
        $formData = collect($formData)->map(function ($value) {

            // Laravel arrays remain arrays
            if (is_array($value)) {
                return array_values($value);
            }

            return $value;
        })->toArray();

        /*
        |--------------------------------------------------------------------------
        | 6. MERGE ALL DATA
        |--------------------------------------------------------------------------
        |
        | Priority:
        |
        | Quotation Data
        |      ↓
        | Existing Saved OTF Data
        |      ↓
        | Current Form Data  <-- HIGHEST PRIORITY
        |
        |--------------------------------------------------------------------------
        */

        $finalJsonData = array_replace_recursive(
            $quotationData,
            $existingFinalData,
            $formData
        );

        // ✅ FIX: Add net_settlement_amount to finalJsonData
        if ($request->has('net_settlement_amount')) {
            $finalJsonData['net_settlement_amount'] = $request->net_settlement_amount;
        }

        // ✅ FIX: Also add do_amount_ta if exists
        if ($request->has('do_amount_ta')) {
            $finalJsonData['do_amount_ta'] = $request->do_amount_ta;
        }

        // Add this after the merge logic in otfSave()
        if ($request->has('net_settlement_amount')) {
            $finalJsonData['net_settlement_amount'] = $request->net_settlement_amount;
        }

        /*
        |--------------------------------------------------------------------------
        | 7. IMPORTANT FIELDS
        |--------------------------------------------------------------------------
        | Make sure important price/detail fields are explicitly retained.
        |--------------------------------------------------------------------------
        */

        $importantFields = [
            'ex_showroom_price',
            'insurance_amount',
            'insurance_covers',
            'policy_type',

            'registration_type',
            'registration_no_type',
            'registration_category',

            'accessories',
            'accessories_amount',

            'coating',
            'coating_price',
            'ceramic_discount',

            'shield',
            'shield_price',

            'rsa',
            'rsa_amount',

            'charger_swapping',
            'charger_swapping_amount',
            'charger_swapping_discount',
            'charger_swapping_discount_type',

            'tcs',
            'total_receivable',
            'total_discount',
            'net_receivable',

            'cash_scheme_oem',
            'csd_discount',
            'fame_subsidy',

            'corporate_discount',
            'loyalty_bonus',

            'exchange_bonus',
            'green_bonus',
            'welcome_bonus',
        ];

        foreach ($importantFields as $field) {

            /*
            * Agar request mein field nahi aayi because it was disabled,
            * existing value ko preserve karo.
            */
            if (
                ! $request->has($field) &&
                array_key_exists($field, $existingFinalData)
            ) {
                $finalJsonData[$field] = $existingFinalData[$field];
            }

            /*
            * Agar existing mein bhi nahi hai but quotation mein hai,
            * quotation value preserve karo.
            */ elseif (
                ! $request->has($field) &&
                ! array_key_exists($field, $existingFinalData) &&
                array_key_exists($field, $quotationData)
            ) {
                $finalJsonData[$field] = $quotationData[$field];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 8. ACCESSORIES
        |--------------------------------------------------------------------------
        */
        if ($request->has('accessories')) {

            $accessories = $request->input('accessories');

            if (is_array($accessories)) {

                $accessories = array_values(
                    array_filter(
                        array_map('trim', $accessories),
                        fn ($value) => $value !== ''
                    )
                );

                $finalJsonData['accessories'] = $accessories;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 9. INSURANCE COVERS
        |--------------------------------------------------------------------------
        */
        if ($request->has('insurance_covers')) {

            $insuranceCovers = $request->input('insurance_covers');

            if (is_array($insuranceCovers)) {
                $finalJsonData['insurance_covers'] = array_values(
                    $insuranceCovers
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 10. SAVE FINAL JSON
        |--------------------------------------------------------------------------
        */
        $booking->final_data = json_encode(
            $finalJsonData,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRETTY_PRINT
        );

        /*
        |--------------------------------------------------------------------------
        | 11. UPDATE BOOKING MAIN FIELDS
        |--------------------------------------------------------------------------
        */

        $booking->gstn = strtoupper(trim($request->gstn ?? $booking->gstn ?? ''));
        $booking->pan_no = strtoupper(trim($request->pan_no ?? $booking->pan_no ?? ''));

        $booking->adhar_no = preg_replace(
            '/\D/',
            '',
            $request->adhar_no ?? $booking->adhar_no ?? ''
        );

        $booking->consultant = $request->consultant
            ?? $booking->consultant;

        $booking->dms_no = $request->dms_no
            ?? $booking->dms_no;

        $booking->dms_otf = $request->dms_otf
            ?? $booking->dms_otf;

        $booking->b_cat = $request->b_cat
            ?? $booking->b_cat;

        $booking->dsa_id = $request->dsa_id
            ?? $booking->dsa_id;

        $booking->buyer_type = $request->exchange
            ?? $booking->buyer_type;

        $booking->chassis_no = $request->chassis
            ?? $booking->chassis_no;

        $booking->inv_no = $request->inv_no
            ?? $booking->inv_no;

        $booking->inv_date = $request->inv_date
            ?? $booking->inv_date;

        /*
        |--------------------------------------------------------------------------
        | 12. UPDATE ENQUIRY
        |--------------------------------------------------------------------------
        */

        $enquiry = null;

        if (! empty($booking->enquiry_id)) {
            $enquiry = Enquiry::find(
                $booking->enquiry_id
            );
        }

        if (! $enquiry && ! empty($booking->enquiry_no)) {
            $enquiry = Enquiry::where(
                'enquiry_no',
                $booking->enquiry_no
            )->first();
        }

        if ($enquiry) {
            $enquiry->update([
                'zipcode' => $request->input('pincode')
                    ?? $enquiry->zipcode,

                'vpo' => $request->input('vpo')
                    ?? $enquiry->vpo,

                'tehsil' => $request->input('customer_tehsil')
                    ?? $enquiry->tehsil,

                'district' => $request->input('customer_district')
                    ?? $enquiry->district,

                'city' => $request->input('city')
                    ?? $enquiry->city,

                'territory' => $request->input('territory')
                    ?? $enquiry->territory,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 13. UPDATE RTO
        |--------------------------------------------------------------------------
        */

        $rtoData = [
            'rgn_no_type' => $request->registration_no_type
                ?? $existingFinalData['registration_no_type']
                ?? null,

            'permit' => $request->permit
                ?? $existingFinalData['permit']
                ?? null,

            'body_type' => $request->body_type
                ?? $existingFinalData['body_type']
                ?? null,

            'sale_type' => $request->sale_type
                ?? $existingFinalData['sale_type']
                ?? null,

            // 'registration_category' => $request->registration_category
            //     ?? $existingFinalData['registration_category']
            //     ?? null,

            'in_house_rto' => $request->input(
                'in_house_rto',
                $existingFinalData['in_house_rto'] ?? 0
            ),
        ];

        XlRto::updateOrCreate(
            ['bid' => $booking->id],
            $rtoData
        );

        /*
        |--------------------------------------------------------------------------
        | 14. UPDATE FINANCE
        |--------------------------------------------------------------------------
        */

        $finance = XFinance::firstOrNew([
            'bid' => $booking->id,
        ]);

        $finance->loan_amount = $request->loan_amount
            ?? $finance->loan_amount;

        $finance->file_charge = $request->file_charge
            ?? $finance->file_charge;

        $finance->margin = $request->margin_money
            ?? $finance->margin;

        $finance->subvention_amount = $request->financier_subvention
            ?? $finance->subvention_amount;

        $finance->instrument_type = $request->vehicle_delivery_on
            ?? $finance->instrument_type;

        $finance->instrument_ref_no = $request->do_number
            ?? $finance->instrument_ref_no;

        if (! $finance->exists) {
            $finance->verification_status = 0;
            $finance->case_status = 1;
        }

        $finance->save();

        /*
        |--------------------------------------------------------------------------
        | 15. UPDATE INSURANCE
        |--------------------------------------------------------------------------
        */

        if (
            $request->has('policy_type') ||
            $request->has('insurance_amount') ||
            $request->has('policy_no') ||
            $request->has('policy_date') ||
            $request->has('insurance_company') ||
            $request->has('insurance_source')
        ) {

            $insurance = XlInsurance::firstOrNew([
                'bid' => $booking->id,
            ]);

            $insurance->pol_type = $request->policy_type
                ?? $insurance->pol_type;

            $insurance->pol_no = $request->policy_no
                ?? $insurance->pol_no;

            $insurance->pol_date = $request->policy_date
                ?? $insurance->pol_date;

            $insurance->insurer = $request->insurance_company
                ? XlInsurer::where('short_name', $request->insurance_company)->value('id')
                : $insurance->insurer;

            $insurance->source = $request->insurance_source
                ?? $insurance->source;

            $insurance->save();
        }

        /*
        |--------------------------------------------------------------------------
        | 16. ACCESSORIES MAIN COLUMN
        |--------------------------------------------------------------------------
        */

        if ($request->has('accessories')) {

            $accessories = $request->input('accessories');

            if (is_array($accessories)) {

                $booking->accessories = implode(
                    ',',
                    array_filter(
                        array_map('trim', $accessories),
                        fn ($value) => $value !== ''
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 17. SAVE BOOKING
        |--------------------------------------------------------------------------
        */

        $booking->save();

        /*
        |--------------------------------------------------------------------------
        | 18. DEBUG FINAL JSON
        |--------------------------------------------------------------------------
        */

        \Log::info('✅ OTF FINAL JSON SAVED', [
            'booking_id' => $booking->id,
            'final_data' => $finalJsonData,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 19. ADD HISTORY
        |--------------------------------------------------------------------------
        */

        $booking->addHistory(
            'commented',
            'OTF Form Saved',
            'OTF form data saved successfully',
            [
                'module' => 'OTF Form',
                'saved_fields' => array_keys($finalJsonData),
            ],
            null,
            backpack_user()
        );

        /*
        |--------------------------------------------------------------------------
        | 20. REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->to(backpack_url('sales/booking/otf-form'))
            ->with('success', 'OTF form saved successfully.');
    }

    public function generateVotfNumber($id)
    {
        if (! backpack_user()->can('SLS_BKNG_OTF')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $booking = Booking::findOrFail($id);

        $branchCode = strtoupper(trim($booking->branch_code ?? ''));

        if ($branchCode === '') {
            return response()->json([
                'success' => false,
                'message' => 'Branch code is missing for this booking.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Financial Year
        |--------------------------------------------------------------------------
        | April 2026 - March 2027 = 27
        |--------------------------------------------------------------------------
        */
        $now = now();

        $financialYear = $now->month >= 4
            ? $now->copy()->addYear()->format('y')
            : $now->format('y');

        /*
        |--------------------------------------------------------------------------
        | Find latest VOTF numbers
        |--------------------------------------------------------------------------
        */

        $records = Booking::query()
            ->whereNotNull('final_data')
            ->where('final_data', '!=', '')
            ->get(['id', 'final_data']);

        $latestGlobalCount = 0;
        $latestBranchCount = 0;

        foreach ($records as $record) {

            $data = is_array($record->final_data)
                ? $record->final_data
                : json_decode($record->final_data, true);

            if (! is_array($data)) {
                continue;
            }

            $votf = trim($data['votf_no'] ?? '');

            if ($votf === '') {
                continue;
            }

            $parts = explode('/', $votf);

            if (count($parts) !== 3) {
                continue;
            }

            [$fy, $branchPart, $globalPart] = $parts;

            // Only current financial year
            if ($fy !== $financialYear) {
                continue;
            }

            /*
            * Global BMPL count
            */
            $globalNumber = (int) $globalPart;

            if ($globalNumber > $latestGlobalCount) {
                $latestGlobalCount = $globalNumber;
            }

            /*
            * Branch count
            */
            if (str_starts_with(strtoupper($branchPart), $branchCode)) {

                $branchNumber = (int) substr(
                    $branchPart,
                    strlen($branchCode)
                );

                if ($branchNumber > $latestBranchCount) {
                    $latestBranchCount = $branchNumber;
                }
            }
        }

        $nextBranchCount = $latestBranchCount + 1;
        $nextGlobalCount = $latestGlobalCount + 1;

        $votfNo = sprintf(
            '%s/%s%04d/%04d',
            $financialYear,
            $branchCode,
            $nextBranchCount,
            $nextGlobalCount
        );

        return response()->json([
            'success' => true,
            'votf_no' => $votfNo,
        ]);
    }

    public function downloadOtfPdf($id)
    {
        if (! backpack_user()->can('SLS_BKNG_OTF')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }

        $this->crud->hasAccessOrFail('show');

        $booking = Booking::findOrFail($id);

        // Get all the data needed for the OTF form (reuse from otfProcess)
        $data = $this->getOtfPdfData($booking);

        // Load the view for PDF
        $pdf = Pdf::loadView('admin.pdf.otf-form-pdf', $data);

        // Set paper size and options
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
            'dpi' => 150,
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true, // For images
        ]);

        $filename = sprintf(
            'OTF_Form_%s_%s_%s.pdf',
            $booking->id,
            str_replace(' ', '_', $booking->name ?? 'Unknown'),
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Prepare OTF data for PDF (reuse from otfProcess)
     */
    /**
     * Prepare OTF data for PDF (reuse from otfProcess)
     */
    private function getOtfPdfData($booking)
    {
        // Reuse the same logic from otfProcess method
        $quotation = null;

        if (! empty($booking->enquiry_id)) {
            $quotation = Quotation::where('enquiry_no', $booking->enquiry_id)->latest()->first();
        }

        if (! empty($booking->quotation_id)) {
            $quotation = Quotation::find($booking->quotation_id);
        }

        $quotationData = $quotation?->standard_data ?? [];
        $finalData = ! empty($booking->final_data) ? json_decode($booking->final_data, true) ?? [] : [];

        $otfData = array_merge($quotationData, $finalData);

        // Get related data
        $rto = XlRto::where('bid', $booking->id)->first();
        $finance = XFinance::where('bid', $booking->id)->first();
        $insurance = XlInsurance::where('bid', $booking->id)->first();

        $receiptLogs = Bookingamount::where('bid', $booking->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->get();

        $receiptTotal = $receiptLogs->sum(function ($receipt) {
            return (float) $receipt->amount;
        });

        $chassisImage = $booking->getFirstMediaUrl('chassis_image') ?: '';

        $segment = Segment::where('code', $booking->segment_code)->first();
        $model = VehicleModel::where('code', $booking->model_code)->first();
        $variant = Variant::with(['permit', 'fuelType', 'bodyType', 'bodyMake'])
            ->where('code', $booking->variant_code)->first();
        $color = Color::where('code', $booking->color_code)->first();

        $consultants = OrgService::getUsers(desigCode: 'SLS_CONS');

        $consultants = array_map(function ($consultant) {
            $consultant['branch_name'] = OrgService::branchName($consultant['primary_branch_code'] ?? '');
            $consultant['location_name'] = OrgService::locationName($consultant['primary_loc_code'] ?? '');
            $consultant['mile_id'] = $consultant['employee_code'] ?? '';

            return $consultant;
        }, $consultants);

        $dsaList = XL_DSA_MASTER::orderBy('name')->get(['id', 'name', 'dlocation']);

        $permit_map = OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(function ($permit, $index) {
                return [
                    (string) ($index + 1) => $permit->value,
                ];
            })
            ->toArray();

        $reg_no_type_map = [
            '1' => 'Regular',
            '2' => 'BH Series',
            '3' => 'Special Number',
        ];

        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

        $body_type_map = [
            '1' => 'Complete',
            '2' => 'CBC',
        ];

        $sale_type_map = [
            '1' => 'Within State',
            '2' => 'Outside State',
        ];

        $deliveryOptions = [
            1 => 'Payment',
            2 => 'DO',
            3 => 'Sanction Letter',
            4 => 'Mail',
            5 => 'Whatsapp',
        ];

        $financierName = XlFinancier::find($booking->financier)?->name ?? 'N/A';

        return compact(
            'booking',
            'otfData',
            'quotationData',
            'finalData',
            'rto',
            'finance',
            'insurance',
            'receiptLogs',
            'receiptTotal',
            'chassisImage',
            'segment',
            'model',
            'variant',
            'color',
            'consultants',
            'dsaList',
            'permit_map',
            'reg_no_type_map',
            'registration_type_map',
            'body_type_map',
            'sale_type_map',
            'deliveryOptions',
            'financierName'
        );
    }
}
