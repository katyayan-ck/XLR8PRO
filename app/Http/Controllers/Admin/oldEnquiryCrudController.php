<?php

namespace App\Http\Controllers\Admin;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Lead;
use App\Models\CRM\LeadSource;
use App\Services\OrgService;
use App\Jobs\ImportEnquiriesJob;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\CRM\Campaign;
use App\Models\Admin\PinCodes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Prologue\Alerts\Facades\Alert;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnquiryCrudController extends CrudController
{
    use CreateOperation, DeleteOperation, ListOperation, UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Enquiry::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/enquiries');
        CRUD::setEntityNameStrings('enquiry', 'enquiries');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.enquiry.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.enquiry.list');
        return view('admin.enquiry.list', [
            'title' => 'Xlr8 Enquiries',
            'gridConfig' => [
                'columns' => $this->getColumns('all'),
                'data' => []
            ]
        ]);
    }

    public function data(Request $request)
    {
        $startRow = max(0, (int) $request->input('startRow', 0));
        $limit = max(1, (int) $request->input('endRow', $startRow + 100)) - $startRow;

        $searchText = trim((string) $request->input('searchText', ''));
        $highlightFilter = trim((string) $request->input('highlightFilter', ''));

        $query = Enquiry::formComplete()->with(['segment', 'model', 'variant', 'color', 'campaign']);

        // Apply Search & Sort
        $this->applyEnquirySearch($query, $searchText);
        $this->applyEnquirySort($query, (array) $request->input('sortModel', []));

        // Apply the Highlight Filter
        OrgService::applyHighlightFilter($query, $highlightFilter);

        $total = (clone $query)->count();
        $gridData = $query->skip($startRow)->take($limit)->get()
            ->map(fn($e, $i) => $this->mapData($e, $startRow + $i, 'all'))->all();

        return response()->json(['rows' => $gridData, 'lastRow' => $total]);
    }

    public function export(Request $request)
    {
        $searchText = trim((string) $request->input('searchText', ''));
        $highlightFilter = trim((string) $request->input('highlightFilter', ''));

        $query = Enquiry::formComplete()->with(['segment', 'model', 'variant', 'color', 'campaign']);

        $this->applyEnquirySearch($query, $searchText);
        OrgService::applyHighlightFilter($query, $highlightFilter); // Make sure exports match the active filter

        $query->orderByDesc('created_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['S.No.', 'Enquiry No.', 'Enquiry Type', 'Source', 'Sub Source', 'Full Name', 'Mobile', 'Email', 'Segment', 'Model', 'Variant', 'Color', 'City', 'Dealer Branch', 'Dealer Location', 'Followup Date']);

            $serial = 0;
            $query->chunk(500, function ($chunk) use ($out, &$serial) {
                foreach ($chunk as $e) {
                    $serial++;
                    $variant = $e->variant?->display_name ?? $e->variant?->custom_name ?? $e->variant?->oem_name;
                    $fDate = $e->followup_date ? Carbon::parse($e->followup_date)->format('d-m-Y') : '';
                    fputcsv($out, [$serial, $e->enquiry_no, $e->enquiry_type, $e->source_code, $e->sub_source, $e->full_name, $e->mobile, $e->email, $e->segment?->name, $e->model?->name, $variant, $e->color?->name, $e->city, $e->dealer_branch, $e->dealer_location, $fDate]);
                }
            });
            fclose($out);
        }, 'enquiries-' . now()->format('Y-m-d_His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    // =========================================================
    // UNIFIED LIST RENDERERS
    // =========================================================

    public function referenceList()
    {
        return $this->buildGrid(Enquiry::reference(), 'admin.enquiry.reference-enquiry', 'Reference Enquiries', 'reference');
    }
    public function virtualNumberList()
    {
        return $this->buildGrid(Enquiry::virtual(), 'admin.enquiry.virtual-number-enquiry', 'Virtual Number Enquiries', 'virtual');
    }
    public function whatsappCampaignList()
    {
        return $this->buildGrid(Enquiry::whatsapp(), 'admin.enquiry.whatsapp-campaign-enquiry', 'WhatsApp Campaign Enquiries', 'whatsapp');
    }
    public function assignedLongList()
    {
        return $this->buildGrid(Enquiry::assignedLong(), 'admin.enquiry.assigned-long-enquiry', 'Assigned Long Enquiries', 'long');
    }
    public function unassignedLongList()
    {
        return $this->buildGrid(Enquiry::unassignedLong(), 'admin.enquiry.unassigned-long-enquiry', 'Unassigned Long Enquiries', 'long');
    }
    public function assignedQuickList()
    {
        return $this->buildGrid(Enquiry::assignedQuick(), 'admin.enquiry.assigned-quick-enquiry', 'Assigned Quick Enquiries', 'quick');
    }
    public function unassignedQuickList()
    {
        return $this->buildGrid(Enquiry::unassignedQuick(), 'admin.enquiry.unassigned-quick-enquiry', 'Unassigned Quick Enquiries', 'quick');
    }

    private function buildGrid($query, $view, $title, $type)
    {
        $this->crud->setListView($view);
        $enquiries = $query->with(['model', 'variant', 'color'])->orderByDesc('created_at')->get();

        return view($view, [
            'title' => $title,
            'segments' => OrgService::segments(),
            'gridConfig' => [
                'columns' => $this->getColumns($type),
                'data' => $enquiries->map(fn($e, $i) => $this->mapData($e, $i, $type))->values()
            ]
        ]);
    }

    private function mapData($e, $i, $type)
    {
        $c = fn($d, $f) => $d ? Carbon::parse($d)->format($f) : '—';
        $editUrl = backpack_url("enquiry/{$e->id}/edit");
        $quotUrl = backpack_url("quotation-form/create?id={$e->id}");

        $actionBtns = '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>';
        if ($type === 'all') $actionBtns .= '<a href="' . $quotUrl . '" class="btn btn-success btn-sm">Form</a>';

        $row = [
            'serial_no' => $i + 1,
            'x8_enquiry_no' => $e->x8_enquiry_no ?? '—',
            'x8_enquiry_date' => $c($e->x8_enquiry_date, 'd-m-Y H:i'),
            'x8_enquiry_assign_date' => $c($e->x8_enquiry_assign_date, 'd-m-Y'),
            'oem_enquiry_assign_date' => $c($e->oem_enquiry_assign_date, 'd-m-Y'),
            'segment_name' => $e->segment?->name ?? $e->segment_code ?? '—',
            'model_name' => $e->model?->name ?? $e->model_code ?? '—',
            'variant_name' => $e->variant?->display_name ?? $e->variant_code ?? '—',
            'mobile' => $e->mobile ?? '—',
            'dms_enquiry_stage' => $e->dms_enquiry_stage ?? '—',
            'cre_enquiry_stage' => $e->cre_enquiry_stage ?? '—',
            'cre_next_fup_date' => $c($e->cre_next_fup_date, 'd-m-Y'),
            'cre_next_fup_time' => $e->cre_next_fup_time ?? '—',
            'cre_next_fup_remarks' => $e->cre_next_fup_remarks ?? '—',
            'quotation_no' => $e->quotation_no ?? '—',
            'booking_no' => $e->booking_no ?? '—',
            'booking_date' => $c($e->booking_date, 'd-m-Y'),
            'oem_booking_no' => $e->oem_booking_no ?? '—',
            'oem_booking_date' => $c($e->oem_booking_date, 'd-m-Y'),
            'oem_otf_no' => $e->oem_otf_no ?? '—',
            'action' => '<div class="d-flex justify-content-center gap-2">' . $actionBtns . '</div>',
        ];

        if ($type === 'reference') {
            $row['referee_name'] = $e->referee_name ?? '—';
            $row['referee_phone'] = $e->referee_phone ?? '—';
            $row['first_name'] = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: '—';
        } elseif ($type === 'virtual') {
            $row['virtual_no'] = $e->virtual_no ?? '—';
            $row['call_date_and_time'] = $c($e->virtual_call_date, 'd-m-Y H:i');
            $row['call_nature'] = $e->call_nature ?? '—';
            $row['remarks'] = $e->remarks ?? '—';
        } elseif ($type === 'whatsapp') {
            $row['campaign_name'] = $e->wapp_campaign_name ?? '—';
            $row['campaign_date'] = $c($e->wapp_campaign_date, 'd-m-Y');
        }

        if (in_array($type, ['long', 'quick', 'all'])) {
            $row += [
                'oem_enquiry_no' => $e->oem_enquiry_no ?? '—',
                'oem_enquiry_date' => $c($e->oem_enquiry_date, 'd-m-Y'),
                'oem_long_enquiry_no' => $e->oem_long_enquiry_no ?? '—',
                'oem_long_enquiry_date' => $c($e->oem_long_enquiry_date, 'd-m-Y'),
                'oem_long_enquiry_assign_date' => $c($e->oem_long_enquiry_assign_date, 'd-m-Y'),
                'oem_quick_enquiry_no' => $e->oem_quick_enquiry_no ?? '—',
                'oem_quick_enquiry_date' => $c($e->oem_quick_enquiry_date, 'd-m-Y'),
                'oem_quick_enquiry_assign_date' => $c($e->oem_quick_enquiry_assign_date, 'd-m-Y'),
                'first_name' => $e->first_name ?? '—',
                'last_name' => $e->last_name ?? '—',
                'full_name' => $e->full_name ?? '—',
                'email' => $e->email ?? '—',
                'gender' => $e->gender ?? '—',
                'enquiry_type' => $e->enquiry_type ?? '—',
                'source_name' => $e->source?->name ?? $e->source_code ?? '—',
                'sub_source' => $e->sub_source ?? '—',
                'likely_purchase_in_days' => $e->likely_purchase_date ?? '—',
                'fuel_type' => $e->fuel_type ?? '—',
                'transmission' => $e->transmission ?? '—',
                'drivetrain' => $e->drivetrain ?? '—',
                'seating' => $e->seating ?? '—',
                'color_name' => $e->color?->name ?? $e->color_code ?? '—',
                'tehsil' => $e->tehsil ?? '—',
                'district' => $e->district ?? '—',
                'city' => $e->city ?? '—',
                'sc_code' => $e->sc_code ?? '—',
                'dealer_branch' => $e->dealer_branch ?? '—',
                'dealer_location' => $e->dealer_location ?? '—',
                'followup_type' => $e->followup_type ?? '—',
                'followup_date' => $c($e->followup_date, 'd-m-Y'),
                'followup_time' => $e->followup_time ?? '—',
                'occupation_type' => $e->occupation_type ?? '—',
                'customer_type' => $e->customer_type ?? '—',
                'occupation_sub_type' => $e->occupation_sub_type ?? '—',
                'company_name' => $e->company_name ?? '—',
                'dob' => $c($e->dob, 'd-m-Y'),
                'marital_status' => $e->marital_status ?? '—',
                'marriage_date' => $c($e->marriage_date, 'd-m-Y'),
                'age_group' => $e->age_group ?? '—',
                'usage_area' => $e->usage_area ?? '—',
                'km_travelled_daily' => $e->km_travelled_daily ?? '—',
                'application_type' => $e->application_type ?? '—',
                'application' => $e->application ?? '—',
                'pincode' => $e->pincode ?? '—',
                'address' => $e->address ?? '—',
                'has_ev' => $e->has_ev ?? '—',
                'purchase_type' => $e->purchase_type ?? '—',
                'remarks' => $e->remarks ?? '—',
                'consider_make' => $e->consider_make ?? '—',
                'consider_model' => $e->consider_model ?? '—',
                'consider_variant' => $e->consider_variant ?? '—',
            ];
        }

        return $row;
    }

    private function getColumns($type)
    {
        $commonEnd = [
            ['field' => 'dms_enquiry_stage', 'headerName' => 'DMS Stage'],
            ['field' => 'cre_enquiry_stage', 'headerName' => 'CRE Stage'],
            ['field' => 'cre_next_fup_date', 'headerName' => 'Next FUP Date'],
            ['field' => 'cre_next_fup_time', 'headerName' => 'Next FUP Time'],
            ['field' => 'cre_next_fup_remarks', 'headerName' => 'FUP Remarks'],
            ['field' => 'quotation_no', 'headerName' => 'Quotation No.'],
            ['field' => 'booking_no', 'headerName' => 'Booking No.'],
            ['field' => 'booking_date', 'headerName' => 'Booking Date'],
            ['field' => 'oem_booking_no', 'headerName' => 'OEM Booking No.'],
            ['field' => 'oem_booking_date', 'headerName' => 'OEM Booking Date'],
            ['field' => 'oem_otf_no', 'headerName' => 'OEM OTF No.'],
            ['field' => 'action', 'headerName' => 'Action']
        ];

        if ($type === 'reference') return array_merge([
            ['field' => 'serial_no', 'headerName' => 'S.No.'],
            ['field' => 'referee_name', 'headerName' => 'Referee Name'],
            ['field' => 'referee_phone', 'headerName' => 'Referee Mobile'],
            ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
            ['field' => 'first_name', 'headerName' => 'Customer Name'],
            ['field' => 'mobile', 'headerName' => 'Customer Mobile'],
            ['field' => 'segment_name', 'headerName' => 'Segment'],
            ['field' => 'model_name', 'headerName' => 'Model'],
            ['field' => 'variant_name', 'headerName' => 'Variant']
        ], $commonEnd);

        if ($type === 'virtual') return array_merge([
            ['field' => 'serial_no', 'headerName' => 'S.No.'],
            ['field' => 'virtual_no', 'headerName' => 'Virtual Number'],
            ['field' => 'call_date_and_time', 'headerName' => 'Call Date & Time'],
            ['field' => 'call_nature', 'headerName' => 'Call Nature'],
            ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
            ['field' => 'mobile', 'headerName' => 'Customer Mobile'],
            ['field' => 'remarks', 'headerName' => 'Remarks'],
        ], $commonEnd);

        if ($type === 'whatsapp') return array_merge([
            ['field' => 'serial_no', 'headerName' => 'S.No.'],
            ['field' => 'campaign_name', 'headerName' => 'Campaign Name'],
            ['field' => 'campaign_date', 'headerName' => 'Campaign Date'],
            ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
            ['field' => 'segment_name', 'headerName' => 'Segment'],
            ['field' => 'model_name', 'headerName' => 'Model'],
            ['field' => 'variant_name', 'headerName' => 'Variant'],
            ['field' => 'mobile', 'headerName' => 'Customer Mobile'],
        ], $commonEnd);

        $cols = [
            ['field' => 'serial_no', 'headerName' => 'S.No.'],
            ['field' => 'x8_enquiry_no', 'headerName' => 'X8 Enquiry No.'],
            ['field' => 'x8_enquiry_date', 'headerName' => 'X8 Enquiry Date'],
            ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
            ['field' => 'oem_enquiry_assign_date', 'headerName' => 'OEM Assign Date']
        ];

        if ($type === 'long') {
            array_push($cols, ['field' => 'oem_long_enquiry_no', 'headerName' => 'OEM Long Enquiry No.'], ['field' => 'oem_long_enquiry_date', 'headerName' => 'OEM Long Enquiry Date'], ['field' => 'oem_long_enquiry_assign_date', 'headerName' => 'OEM Long Enquiry Assign Date']);
        } elseif ($type === 'quick') {
            array_push($cols, ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'], ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'], ['field' => 'oem_quick_enquiry_assign_date', 'headerName' => 'OEM Quick Enquiry Assign Date']);
        } else {
            array_push($cols, ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'], ['field' => 'oem_enquiry_date', 'headerName' => 'OEM Enquiry Date'], ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'], ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'], ['field' => 'oem_quick_enquiry_assign_date', 'headerName' => 'OEM Quick Enquiry Assign Date'], ['field' => 'oem_long_enquiry_no', 'headerName' => 'OEM Long Enquiry No.'], ['field' => 'oem_long_enquiry_date', 'headerName' => 'OEM Long Enquiry Date'], ['field' => 'oem_long_enquiry_assign_date', 'headerName' => 'OEM Long Enquiry Assign Date']);
        }

        $midCols = [
            ['field' => 'segment_name', 'headerName' => 'Segment'],
            ['field' => 'model_name', 'headerName' => 'Model'],
            ['field' => 'variant_name', 'headerName' => 'Variant'],
            ['field' => 'first_name', 'headerName' => 'First Name'],
            ['field' => 'last_name', 'headerName' => 'Last Name'],
            ['field' => 'full_name', 'headerName' => 'Full Name'],
            ['field' => 'mobile', 'headerName' => 'Mobile'],
            ['field' => 'email', 'headerName' => 'Email'],
            ['field' => 'gender', 'headerName' => 'Gender'],
            ['field' => 'enquiry_type', 'headerName' => 'Enquiry Type'],
            ['field' => 'source_name', 'headerName' => 'Source'],
            ['field' => 'sub_source', 'headerName' => 'Sub Source'],
            ['field' => 'likely_purchase_in_days', 'headerName' => 'Likely Purchase In Days'],
            ['field' => 'fuel_type', 'headerName' => 'Fuel Type'],
            ['field' => 'transmission', 'headerName' => 'Transmission'],
            ['field' => 'drivetrain', 'headerName' => 'Drivetrain'],
            ['field' => 'seating', 'headerName' => 'Seating'],
            ['field' => 'color_name', 'headerName' => 'Color'],
            ['field' => 'tehsil', 'headerName' => 'Tehsil'],
            ['field' => 'district', 'headerName' => 'District'],
            ['field' => 'city', 'headerName' => 'City'],
            ['field' => 'sc_code', 'headerName' => 'SC Code'],
            ['field' => 'dealer_branch', 'headerName' => 'Dealer Branch'],
            ['field' => 'dealer_location', 'headerName' => 'Dealer Location'],
            ['field' => 'followup_type', 'headerName' => 'Followup Type'],
            ['field' => 'followup_date', 'headerName' => 'Followup Date'],
            ['field' => 'followup_time', 'headerName' => 'Followup Time'],
            ['field' => 'occupation_type', 'headerName' => 'Occupation Type'],
            ['field' => 'customer_type', 'headerName' => 'Customer Type'],
            ['field' => 'occupation_sub_type', 'headerName' => 'Occupation Sub Type'],
            ['field' => 'company_name', 'headerName' => 'Company Name'],
            ['field' => 'dob', 'headerName' => 'D.O.B.'],
            ['field' => 'marital_status', 'headerName' => 'Marital Status'],
            ['field' => 'marriage_date', 'headerName' => 'Marriage Date'],
            ['field' => 'age_group', 'headerName' => 'Age Group'],
            ['field' => 'usage_area', 'headerName' => 'Usage Area'],
            ['field' => 'km_travelled_daily', 'headerName' => 'KM Daily'],
            ['field' => 'application_type', 'headerName' => 'Application Type'],
            ['field' => 'application', 'headerName' => 'Application'],
            ['field' => 'pincode', 'headerName' => 'Pincode'],
            ['field' => 'address', 'headerName' => 'Address'],
            ['field' => 'has_ev', 'headerName' => 'Has EV'],
            ['field' => 'purchase_type', 'headerName' => 'Purchase Type'],
            ['field' => 'remarks', 'headerName' => 'Remarks'],
            ['field' => 'consider_make', 'headerName' => 'Consideration Make'],
            ['field' => 'consider_model', 'headerName' => 'Consideration Model'],
            ['field' => 'consider_variant', 'headerName' => 'Consideration Variant']
        ];

        return array_merge($cols, $midCols, $commonEnd);
    }

    // =========================================================
    // SEARCH & SORT LOGIC
    // =========================================================

    private function applyEnquirySearch($query, string $searchText): void
    {
        if ($searchText === '') return;
        $like = "%{$searchText}%";

        $query->where(function ($q) use ($like) {
            $q->where('enquiry_no', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhere('mobile', 'like', $like)->orWhere('email', 'like', $like)->orWhere('source_code', 'like', $like)
                ->orWhere('sub_source', 'like', $like)->orWhere('company_name', 'like', $like)->orWhere('vehicle_no', 'like', $like)
                ->orWhere('city', 'like', $like)->orWhere('pincode', 'like', $like)
                ->orWhereHas('model', fn($q2) => $q2->where('name', 'like', $like))
                ->orWhereHas('segment', fn($q2) => $q2->where('name', 'like', $like))
                ->orWhereHas('color', fn($q2) => $q2->where('name', 'like', $like))
                ->orWhereHas('variant', fn($q2) => $q2->where('display_name', 'like', $like)->orWhere('custom_name', 'like', $like)->orWhere('oem_name', 'like', $like));
        });
    }

    private function applyEnquirySort($query, array $sortModel): void
    {
        $cols = ['enquiry_no', 'enquiry_type', 'sub_source', 'person_code', 'first_name', 'last_name', 'mobile', 'email', 'occupation_type', 'customer_type', 'company_name', 'gender', 'dob', 'marital_status', 'city', 'district', 'purchase_type', 'created_at'];
        $sortApplied = false;

        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;
            if ($colId && in_array($colId, $cols, true)) {
                $query->orderBy($colId, strtolower($sort['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
                $sortApplied = true;
            }
        }
        if (!$sortApplied) $query->orderByDesc('created_at');
    }

    // =========================================================
    // CREATE / EDIT / STORE / UPDATE
    // =========================================================

    public function create()
    {
        return view('admin.enquiry.create', ['title' => 'Add New Enquiry'] + $this->getEnquiryFormData());
    }
    public function createReference()
    {
        return view('admin.enquiry.reference-create', ['title' => 'Add Reference Enquiry'] + $this->getEnquiryFormData());
    }

    public function edit($id)
    {
        $data = $this->getEnquiryFormData();
        $data['title'] = 'Edit Enquiry';
        $data['enquiry'] = Enquiry::with(['campaign', 'segment', 'model', 'variant', 'color'])->findOrFail($id);
        return view('admin.enquiry.create', $data);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate($this->getValidationRules());
            $this->processEntityRelations($validated);
            $validated['created_by'] = backpack_user()->id;
            $validated['origin'] = 'QUICK';
            $validated['current_origin'] = 'QUICK';
            $validated['cne'] = 1;

            Enquiry::create($validated);
            Alert::success('Enquiry created successfully.')->flash();
            return redirect(backpack_url('enquiry'));
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    public function update(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);
        $validated = $request->validate($this->getValidationRules($id));
        $this->processEntityRelations($validated);
        $validated['updated_by'] = backpack_user()->id;

        $enquiry->update($validated);
        Alert::success('Enquiry updated successfully.')->flash();
        return redirect(backpack_url('enquiry'));
    }

    public function storeReference(Request $request)
    {
        try {
            $validated = $request->validate([
                'referee_name' => 'required|max:100',
                'referee_phone' => 'required|numeric|digits:10',
                'first_name' => 'required|max:100',
                'last_name' => 'nullable|max:100',
                'mobile' => 'required|numeric|digits:10',
                'segment_code' => 'required',
                'model_code' => 'required',
                'variant_code' => 'nullable',
            ]);

            $validated['enquiry_no'] = 'REF-' . strtoupper(uniqid());
            $this->processEntityRelations($validated);

            $validated['source_code'] = 'REFERENCE';
            $validated['created_by'] = backpack_user()->id;
            $validated['origin'] = 'REFERENCE';
            $validated['current_origin'] = 'REFERENCE';
            $validated['cne'] = 1;

            Enquiry::create($validated);
            Alert::success('Reference Enquiry created successfully.')->flash();
            return redirect(backpack_url('enquiries/reference'));
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    private function processEntityRelations(array &$validated)
    {
        $validated['segment'] = OrgService::segments()[$validated['segment_code']] ?? null;
        $validated['model'] = OrgService::models($validated['segment_code'])[$validated['model_code']] ?? null;
        if (!empty($validated['variant_code'])) $validated['variant'] = OrgService::variants($validated['model_code'])[$validated['variant_code']]['name'] ?? null;
        if (!empty($validated['color_code'])) $validated['color'] = OrgService::colors($validated['variant_code'])[$validated['color_code']] ?? null;
    }

    private function getValidationRules($id = null)
    {
        return [
            // 'enquiry_no' => 'required|unique:xlr8_crm_enquiries,enquiry_no' . ($id ? ",$id" : ''),
            'enquiry_type' => 'required',
            'source_code' => 'required',
            'sub_source' => 'nullable',
            'person_code' => 'nullable',
            'reference_details' => 'nullable|max:255',
            'referred_by' => 'nullable|max:100',
            'referee_phone' => 'nullable|max:15',
            'referee_name' => 'nullable|max:100',
            'planned_campaign' => 'nullable|max:150',
            'likely_purchase_date' => 'nullable|max:150',
            'activity_type' => 'nullable',
            'activity_segment' => 'nullable',
            'activity_model' => 'nullable',
            'activity_start_date' => 'nullable|date',
            'activity_end_date' => 'nullable|date',
            'activity_branch' => 'nullable',
            'activity_location' => 'nullable',
            'first_name' => 'required|max:100',
            'last_name' => 'nullable|max:100',
            'mobile' => 'required|max:15',
            'email' => 'nullable|email|max:150',
            'occupation_type' => 'nullable',
            'customer_type' => 'nullable',
            'occupation_sub_type' => 'nullable',
            'company_name' => 'nullable|max:150',
            'gender' => 'nullable',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable',
            'marriage_date' => 'nullable|date',
            'age_group' => 'nullable',
            'pincode' => 'nullable|max:10',
            'tehsil' => 'nullable|max:100',
            'district' => 'nullable|max:100',
            'city' => 'nullable|max:100',
            'has_ev' => 'nullable',
            'purchase_type' => 'nullable',
            'consider_make' => 'nullable|max:100',
            'consider_model' => 'nullable|max:100',
            'consider_variant' => 'nullable|max:100',
            'vehicle_no' => 'nullable|max:30',
            'remarks' => 'nullable',
            'segment_code' => 'required',
            'model_code' => 'required',
            'variant_code' => 'required',
            'color_code' => 'required',
            'fuel_type' => 'nullable',
            'transmission' => 'nullable',
            'drivetrain' => 'nullable',
            'seating' => 'nullable',
            'usage_area' => 'nullable',
            'km_travelled_daily' => 'nullable',
            'application_type' => 'nullable',
            'application' => 'nullable',
            'place_of_registration' => 'nullable|max:100',
            'dealer_branch' => 'required',
            'dealer_location' => 'required',
            'sc_code' => 'required',
            'followup_type' => 'nullable',
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable'
        ];
    }

    private function getEnquiryFormData()
    {
        $kw = fn($k) => OrgService::keywordValueByCode($k);
        return [
            'segments' => OrgService::segments(),
            'models' => [],
            'variants' => [],
            'colors' => [],
            'locations' => [],
            'saleconsultants' => OrgService::getUsers(desigCode: 'CNS'),
            'branches' => OrgService::branches(),
            'campaigns' => Campaign::orderBy('name')->pluck('name')->toArray(),

            // FIX: Added 'likely_purchase_dates' so create.blade.php can find it
            'likely_purchase_dates' => $kw('LIKELY_PURCHASE_DATE'),

            'enquiry_types' => $kw('ENQUIRY_TYPE'),
            'activity_types' => $kw('ACTIVITY_TYPE'),
            'follow_up_types' => $kw('FOLLOW_UP_TYPE'),
            'occupation_types' => $kw('OCCUPATION_TYPE'),
            'occupation_sub_types' => $kw('OCCUPATION_SUB_TYPE'),
            'customer_types' => $kw('CUSTOMER_TYPE'),
            'genders' => $kw('GENDER'),
            'marital_statuses' => $kw('MARITAL_STATUS'),
            'age_groups' => $kw('AGE_GROUP'),
            'usage_areas' => $kw('USAGE_AREA'),
            'km_travelled_daily' => $kw('KM_TRAVELLED_DAILY'),
            'application_types' => $kw('APPLICATION_TYPE'),
            'applications' => $kw('APPLICATION'),
            'call_nature_virtual' => $kw('CALL_NATURE_VIRTUAL'),
            'sc_fup_remarks' => $kw('SC_FUP_REMARKS'),
            'sc_fup_remarks_types' => $kw('SC_FUP_REMARKS_TYPE'),
            'enquiry_sources' => $kw('ENQ_SOURCE'),
            'enquiry_sub_sources' => $kw('ENQUIRY_SUB_SOURCE'),
            'existing_car_oems' => $kw('EXISTING_CAR_OEM'),
            'existing_car_models' => $kw('EXISTING_CAR_MODEL'),
            'existing_car_variants' => $kw('EXISTING_CAR_VARIANT'),
            'fuel_types' => $kw('FUEL_TYPE'),
            'transmission_types' => $kw('TRANSMISSION_TYPE'),
            'finance_types' => $kw('FINANCE_TYPE'),
            'purchase_reasons' => $kw('PURCHASE_REASON')
        ];
    }

    // =========================================================
    // AJAX & HELPERS
    // =========================================================

    public function getSources()
    {
        return response()->json(LeadSource::active()->orderBy('sort_order')->orderBy('name')->pluck('name', 'code')->toArray());
    }
    public function getVariants($modelCode)
    {
        return response()->json(OrgService::variants($modelCode));
    }
    public function getColors($variantCode)
    {
        return response()->json(OrgService::colors($variantCode));
    }
    public function getModels(string $segmentCode)
    {
        return response()->json(OrgService::models($segmentCode));
    }
    public function getLocations($branchCode)
    {
        return response()->json(OrgService::locations($branchCode));
    }
    public function getKeywordValues($keyword, $parent)
    {
        return response()->json(OrgService::keywordValueByParentCode($keyword, $parent));
    }
    public function getReferenceUsers(Request $request)
    {
        return response()->json(OrgService::getReferenceUsers($request->type, $request->mobile));
    }
    public function locationByPincode(Request $request)
    {
        return response()->json(OrgService::getLocationByPincode($request->pincode));
    }

    public function getLead($leadNo)
    {
        $lead = Lead::where('lead_no', $leadNo)->firstOrFail();
        return response()->json($lead->only(['source_code', 'referral_details', 'first_name', 'last_name', 'mobile', 'email', 'occupation', 'segment_code', 'model_code', 'variant_code', 'color_code']));
    }

    public function checkDuplicateEnquiry(Request $request)
    {
        $enquiry = Enquiry::where('mobile', $request->mobile)->where('segment_code', $request->segment_code)->first();
        return response()->json(['exists' => (bool)$enquiry, 'enquiry_no' => $enquiry?->enquiry_no]);
    }

    // =========================================================
    // EXCEL IMPORT 
    // =========================================================

    public function importEnquiries(Request $request)
    {
        if (!$request->hasFile('excel_file') || !in_array($request->file('excel_file')->getClientOriginalExtension(), ['xlsx', 'xls'])) {
            Alert::error('Invalid or missing file! Only Excel files (.xlsx, .xls) allowed')->flash();
            return redirect()->back();
        }

        $absolutePath = Storage::disk('local')->path($request->file('excel_file')->store('imports', 'local'));
        $importLogId = DB::table('xlr8_crm_import_logs')->insertGetId([
            'file_name' => $request->file('excel_file')->getClientOriginalName(),
            'stored_path' => $absolutePath,
            'status' => 'queued',
            'created_by' => backpack_user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ImportEnquiriesJob::dispatch($importLogId, $absolutePath);
        Alert::success("File uploaded and queued for processing (Import #{$importLogId}).")->flash();
        return redirect()->back();
    }

    public function importStatus($id)
    {
        if (!$log = DB::table('xlr8_crm_import_logs')->where('id', $id)->first()) return response()->json(['error' => 'Not found'], 404);
        return response()->json([
            'id' => $log->id,
            'status' => $log->status,
            'total_rows' => $log->total_rows,
            'processed_rows' => $log->processed_rows,
            'percent' => $log->total_rows > 0 ? round(($log->processed_rows / $log->total_rows) * 100, 1) : 0,
            'stats' => $log->stats ? json_decode($log->stats, true) : null,
            'error_message' => $log->error_message,
        ]);
    }

    public function importHistory()
    {
        return response()->json(DB::table('xlr8_crm_import_logs')->orderByDesc('id')->limit(5)->get());
    }
}
