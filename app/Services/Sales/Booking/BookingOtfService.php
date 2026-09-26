<?php

namespace App\Services\Sales\Booking;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\Xl_DSA_Master;
use App\Models\Module\Booking\XlFinancier;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Finance\XFinance;
use App\Models\Module\Insurance\XlInsurance;
use App\Models\Module\Insurance\XlInsurer;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\OrgService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the Booking OTF/VOTF sub-domain (otfProcess() ->
 * otfSave(), generateVotfNumber()), extracted from BookingCrudController
 * as part of the Sales-system refactor (Phase 4) - see
 * docs/refactor/ai-changelogs-DD-MM-YYYY.md.
 *
 * downloadOtfPdf()/getOtfPdfData() are deliberately NOT covered here -
 * getOtfPdfData() resolves its quotation via a different fallback order
 * (enquiry_id lookup first, then quotation_id override, no mandatory-
 * quotation gate) and is a near- but not exact-duplicate of
 * resolveOtfFormData() below. Forcing them into one shared method risked
 * altering PDF output; investigated and left alone, same as the Phase 3
 * XlInsurance/XlRto/XFinance investigation that found no safe merge.
 */
class BookingOtfService
{
    /** Request fields that fall back to existing final_data, then quotation data, when absent from a resubmission. */
    private const IMPORTANT_FIELDS = [
        'ex_showroom_price',
        'insurance_amount', 'insurance_covers', 'policy_type',
        'registration_type', 'registration_no_type', 'registration_category',
        'accessories', 'accessories_amount',
        'coating', 'coating_price', 'ceramic_discount',
        'shield', 'shield_price',
        'rsa', 'rsa_amount',
        'charger_swapping', 'charger_swapping_amount', 'charger_swapping_discount', 'charger_swapping_discount_type',
        'tcs', 'total_receivable', 'total_discount', 'net_receivable',
        'cash_scheme_oem', 'csd_discount', 'fame_subsidy',
        'corporate_discount', 'loyalty_bonus',
        'exchange_bonus', 'green_bonus', 'welcome_bonus',
    ];

    /**
     * Resolves the full OTF form's display data. Returns null when the
     * booking's linked quotation record can't be found (the "quotation
     * missing" mandatory-gate check on quotation_id itself stays in the
     * controller, since it decides between two different JSON error
     * responses before ever calling into this service).
     *
     * @return array<string, mixed>|null
     */
    public function resolveOtfFormData(Booking $booking): ?array
    {
        $quotation = Quotation::find($booking->quotation_id);

        if (! $quotation) {
            return null;
        }

        $quotationData = $quotation->standard_data ?? [];
        $finalData = [];

        if (! empty($booking->final_data)) {
            $finalData = json_decode($booking->final_data, true) ?? [];
        }

        $otfData = array_merge($quotationData, $finalData);

        if (! empty($otfData['net_settlement_amount'])) {
            $otfData['net_settlement_amount'] = $otfData['net_settlement_amount'];
        } else {
            $loanAmount = (float) ($otfData['loan_amount'] ?? 0);
            $fileCharge = (float) ($otfData['file_charge'] ?? 0);
            $marginMoney = (float) ($otfData['margin_money'] ?? 0);
            $financierSubvention = (float) ($otfData['financier_subvention'] ?? 0);

            $otfData['net_settlement_amount'] = number_format(
                $loanAmount - $fileCharge + $marginMoney - $financierSubvention,
                2
            );
        }

        if (! empty($quotationData['insurance_covers'])) {
            $otfData['insurance_covers'] = $quotationData['insurance_covers'];
        }

        $salesconsultants = OrgService::getUsers(desigCode: 'SLS_CONS');
        $salesconsultants = array_map(function ($consultant) {
            $consultant['branch_name'] = OrgService::branchName($consultant['primary_branch_code'] ?? '');
            $consultant['location_name'] = OrgService::locationName($consultant['primary_loc_code'] ?? '');
            $consultant['mile_id'] = $consultant['employee_code'] ?? '';

            return $consultant;
        }, $salesconsultants);

        $dsaList = Xl_DSA_Master::orderBy('name')->get(['id', 'name', 'dlocation']);
        $finance = XFinance::where('bid', $booking->id)->first();
        $insurance = XlInsurance::where('bid', $booking->id)->first();
        $rto = XlRto::where('bid', $booking->id)->first();

        $groupASelected = 'cash_scheme_oem';
        if (! empty($otfData['csd_discount'])) {
            $groupASelected = 'csd_discount';
        }
        if (! empty($otfData['fame_subsidy'])) {
            $groupASelected = 'fame_subsidy';
        }

        $groupBSelected = 'corporate_discount';
        if (! empty($otfData['loyalty_bonus'])) {
            $groupBSelected = 'loyalty_bonus';
        }

        $groupCSelected = 'exchange_bonus';
        if (! empty($otfData['green_bonus'])) {
            $groupCSelected = 'green_bonus';
        }
        if (! empty($otfData['welcome_bonus'])) {
            $groupCSelected = 'welcome_bonus';
        }

        $selectedExShowroomPrice = $otfData['ex_showroom_price'] ?? null;
        $selectedPolicyType = $insurance?->policy_type ?? ($otfData['policy_type'] ?? null);
        $selectedRegistrationType = $rto?->rgn_type ?? ($otfData['registration_type'] ?? null);

        $dsa = ! empty($booking->dsa_id) ? Xl_DSA_Master::find($booking->dsa_id) : null;

        $segment = Segment::where('code', $booking->segment_code)->first();
        $model = VehicleModel::where('code', $booking->model_code)->first();
        $variant = Variant::with(['permit', 'fuelType', 'bodyType', 'bodyMake'])
            ->where('code', $booking->variant_code)
            ->first();
        $color = Color::where('code', $booking->color_code)->first();

        $accessories = 'N/A';
        $selectedAccessories = [];

        if (! empty($booking->accessories)) {
            $selectedAccessories = array_filter(array_map('trim', explode(',', $booking->accessories)));

            $accNames = [];
            foreach ($selectedAccessories as $accId) {
                $accessory = DB::table('xlr8_vehicle_accessories')->where('part_no', trim($accId))->first();
                if ($accessory) {
                    $accNames[] = $accessory->item.' (₹'.number_format((float) $accessory->ndp, 2).')';
                }
            }

            if (! empty($accNames)) {
                $accessories = implode(', ', $accNames);
            }
        }

        $accessoryList = DB::table('xlr8_vehicle_accessories')->orderBy('item')->get();

        $permit_map = OrgService::getKeyValuesByCode('RTO_PERMIT')
            ->sortBy('id')
            ->values()
            ->mapWithKeys(fn ($permit, $index) => [(string) ($index + 1) => $permit->value])
            ->toArray();

        $reg_no_type_map = ['1' => 'Regular', '2' => 'BH Series', '3' => 'Special Number'];
        $registration_category_map = ['1' => 'Exempted', '2' => 'Standard'];
        $customer_category_map = ['1' => 'Individual', '2' => 'Corporate', '3' => 'CSD - CPC'];
        $body_type_map = ['1' => 'Complete', '2' => 'CBC'];
        $sale_type_map = ['1' => 'Within State', '2' => 'Outside State'];
        $insurance_type_map = [
            '1' => 'Standard',
            '2' => 'Nil Dep',
            '3' => 'Base (Nil Dep + Consumables)',
            '4' => 'Higher (Nil Dep + Consumables + Add Ons)',
        ];
        $registration_type_map = ['0' => 'Tax Only', '1' => 'TRC + Tax', '2' => 'TRC Only', '3' => 'Exempted'];
        $deliveryOptions = [1 => 'Payment', 2 => 'DO', 3 => 'Sanction Letter', 4 => 'Mail', 5 => 'Whatsapp'];

        $financierName = XlFinancier::find($booking->financier)?->name ?? 'N/A';

        $receiptLogs = Bookingamount::where('bid', $booking->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->get();
        $receiptTotal = $receiptLogs->sum(fn ($receipt) => (float) $receipt->amount);

        $chassisImage = $booking->getFirstMediaUrl('chassis_image') ?: '';

        $enquiry = ! empty($booking->enq_no) ? Enquiry::find($booking->enq_no) : null;

        $taStatement = null;
        if ($finance && $finance->instrument_type == 2 && ! empty($finance->instrument_ref_no)) {
            $taStatement = DB::table('xlr8_financer_statement')
                ->where('do_no', trim($finance->instrument_ref_no))
                ->first();
        }

        $insurancePrintData = [];
        $insuranceCovers = $otfData['insurance_covers'] ?? [];

        if (! empty($insuranceCovers) && is_array($insuranceCovers)) {
            foreach ($insuranceCovers as $cover) {
                if (is_string($cover)) {
                    $price = 0;
                    $name = $cover;
                    if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                        $price = floatval(str_replace(',', '', $matches[1]));
                        $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                    }
                    $insurancePrintData[] = ['name' => $name, 'price' => $price];
                } elseif (is_array($cover)) {
                    $insurancePrintData[] = [
                        'name' => $cover['name'] ?? '',
                        'price' => floatval($cover['price'] ?? 0),
                    ];
                }
            }
        }

        if (empty($insurancePrintData)) {
            $insAmount = floatval($otfData['insurance_amount'] ?? 0);
            if ($insAmount > 0) {
                $policyLabel = $insurance_type_map[$otfData['policy_type'] ?? ''] ?? '';
                $insurancePrintData[] = [
                    'name' => $policyLabel ?: 'Insurance Amount',
                    'price' => $insAmount,
                ];
            }
        }

        $accessoriesPrintData = [];
        $otfAccessories = $otfData['accessories'] ?? [];

        if (! empty($otfAccessories) && is_array($otfAccessories)) {
            foreach ($otfAccessories as $accCode) {
                $accessory = DB::table('xlr8_vehicle_accessories')->where('part_no', trim($accCode))->first();
                if ($accessory) {
                    $accessoriesPrintData[] = [
                        'name' => $accessory->item,
                        'price' => (float) $accessory->ndp,
                    ];
                }
            }
        }

        if (empty($accessoriesPrintData)) {
            $accAmount = floatval($otfData['accessories_amount'] ?? 0);
            if ($accAmount > 0) {
                $accessoriesPrintData[] = ['name' => 'Accessories', 'price' => $accAmount];
            }
        }

        return compact(
            'booking', 'finance', 'salesconsultants', 'taStatement', 'enquiry',
            'quotationData', 'finalData', 'otfData', 'insurance', 'rto', 'dsa',
            'segment', 'model', 'variant', 'color', 'accessories', 'permit_map',
            'sale_type_map', 'reg_no_type_map', 'registration_category_map',
            'registration_type_map', 'customer_category_map', 'body_type_map',
            'insurance_type_map', 'dsaList', 'accessoryList', 'selectedAccessories',
            'financierName', 'selectedPolicyType', 'selectedRegistrationType',
            'selectedExShowroomPrice', 'receiptLogs', 'receiptTotal', 'chassisImage',
            'groupASelected', 'groupBSelected', 'groupCSelected', 'deliveryOptions',
            'insurancePrintData', 'accessoriesPrintData'
        );
    }

    /**
     * Applies a validated OTF form submission: merges the submitted form
     * data over existing final_data over quotation data (in that priority
     * order), retains "important" price/detail fields when a field is
     * absent from the submission (disabled inputs don't overwrite saved
     * values), saves the merged JSON onto Booking.final_data, updates the
     * booking's own KYC/DMS/exchange/chassis/invoice fields, syncs the
     * linked Enquiry's address fields, and upserts RTO/Finance/Insurance
     * records from the same submission. Returns the saved Booking.
     *
     * @param  array<string, mixed>  $formData  the raw request payload, already stripped of _token/_method/chassis_image
     */
    public function apply(Booking $booking, array $formData, ?UploadedFile $chassisImage): Booking
    {
        if ($chassisImage) {
            $booking->addMedia($chassisImage)->toMediaCollection('chassis_image');
        }

        $existingFinalData = [];
        if (! empty($booking->final_data)) {
            $decoded = json_decode($booking->final_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $existingFinalData = $decoded;
            }
        }

        $quotationData = [];
        if (! empty($booking->quotation_id)) {
            $quotation = Quotation::find($booking->quotation_id);
            if ($quotation && ! empty($quotation->standard_data)) {
                if (is_array($quotation->standard_data)) {
                    $quotationData = $quotation->standard_data;
                } elseif (is_string($quotation->standard_data)) {
                    $decodedQuotation = json_decode($quotation->standard_data, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedQuotation)) {
                        $quotationData = $decodedQuotation;
                    }
                }
            }
        }

        $normalizedFormData = collect($formData)->map(
            fn ($value) => is_array($value) ? array_values($value) : $value
        )->toArray();

        $finalJsonData = array_replace_recursive($quotationData, $existingFinalData, $normalizedFormData);

        if (array_key_exists('net_settlement_amount', $formData)) {
            $finalJsonData['net_settlement_amount'] = $formData['net_settlement_amount'];
        }

        if (array_key_exists('do_amount_ta', $formData)) {
            $finalJsonData['do_amount_ta'] = $formData['do_amount_ta'];
        }

        foreach (self::IMPORTANT_FIELDS as $field) {
            if (! array_key_exists($field, $formData) && array_key_exists($field, $existingFinalData)) {
                $finalJsonData[$field] = $existingFinalData[$field];
            } elseif (
                ! array_key_exists($field, $formData)
                && ! array_key_exists($field, $existingFinalData)
                && array_key_exists($field, $quotationData)
            ) {
                $finalJsonData[$field] = $quotationData[$field];
            }
        }

        if (array_key_exists('accessories', $formData) && is_array($formData['accessories'])) {
            $finalJsonData['accessories'] = array_values(
                array_filter(array_map('trim', $formData['accessories']), fn ($value) => $value !== '')
            );
        }

        if (array_key_exists('insurance_covers', $formData) && is_array($formData['insurance_covers'])) {
            $finalJsonData['insurance_covers'] = array_values($formData['insurance_covers']);
        }

        $booking->final_data = json_encode(
            $finalJsonData,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        $booking->gstn = strtoupper(trim($formData['gstn'] ?? $booking->gstn ?? ''));
        $booking->pan_no = strtoupper(trim($formData['pan_no'] ?? $booking->pan_no ?? ''));
        $booking->adhar_no = preg_replace('/\D/', '', $formData['adhar_no'] ?? $booking->adhar_no ?? '');
        $booking->consultant = $formData['consultant'] ?? $booking->consultant;
        $booking->dms_no = $formData['dms_no'] ?? $booking->dms_no;
        $booking->dms_otf = $formData['dms_otf'] ?? $booking->dms_otf;
        $booking->b_cat = $formData['b_cat'] ?? $booking->b_cat;
        $booking->dsa_id = $formData['dsa_id'] ?? $booking->dsa_id;
        $booking->buyer_type = $formData['exchange'] ?? $booking->buyer_type;
        $booking->chassis_no = $formData['chassis'] ?? $booking->chassis_no;
        $booking->inv_no = $formData['inv_no'] ?? $booking->inv_no;
        $booking->inv_date = $formData['inv_date'] ?? $booking->inv_date;

        $enquiry = null;
        if (! empty($booking->enquiry_id)) {
            $enquiry = Enquiry::find($booking->enquiry_id);
        }
        if (! $enquiry && ! empty($booking->enquiry_no)) {
            $enquiry = Enquiry::where('enquiry_no', $booking->enquiry_no)->first();
        }

        if ($enquiry) {
            $enquiry->update([
                'zipcode' => $formData['pincode'] ?? $enquiry->zipcode,
                'vpo' => $formData['vpo'] ?? $enquiry->vpo,
                'tehsil' => $formData['customer_tehsil'] ?? $enquiry->tehsil,
                'district' => $formData['customer_district'] ?? $enquiry->district,
                'city' => $formData['city'] ?? $enquiry->city,
                'territory' => $formData['territory'] ?? $enquiry->territory,
            ]);
        }

        XlRto::updateOrCreate(['bid' => $booking->id], [
            'rgn_no_type' => $formData['registration_no_type'] ?? $existingFinalData['registration_no_type'] ?? null,
            'permit' => $formData['permit'] ?? $existingFinalData['permit'] ?? null,
            'body_type' => $formData['body_type'] ?? $existingFinalData['body_type'] ?? null,
            'sale_type' => $formData['sale_type'] ?? $existingFinalData['sale_type'] ?? null,
            'in_house_rto' => $formData['in_house_rto'] ?? $existingFinalData['in_house_rto'] ?? 0,
        ]);

        $finance = XFinance::firstOrNew(['bid' => $booking->id]);
        $finance->loan_amount = $formData['loan_amount'] ?? $finance->loan_amount;
        $finance->file_charge = $formData['file_charge'] ?? $finance->file_charge;
        $finance->margin = $formData['margin_money'] ?? $finance->margin;
        $finance->subvention_amount = $formData['financier_subvention'] ?? $finance->subvention_amount;
        $finance->instrument_type = $formData['vehicle_delivery_on'] ?? $finance->instrument_type;
        $finance->instrument_ref_no = $formData['do_number'] ?? $finance->instrument_ref_no;

        if (! $finance->exists) {
            $finance->verification_status = 0;
            $finance->case_status = 1;
        }

        $finance->save();

        if (
            array_key_exists('policy_type', $formData)
            || array_key_exists('insurance_amount', $formData)
            || array_key_exists('policy_no', $formData)
            || array_key_exists('policy_date', $formData)
            || array_key_exists('insurance_company', $formData)
            || array_key_exists('insurance_source', $formData)
        ) {
            $insurance = XlInsurance::firstOrNew(['bid' => $booking->id]);
            $insurance->pol_type = $formData['policy_type'] ?? $insurance->pol_type;
            $insurance->pol_no = $formData['policy_no'] ?? $insurance->pol_no;
            $insurance->pol_date = $formData['policy_date'] ?? $insurance->pol_date;
            $insurance->insurer = ! empty($formData['insurance_company'])
                ? XlInsurer::where('short_name', $formData['insurance_company'])->value('id')
                : $insurance->insurer;
            $insurance->source = $formData['insurance_source'] ?? $insurance->source;
            $insurance->save();
        }

        if (array_key_exists('accessories', $formData) && is_array($formData['accessories'])) {
            $booking->accessories = implode(
                ',',
                array_filter(array_map('trim', $formData['accessories']), fn ($value) => $value !== '')
            );
        }

        $booking->save();

        $booking->addHistory(
            'commented',
            'OTF Form Saved',
            'OTF form data saved successfully',
            [
                'module' => 'OTF Form',
                'saved_fields' => array_keys($finalJsonData),
            ],
            null,
            backpack_user()
        );

        return $booking;
    }

    /**
     * Generates the next VOTF number for a booking's branch, scanning
     * every booking with saved final_data for the current financial
     * year's highest global and branch-scoped sequence numbers. Format:
     * "{FY}/{BRANCH}{branch_seq:04d}/{global_seq:04d}".
     *
     * BUG-097 (known-bugs-report.md, already documented): this scan-then-
     * increment has no lock, so two concurrent saves can race onto the
     * same number - unchanged by this extraction, still needs the same
     * design decision noted there.
     */
    public function generateVotfNumber(Booking $booking): string
    {
        $branchCode = strtoupper(trim($booking->branch_code ?? ''));

        if ($branchCode === '') {
            throw new \InvalidArgumentException('Branch code is missing for this booking.');
        }

        $now = now();
        $financialYear = $now->month >= 4
            ? $now->copy()->addYear()->format('y')
            : $now->format('y');

        $records = Booking::query()
            ->whereNotNull('final_data')
            ->where('final_data', '!=', '')
            ->get(['id', 'final_data']);

        $latestGlobalCount = 0;
        $latestBranchCount = 0;

        foreach ($records as $record) {
            $data = is_array($record->final_data) ? $record->final_data : json_decode($record->final_data, true);

            if (! is_array($data)) {
                continue;
            }

            $votf = trim($data['votf_no'] ?? '');

            if ($votf === '') {
                continue;
            }

            $parts = explode('/', $votf);

            if (count($parts) !== 3) {
                continue;
            }

            [$fy, $branchPart, $globalPart] = $parts;

            if ($fy !== $financialYear) {
                continue;
            }

            $globalNumber = (int) $globalPart;
            if ($globalNumber > $latestGlobalCount) {
                $latestGlobalCount = $globalNumber;
            }

            if (str_starts_with(strtoupper($branchPart), $branchCode)) {
                $branchNumber = (int) substr($branchPart, strlen($branchCode));
                if ($branchNumber > $latestBranchCount) {
                    $latestBranchCount = $branchNumber;
                }
            }
        }

        return sprintf(
            '%s/%s%04d/%04d',
            $financialYear,
            $branchCode,
            $latestBranchCount + 1,
            $latestGlobalCount + 1
        );
    }
}
