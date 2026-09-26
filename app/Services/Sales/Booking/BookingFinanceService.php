<?php

namespace App\Services\Sales\Booking;

use App\Helpers\CommonHelper;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Stock;
use App\Models\Module\Booking\Xessories;
use App\Models\Module\Booking\Xl_DSA_Master;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Module\Finance\XFinance;
use App\Models\User;
use App\Services\OrgService;
use Illuminate\Support\Collection;

/**
 * Business logic for the Booking Finance sub-domain (finEdit/finUpdate,
 * RetailEdit, PayoutEdit/PayoutUpdate, financeView), extracted from
 * BookingCrudController as part of the Sales-system refactor (Phase 4) -
 * see docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingFinanceService
{
    private const INSTRUMENT_TYPES = [
        1 => 'Financier Payment',
        2 => 'Delivery Order',
        3 => 'Sanction Letter',
        4 => 'Mail Communication',
        5 => 'Whatsapp Communication',
        6 => 'Banker Cheque',
        7 => 'Demand Graph',
        8 => 'Customer Cheque',
    ];

    private const CASE_LOST_REASONS = [
        1 => 'Cash Purchase',
        2 => 'Customer Self Finance',
    ];

    private const VERIFY_LABELS = [
        1 => 'Not Selected',
        2 => 'Verified (Match)',
        3 => 'Verified (Mismatch)',
        4 => 'Plan Cancelled',
    ];

    /**
     * Resolves the display data the Finance edit form (finEdit) needs.
     *
     * @return array{finance: ?XFinance, data: array<string, mixed>, dsaname: string}
     */
    public function resolveFinEditData(Booking $booking): array
    {
        $linkedEnquiry = null;

        if ($booking->enq_no) {
            $linkedEnquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

        if ($linkedEnquiry) {
            $booking->name = $booking->name ?? $linkedEnquiry->name;
            $booking->model_code = $booking->model_code ?? $linkedEnquiry->model_code;
            $booking->variant_code = $booking->variant_code ?? $linkedEnquiry->variant_code;
            $booking->color_code = $booking->color_code ?? $linkedEnquiry->color_code;
        }

        $user = backpack_user();

        $data = $this->baseDisplayData($booking);
        $data['segments'] = OrgService::segments();
        $data['saleconsultants'] = OrgService::salesConsultants();
        $data['remark'] = $this->remarkForDepartments($user->department ?? '', useKeywordLookup: true);

        $dsaname = $this->dsaName($booking->dsa_id);

        $finance = XFinance::where('bid', $booking->id)->first();

        return compact('finance', 'data', 'dsaname');
    }

    /**
     * Resolves the display data the Retail edit form needs.
     *
     * @return array{finance: ?XFinance, data: array<string, mixed>, dsaname: string}
     */
    public function resolveRetailEditData(Booking $booking): array
    {
        $user = backpack_user();

        $data = $this->baseDisplayData($booking);
        $data['segments'] = OrgService::segments();
        $data['saleconsultants'] = OrgService::salesConsultants();
        $data['remark'] = $this->remarkForDepartments($user->department ?? '', useKeywordLookup: false);

        $dsaname = $this->dsaName($booking->dsa_id);

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

        $finance = XFinance::where('bid', $booking->id)->first();

        if ($finance) {
            $booking->fin_mode = $finance->fin_mode;
            $booking->financier = $finance->financier;
            $booking->loan_status = $finance->loan_status;
        }

        return compact('finance', 'data', 'dsaname');
    }

    /**
     * Resolves the display data the Payout edit form needs.
     *
     * @return array{finance: ?XFinance, data: array<string, mixed>, dsaname: string, bookingHistory: Collection}
     */
    public function resolvePayoutEditData(Booking $booking): array
    {
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

        $user = backpack_user();

        $data = $this->baseDisplayData($booking);
        $data['segments'] = CommonHelper::getVehicleSegments();
        $data['saleconsultants'] = OrgService::usersByDesignation('CNS') ?? [];
        $data['remark'] = $this->remarkForDepartments($user->department ?? '', useKeywordLookup: false);

        $dsaname = $this->dsaName($booking->dsa_id);

        $finance = XFinance::where('bid', $booking->id)->first();

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

        return compact('finance', 'data', 'dsaname', 'bookingHistory');
    }

    /**
     * Resolves the display data the read-only Finance view needs.
     *
     * @return array{finance: ?XFinance, data: array<string, mixed>}
     */
    public function resolveFinanceViewData(Booking $booking): array
    {
        $enquiry = null;

        if ($booking->enq_no) {
            $enquiry = Enquiry::resolveByAnyReference($booking->enq_no);
        }

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

        $finance = XFinance::where('bid', $booking->id)->first();

        $data['financiers'] = XlFinancier::select('id', 'name', 'short_name')->get()->toArray();

        return compact('finance', 'data');
    }

    /**
     * Applies validated Finance data: creates/updates the XFinance row,
     * clears/sets the mode-dependent fields, applies the "new + retail"
     * auto-verification shortcut, handles the instrument_proof upload/
     * removal, and records booking history for the finance-completed and
     * retail transitions. Returns the saved XFinance.
     *
     * @param  array<string, mixed>  $validated
     */
    public function apply(Booking $booking, array $validated, bool $hasInstrumentProofFile, bool $deleteInstrumentProof): XFinance
    {
        $finance = XFinance::firstOrNew(['bid' => $booking->id]);
        $isNew = ! $finance->exists;

        if ($hasInstrumentProofFile && ! empty($validated['instrument_proof_file'])) {
            $finance->clearMediaCollection('instrument_proof');

            $file = $validated['instrument_proof_file'];
            $finance->addMedia($file)
                ->usingFileName('instrument_proof_'.$booking->id.'_'.time().'.'.$file->extension())
                ->toMediaCollection('instrument_proof');
        }

        if ($deleteInstrumentProof) {
            $finance->clearMediaCollection('instrument_proof');
        }

        $finance->fin_mode = $validated['fin_mode'];
        $finance->loan_status = $validated['loan_status'] ?? null;
        $finance->financier = $validated['financier'] ?? null;
        $finance->verification_status = $validated['verification_status'];
        $finance->case_status = $validated['case_status'] ?? null;

        if (in_array($validated['fin_mode'], ['Cash', 'Customer Self'])) {
            $finance->instrument_type = null;
            $finance->instrument_ref_no = null;
            $finance->loan_amount = null;
            $finance->margin = null;
            $finance->file_charge = null;
            $finance->case_lost_reason = $validated['fin_mode'] === 'Cash' ? 1 : 2;
        } else {
            $finance->instrument_type = $validated['instrument_type'] ?? null;
            $finance->instrument_ref_no = $validated['instrument_ref_no'] ?? null;
            $finance->loan_amount = $validated['loan_amount'] ?? null;
            $finance->margin = $validated['margin_money'] ?? null;
            $finance->file_charge = $validated['file_charge'] ?? null;
            $finance->subvention_amount = $validated['financier_subvention'] ?? null;
            $finance->case_lost_reason = $validated['case_lost_reason'] ?? null;
        }

        $retail = ($validated['retail'] ?? null) == 1;
        $remark = $validated['remark'] ?? '';

        if ($isNew && $retail) {
            $finance->verification_status = 2;
            $finance->case_status = 2;

            if (trim($remark) === '') {
                $remark = 'Retail booking finance auto-completed';
            }
        }

        if ($isNew) {
            $finance->bid = $booking->id;
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
            || in_array($finance->fin_mode, ['Customer Self', 'Cash', 'Yet To Decide', 'Purchase Plan Cancelled'])
        ) {
            $notInterested = in_array($finance->fin_mode, ['Customer Self', 'Cash', 'Yet To Decide']);

            $title = $notInterested ? 'Finance Not Interested Process Completed' : 'Finance Process Completed';
            $message = $notInterested ? 'Finance not interested case processed .' : 'Finance process completed .';

            if (trim($remark) !== '') {
                $message .= "\n\n,Remarks: ".$remark;
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

        if ($retail) {
            $booking->retail = 1;
            $booking->save();

            $booking->addHistory(
                'commented',
                'Retail Process Completed',
                'Finance retail process completed .',
                [
                    'finance_mode' => $finance->fin_mode,
                    'remark' => $remark,
                ],
                null,
                backpack_user()
            );
        }

        if (($validated['payout'] ?? null) == 1) {
            $booking->payout = 1;
            $booking->save();
        }

        return $finance;
    }

    /**
     * Applies validated Payout data to an existing XFinance row (must
     * already exist - PayoutUpdate operates only on bookings already in
     * finance). Records the "Payout Completed" history entry. Returns the
     * saved XFinance.
     *
     * @param  array<string, mixed>  $validated
     */
    public function applyPayout(Booking $booking, XFinance $finance, array $validated): XFinance
    {
        $payoutCategory = $validated['payout_category'];

        $payoutFields = [
            'instrument_ref_no', 'loan_amount', 'expected_payout_pct', 'gst_included',
            'inv1_no', 'inv1_name', 'inv1_prov_gst', 'inv2_no', 'inv2_name',
            'inv2_prov_gst', 'consideration_no_gst', 'difference',
        ];

        if ($payoutCategory != 1) {
            foreach ($payoutFields as $field) {
                $finance->$field = null;
            }
            if ($payoutCategory == 4) {
                $finance->nopayout_reason = null;
            }
        }

        $finance->payout_category = $payoutCategory;

        if ($payoutCategory == 1) {
            $finance->loan_amount = $validated['loan_amount'];
            $finance->instrument_ref_no = $validated['do_number'] ?? null;
            $finance->expected_payout_pct = $validated['expected_payout_pct'];
            $finance->gst_included = $validated['gst_included'];
            $finance->inv1_no = $validated['inv1_no'];
            $finance->inv1_name = $validated['inv1_name'];
            $finance->inv1_prov_gst = $validated['inv1_prov_gst'];
            $finance->inv2_no = $validated['inv2_no'] ?? null;
            $finance->inv2_name = $validated['inv2_name'] ?? null;
            $finance->inv2_prov_gst = $validated['inv2_prov_gst'] ?? null;
            $finance->consideration_no_gst = $validated['consideration_no_gst'];
            $finance->difference = $validated['difference_no_gst'];

            $booking->payout = 2;
            $booking->save();
        } else {
            $finance->nopayout_reason = $validated['no_payout_reason'] ?? null;
        }

        $finance->updated_by = backpack_auth()->id();

        if (in_array($finance->fin_mode, ['In-house', 'Customer_self']) && $finance->case_status == 2) {
            $finance->status = ($payoutCategory == 1) ? 3 : 2;
        }

        $finance->save();

        $booking->addHistory(
            'commented',
            'Payout Completed',
            'Finance payout process completed .',
            [
                'payout_category' => $payoutCategory,
                'loan_amount' => $validated['loan_amount'] ?? null,
                'do_number' => $validated['do_number'] ?? null,
                'expected_payout' => $validated['expected_payout_pct'] ?? null,
                'difference' => $validated['difference_no_gst'] ?? null,
                'remarks' => $validated['payout_remarks'] ?? null,
            ],
            null,
            backpack_user()
        );

        return $finance;
    }

    public function instrumentTypeLabel(mixed $value): string
    {
        return self::INSTRUMENT_TYPES[$value] ?? (string) $value;
    }

    public function caseLostReasonLabel(mixed $value): string
    {
        return self::CASE_LOST_REASONS[$value] ?? 'Unknown';
    }

    public function verificationStatusLabel(mixed $value): string
    {
        return self::VERIFY_LABELS[$value] ?? (string) $value;
    }

    /** @return array<string, mixed> */
    private function baseDisplayData(Booking $booking): array
    {
        $data = [];

        $data['branch'] = Branch::where('branch_code', $booking->branch_code)
            ->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code > 0
            ? (Location::find($booking->location_code)?->name ?? 'N/A')
            : ($booking->location_other ?? 'N/A');

        $data['accessories'] = $this->accessoryNames($booking->accessories);

        $chassis = Stock::find($booking->chassis_no);
        $data['bchasis'] = $chassis?->chassis_no ?? 'N/A';

        $data['financiers'] = XlFinancier::select('id', 'name', 'short_name')->get()->toArray();
        $data['enum_master'] = OrgService::keywordValueByCode('EXISTING_CAR_OEM');

        $collector = User::find($booking->col_by);
        $data['collector_name'] = $collector
            ? $collector->name.' - '.$collector->emp_code
            : 'N/A';

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';
        $data['oem_ids'] = explode(',', $booking->exist_oem ?? '');

        return $data;
    }

    private function accessoryNames(?string $accessories): string
    {
        $acc = explode(',', $accessories ?? '');
        $accessoryNames = [];

        foreach ($acc as $a) {
            if ($a = trim($a)) {
                $accessory = Xessories::find($a);
                if ($accessory) {
                    $accessoryNames[] = $accessory->item;
                }
            }
        }

        return $accessoryNames ? implode(', ', $accessoryNames) : 'N/A';
    }

    private function dsaName(mixed $dsaId): string
    {
        $drec = Xl_DSA_Master::find($dsaId);

        return $drec ? $drec->name.' - '.$drec->mobile : 'N/A';
    }

    /**
     * finEdit() resolves remark via OrgService::getKeyValueById() (keyword
     * lookup); RetailEdit()/PayoutEdit() instead resolve it via
     * OrgService::departments()+firstWhere('code', ...). Both existing,
     * genuinely different lookup paths - preserved exactly, not unified.
     */
    private function remarkForDepartments(string $departmentIds, bool $useKeywordLookup): int
    {
        $remark = 0;
        $depts = explode(',', $departmentIds);

        if ($useKeywordLookup) {
            foreach ($depts as $deptId) {
                $dept = OrgService::getKeyValueById((int) trim($deptId));
                $deptName = $dept?->value;

                if ($deptName === 'SALES') {
                    $remark = 1;
                }
                if ($deptName === 'ACCOUNTS') {
                    $remark = 2;
                }
            }

            return $remark;
        }

        $departments = OrgService::departments();

        foreach ($depts as $deptId) {
            $deptId = trim($deptId);
            $dept = collect($departments)->firstWhere('code', $deptId);
            $deptName = strtoupper($dept['name'] ?? '');

            if ($deptName == 'SALES') {
                $remark = 1;
            }
            if ($deptName == 'ACCOUNTS') {
                $remark = 2;
            }
        }

        return $remark;
    }
}
