<?php

namespace App\Http\Controllers\Admin\Accounts\JournalVoucher;

use App\Http\Controllers\Controller;
use App\Models\Module\Booking\Bookingamount;
use App\Services\EnquiryReferenceService;
use App\Services\OrgService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Prologue\Alerts\Facades\Alert;

/**
 * No "journal_voucher.*" permission existed in xlr8_iam_permissions before
 * this change. Minted 3 new permissions (journal_voucher.view/create/edit) —
 * no `.delete`, since no `destroy()` method exists on this controller,
 * matching the same shape as the sibling ReceiptCrudController.
 *
 * This is a plain Controller, not a Backpack CrudController, so permission
 * checks are placed directly at the top of each public action.
 *
 * See known-bugs-report.md BUG-035: index() references an undefined
 * $receipt variable (line 44, a copy-paste leftover from
 * ReceiptCrudController) — not fixed here, out of scope for this batch.
 */
class JournalVoucherCrudController extends Controller
{
    private const TYPE_VOUCHER = 2; // Type 2 for Journal Voucher

    public function index()
    {
        if (! backpack_user()->can('ACC_JRVCH_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view journal vouchers.');
        }

        $vouchers = Bookingamount::query()
            ->where('type', self::TYPE_VOUCHER)
            ->orderByDesc('id')
            ->get();

        $enquiryIds = $vouchers->pluck('enq_id')->filter()->unique();
        $enquiries = DB::table('xlr8_crm_enquiries')->whereIn('id', $enquiryIds)->get()->keyBy('id');

        $data = $vouchers->map(function (Bookingamount $voucher, $index) use ($enquiries) {
            $enquiry = $enquiries->get($voucher->enq_id);
            $jvCatMap = [1 => 'Used Car Purchase', 2 => 'Internal Transfer'];

            return [
                'id' => $voucher->id,
                'serial_no' => $index + 1,
                'voucher_no' => $voucher->type_number,
                'voucher_date' => $this->formatDate($voucher->date),

                'amount' => $voucher->amount,
                'on_account_of' => $this->keyValueName($voucher->account_of),
                'jv_category' => $jvCatMap[$voucher->jv_cat] ?? '',

                'debit_to' => $voucher->party_name,
                'credit_to' => $voucher->name ?? $enquiry->name ?? '',
                'customer_name' => $receipt->name ?? $enquiry->name ?? $enquiry->customer_name ?? '',
                'care_of' => $voucher->care_of ?? '',
                'address' => $voucher->address ?? '',
                'contact_no' => $voucher->mobile ?? $enquiry->mobile ?? '',
                'alternate_mobile' => $voucher->alternate_mobile ?? '',

                'registration_no' => $voucher->vh_rgn_no,
                'chassis_no' => $voucher->chassis_no,
                'invoice_no' => $voucher->inv_no,

                'xceler8_booking_no' => $voucher->bid,
                'votf_no' => $voucher->otf_no,

                'cashier_branch' => '', // Auto from auth but not in DB
                'cashier_location' => $voucher->location,
                'payment_mode' => $this->keyValueName($voucher->mode) ?: 'Journal Voucher',

                'action' => '<div class="d-flex gap-2 justify-content-center">'.
                            '<a href="'.backpack_url('accounts/journal-voucher/'.$voucher->id.'/edit').'" class="btn btn-sm btn-primary text-white"><i class="la la-edit"></i> Edit</a>'.
                            '</div>',
            ];
        })->values();

        return view('admin.accounts.journal-voucher.list', ['gridConfig' => ['data' => $data]]);
    }

    public function create()
    {
        if (! backpack_user()->can('ACC_JRVCH_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create journal vouchers.');
        }

        $user = OrgService::getCurrentUser();
        // Fetch the Journal Voucher MOP ID dynamically if it exists
        $mopOptions = $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']);
        $jvModeId = array_search('Journal Voucher', $mopOptions) ?: '';

        return view('admin.accounts.journal-voucher.create', [
            'type' => self::TYPE_VOUCHER,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'jvModeId' => $jvModeId,
            'userBranch' => $user['primary_branch_code'] ?? 'UNKNOWN',
            'userLocation' => $user['primary_loc_code'] ?? 'UNKNOWN',
        ]);
    }

    public function store(Request $request)
    {
        if (! backpack_user()->can('ACC_JRVCH_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create journal vouchers.');
        }

        $this->performValidation($request);

        DB::beginTransaction();
        try {
            $voucherNo = $this->generateVoucherNumber($request->location);

            $voucher = new Bookingamount;
            $this->mapVoucherData($voucher, $request);

            $voucher->type = self::TYPE_VOUCHER;
            $voucher->type_number = $voucherNo;
            $voucher->created_by = Auth::id() ?: 1;

            $voucher->save();
            DB::commit();

            Alert::success("Voucher {$voucherNo} created successfully.")->flash();

            return redirect()->route('accounts.journal-voucher.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Alert::error('Error creating voucher: '.$e->getMessage())->flash();

            return redirect()->back()->withInput();
        }
    }

    public function edit($id)
    {
        if (! backpack_user()->can('ACC_JRVCH_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit journal vouchers.');
        }

        $voucher = Bookingamount::query()->where('type', self::TYPE_VOUCHER)->findOrFail($id);
        $user = OrgService::getCurrentUser();
        $mopOptions = $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']);

        return view('admin.accounts.journal-voucher.create', [
            'type' => self::TYPE_VOUCHER,
            'voucher' => $voucher,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'jvModeId' => array_search('Journal Voucher', $mopOptions) ?: $voucher->mode,
            'userLocation' => $user['primary_loc_code'] ?? 'UNKNOWN',
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        if (! backpack_user()->can('ACC_JRVCH_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit journal vouchers.');
        }

        $voucher = Bookingamount::query()->where('type', self::TYPE_VOUCHER)->findOrFail($id);
        $this->performValidation($request);

        DB::beginTransaction();
        try {
            $this->mapVoucherData($voucher, $request);
            $voucher->updated_by = Auth::id() ?: 1;
            $voucher->save();
            DB::commit();

            Alert::success("Voucher {$voucher->type_number} updated successfully.")->flash();

            return redirect()->route('accounts.journal-voucher.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Alert::error('Error updating voucher: '.$e->getMessage())->flash();

            return redirect()->back()->withInput();
        }
    }

    public function fetchEnquiryDetails(Request $request)
    {
        if (! backpack_user()->can('ACC_JRVCH_CREATE') && ! backpack_user()->can('ACC_JRVCH_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to look up journal voucher customer details.');
        }

        $data = OrgService::getCustomerByTransactionIds(
            $request->enq_no,
            $request->booking_no,
            $request->votf_no
        );

        if (! $data['success']) {
            return response()->json(['success' => false]);
        }

        return response()->json($data);
    }

    private function mapVoucherData(Bookingamount $voucher, Request $request)
    {
        $voucher->date = $request->voucher_date;
        $voucher->location = $request->location;
        $voucher->account_of = $request->on_account_of;
        $voucher->mode = $request->payment_mode; // Always JV Mode

        $cleanEnqId = app(EnquiryReferenceService::class)->fromReference($request->xceler8_enq_no);
        $voucher->enq_id = $cleanEnqId;
        $voucher->bid = $request->xceler8_booking_no;
        $voucher->otf_no = $request->votf_no;

        // JV Category & Specifics
        $voucher->jv_cat = $request->jv_cat;
        $voucher->used_model = $request->jv_cat == 1 ? $request->used_model : null;
        $voucher->used_rgn_no = $request->jv_cat == 1 ? $request->used_rgn_no : null;
        $voucher->from_dept = $request->jv_cat == 2 ? $request->from_dept : null;
        $voucher->to_dept = $request->jv_cat == 2 ? $request->to_dept : null;
        $voucher->exist_receipt_no = $request->jv_cat == 2 ? $request->exist_receipt_no : null;

        $voucher->party_name = $request->party_name;
        $voucher->name = $request->customer_name;
        $voucher->hypo = $request->hypo ?? null;
        $voucher->care_of_type = $request->care_of_type;
        $voucher->care_of = $request->care_of;
        $voucher->address = $request->address;
        $voucher->mobile = $request->mobile;
        $voucher->alternate_mobile = $request->alternate_mobile;

        $voucher->vh_rgn_no = $request->vehicle_registration_no;
        $voucher->chassis_no = $request->vehicle_chassis_no;
        $voucher->inv_no = $request->invoice_no;

        $voucher->amount = $request->amount;
        $voucher->remarks = $request->remarks;
        $voucher->status = 1;
    }

    private function generateVoucherNumber($locationCode)
    {
        $now = Carbon::now('Asia/Kolkata');
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyCode = 'F'.substr(($fyStartYear + 1), -2); // E.g., F27

        $pattern = "JV{$locationCode}{$fyCode}%";

        $lastVoucher = DB::table('xlr8_booking_amount')
            ->where('type_number', 'like', $pattern)
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->first();

        if ($lastVoucher) {
            $lastSequence = (int) str_replace("JV{$locationCode}{$fyCode}", '', $lastVoucher->type_number);
            $nextSequence = str_pad($lastSequence + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $nextSequence = '01';
        }

        return "JV{$locationCode}{$fyCode}{$nextSequence}";
    }

    private function performValidation(Request $request)
    {
        $rules = [
            'voucher_date' => 'required|date',
            'location' => 'required',
            'on_account_of' => 'required',
            'jv_cat' => 'required',
            'party_name' => 'required|string|max:100', // Debit To
            'customer_name' => 'required|string|max:150', // Credit To
            'amount' => 'required|numeric|min:1',
            'remarks' => 'required|string|max:150',
        ];

        if ($request->jv_cat == 1) { // Used Car
            $rules['used_model'] = 'required|string|max:100';
            $rules['used_rgn_no'] = 'required|string|max:100';
        } elseif ($request->jv_cat == 2) { // Internal Transfer
            $rules['from_dept'] = 'required|string|max:100';
            $rules['to_dept'] = 'required|string|max:100';
            $rules['exist_receipt_no'] = 'required|string|max:100';
        }

        $request->validate($rules);
    }

    private function getKeyValueOptions(array $keywordCodes): array
    {
        foreach ($keywordCodes as $keywordCode) {
            $options = OrgService::getKeyValuesByCode($keywordCode);
            if ($options && $options->isNotEmpty()) {
                return $options->pluck('value', 'id')->toArray();
            }
        }

        return [];
    }

    private function keyValueName($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (is_numeric($value)) {
            $kv = OrgService::getKeyValueById((int) $value);
            if ($kv) {
                return $kv->value;
            }
        }

        return OrgService::getKeyValueByCode((string) $value)?->value ?? (string) $value;
    }

    private function formatDate($date): string
    {
        return $date ? Carbon::parse($date)->format('d-m-Y') : '';
    }
}
