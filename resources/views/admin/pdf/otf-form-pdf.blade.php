<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transaction Sheet - {{ $booking->votf_no ?? 'Booking' }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 10mm 12mm 10mm 12mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.3;
        }
        .page-break { page-break-after: always; }

        /* ================= HEADER ================= */
        .brand-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .brand-text-td {
            width: 50%;
            vertical-align: top;
        }
        .dealer-title-main {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0;
        }
        .dealer-address-lines {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
            margin-top: 2px;
        }
        .badge-align-box {
            text-align: right;
            vertical-align: top;
        }
        .sheet-badge-title {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sheet-meta-lines {
            font-size: 10px;
            color: #334155;
            margin-top: 3px;
            line-height: 1.2;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }
        .logo-img {
            max-height: 50px;
            max-width: 80px;
            object-fit: contain;
        }

        /* ================= INFO CARDS ================= */
        .info-card-container {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #ffffff;
            margin-bottom: 10px;
        }
        .card-header-banner {
            background-color: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            padding: 3.5px 6px;
            font-size: 10px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ================= DATA GRID ================= */
        .card-data-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .card-data-grid td {
            padding: 2.5px 6px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            width: 25%;
            font-size: 10px;
            color: #0f172a;
        }
        .card-data-grid tr:last-child td {
            border-bottom: none;
        }
        .field-label {
            font-weight: 600;
            color: #0f172a;
            font-size: 10px;
            /* text-transform: uppercase; */
            width: 20%;
        }
        .field-value {
            font-weight: 500;
            width: 30%;
        }

        /* ================= SPLIT PANEL ================= */
        .split-pane-layout {
            width: 100%;
            border-collapse: collapse;
        }
        .split-pane-column {
            width: 49.3%;
            vertical-align: top;
        }
        .split-pane-spacer {
            width: 1.4%;
        }

        /* ================= ACCOUNTING LEDGER ================= */
        .accounting-ledger-table {
            width: 100%;
            border-collapse: collapse;
        }
        .accounting-ledger-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            padding: 3px 5px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        .accounting-ledger-table td {
            padding: 2.2px 5px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .text-align-right {
            text-align: right;
        }
        .text-align-center {
            text-align: center;
        }

        /* ================= ITEMIZATION BADGES ================= */
        .itemization-badge-strip {
            padding: 4px 6px;
            background-color: #f8fafc;
            font-size: 10px;
            color: #334155;
            line-height: 1.3;
        }
        .itemization-strip-title {
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 10px;
            margin-right: 3px;
        }

        /* ================= SYNTHESIS SUMMARY ================= */
        .synthesis-summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            border: 1px solid #cbd5e1;
        }
        .synthesis-summary-table td {
            padding: 3.5px 6px;
            font-size: 10px;
        }
        .net-receivable-marker {
            background-color: #f0fdf4;
            color: #166534;
            font-weight: 700;
            border-bottom: 1px solid #bbf7d0;
        }
        .credit-offset-marker {
            background-color: #ffffff;
            color: #475569;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        .final-balance-marker {
            background-color: #fef2f2;
            color: #991b1b;
            font-weight: 800;
            font-size: 10px;
        }

        /* ================= LEGAL CLAUSES ================= */
        .legal-clauses-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 4px 8px;
            margin-top: 4px;
        }
        .legal-clauses-box h4 {
            margin: 0 0 2px 0;
            color: #1e293b;
            font-size: 10px;
            text-transform: uppercase;
        }
        .legal-clauses-box ol {
            margin: 0;
            padding-left: 10px;
            color: #475569;
            font-size: 10px;
        }
        .legal-clauses-box li {
            margin-bottom: 1px;
        }

        /* ================= SIGNATURE ================= */
        .signature-footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .signature-station-box {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-line-axis {
            border-top: 1px solid #64748b;
            width: 60%;
            margin: 0 auto 3px auto;
        }
        .signature-title-label {
            font-size: 10px;
            color: #0f172a;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* ================= RECEIPT TABLE ================= */
        .receipt-table {
            width: 100%;
            border-collapse: collapse !important;
            border-spacing: 0;
            margin-bottom: 6px !important;
        }
        .receipt-table thead th {
            background: #f2f2f2 !important;
            border: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            font-size: 10px;
            font-weight: 600;
            text-transform: none;
            letter-spacing: normal;
            color: #000;
            padding: 3px 5px !important;
            height: 26px;
            vertical-align: middle;
        }
        .receipt-table tbody td {
            background: #fff !important;
            border: 1px solid #000 !important;
            padding: 3px 5px !important;
            height: 26px;
            vertical-align: middle;
            font-size: 10px;
            color: #000;
        }
        .receipt-table tfoot td {
            background: #f2f2f2 !important;
            border: 1px solid #000 !important;
            padding: 3px 5px !important;
            height: 26px;
            font-size: 10px;
            font-weight: 600;
            color: #000;
        }

        /* ================= NOTE & SIGNATURE BOX ================= */
        .note-box {
            border: 1px solid #000;
        }
        .note-box p {
            font-size: 7px;
            font-weight: bold;
            line-height: 1.3;
            text-align: justify;
            margin: 0;
        }
        .note-box .note-title {
            font-weight: bold;
            font-size: 8px;
            margin-bottom: 3px;
        }
        .chassis-label-vertical {
            
            font-size: 7px;
            font-weight: bold;
            white-space: nowrap;
            text-align: center;
        }
       .chassis-img {
            max-width: 170px;
            max-height: 70px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 85%;
            margin: 0 auto;
            padding-top: 3px;
        }
        .signature-label-text {
            font-size: 7px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- ================= PAGE 1 ================= -->
<!-- HEADER -->
<!-- ================= HEADER ================= -->
<table class="brand-header-table" style="width:100%; border-collapse:collapse; margin-bottom:8px;">
    <tr>

        {{-- LEFT: BIKANER MOTORS LOGO --}}
        <td style="width:20%; text-align:left; vertical-align:middle;">
            <img src="{{ asset('images/bikaner_logo.png') }}"
                 style="height:55px; max-height:55px; width:auto;">
        </td>

        {{-- CENTER: COMPANY + TRANSACTION DETAILS --}}
        <td style="width:60%; text-align:center; vertical-align:middle;">

            <div class="dealer-title-main"
                 style="font-size:13px; font-weight:800; color:#0f172a; text-transform:uppercase; margin:0;">
                Bikaner Motors Private Limited
            </div>

            <div class="dealer-address-lines"
                 style="font-size:8px; color:#64748b; line-height:1.3; margin-top:2px;">

                <strong>Registered Head Office:</strong>
                Sunehri Chhabil Mansion, NH-11, Jaipur Road, Bikaner-334022
                <br>

                <strong>Regional Branch Node:</strong>
                6th KM Stone, Ratangarh Road, Churu-331001

            </div>

            <div class="sheet-badge-title"
                 style="
                    display:inline-block;
                    background-color:#0f172a;
                    color:#ffffff;
                    font-weight:700;
                    font-size:8.5px;
                    padding:3px 8px;
                    border-radius:2px;
                    text-transform:uppercase;
                    letter-spacing:0.5px;
                    margin-top:4px;
                 ">
                Vehicle Transaction Sheet
            </div>

            <div class="sheet-meta-lines"
                 style="
                    font-size:8px;
                    color:#334155;
                    margin-top:3px;
                    line-height:1.2;
                 ">

                <strong>Xceler8 Booking ID:</strong>
                #{{ $booking->id }}

                &nbsp;|&nbsp;

                <strong>VOTF No:</strong>
                {{ $booking->votf_no ?? $otfData['votf_no'] ?? 'N/A' }}

                <br>

                <strong>Corporate GSTIN:</strong>
                {{ $booking->gstn ?? '27ABCDE1234F1Z5' }}

            </div>

        </td>

        {{-- RIGHT: MAHINDRA LOGO --}}
        <td style="width:20%; text-align:right; vertical-align:middle;">
            @php
                $segment = strtoupper($booking->segment_code ?? '');

                if ($segment == 'LMM') {
                    $mahindraLogo = asset('images/mahindra-lmm-logo.png');
                } elseif ($segment == 'BEV') {
                    $mahindraLogo = asset('images/mahindra-ev-logo.png');
                } else {
                    $mahindraLogo = asset('images/mahindra-pv-cv-logo.png');
                }
            @endphp

            <img src="{{ $mahindraLogo }}"
                 class="logo-img"
                 style="
                    max-height:40px;
                    max-width:80px;
                    width:auto;
                    height:auto;
                    object-fit:contain;
                 ">
        </td>

    </tr>
</table>

<!-- ================= TABLE 1: VEHICLE DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Vehicle Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">GST No.</td>
            <td class="field-value">{{ $booking->gstn ?? 'N/A' }}</td>
            <td class="field-label">Customer Category</td>
            <td class="field-value">{{ $booking->b_cat ?? $otfData['b_cat'] ?? 'Individual' }}</td>
        </tr>
        <tr>
            <td class="field-label">Retail Category</td>
            <td class="field-value">{{ $otfData['retail_category'] ?? 'Normal' }}</td>
            <td class="field-label">Segment</td>
            <td class="field-value">{{ $segment->name ?? $booking->segment_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Model</td>
            <td class="field-value">{{ $model->name ?? $booking->model_code ?? 'N/A' }}</td>
            <td class="field-label">Variant</td>
            <td class="field-value">{{ $variant->display_name ?? $variant->custom_name ?? $variant->oem_name ?? $booking->variant_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Color</td>
            <td class="field-value">{{ $color->name ?? $booking->color_code ?? 'N/A' }}</td>
            <td class="field-label">Body Type</td>
            <td class="field-value">{{ $body_type_map[$otfData['body_type'] ?? $rto?->body_type ?? ''] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Sale Type</td>
            <td class="field-value">{{ $sale_type_map[$otfData['sale_type'] ?? $rto?->sale_type ?? ''] ?? 'N/A' }}</td>
            <td class="field-label">Registration Type</td>
            <td class="field-value">{{ $reg_no_type_map[$otfData['registration_no_type'] ?? $rto?->rgn_no_type ?? ''] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Registration Category</td>
            <td class="field-value">{{ $registration_type_map[$otfData['registration_category'] ?? $rto?->registration_category ?? ''] ?? 'N/A' }}</td>
            <td class="field-label">Permit</td>
            <td class="field-value">{{ $permit_map[$otfData['permit'] ?? $rto?->permit ?? ''] ?? 'N/A' }}</td>
        </tr>
    </table>
</div>

<!-- ================= TABLE 2: CONSULTANT DETAILS ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Consultant Details</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">SC Name</td>
            <td class="field-value">{{ $consultants ? collect($consultants)->firstWhere('person_code', $booking->consultant)['display_name'] ?? $booking->consultant : 'N/A' }}</td>
            <td class="field-label">SC Mile ID</td>
            <td class="field-value">{{ $consultants ? collect($consultants)->firstWhere('person_code', $booking->consultant)['employee_code'] ?? '' : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">SC Branch</td>
            <td class="field-value">{{ $consultants ? collect($consultants)->firstWhere('person_code', $booking->consultant)['branch_name'] ?? 'Bikaner' : 'Bikaner' }}</td>
            <td class="field-label">SC Location</td>
            <td class="field-value">{{ $consultants ? collect($consultants)->firstWhere('person_code', $booking->consultant)['location_name'] ?? 'Bikaner' : 'Bikaner' }}</td>
        </tr>
        <tr>
            <td class="field-label">DMS Enquiry No.</td>
            <td class="field-value">{{ $booking->dms_no ?? 'N/A' }}</td>
            <td class="field-label">DMS OTF No.</td>
            <td class="field-value">{{ $booking->dms_otf ?? $otfData['dms_otf'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Xcler8 Booking ID</td>
            <td class="field-value" colspan="3">{{ $booking->id }}</td>
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
            <td class="field-value">{{ $dsaList->where('id', $otfData['dsa_id'] ?? $booking->dsa_id)->first()?->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">DSA Location</td>
            <td class="field-value">{{ $otfData['dsa_location'] ?? 'N/A' }}</td>
            <td class="field-label">Exchange</td>
            <td class="field-value">{{ $otfData['exchange'] ?? $booking->buyer_type ?? 'NA' }}</td>
        </tr>
        <tr>
            <td class="field-label">In House RTO</td>
            <td class="field-value">{{ ($otfData['in_house_rto'] ?? $rto?->in_house_rto ?? 0) == 1 ? 'Yes' : 'No' }}</td>
            {{-- <td class="field-label">Accessories Items List</td>
            <td class="field-value">
                @php
                    $accList = [];
                    if (!empty($otfData['accessories']) && is_array($otfData['accessories'])) {
                        foreach ($otfData['accessories'] as $accCode) {
                            $acc = DB::table('xlr8_vehicle_accessories')->where('part_no', trim($accCode))->first();
                            if ($acc) {
                                $accList[] = $acc->item . ' (₹' . number_format((float)$acc->ndp, 2) . ')';
                            }
                        }
                    }
                    echo implode(', ', $accList);
                @endphp
            </td> --}}
        </tr>
    </table>
</div>

<!-- ================= TABLE 4: CUSTOMER INFORMATION ================= -->
<div class="info-card-container">
    <div class="card-header-banner">Customer Information</div>
    <table class="card-data-grid">
        <tr>
            <td class="field-label">VOTF No.</td>
            <td class="field-value">{{ $booking->votf_no ?? $otfData['votf_no'] ?? 'N/A' }}</td>
            <td class="field-label">Customer Name</td>
            <td class="field-value">{{ $booking->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Registration Address</td>
            <td class="field-value" colspan="3">{{ $otfData['registration_address'] ?? $booking->address ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Customer Tehsil</td>
            <td class="field-value">{{ $otfData['customer_tehsil'] ?? 'N/A' }}</td>
            <td class="field-label">Customer District</td>
            <td class="field-value">{{ $otfData['customer_district'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Pincode</td>
            <td class="field-value">{{ $otfData['pincode'] ?? 'N/A' }}</td>
            <td class="field-label">Customer Contact No.</td>
            <td class="field-value">{{ $booking->mobile ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Date of Birth</td>
            <td class="field-value">{{ $booking->c_dob ? \Carbon\Carbon::parse($booking->c_dob)->format('d-M-Y') : 'N/A' }}</td>
            <td class="field-label">Marital Status</td>
            <td class="field-value">{{ $otfData['marital_status'] ?? $booking->marital_status ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Date of Anniversary</td>
            <td class="field-value">{{ $otfData['anniversary_date'] ?? $booking->anniversary_date ?? 'N/A' }}</td>
            <td class="field-label">Email ID</td>
            <td class="field-value">{{ $otfData['email'] ?? $booking->email ?? 'N/A' }}</td>
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
            <td class="field-value" style="font-family: monospace; font-weight: 700;">{{ $booking->chassis_no ?? 'N/A' }}</td>
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
            <td class="field-value">{{ $booking->inv_no ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="field-label">Invoice Date</td>
            <td class="field-value" colspan="3">{{ $booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('d-M-Y') : 'N/A' }}</td>
        </tr>
    </table>
</div>

<!-- PAGE BREAK -->
<div class="page-break"></div>

<!-- ================= PAGE 2 ================= -->
<!-- HEADER -->
<table class="brand-header-table">
    <tr>
        {{-- <td class="brand-text-td" style="width:60%;">
            <h1 class="dealer-title-main">Bikaner Motors Private Limited</h1>
        </td> --}}
        {{-- <td class="badge-align-box" style="width:40%; text-align:right; vertical-align:top;">
            <div class="sheet-badge-title">Commercial Audit Ledger</div>
        </td> --}}
    </tr>
</table>

<!-- ================= PRICE DETAILS & DISCOUNT DETAILS (HALF SECTION) ================= -->
<!-- ================= PRICE DETAILS & DISCOUNT DETAILS (HALF SECTION) ================= -->
<table class="split-pane-layout">
    <tr>
        <td class="split-pane-column">
            <!-- PRICE DETAILS -->
            <div class="info-card-container">
                <div class="card-header-banner">Price Details</div>
                <table class="accounting-ledger-table">
                    <thead>
                        <tr><th>Fee Statement Component</th><th class="text-align-right">Amount (INR)</th></tr>
                    </thead>
                    <tbody>
                        @php
                            $exShowroom = (float)($otfData['ex_showroom_price'] ?? 0);
                            $insuranceAmount = (float)($otfData['insurance_amount'] ?? 0);
                            $insuranceCompany = $otfData['insurance_company'] ?? '';
                            $registrationAmount = (float)($otfData['registration_amount'] ?? 0);
                            $accessoriesAmount = (float)($otfData['accessories_amount'] ?? 0);
                            $maxicare = (float)($otfData['maxicare'] ?? 0);
                            $vltdDevice = (float)($otfData['vltd_device'] ?? 0);
                            $coatingPrice = (float)($otfData['coating_price'] ?? 0);
                            $coating = $otfData['coating'] ?? '';
                            $ppf = (float)($otfData['ppf'] ?? 0);
                            $rtoYellowTape = (float)($otfData['rto_yellow_tape'] ?? 0);
                            $kazamChargingKit = (float)($otfData['kazam_charging_kit'] ?? 0);
                            $incidentalCharges = (float)($otfData['incidental_charges'] ?? 0);
                            $shieldPrice = (float)($otfData['shield_price'] ?? 0);
                            $shield = $otfData['shield'] ?? '';
                            $rsaAmount = (float)($otfData['rsa_amount'] ?? 0);
                            $rsa = $otfData['rsa'] ?? '';
                            $fastag = (float)($otfData['fastag'] ?? 0);
                            $codCharges = (float)($otfData['cod_charges'] ?? 0);
                            $chargerSwappingAmount = (float)($otfData['charger_swapping_amount'] ?? 0);
                            $chargerSwapping = $otfData['charger_swapping'] ?? '';
                            $tcs = (float)($otfData['tcs'] ?? 0);
                            $totalReceivable = (float)($otfData['total_receivable'] ?? 0);
                        @endphp

                        @if($exShowroom > 0)
                        <tr><td>Ex-Showroom Price</td><td class="text-align-right">₹ {{ number_format($exShowroom, 2) }}</td></tr>
                        @endif

                        @if($insuranceAmount > 0 || !empty($insuranceCompany) || !empty($otfData['insurance_covers']))
                        <tr>
                            <td>
                                Insurance
                                @if($insuranceCompany)
                                    ({{ $insuranceCompany }})
                                @endif
                            </td>
                            <td class="text-align-right">₹ {{ number_format($insuranceAmount, 2) }}</td>
                        </tr>
                        @endif

                        @if($registrationAmount > 0)
                        <tr>
                            <td>
                                Registration
                                @if($otfData['registration_no_type'] ?? '')
                                    ({{ $reg_no_type_map[$otfData['registration_no_type']] ?? '' }})
                                @endif
                                @if(($otfData['registration_category'] ?? '') !== '' && ($otfData['registration_category'] ?? '') !== null)
                                    ({{ $registration_type_map[$otfData['registration_category']] ?? '' }})
                                @endif
                                @if(($otfData['in_house_rto'] ?? '') !== '')
                                    (In-House: {{ ($otfData['in_house_rto'] ?? '') == '1' ? 'Yes' : 'No' }})
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
                            <td>
                                Coating
                                @if($coating && $coating != 'No Coating')
                                    ({{ $coating }})
                                @endif
                            </td>
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
                            <td>
                                Shield
                                @if($shield && $shield != 'No Shield')
                                    ({{ $shield }})
                                @endif
                            </td>
                            <td class="text-align-right">₹ {{ number_format($shieldPrice, 2) }}</td>
                        </tr>
                        @endif

                        @if($rsaAmount > 0)
                        <tr>
                            <td>
                                RSA
                                @if($rsa && $rsa != 'No RSA')
                                    ({{ $rsa }})
                                @endif
                            </td>
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
                            <td>
                                Charger Swapping
                                @if($chargerSwapping && $chargerSwapping != 'N/A')
                                    ({{ $chargerSwapping }})
                                @endif
                            </td>
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
            <!-- DISCOUNT DETAILS -->
            <div class="info-card-container">
                <div class="card-header-banner">Discount Details</div>
                <table class="accounting-ledger-table">
                    <thead>
                        <tr><th>Promotional Scheme Channel</th><th class="text-align-right">Value (INR)</th></tr>
                    </thead>
                    <tbody>
                        @php
                            $cashSchemeOem = (float)($otfData['cash_scheme_oem'] ?? 0);
                            $csdDiscount = (float)($otfData['csd_discount'] ?? 0);
                            $fameSubsidy = (float)($otfData['fame_subsidy'] ?? 0);
                            $dealerDiscount = (float)($otfData['dealer_discount'] ?? 0);
                            $accessoriesDiscount = (float)($otfData['accessories_discount'] ?? 0);
                            $shieldScheme = (float)($otfData['shield_scheme'] ?? 0);
                            $corporateDiscount = (float)($otfData['corporate_discount'] ?? 0);
                            $loyaltyBonus = (float)($otfData['loyalty_bonus'] ?? 0);
                            $exchangeBonus = (float)($otfData['exchange_bonus'] ?? 0);
                            $greenBonus = (float)($otfData['green_bonus'] ?? 0);
                            $welcomeBonus = (float)($otfData['welcome_bonus'] ?? 0);
                            $accessoriesSplDisc = (float)($otfData['accessories_spl_disc'] ?? 0);
                            $ceramicDiscount = (float)($otfData['ceramic_discount'] ?? 0);
                            $coating = $otfData['coating'] ?? '';
                            $ppfDiscount = (float)($otfData['ppf_discount'] ?? 0);
                            $chargerSwappingDiscount = (float)($otfData['charger_swapping_discount'] ?? 0);
                            $otherCashDiscount = (float)($otfData['other_cash_discount'] ?? 0);
                            $specialCashDiscount = (float)($otfData['special_cash_discount'] ?? 0);
                            $totalDiscount = (float)($otfData['total_discount'] ?? 0);
                        @endphp

                        {{-- Group A: Cash Scheme OEM / CSD Discount / Fame Subsidy --}}
                        @if($cashSchemeOem > 0)
                        <tr><td>Cash Scheme OEM</td><td class="text-align-right">₹ {{ number_format($cashSchemeOem, 2) }}</td></tr>
                        @endif

                        @if($csdDiscount > 0)
                        <tr><td>CSD Discount</td><td class="text-align-right">₹ {{ number_format($csdDiscount, 2) }}</td></tr>
                        @endif

                        @if($fameSubsidy > 0)
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

                        {{-- Group C: Exchange / Green / Welcome / Loyalty Bonus --}}
                        @if($exchangeBonus > 0)
                        <tr><td>Exchange Bonus</td><td class="text-align-right">₹ {{ number_format($exchangeBonus, 2) }}</td></tr>
                        @endif

                        @if($greenBonus > 0)
                        <tr><td>Green Bonus</td><td class="text-align-right">₹ {{ number_format($greenBonus, 2) }}</td></tr>
                        @endif

                        @if($welcomeBonus > 0)
                        <tr><td>Welcome Bonus</td><td class="text-align-right">₹ {{ number_format($welcomeBonus, 2) }}</td></tr>
                        @endif

                        @if($loyaltyBonus > 0)
                        <tr><td>Loyalty Bonus</td><td class="text-align-right">₹ {{ number_format($loyaltyBonus, 2) }}</td></tr>
                        @endif

                        @if($accessoriesSplDisc > 0)
                        <tr><td>Accessories Spl Disc</td><td class="text-align-right">₹ {{ number_format($accessoriesSplDisc, 2) }}</td></tr>
                        @endif

                        @if($ceramicDiscount > 0)
                        <tr>
                            <td>
                                @if($coating && $coating != 'No Coating')
                                    {{ $coating }} 
                                @endif
                                Coating Spl Discount
                            </td>
                            <td class="text-align-right">₹ {{ number_format($ceramicDiscount, 2) }}</td>
                        </tr>
                        @endif

                        @if($ppfDiscount > 0)
                        <tr><td>PPF Spl Discount</td><td class="text-align-right">₹ {{ number_format($ppfDiscount, 2) }}</td></tr>
                        @endif

                        @if($chargerSwappingDiscount > 0)
                        <tr>
                            <td>
                                Charger Swapping Discount
                                @if(!empty($otfData['charger_swapping_option']) && $otfData['charger_swapping_option'] != 'N/A')
                                    ({{ $otfData['charger_swapping_option'] }})
                                @endif
                            </td>
                            <td class="text-align-right">₹ {{ number_format($chargerSwappingDiscount, 2) }}</td>
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

<!-- ================= TOTAL DISCOUNT & NET RECEIVABLE ================= -->
{{-- <table class="bill-table" style="margin-top:-1px; border-top:1px solid #000; width:100%; border-collapse:collapse; margin-bottom:6px;">
    <tr>
        <td class="title" style="width:50%; background:#f2f2f2; font-weight:bold; font-size:10px; border-right:1px solid #000; padding:3px 5px;">TOTAL DISCOUNT</td>
        <td style="width:50%; padding:3px 5px;">
            <input type="text" readonly style="width:100%; border:none; background:transparent; font-size:10px; font-weight:bold; text-align:right;" value="{{ number_format($totalDiscount, 2) }}">
        </td>
    </tr>
</table> --}}
<table class="bill-table" style="margin-top:-1px; border-top:1px solid #000; width:100%; border-collapse:collapse; margin-bottom:6px;">
    <tr>
        <td class="title" style="width:50%; background:#abb8ca; font-weight:bold; font-size:10px; border-right:1px solid #000; color:#000; padding:3px 5px;">NET RECEIVABLE</td>
        <td style="width:50%; padding:3px 5px; background:#abb8ca; color:#000;">
            <input type="text" readonly style="width:100%; border:none; background:transparent; font-size:10px; font-weight:bold; text-align:right;" value="{{ number_format((float)($otfData['net_receivable_summary'] ?? 0), 2) }}">
        </td>
    </tr>
</table>

<!-- ================= INSURANCE & ACCESSORIES ITEMIZATION ================= -->
@php
    $insuranceCovers = $otfData['insurance_covers'] ?? [];
    $insuranceText = '';
    if (!empty($insuranceCovers) && is_array($insuranceCovers)) {
        $insuranceParts = [];
        foreach ($insuranceCovers as $cover) {
            if (is_array($cover)) {
                $name = trim($cover['name'] ?? '');
                $price = (float)($cover['price'] ?? 0);
                if ($name) {
                    $insuranceParts[] = $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                }
            } elseif (is_string($cover)) {
                $insuranceParts[] = $cover;
            }
        }
        $insuranceText = implode(' &middot; ', $insuranceParts);
    }

    $accessoriesText = '';
    if (!empty($otfData['accessories']) && is_array($otfData['accessories'])) {
        $accParts = [];
        foreach ($otfData['accessories'] as $accCode) {
            $accessory = DB::table('xlr8_vehicle_accessories')
                ->where('part_no', trim($accCode))
                ->first();
            if ($accessory) {
                $accessoryName = mb_convert_case(
                    trim($accessory->item),
                    MB_CASE_TITLE,
                    'UTF-8'
                );

                $accParts[] = $accessoryName
                    . ' (₹' . number_format((float)$accessory->ndp, 2) . ')';
            }
        }
        $accessoriesText = implode(' &middot; ', $accParts);
    }
@endphp

@if($insuranceText || $accessoriesText)
<div class="info-card-container" style="margin-bottom: 5px;">
    @if($insuranceText)
    <div class="itemization-badge-strip" style="border-bottom: 1px solid #e2e8f0;">
        <span class="itemization-strip-title">Insurance:</span> {!! $insuranceText !!}
    </div>
    @endif
    @if($accessoriesText)
    <div class="itemization-badge-strip">
        <span class="itemization-strip-title">Accessories:</span> {!! $accessoriesText !!}
    </div>
    @endif
</div>
@endif

<!-- ================= FINANCIER DETAILS ================= -->
<!-- ================= FINANCIER DETAILS (SPLIT INTO TWO HALVES) ================= -->
<table class="split-pane-layout">
    <tr>
        <td class="split-pane-column">
            <!-- FINANCIER DETAILS - LEFT HALF -->
            <div class="info-card-container" style="margin-bottom: 0;">
                <div class="card-header-banner">Financier Details</div>
                <table class="accounting-ledger-table">
                    <tbody>
                        @php
                            $loanAmount = (float)($otfData['loan_amount'] ?? $finance?->loan_amount ?? 0);
                            $fileCharge = (float)($otfData['file_charge'] ?? $finance?->file_charge ?? 0);
                            $marginMoney = (float)($otfData['margin_money'] ?? $finance?->margin ?? 0);
                            $financierSubvention = (float)($otfData['financier_subvention'] ?? $finance?->subvention_amount ?? 0);
                            $doAmount = (float)($otfData['net_settlement_amount'] ?? 0);
                            $receiptTotal = (float)($receiptTotal ?? 0);
                            $expectedBalance = (float)($otfData['expected_balance'] ?? 0);
                            $doSettlementDiff = (float)($otfData['do_settlement_difference'] ?? 0);
                            $discountJV = (float)($otfData['discount_through_jv'] ?? 0);
                            $finalBalance = (float)($otfData['final_balance'] ?? 0);
                        @endphp

                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px; width:45%;">Financier Name</td>
                            <td style="text-align:right; font-weight:500; width:55%;">{{ $financierName ?? $otfData['financier'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Financier Branch</td>
                            <td style="text-align:right; font-weight:500;">{{ $otfData['financier_branch'] ?? 'Bikaner' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Loan Amount</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($loanAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">File Charge / Processing</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($fileCharge, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Payment Made to Financier by Customer (If Any)</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($marginMoney, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Financier Subvention Amount (If Any)</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($financierSubvention, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">DO Amount</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($doAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Receipt Amount</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($receiptTotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Expected Balance</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($expectedBalance, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">DO Settlement Difference</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($doSettlementDiff, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Discount through JV</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($discountJV, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Final Balance</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format($finalBalance, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>

        <td class="split-pane-spacer"></td>

        <td class="split-pane-column">
            <!-- FINANCIER DETAILS - RIGHT HALF -->
            <div class="info-card-container" style="margin-bottom: 0;">
                <div class="card-header-banner">DO Details & Other Receivables</div>
                <table class="accounting-ledger-table">
                    <tbody>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px; width:45%;">
                                Vehicle To Be Delivered On
                            </td>
                            <td style="text-align:right; font-weight:500; font-family: monospace; width:55%;">
                                {{ $deliveryOptions[$otfData['vehicle_delivery_on'] ?? $finance?->instrument_type ?? ''] ?? 'On DO Clearance' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px; width:45%;">DO No. (Delivery Time)</td>
                            <td style="text-align:right; font-weight:500; font-family: monospace; width:55%;">{{ $otfData['do_number'] ?? $finance?->instrument_ref_no ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">DO No. (TA Statement)</td>
                            <td style="text-align:right; font-weight:500; font-family: monospace;">{{ $otfData['do_number_ta'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">DO Amount (TA Statement)</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['do_amount_ta'] ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">DO Voucher Date</td>
                            <td style="text-align:right; font-weight:500;">{{ $otfData['do_voucher_date'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Brokerage Amount</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['brokerage_amount'] ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Other Discount Receivable</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['other_discount_receivable'] ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Other Discount Receivable - M&amp;M Support Discount</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['mm_support_receivable'] ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">Other Discount Receivable - Liquidation Scheme</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['liquidation_scheme_receivable'] ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">RTO Service Charge - Receivable</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['registration_service_charge_receivable'] ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600; color:#0f172a; font-size:10px;">RTO Service Charge - Received</td>
                            <td style="text-align:right; font-weight:500;">₹ {{ number_format((float)($otfData['registration_service_charge_received'] ?? 0), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- ================= SYNTHESIS SUMMARY ================= -->
{{-- <table class="synthesis-summary-table">
    <tr class="net-receivable-marker">
        <td>1. Consolidated Net Operational Obligation (Gross Valuations Less Dynamic Discounts)</td>
        <td class="text-align-right">₹ {{ number_format((float)($otfData['net_receivable_summary'] ?? 0), 2) }}</td>
    </tr>
    <tr class="credit-offset-marker">
        <td>2. Cleared Direct Account Receipts Received In-Bank (Receipt Ref: #{{ $receiptLogs->first()?->reciept ?? 'N/A' }})</td>
        <td class="text-align-right" style="color: #15803d;">(-) ₹ {{ number_format($receiptTotal, 2) }}</td>
    </tr>
    <tr class="credit-offset-marker">
        <td>3. Institutional Underwriter Allocation Settlement (Direct Bank DO Transfer)</td>
        <td class="text-align-right" style="color: #15803d;">(-) ₹ {{ number_format($doAmount, 2) }}</td>
    </tr>
    <tr class="final-balance-marker">
        <td>4. Unsettled Liquid Balance Variance Due Over Account Handoff Ledger</td>
        <td class="text-align-right">₹ {{ number_format($finalBalance, 2) }}</td>
    </tr>
</table> --}}

<!-- ================= REGULATORY CLAUSES ================= -->
{{-- <div class="legal-clauses-box">
    <h4>NOTE:</h4>
    <ol>
        <li>Vehicle assets are released for client handoff execution processes exclusively upon complete, non-disputed realization of liquid balance funds.</li>
        <li>Delayed balances exceeding three standard transactional working days trigger statutory interest penalties at 24% Per Annum.</li>
        <li>Initial token registration deposits do not accrue interest positions or yield values in favor of the client party.</li>
        <li>Final structural configurations, variant trims, and corporate campaign benefits lock explicitly on the day of physical handout execution.</li>
        <li>Verified colored duplications of relevant baseline documents are required to clear asset deployment audit tracking parameters.</li>
    </ol>
</div> --}}

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
            @forelse($receiptLogs ?? [] as $receipt)
            <tr>
                <td style="font-weight:600;">{{ $receipt->reciept }}</td>
                <td>{{ \Carbon\Carbon::parse($receipt->date)->format('d M Y') }}</td>
                <td>{{ $receipt->mode ?? '' }}</td>
                <td style="font-weight:600; color:#28a745;">₹ {{ number_format($receipt->amount, 2) }}</td>
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
                <td style="font-weight:700; color:#28a745;">₹ {{ number_format($receiptLogs->sum('amount') ?? 0, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

<!-- ================= NOTE, CHASSIS & SIGNATURE ================= -->
<table class="bill-table note-box"
       style="width:100%; border-collapse:collapse; margin-top:4px; border:1px solid #000;">

    <tr>

        {{-- ================= NOTE: 50% ================= --}}
        <td style="
            width:55%;
            vertical-align:top;
            border:1px solid #000;
            padding:4px 6px;
        ">
            <div style="
                font-weight:bold;
                font-size:8px;
                margin-bottom:3px;
            ">
                NOTE:
            </div>

            <p style="
                font-size:7px;
                font-weight:bold;
                line-height:1.3;
                text-align:justify;
                margin:0;
            ">
                <b>1.</b> Vehicle shall be delivered only against payment.<br>

                <b>2.</b> Interest shall be charged @ 24% P.A. in case of payments delayed over three days.<br>

                <b>3.</b> No Interest shall be payable on Booking Amount.<br>

                <b>4.</b> Price &amp; Scheme of the vehicle is applicable as on the date of delivery. Price &amp; Scheme are subjected to change without any prior notice.<br>

                <b>5.</b> Self attested coloured copy of original documents is required for any claim. Claims will be rejected in absence of original documents.
            </p>
        </td>


        {{-- ================= CHASSIS IMAGE: 30% ================= --}}
<td style="
    width:30%;
    vertical-align:middle;
    text-align:center;
    border:1px solid #000;
    padding:3px;
">

    <table style="
        width:100%;
        height:90px;
        border-collapse:collapse;
    ">
        {{-- IMAGE --}}
        <tr>
            <td style="
                vertical-align:middle;
                text-align:center;
                padding:0;
                border:none;
            ">
                @if(!empty($chassisImage))
                    <img src="{{ $chassisImage }}"
                         class="chassis-img"
                         style="
                            display:block;
                            margin:0 auto;
                            max-width:170px;
                            max-height:65px;
                            width:auto;
                            height:auto;
                            object-fit:contain;
                         ">
                @endif
            </td>
        </tr>

        {{-- LABEL BELOW IMAGE --}}
        <tr>
            <td style="
                vertical-align:middle;
                text-align:center;
                padding:2px 0 0 0;
                border:none;
                font-size:8px;
                font-weight:bold;
                color:#000;
            ">
                Chassis Verification
            </td>
        </tr>
    </table>

</td>


        {{-- ================= SIGNATURE: 20% ================= --}}
        <td style="
            width:20%;
            vertical-align:bottom;
            text-align:center;
            border:1px solid #000;
            padding:0 3px 5px 3px;
            height:90px;
        ">
            <div style="
                border-top:1px solid #000;
                width:85%;
                margin:0 auto;
                padding-top:3px;
            ">
                <span style="
                    font-size:7px;
                    font-weight:bold;
                ">
                    Customer Signature
                </span>
            </div>
        </td>

    </tr>

</table>

</body>
</html>