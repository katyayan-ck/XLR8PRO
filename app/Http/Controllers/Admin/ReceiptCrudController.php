<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module\Booking\Bookingamount;
use App\Models\CRM\Enquiry;
use App\Services\OrgService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Prologue\Alerts\Facades\Alert;

class ReceiptCrudController extends Controller
{
    private const TYPE_RECEIPT = 1;

    public function index()
    {
        $receipts = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->orderByDesc('id')
            ->get();

        // Collect all unique enquiry IDs referenced by receipts to fetch customer names
        $enquiryIds = $receipts->pluck('enq_id')->filter()->unique();

        $enquiries = DB::table('xlr8_crm_enquiries')
            ->whereIn('id', $enquiryIds)
            ->get()
            ->keyBy('id');

        $data = $receipts->map(function (Bookingamount $receipt, $index) use ($enquiries) {
            $enquiry = $enquiries->get($receipt->enq_id);

            return [
                'id' => $receipt->id,
                'serial_no' => $index + 1,
                'receipt_no'   => $receipt->type_number,
                'receipt_date' => $this->formatDate($receipt->date),
                
                // Fallbacks between the snapshot data and the enquiry table
                'customer_name' => $enquiry->name ?? $enquiry->customer_name ?? $enquiry->first_name ?? '',
                'care_of'       => $receipt->care_of ?? '',
                'address'       => $receipt->address ?? '',
                'contact_no'    => $receipt->mobile ?? $enquiry->mobile ?? '',
                
                'on_account_of' => $this->keyValueName($receipt->account_of),
                'payment_mode'  => $this->keyValueName($receipt->mode),
                
                'amount'           => $receipt->amount,
                'transaction_date' => $this->formatDate($receipt->trans_date),
                'instrument_no'    => $receipt->instrument_no,
                'transaction_no'   => $receipt->trans_no,
                'bank_name'        => $receipt->bank,
                
                'xceler8_enq_no'     => $receipt->enq_id ? 'XENQ-' . $receipt->enq_id : '',
                'xceler8_booking_no' => $receipt->bid,
                'votf_no'            => $receipt->otf_no,
                'registration_no'    => $receipt->vh_rgn_no,
                'chassis_no'         => $receipt->chassis_no,
                
                // Action Buttons for the Data Grid
                'action' => '<div class="d-flex gap-2 justify-content-center">' .
                            '<a href="' . backpack_url('accounts/receipt/' . $receipt->id . '/show') . '" class="btn btn-sm btn-info text-white"><i class="la la-eye"></i> View</a>' .
                            '<a href="' . backpack_url('accounts/receipt/' . $receipt->id . '/edit') . '" class="btn btn-sm btn-primary text-white"><i class="la la-edit"></i> Edit</a>' .
                            '</div>',
            ];
        })->values();

        $gridConfig = [
            'data' => $data,
        ];

        return view('admin.accounts.receipt-list', compact('gridConfig'));
    }

    /**
     * Display the specified Receipt (Read-Only).
     */
    public function show($id)
    {
        $receipt = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->findOrFail($id);

        return view('admin.accounts.receipt-show', [
            'receipt' => $receipt,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'paymentModeOptions' => $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']),
        ]);
    }

    public function create()
    {
        $user = OrgService::getCurrentUser();

        return view('admin.accounts.receipt-create', [
            'type' => self::TYPE_RECEIPT,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'paymentModeOptions' => $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']),
            'userBranch' => $user['primary_branch_code'] ?? 'UNKNOWN',
            'userLocation' => $user['primary_loc_code'] ?? 'UNKNOWN',
        ]);
    }

    public function store(Request $request)
    {
        $this->performConditionalValidation($request);

        DB::beginTransaction();
        try {
            // 1. Generate Concurrency-Safe Receipt Number (using 'location' input)
            $receiptNo = $this->generateReceiptNumber($request->on_account_of, $request->location);

            // 2. Save Receipt Record
            $receipt = new Bookingamount();
            $receipt->type        = self::TYPE_RECEIPT;
            $receipt->type_number = $receiptNo; 
            $receipt->date        = $request->receipt_date;

            $receipt->account_of  = $request->on_account_of;
            $receipt->mode        = $request->payment_mode;

            $cleanEnqId = $request->xceler8_enq_no ? (int) str_replace(['XENQ-', 'xenq-'], '', $request->xceler8_enq_no) : null;
            $receipt->enq_id      = $cleanEnqId;
            $receipt->bid         = $request->xceler8_booking_no;
            $receipt->otf_no      = $request->votf_no;
            $receipt->inv_no      = $request->invoice_no;
            
            // Vehicle
            $receipt->vh_rgn_no   = $request->vehicle_registration_no;
            $receipt->chassis_no  = $request->vehicle_chassis_no;

            // Financials
            $receipt->amount        = $request->amount;
            $receipt->trans_date    = $request->transaction_date;
            $receipt->instrument_no = $request->instrument_no;
            $receipt->trans_no      = $request->transaction_no;
            $receipt->bank          = $request->bank_name;

            // Snapshot & Locations (MAPPED TO YOUR NEW SQL COLUMNS)
            $receipt->location         = $request->location;
            $receipt->care_of_type     = $request->care_of_type;
            $receipt->care_of          = $request->care_of;
            $receipt->address          = $request->address;
            $receipt->mobile           = $request->mobile;
            $receipt->alternate_mobile = $request->alternate_mobile;
            // Removed customer_name since it was not in your ALTER TABLE statement

            $receipt->status     = 1;
            $receipt->created_by = Auth::id() ?: 1;

            $receipt->save();
            DB::commit();

            Alert::success("Receipt {$receiptNo} created successfully.")->flash();
            return redirect()->route('accounts.receipt.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Alert::error('Error creating receipt: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Show Receipt edit form.
     */
    public function edit($id)
    {
        $receipt = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->findOrFail($id);

        $user = OrgService::getCurrentUser();

        return view('admin.accounts.receipt-create', [
            'type' => self::TYPE_RECEIPT,
            'receipt' => $receipt,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'paymentModeOptions' => $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']),
            'isEdit' => true,
            'userBranch' => $user['primary_branch_code'] ?? 'UNKNOWN',
            'userLocation' => $user['primary_loc_code'] ?? 'UNKNOWN',
        ]);
    }

    /**
     * Update Receipt.
     */
    public function update(Request $request, $id)
    {
        $receipt = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->findOrFail($id);

        $this->performConditionalValidation($request);

        DB::beginTransaction();
        try {
            $receipt->date        = $request->receipt_date;
            $receipt->account_of  = $request->on_account_of;
            $receipt->mode        = $request->payment_mode;

            // Xceler8 References
            $cleanEnqId = $request->xceler8_enq_no ? (int) str_replace(['XENQ-', 'xenq-'], '', $request->xceler8_enq_no) : null;
            $receipt->enq_id      = $cleanEnqId;
            $receipt->bid         = $request->xceler8_booking_no;
            $receipt->otf_no      = $request->votf_no;
            $receipt->inv_no      = $request->invoice_no;
            
            // Vehicle
            $receipt->vh_rgn_no   = $request->vehicle_registration_no;
            $receipt->chassis_no  = $request->vehicle_chassis_no;

            // Financials
            $receipt->amount        = $request->amount;
            $receipt->trans_date    = $request->transaction_date;
            $receipt->instrument_no = $request->instrument_no;
            $receipt->trans_no      = $request->transaction_no;
            $receipt->bank          = $request->bank_name;

            // Snapshot & Locations
            // We do NOT update type_number (Receipt No) because it should remain unchanged after creation.
            $receipt->location         = $request->location;
            $receipt->care_of_type     = $request->care_of_type;
            $receipt->care_of          = $request->care_of;
            $receipt->address          = $request->address;
            $receipt->mobile           = $request->mobile;
            $receipt->alternate_mobile = $request->alternate_mobile;
            $receipt->customer_name    = $request->customer_name;

            $receipt->updated_by = Auth::id() ?: 1;
            $receipt->save();
            DB::commit();

            Alert::success("Receipt {$receipt->type_number} updated successfully.")->flash();
            return redirect()->route('accounts.receipt.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Alert::error('Error updating receipt: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    public function fetchEnquiryDetails(Request $request)
    {
        $data = OrgService::getCustomerByTransactionIds(
            $request->enq_no,
            $request->booking_no,
            $request->votf_no
        );

        if (!$data['success']) {
            return response()->json(['success' => false]);
        }

        return response()->json($data);
    }

    private function performConditionalValidation(Request $request)
    {
        $rules = [
            'receipt_date'     => 'required|date',
            'on_account_of'    => 'required',
            'location'         => 'required', // Updated to match your DB
            'payment_mode'     => 'required',
            'amount'           => 'required|numeric|min:1',
            'customer_name'    => 'required|string|max:150', // Still required on UI
            'mobile'           => 'required|string|max:15',  // Updated to match your DB
        ];

        $accountName = strtoupper($this->keyValueName($request->on_account_of));
        $paymentMode = strtoupper($this->keyValueName($request->payment_mode));

        // 1. Account Specific Logic
        if (in_array($accountName, ['NEW VEHICLE SALES', 'USED VEHICLE SALES'])) {
            $rules['xceler8_enq_no'] = 'required';
        }

        if ($accountName === 'SERVICE' || in_array($accountName, ['RSA', 'SHIELD', 'ACCESSORIES'])) {
            $rules['vehicle_registration_no'] = 'required_without:vehicle_chassis_no';
            $rules['vehicle_chassis_no'] = 'required_without:vehicle_registration_no';
        }

        if ($accountName === 'INSURANCE RENEWAL') {
            $rules['vehicle_registration_no'] = 'required';
        }

        if ($accountName === 'ACCIDENTAL REPAIR') {
            $rules['invoice_no'] = 'required';
            $rules['vehicle_registration_no'] = 'required_without:vehicle_chassis_no';
            $rules['vehicle_chassis_no'] = 'required_without:vehicle_registration_no';
        }

        // 2. Payment Specific Logic
        if (in_array($paymentMode, ['CHEQUE', 'RTGS', 'NEFT', 'BANK TRANSFER', 'DEMAND DRAFT'])) {
            $rules['instrument_no'] = 'required';
            $rules['bank_name'] = 'required';
            $rules['transaction_date'] = 'required|date';
        }

        $request->validate($rules);
    }

    /**
     * Concurrency-Safe Receipt Number Generation
     */
    private function generateReceiptNumber($accountId, $locationCode)
    {
        // Resolve Prefix based on selected Master Value
        $accountName = strtoupper($this->keyValueName($accountId));
        $prefixMap = [
            'NEW VEHICLE SALES' => 'NVS',
            'USED VEHICLE SALES' => 'UVS',
            'SERVICE' => 'SRV',
            'ACCIDENTAL REPAIR' => 'BSR',
            'INSURANCE RENEWAL' => 'INS',
            'RSA' => 'RSA',
            'SHIELD' => 'SHL',
            'ACCESSORIES' => 'ACC'
        ];
        $prefix = $prefixMap[$accountName] ?? 'GEN';

        // Resolve Financial Year (E.g., F27 for April 2026 - March 2027)
        $now = Carbon::now('Asia/Kolkata');
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyCode = 'F' . substr(($fyStartYear + 1), -2); // E.g., 2026 -> F27

        $pattern = "{$prefix}{$locationCode}{$fyCode}%";

        // Lock table row safely to get the latest sequence for this exact pattern
        $lastReceipt = DB::table('xlr8_booking_amount')
            ->where('type_number', 'like', $pattern)
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReceipt) {
            // Extract the sequence number from the end
            $lastSequence = (int) str_replace("{$prefix}{$locationCode}{$fyCode}", '', $lastReceipt->type_number);
            $nextSequence = str_pad($lastSequence + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $nextSequence = '01';
        }

        return "{$prefix}{$locationCode}{$fyCode}{$nextSequence}";
    }

    private function convertNumberToWords(float $number): string
    {
        $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
        $words = $f->format($number);
        return 'Rupees ' . ucwords($words) . ' Only';
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
        if (empty($value)) return '';
        if (is_numeric($value)) {
            $kv = OrgService::getKeyValueById((int) $value);
            if ($kv) return $kv->value;
        }
        return OrgService::getKeyValueByCode((string) $value)?->value ?? (string) $value;
    }

    /**
     * Format date for display in the grid
     */
    private function formatDate($date): string
    {
        if (!$date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d-m-Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}