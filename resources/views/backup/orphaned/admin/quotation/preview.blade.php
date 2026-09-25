{{-- ORIGINAL PATH: resources/views/admin/quotation/preview.blade.php --}}
{{-- admin/quotation/preview.blade.php --}}
@extends(backpack_view('blank'))

@php
use App\Services\OrgService;
@endphp

@section('title', 'Quotation Preview')

@push('after_styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .header-logo-left img {
        height: 75px;
    }

    .header-logo-right img {
        max-height: 85px;
        max-width: 170px;
    }

    .header-title h3 {
        margin-bottom: 4px;
    }

    .header-title h4 {
        margin-top: 8px;
        font-weight: bold;
    }

    @media print {
        select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: transparent !important;
            background-image: none !important;
            border: none !important;
            outline: none !important;
            padding-right: 0 !important;
        }

        .no-print {
            display: none !important;
        }

        .print-only-inline {
            display: inline-block !important;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .quotation-sheet {
            width: 100%;
            margin: 0;
            padding: 2mm;
            box-shadow: none;
            border: 1px solid #000;
            display: flex;
            flex-direction: column;
        }

        /* Hide column 2 (OPTION in the price table, TYPE in the discount table) */
        .quotation-grid th:nth-child(2),
        .quotation-grid td:nth-child(2) {
            display: none !important;
        }

        /* Reassign widths for the 2 remaining columns per table */
        .price-grid th:nth-child(1) {
            width: 53% !important;
        }

        .price-grid th:nth-child(3) {
            width: 47% !important;
        }

        .discount-grid th:nth-child(1) {
            width: 49% !important;
        }

        .discount-grid th:nth-child(3) {
            width: 51% !important;
        }

        .quotation-grid {
            width: 100% !important;
            table-layout: fixed !important;
        }

        .quotation-grid-split {
            gap: 6px !important;
        }

        .quotation-grid tr.print-hide {
            display: none !important;
        }

        .financier-discount-grid {
            display: none !important;
        }

        .accessories-note-row {
            display: block !important;
        }

        .quotation-sheet,
        .quotation-grid,
        .quotation-summary,
        .bill-table {
            width: 100% !important;
        }

        /* Fix summary alignment */
        .quotation-summary {
            display: flex !important;
            align-items: stretch !important;
            width: 100% !important;
            border: 1px solid #000 !important;
        }

        .quotation-summary .total-row-cell,
        .quotation-summary .onroad-row-cell {
            display: flex !important;
            align-items: center !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            border-right: none !important;
            padding: 5px 8px !important;
            min-height: 30px !important;
        }

        .quotation-summary .total-row-cell:last-child,
        .quotation-summary .onroad-row-cell:last-child {
            border-right: 1px solid #000 !important;
        }

        .quotation-summary input {
            width: 100% !important;
            text-align: right !important;
            border: none !important;
            background: transparent !important;
            padding: 2px 5px !important;
        }

        /* Match the 4-column proportions */
        .quotation-summary .total-receivable-label {
            flex: 0 0 32% !important;
        }

        .quotation-summary .total-receivable-amount {
            flex: 0 0 18% !important;
            justify-content: flex-end !important;
        }

        .quotation-summary .total-discount-label {
            flex: 0 0 32% !important;
        }

        .quotation-summary .total-discount-amount {
            flex: 0 0 18% !important;
            justify-content: flex-end !important;
        }

        .quotation-summary .onroad-label {
            flex: 0 0 17% !important;
        }

        .quotation-summary .onroad-amount {
            flex: 0 0 13% !important;
            justify-content: center !important;
            align-items: center !important;
        }

        .quotation-summary .onroad-words {
            flex: 1 1 70% !important;
            justify-content: flex-end !important;
        }

        .discount-bifurcation-box {
            display: none !important;
        }

        #accessories+.select2-container {
            display: none !important;
        }

        #accessories_print {
            display: block !important;
            white-space: normal;
            word-break: break-word;
            font-size: 11px;
            line-height: 15px;
        }
    }

    .print-only-inline {
        display: none;
    }

    .quotation-sheet {
        background: #fff;
        border: 1px solid #000;
        padding: 15px;
    }

    .bill-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    .bill-table .title {
        background: #f2f2f2;
        font-weight: bold;
    }

    .bill-table input:focus {
        outline: none;
    }

    .bill-table select {
        border: none;
        width: 100%;
        background: transparent;
    }

    .bill-table select:focus {
        outline: none;
    }

    .bill-table td {
        border: 1px solid #000;
        padding: 2px 5px;
        height: 26px;
        font-size: 10px;
        vertical-align: middle;
    }

    .bill-table .title {
        background: #f2f2f2;
        font-weight: 600;
        white-space: nowrap;
    }

    .bill-table input {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        padding: 2px;
    }

    /* Hide Backpack UI */
    .page-header {
        display: none !important;
    }

    .navbar {
        display: none !important;
    }

    .main-header {
        display: none !important;
    }

    .sidebar {
        display: none !important;
    }

    .app-footer {
        display: none !important;
    }

    .breadcrumb {
        display: none !important;
    }

    .content-header {
        display: none !important;
    }

    .wrapper {
        padding-top: 0 !important;
    }

    .main-body {
        margin-top: 0 !important;
    }

    .row.align-items-stretch {
        align-items: stretch;
    }

    .note-table {
        flex: 1;
    }

    .note-table td {
        vertical-align: top;
    }

    .note-box {
        margin-top: 12px;
    }

    /* ================= Quotation Grid ================= */
    .quotation-box {
        margin-bottom: 15px;
    }

    .quotation-grid-split {
        display: flex;
        gap: 3px;
        align-items: flex-start;
    }

    .quotation-grid-split>.quotation-grid-col {
        flex: 1 1 50%;
        min-width: 0;
    }

    .quotation-grid {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .quotation-grid th,
    .quotation-grid td {
        border: 1px solid #000;
        padding: 3px 5px;
        font-size: 10px;
        height: 26px;
        vertical-align: middle;
        overflow: hidden;
    }

    .quotation-grid thead th {
        background: #d9d9d9;
        font-weight: bold;
        text-align: center;
    }

    .price-grid th:nth-child(1) {
        width: 32%;
    }

    .price-grid th:nth-child(2) {
        width: 40%;
    }

    .price-grid th:nth-child(3) {
        width: 28%;
    }

    .discount-grid th:nth-child(1) {
        width: 33%;
    }

    .discount-grid th:nth-child(2) {
        width: 33%;
    }

    .discount-grid th:nth-child(3) {
        width: 34%;
    }

    .quotation-grid td.cell-label {
        background: #f2f2f2;
        font-weight: 600;
    }

    .quotation-grid td.cell-label .group-select {
        background: #f2f2f2;
        font-weight: 600;
    }

    .quotation-grid input,
    .quotation-grid select {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 10px;
        padding: 2px;
    }

    .quotation-grid input:focus,
    .quotation-grid select:focus {
        outline: none;
    }

    /* ================= Quotation Summary ================= */
    .quotation-summary {
        display: flex;
        font-weight: bold;
        border: 1px solid #000;
        width: 100%;
        margin-top: 10px;
    }

    .quotation-summary .total-row-cell {
        background: #f2f2f2;
        display: flex;
        align-items: center;
        padding: 5px 8px;
        min-height: 30px;
    }

    .quotation-summary .total-receivable-label {
        flex: 0 0 32%;
    }

    .quotation-summary .total-receivable-amount {
        flex: 0 0 18%;
        justify-content: flex-end;
    }

    .quotation-summary .total-discount-label {
        flex: 0 0 32%;
    }

    .quotation-summary .total-discount-amount {
        flex: 1 1 18%;
        justify-content: flex-end;
    }

    .quotation-summary .onroad-row-cell {
        background: #abb8ca;
        color: #000000;
        padding: 5px 8px;
        display: flex;
        align-items: center;
        min-height: 30px;
    }

    .quotation-summary .onroad-label {
        flex: 0 0 25%;
    }

    .quotation-summary .onroad-amount {
        flex: 1 1 18%;
        justify-content: center;
    }

    .quotation-summary input {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 10px;
        font-weight: bold;
        text-align: right;
        padding: 2px 5px;
    }

    /* ================= Discount Bifurcation Box ================= */
    .discount-bifurcation-box {
        margin-top: 10px;
    }

    .bifurcation-grid input {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 10px;
        padding: 2px;
    }

    .bifurcation-grid input:focus {
        outline: none;
    }

    /* ================= Insurance & Accessories Note Rows ================= */
    .insurance-note-row,
    .accessories-note-row {
        display: block;
        padding: 4px 5px;
        font-size: 10px;
        font-weight: bold;
        margin-bottom: 6px;
    }

    #insurance_print,
    #accessories_print {
        font-weight: normal;
        white-space: normal;
        word-break: break-word;
        display: inline;
    }

    /* ================= PREVIEW MODE ================= */
    .quotation-form.preview-mode input,
    .quotation-form.preview-mode select,
    .quotation-form.preview-mode textarea {
        pointer-events: none !important;
    }

    .quotation-form.preview-mode .quotation-grid th:nth-child(2),
    .quotation-form.preview-mode .quotation-grid td:nth-child(2) {
        display: none !important;
    }

    .quotation-form.preview-mode .price-grid th:nth-child(1) {
        width: 53% !important;
    }

    .quotation-form.preview-mode .price-grid th:nth-child(3) {
        width: 47% !important;
    }

    .quotation-form.preview-mode .discount-grid th:nth-child(1) {
        width: 49% !important;
    }

    .quotation-form.preview-mode .discount-grid th:nth-child(3) {
        width: 51% !important;
    }

    .quotation-form.preview-mode .quotation-grid-split {
        gap: 0.5px !important;
    }

    .quotation-form.preview-mode .quotation-grid tr.print-hide {
        display: none !important;
    }

    .quotation-form.preview-mode .discount-bifurcation-box {
        display: none !important;
    }

    .quotation-form.preview-mode .accessories-note-row {
        display: block !important;
    }

    .quotation-form.preview-mode .quotation-summary {
        display: flex !important;
        align-items: stretch !important;
        width: 100% !important;
        border: 1px solid #000 !important;
        border-top: none !important;
    }

    .quotation-form.preview-mode .quotation-summary .total-row-cell,
    .quotation-form.preview-mode .quotation-summary .onroad-row-cell {
        display: flex !important;
        align-items: center !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        border-right: none !important;
        padding: 5px 8px !important;
        min-height: 30px !important;
        background: #f2f2f2 !important;
    }

    .quotation-form.preview-mode .quotation-summary .total-row-cell:last-child,
    .quotation-form.preview-mode .quotation-summary .onroad-row-cell:last-child {
        border-right: 1px solid #000 !important;
    }

    .quotation-form.preview-mode .quotation-summary .onroad-row-cell {
        background: #abb8ca !important;
        color: #000000 !important;
    }

    .quotation-form.preview-mode .quotation-summary input {
        width: 100% !important;
        text-align: right !important;
        border: none !important;
        background: transparent !important;
        padding: 2px 5px !important;
        font-weight: bold !important;
        font-size: 10px !important;
    }

    .quotation-form.preview-mode .quotation-summary .total-receivable-label {
        flex: 0 0 32% !important;
    }

    .quotation-form.preview-mode .quotation-summary .total-receivable-amount {
        flex: 0 0 18% !important;
        justify-content: flex-end !important;
    }

    .quotation-form.preview-mode .quotation-summary .total-discount-label {
        flex: 0 0 32% !important;
    }

    .quotation-form.preview-mode .quotation-summary .total-discount-amount {
        flex: 0 0 18% !important;
        justify-content: flex-end !important;
    }

    .quotation-form.preview-mode .quotation-summary .onroad-label {
        flex: 0 0 25% !important;
    }

    .quotation-form.preview-mode .quotation-summary .onroad-amount {
        flex: 0 0 18% !important;
        justify-content: flex-end !important;
    }

    .quotation-grid input,
    .quotation-summary input {
        text-align: right !important;
    }

    .select2-container--default .select2-selection--multiple {
        min-height: 32px !important;
        height: 32px !important;
        overflow: hidden !important;
    }

    .select2-container--default .select2-selection__rendered {
        display: flex !important;
        align-items: center;
        height: 30px;
        overflow: hidden;
    }

    .select2-selection__choice {
        display: none !important;
    }

    .select2-search--inline {
        width: 100% !important;
    }

    .select2-search__field {
        width: 100% !important;
    }
</style>
@endpush

@section('content')

<div class="quotation-form preview-mode">
    <div class="container-fluid">

        <div class="card shadow-sm mb-3">
            <div class="card-body py-2 px-3">
                @php
                $segment = strtoupper(optional($selectedEnquiry)->segment_code);

                if ($segment == 'LMM') {
                $mahindraLogo = asset('images/mahindra-lmm-logo.png');
                } elseif ($segment == 'BEV') {
                $mahindraLogo = asset('images/mahindra-ev-logo.png');
                } else {
                $mahindraLogo = asset('images/mahindra-pv-cv-logo.png');
                }
                @endphp

                <div class="row align-items-center">

                    <div class="col-2 text-center">
                        <img src="{{ asset('images/bikaner_logo.png') }}" style="height:75px;">
                    </div>

                    <div class="col-8 text-center">
                        <h3 class="fw-bold mb-1">BIKANER MOTORS PRIVATE LIMITED</h3>
                        <div style="font-size:13px;">
                            Regd. Office : Sunderi Chhabil Mansion, NH-11,
                            Jaipur Road, P.O. Udasar, Bikaner-334022
                        </div>
                        <div style="font-size:13px;">
                            Branch Office : 6th KM Stone,
                            Ratangarh Road, Churu (Raj.)
                        </div>
                        <h4 class="mt-2 mb-0 fw-bold text-uppercase">Vehicle Quotation</h4>
                    </div>

                    <div class="col-2 text-center">
                        <img src="{{ $mahindraLogo }}" style="max-width:110px; max-height:60px;">
                    </div>

                </div>

            </div>
        </div>

        <div class="quotation-sheet">
            <input type="hidden" id="insurance_print_data" value='{{ $insurancePrintData ?? ' []' }}'>
            <input type="hidden" id="accessories_print_data" value='{{ $accessoriesPrintData ?? ' []' }}'>

            <div class="form-section">

                {{-- ================= Customer Details ================= --}}
                <table class="bill-table mb-3">
                    <tr>
                        <td class="title" width="18%">Enquiry ID</td>
                        <td width="32%">
                            <input type="text" id="enquiry_id" value="{{ optional($selectedEnquiry)->id }}" readonly>
                            <input type="hidden" name="enquiry_no" id="enquiry_no_hidden"
                                value="{{ optional($selectedEnquiry)->id }}">
                        </td>
                        <td class="title" width="18%">Customer Name</td>
                        <td width="32%">
                            <input type="text" id="customer_name"
                                value="{{ $quotationData['customer_name'] ?? optional($selectedEnquiry)->full_name }}"
                                readonly>
                        </td>
                    </tr>

                    <tr>
                        <td class="title">Mobile Number</td>
                        <td>
                            <input type="text"
                                value="{{ $quotationData['customer_mobile'] ?? optional($selectedEnquiry)->mobile }}"
                                readonly>
                        </td>
                        <td class="title">Care Of Name</td>
                        <td>
                            <div class="input-group">
                                <select name="careof" id="careof" class="form-select2" style="max-width: 80px;"
                                    disabled>
                                    <option value="">Select</option>
                                    <option value="1" {{ ($quotationData['careof'] ?? '' )=='1' ? 'selected' : '' }}>Son
                                        of</option>
                                    <option value="2" {{ ($quotationData['careof'] ?? '' )=='2' ? 'selected' : '' }}>
                                        Daughter of</option>
                                    <option value="3" {{ ($quotationData['careof'] ?? '' )=='3' ? 'selected' : '' }}>
                                        Married to</option>
                                    <option value="4" {{ ($quotationData['careof'] ?? '' )=='4' ? 'selected' : '' }}>
                                        Guardian Name</option>
                                </select>
                                <input type="text" name="careofname" id="careofname" placeholder="Enter Name"
                                    value="{{ $quotationData['careofname'] ?? '' }}" readonly>
                            </div>
                        </td>
                    </tr>
                </table>

                {{-- ================= Vehicle Details ================= --}}
                <table class="bill-table mb-3">
                    <tr>
                        <td class="title" width="18%">Segment</td>
                        <td width="32%">
                            <input type="text" id="segment_display"
                                value="{{ optional($selectedEnquiry->segment)->name ?? $selectedEnquiry->segment_code ?? $quotationData['segment_code'] ?? '' }}"
                                readonly>
                            <input type="hidden" name="segment_code" id="segment_code"
                                value="{{ $quotationData['segment_code'] ?? optional($selectedEnquiry)->segment_code }}">
                        </td>
                        <td class="title" width="18%">Model</td>
                        <td width="32%">
                            <input type="text" id="model_display"
                                value="{{ optional($selectedEnquiry->model)->name ?? $selectedEnquiry->model_code ?? $quotationData['model_code'] ?? '' }}"
                                readonly>
                            <input type="hidden" name="model_code" id="model_code"
                                value="{{ $quotationData['model_code'] ?? optional($selectedEnquiry)->model_code }}">
                        </td>
                    </tr>

                    <tr>
                        <td class="title">Variant</td>
                        <td>
                            <input type="text" id="variant_display"
                                value="{{ optional($selectedEnquiry->variant)->display_name ?? optional($selectedEnquiry->variant)->custom_name ?? $selectedEnquiry->variant_code ?? $quotationData['variant_code'] ?? '' }}"
                                readonly>
                            <input type="hidden" name="variant_code" id="variant_code"
                                value="{{ $quotationData['variant_code'] ?? optional($selectedEnquiry)->variant_code }}">
                        </td>
                        <td class="title">Color</td>
                        <td>
                            <input type="text" id="color_display"
                                value="{{ optional($selectedEnquiry->color)->name ?? $selectedEnquiry->color_code ?? $quotationData['color_code'] ?? '' }}"
                                readonly>
                            <input type="hidden" name="color_code" id="color_code"
                                value="{{ $quotationData['color_code'] ?? optional($selectedEnquiry)->color_code }}">
                        </td>
                    </tr>

                    <tr>
                        <td class="title">Permit</td>
                        <td>
                            <select id="permit" disabled>
                                <option value="">Select Permit</option>
                                @foreach($permitOptions ?? [] as $permit)
                                <option value="{{ $permit }}" {{ ($quotationData['permit'] ?? '' )==$permit ? 'selected'
                                    : '' }}>
                                    {{ $permit }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="title">OEM Code</td>
                        <td>
                            <input type="text" id="oem_code"
                                value="{{ $quotationData['oem_code'] ?? optional($selectedEnquiry)->oem_code }}"
                                readonly>
                            <input type="hidden" name="oem_code" id="oem_code_hidden"
                                value="{{ $quotationData['oem_code'] ?? optional($selectedEnquiry)->oem_code }}">
                        </td>
                    </tr>
                </table>

                <div class="quotation-box">

                    <div class="quotation-grid-split">

                        {{-- ================= PRICE DETAILS BOX ================= --}}
                        <div class="quotation-grid-col">
                            <table class="quotation-grid price-grid">
                                <thead>
                                    <tr>
                                        <th>PRICE DETAILS</th>
                                        <th>OPTION</th>
                                        <th>AMOUNT</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <tr class="grid-row">
                                        <td class="cell-label">Ex-Showroom Price</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input name="ex_showroom_price" id="ex_showroom_price" class="numeric-only"
                                                value="{{ $quotationData['ex_showroom_price'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Insurance</td>
                                        <td class="cell-option">
                                            <select id="insurance_company" class="form-control mb-1" disabled>
                                                <option value="">Select Company</option>
                                                @foreach($quotationData['insurance_companies'] ?? [] as $company)
                                                <option value="{{ $company }}" {{ ($quotationData['insurance_company']
                                                    ?? '' )==$company ? 'selected' : '' }}>
                                                    {{ $company }}
                                                </option>
                                                @endforeach
                                            </select>
                                            <select id="insurance_covers" name="insurance_covers[]" class="form-control"
                                                multiple disabled>
                                                @foreach($quotationData['insurance_covers'] ?? [] as $cover)
                                                @php
                                                $coverName = is_array($cover) ? ($cover['name'] ?? $cover) : $cover;
                                                $coverPrice = is_array($cover) ? ($cover['price'] ?? 0) : 0;

                                                // If it's a string, try to extract price
                                                if (is_string($cover) && preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover,
                                                $matches)) {
                                                $coverPrice = floatval(str_replace(',', '', $matches[1]));
                                                $coverName = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                                                }
                                                @endphp
                                                <option value="{{ $coverName }}" data-price="{{ $coverPrice }}" {{
                                                    isset($cover['selected']) && $cover['selected'] ? 'selected' : ''
                                                    }}>
                                                    {{ $coverName }} (₹{{ number_format($coverPrice, 2) }})
                                                </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" id="insurance_amount" name="insurance_amount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['insurance_amount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>



                                    <tr class="grid-row">
                                        <td class="cell-label">
                                            Registration
                                            <span id="registration_details_print"
                                                class="print-only-inline fw-bold ms-1"></span>
                                        </td>
                                        <td class="cell-option" style="padding: 2px 4px !important;">
                                            <div
                                                style="display: flex; gap: 4px; align-items: center; justify-content: space-between; width: 100%;">

                                                <div style="flex: 1 1 38%;">
                                                    <select name="registration_no_type" id="registration_no_type"
                                                        class="form-select form-select-sm"
                                                        style="font-size: 9px; padding: 1px 3px; height: 22px; border: 1px solid #ccc; border-radius: 3px; width: 100%; background: #fff;"
                                                        disabled>
                                                        <option value="">Select Type</option>
                                                        @foreach($reg_no_type_map ?? ['1'=>'Regular', '2'=>'BH Series',
                                                        '3'=>'Special Number'] as $key => $value)
                                                        <option value="{{ $key }}" {{
                                                            ($quotationData['registration_no_type'] ?? '' )==$key
                                                            ? 'selected' : '' }}>
                                                            {{ $value }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div style="flex: 1 1 38%;">
                                                    <select name="registration_category" id="registration_category"
                                                        class="form-select form-select-sm"
                                                        style="font-size: 9px; padding: 1px 3px; height: 22px; border: 1px solid #ccc; border-radius: 3px; width: 100%; background: #fff;"
                                                        disabled>
                                                        <option value="">Select Category</option>
                                                        @foreach($registration_type_map as $key => $value)
                                                        <option value="{{ $key }}" {{
                                                            ($quotationData['registration_category'] ?? '' )==$key
                                                            ? 'selected' : '' }}>
                                                            {{ $value }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="no-print"
                                                    style="flex: 0 0 auto; display: flex; align-items: center; border: 1px solid #ccc; border-radius: 3px; padding: 1px; background: #fff; height: 22px;">
                                                    <span
                                                        style="font-size: 8px; font-weight: bold; margin-right: 3px; margin-left: 2px; color: #555; white-space: nowrap;">In-House:</span>
                                                    <label
                                                        style="font-size: 8px; font-weight: bold; margin: 0 2px; cursor: pointer; display: flex; align-items: center; gap: 1px;">
                                                        <input type="radio" name="in_house_rto" value="1" {{
                                                            ($quotationData['in_house_rto'] ?? '0' )=='1' ? 'checked'
                                                            : '' }} style="width: auto !important; margin: 0;" disabled>
                                                        Yes
                                                    </label>
                                                    <label
                                                        style="font-size: 8px; font-weight: bold; margin: 0 2px; cursor: pointer; display: flex; align-items: center; gap: 1px;">
                                                        <input type="radio" name="in_house_rto" value="0" {{
                                                            ($quotationData['in_house_rto'] ?? '0' )=='0' ? 'checked'
                                                            : '' }} style="width: auto !important; margin: 0;" disabled>
                                                        No
                                                    </label>
                                                </div>

                                            </div>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" id="registration_amount" name="registration_amount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['registration_amount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Accessories</td>
                                        <td class="cell-option">
                                            <select name="accessories[]" id="accessories" multiple disabled>
                                                @foreach($accessoryList as $accessory)
                                                <option value="{{ $accessory->part_no }}"
                                                    data-price="{{ $accessory->ndp }}" {{ in_array($accessory->part_no,
                                                    (array)($quotationData['accessories'] ?? [])) ? 'selected' : '' }}>
                                                    {{ $accessory->item }} (₹{{ number_format($accessory->ndp,2) }})
                                                </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input id="accessories_amount" name="accessories_amount"
                                                class="numeric-only"
                                                value="{{ $quotationData['accessories_amount'] ?? '0.00' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Maxicare</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="maxicare" name="maxicare" class="numeric-only"
                                                value="{{ $quotationData['maxicare'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">VLTD Device (GPS)</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="vltd_device" name="vltd_device" class="numeric-only"
                                                value="{{ $quotationData['vltd_device'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Coating</td>
                                        <td class="cell-option">
                                            <select id="coating" name="coating" disabled>
                                                <option value="No Coating" {{ ($quotationData['coating'] ?? ''
                                                    )=='No Coating' ? 'selected' : '' }}>No Coating</option>
                                                <option value="Ceramic" {{ ($quotationData['coating'] ?? '' )=='Ceramic'
                                                    ? 'selected' : '' }}>Ceramic</option>
                                                <option value="Graphene" {{ ($quotationData['coating'] ?? ''
                                                    )=='Graphene' ? 'selected' : '' }}>Graphene</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input id="coating_price" name="coating_price" class="numeric-only"
                                                value="{{ $quotationData['coating_price'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">PPF</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="ppf" name="ppf" class="numeric-only"
                                                value="{{ $quotationData['ppf'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">RTO Yellow Tape</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="rto_yellow_tape" name="rto_yellow_tape" class="numeric-only"
                                                value="{{ $quotationData['rto_yellow_tape'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Kazam Charging Kit</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="kazam_charging_kit" name="kazam_charging_kit"
                                                class="numeric-only"
                                                value="{{ $quotationData['kazam_charging_kit'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Incidental Charges</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="incidental_charges" name="incidental_charges"
                                                class="numeric-only"
                                                value="{{ $quotationData['incidental_charges'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Shield</td>
                                        <td class="cell-option">
                                            <select id="shield" name="shield" disabled>
                                                <option value="4th Year" {{ ($quotationData['shield'] ?? ''
                                                    )=='4th Year' ? 'selected' : '' }}>4th Year</option>
                                                <option value="4th + 5th Year" {{ ($quotationData['shield'] ?? ''
                                                    )=='4th + 5th Year' ? 'selected' : '' }}>4th + 5th Year</option>
                                                <option value="No Shield" {{ ($quotationData['shield'] ?? ''
                                                    )=='No Shield' ? 'selected' : '' }}>No Shield</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input id="shield_price" name="shield_price" class="numeric-only"
                                                value="{{ $quotationData['shield_price'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">RSA</td>
                                        <td class="cell-option">
                                            <select id="rsa" name="rsa" disabled>
                                                <option value="1 Year" {{ ($quotationData['rsa'] ?? '' )=='1 Year'
                                                    ? 'selected' : '' }}>1 Year</option>
                                                <option value="2 Year" {{ ($quotationData['rsa'] ?? '' )=='2 Year'
                                                    ? 'selected' : '' }}>2 Year</option>
                                                <option value="3 Year" {{ ($quotationData['rsa'] ?? '' )=='3 Year'
                                                    ? 'selected' : '' }}>3 Year</option>
                                                <option value="4 Year" {{ ($quotationData['rsa'] ?? '' )=='4 Year'
                                                    ? 'selected' : '' }}>4 Year</option>
                                                <option value="5 Year" {{ ($quotationData['rsa'] ?? '' )=='5 Year'
                                                    ? 'selected' : '' }}>5 Year</option>
                                                <option value="No RSA" {{ ($quotationData['rsa'] ?? '' )=='No RSA'
                                                    ? 'selected' : '' }}>No RSA</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input id="rsa_amount" name="rsa_amount" class="numeric-only"
                                                value="{{ $quotationData['rsa_amount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Fastag</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="fastag" name="fastag" class="numeric-only"
                                                value="{{ $quotationData['fastag'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">COD Charges</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="cod_charges" name="cod_charges" class="numeric-only"
                                                value="{{ $quotationData['cod_charges'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Charger Swapping</td>
                                        <td class="cell-option">
                                            <select id="charger_swapping" name="charger_swapping" disabled>
                                                <option value="N/A" {{ ($quotationData['charger_swapping'] ?? ''
                                                    )=='N/A' ? 'selected' : '' }}>N/A</option>
                                                <option value="NCH to 7.2 kW" {{ ($quotationData['charger_swapping']
                                                    ?? '' )=='NCH to 7.2 kW' ? 'selected' : '' }}>NCH to 7.2 kW</option>
                                                <option value="NCH to 11.2 kW" {{ ($quotationData['charger_swapping']
                                                    ?? '' )=='NCH to 11.2 kW' ? 'selected' : '' }}>NCH to 11.2 kW
                                                </option>
                                                <option value="7.2 kW to 11.2 kW" {{ ($quotationData['charger_swapping']
                                                    ?? '' )=='7.2 kW to 11.2 kW' ? 'selected' : '' }}>7.2 kW to 11.2 kW
                                                </option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input id="charger_swapping_amount" name="charger_swapping_amount"
                                                class="numeric-only"
                                                value="{{ $quotationData['charger_swapping_amount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">TCS @1%</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="tcs" name="tcs" class="numeric-only"
                                                value="{{ $quotationData['tcs'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row total-row" style="background: #f2f2f2; font-weight: bold;">
                                        <td class="cell-label"
                                            style="text-align: center; font-weight: bold; font-size: 11px;">TOTAL
                                            RECEIVABLE</td>
                                        <td class="cell-option"></td>
                                        <td class="cell-amount">
                                            <input id="total_receivable" name="total_receivable" readonly
                                                style="font-weight: bold; font-size: 11px; text-align: right;"
                                                value="{{ $quotationData['total_receivable'] ?? '' }}">
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>

                        {{-- ================= DISCOUNT DETAILS BOX ================= --}}
                        <div class="quotation-grid-col">
                            <table class="quotation-grid discount-grid">
                                <thead>
                                    <tr>
                                        <th>DISCOUNT DETAILS</th>
                                        <th>TYPE</th>
                                        <th>AMOUNT</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <tr class="grid-row">
                                        <td class="cell-label">
                                            <select id="group_a_select" class="group-select" disabled>
                                                <option value="cash_scheme_oem" {{ ($quotationData['group_a_select']
                                                    ?? '' )=='cash_scheme_oem' ? 'selected' : '' }}>Cash Scheme OEM
                                                </option>
                                                <option value="csd_discount" {{ ($quotationData['group_a_select'] ?? ''
                                                    )=='csd_discount' ? 'selected' : '' }}>CSD Discount</option>
                                                <option value="fame_subsidy" id="fame_subsidy_option" {{
                                                    ($quotationData['group_a_select'] ?? '' )=='fame_subsidy'
                                                    ? 'selected' : '' }}>Fame Subsidy (LMM)</option>
                                            </select>
                                        </td>
                                        <td class="cell-type">
                                            <select id="group_a_type" disabled>
                                                <option value="INV" {{ ($quotationData['group_a_type'] ?? '' )=='INV'
                                                    ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['group_a_type'] ?? '' )=='CN'
                                                    ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" id="group_a_amount" class="numeric-only"
                                                placeholder="0.00" value="{{ $quotationData['group_a_amount'] ?? '' }}"
                                                readonly>
                                            <input type="hidden" id="cash_scheme_oem" name="cash_scheme_oem"
                                                value="{{ $quotationData['cash_scheme_oem'] ?? '' }}">
                                            <input type="hidden" id="cash_scheme_oem_type" name="cash_scheme_oem_type"
                                                value="{{ $quotationData['cash_scheme_oem_type'] ?? '' }}">
                                            <input type="hidden" id="csd_discount" name="csd_discount"
                                                value="{{ $quotationData['csd_discount'] ?? '' }}">
                                            <input type="hidden" id="csd_discount_type" name="csd_discount_type"
                                                value="{{ $quotationData['csd_discount_type'] ?? '' }}">
                                            <input type="hidden" id="fame_subsidy" name="fame_subsidy"
                                                value="{{ $quotationData['fame_subsidy'] ?? '' }}">
                                            <input type="hidden" id="fame_subsidy_type" name="fame_subsidy_type"
                                                value="{{ $quotationData['fame_subsidy_type'] ?? '' }}">
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Cash Scheme Dealer</td>
                                        <td class="cell-type">
                                            <select id="dealer_discount_type" name="dealer_discount_type" disabled>
                                                <option value="INV" {{ ($quotationData['dealer_discount_type'] ?? ''
                                                    )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['dealer_discount_type'] ?? ''
                                                    )=='CN' ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="dealer_discount" id="dealer_discount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['dealer_discount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Accessories Scheme</td>
                                        <td class="cell-type">
                                            <select id="accessories_discount_type" name="accessories_discount_type"
                                                disabled>
                                                <option value="INV" {{ ($quotationData['accessories_discount_type']
                                                    ?? '' )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['accessories_discount_type'] ?? ''
                                                    )=='CN' ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="accessories_discount" id="accessories_discount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['accessories_discount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Shield Scheme</td>
                                        <td class="cell-type">
                                            <select id="shield_scheme_type" name="shield_scheme_type" disabled>
                                                <option value="INV" {{ ($quotationData['shield_scheme_type'] ?? ''
                                                    )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['shield_scheme_type'] ?? ''
                                                    )=='CN' ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="shield_scheme" id="shield_scheme"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['shield_scheme'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Corporate Discount</td>
                                        <td class="cell-type">
                                            <input type="text" id="group_b_type"
                                                value="{{ $quotationData['group_b_type'] ?? 'INV' }}" readonly>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" id="group_b_amount" class="numeric-only"
                                                placeholder="0.00" value="{{ $quotationData['group_b_amount'] ?? '' }}"
                                                readonly>
                                            <input type="hidden" id="corporate_discount" name="corporate_discount"
                                                value="{{ $quotationData['corporate_discount'] ?? '' }}">
                                            <input type="hidden" id="corporate_discount_type"
                                                name="corporate_discount_type"
                                                value="{{ $quotationData['corporate_discount_type'] ?? '' }}">
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">
                                            <select id="group_c_select" class="group-select" disabled>
                                                <option value="exchange_bonus" {{ ($quotationData['group_c_select']
                                                    ?? '' )=='exchange_bonus' ? 'selected' : '' }}>Exchange Bonus
                                                </option>
                                                <option value="green_bonus" {{ ($quotationData['group_c_select'] ?? ''
                                                    )=='green_bonus' ? 'selected' : '' }}>Green Bonus</option>
                                                <option value="welcome_bonus" {{ ($quotationData['group_c_select'] ?? ''
                                                    )=='welcome_bonus' ? 'selected' : '' }}>Welcome Bonus</option>
                                                <option value="loyalty_bonus" {{ ($quotationData['group_c_select'] ?? ''
                                                    )=='loyalty_bonus' ? 'selected' : '' }}>Loyalty Bonus</option>
                                            </select>
                                        </td>
                                        <td class="cell-type">
                                            <input type="text" id="group_c_type"
                                                value="{{ $quotationData['group_c_type'] ?? 'CN1' }}" readonly>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" id="group_c_amount" class="numeric-only"
                                                placeholder="0.00" value="{{ $quotationData['group_c_amount'] ?? '' }}"
                                                readonly>
                                            <input type="hidden" id="exchange_bonus" name="exchange_bonus"
                                                value="{{ $quotationData['exchange_bonus'] ?? '' }}">
                                            <input type="hidden" id="exchange_bonus_type" name="exchange_bonus_type"
                                                value="{{ $quotationData['exchange_bonus_type'] ?? '' }}">
                                            <input type="hidden" id="green_bonus" name="green_bonus"
                                                value="{{ $quotationData['green_bonus'] ?? '' }}">
                                            <input type="hidden" id="green_bonus_type" name="green_bonus_type"
                                                value="{{ $quotationData['green_bonus_type'] ?? '' }}">
                                            <input type="hidden" id="welcome_bonus" name="welcome_bonus"
                                                value="{{ $quotationData['welcome_bonus'] ?? '' }}">
                                            <input type="hidden" id="welcome_bonus_type" name="welcome_bonus_type"
                                                value="{{ $quotationData['welcome_bonus_type'] ?? '' }}">
                                            <input type="hidden" id="loyalty_bonus" name="loyalty_bonus"
                                                value="{{ $quotationData['loyalty_bonus'] ?? '' }}">
                                            <input type="hidden" id="loyalty_bonus_type" name="loyalty_bonus_type"
                                                value="{{ $quotationData['loyalty_bonus_type'] ?? '' }}">
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Accessories Special Discount</td>
                                        <td class="cell-type">
                                            <select id="accessories_spl_disc_type" name="accessories_spl_disc_type"
                                                disabled>
                                                <option value="INV" {{ ($quotationData['accessories_spl_disc_type']
                                                    ?? '' )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['accessories_spl_disc_type'] ?? ''
                                                    )=='CN' ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="accessories_spl_disc" id="accessories_spl_disc"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['accessories_spl_disc'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label" id="coating_discount_label">
                                            {{ $quotationData['coating_discount_label'] ?? 'Coating Special Discount' }}
                                        </td>
                                        <td class="cell-type">
                                            <select id="ceramic_discount_type" name="ceramic_discount_type" disabled>
                                                <option value="INV" {{ ($quotationData['ceramic_discount_type'] ?? ''
                                                    )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['ceramic_discount_type'] ?? ''
                                                    )=='CN' ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="ceramic_discount" id="ceramic_discount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['ceramic_discount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">PPF Special Discount</td>
                                        <td class="cell-type">
                                            <select id="ppf_discount_type" name="ppf_discount_type" disabled>
                                                <option value="INV" {{ ($quotationData['ppf_discount_type'] ?? ''
                                                    )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['ppf_discount_type'] ?? '' )=='CN'
                                                    ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="ppf_discount" id="ppf_discount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['ppf_discount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label" id="charger_discount_title">Charger Swapping Discount
                                        </td>
                                        <td class="cell-type">
                                            <input type="text" id="charger_swapping_discount_type"
                                                name="charger_swapping_discount_type"
                                                value="{{ $quotationData['charger_swapping_discount_type'] ?? 'CN2' }}"
                                                readonly disabled>
                                        </td>
                                        <td class="cell-amount" id="charger_discount_cell">
                                            <input type="text" id="charger_swapping_discount"
                                                name="charger_swapping_discount" class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['charger_swapping_discount'] ?? '' }}"
                                                readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Other Cash Discount</td>
                                        <td class="cell-type">
                                            <select id="other_cash_discount_type" name="other_cash_discount_type"
                                                disabled>
                                                <option value="INV" {{ ($quotationData['other_cash_discount_type'] ?? ''
                                                    )=='INV' ? 'selected' : '' }}>INV</option>
                                                <option value="CN" {{ ($quotationData['other_cash_discount_type'] ?? ''
                                                    )=='CN' ? 'selected' : '' }}>CN</option>
                                            </select>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="other_cash_discount" id="other_cash_discount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['other_cash_discount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row">
                                        <td class="cell-label">Special Cash Discount</td>
                                        <td class="cell-type">
                                            <input type="text" id="special_cash_discount_type"
                                                name="special_cash_discount_type"
                                                value="{{ $quotationData['special_cash_discount_type'] ?? 'INV' }}"
                                                readonly>
                                        </td>
                                        <td class="cell-amount">
                                            <input type="text" name="special_cash_discount" id="special_cash_discount"
                                                class="numeric-only" placeholder="0.00"
                                                value="{{ $quotationData['special_cash_discount'] ?? '' }}" readonly>
                                        </td>
                                    </tr>

                                    <tr class="grid-row total-row" style="background: #f2f2f2; font-weight: bold;">
                                        <td class="cell-label"
                                            style="text-align: center; font-weight: bold; font-size: 11px;">TOTAL
                                            DISCOUNT</td>
                                        <td class="cell-type"></td>
                                        <td class="cell-amount">
                                            <input id="total_discount_amount" readonly
                                                style="font-weight: bold; font-size: 11px; text-align: right;"
                                                value="{{ $quotationData['total_discount'] ?? $quotationData['total_discount_amount'] ?? '' }}">
                                            <input type="hidden" id="total_discount" name="total_discount"
                                                value="{{ $quotationData['total_discount'] ?? '' }}">
                                        </td>
                                    </tr>

                                </tbody>
                            </table>

                            {{-- ================= DISCOUNT BIFURCATION BOX (Hidden in Preview) ================= --}}
                            <div class="discount-bifurcation-box"
                                style="margin-top: 3px; border: 1px solid #000; width: 100%;">
                                <table class="quotation-grid bifurcation-grid"
                                    style="width:100%; border-collapse: collapse; table-layout: fixed;">
                                    <thead>
                                        <tr>
                                            <th
                                                style="width:20%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                DISCOUNT BIFURCATION</th>
                                            <th
                                                style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                INV</th>
                                            <th
                                                style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                CN</th>
                                            <th
                                                style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                CN1</th>
                                            <th
                                                style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                CN2</th>
                                            <th
                                                style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td
                                                style="background:#f2f2f2; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                AMOUNT</td>
                                            <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                <input id="inv_discount_display" readonly
                                                    style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;"
                                                    value="{{ $quotationData['inv_discount_display'] ?? '' }}">
                                            </td>
                                            <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                <input id="cn_discount_display" readonly
                                                    style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;"
                                                    value="{{ $quotationData['cn_discount_display'] ?? '' }}">
                                            </td>
                                            <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                <input id="cn1_discount_display" readonly
                                                    style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;"
                                                    value="{{ $quotationData['cn1_discount_display'] ?? '' }}">
                                            </td>
                                            <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                <input id="cn2_discount_display" readonly
                                                    style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;"
                                                    value="{{ $quotationData['cn2_discount_display'] ?? '' }}">
                                            </td>
                                            <td
                                                style="background:#abb8ca; border:1px solid #000; padding:3px 5px; text-align:right;">
                                                <input id="total_discount_bifurcation_display" readonly
                                                    style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;"
                                                    value="{{ $quotationData['total_discount_bifurcation_display'] ?? '' }}">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <input type="hidden" id="invoiced_discount_summary" name="invoiced_discount_summary"
                                value="{{ $quotationData['invoiced_discount_summary'] ?? '' }}">
                            <input type="hidden" id="credit_note_discount_summary" name="credit_note_discount_summary"
                                value="{{ $quotationData['credit_note_discount_summary'] ?? '' }}">
                            <input type="hidden" id="cn1_discount_summary" name="cn1_discount_summary"
                                value="{{ $quotationData['cn1_discount_summary'] ?? '' }}">
                            <input type="hidden" id="cn2_discount_summary" name="cn2_discount_summary"
                                value="{{ $quotationData['cn2_discount_summary'] ?? '' }}">
                        </div>

                    </div>

                    {{-- ================= NET RECEIVABLE - 3 Columns Layout ================= --}}
                    <div class="quotation-summary mt-1"
                        style="border: 2px solid #000; margin-top: 10px; display: flex;">

                        <div class="onroad-row-cell onroad-label"
                            style="background: #abb8ca; color: #000; font-size:13px; font-weight: bold; padding: 5px 8px; flex: 0 0 16%; border-right: 1px solid #000;">
                            NET RECEIVABLE
                        </div>


                        <div class="onroad-row-cell onroad-amount"
                            style="background: #abb8ca; color: #000; font-weight: bold; padding: 5px 8px; flex: 0 0 20%; justify-content: flex-end; border-right: 1px solid #000;">
                            <input id="net_receivable_summary" name="net_receivable_summary" readonly
                                style="font-weight: bold; font-size: 12px; text-align: center; background: transparent; border: none; width: 100%;"
                                value="{{ $quotationData['net_receivable'] ?? $quotationData['net_receivable_summary'] ?? '' }}">
                        </div>

                        <div class="onroad-row-cell onroad-words"
                            style="background: #abb8ca; color: #000; font-size: 13px; font-weight: bold; padding: 5px 8px; flex: 1 1 64%; display: flex; align-items: center; justify-content: flex-end; border-right: 1px solid #000;">
                            <span id="net_receivable_words">Zero Rupees Only</span>
                        </div>


                    </div>

                </div>

                @php
                $insuranceNoteText = '';
                $insuranceCovers = $otfData['insurance_covers'] ?? [];

                if (!empty($insuranceCovers) && is_array($insuranceCovers)) {
                $insuranceNoteText = collect($insuranceCovers)->map(function ($cover) {
                $name = $cover['name'] ?? '';
                // If cover is a string, use it directly
                if (is_string($cover)) {
                $name = $cover;
                $price = 0;
                // Extract price if present
                if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                $price = floatval(str_replace(',', '', $matches[1]));
                $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                }
                return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                }
                $price = (float) ($cover['price'] ?? 0);
                return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                })->filter()->implode(', ');
                }

                // ✅ FALLBACK: If no insurance covers, use insurance_amount
                if (empty($insuranceNoteText) && !empty($otfData['insurance_amount'] ?? '') &&
                ($otfData['insurance_amount'] ?? '0') != '0' && ($otfData['insurance_amount'] ?? '0.00') != '0.00') {
                $insurancePolicyLabel = $insurance_type_map[$otfData['policy_type'] ?? ''] ?? '';
                $insuranceNoteText = trim($insurancePolicyLabel . ' (₹' . number_format((float)
                ($otfData['insurance_amount'] ?? 0), 2) . ')');
                }
                @endphp
                <div class="insurance-note-row">
                    Insurance:
                    <span id="insurance_print"
                        style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">{{
                        $insuranceNoteText ?: '' }}&nbsp;</span>
                </div>

                {{-- Accessories Display --}}
                @php
                $accessoriesNoteText = '';
                $accessoriesCovers = $otfData['accessories'] ?? [];
                if (!empty($accessoriesCovers) && is_array($accessoriesCovers)) {
                $accessoryNames = [];
                foreach ($accessoriesCovers as $accCode) {
                $acc = $accessoryList->firstWhere('part_no', $accCode);
                if ($acc) {
                $accessoryNames[] = $acc->item . ' (₹' . number_format((float)$acc->ndp, 2) . ')';
                }
                }
                $accessoriesNoteText = implode(', ', $accessoryNames);
                }
                // ✅ FALLBACK: If no accessories, use accessories_amount
                if (empty($accessoriesNoteText) && !empty($otfData['accessories_amount'] ?? '') &&
                ($otfData['accessories_amount'] ?? '0') != '0' && ($otfData['accessories_amount'] ?? '0.00') != '0.00')
                {
                $accessoriesNoteText = 'Accessories (₹' . number_format((float) ($otfData['accessories_amount'] ?? 0),
                2) . ')';
                }
                @endphp
                <div class="accessories-note-row">
                    Accessories:
                    <span id="accessories_print"
                        style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">{{
                        $accessoriesNoteText ?: '&nbsp;' }}</span>
                </div>

                <table class="bill-table note-box flex-grow-1">
                    <tr>
                        <td>
                            <div style="font-weight:bold; font-size:8px; margin-bottom:3px;">NOTE:</div>
                            <ol id="quotation_notes_list"
                                style="font-size:8px; font-weight:bold; line-height:1.4; text-align:justify; margin:0; padding-left:12px;">
                                <li>Price quoted is current and subject to change without notice.</li>
                                <li>The Price ruling at the time of delivery only will be applicable irrespective of
                                    when payment was made.</li>
                                <li>All specifications, colors and features are subject to change without prior notice.
                                </li>
                                <li>TCS @ 1 % Will be collected on full invoice value, if value is equal to or exceeds
                                    INR 10 Lakhs.</li>
                                <li>Delivery will be against full payment only.</li>
                                <li>This is not a firm order and no claim for priority can be made on the basis of
                                    proforma invoice.</li>
                                <li>All disputes shall be subject to Bikaner jurisdiction only.</li>
                                <li>Booking need to be done with minimum INR 21,000.</li>
                                <li id="rto_charges_note_item">RTO Charges are subject to the vehicle's registration
                                    category.</li>
                            </ol>
                        </td>
                    </tr>
                </table>

            </div>

        </div>

    </div>
</div>

<div class="card-footer text-end mt-3 no-print">
    <button type="button" class="btn btn-primary no-print" onclick="printQuotation();">
        <i class="la la-print"></i> Print / Save PDF
    </button>
    @if($quotation->status != 'booked')
    <a href="{{ backpack_url('sales/quotation/' . $quotation->id . '/edit') }}" class="btn btn-warning">
        <i class="la la-edit"></i> Edit Quotation
    </a>
    <button type="button" class="btn btn-success" onclick="processBooking({{ $quotation->id }})">
        <i class="la la-check-circle"></i> Process to Booking
    </button>
    @endif
    <a href="{{ backpack_url('sales/quotation') }}" class="btn btn-secondary">
        <i class="la la-arrow-left"></i> Back to List
    </a>
</div>

@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ============================================================
// PREVIEW PAGE - COMPLETE SCRIPT
// ============================================================

// ============================================================
// 1. NUMBER TO INDIAN WORDS
// ============================================================
function numberToIndianWords(num) {
    num = parseFloat(num) || 0;
    if (num === 0) return 'Zero Rupees Only';

    const a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'
    ];
    const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function inWords(n) {
        if (n < 20) return a[n];
        if (n < 100) return b[Math.floor(n / 10)] + (n % 10 !== 0 ? ' ' + a[n % 10] : '');
        if (n < 1000) return a[Math.floor(n / 100)] + ' Hundred' + (n % 100 !== 0 ? ' ' + inWords(n % 100) : '');
        if (n < 100000) return inWords(Math.floor(n / 1000)) + ' Thousand' + (n % 1000 !== 0 ? ' ' + inWords(n % 1000) : '');
        if (n < 10000000) return inWords(Math.floor(n / 100000)) + ' Lakh' + (n % 100000 !== 0 ? ' ' + inWords(n % 100000) : '');
        return inWords(Math.floor(n / 10000000)) + ' Crore' + (n % 10000000 !== 0 ? ' ' + inWords(n % 10000000) : '');
    }

    let parts = num.toFixed(2).split('.');
    let rupees = parseInt(parts[0], 10);
    let paise = parseInt(parts[1], 10);

    let result = '';
    if (rupees > 0) result += inWords(rupees) + ' Rupees';
    if (paise > 0) {
        if (rupees > 0) result += ' ';
        result += inWords(paise) + ' Paise';
    }
    return result ? result + ' Only' : 'Zero Rupees Only';
}

// ============================================================
// 2. BUILD INSURANCE TEXT FROM QUOTATION DATA
// ============================================================
function buildInsuranceTextFromData() {
    var insuranceData = $('#insurance_print_data').val();
    
    // ✅ Check if data exists and is valid
    if (insuranceData && insuranceData !== '[]' && insuranceData !== '""' && insuranceData !== 'null') {
        try {
            var parsed = JSON.parse(insuranceData);
            if (Array.isArray(parsed) && parsed.length > 0) {
                var list = parsed.map(function(item) {
                    var name = item.name || '';
                    var price = Number(item.price || 0);
                    return name + (price > 0 ? ' (₹' + price.toLocaleString('en-IN') + ')' : '');
                });
                return list.join(', ');
            }
        } catch(e) {
            console.log('Error parsing insurance data:', e);
        }
    }
    
    // ✅ FALLBACK: Check insurance_amount field
    var insuranceAmount = $('#insurance_amount').val();
    if (insuranceAmount && parseFloat(insuranceAmount) > 0) {
        var policyType = $('#policy_type').find('option:selected').text() || '';
        return (policyType ? policyType + ' ' : '') + '(₹' + parseFloat(insuranceAmount).toLocaleString('en-IN') + ')';
    }
    
    return '';
}

function buildAccessoriesTextFromData() {
    var rawData = $('#accessories_print_data').val();
    
    if (rawData && rawData !== '[]' && rawData !== '""' && rawData !== 'null') {
        try {
            var items = JSON.parse(rawData);
            if (items && items.length > 0) {
                return items.map(function(item) {
                    var name = item.name || '';
                    var price = Number(item.price || 0);
                    return name + (price > 0 ? ' (₹' + price.toLocaleString('en-IN') + ')' : '');
                }).join(', ');
            }
        } catch(e) {
            console.log('Error parsing accessories print data:', e);
        }
    }
    
    // ✅ FALLBACK: Check accessories_amount field
    var accAmount = $('#accessories_amount').val();
    if (accAmount && parseFloat(accAmount) > 0) {
        return 'Accessories (₹' + parseFloat(accAmount).toLocaleString('en-IN') + ')';
    }
    
    return '';
}

// ============================================================
// 4. UPDATE ALL DISPLAY ELEMENTS
// ============================================================
function updatePreviewDisplay() {
    // Update Insurance Print Text
    var insuranceText = buildInsuranceTextFromData();
    if (insuranceText) {
        $('#insurance_print').text(insuranceText);
    }

    // Update Accessories Print Text
    var accessoriesText = buildAccessoriesTextFromData();
    if (accessoriesText) {
        $('#accessories_print').text(accessoriesText);
    }

    // Update Net Receivable Words
    var netReceivable = $('#net_receivable_summary').val();
    if (netReceivable && netReceivable !== '') {
        var words = numberToIndianWords(netReceivable);
        $('#net_receivable_words').text(words);
    }

    // Update Registration Print Text
    updateRegistrationPrintText();

    // Toggle RTO Charges Note
    toggleRtoChargesNote();

    // Toggle Row Visibility
    toggleRowVisibility();
}

// ============================================================
// 5. UPDATE REGISTRATION PRINT TEXT
// ============================================================
function updateRegistrationPrintText() {
    var typeText = $('#registration_no_type option:selected').text().trim();
    var categoryText = $('#registration_category option:selected').text().trim();
    var inHouseVal = $('input[name="in_house_rto"]:checked').val();
    var inHouseText = (inHouseVal === "1") ? "Yes" : "No";

    var parts = [];
    if (typeText && typeText.toLowerCase() !== 'select type') {
        parts.push('(' + typeText + ')');
    }
    if (categoryText && categoryText.toLowerCase() !== 'select category') {
        parts.push('(' + categoryText + ')');
    }
    parts.push('(In-House: ' + inHouseText + ')');

    $('#registration_details_print').text(parts.join(' '));
}

// ============================================================
// 6. TOGGLE RTO CHARGES NOTE
// ============================================================
function toggleRtoChargesNote() {
    var inHouseValue = $('input[name="in_house_rto"]:checked').val();
    var $rtoNoteItem = $('#rto_charges_note_item');

    if (inHouseValue === "1") {
        if ($rtoNoteItem.length === 0) {
            $('#quotation_notes_list').append('<li id="rto_charges_note_item">RTO Charges are subject to the vehicle\'s registration category.</li>');
        }
    } else {
        $rtoNoteItem.remove();
    }
}

// ============================================================
// 7. TOGGLE ROW VISIBILITY FOR PREVIEW/PRINT
// ============================================================
function toggleRowVisibility() {
    $('.price-grid tbody tr').each(function() {
        var $row = $(this);
        var $input = $row.find('td.cell-amount input').first();
        var value = $input.length ? $input.val() : '';
        
        if (value === 'N/A' || value === '' || value === null || value === '0' || value === '0.00') {
            $row.addClass('print-hide');
        } else {
            $row.removeClass('print-hide');
        }
    });
    
    $('.discount-grid tbody tr').each(function() {
        var $row = $(this);
        var $input = $row.find('td.cell-amount input').first();
        var value = $input.length ? $input.val() : '';
        
        if (value === 'N/A' || value === '' || value === null || value === '0' || value === '0.00') {
            $row.addClass('print-hide');
        } else {
            $row.removeClass('print-hide');
        }
    });
}

// ============================================================
// 8. PRINT FUNCTIONS - SAME AS CREATE.BLADE.PHP
// ============================================================
var printLabelRestoreList = [];

function prepareOptionLabelsForPrint() {
    printLabelRestoreList = [];
    $('.quotation-grid td.cell-option select').not('#accessories').each(function () {
        var $select = $(this);
        var selectId = $select.attr('id');

        if (selectId === 'insurance_covers' || selectId === 'registration_no_type' || selectId === 'registration_category') {
            return;
        }

        var selectedText = $select.find('option:selected').first().text().trim();
        if (!selectedText || selectedText.toLowerCase() === 'select') {
            return;
        }

        var $label = $select.closest('tr').find('td.cell-label').first();
        printLabelRestoreList.push({
            el: $label,
            html: $label.html()
        });

        if (selectId === 'insurance_company') {
            $label.html('Insurance');
            $label.append('(' + $select.val() + ')');
            return;
        }

        $label.append('(' + selectedText + ')');
    });
}

function restoreOptionLabelsAfterPrint() {
    printLabelRestoreList.forEach(function (item) {
        item.el.html(item.html);
    });
    printLabelRestoreList = [];
}

function isEmptyGridValue(value) {
    value = (value || '').toString().trim();
    return (value === '' || value === '0' || value === '0.00' || value === 'N/A');
}

function prepareItemVisibilityForPrint() {
    $('.quotation-grid tbody tr').each(function () {
        var $row = $(this);
        var $amountInput = $row.find('td.cell-amount input').first();
        var value = $amountInput.length ? $amountInput.val() : '';

        if (isEmptyGridValue(value)) {
            $row.addClass('print-hide');
        } else {
            $row.removeClass('print-hide');
        }
    });
}

function restoreItemVisibilityAfterPrint() {
    $('.quotation-grid tbody tr').removeClass('print-hide');
}

function printQuotation() {
    prepareOptionLabelsForPrint();
    prepareItemVisibilityForPrint();
    window.print();
    restoreOptionLabelsAfterPrint();
    restoreItemVisibilityAfterPrint();
}

// ============================================================
// 9. PROCESS BOOKING
// ============================================================
function processBooking(quotationId) {
    Swal.fire({
        title: 'Process to Booking',
        text: 'Are you sure you want to create a booking from this quotation?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Process to Booking!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait while we create the booking.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            window.location.href = '{{ backpack_url("sales/booking/create") }}?quotation_id=' + quotationId;
        }
    });
}

// ============================================================
// 10. DOCUMENT READY
// ============================================================
$(document).ready(function() {
    // Initialize Select2 (disabled for preview)
    $('#accessories').select2({
        placeholder: 'Select accessories...',
        width: '100%',
        closeOnSelect: false,
        allowClear: false
    }).prop('disabled', true);

    $('#insurance_covers').select2({
        placeholder: 'Select insurance covers...',
        width: '100%',
        closeOnSelect: false,
        allowClear: false
    }).prop('disabled', true);

    // ✅ Update all display elements
    updatePreviewDisplay();

    console.log('✅ Quotation Preview loaded successfully!');
    console.log('Insurance Text:', $('#insurance_print').text());
    console.log('Accessories Text:', $('#accessories_print').text());
});
</script>
@endpush