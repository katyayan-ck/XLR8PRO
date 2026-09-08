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
use App\Models\Module\Booking\XlFinancier;


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

    // public function index()
    // {
    //     $insurance_type_map = [
    //         1 => 'Standard',
    //         2 => 'Nil Dep',
    //         3 => 'Base',
    //         4 => 'Higher',
    //     ];


    //     $registration_type_map = [
    //         '0' => 'Tax Only',
    //         '1' => 'TRC + Tax',
    //         '2' => 'TRC Only',
    //         '3' => 'Exempted',


    //     ];
    //     $this->crud->setListView('admin.quotation.list');

    //     $quotations = Quotation::with('enquiry')
    //         ->whereNotIn('status', ['booked'])
    //         ->latest('id')
    //         ->get();



    //     $gridData = $quotations->map(function ($quotation, $index) use ($insurance_type_map, $registration_type_map) {
    //         $data = $quotation->standard_data ?? [];
    //         $enquiry = $quotation->enquiry;

    //         // ✅ Get customer name from enquiry or fallback to proposed_data
    //         $customerName = '-';
    //         $mobile = '-';

    //         // ✅ Check if customer_name exists (even if empty string)
    //         if (isset($data['customer_name']) && $data['customer_name'] !== '') {
    //             $customerName = $data['customer_name'];
    //             $mobile = $data['customer_mobile'] ?? '-';
    //         } elseif ($enquiry) {
    //             $customerName = trim($enquiry->first_name . ' ' . $enquiry->last_name);
    //             $mobile = $enquiry->mobile ?? '-';
    //         } else {
    //             $customerName = '-';
    //             $mobile = '-';
    //         }

    //         $segmentCode = $data['segment_code']
    //             ?? $enquiry?->segment_code
    //             ?? '';

    //         $modelCode = $data['model_code']
    //             ?? $enquiry?->model_code
    //             ?? '';

    //         $variantCode = $data['variant_code']
    //             ?? $enquiry?->variant_code
    //             ?? '';

    //         $colorCode = $data['color_code']
    //             ?? $enquiry?->color_code
    //             ?? '';

    //         $segment = DB::table('xlr8_vehicle_segment')
    //             ->where('code', $segmentCode)
    //             ->first();

    //         $model = DB::table('xlr8_vehicle_model')
    //             ->where('code', $modelCode)
    //             ->first();

    //         $variant = DB::table('xlr8_vehicle_variant')
    //             ->where('code', $variantCode)
    //             ->first();

    //         $color = DB::table('xlr8_vehicle_color')
    //             ->where('code', $colorCode)
    //             ->first();

    //         return [

    //             'serial_no' => $index + 1,

    //             'quotation_no' => $quotation->quotation_no,

    //             'enquiry_no' => $enquiry?->id,

    //             'customer_name' => $customerName,
    //             'mobile' => $mobile,



    //             'care_of_type' => [
    //                 1 => 'Son of',
    //                 2 => 'Daughter of',
    //                 3 => 'Married to',
    //                 4 => 'Guardian Name',
    //             ][$data['careof'] ?? ''] ?? '',

    //             'care_of_name' => $data['careofname'] ?? '',

    //             'segment' => $segment->name ?? $segmentCode,

    //             'model' => $model->name ?? $modelCode,

    //             'variant' => $variant->name ?? $variantCode,

    //             'color' => $color->name ?? $colorCode,

    //             'revision' => $quotation->revision,


    //             'ex_showroom_price' => $data['ex_showroom_price'] ?? '',
    //             'policy_type' => $insurance_type_map[$data['policy_type'] ?? ''] ?? '-',
    //             'registration_type' => $registration_type_map[$data['registration_type'] ?? ''] ?? '-',
    //             'insurance_amount' => $data['insurance_amount'] ?? '',
    //             'registration_amount' => $data['registration_amount'] ?? '',

    //             'accessories' => isset($data['accessories'])
    //                 ? (is_array($data['accessories'])
    //                     ? implode(', ', $data['accessories'])
    //                     : $data['accessories'])
    //                 : '',

    //             'accessories_amount' => $data['accessories_amount'] ?? '',
    //             'maxicare' => $data['maxicare'] ?? '',
    //             'vltd_device' => $data['vltd_device'] ?? '',
    //             'coating' => $data['coating'] ?? '',
    //             'coating_price' => $data['coating_price'] ?? '',
    //             'ppf' => $data['ppf'] ?? '',
    //             'rto_yellow_tape' => $data['rto_yellow_tape'] ?? '',
    //             'kazam_charging_kit' => $data['kazam_charging_kit'] ?? '',
    //             'incidental_charges' => $data['incidental_charges'] ?? '',
    //             'shield' => $data['shield'] ?? '',
    //             'shield_price' => $data['shield_price'] ?? '',
    //             'rsa' => $data['rsa'] ?? '',
    //             'rsa_amount' => $data['rsa_amount'] ?? '',
    //             'fastag' => $data['fastag'] ?? '',
    //             'cod_charges' => $data['cod_charges'] ?? '',
    //             'charger_swapping' => $data['charger_swapping'] ?? '',
    //             'charger_swapping_amount' => $data['charger_swapping_amount'] ?? '',
    //             'tcs' => $data['tcs'] ?? '',


    //             'onroad_price' => number_format($quotation->onroad_price, 2),
    //             'invoice_price' => number_format($quotation->invoice_price, 2),
    //             'total_receivable' => $data['total_receivable'] ?? '',
    //             'total_discount' => $data['total_discount'] ?? '',
    //             'net_receivable' => $data['net_receivable_summary'] ?? '',


    //             'oem_scheme_discount' => $data['oem_scheme_discount'] ?? '',
    //             'fame_subsidy' => $data['fame_subsidy'] ?? '',
    //             'exchange_bonus' => $data['exchange_bonus'] ?? '',
    //             'corporate_discount' => $data['corporate_discount'] ?? '',
    //             'accessories_discount' => $data['accessories_discount'] ?? '',
    //             'ceramic_discount' => $data['ceramic_discount'] ?? '',
    //             'ppf_discount' => $data['ppf_discount'] ?? '',
    //             'dealer_discount' => $data['dealer_discount'] ?? '',
    //             'charger_swapping_discount' => $data['charger_swapping_discount'] ?? '',

    //             'status' => ucfirst($quotation->status),

    //             'action' => '
    //     <div class="d-flex gap-2 justify-content-center">

    //         <a href="' . backpack_url('quotation-form/' . $quotation->id . '/edit') . '"
    //             class="btn btn-sm btn-primary">
    //             Edit
    //         </a>

    //         <a href="' . backpack_url('quotation-form/' . $quotation->id . '/history') . '"
    //             class="btn btn-sm btn-info">
    //             History
    //         </a>

    //         <button
    //             type="button"
    //             class="btn btn-sm btn-success"
    //             onclick="confirmBookingProcess(' . $quotation->id . ')">
    //             Process
    //         </button>

    //     </div>',
    //         ];
    //     })->values();

    //     return view('admin.quotation.list', [

    //         'title' => 'Quotation Listing',

    //         'gridConfig' => [

    //             'columns' => [

    //                 ['field' => 'serial_no', 'headerName' => 'S.No.'],
    //                 ['field' => 'quotation_no', 'headerName' => 'Quotation No.'],
    //                 ['field' => 'enquiry_no', 'headerName' => 'Enquiry No.'],
    //                 ['field' => 'customer_name', 'headerName' => 'Customer'],
    //                 ['field' => 'mobile', 'headerName' => 'Mobile'],
    //                 ['field' => 'care_of_type', 'headerName' => 'Care Of'],
    //                 ['field' => 'care_of_name', 'headerName' => 'Care Of Name'],
    //                 ['field' => 'segment', 'headerName' => 'Segment'],
    //                 ['field' => 'model', 'headerName' => 'Model'],
    //                 ['field' => 'variant', 'headerName' => 'Variant'],
    //                 ['field' => 'color', 'headerName' => 'Color'],

    //                 ['field' => 'revision', 'headerName' => 'Revision'],

    //                 ['field' => 'ex_showroom_price', 'headerName' => 'Ex Showroom'],
    //                 ['field' => 'insurance_amount', 'headerName' => 'Insurance Amount'],
    //                 ['field' => 'registration_amount', 'headerName' => 'Registration Amount'],
    //                 ['field' => 'policy_type', 'headerName' => 'Insurance'],
    //                 ['field' => 'registration_type', 'headerName' => 'Registration'],

    //                 ['field' => 'accessories', 'headerName' => 'Accessories'],
    //                 ['field' => 'accessories_amount', 'headerName' => 'Accessories Amount'],

    //                 ['field' => 'maxicare', 'headerName' => 'Maxicare'],
    //                 ['field' => 'vltd_device', 'headerName' => 'VLTD'],
    //                 ['field' => 'coating', 'headerName' => 'Coating'],
    //                 ['field' => 'coating_price', 'headerName' => 'Coating Price'],
    //                 ['field' => 'ppf', 'headerName' => 'PPF'],
    //                 ['field' => 'rto_yellow_tape', 'headerName' => 'Yellow Tape'],
    //                 ['field' => 'kazam_charging_kit', 'headerName' => 'Kazam Kit'],
    //                 ['field' => 'incidental_charges', 'headerName' => 'Incidental'],
    //                 ['field' => 'shield', 'headerName' => 'Shield'],
    //                 ['field' => 'shield_price', 'headerName' => 'Shield Price'],
    //                 ['field' => 'rsa', 'headerName' => 'RSA'],
    //                 ['field' => 'rsa_amount', 'headerName' => 'RSA Amount'],
    //                 ['field' => 'fastag', 'headerName' => 'Fastag'],
    //                 ['field' => 'cod_charges', 'headerName' => 'COD Charges'],
    //                 ['field' => 'charger_swapping', 'headerName' => 'Charger Swapping'],
    //                 ['field' => 'charger_swapping_amount', 'headerName' => 'Swapping Amount'],
    //                 ['field' => 'tcs', 'headerName' => 'TCS'],

    //                 ['field' => 'onroad_price', 'headerName' => 'On Road Price'],
    //                 ['field' => 'oem_scheme_discount', 'headerName' => 'OEM Discount'],
    //                 ['field' => 'fame_subsidy', 'headerName' => 'Fame Subsidy'],
    //                 ['field' => 'exchange_bonus', 'headerName' => 'Exchange Bonus'],
    //                 ['field' => 'corporate_discount', 'headerName' => 'Corporate Discount'],
    //                 ['field' => 'accessories_discount', 'headerName' => 'Accessories Discount'],
    //                 ['field' => 'ceramic_discount', 'headerName' => 'Ceramic Discount'],
    //                 ['field' => 'ppf_discount', 'headerName' => 'PPF Discount'],
    //                 ['field' => 'dealer_discount', 'headerName' => 'Dealer Discount'],
    //                 ['field' => 'charger_swapping_discount', 'headerName' => 'Swapping Discount'],
    //                 ['field' => 'total_discount', 'headerName' => 'Total Discount'],
    //                 ['field' => 'net_receivable', 'headerName' => 'Net Receivable'],
    //                 ['field' => 'invoice_price', 'headerName' => 'Invoice Price'],

    //                 ['field' => 'status', 'headerName' => 'Status'],
    //                 ['field' => 'action', 'headerName' => 'Action'],
    //             ],

    //             'data' => $gridData,
    //         ],
    //     ]);
    // }
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

        $reg_no_type_map = [
            '1' => 'Regular',
            '2' => 'BH Series',
            '3' => 'Special Number',
        ];

        // Mock Enquiries Reference for fallback customer details
        $mockEnquiries = [
            "001" => ["name" => "Rajesh Kumar", "mobile" => "9876543210"],
            "002" => ["name" => "Priya Sharma", "mobile" => "9123456780"],
            "003" => ["name" => "Suresh Yadav", "mobile" => "9988776655"],
            "004" => ["name" => "Amit Singh", "mobile" => "9811223344"],
            "005" => ["name" => "Vikram Mehta", "mobile" => "9765432109"],
            "006" => ["name" => "Rohan Verma", "mobile" => "9876500006"],
            "007" => ["name" => "Sneha Gupta", "mobile" => "9876500007"],
            "008" => ["name" => "Vikas Shah", "mobile" => "9876500008"],
            "009" => ["name" => "Priya Mehra", "mobile" => "9876500009"],
            "010" => ["name" => "Karan Joshi", "mobile" => "9876500010"],
            "011" => ["name" => "Manoj Yadav", "mobile" => "9876500011"],
            "012" => ["name" => "Deepak Singh", "mobile" => "9876500012"],
            "013" => ["name" => "Vikram Mehta", "mobile" => "9876500013"],
            "014" => ["name" => "Ananya Sharma", "mobile" => "9876500014"],
            "015" => ["name" => "Vivek Patel", "mobile" => "9876500015"],
            "016" => ["name" => "Kavya Nair", "mobile" => "9876500016"],
            "017" => ["name" => "Arjun Mehta", "mobile" => "9876500017"],
            "018" => ["name" => "Priya Singh", "mobile" => "9876500018"],
            "019" => ["name" => "Dr. Ananya Reddy", "mobile" => "9876500019"],
            "020" => ["name" => "Mohan Transport", "mobile" => "9876500020"],
            "021" => ["name" => "Sakshi Enterprises", "mobile" => "9876500021"],
        ];

        $this->crud->setListView('admin.quotation.list');

        $quotations = Quotation::with('enquiry')
            ->whereNotIn('status', ['booked'])
            ->latest('id')
            ->get();

        $gridData = $quotations->map(function ($quotation, $index) use ($insurance_type_map, $registration_type_map, $reg_no_type_map, $mockEnquiries) {
            $data = $quotation->standard_data ?? [];
            $enquiry = $quotation->enquiry;
            $enquiryNo = $data['enquiry_no'] ?? $quotation->enquiry_no ?? '';

            // ✅ Extract Customer Name
            $customerName = '';
            if (!empty($data['customer_name'])) {
                $customerName = $data['customer_name'];
            } elseif ($enquiry && !empty(trim($enquiry->first_name . ' ' . $enquiry->last_name))) {
                $customerName = trim($enquiry->first_name . ' ' . $enquiry->last_name);
            } elseif (isset($mockEnquiries[$enquiryNo])) {
                $customerName = $mockEnquiries[$enquiryNo]['name'];
            } else {
                $customerName = '-';
            }

            // ✅ Extract Mobile
            $mobile = '';
            if (!empty($data['customer_mobile'])) {
                $mobile = $data['customer_mobile'];
            } elseif (!empty($data['mobile'])) {
                $mobile = $data['mobile'];
            } elseif ($enquiry && !empty($enquiry->mobile)) {
                $mobile = $enquiry->mobile;
            } elseif (isset($mockEnquiries[$enquiryNo])) {
                $mobile = $mockEnquiries[$enquiryNo]['mobile'];
            } else {
                $mobile = '-';
            }

            $segmentCode = $data['segment_code'] ?? $enquiry?->segment_code ?? '';
            $modelCode = $data['model_code'] ?? $enquiry?->model_code ?? '';
            $variantCode = $data['variant_code'] ?? $enquiry?->variant_code ?? '';
            $colorCode = $data['color_code'] ?? $enquiry?->color_code ?? '';

            $segment = DB::table('xlr8_vehicle_segment')->where('code', $segmentCode)->first();
            $model = DB::table('xlr8_vehicle_model')->where('code', $modelCode)->first();
            $variant = DB::table('xlr8_vehicle_variant')->where('code', $variantCode)->first();
            $color = DB::table('xlr8_vehicle_color')->where('code', $colorCode)->first();

            return [
                'serial_no' => $index + 1,
                'quotation_no' => $quotation->quotation_no,
                'enquiry_no' => $enquiryNo ?: ($enquiry?->id ?? '-'),
                'customer_name' => $customerName,
                'mobile' => $mobile,
                'care_of_type' => [
                    1 => 'Son of',
                    2 => 'Daughter of',
                    3 => 'Married to',
                    4 => 'Guardian Name',
                ][$data['careof'] ?? ''] ?? '-',
                'care_of_name' => $data['careofname'] ?? '-',
                'segment' => $segment->name ?? $segmentCode,
                'model' => $model->name ?? $modelCode,
                'variant' => $variant->name ?? $variantCode,
                'color' => $color->name ?? $colorCode,
                'permit' => $data['permit'] ?? '-',
                'oem_code' => $data['oem_code'] ?? optional($enquiry)->oem_code ?? '-',
                'revision' => $quotation->revision,

                // Receivables
                'ex_showroom_price' => $data['ex_showroom_price'] ?? '',
                'insurance_company' => $data['insurance_company'] ?? '-',
                'policy_type' => $insurance_type_map[$data['policy_type'] ?? ''] ?? '-',
                'insurance_amount' => $data['insurance_amount'] ?? '',
                'registration_no_type' => $reg_no_type_map[$data['registration_no_type'] ?? ''] ?? ($data['registration_no_type'] ?? '-'),
                'registration_category' => $registration_type_map[$data['registration_category'] ?? ''] ?? ($data['registration_category'] ?? '-'),
                'in_house_rto' => (isset($data['in_house_rto']) && $data['in_house_rto'] == '1') ? 'Yes' : 'No',
                'registration_type' => $registration_type_map[$data['registration_type'] ?? ''] ?? '-',
                'registration_amount' => $data['registration_amount'] ?? '',

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

                // Totals & Prices
                'onroad_price' => number_format((float)$quotation->onroad_price, 2),
                'invoice_price' => number_format((float)$quotation->invoice_price, 2),
                'total_receivable' => $data['total_receivable'] ?? '',
                'total_discount' => $data['total_discount'] ?? '',
                'net_receivable' => $data['net_receivable_summary'] ?? '',

                // Group A, B, C & Static Discounts
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

                // EXACT UNTOUCHED ACTION BUTTONS
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
                onclick="confirmBookingProcess(' . $quotation->id . ', ' . ($quotation->booking_id ?? 'null') . ')">
                Booking
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


        $this->crud->setCreateView('admin.quotation.create');

        $bookingId = request('booking_id');

if ($bookingId) {

    $booking = \App\Models\Module\Booking\Booking::findOrFail($bookingId);

    if (empty($booking->enq_no)) {
        abort(404, 'Enquiry not associated with this booking.');
    }

    $enquiry = Enquiry::where(
        'enquiry_no',
        $booking->enq_no
    )->first();

    if (!$enquiry) {
        abort(404, 'Associated enquiry not found.');
    }

    $enquiryId = $enquiry->id;

} else {

    $enquiryId = request('id');

    if (!$enquiryId) {
        abort(404, 'Enquiry not found.');
    }
}

        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])->findOrFail($enquiryId);

        $permit_map = [
    '1'  => 'Private - U/C (4 Wheeler)',
    '2'  => 'Private - BH (4 Wheeler)',
    '3'  => 'Private - EV (4 Wheeler)',
    '4'  => 'Goods - G (4 Wheeler)',
    '5'  => 'Goods - G 3 Ton+ (4 Wheeler)',
    '6'  => 'Goods - G (3 Wheeler)',
    '7'  => 'Goods - G EV (3 Wheeler)',
    '8'  => 'Goods - G EV (4 Wheeler)',
    '9'  => 'Taxi - T (4 Wheeler)',
    '10' => 'Taxi - T EV (4 Wheeler)',
    '11' => 'Passenger - P (3 Wheeler)',
    '12' => 'Passenger - P EV (3 Wheeler)',
    '13' => 'Ambulance (Misc.)',
];





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

        $financiers = XlFinancier::select('id', 'name', 'short_name')
            ->get();



        return view('admin.quotation.create', [
            'selectedEnquiry' => $selectedEnquiry,
            'insurance_type_map' => $insurance_type_map,
            'registration_type_map' => $registration_type_map,
            'accessoryList' => $accessoryList,
            'financiers' => $financiers,
            'permit_map' => $permit_map,
            'bookingId' => $bookingId,

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
    //         $quotation->standard_data  = $quotationData;

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

    //         return redirect(backpack_url('quotation-form/' . $quotation->id . '/edit') . '?saved=1');
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
    //         $quotation->standard_data = $quotationData;

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
    //         return redirect(backpack_url('quotation-form/' . $quotation->id . '/edit') . '?saved=1');
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

            $quotationData['charger_swapping_option'] = $request->input('charger_swapping_option');

            // Fetch enquiry
            $enquiry = null;
            try {
                $enquiry = Enquiry::findOrFail($request->enquiry_no);
            } catch (\Exception $e) {
                \Log::info('Mock enquiry detected: ' . $request->enquiry_no);
            }

            // ✅ FIX: Use insurance_covers_data instead of insurance_covers
            if ($request->has('insurance_covers_data') && !empty($request->insurance_covers_data)) {
                $coversData = json_decode($request->insurance_covers_data, true);
                if (is_array($coversData) && !empty($coversData)) {
                    $quotationData['insurance_covers'] = $coversData;
                }
            } else if ($request->has('insurance_covers')) {
                // Fallback to old method if needed
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
                    $quotationData['insurance_covers'] = $formattedCovers;
                }
            }

            // ✅ FIX: Insurance amount ko bhi sahi se set karein
            if ($request->has('insurance_amount') && !empty($request->insurance_amount)) {
                $quotationData['insurance_amount'] = $request->insurance_amount;
            }

            // ✅ SAVE INSURANCE COMPANY
            if ($request->has('insurance_company') && $request->insurance_company) {
                $quotationData['insurance_company'] = $request->insurance_company;
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


            $initialFinancier = $quotationData['financier'] ?? null;

            $quotationData['financier_history'] = [];

            if (!empty($initialFinancier)) {
                $quotationData['financier_history'][] = $initialFinancier;
            }

            // Check if this Booking already has a quotation
            if ($request->filled('booking_id')) {

                $booking = \App\Models\Module\Booking\Booking::findOrFail(
                    $request->booking_id
                );

                if (!empty($booking->quotation_id)) {
                    abort(422, 'This booking already has a quotation.');
                }
            }

            $quotation = new Quotation();
            $quotation->quotation_no = 0;
            $quotation->enquiry_no = $request->enquiry_no;
            $quotation->booking_id = $request->booking_id ?: null;
            $quotation->person_code = $personCode;
            $quotation->model_code = $request->model_code;
            $quotation->variant_code = $request->variant_code;
            $quotation->color_code = $request->color_code;

            $quotation->revision = 0;
            $quotation->standard_data = $quotationData;

            $quotation->onroad_price =
                $request->net_receivable_summary
                ?? $request->total_receivable
                ?? 0;

            $quotation->invoice_price =
                $request->invoice_amount
                ?? $request->net_receivable_summary
                ?? 0;

            $quotation->status = 'raised';
            $quotation->created_by = backpack_user()->id;

            $quotation->save();

            $quotation->quotation_no = $quotation->id;
            $quotation->save();

            // Link quotation back to Booking
            if ($request->filled('booking_id')) {

                $booking->quotation_id = $quotation->id;
                $booking->save();
            }


            $this->saveDiscountFields($quotation, $quotationData);

            QuoteAction::create([
                'quotation_no' => $quotation->quotation_no,
                'action_by'    => backpack_user()->id,
                'action'       => 'RAISED',
                'requested'    => $quotationData,
                'onroad'       => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,
                'status'       => 'raised',
                'remarks'      => 'Quotation Created',
                'created_by'   => backpack_user()->id,
            ]);

            DB::commit();

            \Alert::success('Quotation created successfully.')->flash();
            return redirect(backpack_url('quotation-form/' . $quotation->id . '/edit') . '?saved=1');
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


    // public function edit($id)
    // {
    //     $this->crud->setEditView('admin.quotation.create');
    //     $quotation = Quotation::findOrFail($id);

    //     // Fetch enquiry safely (fallback for mock data)
    //     $selectedEnquiry = Enquiry::with(['segment', 'model', 'variant', 'color'])
    //         ->find($quotation->enquiry_no);

    //     $insurance_type_map = [
    //         1 => 'Nil Dep',
    //         2 => 'Higher',
    //     ];

    //     $registration_type_map = [
    //         '0' => 'Tax Only',
    //         '1' => 'TRC + Tax',
    //         '2' => 'TRC Only',
    //         '3' => 'Exempted',
    //     ];

    //     $reg_no_type_map = [
    //         '1' => 'Regular',
    //         '2' => 'BH Series',
    //         '3' => 'Special Number',
    //     ];

    //     $accessoryList = Accessory::where('status', 1)->orderBy('item')->get();
    //     $quotationData = $quotation->standard_data ?? [];

    //     $quotationData['enquiry_no'] = !empty($quotationData['enquiry_no'])
    //         ? $quotationData['enquiry_no']
    //         : $quotation->enquiry_no;

    //     $quotationData['segment_code'] = !empty($quotationData['segment_code'])
    //         ? $quotationData['segment_code']
    //         : $quotation->segment_code;

    //     $quotationData['model_code'] = !empty($quotationData['model_code'])
    //         ? $quotationData['model_code']
    //         : $quotation->model_code;

    //     $quotationData['variant_code'] = !empty($quotationData['variant_code'])
    //         ? $quotationData['variant_code']
    //         : $quotation->variant_code;

    //     $quotationData['color_code'] = !empty($quotationData['color_code'])
    //         ? $quotationData['color_code']
    //         : $quotation->color_code;

    //     $quotationData['onroad_price'] = !empty($quotationData['onroad_price'])
    //         ? $quotationData['onroad_price']
    //         : $quotation->onroad_price;

    //     $quotationData['invoice_price'] = !empty($quotationData['invoice_price'])
    //         ? $quotationData['invoice_price']
    //         : $quotation->invoice_price;

    //     $groupASelected = 'cash_scheme_oem';
    //     if (!empty($quotationData['csd_discount']) && !in_array($quotationData['csd_discount'], ['0', '0.00', 'N/A'])) {
    //         $groupASelected = 'csd_discount';
    //     } elseif (!empty($quotationData['fame_subsidy']) && !in_array($quotationData['fame_subsidy'], ['0', '0.00', 'N/A'])) {
    //         $groupASelected = 'fame_subsidy';
    //     } elseif (!empty($quotationData['cash_scheme_oem']) && !in_array($quotationData['cash_scheme_oem'], ['0', '0.00', 'N/A'])) {
    //         $groupASelected = 'cash_scheme_oem';
    //     }

    //     $groupBSelected = 'corporate_discount';

    //     $groupCSelected = 'exchange_bonus';
    //     if (!empty($quotationData['loyalty_bonus']) && !in_array($quotationData['loyalty_bonus'], ['0', 'N/A', '0.00'])) {
    //         $groupCSelected = 'loyalty_bonus';
    //     } elseif (!empty($quotationData['green_bonus']) && !in_array($quotationData['green_bonus'], ['0', 'N/A', '0.00'])) {
    //         $groupCSelected = 'green_bonus';
    //     } elseif (!empty($quotationData['welcome_bonus']) && !in_array($quotationData['welcome_bonus'], ['0', 'N/A', '0.00'])) {
    //         $groupCSelected = 'welcome_bonus';
    //     } elseif (!empty($quotationData['exchange_bonus']) && !in_array($quotationData['exchange_bonus'], ['0', 'N/A', '0.00'])) {
    //         $groupCSelected = 'exchange_bonus';
    //     }

    //     return view('admin.quotation.create', [
    //         'quotation' => $quotation,
    //         'quotationData' => $quotationData,
    //         'selectedEnquiry' => $selectedEnquiry,
    //         'insurance_type_map' => $insurance_type_map,
    //         'registration_type_map' => $registration_type_map,
    //         'reg_no_type_map' => $reg_no_type_map,
    //         'accessoryList' => $accessoryList,
    //         'groupASelected' => $groupASelected,
    //         'groupBSelected' => $groupBSelected,
    //         'groupCSelected' => $groupCSelected,
    //     ]);
    // }
    public function edit($id)
    {
        $this->crud->setEditView('admin.quotation.create');

        /*
    |--------------------------------------------------------------------------
    | 1. Load quotation
    |--------------------------------------------------------------------------
    */
        $quotation = Quotation::findOrFail($id);

        /*
    |--------------------------------------------------------------------------
    | 2. Load original enquiry
    |--------------------------------------------------------------------------
    | We use quotation->enquiry_no first because quotation should open with
    | the enquiry against which it was originally created.
    |--------------------------------------------------------------------------
    */
        $selectedEnquiry = Enquiry::with([
            'segment',
            'model',
            'variant',
            'color',
        ])->find($quotation->enquiry_no);

        /*
    |--------------------------------------------------------------------------
    | 3. Dropdown maps
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

        $permit_map = [
    '1'  => 'Private - U/C (4 Wheeler)',
    '2'  => 'Private - BH (4 Wheeler)',
    '3'  => 'Private - EV (4 Wheeler)',
    '4'  => 'Goods - G (4 Wheeler)',
    '5'  => 'Goods - G 3 Ton+ (4 Wheeler)',
    '6'  => 'Goods - G (3 Wheeler)',
    '7'  => 'Goods - G EV (3 Wheeler)',
    '8'  => 'Goods - G EV (4 Wheeler)',
    '9'  => 'Taxi - T (4 Wheeler)',
    '10' => 'Taxi - T EV (4 Wheeler)',
    '11' => 'Passenger - P (3 Wheeler)',
    '12' => 'Passenger - P EV (3 Wheeler)',
    '13' => 'Ambulance (Misc.)',
];

        $reg_no_type_map = [
            '1' => 'Regular',
            '2' => 'BH Series',
            '3' => 'Special Number',
        ];

        /*
    |--------------------------------------------------------------------------
    | 4. Accessories
    |--------------------------------------------------------------------------
    */
        $accessoryList = Accessory::where('status', 1)
            ->orderBy('item')
            ->get();

        $financiers = XlFinancier::select('id', 'name', 'short_name')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | 5. Get saved quotation snapshot
    |--------------------------------------------------------------------------
    */
        $quotationData = $quotation->standard_data ?? [];

        if (!is_array($quotationData)) {
            $quotationData = [];
        }

        /*
    |--------------------------------------------------------------------------
    | 6. IMPORTANT:
    | Restore fields from quotation table when standard_data is missing.
    |--------------------------------------------------------------------------
    |
    | Old quotations may have these values in quotation columns but not
    | inside standard_data.
    |
    */

        // Enquiry
        $quotationData['enquiry_no'] = !empty($quotationData['enquiry_no'])
            ? $quotationData['enquiry_no']
            : $quotation->enquiry_no;

        /*
    |--------------------------------------------------------------------------
    | Vehicle codes
    |--------------------------------------------------------------------------
    */

        $quotationData['segment_code'] = !empty($quotationData['segment_code'])
            ? $quotationData['segment_code']
            : ($quotation->segment_code
                ?? $selectedEnquiry?->segment_code
                ?? '');

        $quotationData['model_code'] = !empty($quotationData['model_code'])
            ? $quotationData['model_code']
            : ($quotation->model_code
                ?? $selectedEnquiry?->model_code
                ?? '');

        $quotationData['variant_code'] = !empty($quotationData['variant_code'])
            ? $quotationData['variant_code']
            : ($quotation->variant_code
                ?? $selectedEnquiry?->variant_code
                ?? '');

        $quotationData['color_code'] = !empty($quotationData['color_code'])
            ? $quotationData['color_code']
            : ($quotation->color_code
                ?? $selectedEnquiry?->color_code
                ?? '');

        /*
    |--------------------------------------------------------------------------
    | Customer
    |--------------------------------------------------------------------------
    */

        if (empty($quotationData['customer_name'])) {

            if ($selectedEnquiry) {

                $quotationData['customer_name'] =
                    trim(
                        ($selectedEnquiry->first_name ?? '') . ' ' .
                            ($selectedEnquiry->last_name ?? '')
                    );

                if (empty($quotationData['customer_name'])) {
                    $quotationData['customer_name'] =
                        $selectedEnquiry->full_name ?? '';
                }
            } else {
                $quotationData['customer_name'] = '';
            }
        }

        if (empty($quotationData['customer_mobile'])) {

            if ($selectedEnquiry) {
                $quotationData['customer_mobile'] =
                    $selectedEnquiry->mobile
                    ?? $selectedEnquiry->phone
                    ?? '';
            } else {
                $quotationData['customer_mobile'] =
                    $quotationData['mobile'] ?? '';
            }
        }

        /*
    |--------------------------------------------------------------------------
    | OEM Code
    |--------------------------------------------------------------------------
    */

        $quotationData['oem_code'] = !empty($quotationData['oem_code'])
            ? $quotationData['oem_code']
            : ($selectedEnquiry?->oem_code ?? '');

        /*
    |--------------------------------------------------------------------------
    | On Road / Invoice
    |--------------------------------------------------------------------------
    */

        $quotationData['onroad_price'] = !empty($quotationData['onroad_price'])
            ? $quotationData['onroad_price']
            : $quotation->onroad_price;

        $quotationData['invoice_price'] = !empty($quotationData['invoice_price'])
            ? $quotationData['invoice_price']
            : $quotation->invoice_price;

        /*
    |--------------------------------------------------------------------------
    | Accessories
    |--------------------------------------------------------------------------
    */

        if (
            isset($quotationData['accessories']) &&
            !is_array($quotationData['accessories'])
        ) {
            $quotationData['accessories'] = [
                $quotationData['accessories']
            ];
        }

        if (!isset($quotationData['accessories'])) {
            $quotationData['accessories'] = [];
        }

        /*
    |--------------------------------------------------------------------------
    | Insurance Covers
    |--------------------------------------------------------------------------
    */

        if (
            isset($quotationData['insurance_covers']) &&
            !is_array($quotationData['insurance_covers'])
        ) {
            $quotationData['insurance_covers'] = [
                $quotationData['insurance_covers']
            ];
        }

        if (!isset($quotationData['insurance_covers'])) {
            $quotationData['insurance_covers'] = [];
        }

        /*
    |--------------------------------------------------------------------------
    | 7. Determine Group A selection
    |--------------------------------------------------------------------------
    */

        $groupASelected = 'cash_scheme_oem';

        if (
            !empty($quotationData['csd_discount']) &&
            !in_array(
                $quotationData['csd_discount'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupASelected = 'csd_discount';
        } elseif (
            !empty($quotationData['fame_subsidy']) &&
            !in_array(
                $quotationData['fame_subsidy'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupASelected = 'fame_subsidy';
        } elseif (
            !empty($quotationData['cash_scheme_oem']) &&
            !in_array(
                $quotationData['cash_scheme_oem'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupASelected = 'cash_scheme_oem';
        }

        /*
    |--------------------------------------------------------------------------
    | 8. Group B
    |--------------------------------------------------------------------------
    */

        $groupBSelected = 'corporate_discount';

        /*
    |--------------------------------------------------------------------------
    | 9. Group C selection
    |--------------------------------------------------------------------------
    */

        $groupCSelected = 'exchange_bonus';

        if (
            !empty($quotationData['loyalty_bonus']) &&
            !in_array(
                $quotationData['loyalty_bonus'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupCSelected = 'loyalty_bonus';
        } elseif (
            !empty($quotationData['green_bonus']) &&
            !in_array(
                $quotationData['green_bonus'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupCSelected = 'green_bonus';
        } elseif (
            !empty($quotationData['welcome_bonus']) &&
            !in_array(
                $quotationData['welcome_bonus'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupCSelected = 'welcome_bonus';
        } elseif (
            !empty($quotationData['exchange_bonus']) &&
            !in_array(
                $quotationData['exchange_bonus'],
                ['0', '0.00', 'N/A']
            )
        ) {
            $groupCSelected = 'exchange_bonus';
        }

        /*
    |--------------------------------------------------------------------------
    | 10. Return edit view
    |--------------------------------------------------------------------------
    |
    | VERY IMPORTANT:
    | viewMode MUST be false for EDIT.
    |
    |--------------------------------------------------------------------------
    */

        return view('admin.quotation.create', [

            'quotation' => $quotation,

            'quotationData' => $quotationData,

            'selectedEnquiry' => $selectedEnquiry,

            'insurance_type_map' => $insurance_type_map,

            'registration_type_map' => $registration_type_map,

            'permit_map' => $permit_map,

            'reg_no_type_map' => $reg_no_type_map,

            'accessoryList' => $accessoryList,

            'financiers' => $financiers,

            'groupASelected' => $groupASelected,

            'groupBSelected' => $groupBSelected,

            'groupCSelected' => $groupCSelected,

            // IMPORTANT
            'viewMode' => false,
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
    //         $previousProposal = $quotation->standard_data ?? [];

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

    // $oldDataForComparison = $previousProposal;
    // $newDataForComparison = $quotationData;

    // $oldFinancier = $oldDataForComparison['financier'] ?? null;
    // $newFinancier = $newDataForComparison['financier'] ?? null;

    // // Remove financier before comparing
    // unset($oldDataForComparison['financier']);
    // unset($newDataForComparison['financier']);

    // $hasNonFinancierChanges = $oldDataForComparison != $newDataForComparison;

    // $newRevision = $hasNonFinancierChanges
    //     ? ((int) $quotation->revision) + 1
    //     : (int) $quotation->revision;

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

    //         return redirect(backpack_url('quotation-form/' . $quotation->id . '/edit') . '?saved=1');
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

            $previousProposal = $quotation->standard_data ?? [];

            if (!is_array($previousProposal)) {
                $previousProposal = [];
            }

            $quotationData = array_merge(
                $previousProposal,
                $request->except(['_token', '_method'])
            );

            $oldFinancier = $previousProposal['financier'] ?? null;
            $newFinancier = $quotationData['financier'] ?? null;

            $financierHistory = $previousProposal['financier_history'] ?? [];

            if (!is_array($financierHistory)) {
                $financierHistory = [];
            }

            if (
                !empty($newFinancier) &&
                (string) $oldFinancier !== (string) $newFinancier &&
                !in_array($newFinancier, $financierHistory, true)
            ) {
                $financierHistory[] = $newFinancier;
            }

            $quotationData['financier_history'] = $financierHistory;

            $quotationData['charger_swapping_option'] = $request->input('charger_swapping_option');

            // Preserve frozen fields
            $quotationData['segment_code'] = $request->segment_code;
            $quotationData['model_code'] = $request->model_code;
            $quotationData['variant_code'] = $request->variant_code;
            $quotationData['color_code'] = $request->color_code;

            // ✅ FIX: Use insurance_covers_data instead of insurance_covers
            if ($request->has('insurance_covers_data') && !empty($request->insurance_covers_data)) {
                $coversData = json_decode($request->insurance_covers_data, true);
                if (is_array($coversData) && !empty($coversData)) {
                    $quotationData['insurance_covers'] = $coversData;
                }
            }

            // ✅ SAVE INSURANCE COMPANY
            if ($request->has('insurance_company')) {
                $quotationData['insurance_company'] = $request->insurance_company;
            }

            // ✅ 1. Normalize Insurance Covers into structured name/price arrays
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
                    $quotationData['insurance_covers'] = $formattedCovers;
                }
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

            /*
            |--------------------------------------------------------------------------
            | SAVE CUSTOMER DETAILS
            |--------------------------------------------------------------------------
            */

            if ($request->has('customer_name')) {
                $quotationData['customer_name'] = $request->customer_name;
            } else {
                $quotationData['customer_name'] =
                    trim($enquiry->first_name . ' ' . $enquiry->last_name)
                    ?: ($enquiry->full_name ?? '');
            }

            if ($request->has('customer_mobile')) {
                $quotationData['customer_mobile'] = $request->customer_mobile;
            } elseif ($request->has('mobile')) {
                $quotationData['customer_mobile'] = $request->mobile;
            } else {
                $quotationData['customer_mobile'] =
                    $enquiry->mobile ?? $enquiry->phone ?? '';
            }

            $quotationData['enquiry_id'] = $enquiry->id;



            /*
            |--------------------------------------------------------------------------
            | Detect actual quotation changes
            |--------------------------------------------------------------------------
            | financier IS included because it is now one of the 6 history groups.
            | financier_history is internal and should not itself count as a change.
            |--------------------------------------------------------------------------
            */

            $oldDataForComparison = $previousProposal;
            $newDataForComparison = $quotationData;

            unset($oldDataForComparison['financier_history']);
            unset($newDataForComparison['financier_history']);

            /*
            |--------------------------------------------------------------------------
            | Remove empty newly-created fields from comparison
            |--------------------------------------------------------------------------
            */
            foreach ($newDataForComparison as $key => $value) {
                if (
                    !array_key_exists($key, $oldDataForComparison) &&
                    ($value === null ||
                        $value === '' ||
                        $value === [] ||
                        $value === '0' ||
                        $value === '0.00')
                ) {
                    unset($newDataForComparison[$key]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Actual quotation change
            |--------------------------------------------------------------------------
            */
            $hasQuotationChanges =
                $oldDataForComparison != $newDataForComparison;

            /*
            |--------------------------------------------------------------------------
            | Every real quotation update gets a new history revision
            |--------------------------------------------------------------------------
            */
            $newRevision = $hasQuotationChanges
                ? ((int) $quotation->revision) + 1
                : (int) $quotation->revision;

            $quotation->update([
                'enquiry_no'    => $request->enquiry_no,
                'person_code'   => $enquiry->person_code,
                'model_code'    => $request->model_code,
                'variant_code'  => $request->variant_code,
                'color_code'    => $request->color_code,

                'revision'      => $newRevision,

                'standard_data' => $quotationData,

                'onroad_price'  => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,

                'invoice_price' => $request->invoice_amount
                    ?? $request->net_receivable_summary
                    ?? 0,

                'status'        => 'raised',
                'updated_by'    => backpack_user()->id,
            ]);

            // Update discount fields
            $this->saveDiscountFields($quotation, $quotationData);

            if ($hasQuotationChanges) {

                QuoteAction::create([
                    'quotation_no' => $quotation->quotation_no,
                    'action_by'    => backpack_user()->id,
                    'action'       => 'REVISED',
                    'requested'    => $quotationData,
                    'onroad'       => $request->net_receivable_summary
                        ?? $request->total_receivable
                        ?? 0,
                    'status'       => 'raised',
                    'remarks'      => 'Quotation Revised',
                    'created_by'   => backpack_user()->id,
                ]);
            }

            DB::commit();

            \Alert::success('Quotation updated successfully.')->flash();
            return redirect(backpack_url('quotation-form/' . $quotation->id . '/edit') . '?saved=1');
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
        $quotation = Quotation::findOrFail($id);

        $quotationData = $quotation->standard_data ?? [];

        $customerName = '';

        // 1. First priority: proposed_data
        if (!empty($quotationData['customer_name'])) {

            $customerName = $quotationData['customer_name'];
        }
        // 2. Second priority: actual enquiry
        elseif ($quotation->enquiry) {

            $customerName = trim(
                ($quotation->enquiry->first_name ?? '') . ' ' .
                    ($quotation->enquiry->last_name ?? '')
            );

            if (empty($customerName)) {
                $customerName = $quotation->enquiry->full_name ?? '';
            }
        }
        // 3. Third priority: mock enquiry
        if (empty($customerName)) {

            $mockEnquiries = [
                "001" => ["name" => "Rajesh Kumar", "mobile" => "9876543210"],
                "002" => ["name" => "Priya Sharma", "mobile" => "9123456780"],
                "003" => ["name" => "Suresh Yadav", "mobile" => "9988776655"],
                "004" => ["name" => "Amit Singh", "mobile" => "9811223344"],
                "005" => ["name" => "Vikram Mehta", "mobile" => "9765432109"],
                "006" => ["name" => "Rohan Verma", "mobile" => "9876500006"],
                "007" => ["name" => "Sneha Gupta", "mobile" => "9876500007"],
                "008" => ["name" => "Vikas Shah", "mobile" => "9876500008"],
                "009" => ["name" => "Priya Mehra", "mobile" => "9876500009"],
                "010" => ["name" => "Karan Joshi", "mobile" => "9876500010"],
                "011" => ["name" => "Manoj Yadav", "mobile" => "9876500011"],
                "012" => ["name" => "Deepak Singh", "mobile" => "9876500012"],
                "013" => ["name" => "Vikram Mehta", "mobile" => "9876500013"],
                "014" => ["name" => "Ananya Sharma", "mobile" => "9876500014"],
                "015" => ["name" => "Vivek Patel", "mobile" => "9876500015"],
                "016" => ["name" => "Kavya Nair", "mobile" => "9876500016"],
                "017" => ["name" => "Arjun Mehta", "mobile" => "9876500017"],
                "018" => ["name" => "Priya Singh", "mobile" => "9876500018"],
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

        $actions = QuoteAction::where('quotation_no', $quotation->quotation_no)
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

            if (!is_array($history)) {
                $history = [];
            }

            /*
            * Backward compatibility:
            * Old QuoteAction records may not have financier_history.
            * So take the financier stored in the action as the starting value.
            */
            $actionFinancier = $actionData['financier'] ?? null;

            if (
                !empty($actionFinancier) &&
                !in_array($actionFinancier, $history, true)
            ) {
                array_unshift($history, $actionFinancier);
            }

            /*
            * For the latest version, also use the current quotation's
            * financier_history because financier-only changes do not
            * create a QuoteAction.
            */
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
                    !empty($currentFinancier) &&
                    !in_array($currentFinancier, $history, true)
                ) {
                    $history[] = $currentFinancier;
                }
            }

            $names = [];

            foreach ($history as $financierId) {

                $name = $financierNames[$financierId] ?? null;

                if ($name && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }

            $action->financier_display = implode(', ', $names);
        }

        $fieldNames = [
            // Customer & Vehicle
            'customer_name'             => 'Customer Name',
            'customer_mobile'           => 'Mobile',
            'mobile'                    => 'Mobile',
            'careof'                    => 'Care Of',
            'careofname'                => 'Care Of Name',
            'segment_code'              => 'Segment',
            'model_code'                => 'Model',
            'variant_code'              => 'Variant',
            'color_code'                => 'Color',
            'permit'                    => 'Permit',
            'oem_code'                  => 'OEM Code',

            // Price Details (Receivables)
            'ex_showroom_price'         => 'Ex Showroom Price',
            'insurance_company'         => 'Insurance Company',
            'insurance_covers'          => 'Insurance Covers',
            'insurance_amount'          => 'Insurance Amount',
            'registration_no_type'      => 'Registration Type',
            'registration_category'     => 'Registration Category',
            'in_house_rto'              => 'In-House RTO',
            'registration_amount'       => 'Registration Amount',
            'accessories'               => 'Accessories',
            'accessories_amount'        => 'Accessories Amount',
            'maxicare'                  => 'Maxicare',
            'vltd_device'               => 'VLTD Device',
            'coating'                   => 'Coating',
            'coating_price'             => 'Coating Price',
            'ppf'                       => 'PPF',
            'rto_yellow_tape'           => 'RTO Yellow Tape',
            'kazam_charging_kit'        => 'Kazam Charging Kit',
            'incidental_charges'        => 'Incidental Charges',
            'shield'                    => 'Shield',
            'shield_price'              => 'Shield Price',
            'rsa'                       => 'RSA',
            'rsa_amount'                => 'RSA Amount',
            'fastag'                    => 'Fastag',
            'cod_charges'               => 'COD Charges',
            'charger_swapping'          => 'Charger Swapping',
            'charger_swapping_amount'   => 'Charger Swapping Amount',
            'tcs'                       => 'TCS @1%',
            'total_receivable'          => 'Total Receivable',

            // Discount Details (Group A, B, C & Others)
            'cash_scheme_oem'           => 'Cash Scheme OEM',
            'cash_scheme_oem_type'      => 'Cash Scheme OEM Type',
            'csd_discount'              => 'CSD Discount',
            'csd_discount_type'         => 'CSD Discount Type',
            'fame_subsidy'              => 'Fame Subsidy',
            'fame_subsidy_type'         => 'Fame Subsidy Type',
            'dealer_discount'           => 'Dealer Discount',
            'dealer_discount_type'      => 'Dealer Discount Type',
            'accessories_discount'      => 'Accessories Discount',
            'accessories_discount_type' => 'Accessories Discount Type',
            'shield_scheme'             => 'Shield Scheme',
            'shield_scheme_type'        => 'Shield Scheme Type',
            'corporate_discount'        => 'Corporate Discount',
            'corporate_discount_type'   => 'Corporate Discount Type',
            'exchange_bonus'            => 'Exchange Bonus',
            'exchange_bonus_type'       => 'Exchange Bonus Type',
            'green_bonus'               => 'Green Bonus',
            'green_bonus_type'          => 'Green Bonus Type',
            'welcome_bonus'             => 'Welcome Bonus',
            'welcome_bonus_type'        => 'Welcome Bonus Type',
            'loyalty_bonus'             => 'Loyalty Bonus',
            'loyalty_bonus_type'        => 'Loyalty Bonus Type',
            'accessories_spl_disc'      => 'Accessories Special Discount',
            'accessories_spl_disc_type' => 'Accessories Special Discount Type',
            'ceramic_discount'          => 'Coating Special Discount',
            'ceramic_discount_type'     => 'Coating Special Discount Type',
            'ppf_discount'              => 'PPF Special Discount',
            'ppf_discount_type'         => 'PPF Special Discount Type',
            'charger_swapping_discount' => 'Charger Swapping Discount',
            'charger_swapping_discount_type' => 'Charger Swapping Discount Type',
            'other_cash_discount'       => 'Other Cash Discount',
            'other_cash_discount_type'  => 'Other Cash Discount Type',
            'special_cash_discount'     => 'Special Cash Discount',
            'special_cash_discount_type' => 'Special Cash Discount Type',

            // Summary Totals
            'total_discount'            => 'Total Discount',
            'net_receivable_summary'    => 'Net Receivable',
            'invoiced_discount_summary' => 'Invoiced Discount (INV)',
            'inv_oe_discount_summary'   => 'Inv Disc. (OE) Total',
            'inv_d_discount_summary'    => 'Inv Disc. (D) Total',
            'credit_note_discount_summary' => 'Credit Note Discount (CN)',
            'cn1_discount_summary'      => 'CN1 Discount',
            'cn2_discount_summary'      => 'CN2 Discount',
            'cn3_discount_summary'      => 'CN3 Discount Total',
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
            if (!is_array($val)) {
                return (string) $val;
            }

            // 1. Insurance Covers Array: [{"name": "OD", "price": 50000}]
            if ($key === 'insurance_covers' || (!empty($val) && is_array(reset($val)))) {
                return collect($val)->map(function ($item) {
                    if (is_array($item)) {
                        $name = $item['name'] ?? '';
                        $price = isset($item['price']) && $item['price'] !== '' ? ' (₹' . number_format((float)$item['price'], 2) . ')' : '';
                        return trim($name . $price);
                    }
                    return (string) $item;
                })->filter()->implode(', ');
            }

            // 2. Accessories List Array: ["AT00298", "AS20059"]
            return implode(', ', array_filter($val, fn($v) => !is_array($v)));
        };

        // System internal fields jinhe comparison me ignore karna hai
        $ignoredFields = ['_token', '_method', 'insurance_covers_data','financier_history'];

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


        return view('admin.quotation.history', [
            'quotation'    => $quotation,
            'actions'      => $actions,
            'customerName' => $customerName ?: '-',
            'modelName'    => $modelName ?: '-',
        ]);
    }

    public function historyPdf($id, $version)
    {
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
        $actions = QuoteAction::where('quotation_no', $quotation->quotation_no)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $action = $actions->get(((int) $version) - 1);

        if (!$action) {
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
        ])->find($enquiryNo);

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

        $permit_map = [
    '1'  => 'Private - U/C (4 Wheeler)',
    '2'  => 'Private - BH (4 Wheeler)',
    '3'  => 'Private - EV (4 Wheeler)',
    '4'  => 'Goods - G (4 Wheeler)',
    '5'  => 'Goods - G 3 Ton+ (4 Wheeler)',
    '6'  => 'Goods - G (3 Wheeler)',
    '7'  => 'Goods - G EV (3 Wheeler)',
    '8'  => 'Goods - G EV (4 Wheeler)',
    '9'  => 'Taxi - T (4 Wheeler)',
    '10' => 'Taxi - T EV (4 Wheeler)',
    '11' => 'Passenger - P (3 Wheeler)',
    '12' => 'Passenger - P EV (3 Wheeler)',
    '13' => 'Ambulance (Misc.)',
];

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
            !empty($quotationData['csd_discount']) &&
            !in_array($quotationData['csd_discount'], ['0', '0.00', 'N/A'])
        ) {
            $groupASelected = 'csd_discount';
        } elseif (
            !empty($quotationData['fame_subsidy']) &&
            !in_array($quotationData['fame_subsidy'], ['0', '0.00', 'N/A'])
        ) {
            $groupASelected = 'fame_subsidy';
        } elseif (
            !empty($quotationData['cash_scheme_oem']) &&
            !in_array($quotationData['cash_scheme_oem'], ['0', '0.00', 'N/A'])
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
            !empty($quotationData['loyalty_bonus']) &&
            !in_array($quotationData['loyalty_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'loyalty_bonus';
        } elseif (
            !empty($quotationData['green_bonus']) &&
            !in_array($quotationData['green_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'green_bonus';
        } elseif (
            !empty($quotationData['welcome_bonus']) &&
            !in_array($quotationData['welcome_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'welcome_bonus';
        } elseif (
            !empty($quotationData['exchange_bonus']) &&
            !in_array($quotationData['exchange_bonus'], ['0', '0.00', 'N/A'])
        ) {
            $groupCSelected = 'exchange_bonus';
        }

        /*
    |--------------------------------------------------------------------------
    | Open same create.blade.php in VIEW MODE
    |--------------------------------------------------------------------------
    */
        return view('admin.quotation.create', [
            'quotation'          => $quotation,
            'quotationData'      => $quotationData,
            'selectedEnquiry'    => $selectedEnquiry,

            'insurance_type_map' => $insurance_type_map,
            'registration_type_map' => $registration_type_map,
            'reg_no_type_map'    => $reg_no_type_map,

            'accessoryList'      => $accessoryList,
            'financiers' => $financiers,
            'groupASelected'     => $groupASelected,
            'groupBSelected'     => $groupBSelected,
            'groupCSelected'     => $groupCSelected,
            'permit_map'         => $permit_map,
            'viewMode'           => true,
            
            'revisionPdf'        => true,
            'revisionNumber'     => $version,
        ]);
    }

    // public function preview($quotation_no)
    // {
    //     $quotation = Quotation::with('enquiry')
    //         ->where('quotation_no', $quotation_no)
    //         ->firstOrFail();

    //     $selectedEnquiry = Enquiry::with([
    //         'segment',
    //         'model',
    //         'variant',
    //         'color',
    //     ])->findOrFail($quotation->enquiry_no);

    //     // ✅ Fetch variant with permits
    //     $variant = null;
    //     if ($selectedEnquiry->variant_code) {
    //         $variant = \App\Models\Vehicle\Variant::with(['permit'])
    //             ->where('code', $selectedEnquiry->variant_code)
    //             ->first();
    //     }

    //     $permitOptions = $variant ? $variant->permit->pluck('name')->toArray() : [];

    //     $insurance_type_map = [
    //         1 => 'Nil Dep',
    //         2 => 'Higher',
    //     ];

    //     $registration_type_map = [
    //         0 => 'Exempted',
    //         1 => 'TRC Only',
    //         2 => 'Tax Only',
    //         3 => 'TRC + Tax',
    //     ];

    //     $registration_category_map = [
    //         '1' => 'Exempted',
    //         '2' => 'Standard',
    //         '3' => 'Special',
    //     ];

    //     $accessoryList = Accessory::where('status', 1)
    //         ->orderBy('item')
    //         ->get();

    //     $quotationData = $quotation->standard_data ?? [];

    //     // Group selections
    //     $groupASelected = 'cash_scheme_oem';
    //     if (!empty($quotationData['csd_discount'])) {
    //         $groupASelected = 'csd_discount';
    //     }
    //     if (!empty($quotationData['fame_subsidy'])) {
    //         $groupASelected = 'fame_subsidy';
    //     }

    //     $groupBSelected = 'corporate_discount';
    //     if (!empty($quotationData['loyalty_bonus'])) {
    //         $groupBSelected = 'loyalty_bonus';
    //     }

    //     $groupCSelected = 'exchange_bonus';
    //     if (!empty($quotationData['green_bonus'])) {
    //         $groupCSelected = 'green_bonus';
    //     }
    //     if (!empty($quotationData['welcome_bonus'])) {
    //         $groupCSelected = 'welcome_bonus';
    //     }

    //     $insurancePrintData = [];
    //     $insuranceCovers = $quotationData['insurance_covers'] ?? [];

    //     // ✅ FIX: Properly handle insurance covers
    //     if (!empty($insuranceCovers) && is_array($insuranceCovers)) {
    //         foreach ($insuranceCovers as $cover) {
    //             // Handle string format
    //             if (is_string($cover)) {
    //                 $price = 0;
    //                 $name = $cover;
    //                 if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
    //                     $price = floatval(str_replace(',', '', $matches[1]));
    //                     $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
    //                 }
    //                 $insurancePrintData[] = ['name' => $name, 'price' => $price];
    //             }
    //             // Handle array format
    //             elseif (is_array($cover)) {
    //                 $insurancePrintData[] = [
    //                     'name' => $cover['name'] ?? '',
    //                     'price' => floatval($cover['price'] ?? 0)
    //                 ];
    //             }
    //         }
    //     }

    //     // ✅ FIX: If insurance_covers is empty but insurance_amount exists
    //     if (empty($insurancePrintData)) {
    //         $insAmount = floatval($quotationData['insurance_amount'] ?? 0);
    //         if ($insAmount > 0) {
    //             // Try to get covers from insurance_covers_display if exists
    //             $displayCovers = $quotationData['insurance_covers_display'] ?? [];
    //             if (!empty($displayCovers) && is_array($displayCovers)) {
    //                 foreach ($displayCovers as $cover) {
    //                     if (is_array($cover)) {
    //                         $insurancePrintData[] = [
    //                             'name' => $cover['name'] ?? '',
    //                             'price' => floatval($cover['price'] ?? 0)
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 // Fallback: Show just the amount with policy type
    //                 $policyLabel = $insurance_type_map[$quotationData['policy_type'] ?? ''] ?? '';
    //                 $insurancePrintData[] = [
    //                     'name' => $policyLabel ?: 'Insurance',
    //                     'price' => $insAmount
    //                 ];
    //             }
    //         }
    //     }

    //     // ✅ DEBUG LOG
    //     \Log::info('Insurance Print Data Final', [
    //         'insurancePrintData' => $insurancePrintData,
    //         'count' => count($insurancePrintData),
    //     ]);

    //     // ✅ Prepare Accessories Data for Print
    //     $accessoriesPrintData = [];
    //     $accessories = $quotationData['accessories'] ?? [];

    //     if (!empty($accessories) && is_array($accessories)) {
    //         foreach ($accessories as $accCode) {
    //             $accessory = Accessory::where('part_no', $accCode)->first();
    //             if ($accessory) {
    //                 $accessoriesPrintData[] = [
    //                     'name' => $accessory->item,
    //                     'price' => floatval($accessory->ndp ?? 0)
    //                 ];
    //             }
    //         }
    //     }

    //     return view('admin.quotation.preview', [
    //         'quotation' => $quotation,
    //         'quotationData' => $quotationData,
    //         'selectedEnquiry' => $selectedEnquiry,
    //         'insurance_type_map' => $insurance_type_map,
    //         'registration_type_map' => $registration_type_map,
    //         'registration_category_map' => $registration_category_map,
    //         'accessoryList' => $accessoryList,
    //         'groupASelected' => $groupASelected,
    //         'groupBSelected' => $groupBSelected,
    //         'groupCSelected' => $groupCSelected,
    //         'permitOptions' => $permitOptions,
    //         'insurancePrintData' => json_encode($insurancePrintData),
    //         'accessoriesPrintData' => json_encode($accessoriesPrintData),
    //     ]);
    // }
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
        ])->find($quotation->enquiry_no);

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

        $permit_map = [
    '1'  => 'Private - U/C (4 Wheeler)',
    '2'  => 'Private - BH (4 Wheeler)',
    '3'  => 'Private - EV (4 Wheeler)',
    '4'  => 'Goods - G (4 Wheeler)',
    '5'  => 'Goods - G 3 Ton+ (4 Wheeler)',
    '6'  => 'Goods - G (3 Wheeler)',
    '7'  => 'Goods - G EV (3 Wheeler)',
    '8'  => 'Goods - G EV (4 Wheeler)',
    '9'  => 'Taxi - T (4 Wheeler)',
    '10' => 'Taxi - T EV (4 Wheeler)',
    '11' => 'Passenger - P (3 Wheeler)',
    '12' => 'Passenger - P EV (3 Wheeler)',
    '13' => 'Ambulance (Misc.)',
];

        $reg_no_type_map = [
            '1' => 'Regular',
            '2' => 'BH Series',
            '3' => 'Special Number',
        ];

        $accessoryList = Accessory::where('status', 1)->orderBy('item')->get();
        $quotationData = $quotation->standard_data ?? [];

        $groupASelected = 'cash_scheme_oem';
        if (!empty($quotationData['csd_discount']) && !in_array($quotationData['csd_discount'], ['0', '0.00', 'N/A'])) {
            $groupASelected = 'csd_discount';
        } elseif (!empty($quotationData['fame_subsidy']) && !in_array($quotationData['fame_subsidy'], ['0', '0.00', 'N/A'])) {
            $groupASelected = 'fame_subsidy';
        } elseif (!empty($quotationData['cash_scheme_oem']) && !in_array($quotationData['cash_scheme_oem'], ['0', '0.00', 'N/A'])) {
            $groupASelected = 'cash_scheme_oem';
        }

        $groupBSelected = 'corporate_discount';

        $groupCSelected = 'exchange_bonus';
        if (!empty($quotationData['loyalty_bonus']) && !in_array($quotationData['loyalty_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'loyalty_bonus';
        } elseif (!empty($quotationData['green_bonus']) && !in_array($quotationData['green_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'green_bonus';
        } elseif (!empty($quotationData['welcome_bonus']) && !in_array($quotationData['welcome_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'welcome_bonus';
        } elseif (!empty($quotationData['exchange_bonus']) && !in_array($quotationData['exchange_bonus'], ['0', 'N/A', '0.00'])) {
            $groupCSelected = 'exchange_bonus';
        }

        $financiers = XlFinancier::select('id', 'name', 'short_name')
            ->get();

        return view('admin.quotation.create', [
            'quotation' => $quotation,
            'quotationData' => $quotationData,
            'selectedEnquiry' => $selectedEnquiry,
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
