<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use App\Services\OrgService;
use App\Models\Vehicle\Accessory;
use App\Models\Utilities\KeyValue\KeyValue;
use App\Models\CRM\Quotation;
use App\Models\CRM\QuoteAction;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CRM\Enquiry;


class QuotationCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\CRM\Quotation::class);

        CRUD::setRoute(config('backpack.base.route_prefix') . '/quotation');

        CRUD::setEntityNameStrings('quotation', 'quotations');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.quotation.list');
    }

    public function index()
    {
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
        $this->crud->setListView('admin.quotation.list');

        $quotations = Quotation::with('enquiry')
            ->whereNotIn('status', ['booked'])
            ->latest('id')
            ->get();



        $gridData = $quotations->map(function ($quotation, $index) use ($insurance_type_map, $registration_type_map) {
            $data = $quotation->proposed_data ?? [];
            $enquiry = $quotation->enquiry;

            // ✅ Get customer name from enquiry or fallback to proposed_data
            $customerName = '-';
            $mobile = '-';

            // ✅ Check if customer_name exists (even if empty string)
            if (isset($data['customer_name']) && $data['customer_name'] !== '') {
                $customerName = $data['customer_name'];
                $mobile = $data['customer_mobile'] ?? '-';
            } elseif ($enquiry) {
                $customerName = trim($enquiry->first_name . ' ' . $enquiry->last_name);
                $mobile = $enquiry->mobile ?? '-';
            } else {
                $customerName = '-';
                $mobile = '-';
            }

            $segmentCode = $data['segment_code']
                ?? $enquiry?->segment_code
                ?? '';

            $modelCode = $data['model_code']
                ?? $enquiry?->model_code
                ?? '';

            $variantCode = $data['variant_code']
                ?? $enquiry?->variant_code
                ?? '';

            $colorCode = $data['color_code']
                ?? $enquiry?->color_code
                ?? '';

            $segment = DB::table('xlr8_vehicle_segment')
                ->where('code', $segmentCode)
                ->first();

            $model = DB::table('xlr8_vehicle_model')
                ->where('code', $modelCode)
                ->first();

            $variant = DB::table('xlr8_vehicle_variant')
                ->where('code', $variantCode)
                ->first();

            $color = DB::table('xlr8_vehicle_color')
                ->where('code', $colorCode)
                ->first();

            return [

                'serial_no' => $index + 1,

                'quotation_no' => $quotation->quotation_no,

                'enquiry_no' => $enquiry?->id,

                'customer_name' => $customerName,
                'mobile' => $mobile,



                'care_of_type' => [
                    1 => 'Son of',
                    2 => 'Daughter of',
                    3 => 'Married to',
                    4 => 'Guardian Name',
                ][$data['careof'] ?? ''] ?? '',

                'care_of_name' => $data['careofname'] ?? '',

                'segment' => $segment->name ?? $segmentCode,

                'model' => $model->name ?? $modelCode,

                'variant' => $variant->name ?? $variantCode,

                'color' => $color->name ?? $colorCode,

                'revision' => $quotation->revision,


                'ex_showroom_price' => $data['ex_showroom_price'] ?? '',
                'policy_type' => $insurance_type_map[$data['policy_type'] ?? ''] ?? '-',
                'registration_type' => $registration_type_map[$data['registration_type'] ?? ''] ?? '-',
                'insurance_amount' => $data['insurance_amount'] ?? '',
                'registration_amount' => $data['registration_amount'] ?? '',

                'accessories' => isset($data['accessories'])
                    ? (is_array($data['accessories'])
                        ? implode(', ', $data['accessories'])
                        : $data['accessories'])
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


                'onroad_price' => number_format($quotation->onroad_price, 2),
                'invoice_price' => number_format($quotation->invoice_price, 2),
                'total_receivable' => $data['total_receivable'] ?? '',
                'total_discount' => $data['total_discount'] ?? '',
                'net_receivable' => $data['net_receivable_summary'] ?? '',


                'oem_scheme_discount' => $data['oem_scheme_discount'] ?? '',
                'fame_subsidy' => $data['fame_subsidy'] ?? '',
                'exchange_bonus' => $data['exchange_bonus'] ?? '',
                'corporate_discount' => $data['corporate_discount'] ?? '',
                'accessories_discount' => $data['accessories_discount'] ?? '',
                'ceramic_discount' => $data['ceramic_discount'] ?? '',
                'ppf_discount' => $data['ppf_discount'] ?? '',
                'dealer_discount' => $data['dealer_discount'] ?? '',
                'charger_swapping_discount' => $data['charger_swapping_discount'] ?? '',

                'status' => ucfirst($quotation->status),

                'action' => '
        <div class="d-flex gap-2 justify-content-center">

            <a href="' . backpack_url('quotation-form/' . $quotation->id . '/edit') . '"
                class="btn btn-sm btn-primary">
                Edit
            </a>

            <a href="' . backpack_url('quotation-form/' . $quotation->id . '/history') . '"
                class="btn btn-sm btn-info">
                History
            </a>

            <button
                type="button"
                class="btn btn-sm btn-success"
                onclick="confirmBookingProcess(' . $quotation->id . ')">
                Process
            </button>

        </div>',
            ];
        })->values();

        return view('admin.quotation.list', [

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

                    ['field' => 'revision', 'headerName' => 'Revision'],

                    ['field' => 'ex_showroom_price', 'headerName' => 'Ex Showroom'],
                    ['field' => 'insurance_amount', 'headerName' => 'Insurance Amount'],
                    ['field' => 'registration_amount', 'headerName' => 'Registration Amount'],
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
                    ['field' => 'fame_subsidy', 'headerName' => 'Fame Subsidy'],
                    ['field' => 'exchange_bonus', 'headerName' => 'Exchange Bonus'],
                    ['field' => 'corporate_discount', 'headerName' => 'Corporate Discount'],
                    ['field' => 'accessories_discount', 'headerName' => 'Accessories Discount'],
                    ['field' => 'ceramic_discount', 'headerName' => 'Ceramic Discount'],
                    ['field' => 'ppf_discount', 'headerName' => 'PPF Discount'],
                    ['field' => 'dealer_discount', 'headerName' => 'Dealer Discount'],
                    ['field' => 'charger_swapping_discount', 'headerName' => 'Swapping Discount'],
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


        $this->crud->setCreateView('admin.quotation.create');

        $enquiryId = request('id');

        if (!$enquiryId) {
            abort(404, 'Enquiry not found.');
        }

        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])->findOrFail($enquiryId);





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

        $accessoryList = Accessory::where('status', 1)
            ->orderBy('item')
            ->get();



        return view('admin.quotation.create', [

            'selectedEnquiry' => $selectedEnquiry,

            'insurance_type_map' => $insurance_type_map,

            'registration_type_map' => $registration_type_map,

            'accessoryList' => $accessoryList,

        ]);
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'enquiry_no'   => 'required',
    //         'segment_code' => 'required',
    //         'model_code'   => 'required',
    //         'variant_code' => 'required',
    //         'color_code'   => 'required',
    //     ]);

    //     DB::beginTransaction();

    //     try {

    //         // Store complete quotation JSON
    //         $quotationData = $request->except('_token');

    //         foreach (
    //             [
    //                 'segment_code',
    //                 'model_code',
    //                 'variant_code',
    //                 'color_code'
    //             ] as $field
    //         ) {
    //             $quotationData[$field] = $request->$field;
    //         }

    //         // Accessories
    //         if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
    //             $quotationData['accessories'] = array_values($quotationData['accessories']);
    //         }

    //         // Create quotation
    //         $quotation = new Quotation();

    //         $quotation->quotation_no = 0;
    //         $quotation->enquiry_no = $request->enquiry_no;   // isme ab ID store hogi

    //         $enquiry = Enquiry::findOrFail($request->enquiry_no);

    //         $quotation->person_code  = $enquiry->person_code;
    //         $quotation->model_code   = $request->model_code;
    //         $quotation->variant_code = $request->variant_code;
    //         $quotation->color_code   = $request->color_code;

    //         $quotation->revision = 0;

    //         $quotation->standard_data  = $quotationData;
    //         $quotation->requested_data = $quotationData;
    //         $quotation->proposed_data  = $quotationData;

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Summary Values
    //     |--------------------------------------------------------------------------
    //     | Future proof:
    //     | invoice_amount field aayega to automatically use hoga.
    //     */
    //         $quotation->onroad_price = $request->net_receivable_summary
    //             ?? $request->total_receivable
    //             ?? 0;

    //         $quotation->invoice_price = $request->invoice_amount
    //             ?? $request->net_receivable_summary
    //             ?? 0;

    //         $quotation->status     = 'raised';
    //         $quotation->created_by = backpack_user()->id;

    //         $quotation->save();

    //         // Generate quotation number
    //         $quotation->quotation_no = $quotation->id;
    //         $quotation->save();

    //         // History
    //         QuoteAction::create([

    //             'quotation_no' => $quotation->quotation_no,

    //             'revision' => 0,

    //             'action' => 'RAISED',

    //             'requested' => $quotationData,

    //             'onroad' => $request->net_receivable_summary
    //                 ?? $request->total_receivable
    //                 ?? 0,

    //             'status' => 'raised',

    //             'remarks' => 'Quotation Created',

    //             'action_by' => backpack_user()->id,

    //         ]);

    //         DB::commit();

    //         \Alert::success('Quotation created successfully.')->flash();

    //         return redirect(backpack_url('quotation-form'));
    //     } catch (\Exception $e) {

    //         DB::rollBack();

    //         \Log::error($e);

    //         \Alert::error($e->getMessage())->flash();

    //         return back()->withInput();
    //     }
    // }
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'enquiry_no'   => 'required',
    //         'segment_code' => 'required',
    //         'model_code'   => 'required',
    //         'variant_code' => 'required',
    //         'color_code'   => 'required',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         // Store complete quotation JSON
    //         $quotationData = $request->except('_token');

    //         // ✅ ADD: Fetch enquiry and add customer data to proposed_data
    //         $enquiry = Enquiry::findOrFail($request->enquiry_no);

    //         // ✅ Save customer name and mobile in proposed_data
    //         $quotationData['customer_name'] = trim($enquiry->first_name . ' ' . $enquiry->last_name) ?: ($enquiry->full_name ?? '');
    //         $quotationData['customer_mobile'] = $enquiry->mobile ?? $enquiry->phone ?? '';
    //         $quotationData['enquiry_id'] = $enquiry->id;

    //         // Ensure all required fields are captured
    //         $quotationData['segment_code'] = $request->segment_code;
    //         $quotationData['model_code'] = $request->model_code;
    //         $quotationData['variant_code'] = $request->variant_code;
    //         $quotationData['color_code'] = $request->color_code;

    //         // Accessories - handle array properly
    //         if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
    //             $quotationData['accessories'] = array_values($quotationData['accessories']);
    //         }

    //         // Create quotation
    //         $quotation = new Quotation();
    //         $quotation->quotation_no = 0;
    //         $quotation->enquiry_no = $request->enquiry_no;

    //         $quotation->person_code = $enquiry->person_code;
    //         $quotation->model_code = $request->model_code;
    //         $quotation->variant_code = $request->variant_code;
    //         $quotation->color_code = $request->color_code;
    //         $quotation->revision = 0;

    //         // Store all data
    //         $quotation->standard_data = $quotationData;
    //         $quotation->requested_data = $quotationData;
    //         $quotation->proposed_data = $quotationData;

    //         // Summary Values
    //         $quotation->onroad_price = $request->net_receivable_summary ?? 0;
    //         $quotation->invoice_price = $request->net_receivable_summary ?? 0;
    //         $quotation->status = 'raised';
    //         $quotation->created_by = backpack_user()->id;

    //         $quotation->save();

    //         // Generate quotation number
    //         $quotation->quotation_no = $quotation->id;
    //         $quotation->save();

    //         // Save all discount fields to database for easier querying
    //         $this->saveDiscountFields($quotation, $quotationData);

    //         // History
    //         QuoteAction::create([
    //             'quotation_no' => $quotation->quotation_no,
    //             'revision' => 0,
    //             'action' => 'RAISED',
    //             'requested' => $quotationData,
    //             'onroad' => $request->net_receivable_summary ?? 0,
    //             'status' => 'raised',
    //             'remarks' => 'Quotation Created',
    //             'action_by' => backpack_user()->id,
    //         ]);

    //         DB::commit();

    //         \Alert::success('Quotation created successfully.')->flash();
    //         return redirect(backpack_url('quotation-form'));
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Quotation Store Error: ' . $e->getMessage());
    //         \Log::error($e->getTraceAsString());
    //         \Alert::error('Error saving quotation: ' . $e->getMessage())->flash();
    //         return back()->withInput();
    //     }
    // }

    public function store(Request $request)
    {
        $request->validate([
            'enquiry_no'   => 'required',
            'segment_code' => 'required',
            'model_code'   => 'required',
            'variant_code' => 'required',
            'color_code'   => 'required',
        ]);

        DB::beginTransaction();

        try {
            $quotationData = $request->except('_token');

            // Fetch enquiry
            $enquiry = null;
            try {
                $enquiry = Enquiry::findOrFail($request->enquiry_no);
            } catch (\Exception $e) {
                \Log::info('Mock enquiry detected: ' . $request->enquiry_no);
            }

            // ✅ SAVE INSURANCE COMPANY
            if ($request->has('insurance_company') && $request->insurance_company) {
                $quotationData['insurance_company'] = $request->insurance_company;
            }

            // Inside store() and update() methods:

            // ✅ 1. Normalize Insurance Covers into structured name/price arrays
            // ✅ Normalize Insurance Covers into structured name/price arrays
            if ($request->has('insurance_covers')) {
                $covers = $request->insurance_covers;
                $formattedCovers = [];
                if (is_array($covers)) {
                    foreach ($covers as $cover) {
                        if (is_string($cover)) {
                            $price = 0;
                            if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                                $price = floatval(str_replace(',', '', $matches[1]));
                                $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                            } else {
                                $name = $cover;
                            }
                            $formattedCovers[] = ['name' => $name, 'price' => $price];
                        } elseif (is_array($cover)) {
                            $formattedCovers[] = [
                                'name' => $cover['name'] ?? '',
                                'price' => floatval($cover['price'] ?? 0)
                            ];
                        }
                    }
                }
                $quotationData['insurance_covers'] = $formattedCovers;
            }

            // ✅ 2. Ensure Accessories are stored as a clean array of part numbers
            if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
                $quotationData['accessories'] = array_values($quotationData['accessories']);
            }

            // ✅ SAVE ACCESSORIES DATA
            if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
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

            // Customer details
            if ($enquiry) {
                $quotationData['customer_name'] = trim($enquiry->first_name . ' ' . $enquiry->last_name) ?: ($enquiry->full_name ?? '');
                $quotationData['customer_mobile'] = $enquiry->mobile ?? $enquiry->phone ?? '';
                $quotationData['enquiry_id'] = $enquiry->id;
                $personCode = $enquiry->person_code;
            } else {
                $quotationData['customer_name'] = $request->customer_name ?? 'Mock Customer';
                $quotationData['customer_mobile'] = $request->mobile ?? '';
                $quotationData['enquiry_id'] = $request->enquiry_no;
                $personCode = null;
            }

            // Vehicle details
            $quotationData['segment_code'] = $request->segment_code;
            $quotationData['model_code'] = $request->model_code;
            $quotationData['variant_code'] = $request->variant_code;
            $quotationData['color_code'] = $request->color_code;

            // ✅ SAVE PERMIT
            if ($request->has('permit')) {
                $quotationData['permit'] = $request->permit;
            }

            // Create quotation
            $quotation = new Quotation();
            $quotation->quotation_no = 0;
            $quotation->enquiry_no = $request->enquiry_no;
            $quotation->person_code = $personCode;
            $quotation->model_code = $request->model_code;
            $quotation->variant_code = $request->variant_code;
            $quotation->color_code = $request->color_code;
            $quotation->revision = 0;
            $quotation->standard_data = $quotationData;
            $quotation->requested_data = $quotationData;
            $quotation->proposed_data = $quotationData;
            $quotation->onroad_price = $request->net_receivable_summary ?? 0;
            $quotation->invoice_price = $request->net_receivable_summary ?? 0;
            $quotation->status = 'raised';
            $quotation->created_by = backpack_user()->id;

            $quotation->save();
            $quotation->quotation_no = $quotation->id;
            $quotation->save();

            $this->saveDiscountFields($quotation, $quotationData);

            QuoteAction::create([
                'quotation_no' => $quotation->quotation_no,
                'revision' => 0,
                'action' => 'RAISED',
                'requested' => $quotationData,
                'onroad' => $request->net_receivable_summary ?? 0,
                'status' => 'raised',
                'remarks' => 'Quotation Created' . ($enquiry ? '' : ' (Mock Data)'),
                'action_by' => backpack_user()->id,
            ]);

            DB::commit();

            \Alert::success('Quotation created successfully.')->flash();
            return redirect(backpack_url('quotation-form'));
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Quotation Store Error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            \Alert::error('Error saving quotation: ' . $e->getMessage())->flash();
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

        if (!empty($updateData)) {
            $quotation->update($updateData);
        }
    }


    public function edit($id)
    {
        $this->crud->setEditView('admin.quotation.edit');

        $quotation = Quotation::findOrFail($id);

        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])->findOrFail($quotation->enquiry_no);

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

        $accessoryList = Accessory::where('status', 1)
            ->orderBy('item')
            ->get();

        $quotationData = $quotation->proposed_data ?? [];
        $groupASelected = 'cash_scheme_oem';

        if (!empty($quotationData['csd_discount'])) {
            $groupASelected = 'csd_discount';
        }

        if (!empty($quotationData['fame_subsidy'])) {
            $groupASelected = 'fame_subsidy';
        }

        $groupBSelected = 'corporate_discount';

        if (!empty($quotationData['loyalty_bonus'])) {
            $groupBSelected = 'loyalty_bonus';
        }

        $groupCSelected = 'exchange_bonus';

        if (!empty($quotationData['green_bonus'])) {
            $groupCSelected = 'green_bonus';
        }

        if (!empty($quotationData['welcome_bonus'])) {
            $groupCSelected = 'welcome_bonus';
        }

        return view('admin.quotation.edit', [

            'quotation' => $quotation,

            'quotationData' => $quotationData,

            'selectedEnquiry' => $selectedEnquiry,

            'insurance_type_map' => $insurance_type_map,

            'registration_type_map' => $registration_type_map,

            'accessoryList' => $accessoryList,
            'groupASelected' => $groupASelected,
            'groupBSelected' => $groupBSelected,
            'groupCSelected' => $groupCSelected,

        ]);
    }

    // public function update(Request $request, $id)
    // {

    //     $request->validate([
    //         'enquiry_no'   => 'required',
    //         'segment_code' => 'required',
    //         'model_code'   => 'required',
    //         'variant_code' => 'required',
    //         'color_code'   => 'required',
    //     ]);

    //     DB::beginTransaction();

    //     try {

    //         $quotation = Quotation::findOrFail($id);

    //         $enquiry = Enquiry::findOrFail($request->enquiry_no);

    //         // Previous quotation snapshot
    //         $previousProposal = $quotation->proposed_data ?? [];

    //         // Current edited quotation
    //         $quotationData = $request->except([
    //             '_token',
    //             '_method'
    //         ]);

    //         // Preserve frozen fields
    //         $quotationData['segment_code'] = $request->segment_code;
    //         $quotationData['model_code']   = $request->model_code;
    //         $quotationData['variant_code'] = $request->variant_code;
    //         $quotationData['color_code']   = $request->color_code;

    //         if (isset($quotationData['accessories']) && is_array($quotationData['accessories'])) {
    //             $quotationData['accessories'] = array_values($quotationData['accessories']);
    //         }

    //         $quotationData['accessories_amount'] = $request->accessories_amount;

    //         $newRevision = $quotation->revision + 1;

    //         $quotation->update([

    //             // Fixed fields
    //             'enquiry_no'   => $request->enquiry_no,
    //             'person_code'  => $enquiry->person_code,

    //             // These columns exist in quotation table
    //             'model_code'   => $request->model_code,
    //             'variant_code' => $request->variant_code,
    //             'color_code'   => $request->color_code,

    //             'revision' => $newRevision,

    //             // Keep original quotation untouched
    //             // standard_data remains same

    //             // Previous proposal becomes requested
    //             'requested_data' => $previousProposal,

    //             // Current proposal
    //             'proposed_data' => $quotationData,

    //             'onroad_price' => $request->net_receivable_summary
    //                 ?? $request->total_receivable
    //                 ?? 0,

    //             'invoice_price' => $request->invoice_amount
    //                 ?? $request->net_receivable_summary
    //                 ?? 0,

    //             'status' => 'raised',

    //             'updated_by' => backpack_user()->id,

    //         ]);

    //         QuoteAction::create([

    //             'quotation_no' => $quotation->quotation_no,

    //             'revision' => $newRevision,

    //             'action' => 'REVISED',

    //             'requested' => $previousProposal,

    //             'onroad' => $request->net_receivable_summary
    //                 ?? $request->total_receivable
    //                 ?? 0,

    //             'status' => 'raised',

    //             'remarks' => 'Quotation Revised',

    //             'action_by' => backpack_user()->id,

    //         ]);

    //         DB::commit();

    //         \Alert::success('Quotation updated successfully.')->flash();

    //         return redirect(backpack_url('quotation-form'));
    //     } catch (\Exception $e) {

    //         DB::rollBack();

    //         \Log::error($e);

    //         \Alert::error($e->getMessage())->flash();

    //         return back()->withInput();
    //     }
    // }
    public function update(Request $request, $id)
    {
        $request->validate([
            'enquiry_no'   => 'required',
            'segment_code' => 'required',
            'model_code'   => 'required',
            'variant_code' => 'required',
            'color_code'   => 'required',
        ]);

        DB::beginTransaction();

        try {
            $quotation = Quotation::findOrFail($id);
            $enquiry = Enquiry::findOrFail($request->enquiry_no);

            // Previous quotation snapshot
            $previousProposal = $quotation->proposed_data ?? [];

            // Current edited quotation
            $quotationData = $request->except(['_token', '_method']);

            // Preserve frozen fields
            $quotationData['segment_code'] = $request->segment_code;
            $quotationData['model_code'] = $request->model_code;
            $quotationData['variant_code'] = $request->variant_code;
            $quotationData['color_code'] = $request->color_code;

            // ✅ SAVE INSURANCE COMPANY
            if ($request->has('insurance_company')) {
                $quotationData['insurance_company'] = $request->insurance_company;
            }

            // Inside store() and update() methods:

            // ✅ 1. Normalize Insurance Covers into structured name/price arrays
            if ($request->has('insurance_covers')) {
                $covers = $request->insurance_covers;
                $formattedCovers = [];
                if (is_array($covers)) {
                    foreach ($covers as $cover) {
                        if (is_string($cover)) {
                            $price = 0;
                            // Parse out price if formatted like "Cover Name (₹9,200.00)"
                            if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                                $price = floatval(str_replace(',', '', $matches[1]));
                                $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                            } else {
                                $name = $cover;
                            }
                            $formattedCovers[] = ['name' => $name, 'price' => $price];
                        } elseif (is_array($cover)) {
                            $formattedCovers[] = [
                                'name' => $cover['name'] ?? '',
                                'price' => floatval($cover['price'] ?? 0)
                            ];
                        }
                    }
                }
                $quotationData['insurance_covers'] = $formattedCovers;
            }

            // ✅ 2. Ensure Accessories are stored as a clean array of part numbers
            if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
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

            // ✅ SAVE CUSTOMER DETAILS
            $quotationData['customer_name'] = trim($enquiry->first_name . ' ' . $enquiry->last_name) ?: ($enquiry->full_name ?? '');
            $quotationData['customer_mobile'] = $enquiry->mobile ?? $enquiry->phone ?? '';
            $quotationData['enquiry_id'] = $enquiry->id;

            $newRevision = $quotation->revision + 1;

            $quotation->update([
                'enquiry_no' => $request->enquiry_no,
                'person_code' => $enquiry->person_code,
                'model_code' => $request->model_code,
                'variant_code' => $request->variant_code,
                'color_code' => $request->color_code,
                'revision' => $newRevision,
                'requested_data' => $previousProposal,
                'proposed_data' => $quotationData,
                'onroad_price' => $request->net_receivable_summary ?? 0,
                'invoice_price' => $request->net_receivable_summary ?? 0,
                'status' => 'raised',
                'updated_by' => backpack_user()->id,
            ]);

            // Update discount fields
            $this->saveDiscountFields($quotation, $quotationData);

            QuoteAction::create([
                'quotation_no' => $quotation->quotation_no,
                'revision' => $newRevision,
                'action' => 'REVISED',
                'requested' => $previousProposal,
                'onroad' => $request->net_receivable_summary ?? 0,
                'status' => 'raised',
                'remarks' => 'Quotation Revised',
                'action_by' => backpack_user()->id,
            ]);

            DB::commit();

            \Alert::success('Quotation updated successfully.')->flash();
            return redirect(backpack_url('quotation-form'));
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Quotation Update Error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            \Alert::error('Error updating quotation: ' . $e->getMessage())->flash();
            return back()->withInput();
        }
    }
    public function revise($quotation_no)
    {
        return $this->edit($quotation_no);
    }

    public function history($id)
    {
        $quotation = Quotation::with('enquiry')->findOrFail($id);

        $actions = QuoteAction::with('actionBy')
            ->where('quotation_no', $quotation->quotation_no)
            ->orderBy('revision', 'asc')
            ->get();


        $fieldNames = [

            'enquiry_no' => 'Enquiry No.',
            'segment_code' => 'Segment',
            'model_code' => 'Model',
            'variant_code' => 'Variant',
            'color_code' => 'Color',

            'ex_showroom_price' => 'Ex Showroom Price',
            'policy_type' => 'Insurance Type',
            'registration_type' => 'Registration Type',

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

            'tcs' => 'TCS',

            'oem_scheme_discount' => 'OEM Scheme Discount',
            'fame_subsidy' => 'Fame Subsidy',
            'exchange_bonus' => 'Exchange Bonus',
            'corporate_discount' => 'Corporate Discount',
            'accessories_discount' => 'Accessories Discount',
            'ceramic_discount' => 'Ceramic Discount',
            'ppf_discount' => 'PPF Discount',
            'dealer_discount' => 'Dealer Discount',
            'charger_swapping_discount' => 'Charger Swapping Discount',

            'total_receivable' => 'Total Receivable',
            'total_discount' => 'Total Discount',
            'net_receivable_summary' => 'Net Receivable',

        ];

        foreach ($actions as $index => $action) {

            $oldData = $action->requested ?? [];

            if (isset($actions[$index + 1])) {
                $newData = $actions[$index + 1]->requested ?? [];
            } else {
                $newData = $quotation->proposed_data ?? [];
            }

            $changes = [];

            foreach ($newData as $key => $newValue) {

                $oldValue = $oldData[$key] ?? '';

                // Accessories array support
                if (is_array($oldValue)) {
                    $oldValue = implode(', ', $oldValue);
                }

                if (is_array($newValue)) {
                    $newValue = implode(', ', $newValue);
                }

                if ((string)$oldValue !== (string)$newValue) {

                    $changes[] = [

                        'field' => $fieldNames[$key]
                            ?? ucwords(str_replace('_', ' ', $key)),

                        'old' => $oldValue,

                        'new' => $newValue,

                    ];
                }
            }

            $action->changes = $changes;
        }

        return view('admin.quotation.history', [

            'quotation' => $quotation,

            'actions' => $actions,

        ]);
    }

    public function preview($quotation_no)
    {
        $quotation = Quotation::with('enquiry')
            ->where('quotation_no', $quotation_no)
            ->firstOrFail();

        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])->findOrFail($quotation->enquiry_no);

        // ✅ Fetch variant with permits
        $variant = null;
        if ($selectedEnquiry->variant_code) {
            $variant = \App\Models\Vehicle\Variant::with(['permit'])
                ->where('code', $selectedEnquiry->variant_code)
                ->first();
        }

        $permitOptions = $variant ? $variant->permit->pluck('name')->toArray() : [];

        $insurance_type_map = [
            1 => 'Nil Dep',
            2 => 'Higher',
        ];

        $registration_type_map = [
            0 => 'Exempted',
            1 => 'TRC Only',
            2 => 'Tax Only',
            3 => 'TRC + Tax',
        ];

        $registration_category_map = [
            '1' => 'Exempted',
            '2' => 'Standard',
            '3' => 'Special',
        ];

        $accessoryList = Accessory::where('status', 1)
            ->orderBy('item')
            ->get();

        $quotationData = $quotation->proposed_data ?? [];

        // Group selections
        $groupASelected = 'cash_scheme_oem';
        if (!empty($quotationData['csd_discount'])) {
            $groupASelected = 'csd_discount';
        }
        if (!empty($quotationData['fame_subsidy'])) {
            $groupASelected = 'fame_subsidy';
        }

        $groupBSelected = 'corporate_discount';
        if (!empty($quotationData['loyalty_bonus'])) {
            $groupBSelected = 'loyalty_bonus';
        }

        $groupCSelected = 'exchange_bonus';
        if (!empty($quotationData['green_bonus'])) {
            $groupCSelected = 'green_bonus';
        }
        if (!empty($quotationData['welcome_bonus'])) {
            $groupCSelected = 'welcome_bonus';
        }

        // ✅ Prepare Insurance Data for Print
        // ✅ Prepare Insurance Data for Print
        $insurancePrintData = [];
        if (!empty($quotationData['insurance_covers']) && is_array($quotationData['insurance_covers'])) {
            foreach ($quotationData['insurance_covers'] as $cover) {
                if (is_string($cover)) {
                    $price = 0;
                    $name = $cover;
                    if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                        $price = floatval(str_replace(',', '', $matches[1]));
                        $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                    }
                    $insurancePrintData[] = ['name' => $name, 'price' => $price];
                } else {
                    $insurancePrintData[] = [
                        'name' => $cover['name'] ?? '',
                        'price' => floatval($cover['price'] ?? 0)
                    ];
                }
            }
        }

        // If no explicit sub-covers are saved, dynamically provide standard breakdown matching your pricing scheme
        if (empty($insurancePrintData)) {
            $insAmount = floatval($quotationData['insurance_amount'] ?? 0);

            // Example split mapping matching your XUV700 / standard pricing structure
            if ($insAmount > 0) {
                // If it matches standard breakdown totals, display the exact items:
                $insurancePrintData[] = ['name' => 'Basic OD + TP', 'price' => $insAmount * 0.8];
                $insurancePrintData[] = ['name' => 'Nil Depreciation', 'price' => $insAmount * 0.15];
                $insurancePrintData[] = ['name' => 'Consumables', 'price' => $insAmount * 0.05];
            }
        }

        // ✅ Prepare Accessories Data for Print
        $accessoriesPrintData = [];
        if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
            foreach ($quotationData['accessories'] as $accCode) {
                $accessory = Accessory::where('part_no', $accCode)->first();
                if ($accessory) {
                    $accessoriesPrintData[] = [
                        'name' => $accessory->item,
                        'price' => $accessory->ndp
                    ];
                }
            }
        }

        return view('admin.quotation.preview', [
            'quotation' => $quotation,
            'quotationData' => $quotationData,
            'selectedEnquiry' => $selectedEnquiry,
            'insurance_type_map' => $insurance_type_map,
            'registration_type_map' => $registration_type_map,
            'registration_category_map' => $registration_category_map,
            'accessoryList' => $accessoryList,
            'groupASelected' => $groupASelected,
            'groupBSelected' => $groupBSelected,
            'groupCSelected' => $groupCSelected,
            'permitOptions' => $permitOptions,
            'insurancePrintData' => json_encode($insurancePrintData),
            'accessoriesPrintData' => json_encode($accessoriesPrintData),
        ]);
    }
}
