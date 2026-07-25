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
            0 => 'Exempted',
            1 => 'TRC Only',
            2 => 'Tax Only',
            3 => 'TRC + Tax',
        ];
        $this->crud->setListView('admin.quotation.list');

        $quotations = Quotation::with('enquiry')
            ->whereNotIn('status', ['booked'])
            ->latest('id')
            ->get();



        $gridData = $quotations->map(function ($quotation, $index) use ($insurance_type_map, $registration_type_map) {

            $data = $quotation->proposed_data ?? [];

            $enquiry = $quotation->enquiry;

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

                'enquiry_no' => $quotation->enquiry_no,

                'customer_name' => $enquiry
                    ? trim($enquiry->first_name . ' ' . $enquiry->last_name)
                    : '-',

                'mobile' => $enquiry->mobile ?? '-',

                'segment' => $segment->name ?? $segmentCode,

                'model' => $model->name ?? $modelCode,

                'variant' => $variant->name ?? $variantCode,

                'color' => $color->name ?? $colorCode,

                'revision' => $quotation->revision,


                'ex_showroom_price' => $data['ex_showroom_price'] ?? '',
                'policy_type' => $insurance_type_map[$data['policy_type'] ?? ''] ?? '-',
                'registration_type' => $registration_type_map[$data['registration_type'] ?? ''] ?? '-',

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
                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'model', 'headerName' => 'Model'],
                    ['field' => 'variant', 'headerName' => 'Variant'],
                    ['field' => 'color', 'headerName' => 'Color'],

                    ['field' => 'revision', 'headerName' => 'Revision'],

                    ['field' => 'ex_showroom_price', 'headerName' => 'Ex Showroom'],
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

        $enquiryId = request('enquiry_id');

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
            0 => 'Exempted',
            1 => 'TRC Only',
            2 => 'Tax Only',
            3 => 'TRC + Tax',
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

            // Store complete quotation JSON
            $quotationData = $request->except('_token');

            foreach (
                [
                    'segment_code',
                    'model_code',
                    'variant_code',
                    'color_code'
                ] as $field
            ) {
                $quotationData[$field] = $request->$field;
            }

            // Accessories
            if (!empty($quotationData['accessories']) && is_array($quotationData['accessories'])) {
                $quotationData['accessories'] = array_values($quotationData['accessories']);
            }

            // Create quotation
            $quotation = new Quotation();

            $quotation->quotation_no = 0;
            $quotation->enquiry_no   = $request->enquiry_no;

            $enquiry = Enquiry::where(
                'enquiry_no',
                $request->enquiry_no
            )->firstOrFail();

            $quotation->person_code  = $enquiry->person_code;
            $quotation->model_code   = $request->model_code;
            $quotation->variant_code = $request->variant_code;
            $quotation->color_code   = $request->color_code;

            $quotation->revision = 0;

            $quotation->standard_data  = $quotationData;
            $quotation->requested_data = $quotationData;
            $quotation->proposed_data  = $quotationData;

            /*
        |--------------------------------------------------------------------------
        | Summary Values
        |--------------------------------------------------------------------------
        | Future proof:
        | invoice_amount field aayega to automatically use hoga.
        */
            $quotation->onroad_price = $request->net_receivable_summary
                ?? $request->total_receivable
                ?? 0;

            $quotation->invoice_price = $request->invoice_amount
                ?? $request->net_receivable_summary
                ?? 0;

            $quotation->status     = 'raised';
            $quotation->created_by = backpack_user()->id;

            $quotation->save();

            // Generate quotation number
            $quotation->quotation_no = $quotation->id;
            $quotation->save();

            // History
            QuoteAction::create([

                'quotation_no' => $quotation->quotation_no,

                'revision' => 0,

                'action' => 'RAISED',

                'requested' => $quotationData,

                'onroad' => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,

                'status' => 'raised',

                'remarks' => 'Quotation Created',

                'action_by' => backpack_user()->id,

            ]);

            DB::commit();

            \Alert::success('Quotation created successfully.')->flash();

            return redirect(backpack_url('quotation-form'));
        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error($e);

            \Alert::error($e->getMessage())->flash();

            return back()->withInput();
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
        ])->where(
            'enquiry_no',
            $quotation->enquiry_no
        )->firstOrFail();

        $insurance_type_map = [
            1 => 'Nil Dep',
            2 => 'Higher',
        ];

        $registration_type_map = [
            0 => 'Exempted (Reg & Hypo Fee Only)',
            1 => 'TRC Only',
            2 => 'Tax Only',
            3 => 'TRC + Tax',
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

            $enquiry = Enquiry::where(
                'enquiry_no',
                $request->enquiry_no
            )->firstOrFail();

            // Previous quotation snapshot
            $previousProposal = $quotation->proposed_data ?? [];

            // Current edited quotation
            $quotationData = $request->except([
                '_token',
                '_method'
            ]);

            // Preserve frozen fields
            $quotationData['segment_code'] = $request->segment_code;
            $quotationData['model_code']   = $request->model_code;
            $quotationData['variant_code'] = $request->variant_code;
            $quotationData['color_code']   = $request->color_code;

            if (isset($quotationData['accessories']) && is_array($quotationData['accessories'])) {
                $quotationData['accessories'] = array_values($quotationData['accessories']);
            }

            $quotationData['accessories_amount'] = $request->accessories_amount;

            $newRevision = $quotation->revision + 1;

            $quotation->update([

                // Fixed fields
                'enquiry_no'   => $request->enquiry_no,
                'person_code'  => $enquiry->person_code,

                // These columns exist in quotation table
                'model_code'   => $request->model_code,
                'variant_code' => $request->variant_code,
                'color_code'   => $request->color_code,

                'revision' => $newRevision,

                // Keep original quotation untouched
                // standard_data remains same

                // Previous proposal becomes requested
                'requested_data' => $previousProposal,

                // Current proposal
                'proposed_data' => $quotationData,

                'onroad_price' => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,

                'invoice_price' => $request->invoice_amount
                    ?? $request->net_receivable_summary
                    ?? 0,

                'status' => 'raised',

                'updated_by' => backpack_user()->id,

            ]);

            QuoteAction::create([

                'quotation_no' => $quotation->quotation_no,

                'revision' => $newRevision,

                'action' => 'REVISED',

                'requested' => $previousProposal,

                'onroad' => $request->net_receivable_summary
                    ?? $request->total_receivable
                    ?? 0,

                'status' => 'raised',

                'remarks' => 'Quotation Revised',

                'action_by' => backpack_user()->id,

            ]);

            DB::commit();

            \Alert::success('Quotation updated successfully.')->flash();

            return redirect(backpack_url('quotation-form'));
        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error($e);

            \Alert::error($e->getMessage())->flash();

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

        ])->where(

            'enquiry_no',

            $quotation->enquiry_no

        )->firstOrFail();



        $insurance_type_map = [

            1 => 'Standard',

            2 => 'Nil Dep',

            3 => 'Base (Nil Dep + Consumables)',

            4 => 'Higher (Nil Dep + Consumables + Add Ons)',

        ];



        $registration_type_map = [

            0 => 'Exempted (Reg & Hypo Fee Only)',

            1 => 'TRC Only',

            2 => 'Tax Only',

            3 => 'TRC + Tax',

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



        return view('admin.quotation.preview', [



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
}
