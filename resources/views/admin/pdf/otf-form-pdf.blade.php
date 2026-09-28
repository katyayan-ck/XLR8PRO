<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transaction Sheet - {{ $booking->votf_no ?? $otfData['votf_no'] ?? 'Booking' }}</title>
    <style>
        @page { size: a4 portrait; margin: 10mm 12mm 10mm 12mm; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b; background: #ffffff;
            margin: 0; padding: 0; font-size: 10px; line-height: 1.3;
        }
        .page-break { page-break-after: always; }

        .brand-header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .dealer-title-main { font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin: 0; }
        .dealer-address-lines { font-size: 8px; color: #64748b; line-height: 1.3; margin-top: 2px; }
        .sheet-badge-title {
            display: inline-block; background-color: #0f172a; color: #ffffff;
            font-weight: 700; font-size: 8.5px; padding: 3px 8px;
            border-radius: 2px; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;
        }
        .sheet-meta-lines { font-size: 8px; color: #334155; margin-top: 3px; line-height: 1.2; }
        .logo-img { max-height: 40px; max-width: 80px; object-fit: contain; }

        .info-card-container { border: 1px solid #cbd5e1; border-radius: 6px; background-color: #ffffff; margin-bottom: 10px; }
        .card-header-banner {
            background-color: #f8fafc; border-bottom: 1px solid #cbd5e1;
            padding: 3.5px 6px; font-size: 10px; font-weight: 700;
            color: #334155; text-transform: uppercase; letter-spacing: 0.3px;
        }

        .card-data-grid { width: 100%; border-collapse: collapse; }
        .card-data-grid td {
            padding: 2.5px 6px; vertical-align: middle;
            border-bottom: 1px solid #f1f5f9; width: 25%; font-size: 10px; color: #0f172a;
        }
        .card-data-grid tr:last-child td { border-bottom: none; }
        .field-label { font-weight: 600; color: #0f172a; font-size: 10px; width: 20%; }
        .field-value { font-weight: 500; width: 30%; }

        .split-pane-layout { width: 100%; border-collapse: collapse; }
        .split-pane-column { width: 49.3%; vertical-align: top; }
        .split-pane-spacer { width: 1.4%; }

        .accounting-ledger-table { width: 100%; border-collapse: collapse; }
        .accounting-ledger-table th {
            background-color: #f8fafc; color: #475569; font-weight: 700;
            font-size: 10px; text-transform: uppercase; padding: 3px 5px;
            border-bottom: 1px solid #cbd5e1; text-align: left;
        }
        .accounting-ledger-table td { padding: 2.2px 5px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .text-align-right { text-align: right; }
        .text-align-center { text-align: center; }

        .itemization-badge-strip { padding: 4px 6px; background-color: #f8fafc; font-size: 10px; color: #334155; line-height: 1.3; }
        .itemization-strip-title { font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; margin-right: 3px; }

        .receipt-table { width: 100%; border-collapse: collapse !important; border-spacing: 0; margin-bottom: 6px !important; }
        .receipt-table thead th {
            background: #f2f2f2 !important; border: 1px solid #000 !important;
            font-size: 10px; font-weight: 600; color: #000;
            padding: 3px 5px !important; height: 26px; vertical-align: middle;
        }
        .receipt-table tbody td {
            background: #fff !important; border: 1px solid #000 !important;
            padding: 3px 5px !important; height: 26px; vertical-align: middle;
            font-size: 10px; color: #000;
        }
        .receipt-table tfoot td {
            background: #f2f2f2 !important; border: 1px solid #000 !important;
            padding: 3px 5px !important; height: 26px;
            font-size: 10px; font-weight: 600; color: #000;
        }

        .note-box { border: 1px solid #000; }
        .chassis-img {
            max-width: 170px; max-height: 70px; width: auto; height: auto;
            object-fit: contain; display: block; margin: 0 auto;
        }
    </style>
</head>
<body>

{{-- ============================================================
     SHARED PHP BLOCK — mirrors BookingOtfService::resolveOtfFormData()
     so the PDF NEVER falls out of sync with the on-screen form.
============================================================ --}}
@php
    /* ---------- Ensure $otfData / $finalData / $quotationData exist ---------- */
    $otfData       = $otfData       ?? [];
    $finalData     = $finalData     ?? [];
    $quotationData = $quotationData ?? [];

    /* ---------- Segment / Mahindra logo ---------- */
    $segmentCode = strtoupper($booking->segment_code ?? $segment?->code ?? '');
    if ($segmentCode === 'LMM') {
        $mahindraLogo = asset('images/mahindra-lmm-logo.png');
    } elseif ($segmentCode === 'BEV') {
        $mahindraLogo = asset('images/mahindra-ev-logo.png');
    } else {
        $mahindraLogo = asset('images/mahindra-pv-cv-logo.png');
    }

    /* ---------- Receipts (service already normalises receipt_no / mode_name) ---------- */
    $receiptLogs   = $receiptLogs ?? collect();
    $receiptTotal  = (float) ($receiptTotal ?? $receiptLogs->sum(fn ($r) => (float) $r->amount));

    /* ---------- JV amount (form reads $jvAmount) ---------- */
    $jvAmount = (float) ($jvAmount ?? 0);

    /* ---------- Delivery label (service exports $deliveryOptions keyed by int 1..8) ---------- */
    $instrumentTypeRaw = $finance?->instrument_type
        ?? $otfData['vehicle_delivery_on']
        ?? $otfData['instrument_type']
        ?? '';
    $deliveryLabel = $deliveryOptions[$instrumentTypeRaw]
        ?? (is_string($instrumentTypeRaw) && $instrumentTypeRaw !== ''
            ? $instrumentTypeRaw
            : 'On DO Clearance');

    /* ---------- RTO / Sales-consultant fields for Vehicle Details grid ---------- */
    $bodyTypeVal  = $otfData['body_type']              ?? $rto?->body_type             ?? '';
    $saleTypeVal  = $otfData['sale_type']              ?? $rto?->sale_type             ?? '';
    $regNoTypeVal = $otfData['registration_no_type']   ?? $rto?->rgn_no_type           ?? '';
    $regCatVal    = $otfData['registration_category']  ?? $rto?->registration_category ?? '';
    $permitVal    = $otfData['permit']                 ?? $rto?->permit                ?? '';

    /* ---------- Sales consultant (from service if present, else re-derive) ---------- */
    $scName     = $selectedSc['display_name']  ?? $selectedSc['name'] ?? ($booking->consultant ?? 'N/A');
    $scMileId   = $selectedScMileId            ?? ($selectedSc['mile_id']       ?? $selectedSc['employee_code'] ?? '');
    $scBranch   = $selectedScBranch            ?? ($selectedSc['branch_name']   ?? '');
    $scLocation = $selectedScLocation          ?? ($selectedSc['location_name'] ?? '');

    /* ---------- DSA (service exposes $dsa as a model when dsa_id is set) ---------- */
    $dsaName     = $dsa?->name       ?? 'N/A';
    $dsaLocation = $otfData['dsa_location'] ?? ($dsa?->dlocation ?? 'N/A');

    /* ---------- Financier display name (service exports $financierName) ---------- */
    $financierDisplay = $financierName
        ?? $otfData['financier_name']
        ?? XlFinancier::find($booking->financier ?? null)?->name
        ?? 'N/A';

    /* ---------- PRICE DETAILS — same keys the form writes ---------- */
    $exShowroom          = (float) ($otfData['ex_showroom_price'] ?? 0);
    $insuranceAmount     = (float) ($otfData['insurance_amount'] ?? 0);
    $insuranceCompany    = $otfData['insurance_company'] ?? '';
    $registrationAmount  = (float) ($otfData['registration_amount'] ?? 0);
    $accessoriesAmount   = (float) ($otfData['accessories_amount'] ?? 0);
    $maxicare            = (float) ($otfData['maxicare'] ?? 0);
    $vltdDevice          = (float) ($otfData['vltd_device'] ?? 0);
    $coatingPrice        = (float) ($otfData['coating_price'] ?? 0);
    $coating             = $otfData['coating'] ?? '';
    $ppf                 = (float) ($otfData['ppf'] ?? 0);
    $rtoYellowTape       = (float) ($otfData['rto_yellow_tape'] ?? 0);
    $kazamChargingKit    = (float) ($otfData['kazam_charging_kit'] ?? 0);
    $incidentalCharges   = (float) ($otfData['incidental_charges'] ?? 0);
    $shieldPrice         = (float) ($otfData['shield_price'] ?? 0);
    $shield              = $otfData['shield'] ?? '';
    $rsaAmount           = (float) ($otfData['rsa_amount'] ?? 0);
    $rsa                 = $otfData['rsa'] ?? '';
    $fastag              = (float) ($otfData['fastag'] ?? 0);
    $codCharges          = (float) ($otfData['cod_charges'] ?? 0);
    $chargerSwappingAmount = (float) ($otfData['charger_swapping_amount'] ?? 0);
    $chargerSwapping     = $otfData['charger_swapping'] ?? '';
    $tcs                 = (float) ($otfData['tcs'] ?? 0);
    $totalReceivable     = (float) ($otfData['total_receivable'] ?? 0);

    /* ---------- DISCOUNT DETAILS — Group A / B / C selection logic ---------- */
    $cashSchemeOem       = (float) ($otfData['cash_scheme_oem'] ?? 0);
    $csdDiscount         = (float) ($otfData['csd_discount'] ?? 0);
    $fameSubsidy         = (float) ($otfData['fame_subsidy'] ?? 0);
    $dealerDiscount      = (float) ($otfData['dealer_discount'] ?? 0);
    $accessoriesDiscount = (float) ($otfData['accessories_discount'] ?? 0);
    $shieldScheme        = (float) ($otfData['shield_scheme'] ?? 0);
    $corporateDiscount   = (float) ($otfData['corporate_discount'] ?? 0);
    $exchangeBonus       = (float) ($otfData['exchange_bonus'] ?? 0);
    $greenBonus          = (float) ($otfData['green_bonus'] ?? 0);
    $welcomeBonus        = (float) ($otfData['welcome_bonus'] ?? 0);
    $loyaltyBonus        = (float) ($otfData['loyalty_bonus'] ?? 0);
    $accessoriesSplDisc  = (float) ($otfData['accessories_spl_disc'] ?? 0);
    $ceramicDiscount     = (float) ($otfData['ceramic_discount'] ?? 0);
    $ppfDiscount         = (float) ($otfData['ppf_discount'] ?? 0);
    $chargerSwappingDisc = (float) ($otfData['charger_swapping_discount'] ?? 0);
    $otherCashDiscount   = (float) ($otfData['other_cash_discount'] ?? 0);
    $specialCashDiscount = (float) ($otfData['special_cash_discount'] ?? 0);

    /* Group A: only the selected row is meaningful (matches form behaviour) */
    $groupASelected = 'cash_scheme_oem';
    if (!empty($csdDiscount))   { $groupASelected = 'csd_discount'; }
    if (!empty($fameSubsidy))   { $groupASelected = 'fame_subsidy'; }
    $groupAAmount = ${$groupASelected} ?? 0;

    /* Group C: only the selected row */
    $groupCSelected = 'exchange_bonus';
    if (!empty($greenBonus))   { $groupCSelected = 'green_bonus'; }
    if (!empty($welcomeBonus)) { $groupCSelected = 'welcome_bonus'; }
    if (!empty($loyaltyBonus)) { $groupCSelected = 'loyalty_bonus'; }
    $groupCAmount = ${$groupCSelected} ?? 0;

    /* Total discount — matches form's JS (all listed fields summed) */
    $totalDiscount = $groupAAmount + $dealerDiscount + $accessoriesDiscount
        + $shieldScheme + $corporateDiscount + $groupCAmount
        + $accessoriesSplDisc + $ceramicDiscount + $ppfDiscount
        + $chargerSwappingDisc + $otherCashDiscount + $specialCashDiscount;

    /* Net receivable: prefer the explicitly-saved value, else compute */
    $netReceivable = (float) ($otfData['net_receivable_summary']
        ?? $otfData['net_receivable']
        ?? ($totalReceivable - $totalDiscount));

    /* ---------- FINANCIER DETAILS (form writes these exact keys) ---------- */
    $loanAmount          = (float) ($otfData['loan_amount']         ?? $finance?->loan_amount        ?? 0);
    $fileCharge          = (float) ($otfData['file_charge']         ?? $finance?->file_charge        ?? 0);
    $marginMoney         = (float) ($otfData['margin_money']        ?? $finance?->margin             ?? 0);
    $financierSubvention = (float) ($otfData['financier_subvention']?? $finance?->subvention_amount ?? 0);
    $doAmount            = (float) ($otfData['net_settlement_amount']
        ?? ($loanAmount - $fileCharge + $marginMoney - $financierSubvention));

    $expectedBalance  = (float) ($otfData['expected_balance']
        ?? ($netReceivable - $doAmount - $receiptTotal + (float) ($otfData['do_settlement_difference'] ?? 0)));
    $doSettlementDiff = (float) ($otfData['do_settlement_difference'] ?? 0);
    $finalBalance     = (float) ($otfData['final_balance'] ?? ($expectedBalance - $jvAmount));

    /* ---------- DO / TA statement (form saves these directly) ---------- */
    $doNumber        = $otfData['do_number']    ?? $finance?->instrument_ref_no ?? 'N/A';
    $doNumberTa      = $otfData['do_number_ta'] ?? $taStatement?->do_no ?? 'N/A';
    $doAmountTa      = (float) ($otfData['do_amount_ta'] ?? $taStatement?->credit_amount ?? 0);
    $doVoucherDate   = $otfData['do_voucher_date'] ?? $taStatement?->created_at ?? null;

    /* ---------- Insurance / Accessories itemization (service exports these) ---------- */
    $insurancePrintData   = $insurancePrintData   ?? [];
    $accessoriesPrintData = $accessoriesPrintData ?? [];

    $insuranceText = collect($insurancePrintData)->map(function ($cover) {
        $name  = trim($cover['name'] ?? '');
        $price = (float) ($cover['price'] ?? 0);
        return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
    })->filter()->implode(' · ');

    $accessoriesText = collect($accessoriesPrintData)->map(function ($acc) {
        $name  = trim($acc['name'] ?? '');
        $price = (float) ($acc['price'] ?? 0);
        return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
    })->filter()->implode(' · ');
@endphp

<!-- ================= PAGE 1 ================= -->
<table class="brand-header-table">
    <tr>
        <td style="width:20%; text-align:left; vertical-align:middle;">
            <img src="{{ asset('images/bikaner_logo.png') }}" style="height:55px;">
        </td>
        <td style="width:60%; text-align:center; vertical-align:middle;">
            <div class="dealer-title-main">Bikaner Motors Private Limited</div>
            <div class="dealer-address-lines">
                <strong>Registered Head Office:</strong>
                Sunehri Chhabil Mansion, NH-11, Jaipur Road, Bikaner-334022
                <br>
                <strong>Regional Branch Node:</strong>
                6th KM Stone, Ratangarh Road, Churu-331001
            </div>
            <div class="sheet-badge-title">Vehicle Transaction Sheet</div>
            <div class="sheet-meta-lines">
                <strong>Xceler8 Booking ID:</strong> XB-{{ $booking->id }}
                &nbsp;|&nbsp;
                <strong>VOTF No:</strong>
                {{ $otfData['votf_no'] ?? $booking->votf_no ?? 'N/A' }}
                <br>
                <strong>Corporate GSTIN:</strong>
                {{ $booking->gstn ?? 'N/A' }}
            </div>
        </td>
        <td style="width:20%; text-align:right; vertical-align:middle;">
            <img src="{{ $mahindraLogo }}" class="logo-img">
        </td>
    </tr>
</table>

<!-- ================= TABLE 1: VEHICLE DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Vehicle Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">GST No.</td>
            <td class="field-value">{{ $booking->gstn ?? $otfData['gstn'] ?? 'N/A' }}</td>
            <td class="field-label">Customer Category</td>
            <td class="field-value">{{ $otfData['b_cat'] ?? $booking->b_cat ?? 'Individual' }}</td>
        </tr>
        <tr>
            <td class="field-label">Retail Category</td>
            <td class="field-value">{{ $otfData['retail_category'] ?? 'Normal' }}</td>
            <td class="field-label">Segment</td>
            <td class="field-value">{{ $segment?->name ?? $booking->segment_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Model</td>
            <td class="field-value">{{ $model?->name ?? $booking->model_code ?? 'N/A' }}</td>
            <td class="field-label">Variant</td>
            <td class="field-value">{{ $variant?->display_name ?? $variant?->custom_name ?? $variant?->oem_name ?? $booking->variant_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Color</td>
            <td class="field-value">{{ $color?->name ?? $booking->color_code ?? 'N/A' }}</td>
            <td class="field-label">Body Type</td>
            <td class="field-value">{{ $body_type_map[$bodyTypeVal] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Sale Type</td>
            <td class="field-value">{{ $sale_type_map[$saleTypeVal] ?? 'N/A' }}</td>
            <td class="field-label">Registration Type</td>
            <td class="field-value">{{ $reg_no_type_map[$regNoTypeVal] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Registration Category</td>
            <td class="field-value">{{ $registration_category_map[$regCatVal] ?? $registration_type_map[$regCatVal] ?? 'N/A' }}</td>
            <td class="field-label">Permit</td>
            <td class="field-value">{{ $permit_map[$permitVal] ?? 'N/A' }}</td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 2: CONSULTANT DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Consultant Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">SC Name</td>
            <td class="field-value">{{ $scName ?: 'N/A' }}</td>
            <td class="field-label">SC Mile ID</td>
            <td class="field-value">{{ $scMileId ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">SC Branch</td>
            <td class="field-value">{{ $scBranch ?: 'N/A' }}</td>
            <td class="field-label">SC Location</td>
            <td class="field-value">{{ $scLocation ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">DMS Enquiry No.</td>
            <td class="field-value">{{ $booking->dms_no ?? 'N/A' }}</td>
            <td class="field-label">DMS OTF No.</td>
            <td class="field-value">{{ $booking->dms_otf ?? $otfData['dms_otf'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Xcler8 Booking ID</td>
            <td class="field-value" colspan="3">XB-{{ $booking->id }}</td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 3: ADDITIONAL SALES DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Additional Sales Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">DSA Retail</td>
            <td class="field-value">{{ !empty($otfData['dsa_id'] ?? $booking->dsa_id) ? 'Yes' : 'No' }}</td>
            <td class="field-label">DSA Name</td>
            <td class="field-value">{{ $dsaName }}</td>
        </tr>
        <tr>
            <td class="field-label">DSA Location</td>
            <td class="field-value">{{ $dsaLocation }}</td>
            <td class="field-label">Exchange</td>
            <td class="field-value">{{ $otfData['exchange'] ?? $booking->buyer_type ?? 'NA' }}</td>
        </tr>
        <tr>
            <td class="field-label">In House RTO</td>
            <td class="field-value">{{ ($otfData['in_house_rto'] ?? $rto?->in_house_rto ?? 0) == 1 ? 'Yes' : 'No' }}</td>
            <td class="field-label"></td>
            <td class="field-value"></td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 4: CUSTOMER INFORMATION ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Customer Information</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">VOTF No.</td>
            <td class="field-value">{{ $otfData['votf_no'] ?? $booking->votf_no ?? 'N/A' }}</td>
            <td class="field-label">Customer Name</td>
            <td class="field-value">{{ $enquiry?->name ?? $booking->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Registration Address</td>
            <td class="field-value" colspan="3">{{ $otfData['registration_address'] ?? $enquiry?->customer_address ?? $booking->address ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Customer Tehsil</td>
            <td class="field-value">{{ $otfData['customer_tehsil'] ?? $enquiry?->tehsil ?? 'N/A' }}</td>
            <td class="field-label">Customer District</td>
            <td class="field-value">{{ $otfData['customer_district'] ?? $enquiry?->district ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Pincode</td>
            <td class="field-value">{{ $otfData['pincode'] ?? $enquiry?->zipcode ?? 'N/A' }}</td>
            <td class="field-label">Customer Contact No.</td>
            <td class="field-value">{{ $enquiry?->mobile ?? $booking->mobile ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Date of Birth</td>
            <td class="field-value">{{ $enquiry?->dob ? \Carbon\Carbon::parse($enquiry->dob)->format('d-M-Y') : ($booking->c_dob ? \Carbon\Carbon::parse($booking->c_dob)->format('d-M-Y') : 'N/A') }}</td>
            <td class="field-label">Marital Status</td>
            <td class="field-value">{{ $otfData['marital_status'] ?? $booking->marital_status ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Date of Anniversary</td>
            <td class="field-value">{{ $otfData['anniversary_date'] ?? $enquiry?->marriage_date ?? $booking->anniversary_date ?? 'N/A' }}</td>
            <td class="field-label">Email ID</td>
            <td class="field-value">{{ $otfData['email'] ?? $enquiry?->email ?? $booking->email ?? 'N/A' }}</td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 5: CONTACT & PERSONAL DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Contact & Personal Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">Contact Person (If Any Other)</td>
            <td class="field-value">{{ $otfData['contact_person'] ?? $booking->contact_person ?? 'N/A' }}</td>
            <td class="field-label">Contact Person Contact No.</td>
            <td class="field-value">{{ $otfData['contact_person_mobile'] ?? $booking->contact_person_mobile ?? 'N/A' }}</td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 6: KYC & NOMINEE DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">KYC & Nominee Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">PAN No.</td>
            <td class="field-value" style="font-family: monospace;">{{ $booking->pan_no ?? $otfData['pan_no'] ?? 'N/A' }}</td>
            <td class="field-label">Aadhaar No.</td>
            <td class="field-value" style="font-family: monospace;">{{ $booking->adhar_no ?? $otfData['adhar_no'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Driving License No.</td>
            <td class="field-value" style="font-family: monospace;">{{ $otfData['driving_license_no'] ?? 'N/A' }}</td>
            <td class="field-label">Voter ID No.</td>
            <td class="field-value" style="font-family: monospace;">{{ $otfData['voter_id_no'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Nominee Name (For Insurance)</td>
            <td class="field-value">{{ $otfData['nominee_name'] ?? 'N/A' }}</td>
            <td class="field-label">Relation with Nominee</td>
            <td class="field-value">{{ $otfData['nominee_relation'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Age of Nominee</td>
            <td class="field-value" colspan="3">{{ $otfData['nominee_age'] ?? 'N/A' }} Years</td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 7: VEHICLE DELIVERY / INVOICE DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Vehicle Delivery / Invoice Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">Chassis No.</td>
            <td class="field-value" style="font-family: monospace; font-weight: 700;">{{ $booking->chassis_no ?? $otfData['chassis'] ?? 'N/A' }}</td>
            <td class="field-label">Engine No.</td>
            <td class="field-value" style="font-family: monospace;">{{ $otfData['engine_no'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Chassis Image</td>
            <td class="field-value">@if(!empty($chassisImage)) ✓ Uploaded @else N/A @endif</td>
            <td class="field-label">OEM Model Code</td>
            <td class="field-value">{{ $otfData['oem_model_code'] ?? $variant?->oem_name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">GST Slab</td>
            <td class="field-value">{{ $otfData['gst_slab'] ?? $variant?->gst_slab ?? 'N/A' }}</td>
            <td class="field-label">Invoice No.</td>
            <td class="field-value">{{ $booking->inv_no ?? $otfData['inv_no'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Invoice Date</td>
            <td class="field-value" colspan="3">
                @php
                    $invDate = $booking->inv_date ?? $otfData['inv_date'] ?? null;
                @endphp
                {{ $invDate ? \Carbon\Carbon::parse($invDate)->format('d-M-Y') : 'N/A' }}
            </td>
        </tr>
    </table>
</div>

<!-- PAGE BREAK -->
<div class="page-break"></div>

<!-- ================= PAGE 2 ================= -->
<table class="split-pane-layout">
    <tr>
        <td class="split-pane-column">
            <div class="info-card-container">
                <div class="card-header-banner">Price Details</div>
                <table class="accounting-ledger-table">
                    <thead>
                        <tr><th>Fee Statement Component</th><th class="text-align-right">Amount (INR)</th></tr>
                    </thead>
                    <tbody>
                        @if($exShowroom > 0)
                        <tr><td>Ex-Showroom Price</td><td class="text-align-right">₹ {{ number_format($exShowroom, 2) }}</td></tr>
                        @endif

                        @if($insuranceAmount > 0 || !empty($insuranceCompany))
                        <tr>
                            <td>Insurance @if($insuranceCompany)({{ $insuranceCompany }})@endif</td>
                            <td class="text-align-right">₹ {{ number_format($insuranceAmount, 2) }}</td>
                        </tr>
                        @endif

                        @if($registrationAmount > 0)
                        <tr>
                            <td>
                                Registration
                                @if($regNoTypeVal)({{ $reg_no_type_map[$regNoTypeVal] ?? '' }})@endif
                                @if($regCatVal !== '' && $regCatVal !== null)({{ $registration_category_map[$regCatVal] ?? $registration_type_map[$regCatVal] ?? '' }})@endif
                                @if(isset($otfData['in_house_rto']) && $otfData['in_house_rto'] !== '')
                                    (In-House: {{ $otfData['in_house_rto'] == '1' ? 'Yes' : 'No' }})
                                @endif
                            </td>
                            <td class="text-align-right">₹ {{ number_format($registrationAmount, 2) }}</td>
                        </tr>
                        @endif

                        @if($accessoriesAmount > 0)
                        <tr><td>Accessories</td><td class="text-align-right">₹ {{ number_format($accessoriesAmount, 2) }}</td></tr>
                        @endif

                        @if($maxicare > 0)
                        <tr><td>Maxicare</td><td class="text-align-right">₹ {{ number_format($maxicare, 2) }}</td></tr>
                        @endif

                        @if($vltdDevice > 0)
                        <tr><td>VLTD Device (GPS)</td><td class="text-align-right">₹ {{ number_format($vltdDevice, 2) }}</td></tr>
                        @endif

                        @if($coatingPrice > 0)
                        <tr>
                            <td>Coating @if($coating && $coating != 'No Coating')({{ $coating }})@endif</td>
                            <td class="text-align-right">₹ {{ number_format($coatingPrice, 2) }}</td>
                        </tr>
                        @endif

                        @if($ppf > 0)
                        <tr><td>PPF</td><td class="text-align-right">₹ {{ number_format($ppf, 2) }}</td></tr>
                        @endif

                        @if($rtoYellowTape > 0)
                        <tr><td>RTO Yellow Tape</td><td class="text-align-right">₹ {{ number_format($rtoYellowTape, 2) }}</td></tr>
                        @endif

                        @if($kazamChargingKit > 0)
                        <tr><td>Kazam Charging Kit</td><td class="text-align-right">₹ {{ number_format($kazamChargingKit, 2) }}</td></tr>
                        @endif

                        @if($incidentalCharges > 0)
                        <tr><td>Incidental Charges</td><td class="text-align-right">₹ {{ number_format($incidentalCharges, 2) }}</td></tr>
                        @endif

                        @if($shieldPrice > 0)
                        <tr>
                            <td>Shield @if($shield && $shield != 'No Shield')({{ $shield }})@endif</td>
                            <td class="text-align-right">₹ {{ number_format($shieldPrice, 2) }}</td>
                        </tr>
                        @endif

                        @if($rsaAmount > 0)
                        <tr>
                            <td>RSA @if($rsa && $rsa != 'No RSA')({{ $rsa }})@endif</td>
                            <td class="text-align-right">₹ {{ number_format($rsaAmount, 2) }}</td>
                        </tr>
                        @endif

                        @if($fastag > 0)
                        <tr><td>Fastag</td><td class="text-align-right">₹ {{ number_format($fastag, 2) }}</td></tr>
                        @endif

                        @if($codCharges > 0)
                        <tr><td>COD Charges</td><td class="text-align-right">₹ {{ number_format($codCharges, 2) }}</td></tr>
                        @endif

                        @if($chargerSwappingAmount > 0)
                        <tr>
                            <td>Charger Swapping @if($chargerSwapping && $chargerSwapping != 'N/A')({{ $chargerSwapping }})@endif</td>
                            <td class="text-align-right">₹ {{ number_format($chargerSwappingAmount, 2) }}</td>
                        </tr>
                        @endif

                        @if($tcs > 0)
                        <tr><td>TCS @1%</td><td class="text-align-right">₹ {{ number_format($tcs, 2) }}</td></tr>
                        @endif

                        <tr style="font-weight:700; background-color:#f1f5f9;">
                            <td>TOTAL RECEIVABLE</td>
                            <td class="text-align-right">₹ {{ number_format($totalReceivable, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>

        <td class="split-pane-spacer"></td>

        <td class="split-pane-column">
            <div class="info-card-container">
                <div class="card-header-banner">Discount Details</div>
                <table class="accounting-ledger-table">
                    <thead>
                        <tr><th>Promotional Scheme Channel</th><th class="text-align-right">Value (INR)</th></tr>
                    </thead>
                    <tbody>
                        {{-- Group A: only the selected row (mirrors form behaviour) --}}
                        @if($groupASelected === 'cash_scheme_oem' && $cashSchemeOem > 0)
                        <tr><td>Cash Scheme OEM</td><td class="text-align-right">₹ {{ number_format($cashSchemeOem, 2) }}</td></tr>
                        @elseif($groupASelected === 'csd_discount' && $csdDiscount > 0)
                        <tr><td>CSD Discount</td><td class="text-align-right">₹ {{ number_format($csdDiscount, 2) }}</td></tr>
                        @elseif($groupASelected === 'fame_subsidy' && $fameSubsidy > 0)
                        <tr><td>Fame Subsidy (LMM)</td><td class="text-align-right">₹ {{ number_format($fameSubsidy, 2) }}</td></tr>
                        @endif

                        @if($dealerDiscount > 0)
                        <tr><td>Cash Scheme Dealer</td><td class="text-align-right">₹ {{ number_format($dealerDiscount, 2) }}</td></tr>
                        @endif

                        @if($accessoriesDiscount > 0)
                        <tr><td>Accessories Scheme</td><td class="text-align-right">₹ {{ number_format($accessoriesDiscount, 2) }}</td></tr>
                        @endif

                        @if($shieldScheme > 0)
                        <tr><td>Shield Scheme</td><td class="text-align-right">₹ {{ number_format($shieldScheme, 2) }}</td></tr>
                        @endif

                        @if($corporateDiscount > 0)
                        <tr><td>Corporate Discount</td><td class="text-align-right">₹ {{ number_format($corporateDiscount, 2) }}</td></tr>
                        @endif

                        {{-- Group C: only the selected row --}}
                        @if($groupCSelected === 'exchange_bonus' && $exchangeBonus > 0)
                        <tr><td>Exchange Bonus</td><td class="text-align-right">₹ {{ number_format($exchangeBonus, 2) }}</td></tr>
                        @elseif($groupCSelected === 'green_bonus' && $greenBonus > 0)
                        <tr><td>Green Bonus</td><td class="text-align-right">₹ {{ number_format($greenBonus, 2) }}</td></tr>
                        @elseif($groupCSelected === 'welcome_bonus' && $welcomeBonus > 0)
                        <tr><td>Welcome Bonus</td><td class="text-align-right">₹ {{ number_format($welcomeBonus, 2) }}</td></tr>
                        @elseif($groupCSelected === 'loyalty_bonus' && $loyaltyBonus > 0)
                        <tr><td>Loyalty Bonus</td><td class="text-align-right">₹ {{ number_format($loyaltyBonus, 2) }}</td></tr>
                        @endif

                        @if($accessoriesSplDisc > 0)
                        <tr><td>Accessories Spl Disc</td><td class="text-align-right">₹ {{ number_format($accessoriesSplDisc, 2) }}</td></tr>
                        @endif

                        @if($ceramicDiscount > 0)
                        <tr>
                            <td>
                                @if($coating && $coating != 'No Coating'){{ $coating }} @endif
                                Coating Spl Discount
                            </td>
                            <td class="text-align-right">₹ {{ number_format($ceramicDiscount, 2) }}</td>
                        </tr>
                        @endif

                        @if($ppfDiscount > 0)
                        <tr><td>PPF Spl Discount</td><td class="text-align-right">₹ {{ number_format($ppfDiscount, 2) }}</td></tr>
                        @endif

                        @if($chargerSwappingDisc > 0)
                        <tr>
                            <td>
                                Charger Swapping Discount
                                @if(!empty($otfData['charger_swapping_option']) && $otfData['charger_swapping_option'] != 'N/A')
                                    ({{ $otfData['charger_swapping_option'] }})
                                @endif
                            </td>
                            <td class="text-align-right">₹ {{ number_format($chargerSwappingDisc, 2) }}</td>
                        </tr>
                        @endif

                        @if($otherCashDiscount > 0)
                        <tr><td>Other Cash Discount</td><td class="text-align-right">₹ {{ number_format($otherCashDiscount, 2) }}</td></tr>
                        @endif

                        @if($specialCashDiscount > 0)
                        <tr><td>Special Cash Discount</td><td class="text-align-right">₹ {{ number_format($specialCashDiscount, 2) }}</td></tr>
                        @endif

                        <tr style="font-weight:700; background-color:#f1f5f9;">
                            <td>TOTAL DISCOUNT</td>
                            <td class="text-align-right">₹ {{ number_format($totalDiscount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- ================= NET RECEIVABLE ================= -->
<table style="margin-top:-1px; border-top:1px solid #000; width:100%; border-collapse:collapse; margin-bottom:6px;">
    <tr>
        <td style="width:50%; background:#abb8ca; font-weight:bold; font-size:10px; border-right:1px solid #000; color:#000; padding:3px 5px;">NET RECEIVABLE</td>
        <td style="width:50%; padding:3px 5px; background:#abb8ca; color:#000; text-align:right; font-weight:bold;">
            ₹ {{ number_format($netReceivable, 2) }}
        </td>
    </tr>
</table>

<!-- ================= INSURANCE & ACCESSORIES ITEMIZATION ================= -->
@if($insuranceText || $accessoriesText)
<div class="info-card-container" style="margin-bottom: 5px;">
    @if($insuranceText)
    <div class="itemization-badge-strip" style="border-bottom: 1px solid #e2e8f0;">
        <span class="itemization-strip-title">Insurance:</span> {{ $insuranceText }}
    </div>
    @endif
    @if($accessoriesText)
    <div class="itemization-badge-strip">
        <span class="itemization-strip-title">Accessories:</span> {{ $accessoriesText }}
    </div>
    @endif
</div>
@endif

<!-- ================= FINANCIER + DO DETAILS (SPLIT) ================= -->
<table class="split-pane-layout">
    <tr>
        <td class="split-pane-column">
            <div class="info-card-container" style="margin-bottom: 0;">
                <div class="card-header-banner">Financier Details</div>
                <table class="accounting-ledger-table">
                    <tbody>
                        <tr>
                            <td style="font-weight:600; width:45%;">Financier Name</td>
                            <td style="text-align:right; width:55%;">{{ $financierDisplay }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Financier Branch</td>
                            <td style="text-align:right;">{{ $otfData['financier_branch'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Loan Amount</td>
                            <td style="text-align:right;">₹ {{ number_format($loanAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">File Charge / Processing</td>
                            <td style="text-align:right;">₹ {{ number_format($fileCharge, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Payment Made to Financier by Customer (If Any)</td>
                            <td style="text-align:right;">₹ {{ number_format($marginMoney, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Financier Subvention Amount (If Any)</td>
                            <td style="text-align:right;">₹ {{ number_format($financierSubvention, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">DO Amount</td>
                            <td style="text-align:right;">₹ {{ number_format($doAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Receipt Amount</td>
                            <td style="text-align:right;">₹ {{ number_format($receiptTotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Expected Balance</td>
                            <td style="text-align:right;">₹ {{ number_format($expectedBalance, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">DO Settlement Difference</td>
                            <td style="text-align:right;">₹ {{ number_format($doSettlementDiff, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Discount through JV</td>
                            <td style="text-align:right;">₹ {{ number_format($jvAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Final Balance</td>
                            <td style="text-align:right;">₹ {{ number_format($finalBalance, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>

        <td class="split-pane-spacer"></td>

        <td class="split-pane-column">
            <div class="info-card-container" style="margin-bottom: 0;">
                <div class="card-header-banner">DO Details</div>
                <table class="accounting-ledger-table">
                    <tbody>
                        <tr>
                            <td style="font-weight:600; width:45%;">Vehicle To Be Delivered On</td>
                            <td style="text-align:right; font-family: monospace; width:55%;">{{ $deliveryLabel }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">DO No. (Delivery Time)</td>
                            <td style="text-align:right; font-family: monospace;">{{ $doNumber ?: 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">DO No. (TA Statement)</td>
                            <td style="text-align:right; font-family: monospace;">{{ $doNumberTa ?: 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">DO Amount (TA Statement)</td>
                            <td style="text-align:right;">₹ {{ number_format($doAmountTa, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">DO Voucher Date</td>
                            <td style="text-align:right;">
                                {{ $doVoucherDate ? \Carbon\Carbon::parse($doVoucherDate)->format('d-M-Y') : 'N/A' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- ================= RECEIPT TABLE ================= -->
<div style="margin-top:4px;">
    <table class="receipt-table">
        <thead>
            <tr>
                <th style="width:30%;">Receipt No.</th>
                <th style="width:25%;">Date</th>
                <th style="width:25%;">Receipt Mode</th>
                <th style="width:20%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($receiptLogs as $receipt)
            <tr>
                <td style="font-weight:600;">{{ $receipt->receipt_no ?? $receipt->type_number ?? '' }}</td>
                <td>{{ $receipt->date ? \Carbon\Carbon::parse($receipt->date)->format('d M Y') : '' }}</td>
                <td>{{ $receipt->mode_name ?? $receipt->mode ?? '' }}</td>
                <td style="font-weight:600; color:#28a745;">₹ {{ number_format((float) $receipt->amount, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align:center; padding:10px;">No Receipts Found</td>
            </tr>
            @endforelse
        </tbody>
        @if($receiptLogs->count() > 0)
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right;">TOTAL:</td>
                <td style="font-weight:700; color:#28a745;">₹ {{ number_format($receiptTotal, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

<!-- ================= NOTE, CHASSIS & SIGNATURE ================= -->
<table class="note-box" style="width:100%; border-collapse:collapse; margin-top:4px; border:1px solid #000;">
    <tr>
        <td style="width:55%; vertical-align:top; border:1px solid #000; padding:4px 6px;">
            <div style="font-weight:bold; font-size:8px; margin-bottom:3px;">NOTE:</div>
            <p style="font-size:7px; font-weight:bold; line-height:1.3; text-align:justify; margin:0;">
                <b>1.</b> Vehicle shall be delivered only against payment.<br>
                <b>2.</b> Interest shall be charged @ 24% P.A. in case of payments delayed over three days.<br>
                <b>3.</b> No Interest shall be payable on Booking Amount.<br>
                <b>4.</b> Price &amp; Scheme of the vehicle is applicable as on the date of delivery. Price &amp; Scheme are subjected to change without any prior notice.<br>
                <b>5.</b> Self attested coloured copy of original documents is required for any claim. Claims will be rejected in absence of original documents.
            </p>
        </td>

        <td style="width:30%; vertical-align:middle; text-align:center; border:1px solid #000; padding:3px;">
            <table style="width:100%; height:90px; border-collapse:collapse;">
                <tr>
                    <td style="vertical-align:middle; text-align:center; padding:0; border:none;">
                        @if(!empty($chassisImage))
                            <img src="{{ $chassisImage }}" class="chassis-img">
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align:middle; text-align:center; padding:2px 0 0 0; border:none; font-size:8px; font-weight:bold; color:#000;">
                        Chassis Verification
                    </td>
                </tr>
            </table>
        </td>

        <td style="width:20%; vertical-align:bottom; text-align:center; border:1px solid #000; padding:0 3px 5px 3px; height:90px;">
            <div style="border-top:1px solid #000; width:85%; margin:0 auto; padding-top:3px;">
                <span style="font-size:7px; font-weight:bold;">Customer Signature</span>
            </div>
        </td>
    </tr>
</table>

</body>
</html>