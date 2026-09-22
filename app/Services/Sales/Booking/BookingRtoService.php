<?php

namespace App\Services\Sales\Booking;

use App\Helpers\CommonHelper;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Stock;
use App\Models\Module\Booking\XL_DSA_MASTER;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Booking\XlRtoRules;
use App\Models\User;
use App\Services\OrgService;
use Illuminate\Http\UploadedFile;

/**
 * Business logic for the Booking RTO sub-domain (Pending RTO list ->
 * rtoEdit() -> rtoUpdate()), extracted from BookingCrudController as part
 * of the Sales-system refactor (Phase 4) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 */
class BookingRtoService
{
    private const SALE_TYPE_MAP = ['1' => 'Within State', '2' => 'Outside State'];

    private const BODY_TYPE_MAP = ['1' => 'Complete', '2' => 'CBC'];

    private const REG_NO_TYPE_MAP = ['1' => 'Regular', '2' => 'BH', '3' => 'Special'];

    /** Form field => XlRtoRules column, for the "is this field required by the matching rule" check. */
    private const RULE_FIELD_MAP = [
        'trc_number' => 'trc_number',
        'bank_ref_no' => 'trc_pay',
        'trc_copy' => 'trc_copy',
        'application_no' => 'app_no',
        'tax_payment_ref_no' => 'tax_pay',
        'vehicle_reg_no' => 'veh_reg',
        'tax_receipt_copy' => 'tax_copy',
    ];

    /** @return array<string, string> map of RTO_PERMIT keyword values, keyed '1', '2', ... in id order. */
    public function permitMap(): array
    {
        return OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(fn ($permit, $index) => [(string) ($index + 1) => $permit->value])
            ->toArray();
    }

    /**
     * Resolves the display data the RTO edit form needs, mirroring
     * BookingInsuranceService::resolveEditData()'s shape and the same
     * load-bearing $booking-mutation side effect.
     *
     * @return array{rto: ?XlRto, data: array<string, mixed>, dsaname: string}
     */
    public function resolveEditData(Booking $booking): array
    {
        $rto = XlRto::where('bid', $booking->id)->first();

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
                $booking->location_other = $enquiry->dealer_location_other ?? $booking->location_other;
            }
        }

        $data = [];
        $data['permit_map'] = $this->permitMap();
        $data['segments'] = CommonHelper::getVehicleSegments() ?? [];
        $data['models'] = CommonHelper::getVehicleModels($booking->segment_code ?? null) ?? [];
        $data['variants'] = CommonHelper::getVehicleVariants($booking->model_code ?? null) ?? [];
        $data['colors'] = CommonHelper::getVehicleColors($booking->variant_code ?? null) ?? [];

        $data['branch'] = Branch::where('code', $booking->branch_code)->value('name') ?? 'N/A';
        $data['location'] = $booking->location_code
            ? (Location::where('code', $booking->location_code)->value('name') ?? 'N/A')
            : ($booking->location_other ?? 'N/A');
        $data['fbranch'] = $data['branch'];
        $data['flocation'] = $data['location'];

        $data['rto_rules'] = $this->rtoRules();
        $data['allusers'] = OrgService::usersByDepartment('SLS');
        $data['saleconsultants'] = OrgService::usersByDesignation('CNS');

        $stock = Stock::find($booking->chassis_no);

        if ($stock) {
            $data['bchasis'] = $stock->chassis_no;
            $data['chassis'] = Stock::where('model_code', $stock->model_code)
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
        $dsaname = $drec ? $drec->name.' - '.$drec->mobile : 'N/A';

        $data['make1'] = $booking->exist_oem1 ?? 'N/A';
        $data['make2'] = $booking->exist_oem2 ?? 'N/A';

        return compact('rto', 'data', 'dsaname');
    }

    /**
     * Applies validated RTO data: matches the submitted sale/permit/body/
     * reg-no-type combination against XlRtoRules to determine which
     * optional fields are actually required for this combination
     * (an existing uploaded file satisfies a file requirement without a
     * new upload), sets status=2 only when every rule-required field is
     * present, creates/updates the XlRto row, and attaches any uploaded
     * files. Returns the saved XlRto.
     *
     * @param  array{trade_used: string, sale_type: string, permit: string, body_type: string, registration_type: string, reg_no_type: string, trc_number: ?string, bank_ref_no: ?string, application_no: ?string, tax_payment_ref_no: ?string, vehicle_reg_no: ?string}  $validated
     */
    public function apply(
        int $bookingId,
        array $validated,
        ?UploadedFile $trcCopy,
        ?UploadedFile $taxReceiptCopy,
    ): XlRto {
        $permitMap = $this->permitMap();

        $saleText = self::SALE_TYPE_MAP[$validated['sale_type']] ?? '';
        $permitText = $permitMap[$validated['permit']] ?? '';
        $bodyText = self::BODY_TYPE_MAP[$validated['body_type']] ?? '';
        $regNoTypeText = self::REG_NO_TYPE_MAP[$validated['reg_no_type']] ?? '';

        $matchingRule = null;

        foreach ($this->rtoRules() as $rule) {
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

        $allRequiredFilled = $matchingRule !== null;

        if ($matchingRule) {
            $fileFields = ['trc_copy' => $trcCopy, 'tax_receipt_copy' => $taxReceiptCopy];

            foreach (self::RULE_FIELD_MAP as $formField => $ruleKey) {
                if (($matchingRule[$ruleKey] ?? '') !== 'Yes') {
                    continue;
                }

                if (array_key_exists($formField, $fileFields)) {
                    $hasExistingFile = $this->hasExistingMedia($bookingId, $formField);
                    $uploadedFile = $fileFields[$formField];

                    if (! $hasExistingFile && (! $uploadedFile || ! $uploadedFile->isValid())) {
                        $allRequiredFilled = false;
                        break;
                    }
                } elseif (! $this->isFilled($validated[$formField] ?? null)) {
                    $allRequiredFilled = false;
                    break;
                }
            }
        }

        $data = [
            'bid' => $bookingId,
            'trade_used' => $validated['trade_used'],
            'sale_type' => $validated['sale_type'],
            'permit' => $validated['permit'],
            'body_type' => $validated['body_type'],
            'rgn_type' => $validated['registration_type'],
            'rgn_no_type' => $validated['reg_no_type'],
            'trc_no' => $validated['trc_number'] ?? null,
            'trc_payment_no' => $validated['bank_ref_no'] ?? null,
            'app_no' => $validated['application_no'] ?? null,
            'tax_payment_bank_ref_no' => $validated['tax_payment_ref_no'] ?? null,
            'vh_rgn_no' => $validated['vehicle_reg_no'] ?? null,
            'status' => $allRequiredFilled ? 2 : 1,
            'updated_by' => backpack_auth()->id() ?? 1,
        ];

        $rto = XlRto::updateOrCreate(['bid' => $bookingId], $data);

        if ($trcCopy && $trcCopy->isValid()) {
            $rto->clearMediaCollection('trc_copy');
            $rto->addMedia($trcCopy)->toMediaCollection('trc_copy');
        }

        if ($taxReceiptCopy && $taxReceiptCopy->isValid()) {
            $rto->clearMediaCollection('tax_receipt_copy');
            $rto->addMedia($taxReceiptCopy)->toMediaCollection('tax_receipt_copy');
        }

        return $rto;
    }

    public function hasExistingMedia(int $bookingId, string $collection): bool
    {
        $rto = XlRto::where('bid', $bookingId)->first();

        return $rto && $rto->getFirstMedia($collection) !== null;
    }

    /**
     * Matches Laravel's Request::filled() semantics exactly (present and
     * not null/''/[]) rather than PHP's empty(), which would also treat
     * the literal string "0" as unfilled - a real difference for fields
     * like vehicle_reg_no that have no format regex and could genuinely
     * be submitted as "0".
     */
    private function isFilled(mixed $value): bool
    {
        return ! in_array($value, [null, '', []], true);
    }

    /** @return array<int, array<string, mixed>> */
    private function rtoRules(): array
    {
        return XlRtoRules::select(
            'sale_type', 'permit', 'body_type', 'reg_no_type', 'trc_number',
            'trc_pay', 'trc_copy', 'app_no', 'tax_pay', 'veh_reg', 'tax_copy'
        )->get()->toArray();
    }
}
