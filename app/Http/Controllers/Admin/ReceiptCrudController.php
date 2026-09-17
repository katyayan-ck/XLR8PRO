<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module\Booking\Bookingamount;
use App\Services\OrgService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class ReceiptCrudController extends Controller
{
    /**
     * xlr8_booking_amount type:
     * 1 = Receipt
     * 2 = Voucher
     * 3 = Special Discount
     * 4 = RTO Charges
     */
    private const TYPE_RECEIPT = 1;

/**
 * Display Receipt list.
 */
/**
     * Display Receipt list.
     */
    public function index()
    {
        $receipts = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->orderByDesc('id')
            ->get();

        // Collect all unique enquiry IDs referenced by receipts
        $enquiryIds = $receipts->pluck('enq_id')->filter()->unique();

        // Query xlr8_crm_enquiries table directly
        $enquiries = DB::table('xlr8_crm_enquiries')
            ->whereIn('id', $enquiryIds)
            ->get()
            ->keyBy('id');

        $data = $receipts->map(function (Bookingamount $receipt, $index) use ($enquiries) {
            // Find corresponding enquiry record
            $enquiry = $enquiries->get($receipt->enq_id);

            return [
                'id' => $receipt->id,
                'serial_no' => $index + 1,

                'receipt_no'   => $receipt->type_number,
                'receipt_date' => $this->formatDate($receipt->date),

                // Map fields directly from the xlr8_crm_enquiries record
                'customer_name' => $enquiry->name ?? $enquiry->customer_name ?? $enquiry->first_name ?? '',
                'care_of'       => $enquiry->care_of ?? '',
                'address'       => $enquiry->address ?? '',
                'contact_no'    => $enquiry->mobile ?? $enquiry->contact_no ?? $enquiry->phone ?? '',

                'on_account_of' => $this->keyValueName($receipt->account_of),
                'payment_mode'  => $this->keyValueName($receipt->mode),

                'amount'           => $receipt->amount,
                'transaction_date' => $this->formatDate($receipt->trans_date),
                'instrument_no'    => $receipt->instrument_no,
                'transaction_no'   => $receipt->trans_no,
                'bank_name'        => $receipt->bank,

                'xceler8_enq_no'     => $receipt->enq_id,
                'xceler8_booking_no' => $receipt->bid,
                'votf_no'            => $receipt->otf_no,
                'registration_no'    => $receipt->vh_rgn_no,
                'chassis_no'         => $receipt->chassis_no,

                'action' => '<a href="' . backpack_url('accounts/receipt/' . $receipt->id . '/edit') . '" class="btn btn-sm btn-link"><i class="la la-edit"></i> Edit</a>',
            ];
        })->values();

        $gridConfig = [
            'data' => $data,
        ];

        return view('admin.accounts.receipt-list', compact('gridConfig'));
    }

    /**
     * Show Receipt create form.
     */
    public function create()
    {
        return view('admin.accounts.receipt-create', [
            'type' => self::TYPE_RECEIPT,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'paymentModeOptions' => $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']),
        ]);
    }

    /**
     * Store a Receipt.
     */
    public function store(Request $request)
{
    $validated = $request->validate($this->validationRules());

    $enqId = $validated['xceler8_enq_no'] ?? null;

    // Build payload using only existing columns in xlr8_crm_enquiries
    $enquiryData = [];

    if (!empty($validated['customer_name'])) {
        if (Schema::hasColumn('xlr8_crm_enquiries', 'name')) {
            $enquiryData['name'] = $validated['customer_name'];
        } elseif (Schema::hasColumn('xlr8_crm_enquiries', 'first_name')) {
            $enquiryData['first_name'] = $validated['customer_name'];
        }

        if (Schema::hasColumn('xlr8_crm_enquiries', 'care_of')) {
            $enquiryData['care_of'] = $validated['care_of'] ?? null;
        }

        if (Schema::hasColumn('xlr8_crm_enquiries', 'address')) {
            $enquiryData['address'] = $validated['address'] ?? null;
        } elseif (Schema::hasColumn('xlr8_crm_enquiries', 'address1')) {
            $enquiryData['address1'] = $validated['address'] ?? null;
        }

        if (Schema::hasColumn('xlr8_crm_enquiries', 'mobile')) {
            $enquiryData['mobile'] = $validated['contact_no'] ?? null;
        } elseif (Schema::hasColumn('xlr8_crm_enquiries', 'contact_no')) {
            $enquiryData['contact_no'] = $validated['contact_no'] ?? null;
        } elseif (Schema::hasColumn('xlr8_crm_enquiries', 'phone')) {
            $enquiryData['phone'] = $validated['contact_no'] ?? null;
        }

        if (!empty($enquiryData)) {
            $enquiryData['updated_at'] = now();

            if ($enqId) {
                DB::table('xlr8_crm_enquiries')
                    ->where('id', $enqId)
                    ->update($enquiryData);
            } else {
                $enquiryData['created_at'] = now();
                $enqId = DB::table('xlr8_crm_enquiries')->insertGetId($enquiryData);
            }
        }
    }

    // Save Receipt record
    $receipt = new Bookingamount();

    $receipt->type        = self::TYPE_RECEIPT;
    $receipt->type_number = $validated['receipt_no'];
    $receipt->date        = $validated['receipt_date'];

    $receipt->account_of  = $validated['on_account_of'];
    $receipt->mode        = $validated['payment_mode'];

    $receipt->enq_id      = $enqId;
    $receipt->bid         = $validated['xceler8_booking_no'] ?? null;
    $receipt->otf_no      = $validated['votf_no'] ?? null;
    $receipt->vh_rgn_no   = $validated['vehicle_registration_no']
        ?? $validated['registration_no']
        ?? null;
    $receipt->chassis_no  = $validated['vehicle_chassis_no']
        ?? $validated['chassis_no']
        ?? null;

    $receipt->amount        = $validated['amount'];
    $receipt->trans_date    = $validated['transaction_date'] ?? null;
    $receipt->instrument_no = $validated['instrument_no'] ?? null;
    $receipt->trans_no      = $validated['transaction_no'] ?? null;
    $receipt->bank          = $validated['bank_name'] ?? null;

    $receipt->status     = 1;
    $receipt->created_by = Auth::id() ?: 1;

    $receipt->save();

    \Alert::success('Receipt created successfully.')->flash();

    return redirect()->route('accounts.receipt.index');
}
    /**
     * Show Receipt edit form.
     */
    public function edit($id)
    {
        $receipt = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->findOrFail($id);

        return view('admin.accounts.receipt-create', [
            'type' => self::TYPE_RECEIPT,
            'receipt' => $receipt,
            'onAccountOfOptions' => $this->getKeyValueOptions(['ACC_OF', 'ON_ACCOUNT_OF', 'ACCOUNT']),
            'paymentModeOptions' => $this->getKeyValueOptions(['PAYMENT_MODE', 'MODE_OF_PAYMENT', 'PAYMENT', 'MOP']),
            'isEdit' => true,
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

        $validated = $request->validate($this->validationRules());

        $receipt->type = self::TYPE_RECEIPT;
        $receipt->type_number = $validated['receipt_no'];
        $receipt->date = $validated['receipt_date'];

        $receipt->account_of = $validated['on_account_of'];
        $receipt->Mode = $validated['payment_mode'];

        $receipt->enq_id = $validated['xceler8_enq_no'] ?? null;
        $receipt->bid = $validated['xceler8_booking_no'] ?? null;
        $receipt->otf_no = $validated['votf_no'] ?? null;
        $receipt->vh_rgn_no = $validated['vehicle_registration_no']
            ?? $validated['registration_no']
            ?? null;
        $receipt->chassis_no = $validated['vehicle_chassis_no']
            ?? $validated['chassis_no']
            ?? null;

        $receipt->amount = $validated['amount'];
        $receipt->trans_date = $validated['transaction_date'] ?? null;
        $receipt->instrument_no = $validated['instrument_no'] ?? null;
        $receipt->trans_no = $validated['transaction_no'] ?? null;
        $receipt->bank = $validated['bank_name'] ?? null;

        $receipt->updated_by = Auth::id() ?: 1;
        $receipt->save();

        \Alert::success('Receipt updated successfully.')->flash();

        return redirect()->route('accounts.receipt.index');
    }

    /**
     * Soft delete Receipt.
     */
    public function destroy($id)
    {
        $receipt = Bookingamount::query()
            ->where('type', self::TYPE_RECEIPT)
            ->findOrFail($id);

        $receipt->deleted_by = Auth::id() ?: 1;
        $receipt->save();

        $receipt->delete();

        \Alert::success('Receipt deleted successfully.')->flash();

        return redirect()->route('accounts.receipt.index');
    }

    /**
     * Get active key/value options mapped as [id => value].
     */
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

    /**
     * Convert stored ID or CODE to display VALUE.
     */
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
        if (!$date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d-m-Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    private function validationRules(): array
    {
        return [
            'receipt_no' => ['required', 'string', 'max:50'],
            'receipt_date' => ['required', 'date'],

            'customer_name' => ['nullable', 'string', 'max:255'],
            'care_of' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'contact_no' => ['nullable', 'string', 'max:50'],

            'on_account_of' => ['required'],
            'payment_mode' => ['required'],
            'amount' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['nullable', 'date'],
            'instrument_no' => ['nullable', 'string', 'max:50'],
            'transaction_no' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:50'],

            'xceler8_enq_no' => ['nullable'],
            'xceler8_booking_no' => ['nullable'],
            'votf_no' => ['nullable', 'string', 'max:50'],
            'vehicle_registration_no' => ['nullable', 'string', 'max:50'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'vehicle_chassis_no' => ['nullable', 'string', 'max:50'],
            'chassis_no' => ['nullable', 'string', 'max:50'],
            'invoice_no' => ['nullable', 'string', 'max:50'],
        ];
    }
}