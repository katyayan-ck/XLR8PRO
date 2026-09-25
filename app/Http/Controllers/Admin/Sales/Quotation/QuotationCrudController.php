<?php

namespace App\Http\Controllers\Admin\Sales\Quotation;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\CRM\QuoteAction;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Vehicle\Accessory;
use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Quotation::class);

        CRUD::setRoute(config('backpack.base.route_prefix').'/sales/quotation');

        CRUD::setEntityNameStrings('quotation', 'quotations');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.sales.quotation.list');
    }

    public function index()
    {
        if (! backpack_user()->can('SLS_QUOT_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view quotations.');
        }

        $insurance_type_map = [
            1 => 'Standard',
            2 => 'Nil Dep',
            3 => 'Base',
            4 => 'Higher',
        ];

        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

        $reg_no_type_map = [
            '1' => 'Regular',
            '2' => 'BH Series',
            '3' => 'Special Number',
        ];

        $this->crud->setListView('admin.sales.quotation.list');

        $quotations = Quotation::with('enquiry')
            ->whereNotIn('status', ['booked'])
            ->latest('id')
            ->get();
        
        $bookingMap = DB::table('xlr8_booking_master')
            ->whereNotNull('quotation_id')
            ->pluck('id', 'quotation_id');

        $gridData = $quotations->map(function ($quotation, $index) use ($insurance_type_map, $registration_type_map, $reg_no_type_map, $bookingMap) {

            $data = $quotation->standard_data ?? [];
            $enquiry = $quotation->enquiry;
            $bookingId = $bookingMap[$quotation->id] ?? null;

            // If enquiry is null, try to find it by enquiry_no
            if (! $enquiry) {
                $enquiry = Enquiry::where('enquiry_no', $quotation->enquiry_no)->first();
            }

            // ============================================================
            // FIX: CUSTOMER NAME - Try multiple sources
            // ============================================================
            $customerName = '-';
            $mobile = '-';

            // 1. Try from enquiry first
            if ($enquiry) {
                $customerName = trim(
                    ($enquiry->first_name ?? '').' '.
                        ($enquiry->last_name ?? '')
                );
                if (empty($customerName)) {
                    $customerName = $enquiry->full_name ?? '';
                }
                if (empty($customerName)) {
                    $customerName = $enquiry->customer_name ?? '';
                }
                if (empty($customerName)) {
                    $customerName = $enquiry->name ?? '';
                }
                $mobile = $enquiry->mobile ?? $enquiry->phone ?? '-';
            }

            // 2. Fallback to standard_data if enquiry not found or name empty
            if (empty($customerName) || $customerName === '-') {
                $customerName = $data['customer_name'] ?? $data['customerName'] ?? '-';
            }

            // 3. Fallback for mobile
            if (empty($mobile) || $mobile === '-') {
                $mobile = $data['customer_mobile'] ?? $data['mobile'] ?? '-';
            }

            // ============================================================
            // FIX: VEHICLE DETAILS - Try multiple sources
            // ============================================================
            // 1. Try from enquiry first
            $segmentCode = $enquiry?->segment_code ?? '';
            $modelCode = $enquiry?->model_code ?? '';
            $variantCode = $enquiry?->variant_code ?? '';
            $colorCode = $enquiry?->color_code ?? '';

            // 2. Fallback to standard_data
            if (empty($segmentCode)) {
                $segmentCode = $data['segment_code'] ?? '';
            }
            if (empty($modelCode)) {
                $modelCode = $data['model_code'] ?? '';
            }
            if (empty($variantCode)) {
                $variantCode = $data['variant_code'] ?? '';
            }
            if (empty($colorCode)) {
                $colorCode = $data['color_code'] ?? '';
            }

            // ============================================================
            // FETCH VEHICLE NAMES FROM MASTER TABLES
            // ============================================================
            $segmentName = '-';
            $modelName = '-';
            $variantName = '-';
            $colorName = '-';

            if (! empty($segmentCode)) {
                $segment = DB::table('xlr8_vehicle_segment')
                    ->where('code', $segmentCode)
                    ->first();
                $segmentName = $segment->name ?? $enquiry?->segment ?? $data['segment'] ?? $segmentCode;
            }

            if (! empty($modelCode)) {
                $model = DB::table('xlr8_vehicle_model')
                    ->where('code', $modelCode)
                    ->first();
                $modelName = $model->name ?? $enquiry?->model ?? $data['model'] ?? $modelCode;
            }

            if (! empty($variantCode)) {
                $variant = DB::table('xlr8_vehicle_variant')
                    ->where('code', $variantCode)
                    ->first();
                $variantName = $variant->display_name
                    ?? $variant->custom_name
                    ?? $variant->oem_name
                    ?? $enquiry?->variant
                    ?? $data['variant']
                    ?? $variantCode;
            }

            if (! empty($colorCode)) {
                $color = DB::table('xlr8_vehicle_color')
                    ->where('code', $colorCode)
                    ->first();
                $colorName = $color->name
                    ?? $enquiry?->color
                    ?? $data['color']
                    ?? $colorCode;
            }

            // ============================================================
            // OTHER FIELDS
            // ============================================================
            $enquiryNo = $quotation->enquiry_no ?? '-';

            return [
                'serial_no' => $index + 1,
                'quotation_no' => $quotation->id,
                'enquiry_no' => $enquiryNo,

                // ✅ FIXED: Customer from enquiry with fallback
                'customer_name' => $customerName,
                'mobile' => $mobile,

                // ✅ FIXED: Vehicle from enquiry with fallback
                'segment' => $segmentName,
                'model' => $modelName,
                'variant' => $variantName,
                'color' => $colorName,

                // Other fields from standard_data
                'care_of_type' => [
                    1 => 'Son of',
                    2 => 'Daughter of',
                    3 => 'Married to',
                    4 => 'Guardian Name',
                ][$data['careof'] ?? ''] ?? '-',
                'care_of_name' => $data['careofname'] ?? '-',
                'permit' => $data['permit'] ?? '-',
                'oem_code' => $data['oem_code'] ?? $enquiry?->oem_code ?? '-',
                'revision' => $quotation->revision,

                // Price & Receivables
                'ex_showroom_price' => $data['ex_showroom_price'] ?? '',
                'insurance_company' => $data['insurance_company'] ?? '-',
                'policy_type' => $insurance_type_map[$data['policy_type'] ?? ''] ?? '-',
                'insurance_amount' => $data['insurance_amount'] ?? '',
                'registration_no_type' => $reg_no_type_map[$data['registration_no_type'] ?? ''] ?? ($data['registration_no_type'] ?? '-'),
                'registration_category' => $registration_type_map[$data['registration_category'] ?? ''] ?? ($data['registration_category'] ?? '-'),
                'in_house_rto' => (isset($data['in_house_rto']) && $data['in_house_rto'] == '1') ? 'Yes' : 'No',
                'registration_type' => $registration_type_map[$data['registration_type'] ?? ''] ?? '-',
                'registration_amount' => $data['registration_amount'] ?? '',

                // Accessories & Add-ons
                'accessories' => isset($data['accessories'])
                    ? (is_array($data['accessories']) ? implode(', ', $data['accessories']) : $data['accessories'])
                    : '',
                'accessories_amount' => $data['accessories_amount'] ?? '',
                'maxicare' => $data['maxicare'] ?? '',
                'vltd_device' => $data['vltd_device'] ?? '',
                'coating' => $data['coating'] ?? '',
                'coating_price' => $data['coating_price'] ?? '',
                'ppf' => $data['ppf'] ?? '',
                'rto_yellow_tape' => $data['rto_yellow_tape'] ?? '',
                'kazam_charging_kit' => $data['kazam_charging_kit'] ?? '',
                'incidental_charges' => $data['incidental_charges'] ?? '',
                'shield' => $data['shield'] ?? '',
                'shield_price' => $data['shield_price'] ?? '',
                'rsa' => $data['rsa'] ?? '',
                'rsa_amount' => $data['rsa_amount'] ?? '',
                'fastag' => $data['fastag'] ?? '',
                'cod_charges' => $data['cod_charges'] ?? '',
                'charger_swapping' => $data['charger_swapping'] ?? '',
                'charger_swapping_amount' => $data['charger_swapping_amount'] ?? '',
                'tcs' => $data['tcs'] ?? '',

                'onroad_price' => number_format((float) ($quotation->onroad_price ?: ($data['net_receivable_summary'] ?? $data['total_receivable'] ?? 0)), 2),

                'invoice_price' => number_format((float) ($quotation->invoice_price ?: ($data['invoice_amount'] ?? 0)), 2),
                'total_receivable' => $data['total_receivable'] ?? '',
                'total_discount' => $data['total_discount'] ?? '',
                'net_receivable' => $data['net_receivable_summary'] ?? '',

                // Discounts
                'oem_scheme_discount' => $data['cash_scheme_oem'] ?? $data['oem_scheme_discount'] ?? '',
                'cash_scheme_oem' => $data['cash_scheme_oem'] ?? '',
                'cash_scheme_oem_type' => $data['cash_scheme_oem_type'] ?? '',
                'csd_discount' => $data['csd_discount'] ?? '',
                'fame_subsidy' => $data['fame_subsidy'] ?? '',
                'exchange_bonus' => $data['exchange_bonus'] ?? '',
                'green_bonus' => $data['green_bonus'] ?? '',
                'welcome_bonus' => $data['welcome_bonus'] ?? '',
                'loyalty_bonus' => $data['loyalty_bonus'] ?? '',
                'corporate_discount' => $data['corporate_discount'] ?? '',
                'accessories_discount' => $data['accessories_discount'] ?? '',
                'accessories_spl_disc' => $data['accessories_spl_disc'] ?? '',
                'ceramic_discount' => $data['ceramic_discount'] ?? '',
                'ppf_discount' => $data['ppf_discount'] ?? '',
                'dealer_discount' => $data['dealer_discount'] ?? '',
                'charger_swapping_discount' => $data['charger_swapping_discount'] ?? '',
                'other_cash_discount' => $data['other_cash_discount'] ?? '',
                'special_cash_discount' => $data['special_cash_discount'] ?? '',

                'inv_discount' => $data['invoiced_discount_summary'] ?? '',
                'inv_oe_discount' => $data['inv_oe_discount_summary'] ?? '',
                'inv_d_discount' => $data['inv_d_discount_summary'] ?? '',
                'cn_discount' => $data['credit_note_discount_summary'] ?? '',
                'cn1_discount' => $data['cn1_discount_summary'] ?? '',
                'cn2_discount' => $data['cn2_discount_summary'] ?? '',
                'cn3_discount' => $data['cn3_discount_summary'] ?? '',

                'status' => ucfirst($quotation->status),

                'action' => '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.backpack_url('sales/quotation/'.$quotation->id.'/edit').'"
                        class="btn btn-sm btn-primary">
                        Edit
                    </a>
                    <a href="'.backpack_url('sales/quotation/'.$quotation->id.'/history').'"
                        class="btn btn-sm btn-info">
                        History
                    </a>
                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        onclick="confirmBookingProcess('.$quotation->id.', '.($bookingId ?? 'null').')">
                        Booking
                    </button>
                </div>',
            ];
        })->values();

        return view('admin.sales.quotation.list', [
            'title' => 'Quotation Listing',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'quotation_no', 'headerName' => 'Quotation No.'],
                    ['field' => 'enquiry_no', 'headerName' => 'Enquiry No.'],
                    ['field' => 'customer_name', 'headerName' => 'Customer'],
                    ['field' => 'mobile', 'headerName' => 'Mobile'],
                    ['field' => 'care_of_type', 'headerName' => 'Care Of'],
                    ['field' => 'care_of_name', 'headerName' => 'Care Of Name'],
                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'model', 'headerName' => 'Model'],
                    ['field' => 'variant', 'headerName' => 'Variant'],
                    ['field' => 'color', 'headerName' => 'Color'],
                    ['field' => 'permit', 'headerName' => 'Permit'],
                    ['field' => 'oem_code', 'headerName' => 'OEM Code'],
                    ['field' => 'revision', 'headerName' => 'Revision'],

                    ['field' => 'ex_showroom_price', 'headerName' => 'Ex Showroom'],
                    ['field' => 'insurance_company', 'headerName' => 'Insurance Co.'],
                    ['field' => 'insurance_amount', 'headerName' => 'Insurance Amount'],
                    ['field' => 'registration_amount', 'headerName' => 'Registration Amount'],
                    ['field' => 'registration_no_type', 'headerName' => 'Reg Type'],
                    ['field' => 'registration_category', 'headerName' => 'Reg Category'],
                    ['field' => 'in_house_rto', 'headerName' => 'In-House RTO'],
                    ['field' => 'policy_type', 'headerName' => 'Insurance'],
                    ['field' => 'registration_type', 'headerName' => 'Registration'],

                    ['field' => 'accessories', 'headerName' => 'Accessories'],
                    ['field' => 'accessories_amount', 'headerName' => 'Accessories Amount'],
                    ['field' => 'maxicare', 'headerName' => 'Maxicare'],
                    ['field' => 'vltd_device', 'headerName' => 'VLTD'],
                    ['field' => 'coating', 'headerName' => 'Coating'],
                    ['field' => 'coating_price', 'headerName' => 'Coating Price'],
                    ['field' => 'ppf', 'headerName' => 'PPF'],
                    ['field' => 'rto_yellow_tape', 'headerName' => 'Yellow Tape'],
                    ['field' => 'kazam_charging_kit', 'headerName' => 'Kazam Kit'],
                    ['field' => 'incidental_charges', 'headerName' => 'Incidental'],
                    ['field' => 'shield', 'headerName' => 'Shield'],
                    ['field' => 'shield_price', 'headerName' => 'Shield Price'],
                    ['field' => 'rsa', 'headerName' => 'RSA'],
                    ['field' => 'rsa_amount', 'headerName' => 'RSA Amount'],
                    ['field' => 'fastag', 'headerName' => 'Fastag'],
                    ['field' => 'cod_charges', 'headerName' => 'COD Charges'],
                    ['field' => 'charger_swapping', 'headerName' => 'Charger Swapping'],
                    ['field' => 'charger_swapping_amount', 'headerName' => 'Swapping Amount'],
                    ['field' => 'tcs', 'headerName' => 'TCS'],

                    ['field' => 'onroad_price', 'headerName' => 'On Road Price'],
                    ['field' => 'oem_scheme_discount', 'headerName' => 'OEM Discount'],
                    ['field' => 'cash_scheme_oem', 'headerName' => 'Cash OEM Scheme'],
                    ['field' => 'cash_scheme_oem_type', 'headerName' => 'OEM Scheme Type'],
                    ['field' => 'csd_discount', 'headerName' => 'CSD Discount'],
                    ['field' => 'fame_subsidy', 'headerName' => 'Fame Subsidy'],
                    ['field' => 'exchange_bonus', 'headerName' => 'Exchange Bonus'],
                    ['field' => 'green_bonus', 'headerName' => 'Green Bonus'],
                    ['field' => 'welcome_bonus', 'headerName' => 'Welcome Bonus'],
                    ['field' => 'loyalty_bonus', 'headerName' => 'Loyalty Bonus'],
                    ['field' => 'corporate_discount', 'headerName' => 'Corporate Discount'],
                    ['field' => 'accessories_discount', 'headerName' => 'Accessories Discount'],
                    ['field' => 'accessories_spl_disc', 'headerName' => 'Acc Spl Disc'],
                    ['field' => 'ceramic_discount', 'headerName' => 'Ceramic Discount'],
                    ['field' => 'ppf_discount', 'headerName' => 'PPF Discount'],
                    ['field' => 'dealer_discount', 'headerName' => 'Dealer Discount'],
                    ['field' => 'charger_swapping_discount', 'headerName' => 'Swapping Discount'],
                    ['field' => 'other_cash_discount', 'headerName' => 'Other Cash Disc'],
                    ['field' => 'special_cash_discount', 'headerName' => 'Special Cash Disc'],

                    ['field' => 'inv_discount', 'headerName' => 'Total INV'],
                    ['field' => 'inv_oe_discount', 'headerName' => 'Total INV (OE)'],
                    ['field' => 'inv_d_discount', 'headerName' => 'Total INV (D)'],
                    ['field' => 'cn_discount', 'headerName' => 'Total CN'],
                    ['field' => 'cn1_discount', 'headerName' => 'Total CN1'],
                    ['field' => 'cn2_discount', 'headerName' => 'Total CN2'],
                    ['field' => 'cn3_discount', 'headerName' => 'Total CN3'],
                    ['field' => 'total_discount', 'headerName' => 'Total Discount'],
                    ['field' => 'net_receivable', 'headerName' => 'Net Receivable'],
                    ['field' => 'invoice_price', 'headerName' => 'Invoice Price'],

                    ['field' => 'status', 'headerName' => 'Status'],
                    ['field' => 'action', 'headerName' => 'Action'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('SLS_QUOT_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create quotations.');
        }

        $this->crud->setCreateView('admin.sales.quotation.create');

        $bookingId = request('booking_id');
        $booking = null;
        $enquiry = null;

        if ($bookingId) {

            $booking = Booking::findOrFail($bookingId);

            if (!empty($booking->enq_no)) {
                $enquiry = Enquiry::resolveByAnyReference(
                    $booking->enq_no
                );
            }

            if (!$enquiry && !empty($booking->quotation_id)) {
                $linkedQuotation = Quotation::with('enquiry')
                    ->find($booking->quotation_id);

                $enquiry = $linkedQuotation?->enquiry;
            }

            if (!$enquiry) {
                abort(
                    404,
                    'Associated enquiry not found. ' .
                    'Booking ID: ' . $bookingId .
                    ', Enquiry Reference: ' . ($booking->enq_no ?? 'NULL')
                );
            }

            $enquiryId = $enquiry->id;

        } else {

            $enquiryReference = request('id');

            if (!$enquiryReference) {
                abort(404, 'Enquiry not found.');
            }

            $selectedEnquiry = Enquiry::resolveByAnyReference(
                $enquiryReference
            );

            if (!$selectedEnquiry) {
                abort(
                    404,
                    'Enquiry not found. Reference: ' . $enquiryReference
                );
            }

            $enquiryId = $selectedEnquiry->id;
        }

        $selectedEnquiry = Enquiry::findOrFail($enquiryId);

        $segment = DB::table('xlr8_vehicle_segment')
            ->where('code', $selectedEnquiry->segment_code)
            ->first();

        $model = DB::table('xlr8_vehicle_model')
            ->where('code', $selectedEnquiry->model_code)
            ->first();

        $variant = DB::table('xlr8_vehicle_variant')
            ->where('code', $selectedEnquiry->variant_code)
            ->first();

        $color = DB::table('xlr8_vehicle_color')
            ->where('model_code', $selectedEnquiry->model_code)
            ->where('variant_code', $selectedEnquiry->variant_code)
            ->where('code', $selectedEnquiry->color_code)
            ->first();

        $segmentName = $segment->name ?? $selectedEnquiry->segment_code ?? '';

        $modelName = $model->name ?? $selectedEnquiry->model_code ?? '';

        $variantName = $variant->display_name
            ?? $variant->custom_name
            ?? $variant->oem_name
            ?? $selectedEnquiry->variant_code
            ?? '';

        $colorName = $color->name ?? $selectedEnquiry->color ?? '';

        $careOf = $selectedEnquiry->care_of_type ?? '';
        $careOfName = $selectedEnquiry->care_of ?? '';

        $permit_map = OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(function ($permit, $index) {
                return [
                    (string) ($index + 1) => $permit->value,
                ];
            })
            ->toArray();

        $insurance_type_map = [
            1 => 'Nil Dep',
            2 => 'Higher',
        ];

        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

        $accessoryList = Accessory::where(
            'status',
            1
        )
            ->orderBy('item')
            ->get();

        $financiers = XlFinancier::select('id', 'name', 'short_name')
            ->get();

        $data = [
            'selectedEnquiry' => $selectedEnquiry,
            'segmentName' => $segmentName,
            'modelName' => $modelName,
            'variantName' => $variantName,
            'colorName' => $colorName,
            'insurance_type_map' => $insurance_type_map,
            'registration_type_map' => $registration_type_map,
            'accessoryList' => $accessoryList,
            'financiers' => $financiers,
            'permit_map' => $permit_map,
            'bookingId' => $bookingId,
            'quotationData' => [
                'careof' => $careOf,
                'careofname' => $careOfName,
            ],
        ];


        return view('admin.sales.quotation.create', $data);
    }

    public function store(Request $request)
    {
        if (! backpack_user()->can('SLS_QUOT_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create quotations.');
        }
        
        $booking = null;

        if ($request->filled('booking_id')) {
            $booking = Booking::findOrFail(
                $request->input('booking_id')
            );
        }

        $quotationData = $request->except([
            '_token',
            'booking_id',
            'enquiry_id',
            'customer_name',
            'mobile',
            // 'careof',
            // 'careofname',
            'segment_code',
            'model_code',
            'variant_code',
            'color_code',
        ]);
        // dd($quotationData);

        // Filter out null or empty string values from being saved in the JSON payload
        $quotationData = array_filter($quotationData, function ($value) {
            return ! is_null($value) && $value !== '';
        });

        if ($request->has('careof')) {
            $quotationData['careof'] = $request->input('careof');
        }

        if ($request->has('careofname')) {
            $quotationData['careofname'] = $request->input('careofname');
        }

        DB::beginTransaction();

        try {
            // Normalize insurance covers from the hidden field
            if ($request->has('insurance_covers_data') && ! empty($request->insurance_covers_data)) {
                $coversData = json_decode($request->insurance_covers_data, true);
                if (is_array($coversData) && ! empty($coversData)) {
                    $quotationData['insurance_covers'] = $coversData;
                }
            }

            if (isset($quotationData['accessories']) && is_array($quotationData['accessories'])) {
                $quotationData['accessories'] = array_values($quotationData['accessories']);
            }

            // Save main quotation details
            $quotation = new Quotation;

            $quotation->enquiry_no = $request->input('enquiry_id');

            $quotation->onroad_price = $request->input('net_receivable_summary') ?? $request->input('total_receivable') ?? 0;

            $quotation->invoice_price = $request->input('invoice_amount') ?? 0;

            $quotation->standard_data = $quotationData;
            $quotation->status = 'raised';
            $quotation->created_by = auth()->id();

            $quotation->save();     

            
            if ($booking) {
                $booking->quotation_id = $quotation->id;
                $booking->save();
            }

           
            $this->saveDiscountFields(
                $quotation,
                $quotationData
            );

            QuoteAction::create([
                'quotation_no' => $quotation->id,
                'action_by' => backpack_user()->id,
                'action' => 'RAISED',
                'requested' => $quotationData,
                'onroad' => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,
                'status' => 'raised',
                'remarks' => 'Quotation Created',
                'created_by' => backpack_user()->id,
            ]);

            /*
        |--------------------------------------------------------------------------
        | 26. Commit
        |--------------------------------------------------------------------------
        */
            DB::commit();

            \Alert::success(
                'Quotation created successfully.'
            )->flash();

            return redirect(
                backpack_url(
                    'sales/quotation/'.
                        $quotation->id.
                        '/edit'
                ).'?saved=1'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error(
                'Quotation Store Error: '.
                    $e->getMessage()
            );

            \Log::error(
                $e->getTraceAsString()
            );

            \Alert::error(
                'Error saving quotation: '.
                    $e->getMessage()
            )->flash();

            return back()->withInput();
        }
    }

    private function saveDiscountFields($quotation, $data)
    {
        // Correct mapping - form field name => database column name
        $discountFields = [
            'cash_scheme_oem' => 'oem_scheme_discount',
            'csd_discount' => 'csd_discount',
            'fame_subsidy' => 'fame_subsidy',
            'dealer_discount' => 'dealer_discount',
            'accessories_discount' => 'accessories_discount',
            'shield_scheme' => 'shield_scheme',
            'corporate_discount' => 'corporate_discount',
            'loyalty_bonus' => 'loyalty_bonus',
            'exchange_bonus' => 'exchange_bonus',
            'green_bonus' => 'green_bonus',
            'welcome_bonus' => 'welcome_bonus',
            'accessories_spl_disc' => 'accessories_spl_disc',
            'ceramic_discount' => 'ceramic_discount',
            'ppf_discount' => 'ppf_discount',
            'charger_swapping_discount' => 'charger_swapping_discount',
            'other_cash_discount' => 'other_cash_discount',
            'special_cash_discount' => 'special_cash_discount',
        ];

        $updateData = [];
        foreach ($discountFields as $formField => $dbField) {
            $value = $data[$formField] ?? '';
            // Skip N/A, empty, or 0 values
            if ($value !== '' && $value !== 'N/A' && $value !== null && $value !== '0' && $value !== '0.00') {
                $updateData[$dbField] = $value;
            }
        }

        if (! empty($updateData)) {
            $quotation->update($updateData);
        }
    }

    public function edit($id)
    {
        if (! backpack_user()->can('SLS_QUOT_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit quotations.');
        }

        $this->crud->setEditView('admin.sales.quotation.create');

        /*
        |--------------------------------------------------------------------------
        | 1. Load quotation
        |--------------------------------------------------------------------------
        */
        $quotation = Quotation::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | 2. Find original enquiry
        |--------------------------------------------------------------------------
        | New quotations store enquiry ID in quotation.enquiry_no.
        | Old quotations may still contain enquiry_no.
        |--------------------------------------------------------------------------
        */
        $selectedEnquiry = Enquiry::find($quotation->enquiry_no);

        /*
        |--------------------------------------------------------------------------
        | Backward compatibility for old quotations
        |--------------------------------------------------------------------------
        */
        if (! $selectedEnquiry) {
            $selectedEnquiry = Enquiry::where(
                'enquiry_no',
                $quotation->enquiry_no
            )->first();
        }
        /*
        |--------------------------------------------------------------------------
        | 3. Get saved quotation data
        |--------------------------------------------------------------------------
        */
        $quotationData = $quotation->standard_data;

        // Agar double-encoded JSON string hai to array me convert karo
        if (is_string($quotationData)) {
            $quotationData = json_decode($quotationData, true);
        }
        if (is_string($quotationData)) {
            $quotationData = json_decode($quotationData, true);
        }
        if (! is_array($quotationData)) {
            $quotationData = [];
        }
        /*
        |--------------------------------------------------------------------------
        | 4. FIX: Customer Name - ALWAYS from enquiry first, fallback to standard_data
        |--------------------------------------------------------------------------
        */
        $customerName = '';
        if ($selectedEnquiry) {
            $customerName = trim(
                ($selectedEnquiry->first_name ?? '').' '.
                    ($selectedEnquiry->last_name ?? '')
            );
            if (empty($customerName)) {
                $customerName = $selectedEnquiry->full_name ?? '';
            }
            if (empty($customerName)) {
                $customerName = $selectedEnquiry->customer_name ?? '';
            }
            if (empty($customerName)) {
                $customerName = $selectedEnquiry->name ?? '';
            }
        }
        // Fallback to standard_data if enquiry not found or name is empty
        if (empty($customerName)) {
            $customerName = $quotationData['customer_name'] ?? $quotationData['customerName'] ?? '';
        }
        /*
        |--------------------------------------------------------------------------
        | 5. FIX: Mobile - ALWAYS from enquiry first
        |--------------------------------------------------------------------------
        */
        $customerMobile = '';
        if ($selectedEnquiry) {
            $customerMobile = $selectedEnquiry->mobile
                ?? $selectedEnquiry->phone
                ?? $selectedEnquiry->mobile_no
                ?? '';
        }
        // Fallback to standard_data
        if (empty($customerMobile)) {
            $customerMobile = $quotationData['customer_mobile']
                ?? $quotationData['mobile']
                ?? '';
        }
        /*
        |--------------------------------------------------------------------------
        | 6. FIX: Vehicle codes - ALWAYS from enquiry first, fallback to standard_data
        |--------------------------------------------------------------------------
        */
        $segmentCode = $selectedEnquiry?->segment_code ?? $quotationData['segment_code'] ?? '';
        $modelCode = $selectedEnquiry?->model_code ?? $quotationData['model_code'] ?? '';
        $variantCode = $selectedEnquiry?->variant_code ?? $quotationData['variant_code'] ?? '';
        $colorCode = $selectedEnquiry?->color_code ?? $quotationData['color_code'] ?? '';

        $careOf = $selectedEnquiry?->care_of_type ?? $quotationData['careof'] ?? '';
        $careOfName = $selectedEnquiry?->care_of ?? $quotationData['careofname'] ?? '';
        /*
        |--------------------------------------------------------------------------
        | 7. FIX: Vehicle names with proper fallback
        |--------------------------------------------------------------------------
        */
        $segmentName = '';
        $modelName = '';
        $variantName = '';
        $colorName = '';

        if (! empty($segmentCode)) {
            $segment = DB::table('xlr8_vehicle_segment')
                ->where('code', $segmentCode)
                ->first();
            $segmentName = $segment->name ?? $selectedEnquiry?->segment ?? $quotationData['segment'] ?? $segmentCode;
        }

        if (! empty($modelCode)) {
            $model = DB::table('xlr8_vehicle_model')
                ->where('code', $modelCode)
                ->first();
            $modelName = $model->name ?? $selectedEnquiry?->model ?? $quotationData['model'] ?? $modelCode;
        }

        if (! empty($variantCode)) {
            $variant = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->first();
            $variantName = $variant->display_name
                ?? $variant->custom_name
                ?? $variant->oem_name
                ?? $selectedEnquiry?->variant
                ?? $quotationData['variant']
                ?? $variantCode;
        }

        if (! empty($colorCode)) {
            $color = DB::table('xlr8_vehicle_color')
                ->where('code', $colorCode)
                ->first();
            $colorName = $color->name
                ?? $selectedEnquiry?->color
                ?? $quotationData['color']
                ?? $colorCode;
        }
        /*
        |--------------------------------------------------------------------------
        | 8. FIX: Ensure quotationData has the updated values
        |--------------------------------------------------------------------------
        */
        $quotationData['customer_name'] = $customerName;
        $quotationData['customer_mobile'] = $customerMobile;
        $quotationData['mobile'] = $customerMobile;
        $quotationData['enquiry_no'] = $quotation->enquiry_no;
        $quotationData['careof'] = $careOf;
        $quotationData['careofname'] = $careOfName;

        /*
        |--------------------------------------------------------------------------
        | 9. FIX: Ensure vehicle codes and names are in quotationData for the form
        |--------------------------------------------------------------------------
        */
        $quotationData['segment_code'] = $segmentCode;
        $quotationData['model_code'] = $modelCode;
        $quotationData['variant_code'] = $variantCode;
        $quotationData['color_code'] = $colorCode;
        $quotationData['segment'] = $segmentName;
        $quotationData['model'] = $modelName;
        $quotationData['variant'] = $variantName;
        $quotationData['color'] = $colorName;

        /*
        |--------------------------------------------------------------------------
        | 10. OEM Code
        |--------------------------------------------------------------------------
        */
        $quotationData['oem_code'] = $selectedEnquiry?->oem_code
            ?? $quotationData['oem_code']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | 11. On Road / Invoice Price
        |--------------------------------------------------------------------------
        */
        $quotationData['onroad_price'] = ! empty($quotationData['onroad_price'])
            ? $quotationData['onroad_price']
            : $quotation->onroad_price;

        $quotationData['invoice_price'] = ! empty($quotationData['invoice_price'])
            ? $quotationData['invoice_price']
            : $quotation->invoice_price;

        /*
        |--------------------------------------------------------------------------
        | 12. Accessories - Ensure it's an array
        |--------------------------------------------------------------------------
        */
        if (isset($quotationData['accessories']) && ! is_array($quotationData['accessories'])) {
            $quotationData['accessories'] = [
                $quotationData['accessories'],
            ];
        }
        if (! isset($quotationData['accessories'])) {
            $quotationData['accessories'] = [];
        }
        /*
        |--------------------------------------------------------------------------
        | 13. Insurance Covers - Ensure it's an array
        |--------------------------------------------------------------------------
        */
        if (isset($quotationData['insurance_covers']) && ! is_array($quotationData['insurance_covers'])) {
            $quotationData['insurance_covers'] = [
                $quotationData['insurance_covers'],
            ];
        }
        if (! isset($quotationData['insurance_covers'])) {
            $quotationData['insurance_covers'] = [];
        }
        /*
        |--------------------------------------------------------------------------
        | 14. Dropdown maps
        |--------------------------------------------------------------------------
        */
        $insurance_type_map = [
            1 => 'Nil Dep',
            2 => 'Higher',
        ];

        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

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

        /*
        |--------------------------------------------------------------------------
        | 15. Accessories List
        |--------------------------------------------------------------------------
        */
        $accessoryList = Accessory::where(
            'status',
            1
        )
            ->orderBy('item')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 16. Financiers
        |--------------------------------------------------------------------------
        */
        $financiers = XlFinancier::select('id', 'name', 'short_name')->get();

        /*
        |--------------------------------------------------------------------------
        | 17. Determine Group A
        |--------------------------------------------------------------------------
        */
        $groupASelected = 'cash_scheme_oem';
        if (! empty($quotationData['csd_discount']) && ! in_array(
            $quotationData['csd_discount'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupASelected = 'csd_discount';
        } elseif (! empty($quotationData['fame_subsidy']) && ! in_array(
            $quotationData['fame_subsidy'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupASelected = 'fame_subsidy';
        } elseif (! empty($quotationData['cash_scheme_oem']) && ! in_array(
            $quotationData['cash_scheme_oem'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupASelected = 'cash_scheme_oem';
        }
        /*
        |--------------------------------------------------------------------------
        | 18. Group B
        |--------------------------------------------------------------------------
        */
        $groupBSelected = 'corporate_discount';

        /*
        |--------------------------------------------------------------------------
        | 19. Group C
        |--------------------------------------------------------------------------
        */
        $groupCSelected = 'exchange_bonus';
        if (! empty($quotationData['loyalty_bonus']) && ! in_array(
            $quotationData['loyalty_bonus'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupCSelected = 'loyalty_bonus';
        } elseif (! empty($quotationData['green_bonus']) && ! in_array(
            $quotationData['green_bonus'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupCSelected = 'green_bonus';
        } elseif (! empty($quotationData['welcome_bonus']) && ! in_array(
            $quotationData['welcome_bonus'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupCSelected = 'welcome_bonus';
        } elseif (! empty($quotationData['exchange_bonus']) && ! in_array(
            $quotationData['exchange_bonus'],
            [
                '0',
                '0.00',
                'N/A',
            ]
        )) {
            $groupCSelected = 'exchange_bonus';
        }

        /*
        |--------------------------------------------------------------------------
        | 20. Return edit view
        |--------------------------------------------------------------------------
        */
        return view(
            'admin.sales.quotation.create',
            [
                'quotation' => $quotation,
                'quotationData' => $quotationData,
                'selectedEnquiry' => $selectedEnquiry,
                // Vehicle display values
                'segmentName' => $segmentName,
                'modelName' => $modelName,
                'variantName' => $variantName,
                'colorName' => $colorName,
                // Vehicle codes
                'segmentCode' => $segmentCode,
                'modelCode' => $modelCode,
                'variantCode' => $variantCode,
                'colorCode' => $colorCode,
                // Dropdown maps
                'insurance_type_map' => $insurance_type_map,
                'registration_type_map' => $registration_type_map,
                'permit_map' => $permit_map,
                'reg_no_type_map' => $reg_no_type_map,
                // Other data
                'accessoryList' => $accessoryList,
                'financiers' => $financiers,
                // Discount groups
                'groupASelected' => $groupASelected,
                'groupBSelected' => $groupBSelected,
                'groupCSelected' => $groupCSelected,

                'viewMode' => false,
            ]
        );
    }

    public function update(Request $request, $id)
    {
        if (! backpack_user()->can('SLS_QUOT_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit quotations.');
        }

        // ✅ 1. REMOVED: segment_code, model_code, variant_code, color_code validation
        $request->validate([
            'enquiry_id' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $quotation = Quotation::findOrFail($id);

            $enquiry = null;

            if ($quotation->enquiry_no) {
                $enquiry = Enquiry::where('id', $quotation->enquiry_no)
                    ->orWhere('enquiry_no', $quotation->enquiry_no)
                    ->first();
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback only when existing enquiry link is unavailable
            |--------------------------------------------------------------------------
            */

            if (! $enquiry && $request->filled('enquiry_id')) {
                $enquiry = Enquiry::where('id', $request->enquiry_id)
                    ->orWhere('enquiry_no', $request->enquiry_id)
                    ->first();
            }

            if (! $enquiry) {
                \Log::info('Mock enquiry detected in update: '.$request->enquiry_id);
            }

            $previousProposal = $quotation->standard_data ?? [];

            if (! is_array($previousProposal)) {
                $previousProposal = [];
            }

            $quotationData = array_merge(
                $previousProposal,
                $request->except(['_token', '_method'])
            );

            $oldFinancier = $previousProposal['financier'] ?? null;
            $newFinancier = $quotationData['financier'] ?? null;

            $financierHistory = $previousProposal['financier_history'] ?? [];

            if (! is_array($financierHistory)) {
                $financierHistory = [];
            }

            if (
                ! empty($newFinancier) &&
                (string) $oldFinancier !== (string) $newFinancier &&
                ! in_array($newFinancier, $financierHistory, true)
            ) {
                $financierHistory[] = $newFinancier;
            }

            $quotationData['financier_history'] = $financierHistory;

            $quotationData['charger_swapping_option'] = $request->input('charger_swapping_option');

            // ✅ 3. REMOVED: vehicle code preservation - these come from enquiry now
            // ❌ DELETE THESE LINES:
            // $quotationData['segment_code'] = $request->segment_code;
            // $quotationData['model_code'] = $request->model_code;
            // $quotationData['variant_code'] = $request->variant_code;
            // $quotationData['color_code'] = $request->color_code;

            // ✅ FIX: Use insurance_covers_data instead of insurance_covers
            if ($request->has('insurance_covers_data') && ! empty($request->insurance_covers_data)) {
                $coversData = json_decode($request->insurance_covers_data, true);
                if (is_array($coversData) && ! empty($coversData)) {
                    $quotationData['insurance_covers'] = $coversData;
                }
            }

            // ✅ SAVE INSURANCE COMPANY
            if ($request->has('insurance_company')) {
                $quotationData['insurance_company'] = $request->insurance_company;
            }

            // ============================================================
            // INSURANCE COVERS - PRIORITIZE ACTUAL PRICE FROM HIDDEN JSON
            // ============================================================

            $insuranceCoversSaved = false;

            /*
            |--------------------------------------------------------------------------
            | 1. PRIMARY SOURCE
            |--------------------------------------------------------------------------
            | insurance_covers_data contains:
            | [
            |   {
            |       "name": "Basic OD + TP",
            |       "price": 12345
            |   },
            |   ...
            | ]
            |
            | Always use this first because this contains the real selected prices.
            |--------------------------------------------------------------------------
            */

            if ($request->filled('insurance_covers_data')) {

                $coversData = json_decode(
                    $request->input('insurance_covers_data'),
                    true
                );

                if (is_array($coversData)) {

                    $formattedCovers = [];

                    foreach ($coversData as $cover) {

                        if (! is_array($cover)) {
                            continue;
                        }

                        $name = trim((string) ($cover['name'] ?? ''));
                        $price = (float) ($cover['price'] ?? 0);

                        if ($name === '') {
                            continue;
                        }

                        $formattedCovers[] = [
                            'name' => $name,
                            'price' => $price,
                        ];
                    }

                    $quotationData['insurance_covers'] = $formattedCovers;

                    $insuranceCoversSaved = true;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. FALLBACK
            |--------------------------------------------------------------------------
            | Only use insurance_covers[] when insurance_covers_data is unavailable.
            |--------------------------------------------------------------------------
            */

            if (! $insuranceCoversSaved && $request->has('insurance_covers')) {

                $covers = $request->input('insurance_covers', []);
                $formattedCovers = [];

                if (is_array($covers)) {

                    foreach ($covers as $cover) {

                        /*
                        * Array format
                        */
                        if (is_array($cover)) {

                            $name = trim((string) ($cover['name'] ?? ''));
                            $price = (float) ($cover['price'] ?? 0);

                            if ($name !== '') {

                                $formattedCovers[] = [
                                    'name' => $name,
                                    'price' => $price,
                                ];
                            }

                        }

                        /*
                        * Old/string format
                        */
                        else {

                            $cover = trim((string) $cover);

                            $price = 0;
                            $name = $cover;

                            if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {

                                $price = (float) str_replace(',', '', $matches[1]);

                                $name = trim(
                                    preg_replace(
                                        '/\(₹[\d,]+\.?\d*\)/',
                                        '',
                                        $cover
                                    )
                                );
                            }

                            if ($name !== '') {

                                $formattedCovers[] = [
                                    'name' => $name,
                                    'price' => $price,
                                ];
                            }
                        }
                    }
                }

                $quotationData['insurance_covers'] = $formattedCovers;
            }

            // ✅ 2. Ensure Accessories are stored as a clean array of part numbers
            if (! empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
                $quotationData['accessories'] = array_values($quotationData['accessories']);
            }

            // ✅ SAVE ACCESSORIES
            if (isset($quotationData['accessories']) && is_array($quotationData['accessories'])) {
                $quotationData['accessories'] = array_values($quotationData['accessories']);
            }
            if ($request->has('accessories_amount')) {
                $quotationData['accessories_amount'] = $request->accessories_amount;
            }

            // ✅ SAVE REGISTRATION DETAILS
            if ($request->has('registration_no_type')) {
                $quotationData['registration_no_type'] = $request->registration_no_type;
            }
            if ($request->has('registration_category')) {
                $quotationData['registration_category'] = $request->registration_category;
            }
            if ($request->has('in_house_rto')) {
                $quotationData['in_house_rto'] = $request->in_house_rto;
            }

            // ✅ SAVE PERMIT
            if ($request->has('permit')) {
                $quotationData['permit'] = $request->permit;
            }

            /*
        |--------------------------------------------------------------------------
        | SAVE CUSTOMER DETAILS
        |--------------------------------------------------------------------------
        */

            /*
            |--------------------------------------------------------------------------
            | SAVE CUSTOMER DETAILS
            |--------------------------------------------------------------------------
            */

            /*
            |--------------------------------------------------------------------------
            | SAVE CUSTOMER NAME - NEVER OVERWRITE WITH BLANK
            |--------------------------------------------------------------------------
            */

            $submittedCustomerName = trim(
                (string) $request->input('customer_name', '')
            );

            $previousCustomerName = trim(
                (string) (
                    $previousProposal['customer_name']
                    ?? $previousProposal['customerName']
                    ?? ''
                )
            );

            $customerName = '';

            /*
            |--------------------------------------------------------------------------
            | 1. Submitted customer name
            |--------------------------------------------------------------------------
            */
            if (! empty($submittedCustomerName)) {
                $customerName = $submittedCustomerName;
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Existing saved quotation customer name
            |--------------------------------------------------------------------------
            */
            if (empty($customerName) && ! empty($previousCustomerName)) {
                $customerName = $previousCustomerName;
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Actual enquiry
            |--------------------------------------------------------------------------
            */
            if (empty($customerName) && $enquiry) {

                $customerName = trim(
                    ($enquiry->first_name ?? '').' '.
                    ($enquiry->last_name ?? '')
                );

                if (empty($customerName)) {
                    $customerName = trim(
                        (string) ($enquiry->full_name ?? '')
                    );
                }

                if (empty($customerName)) {
                    $customerName = trim(
                        (string) ($enquiry->customer_name ?? '')
                    );
                }

                if (empty($customerName)) {
                    $customerName = trim(
                        (string) ($enquiry->name ?? '')
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Save only if we actually have a name
            |--------------------------------------------------------------------------
            */
            if (! empty($customerName)) {
                $quotationData['customer_name'] = $customerName;
            }

            if ($request->has('customer_mobile')) {
                $quotationData['customer_mobile'] = $request->customer_mobile;
            } elseif ($request->has('mobile')) {
                $quotationData['customer_mobile'] = $request->mobile;
            } elseif ($enquiry) {
                $quotationData['customer_mobile'] =
                    $enquiry->mobile ?? $enquiry->phone ?? '';
            }

            // If a real enquiry record was found, use its id/enquiry_no.
            // Otherwise (mock/demo enquiry used for testing) keep the raw
            // value submitted from the form instead of nulling it out.
            if ($enquiry) {
                $quotationData['enquiry_id'] = $enquiry->id;
                $quotationData['enquiry_no'] = $enquiry->enquiry_no;
            } else {
                $quotationData['enquiry_id'] = $request->enquiry_id;
                $quotationData['enquiry_no'] = $request->enquiry_id;
            }

            $oldDataForComparison = $previousProposal;
            $newDataForComparison = $quotationData;

            unset($oldDataForComparison['financier_history']);
            unset($newDataForComparison['financier_history']);

            foreach ($newDataForComparison as $key => $value) {
                if (
                    ! array_key_exists($key, $oldDataForComparison) &&
                    ($value === null ||
                        $value === '' ||
                        $value === [] ||
                        $value === '0' ||
                        $value === '0.00')
                ) {
                    unset($newDataForComparison[$key]);
                }
            }

            $hasQuotationChanges =
                $oldDataForComparison != $newDataForComparison;

            $newRevision = $hasQuotationChanges
                ? ((int) $quotation->revision) + 1
                : (int) $quotation->revision;

            // ✅ 5. REMOVED: person_code, model_code, variant_code, color_code from update
            // BUG-096: status must not regress once a Quotation has been converted to a Booking
            // (`booked`) — the FRS documents `booked` as the post-conversion state, and the main
            // quotation listing (index(), whereNotIn('status', ['booked'])) relies on it staying
            // `booked` to stop showing already-converted quotations as pending. Preserve it here
            // instead of unconditionally overwriting with `raised` on every edit.
            $statusAfterUpdate = $quotation->status === 'booked' ? 'booked' : 'raised';

            $quotation->update([
                'enquiry_no' => $enquiry ? $enquiry->id : $request->enquiry_id,
                'revision' => $newRevision,

                'standard_data' => $quotationData,

                'onroad_price' => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,

                'invoice_price' => $request->invoice_amount
                    ?? $request->net_receivable_summary
                    ?? 0,

                'status' => $statusAfterUpdate,
                'updated_by' => backpack_user()->id,
            ]);

            $this->saveDiscountFields($quotation, $quotationData);

            if ($hasQuotationChanges) {

                QuoteAction::create([
                    'quotation_no' => $quotation->id,
                    'action_by' => backpack_user()->id,
                    'action' => 'REVISED',
                    'requested' => $quotationData,
                    'onroad' => $request->net_receivable_summary
                        ?? $request->total_receivable
                        ?? 0,
                    'status' => $statusAfterUpdate,
                    'remarks' => 'Quotation Revised',
                    'created_by' => backpack_user()->id,
                ]);
            }

            DB::commit();

            \Alert::success('Quotation updated successfully.')->flash();

            return redirect(backpack_url('sales/quotation/'.$quotation->id.'/edit').'?saved=1');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Quotation Update Error: '.$e->getMessage());
            \Log::error($e->getTraceAsString());
            \Alert::error('Error updating quotation: '.$e->getMessage())->flash();

            return back()->withInput();
        }
    }

    public function revise($quotation_no)
    {
        return $this->edit($quotation_no);
    }

    public function history($id)
    {
        if (! backpack_user()->can('SLS_QUOT_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view quotations.');
        }

        $quotation = Quotation::findOrFail($id);

        $quotationData = $quotation->standard_data ?? [];

        $customerName = '';

        // 1. First priority: proposed_data
        if (! empty($quotationData['customer_name'])) {

            $customerName = $quotationData['customer_name'];
        }
        // 2. Second priority: actual enquiry
        elseif ($quotation->enquiry) {

            $customerName = trim(
                ($quotation->enquiry->first_name ?? '').' '.
                    ($quotation->enquiry->last_name ?? '')
            );

            if (empty($customerName)) {
                $customerName = $quotation->enquiry->full_name ?? '';
            }
        }
        if (empty($customerName)) {

            $mockEnquiries = [
                '001' => ['name' => 'Rajesh Kumar', 'mobile' => '9876543210'],
                '002' => ['name' => 'Priya Sharma', 'mobile' => '9123456780'],
                '003' => ['name' => 'Suresh Yadav', 'mobile' => '9988776655'],
                '004' => ['name' => 'Amit Singh', 'mobile' => '9811223344'],
                '005' => ['name' => 'Vikram Mehta', 'mobile' => '9765432109'],
                '006' => ['name' => 'Rohan Verma', 'mobile' => '9876500006'],
                '007' => ['name' => 'Sneha Gupta', 'mobile' => '9876500007'],
                '008' => ['name' => 'Vikas Shah', 'mobile' => '9876500008'],
                '009' => ['name' => 'Priya Mehra', 'mobile' => '9876500009'],
                '010' => ['name' => 'Karan Joshi', 'mobile' => '9876500010'],
                '011' => ['name' => 'Manoj Yadav', 'mobile' => '9876500011'],
                '012' => ['name' => 'Deepak Singh', 'mobile' => '9876500012'],
                '013' => ['name' => 'Vikram Mehta', 'mobile' => '9876500013'],
                '014' => ['name' => 'Ananya Sharma', 'mobile' => '9876500014'],
                '015' => ['name' => 'Vivek Patel', 'mobile' => '9876500015'],
                '016' => ['name' => 'Kavya Nair', 'mobile' => '9876500016'],
                '017' => ['name' => 'Arjun Mehta', 'mobile' => '9876500017'],
                '018' => ['name' => 'Priya Singh', 'mobile' => '9876500018'],
            ];

            $enquiryNo = $quotation->enquiry_no;

            $customerName = $mockEnquiries[$enquiryNo]['name'] ?? '';
        }

        $customerName = $customerName ?: '-';

        $modelCode = $quotationData['model_code']
            ?? $quotation->model_code
            ?? '';

        $model = DB::table('xlr8_vehicle_model')
            ->where('code', $modelCode)
            ->first();

        $modelName = $model->name ?? $modelCode ?? '-';

        $actions = QuoteAction::where('quotation_no', $quotation->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($actions as $index => $action) {
            $action->version = $index + 1;
        }

        $financierNames = XlFinancier::pluck('name', 'id');

        foreach ($actions as $action) {

            $actionData = $action->requested ?? [];

            if (is_string($actionData)) {
                $actionData = json_decode($actionData, true) ?? [];
            }

            $history = $actionData['financier_history'] ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            /*
            * Backward compatibility:
            * Old QuoteAction records may not have financier_history.
            * So take the financier stored in the action as the starting value.
            */
            $actionFinancier = $actionData['financier'] ?? null;

            if (
                ! empty($actionFinancier) &&
                ! in_array($actionFinancier, $history, true)
            ) {
                array_unshift($history, $actionFinancier);
            }

            if ($action === $actions->last()) {

                $currentHistory =
                    $quotation->standard_data['financier_history'] ?? [];

                if (is_array($currentHistory)) {
                    $history = array_values(array_unique(
                        array_merge($history, $currentHistory),
                        SORT_REGULAR
                    ));
                }

                $currentFinancier =
                    $quotation->standard_data['financier'] ?? null;

                if (
                    ! empty($currentFinancier) &&
                    ! in_array($currentFinancier, $history, true)
                ) {
                    $history[] = $currentFinancier;
                }
            }

            $names = [];

            foreach ($history as $financierId) {

                $name = $financierNames[$financierId] ?? null;

                if ($name && ! in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }

            $action->financier_display = implode(', ', $names);
        }

        $fieldNames = [
            // Customer & Vehicle
            'customer_name' => 'Customer Name',
            'customer_mobile' => 'Mobile',
            'mobile' => 'Mobile',
            'careof' => 'Care Of',
            'careofname' => 'Care Of Name',
            'segment_code' => 'Segment',
            'model_code' => 'Model',
            'variant_code' => 'Variant',
            'color_code' => 'Color',
            'permit' => 'Permit',
            'oem_code' => 'OEM Code',

            // Price Details (Receivables)
            'ex_showroom_price' => 'Ex Showroom Price',
            'insurance_company' => 'Insurance Company',
            'insurance_covers' => 'Insurance Covers',
            'insurance_amount' => 'Insurance Amount',
            'registration_no_type' => 'Registration Type',
            'registration_category' => 'Registration Category',
            'in_house_rto' => 'In-House RTO',
            'registration_amount' => 'Registration Amount',
            'accessories' => 'Accessories',
            'accessories_amount' => 'Accessories Amount',
            'maxicare' => 'Maxicare',
            'vltd_device' => 'VLTD Device',
            'coating' => 'Coating',
            'coating_price' => 'Coating Price',
            'ppf' => 'PPF',
            'rto_yellow_tape' => 'RTO Yellow Tape',
            'kazam_charging_kit' => 'Kazam Charging Kit',
            'incidental_charges' => 'Incidental Charges',
            'shield' => 'Shield',
            'shield_price' => 'Shield Price',
            'rsa' => 'RSA',
            'rsa_amount' => 'RSA Amount',
            'fastag' => 'Fastag',
            'cod_charges' => 'COD Charges',
            'charger_swapping' => 'Charger Swapping',
            'charger_swapping_amount' => 'Charger Swapping Amount',
            'tcs' => 'TCS @1%',
            'total_receivable' => 'Total Receivable',

            // Discount Details (Group A, B, C & Others)
            'cash_scheme_oem' => 'Cash Scheme OEM',
            'cash_scheme_oem_type' => 'Cash Scheme OEM Type',
            'csd_discount' => 'CSD Discount',
            'csd_discount_type' => 'CSD Discount Type',
            'fame_subsidy' => 'Fame Subsidy',
            'fame_subsidy_type' => 'Fame Subsidy Type',
            'dealer_discount' => 'Dealer Discount',
            'dealer_discount_type' => 'Dealer Discount Type',
            'accessories_discount' => 'Accessories Discount',
            'accessories_discount_type' => 'Accessories Discount Type',
            'shield_scheme' => 'Shield Scheme',
            'shield_scheme_type' => 'Shield Scheme Type',
            'corporate_discount' => 'Corporate Discount',
            'corporate_discount_type' => 'Corporate Discount Type',
            'exchange_bonus' => 'Exchange Bonus',
            'exchange_bonus_type' => 'Exchange Bonus Type',
            'green_bonus' => 'Green Bonus',
            'green_bonus_type' => 'Green Bonus Type',
            'welcome_bonus' => 'Welcome Bonus',
            'welcome_bonus_type' => 'Welcome Bonus Type',
            'loyalty_bonus' => 'Loyalty Bonus',
            'loyalty_bonus_type' => 'Loyalty Bonus Type',
            'accessories_spl_disc' => 'Accessories Special Discount',
            'accessories_spl_disc_type' => 'Accessories Special Discount Type',
            'ceramic_discount' => 'Coating Special Discount',
            'ceramic_discount_type' => 'Coating Special Discount Type',
            'ppf_discount' => 'PPF Special Discount',
            'ppf_discount_type' => 'PPF Special Discount Type',
            'charger_swapping_discount' => 'Charger Swapping Discount',
            'charger_swapping_discount_type' => 'Charger Swapping Discount Type',
            'other_cash_discount' => 'Other Cash Discount',
            'other_cash_discount_type' => 'Other Cash Discount Type',
            'special_cash_discount' => 'Special Cash Discount',
            'special_cash_discount_type' => 'Special Cash Discount Type',

            // Summary Totals
            'total_discount' => 'Total Discount',
            'net_receivable_summary' => 'Net Receivable',
            'invoiced_discount_summary' => 'Invoiced Discount (INV)',
            'inv_oe_discount_summary' => 'Inv Disc. (OE) Total',
            'inv_d_discount_summary' => 'Inv Disc. (D) Total',
            'credit_note_discount_summary' => 'Credit Note Discount (CN)',
            'cn1_discount_summary' => 'CN1 Discount',
            'cn2_discount_summary' => 'CN2 Discount',
            'cn3_discount_summary' => 'CN3 Discount Total',
        ];

        /*
        |--------------------------------------------------------------------------
        | CHANGE GROUPS
        |--------------------------------------------------------------------------
        | These are the ONLY categories shown in:
        | "Changes after Financier"
        |--------------------------------------------------------------------------
        */

        $changeGroups = [
            'Permit' => [
                'permit',
            ],

            'Financier' => [
                'financier',
            ],

            'Scheme' => [

                // Group A
                'cash_scheme_oem',
                'cash_scheme_oem_type',
                'csd_discount',
                'csd_discount_type',
                'fame_subsidy',
                'fame_subsidy_type',

                // Dealer / Group B
                'dealer_discount',
                'dealer_discount_type',
                'accessories_discount',
                'accessories_discount_type',
                'shield_scheme',
                'shield_scheme_type',
                'corporate_discount',
                'corporate_discount_type',
                'loyalty_bonus',
                'loyalty_bonus_type',

                // Group C
                'exchange_bonus',
                'exchange_bonus_type',
                'green_bonus',
                'green_bonus_type',
                'welcome_bonus',
                'welcome_bonus_type',

                // Special discounts
                'accessories_spl_disc',
                'accessories_spl_disc_type',
                'ceramic_discount',
                'ceramic_discount_type',
                'ppf_discount',
                'ppf_discount_type',
                'charger_swapping_discount',
                'charger_swapping_discount_type',
                'other_cash_discount',
                'other_cash_discount_type',
                'special_cash_discount',
                'special_cash_discount_type',

                // Discount totals / summary
                'total_discount',
                'invoice_amount',
                'invoiced_discount_summary',
                'inv_oe_discount_summary',
                'inv_d_discount_summary',
                'credit_note_discount_summary',
                'cn1_discount_summary',
                'cn2_discount_summary',
                'cn3_discount_summary',
            ],

            'Price' => [

                'ex_showroom_price',

                'insurance_company',
                'insurance_covers',
                'insurance_amount',

                'registration_no_type',
                'registration_category',
                'in_house_rto',
                'registration_amount',

                'accessories',
                'accessories_amount',

                'maxicare',

                'vltd_device',

                'coating',
                'coating_price',

                'ppf',

                'rto_yellow_tape',

                'kazam_charging_kit',

                'incidental_charges',

                'shield',
                'shield_price',

                'rsa',
                'rsa_amount',

                'fastag',

                'cod_charges',

                'charger_swapping',
                'charger_swapping_amount',

                'tcs',

                'total_receivable',
            ],

            'Customer' => [
                'customer_name',
                'customer_mobile',
                'mobile',
                'careof',
                'careofname',
            ],

            'Vehicle' => [
                'segment_code',
                'model_code',
                'variant_code',
                'color_code',
            ],
        ];

        // Values format karne ke liye safe helper function (Arrays & Objects handle karne ke liye)
        $formatValue = function ($key, $val) {
            if ($val === null || $val === '' || $val === 'N/A') {
                return '-';
            }

            // In-House RTO Radio format
            if ($key === 'in_house_rto') {
                return ($val == '1' || $val === 1) ? 'Yes' : 'No';
            }

            // Care of type format
            if ($key === 'careof') {
                return [
                    1 => 'Son of',
                    2 => 'Daughter of',
                    3 => 'Married to',
                    4 => 'Guardian Name',
                ][$val] ?? $val;
            }

            // Non-array direct string
            if (! is_array($val)) {
                return (string) $val;
            }

            // 1. Insurance Covers Array: [{"name": "OD", "price": 50000}]
            if ($key === 'insurance_covers' || (! empty($val) && is_array(reset($val)))) {
                return collect($val)->map(function ($item) {
                    if (is_array($item)) {
                        $name = $item['name'] ?? '';
                        $price = isset($item['price']) && $item['price'] !== '' ? ' (₹'.number_format((float) $item['price'], 2).')' : '';

                        return trim($name.$price);
                    }

                    return (string) $item;
                })->filter()->implode(', ');
            }

            // 2. Accessories List Array: ["AT00298", "AS20059"]
            return implode(', ', array_filter($val, fn ($v) => ! is_array($v)));
        };

        // System internal fields jinhe comparison me ignore karna hai
        $ignoredFields = ['_token', '_method', 'insurance_covers_data', 'financier_history'];

        /*
        |--------------------------------------------------------------------------
        | Detect change groups for each history revision
        |--------------------------------------------------------------------------
        |
        | V1 = quotation creation
        | V2 = changes from V1 -> V2
        | V3 = changes from V2 -> V3
        | etc.
        |
        */

        foreach ($actions as $index => $action) {

            /*
            |--------------------------------------------------------------------------
            | First revision = creation, so no "after financier" changes
            |--------------------------------------------------------------------------
            */

            if ($index === 0) {
                $action->change_groups = '';

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Previous quotation snapshot
            |--------------------------------------------------------------------------
            */

            $previousAction = $actions[$index - 1];

            $oldData = $previousAction->requested ?? [];
            $newData = $action->requested ?? [];

            if (is_string($oldData)) {
                $oldData = json_decode($oldData, true) ?? [];
            }

            if (is_string($newData)) {
                $newData = json_decode($newData, true) ?? [];
            }

            $changedGroups = [];

            /*
            |--------------------------------------------------------------------------
            | Compare all six groups
            |--------------------------------------------------------------------------
            */

            foreach ($changeGroups as $groupName => $fields) {

                $groupChanged = false;

                foreach ($fields as $field) {

                    $oldValue = $oldData[$field] ?? '';
                    $newValue = $newData[$field] ?? '';

                    /*
                    * Normalize arrays so accessories / insurance covers
                    * are compared by their actual contents.
                    */
                    $oldFormatted = $formatValue($field, $oldValue);
                    $newFormatted = $formatValue($field, $newValue);

                    if (
                        trim((string) $oldFormatted) !==
                        trim((string) $newFormatted)
                    ) {
                        $groupChanged = true;
                        break;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Add group only once
                |--------------------------------------------------------------------------
                */

                if ($groupChanged) {
                    $changedGroups[] = $groupName;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Final display value
            |--------------------------------------------------------------------------
            */

            $action->change_groups = implode(', ', $changedGroups);
        }

        /*
        |--------------------------------------------------------------------------
        | ONLY FOR DISPLAY
        | Latest version first
        |--------------------------------------------------------------------------
        */
        $actions = $actions
            ->sortByDesc(function ($action) {
                return $action->created_at;
            })
            ->values();

        return view('admin.sales.quotation.history', [
            'quotation' => $quotation,
            'actions' => $actions,
            'customerName' => $customerName ?: '-',
            'modelName' => $modelName ?: '-',
        ]);
    }

    public function historyPdf($id, $version)
    {
        if (! backpack_user()->can('SLS_QUOT_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view quotations.');
        }

        $quotation = Quotation::findOrFail($id);

        /*
            |--------------------------------------------------------------------------
            | Get historical version
            |--------------------------------------------------------------------------
            | Version 1 = oldest action
            | Version 2 = second action
            | etc.
            |--------------------------------------------------------------------------
            */
        $actions = QuoteAction::where('quotation_no', $quotation->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $action = $actions->get(((int) $version) - 1);

        if (! $action) {
            abort(404, 'Quotation revision not found.');
        }

        /*
            |--------------------------------------------------------------------------
            | Historical quotation data
            |--------------------------------------------------------------------------
            */
        $quotationData = $action->requested ?? [];

        if (is_string($quotationData)) {
            $quotationData = json_decode($quotationData, true) ?? [];
        }

        $financierNames = XlFinancier::pluck('name', 'id');

        /*
        |--------------------------------------------------------------------------
        | PDF should show ONLY the latest/current financier
        |--------------------------------------------------------------------------
        | Do NOT use financier_history here because that is only for
        | displaying the accumulated financiers in the History page.
        |--------------------------------------------------------------------------
        */

        $latestFinancier = $quotationData['financier'] ?? null;

        /*
        * If this is the latest version and the financier was changed
        * without creating a new QuoteAction, use the current quotation financier.
        */
        if ($action === $actions->last()) {
            $latestFinancier =
                $quotation->standard_data['financier'] ?? $latestFinancier;
        }

        $quotationData['financier_display'] =
            $financierNames[$latestFinancier] ?? '-';

        /*
            |--------------------------------------------------------------------------
            | Enquiry
            |--------------------------------------------------------------------------
            */
        $enquiryNo = $quotationData['enquiry_no']
            ?? $quotation->enquiry_no;

        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])
            ->where('id', $enquiryNo)
            ->orWhere('enquiry_no', $enquiryNo)
            ->first();

        if (! $selectedEnquiry) {
            // Final fallback to the quotation's original enquiry
            $selectedEnquiry = Enquiry::with([
                'segment',
                'model',
                'variant',
                'color',
            ])->find($quotation->enquiry_no);
        }

        /*
        |--------------------------------------------------------------------------
        | Customer Name
        |--------------------------------------------------------------------------
        | Historical action may contain an empty customer_name.
        | Always fallback to the actual enquiry.
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER NAME - HISTORY PDF
        |--------------------------------------------------------------------------
        */

        $customerName = trim(
            (string) ($quotationData['customer_name'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | 1. Historical quotation data
        |--------------------------------------------------------------------------
        */
        if (empty($customerName)) {
            $customerName = trim(
                (string) ($quotationData['customerName'] ?? '')
            );
        }

        if (empty($customerName)) {

            $currentQuotationData = $quotation->standard_data ?? [];

            if (is_string($currentQuotationData)) {
                $currentQuotationData = json_decode(
                    $currentQuotationData,
                    true
                ) ?? [];
            }

            if (is_array($currentQuotationData)) {

                $customerName = trim(
                    (string) (
                        $currentQuotationData['customer_name']
                        ?? $currentQuotationData['customerName']
                        ?? ''
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Actual enquiry
        |--------------------------------------------------------------------------
        */
        if (empty($customerName) && $selectedEnquiry) {

            $customerName = trim(
                ($selectedEnquiry->first_name ?? '').' '.
                ($selectedEnquiry->last_name ?? '')
            );

            if (empty($customerName)) {
                $customerName = trim(
                    (string) ($selectedEnquiry->full_name ?? '')
                );
            }

            if (empty($customerName)) {
                $customerName = trim(
                    (string) ($selectedEnquiry->customer_name ?? '')
                );
            }

            if (empty($customerName)) {
                $customerName = trim(
                    (string) ($selectedEnquiry->name ?? '')
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Final fallback
        |--------------------------------------------------------------------------
        */
        if (empty($customerName)) {
            $customerName = '-';
        }

        $quotationData['customer_name'] = $customerName;

        /*
        |--------------------------------------------------------------------------
        | Vehicle Codes & Names
        |--------------------------------------------------------------------------
        */

        $segmentCode = $selectedEnquiry?->segment_code
            ?? $quotationData['segment_code']
            ?? '';

        $modelCode = $selectedEnquiry?->model_code
            ?? $quotationData['model_code']
            ?? '';

        $variantCode = $selectedEnquiry?->variant_code
            ?? $quotationData['variant_code']
            ?? '';

        $colorCode = $selectedEnquiry?->color_code
            ?? $quotationData['color_code']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | Fetch Vehicle Names
        |--------------------------------------------------------------------------
        */

        $segmentName = '';

        if (! empty($segmentCode)) {
            $segment = DB::table('xlr8_vehicle_segment')
                ->where('code', $segmentCode)
                ->first();

            $segmentName =
                $segment->name
                ?? $selectedEnquiry?->segment
                ?? $quotationData['segment']
                ?? $segmentCode;
        }

        $modelName = '';

        if (! empty($modelCode)) {
            $model = DB::table('xlr8_vehicle_model')
                ->where('code', $modelCode)
                ->first();

            $modelName =
                $model->name
                ?? $selectedEnquiry?->model
                ?? $quotationData['model']
                ?? $modelCode;
        }

        $variantName = '';

        if (! empty($variantCode)) {
            $variant = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->first();

            $variantName =
                $variant->display_name
                ?? $variant->custom_name
                ?? $variant->oem_name
                ?? $selectedEnquiry?->variant
                ?? $quotationData['variant']
                ?? $variantCode;
        }

        $colorName = '';

        if (! empty($colorCode)) {
            $color = DB::table('xlr8_vehicle_color')
                ->where('code', $colorCode)
                ->first();

            $colorName =
                $color->name
                ?? $selectedEnquiry?->color
                ?? $quotationData['color']
                ?? $colorCode;
        }

        /*
        |--------------------------------------------------------------------------
        | Dropdown maps
        |--------------------------------------------------------------------------
        */
        $insurance_type_map = [
            1 => 'Nil Dep',
            2 => 'Higher',
        ];

        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

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

        /*
    |--------------------------------------------------------------------------
    | Accessories
    |--------------------------------------------------------------------------
    */
        $accessoryList = Accessory::where('status', 1)
            ->orderBy('item')
            ->get();

        $financiers = XlFinancier::select('id', 'name', 'short_name')
            ->get();
        /*
    |--------------------------------------------------------------------------
    | Group A
    |--------------------------------------------------------------------------
    */
        $groupASelected = 'cash_scheme_oem';

        if (
            ! empty($quotationData['csd_discount']) &&
            ! in_array($quotationData['csd_discount'], ['0', '0.00', 'N/A'])
        ) {
            $groupASelected = 'csd_discount';
        } elseif (
            ! empty($quotationData['fame_subsidy']) &&
            ! in_array($quotationData['fame_subsidy'], ['0', '0.00', 'N/A'])
        ) {
            $groupASelected = 'fame_subsidy';
        } elseif (
            ! empty($quotationData['cash_scheme_oem']) &&
            ! in_array($quotationData['cash_scheme_oem'], ['0', '0.00', 'N/A'])
        ) {
            $groupASelected = 'cash_scheme_oem';
        }

        /*
    |--------------------------------------------------------------------------
    | Group B
    |--------------------------------------------------------------------------
    */
        $groupBSelected = 'corporate_discount';

        /*
    |--------------------------------------------------------------------------
    | Group C
    |--------------------------------------------------------------------------
    */
        $groupCSelected = 'exchange_bonus';

        if (
            ! empty($quotationData['loyalty_bonus']) &&
            ! in_array($quotationData['loyalty_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'loyalty_bonus';
        } elseif (
            ! empty($quotationData['green_bonus']) &&
            ! in_array($quotationData['green_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'green_bonus';
        } elseif (
            ! empty($quotationData['welcome_bonus']) &&
            ! in_array($quotationData['welcome_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'welcome_bonus';
        } elseif (
            ! empty($quotationData['exchange_bonus']) &&
            ! in_array($quotationData['exchange_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'exchange_bonus';
        }

        /*
    |--------------------------------------------------------------------------
    | Open same create.blade.php in VIEW MODE
    |--------------------------------------------------------------------------
    */
        return view('admin.sales.quotation.create', [
            'quotation' => $quotation,
            'quotationData' => $quotationData,
            'selectedEnquiry' => $selectedEnquiry,

            // Vehicle codes
            'segmentCode' => $segmentCode,
            'modelCode' => $modelCode,
            'variantCode' => $variantCode,
            'colorCode' => $colorCode,

            // Vehicle names
            'segmentName' => $segmentName,
            'modelName' => $modelName,
            'variantName' => $variantName,
            'colorName' => $colorName,

            'insurance_type_map' => $insurance_type_map,
            'registration_type_map' => $registration_type_map,
            'reg_no_type_map' => $reg_no_type_map,

            'accessoryList' => $accessoryList,
            'financiers' => $financiers,
            'groupASelected' => $groupASelected,
            'groupBSelected' => $groupBSelected,
            'groupCSelected' => $groupCSelected,
            'permit_map' => $permit_map,

            'viewMode' => true,
            'revisionPdf' => true,
            'revisionNumber' => $version,
        ]);
    }

    //
    public function preview($id)
    {
        if (! backpack_user()->can('SLS_QUOT_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view quotations.');
        }

        $quotation = Quotation::with('enquiry')
            ->where('id', $id)  // ✅ Use id
            ->firstOrFail();

        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])->find($quotation->enquiry_no);

        // ============================================================
        // VEHICLE DETAILS FOR PREVIEW
        // ============================================================

        $segmentCode = $selectedEnquiry?->segment_code ?? '';
        $modelCode = $selectedEnquiry?->model_code ?? '';
        $variantCode = $selectedEnquiry?->variant_code ?? '';
        $colorCode = $selectedEnquiry?->color_code ?? '';

        $segment = null;
        $model = null;
        $variant = null;
        $color = null;

        if (! empty($segmentCode)) {
            $segment = DB::table('xlr8_vehicle_segment')
                ->where('code', $segmentCode)
                ->first();
        }

        if (! empty($modelCode)) {
            $model = DB::table('xlr8_vehicle_model')
                ->where('code', $modelCode)
                ->first();
        }

        if (! empty($variantCode)) {
            $variant = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->first();
        }

        if (! empty($colorCode)) {
            $color = DB::table('xlr8_vehicle_color')
                ->where('model_code', $modelCode)
                ->where('variant_code', $variantCode)
                ->where('code', $colorCode)
                ->first();
        }

        // Display names
        $segmentName = $segment->name
            ?? $selectedEnquiry?->segment
            ?? $segmentCode
            ?? '';

        $modelName = $model->name
            ?? $selectedEnquiry?->model
            ?? $modelCode
            ?? '';

        $variantName = $variant->display_name
            ?? $variant->custom_name
            ?? $variant->oem_name
            ?? $selectedEnquiry?->variant
            ?? $variantCode
            ?? '';

        $colorName = $color->name
            ?? $selectedEnquiry?->color
            ?? $colorCode
            ?? '';

        $insurance_type_map = [
            1 => 'Nil Dep',
            2 => 'Higher',
        ];

        $registration_type_map = [
            '0' => 'Tax Only',
            '1' => 'TRC + Tax',
            '2' => 'TRC Only',
            '3' => 'Exempted',
        ];

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

        $accessoryList = Accessory::where('status', 1)->orderBy('item')->get();
        $quotationData = $quotation->standard_data ?? [];

        $groupASelected = 'cash_scheme_oem';
        if (! empty($quotationData['csd_discount']) && ! in_array($quotationData['csd_discount'], ['0', '0.00', 'N/A'])) {
            $groupASelected = 'csd_discount';
        } elseif (! empty($quotationData['fame_subsidy']) && ! in_array($quotationData['fame_subsidy'], ['0', '0.00', 'N/A'])) {
            $groupASelected = 'fame_subsidy';
        } elseif (! empty($quotationData['cash_scheme_oem']) && ! in_array($quotationData['cash_scheme_oem'], ['0', '0.00', 'N/A'])) {
            $groupASelected = 'cash_scheme_oem';
        }

        $groupBSelected = 'corporate_discount';

        $groupCSelected = 'exchange_bonus';
        if (! empty($quotationData['loyalty_bonus']) && ! in_array($quotationData['loyalty_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'loyalty_bonus';
        } elseif (! empty($quotationData['green_bonus']) && ! in_array($quotationData['green_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'green_bonus';
        } elseif (! empty($quotationData['welcome_bonus']) && ! in_array($quotationData['welcome_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'welcome_bonus';
        } elseif (! empty($quotationData['exchange_bonus']) && ! in_array($quotationData['exchange_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'exchange_bonus';
        }

        $financiers = XlFinancier::select('id', 'name', 'short_name')
            ->get();

        return view('admin.sales.quotation.create', [
            'quotation' => $quotation,
            'quotationData' => $quotationData,
            'selectedEnquiry' => $selectedEnquiry,

            // Vehicle codes
            'segmentCode' => $segmentCode,
            'modelCode' => $modelCode,
            'variantCode' => $variantCode,
            'colorCode' => $colorCode,

            // Vehicle names
            'segmentName' => $segmentName,
            'modelName' => $modelName,
            'variantName' => $variantName,
            'colorName' => $colorName,

            'insurance_type_map' => $insurance_type_map,
            'registration_type_map' => $registration_type_map,
            'permit_map' => $permit_map,
            'reg_no_type_map' => $reg_no_type_map,
            'accessoryList' => $accessoryList,
            'financiers' => $financiers,
            'groupASelected' => $groupASelected,
            'groupBSelected' => $groupBSelected,
            'groupCSelected' => $groupCSelected,
            'viewMode' => true,
        ]);
    }
}
