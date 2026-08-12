@extends(backpack_view('blank'))

@php
use App\Services\OrgService;
@endphp

@section('title', 'Quotation Form')

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

        /* Reassign widths for the 2 remaining columns per table - each totals 100% */
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

        /* Fix summary alignment to match 4 columns */
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
            justify-content: flex-center !important;
            align-items: center !important;
        }

        .quotation-summary .onroad-words {
            flex: 1 1 70% !important;
            justify-content: flex-end !important;
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

    .bill-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
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

    /* Select2 fixed height */
    .select2-container {
        width: 100% !important;
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

    /* Hide selected chips */
    .select2-selection__choice {
        display: none !important;
    }

    .select2-search--inline {
        width: 100% !important;
    }

    .select2-search__field {
        width: 100% !important;
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

    /* ================= Quotation Grid (Price / Discount) — now TWO independent
       tables/boxes placed side by side. Because each side is its own table with
       its own <tbody>, every row (price item or discount item) can be shown or
       hidden completely independently. When a field is blank / 0 / N/A, its row
       is simply removed from the flow (display:none) while printing, and the
       remaining rows in that box naturally move up to close the gap — the two
       boxes no longer need to stay row-for-row aligned with each other. ================= */

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

    /* Column widths are set on the <thead> cells (not a <colgroup>/<col>) because
       with table-layout:fixed the widths of the FIRST ROW's cells define every
       column's width for the whole table — this is the spec-defined, most
       reliably-supported way across browsers/print engines, unlike overriding
       <col> widths which some print renderers ignore. */
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

    /* ================= Quotation Summary (Total Receivable / Total Discount / On Road Price) ================= */
    /* ================= Quotation Summary (Total Receivable / Total Discount / On Road Price) ================= */
    .quotation-summary {
        display: flex;
        font-weight: bold;
        border: 1px solid #000;
        width: 100%;
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

    /* ================= Financier Invoice / Discount Bifurcation — div based ================= */
    .financier-discount-grid {
        display: grid;
        grid-template-columns: 25% 25% 25% 25%;
        border-left: 1px solid #000;
        border-top: 1px solid #000;
        margin-bottom: 15px;
    }

    .financier-discount-grid>div {
        border-right: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 3px 5px;
        font-size: 10px;
        min-height: 26px;
        display: flex;
        align-items: center;
    }

    .financier-discount-grid .fd-header {
        background: #d9d9d9;
        font-weight: bold;
        text-align: center;
        justify-content: center;
        grid-column: span 2;
    }

    .financier-discount-grid .fd-label {
        background: #f2f2f2;
        font-weight: 600;
    }

    .financier-discount-grid .fd-bold input {
        font-weight: bold;
    }

    .financier-discount-grid input {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 10px;
    }


    .cell-label:has(.group-select) {
        background: #f2f2f2;
        font-weight: 600;
    }

    /* Accessories note line: hidden on screen, shown only in print above the Note box */
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

    @media print {

        /* OPTION and TYPE columns are always folded into the label / omitted for print.
           Each table now only has 3 columns of its own, so this is simply column 2. */
        .quotation-grid th:nth-child(2),
        .quotation-grid td:nth-child(2) {
            display: none !important;
        }

        /* Hiding column 2 above would otherwise leave each table using less than
           its full width (blank space on the right), so the box would look
           "shrunk". Re-assign the widths of the 2 remaining columns per table
           (on the <th> cells, since that's what actually drives
           table-layout:fixed column sizing) so they always add up to 100%
           while printing. Price and discount keep their own ratio. */
        .price-grid th:nth-child(1) {
            width: 70% !important;
        }

        .price-grid th:nth-child(3) {
            width: 30% !important;
        }

        .discount-grid th:nth-child(1) {
            width: 70% !important;
        }

        .discount-grid th:nth-child(3) {
            width: 30% !important;
        }

        /* Every price/discount item is its own independent row now. A row is
           hidden purely on its own value being blank / 0 / N/A — the other box
           is completely unaffected, and its own remaining rows just move up to
           close the gap since it's normal table flow. */
        .quotation-grid tr.print-hide {
            display: none !important;
        }

        .quotation-grid-split {
            gap: 3px !important;
        }

        /* Hide Financier Invoice / Discount Bifurcation box while printing */
        .financier-discount-grid {
            display: none !important;
        }

        /* Show the Accessories line above the Note box only while printing */
        .accessories-note-row {
            display: block !important;
        }

        /* Keep the boxes at full width, don't let them shrink when items are hidden */
        .quotation-sheet,
        .quotation-grid,
        .quotation-summary,
        .bill-table {
            width: 100% !important;
        }

        #accessories+.select2-container {
            display: none !important;
        }

        /* Sirf text dikhao */
        #accessories_print {
            display: block !important;
            white-space: normal;
            word-break: break-word;
            font-size: 11px;
            line-height: 15px;
        }
    }

    .quotation-grid input.numeric-only,
    .quotation-grid input.amount-field,
    .quotation-summary input,
    .financier-discount-grid input {
        text-align: right !important;
    }

    /* ================= Custom Multi-Select with Checkboxes ================= */
    .select2-container--default .select2-results__option {
        padding: 6px 12px;
        user-select: none;
    }

    .select2-container--default .select2-results__option .select2-checkbox {
        margin-right: 8px;
        width: 15px;
        height: 15px;
        accent-color: #0d6efd;
        cursor: pointer;
    }

    .select2-checkbox-label {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    /* Disabled/Freezed options */
    .select2-container--default .select2-results__option--disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .select2-container--default .select2-results__option--disabled .select2-checkbox {
        cursor: not-allowed;
        opacity: 0.6;
    }

    /* ================= HIDE CHIPS - ONLY SHOW COUNT ================= */
    /* Hide chips completely for both selects */
    .select2-selection--multiple .select2-selection__choice {
        display: none !important;
    }

    /* Show only the placeholder/count text */
    .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
    }

    .select2-selection--multiple .select2-selection__rendered .select2-selection__placeholder {
        color: #6c757d;
    }

    /* Selected count text style - override any other styles */
    .select2-selection--multiple .select2-selection__rendered .select2-selection__choice {
        display: none !important;
    }

    /* Fix for selection display */
    .select2-selection--multiple .select2-selection__rendered li {
        list-style: none;
    }

    .select2-selection--multiple .select2-selection__rendered .select2-selection__choice__remove {
        display: none !important;
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

    @media print {
        .discount-bifurcation-box {
            display: none !important;
        }
    }
</style>

@endpush

@section('content')

<div class="quotation-form">
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
                // Commercial / Personal Vehicle
                $mahindraLogo = asset('images/mahindra-pv-cv-logo.png');
                }
                @endphp

                <div class="row align-items-center">

                    <!-- Left Logo -->
                    <div class="col-2 text-center">
                        <img src="{{ asset('images/bikaner_logo.png') }}" style="height:75px;">
                    </div>

                    <!-- Center Text -->
                    <div class="col-8 text-center">

                        <h3 class="fw-bold mb-1">
                            BIKANER MOTORS PRIVATE LIMITED
                        </h3>

                        <div style="font-size:13px;">
                            Regd. Office : Sunderi Chhabil Mansion, NH-11,
                            Jaipur Road, P.O. Udasar, Bikaner-334022
                        </div>

                        <div style="font-size:13px;">
                            Branch Office : 6th KM Stone,
                            Ratangarh Road, Churu (Raj.)
                        </div>

                        <h4 class="mt-2 mb-0 fw-bold text-uppercase">
                            Vehicle Quotation
                        </h4>

                    </div>

                    <!-- Right Logo -->
                    <div class="col-2 text-center">
                        <img src="{{ $mahindraLogo }}" style="max-width:110px; max-height:60px;">
                    </div>

                </div>

            </div>
        </div>

        <form method="POST" action="{{ route('quotation.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="quotation-sheet">

                <div class="form-section">

                    {{-- ================= Customer Details ================= --}}
                    {{-- ================= Customer Details ================= --}}
                    {{-- MOCK ENQUIRY TEST INPUT (NO-PRINT) --}}
                    <div class="no-print mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">🔍 Mock Enquiry</span>
                                    <input type="text" id="mock_enquiry_no" class="form-control"
                                        placeholder="Enter 001-013" value="001">
                                    <button type="button" id="btnFetchMock" class="btn btn-primary">
                                        <i class="la la-refresh"></i> Fetch
                                    </button>
                                    <button type="button" id="btnResetMock" class="btn btn-secondary">
                                        <i class="la la-undo"></i> Reset
                                    </button>
                                </div>
                                <small class="text-muted">Enter enquiry number (001-013) and click Fetch to load mock
                                    data</small>
                            </div>
                        </div>
                    </div>

                    <table class="bill-table mb-3">
                        <tr>
                            <td class="title" width="18%">Enquiry ID</td>
                            <td width="32%">
                                <input type="text" id="enquiry_id" value="{{ optional($selectedEnquiry)->id }}"
                                    readonly>
                                <input type="hidden" name="enquiry_no" id="enquiry_no_hidden"
                                    value="{{ optional($selectedEnquiry)->id }}">
                            </td>
                            <td class="title" width="18%">Customer Name</td>
                            <td width="32%">
                                <input type="text" id="customer_name"
                                    value="{{ optional($selectedEnquiry)->full_name }}" readonly>
                                <input type="hidden" name="customer_name" id="customer_name_hidden"
                                    value="{{ optional($selectedEnquiry)->full_name }}">
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Mobile Number</td>
                            <td>
                                <input type="text" value="{{ optional($selectedEnquiry)->mobile }}" readonly>
                                <input type="hidden" name="mobile" id="mobile_hidden"
                                    value="{{ optional($selectedEnquiry)->mobile }}">
                            </td>



                            <td class="title">Care Of Name</td>
                            <td>
                                <div class="input-group">
                                    <select name="careof" id="careof" class="form-select2" style="max-width: 80px;">
                                        <option value="">Select</option>
                                        <option value="1">Son of</option>
                                        <option value="2">Daughter of</option>
                                        <option value="3">Married to</option>
                                        <option value="4">Guardian Name</option>
                                    </select>

                                    <input type="text" name="careofname" id="careofname" placeholder="Enter Name"
                                        value="{{ old('careofname') }}">
                                </div>
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Vehicle Details ================= --}}

                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="18%">Segment</td>
                            <td width="32%">
                                <input type="text" id="segment"
                                    value="{{ optional($selectedEnquiry->segment)->name ?? $selectedEnquiry->segment_code }}"
                                    readonly>
                                <input type="hidden" name="segment_code" id="segment_code"
                                    value="{{ optional($selectedEnquiry)->segment_code }}">
                            </td>
                            <td class="title" width="18%">Model</td>
                            <td width="32%">
                                <input type="text" id="model"
                                    value="{{ optional($selectedEnquiry->model)->name ?? $selectedEnquiry->model_code }}"
                                    readonly>
                                <input type="hidden" name="model_code" id="model_code"
                                    value="{{ optional($selectedEnquiry)->model_code }}">
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Variant</td>
                            <td>
                                <input type="text" id="variant" value="{{ optional($selectedEnquiry->variant)->display_name
                                ?? optional($selectedEnquiry->variant)->custom_name
                                ?? $selectedEnquiry->variant_code }}" readonly>
                                <input type="hidden" name="variant_code" id="variant_code"
                                    value="{{ optional($selectedEnquiry)->variant_code }}">
                            </td>
                            <td class="title">Color</td>
                            <td>
                                <input type="text" id="color"
                                    value="{{ optional($selectedEnquiry->color)->name ?? $selectedEnquiry->color_code }}"
                                    readonly>
                                <input type="hidden" name="color_code" id="color_code"
                                    value="{{ optional($selectedEnquiry)->color_code }}">
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Permit</td>
                            <td>
                                <select id="permit">
                                    <option value="">Select Permit</option>
                                </select>
                            </td>
                            <td class="title">OEM Code</td>
                            <td>
                                <input type="text" id="oem_code" value="{{ optional($selectedEnquiry)->oem_code }}"
                                    readonly>
                                <input type="hidden" name="oem_code" id="oem_code_hidden"
                                    value="{{ optional($selectedEnquiry)->oem_code }}">
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
                                                <input name="ex_showroom_price" id="ex_showroom_price"
                                                    class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Insurance </td>
                                            <td class="cell-option">
                                                {{-- Insurance Company --}}
                                                <select id="insurance_company" class="form-control mb-1"
                                                    style="margin-bottom:2px !important;">
                                                    <option value="">Select Company</option>
                                                </select>
                                                {{-- Insurance Covers --}}
                                                {{-- ================= PRICE DETAILS BOX ================= --}}
                                                <select id="insurance_covers" name="insurance_covers[]"
                                                    class="form-control" multiple style="height:auto; min-height:30px;">
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" id="insurance_amount" name="insurance_amount"
                                                    class="numeric-only" placeholder="0.00" readonly>
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">
                                                Registration
                                                <!-- Printed text span for Registration details -->
                                                <span id="registration_details_print"
                                                    class="print-only-inline fw-bold ms-1"></span>
                                            </td>
                                            <td class="cell-option" style="padding: 2px 4px !important;">
                                                <div
                                                    style="display: flex; gap: 4px; align-items: center; justify-content: space-between; width: 100%;">

                                                    <!-- 1. Registration Type Dropdown -->
                                                    <div style="flex: 1 1 38%;">
                                                        <select name="registration_no_type" id="registration_no_type"
                                                            class="form-select form-select-sm"
                                                            style="font-size: 9px; padding: 1px 3px; height: 22px; border: 1px solid #ccc; border-radius: 3px; width: 100%; background: #fff;">
                                                            <option value="">Select Type</option>
                                                            @foreach($reg_no_type_map ?? ['1'=>'Regular', '2'=>'BH
                                                            Series', '3'=>'Special Number'] as $key => $value)
                                                            <option value="{{ $key }}" {{ old('registration_no_type',
                                                                $rto?->rgn_no_type ?? '') == $key ? 'selected' : '' }}>
                                                                {{ $value }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <!-- 2. Registration Category Dropdown -->
                                                    <div style="flex: 1 1 38%;">
                                                        <select name="registration_category" id="registration_category"
                                                            class="form-select form-select-sm"
                                                            style="font-size: 9px; padding: 1px 3px; height: 22px; border: 1px solid #ccc; border-radius: 3px; width: 100%; background: #fff;">
                                                            <option value="">Select Category</option>
                                                            @foreach($registration_type_map as $key => $value)
                                                            <option value="{{ $key }}" {{ old('registration_category',
                                                                $otfData['registration_category'] ?? $rto?->
                                                                registration_category ?? '') == $key ? 'selected' : ''
                                                                }}>
                                                                {{ $value }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <!-- 3. In House RTO (Radio Buttons - Screen View Only) -->
                                                    <div class="no-print"
                                                        style="flex: 0 0 auto; display: flex; align-items: center; border: 1px solid #ccc; border-radius: 3px; padding: 1px; background: #fff; height: 22px;">
                                                        <span
                                                            style="font-size: 8px; font-weight: bold; margin-right: 3px; margin-left: 2px; color: #555; white-space: nowrap;">In-House:</span>

                                                        <label
                                                            style="font-size: 8px; font-weight: bold; margin: 0 2px; cursor: pointer; display: flex; align-items: center; gap: 1px;">
                                                            <input type="radio" name="in_house_rto" value="1" {{
                                                                old('in_house_rto', ($rto ?? null) ? '1' : '0' )=='1'
                                                                ? 'checked' : '' }}
                                                                style="width: auto !important; margin: 0;"> Yes
                                                        </label>

                                                        <label
                                                            style="font-size: 8px; font-weight: bold; margin: 0 2px; cursor: pointer; display: flex; align-items: center; gap: 1px;">
                                                            <input type="radio" name="in_house_rto" value="0" {{
                                                                old('in_house_rto', ($rto ?? null) ? '1' : '0' )=='0'
                                                                ? 'checked' : '' }}
                                                                style="width: auto !important; margin: 0;"> No
                                                        </label>
                                                    </div>

                                                </div>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" id="registration_amount" name="registration_amount"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Accessories</td>
                                            <td class="cell-option">
                                                <select name="accessories[]" id="accessories" multiple>
                                                    @foreach($accessoryList as $accessory)
                                                    <option value="{{ $accessory->part_no }}"
                                                        data-price="{{ $accessory->ndp }}">
                                                        {{ $accessory->item }}
                                                        (₹{{ number_format($accessory->ndp,2) }})
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input id="accessories_amount" name="accessories_amount"
                                                    class="numeric-only" readonly value="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Maxicare</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="maxicare" name="maxicare" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">VLTD Device (GPS)</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="vltd_device" name="vltd_device" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Coating</td>
                                            <td class="cell-option">
                                                <select id="coating" name="coating">
                                                    <option value="No Coating">No Coating</option>
                                                    <option value="Ceramic">Ceramic</option>
                                                    <option value="Graphene">Graphene</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input id="coating_price" name="coating_price" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">PPF</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="ppf" name="ppf" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">RTO Yellow Tape</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="rto_yellow_tape" name="rto_yellow_tape" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Kazam Charging Kit</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="kazam_charging_kit" name="kazam_charging_kit"
                                                    class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Incidental Charges</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="incidental_charges" name="incidental_charges"
                                                    class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Shield</td>
                                            <td class="cell-option">
                                                <select id="shield" name="shield">
                                                    <option value="4th Year">4th Year</option>
                                                    <option value="4th + 5th Year">4th + 5th Year</option>
                                                    <option value="No Shield">No Shield</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input id="shield_price" name="shield_price" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">RSA</td>
                                            <td class="cell-option">
                                                <select id="rsa" name="rsa">
                                                    <option>1 Year</option>
                                                    <option>2 Year</option>
                                                    <option>3 Year</option>
                                                    <option>4 Year</option>
                                                    <option>5 Year</option>
                                                    <option>No RSA</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input id="rsa_amount" name="rsa_amount" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Fastag</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="fastag" name="fastag" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">COD Charges</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="cod_charges" name="cod_charges" class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Charger Swapping</td>
                                            <td class="cell-option">
                                                <select id="charger_swapping" name="charger_swapping">
                                                    <option value="N/A">N/A</option>
                                                    <option value="NCH to 7.2 kW">NCH to 7.2 kW</option>
                                                    <option value="NCH to 11.2 kW">NCH to 11.2 kW</option>
                                                    <option value="7.2 kW to 11.2 kW">7.2 kW to 11.2 kW</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input id="charger_swapping_amount" name="charger_swapping_amount"
                                                    class="numeric-only">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">TCS @1%</td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="tcs" name="tcs" class="numeric-only" readonly>
                                            </td>
                                        </tr>

                                        <tr class="grid-row total-row" style="background: #f2f2f2; font-weight: bold;">
                                            <td class="cell-label"
                                                style="text-align: center; font-weight: bold; font-size: 11px;">TOTAL
                                                RECEIVABLE
                                            </td>
                                            <td class="cell-option"></td>
                                            <td class="cell-amount">
                                                <input id="total_receivable" name="total_receivable" readonly
                                                    style="font-weight: bold; font-size: 11px; text-align: right;">
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
                                                <select id="group_a_select" class="group-select">
                                                    <option value="cash_scheme_oem">Cash Scheme OEM</option>
                                                    <option value="csd_discount">CSD Discount</option>
                                                    <option value="fame_subsidy" id="fame_subsidy_option">Fame Subsidy
                                                        (LMM)
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="cell-type">
                                                <select id="group_a_type">
                                                    <option>INV</option>
                                                    <option>CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" id="group_a_amount" class="numeric-only"
                                                    placeholder="0.00">
                                                <input type="hidden" id="cash_scheme_oem" name="cash_scheme_oem">
                                                <input type="hidden" id="cash_scheme_oem_type"
                                                    name="cash_scheme_oem_type">
                                                <input type="hidden" id="csd_discount" name="csd_discount">
                                                <input type="hidden" id="csd_discount_type" name="csd_discount_type">
                                                <input type="hidden" id="fame_subsidy" name="fame_subsidy">
                                                <input type="hidden" id="fame_subsidy_type" name="fame_subsidy_type">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Cash Scheme Dealer</td>
                                            <td class="cell-type">
                                                <select id="dealer_discount_type" name="dealer_discount_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="dealer_discount" id="dealer_discount"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Accessories Scheme</td>
                                            <td class="cell-type">
                                                <select id="accessories_discount_type" name="accessories_discount_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="accessories_discount" id="accessories_discount"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Shield Scheme</td>
                                            <td class="cell-type">
                                                <select id="shield_scheme_type" name="shield_scheme_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="shield_scheme" id="shield_scheme"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Corporate Discount</td>
                                            <td class="cell-type">
                                                <input type="text" id="group_b_type" value="INV" readonly>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" id="group_b_amount" class="numeric-only"
                                                    placeholder="0.00">
                                                <input type="hidden" id="corporate_discount" name="corporate_discount">
                                                <input type="hidden" id="corporate_discount_type"
                                                    name="corporate_discount_type">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">
                                                <select id="group_c_select" class="group-select">
                                                    <option value="exchange_bonus">Exchange Bonus</option>
                                                    <option value="green_bonus">Green Bonus</option>
                                                    <option value="welcome_bonus">Welcome Bonus</option>
                                                    <option value="loyalty_bonus">Loyalty Bonus</option>
                                                </select>
                                            </td>
                                            <td class="cell-type">
                                                <input type="text" id="group_c_type" value="CN1" readonly>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" id="group_c_amount" class="numeric-only"
                                                    placeholder="0.00">
                                                <input type="hidden" id="exchange_bonus" name="exchange_bonus">
                                                <input type="hidden" id="exchange_bonus_type"
                                                    name="exchange_bonus_type">
                                                <input type="hidden" id="green_bonus" name="green_bonus">
                                                <input type="hidden" id="green_bonus_type" name="green_bonus_type">
                                                <input type="hidden" id="welcome_bonus" name="welcome_bonus">
                                                <input type="hidden" id="welcome_bonus_type" name="welcome_bonus_type">
                                                <input type="hidden" id="loyalty_bonus" name="loyalty_bonus">
                                                <input type="hidden" id="loyalty_bonus_type" name="loyalty_bonus_type">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Accessories Special Discount</td>
                                            <td class="cell-type">
                                                <select id="accessories_spl_disc_type" name="accessories_spl_disc_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="accessories_spl_disc" id="accessories_spl_disc"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label" id="coating_discount_label">
                                                Coating Special Discount
                                            </td>
                                            <td class="cell-type">
                                                <select id="ceramic_discount_type" name="ceramic_discount_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="ceramic_discount" id="ceramic_discount"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">PPF Special Discount</td>
                                            <td class="cell-type">
                                                <select id="ppf_discount_type" name="ppf_discount_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="ppf_discount" id="ppf_discount"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label" id="charger_discount_title">Charger Swapping Discount
                                            </td>
                                            <td class="cell-type">
                                                <input type="text" id="charger_swapping_discount_type"
                                                    name="charger_swapping_discount_type" value="CN2" readonly disabled>
                                            </td>
                                            <td class="cell-amount" id="charger_discount_cell">
                                                <input type="text" id="charger_swapping_discount"
                                                    name="charger_swapping_discount" class="numeric-only"
                                                    placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Other Cash Discount</td>
                                            <td class="cell-type">
                                                <select id="other_cash_discount_type" name="other_cash_discount_type">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
                                                </select>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="other_cash_discount" id="other_cash_discount"
                                                    class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>

                                        <tr class="grid-row">
                                            <td class="cell-label">Special Cash Discount</td>
                                            <td class="cell-type">
                                                <input type="text" id="special_cash_discount_type"
                                                    name="special_cash_discount_type" value="INV" readonly>
                                            </td>
                                            <td class="cell-amount">
                                                <input type="text" name="special_cash_discount"
                                                    id="special_cash_discount" class="numeric-only" placeholder="0.00">
                                            </td>
                                        </tr>
                                        <tr class="grid-row total-row" style="background: #f2f2f2; font-weight: bold;">
                                            <td class="cell-label"
                                                style="text-align: center; font-weight: bold; font-size: 11px;">TOTAL
                                                DISCOUNT
                                            </td>
                                            <td class="cell-type"></td>
                                            <td class="cell-amount">
                                                <input id="total_discount_amount" readonly
                                                    style="font-weight: bold; font-size: 11px; text-align: right;">
                                                <input type="hidden" id="total_discount" name="total_discount">
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                                {{-- ================= DISCOUNT BIFURCATION BOX ================= --}}
                                <div class="discount-bifurcation-box"
                                    style="margin-top: 3px; border: 1px solid #000; width: 100%;">
                                    <table class="quotation-grid bifurcation-grid"
                                        style="width:100%; border-collapse: collapse; table-layout: fixed;">
                                        <thead>
                                            <tr>
                                                <th
                                                    style="width:20%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    DISCOUNT BIFURCATION
                                                </th>
                                                <th
                                                    style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    INV
                                                </th>
                                                <th
                                                    style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    CN
                                                </th>
                                                <th
                                                    style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    CN1
                                                </th>
                                                <th
                                                    style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    CN2
                                                </th>
                                                <th
                                                    style="width:16%; background: #d9d9d9; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    TOTAL
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td
                                                    style="background:#f2f2f2; border:1px solid #000; padding:3px 5px; font-size:10px; font-weight:bold; text-align:center;">
                                                    AMOUNT
                                                </td>
                                                <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                    <input id="inv_discount_display" readonly
                                                        style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;">
                                                </td>
                                                <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                    <input id="cn_discount_display" readonly
                                                        style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;">
                                                </td>
                                                <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                    <input id="cn1_discount_display" readonly
                                                        style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;">
                                                </td>
                                                <td style="border:1px solid #000; padding:3px 5px; text-align:right;">
                                                    <input id="cn2_discount_display" readonly
                                                        style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;">
                                                </td>
                                                <td
                                                    style="background:#abb8ca; border:1px solid #000; padding:3px 5px; text-align:right;">
                                                    <input id="total_discount_bifurcation_display" readonly
                                                        style="font-weight:bold; font-size:11px; text-align:center; width:100%; border:none; background:transparent;">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Hidden inputs for form submission --}}
                                <input type="hidden" id="invoiced_discount_summary" name="invoiced_discount_summary">
                                <input type="hidden" id="credit_note_discount_summary"
                                    name="credit_note_discount_summary">
                                <input type="hidden" id="cn1_discount_summary" name="cn1_discount_summary">
                                <input type="hidden" id="cn2_discount_summary" name="cn2_discount_summary">
                            </div>

                        </div>


                        <!-- ON ROAD PRICE - Separate summary -->
                        <!-- ON ROAD PRICE / NET RECEIVABLE - 3 Columns Layout -->
                        <div class="quotation-summary mt-1"
                            style="border: 2px solid #000; margin-top: 10px; display: flex;">

                            <!-- 1. NET RECEIVABLE Label -->
                            <div class="onroad-row-cell onroad-label"
                                style="background: #abb8ca; color: #000; font-size:13px; font-weight: bold; padding: 5px 8px; flex: 0 0 16%; border-right: 1px solid #000;">
                                NET RECEIVABLE
                            </div>

                            <!-- 2. NET RECEIVABLE Amount (Number) -->
                            <div class="onroad-row-cell onroad-amount"
                                style="background: #abb8ca; color: #000; font-weight: bold; padding: 5px 8px; flex: 0 0 20%; justify-content: flex-end; border-right: 1px solid #000;">
                                <input id="net_receivable_summary" name="net_receivable_summary" readonly
                                    style="font-weight: bold; font-size: 12px; text-align: center; background: transparent; border: none; width: 100%;">
                            </div>

                            <!-- 3. NET RECEIVABLE Words (Dynamic Text) -->
                            <div class="onroad-row-cell onroad-words"
                                style="background: #abb8ca; color: #000; font-size: 13px; font-weight: bold; padding: 5px 8px; flex: 1 1 64%; display: flex; align-items: center; justify-content: flex-end; border-right: 1px solid #000;">
                                <span id="net_receivable_words">Zero Rupees Only</span>
                            </div>

                        </div>

                    </div>



                    {{-- ================= Accessories (shown only while printing) ================= --}}
                    {{-- ================= Insurance (shown only while printing) ================= --}}
                    <div class="insurance-note-row">
                        Insurance:
                        <span id="insurance_print"
                            style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">&nbsp;</span>
                    </div>

                    {{-- ================= Accessories (shown only while printing) ================= --}}
                    <div class="accessories-note-row">
                        Accessories:
                        <span id="accessories_print"
                            style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">&nbsp;</span>
                    </div>


                    <table class="bill-table note-box flex-grow-1">
                        <tr>
                            <td>
                                <div style="font-weight:bold; font-size:8px; margin-bottom:3px;">
                                    NOTE:
                                </div>

                                <!-- Added id="quotation_notes_list" -->
                                <ol id="quotation_notes_list" style="
                font-size:8px;
                font-weight:bold;
                line-height:1.4;
                text-align:justify;
                margin:0;
                padding-left:12px;
            ">
                                    <li>Price quoted is current and subject to change without notice.</li>
                                    <li>The Price ruling at the time of delivery only will be applicable irrespective of
                                        when payment was made.</li>
                                    <li>All specifications, colors and features are subject to change without prior
                                        notice.</li>
                                    <li>TCS @ 1 % Will be collected on full invoice value, if value is equal to or
                                        exceeds INR 10 Lakhs.</li>
                                    <li>Delivery will be against full payment only.</li>
                                    <li>This is not a firm order and no claim for priority can be made on the basis of
                                        proforma invoice.</li>
                                    <li>All disputes shall be subject to Bikaner jurisdiction only.</li>
                                    <li>Booking need to be done with minimum INR 21,000.</li>
                                </ol>
                            </td>
                        </tr>
                    </table>

                </div>

            </div>


    </div>
</div>
</div>

<div class="card-footer text-end mt-3 no-print">
    <button type="button" class="btn btn-primary no-print" onclick="printQuotation();">

        <i class="la la-print"></i>

        Print / Save PDF

    </button>
    <button type="submit" class="btn btn-success">
        <i class="la la-save"></i> Save Quotation
    </button>

    <a href="{{ backpack_url('quotation-form') }}" class="btn btn-secondary">
        Cancel
    </a>
</div>
</form>

</div>
</div>

@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ============================================================
// 1. MOCK DATA DEFINITION
// ============================================================

const ENQUIRIES = {
    "001": {
        enquiry_no: "ENQ0001",
        customer: { name: "Rajesh Kumar", mobile: "9876543210", careOf: "1", careOfName: "Ramesh Kumar" },
        vehicle: {
            segment_code: "UV",
            segment_name: "UV",
            model_code: "XUV700",
            model_name: "XUV700",
            variant_code: "AX7L",
            variant_name: "AX7 L Diesel AT",
            color_code: "MB",
            color_name: "Midnight Black",
            oem_code: "XUV700-AX7L-DIE-AT-MB"
        },
        pricingKey: "xuv700"
    },
    "002": {
        enquiry_no: "ENQ0002",
        customer: { name: "Priya Sharma", mobile: "9123456780", careOf: "2", careOfName: "Mahesh Sharma" },
        vehicle: {
            segment_code: "EV",
            segment_name: "Electric",
            model_code: "BEVX9",
            model_name: "BEV X9",
            variant_code: "X9",
            variant_name: "X9 Long Range",
            color_code: "WH",
            color_name: "Pearl White",
            oem_code: "BEV-X9-LR-WH"
        },
        pricingKey: "bevx9"
    },
    "003": {
        enquiry_no: "ENQ0003",
        customer: { name: "Suresh Yadav", mobile: "9988776655", careOf: "", careOfName: "" },  // edge case: Care Of left blank
        vehicle: {
            segment_code: "CV",
            segment_name: "Commercial",
            model_code: "BOLERO",
            model_name: "Bolero Camper",
            variant_code: "CAMPER",
            variant_name: "Camper 4WD",
            color_code: "GR",
            color_name: "Dune Beige",
            oem_code: "BOLERO-CAMPER-4WD-GR"
        },
        pricingKey: "bolero"
    },
    "004": {
        enquiry_no: "ENQ0004",
        customer: { name: "Amit Singh", mobile: "9811223344", careOf: "1", careOfName: "Balwant Singh" },
        vehicle: {
            segment_code: "CV",
            segment_name: "Commercial",
            model_code: "VEERO",
            model_name: "Veero",
            variant_code: "VEERO",
            variant_name: "Veero Pickup",
            color_code: "WH",
            color_name: "Arctic White",
            oem_code: "VEERO-PICKUP-WH"
        },
        pricingKey: "veero"
    },
    "005": {
        enquiry_no: "ENQ0005",
        customer: { name: "Vikram Mehta", mobile: "9765432109", careOf: "1", careOfName: "Ashok Mehta" },
        vehicle: {
            segment_code: "LMM",
            segment_name: "LMM",
            model_code: "TREO",
            model_name: "Treo",
            variant_code: "TREO",
            variant_name: "Treo Yaari",
            color_code: "BL",
            color_name: "Ocean Blue",
            oem_code: "TREO-YAARI-BL"
        },
        pricingKey: "treo"
    },
    "006": {
        enquiry_no: "ENQ0006",
        customer: { name: "Rohan Verma", mobile: "9876500006", careOf: "1", careOfName: "Suresh Verma" },
        vehicle: {
            segment_code: "PV",
            segment_name: "Personal Vehicle",
            model_code: "PVX1",
            model_name: "PV X1",
            variant_code: "X1-AT",
            variant_name: "X1 Automatic",
            color_code: "RD",
            color_name: "Radiant Red",
            oem_code: "PV-ABOVE-TCS-RED"
        },
        pricingKey: "pvAboveTcs"
    },
    "007": {
        enquiry_no: "ENQ0007",
        customer: { name: "Sneha Gupta", mobile: "9876500007", careOf: "3", careOfName: "Rajesh Gupta" },
        vehicle: {
            segment_code: "PV",
            segment_name: "Personal Vehicle",
            model_code: "PVX2",
            model_name: "PV X2",
            variant_code: "X2-MT",
            variant_name: "X2 Manual",
            color_code: "BL",
            color_name: "Deep Blue",
            oem_code: "PV-BELOW-TCS-BLUE"
        },
        pricingKey: "pvBelowTcs"
    },
    "008": {
        enquiry_no: "ENQ0008",
        customer: { name: "Vikas Shah", mobile: "9876500008", careOf: "1", careOfName: "Prakash Shah" },
        vehicle: {
            segment_code: "PV",
            segment_name: "Personal Vehicle",
            model_code: "PVX3",
            model_name: "PV X3",
            variant_code: "X3-AT",
            variant_name: "X3 Automatic",
            color_code: "GR",
            color_name: "Graphite Grey",
            oem_code: "PV-NEAR-TCS-ADJUST"
        },
        pricingKey: "pvNearTcs"
    },
    "009": {
        enquiry_no: "ENQ0009",
        customer: { name: "Priya Mehra", mobile: "9876500009", careOf: "2", careOfName: "Ashok Mehra" },
        vehicle: {
            segment_code: "EV",
            segment_name: "Battery EV",
            model_code: "BEVX9",
            model_name: "BEV X9",
            variant_code: "X9-LR",
            variant_name: "X9 Long Range",
            color_code: "WH",
            color_name: "Pearl White",
            oem_code: "BEV-FAME-OEM"
        },
        pricingKey: "bevFameOem"
    },
    "010": {
        enquiry_no: "ENQ0010",
        customer: { name: "Karan Joshi", mobile: "9876500010", careOf: "1", careOfName: "Deepak Joshi" },
        vehicle: {
            segment_code: "EV",
            segment_name: "Battery EV",
            model_code: "BEVX7",
            model_name: "BEV X7",
            variant_code: "X7-SR",
            variant_name: "X7 Standard Range",
            color_code: "GN",
            color_name: "Emerald Green",
            oem_code: "BEV-FAME-ONLY"
        },
        pricingKey: "bevFameOnly"
    },
    "011": {
        enquiry_no: "ENQ0011",
        customer: { name: "Manoj Yadav", mobile: "9876500011", careOf: "4", careOfName: "Ram Yadav" },  // edge case: Guardian Name
        vehicle: {
            segment_code: "PV",
            segment_name: "Passenger Vehicle",
            model_code: "PVX4",
            model_name: "PV X4",
            variant_code: "X4-MPV",
            variant_name: "X4 MPV",
            color_code: "WH",
            color_name: "Arctic White",
            oem_code: "PV-PASSENGER-RTO-TAPE"
        },
        pricingKey: "pvPassengerTape"
    },
    "012": {
        enquiry_no: "ENQ0012",
        customer: { name: "Deepak Singh", mobile: "9876500012", careOf: "1", careOfName: "Mahendra Singh" },
        vehicle: {
            segment_code: "PV",
            segment_name: "Passenger Vehicle",
            model_code: "PVX5",
            model_name: "PV X5",
            variant_code: "X5-TAXI",
            variant_name: "X5 Taxi",
            color_code: "YL",
            color_name: "Sun Yellow",
            oem_code: "PV-PERMIT-INS-RTO"
        },
        pricingKey: "pvPermitInsRto"
    },
    "013": {
        enquiry_no: "ENQ0013",
        customer: { name: "Vikram Mehta", mobile: "9876500013", careOf: "3", careOfName: "Sunita Mehta" },
        vehicle: {
            segment_code: "LMM",
            segment_name: "LMM",
            model_code: "TREO",
            model_name: "Treo",
            variant_code: "TREO-Y",
            variant_name: "Treo Yaari LMM",
            color_code: "BL",
            color_name: "Ocean Blue",
            oem_code: "LMM-KAZAM-CHARGING"
        },
        pricingKey: "lmmKazam"
    },
        "014": {
        enquiry_no: "ENQ0014",
        customer: { name: "Ananya Sharma", mobile: "9876500014", careOf: "2", careOfName: "Rajesh Sharma" },
        vehicle: {
            segment_code: "PV",
            segment_name: "Personal Vehicle",
            model_code: "XUV700",
            model_name: "XUV700",
            variant_code: "AX7L",
            variant_name: "AX7 L Diesel AT",
            color_code: "BA",
            color_name: "Electric Blue",
            oem_code: "XUV700-AX7L-DIE-AT-BA"
        },
        pricingKey: "xuv700" // Uses the existing comprehensive XUV700 pricing
    },
    "015": {
        enquiry_no: "ENQ0015",
        customer: { name: "Vivek Patel", mobile: "9876500015", careOf: "1", careOfName: "Mahendra Patel" },
        vehicle: {
            segment_code: "BEV",
            segment_name: "Electric Vehicle",
            model_code: "BE6",
            model_name: "BE6",
            variant_code: "BM12AH515MB01D00",
            variant_name: "BE6 One Above B59 R19 C11",
            color_code: "QK",
            color_name: "Galaxy Grey",
            oem_code: "BE6-ONE-ABOVE-GALAXY-GREY"
        },
        pricingKey: "bevx9" // Uses the existing BEV X9 pricing
    },
    "016": {
        enquiry_no: "ENQ0016",
        customer: { name: "Kavya Nair", mobile: "9876500016", careOf: "3", careOfName: "Deepak Nair" },
        vehicle: {
            segment_code: "CV",
            segment_name: "Commercial Vehicle",
            model_code: "MAXX HD",
            model_name: "MAXX HD",
            variant_code: "PFN2EADMBS6PPFD3",
            variant_name: "MAXX HD 1.7 VXi AC BS6.2",
            color_code: "WD",
            color_name: "Diamond White",
            oem_code: "MAXX-HD-1.7-VXI-AC-WD"
        },
        pricingKey: "maxxhd" // New pricing for MAXX HD
    },
    "017": {
        enquiry_no: "ENQ0017",
        customer: { name: "Arjun Mehta", mobile: "9876500017", careOf: "1", careOfName: "Ravi Mehta" },
        vehicle: {
            segment_code: "LMM",
            segment_name: "Last Mile Mobility",
            model_code: "TREO YAARI",
            model_name: "Treo Yaari",
            variant_code: "0000AMJ0002",
            variant_name: "Treo Yaari Soft Top",
            color_code: "0N",
            color_name: "Blue White",
            oem_code: "TREO-YAARI-SOFT-TOP-BW"
        },
        pricingKey: "treoYaari" // New pricing for Treo Yaari
    },
    "018": {
        enquiry_no: "ENQ0018",
        customer: { name: "Priya Singh", mobile: "9876500018", careOf: "2", careOfName: "Vikram Singh" },
        vehicle: {
            segment_code: "PV",
            segment_name: "Personal Vehicle",
            model_code: "SCORPIO-N",
            model_name: "Scorpio-N",
            variant_code: "AZ1116YGTTA4GA01",
            variant_name: "Z4 D AT 2WD 7 STR BS6.2",
            color_code: "JD",
            color_name: "Deep Forest",
            oem_code: "SCORPIO-N-Z4-D-AT-JD"
        },
        pricingKey: "scorpioN" // New pricing for Scorpio-N
    }
};

const PRICING = {
    xuv700: {
        permit: [
            { type: "Private", default: true },
            { type: "Passenger", default: false }
        ],
        receivables: {
            exShowroom: 2199000,
            insurance: [
                {
                    permit: "Private",
                    default: true,
                    companies: [
                        {
                            insCo: "ICICI",
                            default: false,
                            price: [
                                { head: "Basic OD + TP", price: 48500, Nature: "M" },
                                { head: "Nil Depreciation", price: 9200, Nature: "M" },
                                { head: "Consumables", price: 1850, Nature: "M" },
                                { head: "RTI", price: 7800, Nature: "O" },
                                { head: "Engine Protect", price: 6200, Nature: "O" },
                                { head: "Key Protect", price: 1100, Nature: "O" }
                            ]
                        },
                        {
                            insCo: "USGI",
                            default: true,
                            price: [
                                { head: "Basic OD + TP", price: 51200, Nature: "M" },
                                { head: "Nil Depreciation", price: 9800, Nature: "M" },
                                { head: "Consumables", price: 2100, Nature: "M" },
                                { head: "RTI", price: 8500, Nature: "O" },
                                { head: "Engine Protect", price: 7100, Nature: "O" },
                                { head: "Tyre Protect", price: 2650, Nature: "O" },
                                { head: "NCB Protect", price: 3200, Nature: "O" }
                            ]
                        }
                    ]
                },
                {
                    permit: "Passenger",
                    default: false,
                    companies: [
                        {
                            insCo: "ICICI",
                            default: true,
                            price: [
                                { head: "Basic OD + TP", price: 56800, Nature: "M" },
                                { head: "Nil Depreciation", price: 11200, Nature: "M" },
                                { head: "Consumables", price: 2450, Nature: "M" },
                                { head: "RTI", price: 9600, Nature: "O" },
                                { head: "RSA", price: 1350, Nature: "O" }
                            ]
                        },
                        {
                            insCo: "USGI",
                            default: false,
                            price: [
                                { head: "Basic OD + TP", price: 59500, Nature: "M" },
                                { head: "Nil Depreciation", price: 12100, Nature: "M" },
                                { head: "Consumables", price: 2700, Nature: "M" },
                                { head: "Engine Protect", price: 8500, Nature: "O" },
                                { head: "RTI", price: 10200, Nature: "O" }
                            ]
                        }
                    ]
                }
            ],
            RTO: {
                TRC: 1500,
                TAX: [
                    { permit: "Private", default: true, amount: 198000 },
                    { permit: "Passenger", default: false, amount: 245000 }
                ]
            },
            accessories: [
                { item: "Dash Cam", mrp: 4079, discount: 500, code: "DC1" },
                { item: "Maxicare 5Yr", mrp: 24999, discount: 2500, code: "MX1" },
                { item: "Ceramic Coating", mrp: 16729, discount: 2000, code: "CER" },
                { item: "PPF Ultra", mrp: 78119, discount: 8000, code: "PPF" },
                { item: "Seat Cover 7Str", mrp: 7190, discount: 800, code: "SC1" },
                { item: "Floor Mat", mrp: 3452, discount: 400, code: "FM1" },
                { item: "Chrome Set", mrp: 10218, discount: 1200, code: "CS1" }
            ],
            maxicare: 24999,
            coating: [
                { title: "No Coating", price: 0, default: true },
                { title: "Ceramic", price: 16729, default: false },
                { title: "Graphene", price: 24500, default: false }
            ],
            ppf: [
                { title: "No PPF", price: 0, default: false },
                { title: "Ultra", price: 78119, default: true }
            ],
            shield: [
                { title: "4th Year", price: 24990, default: true },
                { title: "4th + 5th Year", price: 38990, default: false },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1499, default: true },
                { title: "2 Year", price: 2799, default: false },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 5000,
            incidental: 3500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 1500,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: false },
                { title: "NCH to 7.2 kW @ ₹18,500", amount: 18500, default: true }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 45000, type: "INV" },
                { key: "csd_discount", label: "CSD Discount", amount: 20000, type: "INV" },
                { key: "fame_subsidy", label: "Fame Subsidy", amount: 10000, type: "INV" }
            ],
            "dealer-scheme": { amount: 15000, type: "CN" },
            "accessory-scheme": { amount: 5000, type: "INV" },
            "shield-scheme": { amount: 2500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 50000, type: "INV" },
                { name: "Loyalty Bonus", amount: 25000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 30000, type: "CN1" },
                { name: "Green Bonus", amount: 15000, type: "CN1" },
                { name: "Welcome Bonus", amount: 10000, type: "CN1" }
            ],
            "accessories-spl-discount": { amount: 2500, type: "INV" },
            "coating-spl-discount": { amount: 2000, type: "INV" },
            "ppf-spl-discount": { amount: 5000, type: "CN" },
            "charger-swapping-discount": { amount: 3500, type: "CN2" },
            "other-cash-discount": { amount: 1000, type: "CN" },
            "special-cash-discount": { amount: 12000, type: "INV" }
        }
    },
    bevx9: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 1899000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [{
                    insCo: "ICICI",
                    default: true,
                    price: [
                        { head: "Basic OD + TP", price: 28500, Nature: "M" },
                        { head: "Nil Depreciation", price: 6200, Nature: "M" },
                        { head: "Consumables", price: 1400, Nature: "M" },
                        { head: "Battery Protect", price: 8500, Nature: "O" },
                        { head: "RTI", price: 5200, Nature: "O" }
                    ]
                }]
            }],
            RTO: { TRC: 1000, TAX: [{ permit: "Private", default: true, amount: 0 }] },
            accessories: [
                { item: "Home Charger 7.2kW", mrp: 45000, discount: 5000, code: "HC1" },
                { item: "Dash Cam", mrp: 4079, discount: 0, code: "DC1" },
                { item: "Floor Mat EV", mrp: 2800, discount: 300, code: "FM2" }
            ],
            shield: [
                { title: "4th Year", price: 18990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1999, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2000,
            "rto-tape": 0,
            fastag: 600,
            COD: 0,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: true },
                { title: "NCH to 7.2 kW @ ₹18,500", amount: 18500, default: false },
                { title: "NCH to 11.2 kW @ ₹28,500", amount: 28500, default: false }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 75000, type: "INV" }
            ],
            "dealer-scheme": { amount: 10000, type: "CN" },
            "accessory-scheme": { amount: 3000, type: "INV" },
            "shield-scheme": { amount: 1500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 40000, type: "INV" },
                { name: "Loyalty Bonus", amount: 20000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 25000, type: "CN1" },
                { name: "Green Bonus", amount: 35000, type: "CN1" }
            ],
            "fame-subsidy": { amount: 100000, type: "INV" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 100000, upper: 1050000, max: 50000, amount: 0, type: "INV" }
        }
    },
    bolero: {
        permit: [{ type: "Goods", default: true }],
        receivables: {
            exShowroom: 1125000,
            insurance: [{
                permit: "Goods",
                default: true,
                companies: [{
                    insCo: "USGI",
                    default: true,
                    price: [
                        { head: "Basic OD + TP", price: 22400, Nature: "M" },
                        { head: "Nil Depreciation", price: 4800, Nature: "M" },
                        { head: "Consumables", price: 950, Nature: "M" },
                        { head: "RTI", price: 3200, Nature: "O" }
                    ]
                }]
            }],
            RTO: {
                TRC: 2500,
                TAX: [{ permit: "Goods", default: true, amount: 85000 }]
            },
            accessories: [
                { item: "Canopy", mrp: 28500, discount: 3000, code: "CAN" },
                { item: "Seat Cover", mrp: 4500, discount: 500, code: "SC2" },
                { item: "Mud Flaps", mrp: 890, discount: 0, code: "MF1" }
            ],
            shield: [
                { title: "4th Year", price: 14990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1299, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: { permit: "Goods", price: 4250 },
            kazam: 0,
            incidental: 2500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 25000, type: "INV" }
            ],
            "dealer-scheme": { amount: 8000, type: "CN" },
            "accessory-scheme": { amount: 2000, type: "INV" },
            "shield-scheme": { amount: 1000, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 20000, type: "INV" },
                { name: "Loyalty Bonus", amount: 10000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 15000, type: "CN1" },
                { name: "Welcome Bonus", amount: 8000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 100000, upper: 1050000, max: 50000, amount: 0, type: "INV" }
        }
    },
    veero: {
        permit: [{ type: "Goods", default: true }],
        receivables: {
            exShowroom: 875000,
            insurance: [{
                permit: "Goods",
                default: true,
                companies: [{
                    insCo: "ICICI",
                    default: true,
                    price: [
                        { head: "Basic OD + TP", price: 16800, Nature: "M" },
                        { head: "Nil Depreciation", price: 3500, Nature: "M" },
                        { head: "Consumables", price: 750, Nature: "M" },
                        { head: "RTI", price: 2400, Nature: "O" }
                    ]
                }]
            }],
            RTO: {
                TRC: 2000,
                TAX: [{ permit: "Goods", default: true, amount: 62000 }]
            },
            accessories: [
                { item: "Load Body Cover", mrp: 6500, discount: 800, code: "LBC" },
                { item: "Seat Cover", mrp: 3200, discount: 300, code: "SC3" }
            ],
            shield: [
                { title: "4th Year", price: 9990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 999, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: { permit: "Goods", price: 3800 },
            kazam: 0,
            incidental: 1500,
            "rto-tape": 999,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 18000, type: "INV" }
            ],
            "dealer-scheme": { amount: 5000, type: "CN" },
            "accessory-scheme": { amount: 1000, type: "INV" },
            "shield-scheme": { amount: 800, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 15000, type: "INV" },
                { name: "Loyalty Bonus", amount: 8000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 12000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 100000, upper: 1050000, max: 50000, amount: 0, type: "INV" }
        }
    },
    treo: {
        permit: [{ type: "LMM", default: true }],
        receivables: {
            exShowroom: 312000,
            insurance: [{
                permit: "LMM",
                default: true,
                companies: [{
                    insCo: "USGI",
                    default: true,
                    price: [
                        { head: "Basic OD + TP", price: 8500, Nature: "M" },
                        { head: "Nil Depreciation", price: 1800, Nature: "M" },
                        { head: "Consumables", price: 450, Nature: "M" },
                        { head: "RTI", price: 1200, Nature: "O" }
                    ]
                }]
            }],
            RTO: {
                TRC: 800,
                TAX: [{ permit: "LMM", default: true, amount: 18500 }]
            },
            accessories: [
                { item: "Welcome Kit", mrp: 589, discount: 0, code: "WK1" },
                { item: "Seat Cover", mrp: 2100, discount: 200, code: "SC4" },
                { item: "Floor Mat", mrp: 890, discount: 0, code: "FM3" }
            ],
            shield: [
                { title: "4th Year", price: 4990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 699, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 6798,
            incidental: 1200,
            "rto-tape": 0,
            fastag: 0,
            COD: 2500,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: true },
                { title: "NCH to 7.2 kW @ ₹12,500", amount: 12500, default: false }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 12000, type: "INV" }
            ],
            "dealer-scheme": { amount: 3000, type: "CN" },
            "accessory-scheme": { amount: 500, type: "INV" },
            "shield-scheme": { amount: 500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 8000, type: "INV" },
                { name: "Loyalty Bonus", amount: 5000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 7000, type: "CN1" },
                { name: "Welcome Bonus", amount: 3000, type: "CN1" }
            ],
            "fame-subsidy": { amount: 0, type: "INV" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 100000, upper: 1050000, max: 50000, amount: 0, type: "INV" }
        }
    },

    // 006 — PV above TCS threshold: high ex-showroom, multi-insurer, rich accessories (Maxicare/PPF/Ceramic)
    pvAboveTcs: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 1850000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 32500, Nature: "M" },
                            { head: "Nil Depreciation", price: 7200, Nature: "M" },
                            { head: "Consumables", price: 1600, Nature: "M" },
                            { head: "Engine Protect", price: 5800, Nature: "O" },
                            { head: "RTI", price: 6500, Nature: "O" },
                            { head: "RSA", price: 950, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "United India (USGI)",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 34800, Nature: "M" },
                            { head: "Nil Depreciation", price: 7800, Nature: "M" },
                            { head: "Consumables", price: 1850, Nature: "M" },
                            { head: "Engine Protect", price: 6200, Nature: "O" },
                            { head: "RTI", price: 7200, Nature: "O" },
                            { head: "Key Protect", price: 1200, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 1500, TAX: [{ permit: "Private", default: true, amount: 210000 }] },
            accessories: [
                { item: "Welcome Kit", mrp: 989, discount: 0, code: "WK-PV1" },
                { item: "Dash Cam", mrp: 4079, discount: 500, code: "DC-PV1" },
                { item: "Reverse Parking Camera", mrp: 2089, discount: 0, code: "RPC-PV1" },
                { item: "Seat Cover Premium", mrp: 7190, discount: 800, code: "SC-PV1" },
                { item: "Floor Mat Set", mrp: 3452, discount: 400, code: "FM-PV1" }
            ],
            maxicare: 24999,
            coating: [
                { title: "No Coating", price: 0, default: false },
                { title: "Ceramic", price: 16729, default: true }
            ],
            ppf: [
                { title: "No PPF", price: 0, default: false },
                { title: "Ultra", price: 78119, default: true }
            ],
            shield: [
                { title: "4th Year", price: 19990, default: true },
                { title: "4th & 5th Year", price: 32990, default: false },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1499, default: true },
                { title: "2 Year", price: 2799, default: false },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 0,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: true },
                { title: "NCH to 7.2 kW @ ₹18,500", amount: 18500, default: false },
                { title: "NCH to 11.2 kW @ ₹28,500", amount: 28500, default: false }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 45000, type: "INV" },
                { key: "csd_discount", label: "CSD Discount", amount: 15000, type: "INV" }
            ],
            "dealer-scheme": { amount: 15000, type: "CN" },
            "accessory-scheme": { amount: 5000, type: "INV" },
            "shield-scheme": { amount: 2500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 40000, type: "INV" },
                { name: "Loyalty Bonus", amount: 25000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 30000, type: "CN1" },
                { name: "Green Bonus", amount: 20000, type: "CN1" }
            ],
            "accessories-spl-discount": { amount: 2500, type: "INV" },
            "coating-spl-discount": { amount: 2000, type: "INV" },
            "ppf-spl-discount": { amount: 8000, type: "CN" },
            "charger-swapping-discount": { amount: 5000, type: "CN2" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 1600000, upper: 2000000, max: 60000, amount: 0, type: "INV" }
        }
    },

    // 007 — PV below TCS threshold: mid-range ex-showroom, multi-insurer with different addon sets
    pvBelowTcs: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 825000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 18500, Nature: "M" },
                            { head: "Nil Depreciation", price: 3500, Nature: "M" },
                            { head: "Consumables", price: 750, Nature: "M" },
                            { head: "RTI", price: 3200, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "USGI",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 17800, Nature: "M" },
                            { head: "Nil Depreciation", price: 3300, Nature: "M" },
                            { head: "Consumables", price: 700, Nature: "M" },
                            { head: "NCB Protect", price: 2800, Nature: "O" },
                            { head: "RSA", price: 950, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 1000, TAX: [{ permit: "Private", default: true, amount: 82000 }] },
            accessories: [
                { item: "Welcome Kit", mrp: 589, discount: 0, code: "WK-PV2" },
                { item: "Seat Cover", mrp: 4100, discount: 400, code: "SC-PV2" },
                { item: "Floor Mat", mrp: 2100, discount: 200, code: "FM-PV2" },
                { item: "Alloy Wheels", mrp: 32000, discount: 4500, code: "AL-PV2" }
            ],
            shield: [
                { title: "4th Year", price: 12990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 999, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2000,
            "rto-tape": 0,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 25000, type: "INV" }
            ],
            "dealer-scheme": { amount: 8000, type: "CN" },
            "accessory-scheme": { amount: 2000, type: "INV" },
            "shield-scheme": { amount: 1000, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 15000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 10000, type: "CN1" }
            ],
            "accessories-spl-discount": { amount: 4500, type: "INV" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: false, lower: 0, upper: 0, max: 0, amount: 0, type: "INV" }
        }
    },

    // 008 — PV near TCS threshold: special-cash-discount enabled to nudge Finvoice above/below the boundary
    pvNearTcs: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 995000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 26500, Nature: "M" },
                            { head: "Nil Depreciation", price: 5200, Nature: "M" },
                            { head: "Consumables", price: 1200, Nature: "M" },
                            { head: "RTI", price: 5400, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "USGI",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 25800, Nature: "M" },
                            { head: "Nil Depreciation", price: 5100, Nature: "M" },
                            { head: "Consumables", price: 1100, Nature: "M" },
                            { head: "Engine Protect", price: 5800, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 1200, TAX: [{ permit: "Private", default: true, amount: 96000 }] },
            accessories: [
                { item: "Seat Cover Deluxe", mrp: 6100, discount: 500, code: "SC-PV3" },
                { item: "Floor Mat", mrp: 2400, discount: 200, code: "FM-PV3" },
                { item: "Chrome Set", mrp: 9218, discount: 1200, code: "CS-PV3" }
            ],
            shield: [
                { title: "4th Year", price: 15990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1299, default: true },
                { title: "2 Year", price: 2199, default: false },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 35000, type: "INV" }
            ],
            "dealer-scheme": { amount: 10000, type: "CN" },
            "accessory-scheme": { amount: 3000, type: "INV" },
            "shield-scheme": { amount: 1500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 25000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 20000, type: "CN1" },
                { name: "Welcome Bonus", amount: 8000, type: "CN1" }
            ],
            "accessories-spl-discount": { amount: 1200, type: "INV" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 900000, upper: 1020000, max: 30000, amount: 0, type: "INV" }
        }
    },

    // 009 — BEV with FAME + OEM scheme, multi-insurer EV covers, charger-swapping on receivable & discount sides
    bevFameOem: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 1899000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 28500, Nature: "M" },
                            { head: "Nil Depreciation", price: 6200, Nature: "M" },
                            { head: "Consumables", price: 1400, Nature: "M" },
                            { head: "Battery Protect", price: 8500, Nature: "O" },
                            { head: "RTI", price: 5200, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "USGI",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 27800, Nature: "M" },
                            { head: "Nil Depreciation", price: 6100, Nature: "M" },
                            { head: "Consumables", price: 1350, Nature: "M" },
                            { head: "Battery Protect", price: 7800, Nature: "O" },
                            { head: "NCB Protect", price: 3200, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 1000, TAX: [{ permit: "Private", default: true, amount: 0 }] },
            accessories: [
                { item: "Home Charger 7.2kW", mrp: 45000, discount: 5000, code: "HC-BEV1" },
                { item: "Dash Cam EV", mrp: 4079, discount: 0, code: "DC-BEV1" },
                { item: "Floor Mat EV", mrp: 2800, discount: 300, code: "FM-BEV1" }
            ],
            shield: [
                { title: "4th Year", price: 18990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1999, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2000,
            "rto-tape": 0,
            fastag: 600,
            COD: 0,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: true },
                { title: "NCH to 7.2 kW @ ₹18,500", amount: 18500, default: false },
                { title: "NCH to 11.2 kW @ ₹28,500", amount: 28500, default: false }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 75000, type: "INV" },
                { key: "fame_subsidy", label: "Fame Subsidy", amount: 100000, type: "INV" }
            ],
            "dealer-scheme": { amount: 10000, type: "CN" },
            "accessory-scheme": { amount: 3000, type: "INV" },
            "shield-scheme": { amount: 1500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 40000, type: "INV" },
                { name: "Loyalty Bonus", amount: 20000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 25000, type: "CN1" }
            ],
            "fame-subsidy": { amount: 100000, type: "INV" },
            "charger-swapping-discount": { amount: 5000, type: "CN2" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 1500000, upper: 2100000, max: 80000, amount: 0, type: "INV" }
        }
    },

    // 010 — BEV with FAME only (no OEM scheme): isolates FAME-only behaviour, simpler accessories/discounts
    bevFameOnly: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 1350000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 24500, Nature: "M" },
                            { head: "Nil Depreciation", price: 5200, Nature: "M" },
                            { head: "Battery Protect", price: 7800, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "USGI",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 23800, Nature: "M" },
                            { head: "Nil Depreciation", price: 5100, Nature: "M" },
                            { head: "Battery Protect", price: 7200, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 1000, TAX: [{ permit: "Private", default: true, amount: 0 }] },
            accessories: [
                { item: "Home Charger 3.3kW", mrp: 28000, discount: 3000, code: "HC-BEV2" },
                { item: "Floor Mat EV", mrp: 2600, discount: 200, code: "FM-BEV2" }
            ],
            shield: [
                { title: "4th Year", price: 14990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1799, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2000,
            "rto-tape": 0,
            fastag: 600,
            COD: 0,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: true }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "fame_subsidy", label: "Fame Subsidy", amount: 80000, type: "INV" }
            ],
            "dealer-scheme": { amount: 8000, type: "CN" },
            "accessory-scheme": { amount: 2000, type: "INV" },
            "shield-scheme": { amount: 1200, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 25000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 15000, type: "CN1" }
            ],
            "fame-subsidy": { amount: 80000, type: "INV" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 1100000, upper: 1500000, max: 40000, amount: 0, type: "INV" }
        }
    },

    // 011 — PV multi-permit (Private + Passenger, default Passenger), VLTD only for Passenger, RTO Yellow Tape auto-added
    pvPassengerTape: {
        permit: [
            { type: "Private", default: false },
            { type: "Passenger", default: true }
        ],
        receivables: {
            exShowroom: 1520000,
            insurance: [{
                permit: "Passenger",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 32500, Nature: "M" },
                            { head: "Nil Depreciation", price: 7200, Nature: "M" },
                            { head: "Consumables", price: 1650, Nature: "M" },
                            { head: "Passenger Cover", price: 4800, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "USGI",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 33800, Nature: "M" },
                            { head: "Nil Depreciation", price: 7400, Nature: "M" },
                            { head: "Passenger Cover", price: 5200, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: {
                TRC: 1500,
                TAX: [
                    { permit: "Private", default: false, amount: 140000 },
                    { permit: "Passenger", default: true, amount: 165000 }
                ]
            },
            accessories: [
                { item: "Seat Cover MPV", mrp: 8100, discount: 800, code: "SC-PV4" },
                { item: "Floor Mat MPV", mrp: 3400, discount: 400, code: "FM-PV4" }
            ],
            shield: [
                { title: "4th Year", price: 17990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "2 Year", price: 2799, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: { permit: "Passenger", price: 3400 },
            kazam: 0,
            incidental: 2500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 30000, type: "INV" }
            ],
            "dealer-scheme": { amount: 12000, type: "CN" },
            "accessory-scheme": { amount: 4000, type: "INV" },
            "shield-scheme": { amount: 1500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 26000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 18000, type: "CN1" },
                { name: "Green Bonus", amount: 12000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 1300000, upper: 1700000, max: 40000, amount: 0, type: "INV" }
        }
    },

    // 012 — PV with permit-driven Insurance AND RTO (Passenger/Taxi), VLTD, TRC/TAX combos, standard CN/OEM validation
    pvPermitInsRto: {
        permit: [
            { type: "Private", default: false },
            { type: "Passenger", default: true }
        ],
        receivables: {
            exShowroom: 1120000,
            insurance: [{
                permit: "Passenger",
                default: true,
                companies: [
                    {
                        insCo: "ICICI Lombard",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 29500, Nature: "M" },
                            { head: "Nil Depreciation", price: 5800, Nature: "M" },
                            { head: "Passenger Cover", price: 5200, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "USGI",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 30500, Nature: "M" },
                            { head: "Nil Depreciation", price: 6000, Nature: "M" },
                            { head: "Passenger Cover", price: 5400, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 2000, TAX: [{ permit: "Passenger", default: true, amount: 145000 }] },
            accessories: [
                { item: "Taxi Roof Light", mrp: 2100, discount: 200, code: "TX-RL" },
                { item: "Seat Cover Taxi", mrp: 5100, discount: 500, code: "SC-PV5" }
            ],
            shield: [
                { title: "4th Year", price: 14990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1999, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: { permit: "Passenger", price: 4250 },
            kazam: 0,
            incidental: 2500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 25000, type: "INV" }
            ],
            "dealer-scheme": { amount: 8000, type: "CN" },
            "accessory-scheme": { amount: 2000, type: "INV" },
            "shield-scheme": { amount: 1000, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 15000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 15000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 1000000, upper: 1300000, max: 25000, amount: 0, type: "INV" }
        }
    },

    // 013 — LMM with Kazam charging kit, charger-swapping add-on with corresponding C2 discount, small TCS config
    lmmKazam: {
        permit: [{ type: "LMM", default: true }],
        receivables: {
            exShowroom: 312000,
            insurance: [{
                permit: "LMM",
                default: true,
                companies: [
                    {
                        insCo: "USGI",
                        default: true,
                        price: [
                            { head: "Basic OD TP", price: 8500, Nature: "M" },
                            { head: "Nil Depreciation", price: 1800, Nature: "M" },
                            { head: "Consumables", price: 450, Nature: "M" },
                            { head: "RTI", price: 1200, Nature: "O" }
                        ]
                    },
                    {
                        insCo: "ICICI Lombard",
                        default: false,
                        price: [
                            { head: "Basic OD TP", price: 8200, Nature: "M" },
                            { head: "Nil Depreciation", price: 1750, Nature: "M" },
                            { head: "RTI", price: 1100, Nature: "O" }
                        ]
                    }
                ]
            }],
            RTO: { TRC: 800, TAX: [{ permit: "LMM", default: true, amount: 18500 }] },
            accessories: [
                { item: "Welcome Kit", mrp: 589, discount: 0, code: "WK-LMM2" },
                { item: "Seat Cover", mrp: 2100, discount: 200, code: "SC-LMM2" },
                { item: "Floor Mat", mrp: 890, discount: 0, code: "FM-LMM2" }
            ],
            shield: [
                { title: "4th Year", price: 4990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 699, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 6798,
            incidental: 1200,
            "rto-tape": 0,
            fastag: 0,
            COD: 2500,
            "charger-swapping": [
                { title: "No Swapping @ ₹0", amount: 0, default: true },
                { title: "NCH to 7.2 kW @ ₹12,500", amount: 12500, default: false }
            ],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 12000, type: "INV" }
            ],
            "dealer-scheme": { amount: 3000, type: "CN" },
            "accessory-scheme": { amount: 500, type: "INV" },
            "shield-scheme": { amount: 500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 8000, type: "INV" },
                { name: "Loyalty Bonus", amount: 5000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 7000, type: "CN1" }
            ],
            "fame-subsidy": { amount: 0, type: "INV" },
            "charger-swapping-discount": { amount: 4000, type: "CN2" },
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 250000, upper: 400000, max: 15000, amount: 0, type: "INV" }
        }
    },
        maxxhd: {
        permit: [{ type: "Goods", default: true }],
        receivables: {
            exShowroom: 895000,
            insurance: [{
                permit: "Goods",
                default: true,
                companies: [{
                    insCo: "ICICI Lombard",
                    default: true,
                    price: [
                        { head: "Basic OD TP", price: 17800, Nature: "M" },
                        { head: "Nil Depreciation", price: 3500, Nature: "M" },
                        { head: "Consumables", price: 750, Nature: "M" },
                        { head: "Engine Protect", price: 4200, Nature: "O" },
                        { head: "RTI", price: 3800, Nature: "O" }
                    ]
                }]
            }],
            RTO: { TRC: 2000, TAX: [{ permit: "Goods", default: true, amount: 72000 }] },
            accessories: [
                { item: "Loading Body Cover", mrp: 8500, discount: 1000, code: "LBC-MH" },
                { item: "Seat Cover Heavy Duty", mrp: 6200, discount: 800, code: "SC-HD" },
                { item: "Mud Flaps Set", mrp: 1200, discount: 0, code: "MF-MH" }
            ],
            shield: [
                { title: "4th Year", price: 12990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1099, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: { permit: "Goods", price: 3800 },
            kazam: 0,
            incidental: 1800,
            "rto-tape": 1299,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 20000, type: "INV" }
            ],
            "dealer-scheme": { amount: 7000, type: "CN" },
            "accessory-scheme": { amount: 1500, type: "INV" },
            "shield-scheme": { amount: 1000, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 15000, type: "INV" },
                { name: "Loyalty Bonus", amount: 8000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 12000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 700000, upper: 900000, max: 25000, amount: 0, type: "INV" }
        }
    },
    treoYaari: {
        permit: [{ type: "LMM", default: true }],
        receivables: {
            exShowroom: 325000,
            insurance: [{
                permit: "LMM",
                default: true,
                companies: [{
                    insCo: "USGI",
                    default: true,
                    price: [
                        { head: "Basic OD TP", price: 8900, Nature: "M" },
                        { head: "Nil Depreciation", price: 1900, Nature: "M" },
                        { head: "Consumables", price: 500, Nature: "M" },
                        { head: "RTI", price: 1300, Nature: "O" }
                    ]
                }]
            }],
            RTO: { TRC: 800, TAX: [{ permit: "LMM", default: true, amount: 19500 }] },
            accessories: [
                { item: "Welcome Kit", mrp: 589, discount: 0, code: "WK-TY" },
                { item: "Seat Cover", mrp: 2300, discount: 250, code: "SC-TY" },
                { item: "Floor Mat", mrp: 950, discount: 0, code: "FM-TY" }
            ],
            shield: [
                { title: "4th Year", price: 4990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 699, default: true },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 1200,
            "rto-tape": 0,
            fastag: 0,
            COD: 2500,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 10000, type: "INV" }
            ],
            "dealer-scheme": { amount: 3000, type: "CN" },
            "accessory-scheme": { amount: 500, type: "INV" },
            "shield-scheme": { amount: 500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 8000, type: "INV" },
                { name: "Loyalty Bonus", amount: 4000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 6000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 250000, upper: 400000, max: 15000, amount: 0, type: "INV" }
        }
    },
    scorpioN: {
        permit: [{ type: "Private", default: true }],
        receivables: {
            exShowroom: 1425000,
            insurance: [{
                permit: "Private",
                default: true,
                companies: [{
                    insCo: "ICICI Lombard",
                    default: true,
                    price: [
                        { head: "Basic OD TP", price: 26500, Nature: "M" },
                        { head: "Nil Depreciation", price: 5500, Nature: "M" },
                        { head: "Consumables", price: 1250, Nature: "M" },
                        { head: "Engine Protect", price: 4800, Nature: "O" },
                        { head: "RTI", price: 4200, Nature: "O" }
                    ]
                }]
            }],
            RTO: { TRC: 1200, TAX: [{ permit: "Private", default: true, amount: 125000 }] },
            accessories: [
                { item: "Dash Cam", mrp: 4079, discount: 500, code: "DC-SN" },
                { item: "Seat Cover 7Str", mrp: 7190, discount: 800, code: "SC-SN" },
                { item: "Floor Mat Set", mrp: 3452, discount: 400, code: "FM-SN" }
            ],
            shield: [
                { title: "4th Year", price: 19990, default: true },
                { title: "No Shield", price: 0, default: false }
            ],
            rsa: [
                { title: "1 Year", price: 1499, default: true },
                { title: "2 Year", price: 2699, default: false },
                { title: "No RSA", price: 0, default: false }
            ],
            vltd: null,
            kazam: 0,
            incidental: 2500,
            "rto-tape": 1499,
            fastag: 600,
            COD: 0,
            "charger-swapping": [],
            tcs: { limit: 1000000, rate: 1.0 }
        },
        deductibles: {
            "oem-schemes": [
                { key: "cash_scheme_oem", label: "Cash Scheme OEM", amount: 35000, type: "INV" }
            ],
            "dealer-scheme": { amount: 10000, type: "CN" },
            "accessory-scheme": { amount: 2500, type: "INV" },
            "shield-scheme": { amount: 1500, type: "CN" },
            "corp-scheme": [
                { name: "Corporate Discount", amount: 25000, type: "INV" },
                { name: "Loyalty Bonus", amount: 12000, type: "INV" }
            ],
            "exchange-scheme": [
                { name: "Exchange Bonus", amount: 18000, type: "CN1" },
                { name: "Welcome Bonus", amount: 8000, type: "CN1" }
            ],
            "other-cash-discount": { amount: 0, type: "CN", editable: true },
            "special-cash-discount": { enabled: true, lower: 1200000, upper: 1600000, max: 40000, amount: 0, type: "INV" }
        }
    }
};

// ============================================================
// 2. HELPER FUNCTIONS
// ============================================================

function numberToIndianWords(num) {
    num = parseFloat(num) || 0;
    if (num === 0) return 'Zero Rupees Only';

    const a = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
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

    // Split Integer and Decimal (Paise) parts
    let parts = num.toFixed(2).split('.');
    let rupees = parseInt(parts[0], 10);
    let paise = parseInt(parts[1], 10);

    let result = '';

    if (rupees > 0) {
        result += inWords(rupees) + ' Rupees';
    }

    if (paise > 0) {
        if (rupees > 0) result += ' ';
        result += inWords(paise) + ' Paise';
    }

    return result ? result + ' Only' : 'Zero Rupees Only';
}

let currentInsurance = null;
let currentPricing = null;

function loadInsurance(company) {
    let total = 0;
    $("#insurance_covers").empty();

    company.price.forEach(function (item) {
        let option = new Option(
            item.head + " (₹" + item.price + ")",
            item.head,
            item.Nature == "M",
            item.Nature == "M"
        );
        $(option).attr("data-price", item.price);
        if (item.Nature == "M") {
            $(option).prop("disabled", true);
            total += item.price;
        }
        $("#insurance_covers").append(option);
    });

    $("#insurance_covers").trigger("change");
    $("#insurance_amount").val(total);
    updateInsurancePrintText();
}

function loadInsuranceByPermit() {
    let permit = $("#permit").val();
    let enquiry = ENQUIRIES[$("#mock_enquiry_no").val()];
    if (!enquiry) return;
    let pricing = PRICING[enquiry.pricingKey];

    currentInsurance = pricing.receivables.insurance.find(
        x => x.permit === permit
    );

    if (!currentInsurance) {
        currentInsurance = pricing.receivables.insurance[0];
    }

    $("#insurance_company").empty();
    currentInsurance.companies.forEach(function (company) {
        $("#insurance_company").append(
            `<option value="${company.insCo}">${company.insCo}</option>`
        );
    });

    let defaultCompany = currentInsurance.companies.find(x => x.default) || currentInsurance.companies[0];
    $("#insurance_company").val(defaultCompany.insCo);
    loadInsurance(defaultCompany);
}

function updateRegistrationAmount() {
    let permit = $("#permit").val();
    let enquiry = ENQUIRIES[$("#mock_enquiry_no").val()];
    if (!enquiry) return;
    let pricing = PRICING[enquiry.pricingKey];
    let tax = pricing.receivables.RTO.TAX.find(x => x.permit === permit) || pricing.receivables.RTO.TAX[0];
    $('#registration_amount').val((tax.amount + pricing.receivables.RTO.TRC).toFixed(2));
}

function updateInsurancePrintText() {
    var list = [];
    $('#insurance_covers option:selected').each(function () {
        var price = Number($(this).data('price') || 0);
        // Add (M) for mandatory items
        var mandatory = $(this).prop('disabled') ? '' : '';
        list.push($(this).val() + mandatory + ' (₹' + price.toLocaleString('en-IN') + ')');
    });
    $('#insurance_print').text(list.join(', '));
}

function updateAccessoriesPrintText() {
    let list = [];
    $('#accessories option:selected').each(function () {
        let name = $(this).text().trim();
        let price = parseFloat($(this).data('price') || 0);
        list.push(name.replace(/\(.*?\)/, '').trim() + ' (₹' + price.toLocaleString('en-IN') + ')');
    });
    $('#accessories_print').text(list.join(', '));
}

function updateAccessoriesAmount() {
    let total = 0;
    $('#accessories option:selected').each(function () {
        total += parseFloat($(this).data('price')) || 0;
    });
    $('#accessories_amount').val(total.toFixed(2));
    calculateQuotation();
}

// ============================================================
// 3. HIDE/SHOW ROWS BASED ON VALUE
// ============================================================

function toggleRowVisibility() {
    // Price grid rows - hide if value is N/A, empty, 0, or 0.00
    $('.price-grid tbody tr').each(function() {
        let $row = $(this);
        let $input = $row.find('td.cell-amount input').first();
        let value = $input.length ? $input.val() : '';
        
        // Check if value is N/A or empty or 0
        if (value === 'N/A' || value === '' || value === null || value === '0' || value === '0.00') {
            $row.hide();
        } else {
            $row.show();
        }
    });
    
    // Discount grid rows
    $('.discount-grid tbody tr').each(function() {
        let $row = $(this);
        let $input = $row.find('td.cell-amount input').first();
        let value = $input.length ? $input.val() : '';
        
        if (value === 'N/A' || value === '' || value === null || value === '0' || value === '0.00') {
            $row.hide();
        } else {
            $row.show();
        }
    });
}

// ============================================================
// 4. DYNAMIC GROUP A DISCOUNTS RENDERER
// ============================================================

// Group A "Type" cell: only "Cash Scheme OEM" genuinely has 2 valid types
// (INV / CN), so it stays a dropdown. "CSD Discount" and "Fame Subsidy"
// only ever have INV, so for those a dropdown is pointless — render a
// plain readonly field instead. Keeps the same #group_a_type id either way.
function renderGroupAType(key, presetType) {
    const $old = $('#group_a_type');
    let $new;

    if (key === 'cash_scheme_oem') {
        $new = $('<select id="group_a_type"><option value="INV">INV</option><option value="CN">CN</option></select>');
        $new.val(presetType || 'INV');
    } else {
        $new = $('<input type="text" id="group_a_type" readonly>');
        $new.val(presetType || (key ? 'INV' : ''));
    }

    $old.replaceWith($new);
    return $new;
}

function renderGroupADiscounts(pricing) {

    // User agar manually edit kar raha hai to overwrite mat karo
    if ($('#group_a_amount').is(':focus')) return;

    let schemes = pricing.deductibles["oem-schemes"] || [];
    let activeSchemes = schemes.filter(s => Number(s.amount) > 0);

    // Clear hidden fields
    $('#cash_scheme_oem, #csd_discount, #fame_subsidy').val('');
    $('#cash_scheme_oem_type, #csd_discount_type, #fame_subsidy_type').val('');

    if (!activeSchemes.length) return;

    let firstScheme = activeSchemes[0];

    // Set visible values
    $('#group_a_select').val(firstScheme.key);
    renderGroupAType(firstScheme.key, firstScheme.type);
    $('#group_a_amount').val(firstScheme.amount);

    // Hidden values
    $('#' + firstScheme.key).val(firstScheme.amount);
    $('#' + firstScheme.key + '_type').val(firstScheme.type);

    // Additional schemes
    activeSchemes.slice(1).forEach(function (scheme) {
        $('#' + scheme.key).val(scheme.amount);
        $('#' + scheme.key + '_type').val(scheme.type);
    });

    // Trigger after everything is set
    $('#group_a_amount').trigger('change');
}

// ============================================================
// 5. POPULATE ACCESSORIES - MAP MOCK CODES TO ACTUAL VALUES
// ============================================================

function populateAccessories(accessoryCodes) {
    if (!accessoryCodes || accessoryCodes.length === 0) {
        $('#accessories').val([]).trigger('change');
        return;
    }
    
    // Deselect all first
    $('#accessories option').prop('selected', false);
    
    // Select matching options - match by code (part_no) or by item name
    $('#accessories option').each(function() {
        let optionValue = $(this).val();
        let optionText = $(this).text().trim();
        
        // Check if the option value matches any of the codes
        if (accessoryCodes.includes(optionValue)) {
            $(this).prop('selected', true);
            return;
        }
        
        // Also try to match by partial text match (for safety)
        for (let i = 0; i < accessoryCodes.length; i++) {
            if (optionText.includes(accessoryCodes[i]) || accessoryCodes[i].includes(optionText)) {
                $(this).prop('selected', true);
                break;
            }
        }
    });
    
    // Trigger change to update UI
    $('#accessories').trigger('change');
}

// ============================================================
// 6. FETCH MOCK DATA
// ============================================================

$('#btnFetchMock').click(function () {
    let no = $('#mock_enquiry_no').val().trim();

    if (!ENQUIRIES[no]) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Enquiry',
            text: 'Please enter a valid enquiry number (001-013)'
        });
        return;
    }

    let enquiry = ENQUIRIES[no];
    let pricing = PRICING[enquiry.pricingKey];
    currentPricing = pricing;

    // ---- Populate Permit ----
    $("#permit").empty();
    pricing.permit.forEach(function (item) {
        let selected = item.default ? 'selected' : '';
        $("#permit").append(`<option value="${item.type}" ${selected}>${item.type}</option>`);
    });

    // ---- Populate Insurance ----
    loadInsuranceByPermit();

    // ---- Populate Customer Details ----
    $('#customer_name').val(enquiry.customer.name);
    $('#mobile').val(enquiry.customer.mobile);
    $('#customer_name_hidden').val(enquiry.customer.name);
$('#mobile_hidden').val(enquiry.customer.mobile);
    $('#careof').val(enquiry.customer.careOf || '').trigger('change');
    $('#careofname').val(enquiry.customer.careOfName || '');
    $('#enquiry_id').val(enquiry.enquiry_no);
    // $('#enquiry_no_hidden').val(enquiry.enquiry_no);
    $('#enquiry_no_hidden').val(no);

    // ---- Populate Vehicle Details ----
    $('#segment').val(enquiry.vehicle.segment_name);
    $('#model').val(enquiry.vehicle.model_name);
    $('#variant').val(enquiry.vehicle.variant_name);
    $('#color').val(enquiry.vehicle.color_name);
    $('#segment_code').val(enquiry.vehicle.segment_code);
    $('#model_code').val(enquiry.vehicle.model_code);
    $('#variant_code').val(enquiry.vehicle.variant_code);
    $('#color_code').val(enquiry.vehicle.color_code);

    $('#oem_code').val(enquiry.vehicle.oem_code);
    $('#oem_code_hidden').val(enquiry.vehicle.oem_code);
    // ---- Populate Receivables ----
    $('#ex_showroom_price').val(pricing.receivables.exShowroom);
    updateRegistrationAmount();

    // Maxicare
    if (pricing.receivables.maxicare !== undefined && pricing.receivables.maxicare > 0) {
        $('#maxicare').val(pricing.receivables.maxicare).prop('disabled', false);
    } else {
        $('#maxicare').val('N/A').prop('disabled', true);
    }

    // VLTD Device
    if (pricing.receivables.vltd && pricing.receivables.vltd.price > 0) {
        $('#vltd_device').val(pricing.receivables.vltd.price).prop('disabled', false);
    } else {
        $('#vltd_device').val('N/A').prop('disabled', true);
    }

    // Coating
    if (pricing.receivables.coating) {
        let coating = pricing.receivables.coating.find(x => x.default) || pricing.receivables.coating[0];
        $("#coating").val(coating.title);
        $("#coating_price").val(coating.price > 0 ? coating.price : 'N/A');
        $("#coating_price").prop('disabled', coating.price === 0);
    }

    // PPF
    if (pricing.receivables.ppf) {
        let ppf = pricing.receivables.ppf.find(x => x.default) || pricing.receivables.ppf[0];
        $("#ppf").val(ppf.price > 0 ? ppf.price : 'N/A');
        $("#ppf").prop('disabled', ppf.price === 0);
    }

    // RTO Yellow Tape
    if (pricing.receivables["rto-tape"] > 0) {
        $('#rto_yellow_tape').val(pricing.receivables["rto-tape"]).prop('disabled', false);
    } else {
        $('#rto_yellow_tape').val('N/A').prop('disabled', true);
    }

    // Kazam Charging Kit
    if (pricing.receivables.kazam > 0) {
        $('#kazam_charging_kit').val(pricing.receivables.kazam).prop('disabled', false);
    } else {
        $('#kazam_charging_kit').val('N/A').prop('disabled', true);
    }

    // Incidental Charges
    if (pricing.receivables.incidental > 0) {
        $('#incidental_charges').val(pricing.receivables.incidental).prop('disabled', false);
    } else {
        $('#incidental_charges').val('N/A').prop('disabled', true);
    }

    // Shield
    if (pricing.receivables.shield) {
        let shield = pricing.receivables.shield.find(x => x.default) || pricing.receivables.shield[0];
        $('#shield').val(shield.title);
        $('#shield_price').val(shield.price > 0 ? shield.price : 'N/A');
        $('#shield_price').prop('disabled', shield.price === 0);
    }

    // RSA
    if (pricing.receivables.rsa) {
        let rsa = pricing.receivables.rsa.find(x => x.default) || pricing.receivables.rsa[0];
        $('#rsa').val(rsa.title);
        $('#rsa_amount').val(rsa.price > 0 ? rsa.price : 'N/A');
        $('#rsa_amount').prop('disabled', rsa.price === 0);
    }

    // Fastag
    if (pricing.receivables.fastag > 0) {
        $('#fastag').val(pricing.receivables.fastag).prop('disabled', false);
    } else {
        $('#fastag').val('N/A').prop('disabled', true);
    }

    // COD Charges
    if (pricing.receivables.COD > 0) {
        $('#cod_charges').val(pricing.receivables.COD).prop('disabled', false);
    } else {
        $('#cod_charges').val('N/A').prop('disabled', true);
    }

    // Charger Swapping
    if (pricing.receivables["charger-swapping"] && pricing.receivables["charger-swapping"].length > 0) {
        $('#charger_swapping').empty().prop('disabled', false);
        pricing.receivables["charger-swapping"].forEach(function (item) {
            let selected = item.default ? 'selected' : '';
            $('#charger_swapping').append(
                `<option value="${item.title}" data-amount="${item.amount}" ${selected}>${item.title}</option>`
            );
            if (item.default) {
                $('#charger_swapping_amount').val(item.amount > 0 ? item.amount : 'N/A');
                $('#charger_swapping_amount').prop('disabled', item.amount === 0);
            }
        });
        $('#charger_swapping_discount').prop('disabled', false);
        $('#charger_swapping_discount_type').prop('disabled', false);
    } else {
        $('#charger_swapping').val('N/A').prop('disabled', true);
        $('#charger_swapping_amount').val('N/A').prop('disabled', true);
        $('#charger_swapping_discount').val('N/A').prop('disabled', true);
        $('#charger_swapping_discount_type').val('').prop('disabled', true);
    }

    // TCS
    $('#tcs').val('N/A').prop('disabled', true);

    // ---- Populate Accessories ----
    if (pricing.receivables.accessories && pricing.receivables.accessories.length > 0) {
        let accessoryCodes = pricing.receivables.accessories.map(acc => acc.code);
        populateAccessories(accessoryCodes);
    } else {
        $('#accessories').val([]).trigger('change');
    }

    // ---- Populate Dynamic Group A Discounts ----
    renderGroupADiscounts(pricing);

    const groupASelected = $('#group_a_select').val();
const groupAAmount = $('#group_a_amount').val();
const groupAType = $('#group_a_type').val();
if (groupASelected && groupAAmount) {
    $('#' + groupASelected).val(groupAAmount);
    $('#' + groupASelected + '_type').val(groupAType);
}

    // ---- Populate Static Discounts ----
    if (pricing.deductibles["dealer-scheme"]) {
        let val = pricing.deductibles["dealer-scheme"].amount;
        $('#dealer_discount').val(val > 0 ? val : 'N/A');
        $('#dealer_discount_type').val(pricing.deductibles["dealer-scheme"].type);
    }
    if (pricing.deductibles["accessory-scheme"]) {
        let val = pricing.deductibles["accessory-scheme"].amount;
        $('#accessories_discount').val(val > 0 ? val : 'N/A');
        $('#accessories_discount_type').val(pricing.deductibles["accessory-scheme"].type);
    }
    if (pricing.deductibles["shield-scheme"]) {
        let val = pricing.deductibles["shield-scheme"].amount;
        $('#shield_scheme').val(val > 0 ? val : 'N/A');
        $('#shield_scheme_type').val(pricing.deductibles["shield-scheme"].type);
    }
    if (pricing.deductibles["corp-scheme"] && pricing.deductibles["corp-scheme"].length > 0) {
    let corp = pricing.deductibles["corp-scheme"].find(x => x.name === "Corporate Discount") || pricing.deductibles["corp-scheme"][0];
    if (corp && corp.name === "Corporate Discount") {
        $('#group_b_select').val('corporate_discount').trigger('change');
        $('#group_b_type').val(corp.type || 'INV');
        $('#group_b_amount').val(corp.amount).trigger('keyup');
    }

    // Agar corp-scheme mein Loyalty Bonus milta hai, to usse Group C mein set karein
    let loyalty = pricing.deductibles["corp-scheme"].find(x => x.name === "Loyalty Bonus");
    if (loyalty) {
        $('#group_c_select').val('loyalty_bonus').trigger('change');
        $('#group_c_type').val(loyalty.type || 'CN1');
        $('#group_c_amount').val(loyalty.amount).trigger('keyup');
    }
}
    if (pricing.deductibles["exchange-scheme"] && pricing.deductibles["exchange-scheme"].length > 0) {
        let exch = pricing.deductibles["exchange-scheme"][0];
        $('#group_c_select').val(exch.name ? exch.name.toLowerCase().replace(' ', '_') : 'exchange_bonus').trigger('change');
        $('#group_c_type').val(exch.type || 'CN1');
        $('#group_c_amount').val(exch.amount).trigger('keyup');
    }
    if (pricing.deductibles["accessories-spl-discount"]) {
        let val = pricing.deductibles["accessories-spl-discount"].amount;
        $('#accessories_spl_disc').val(val > 0 ? val : 'N/A');
        $('#accessories_spl_disc_type').val(pricing.deductibles["accessories-spl-discount"].type);
    }
    if (pricing.deductibles["coating-spl-discount"]) {
        let val = pricing.deductibles["coating-spl-discount"].amount;
        $('#ceramic_discount').val(val > 0 ? val : 'N/A');
        $('#ceramic_discount_type').val(pricing.deductibles["coating-spl-discount"].type);
    }
    if (pricing.deductibles["ppf-spl-discount"]) {
        let val = pricing.deductibles["ppf-spl-discount"].amount;
        $('#ppf_discount').val(val > 0 ? val : 'N/A');
        $('#ppf_discount_type').val(pricing.deductibles["ppf-spl-discount"].type);
    }
    if (pricing.deductibles["charger-swapping-discount"]) {
        let val = pricing.deductibles["charger-swapping-discount"].amount;
        $('#charger_swapping_discount').val(val > 0 ? val : 'N/A');
        $('#charger_swapping_discount_type').val(pricing.deductibles["charger-swapping-discount"].type);
    }
    if (pricing.deductibles["other-cash-discount"]) {
        let val = pricing.deductibles["other-cash-discount"].amount;
        $('#other_cash_discount').val(val > 0 ? val : 'N/A');
        $('#other_cash_discount_type').val(pricing.deductibles["other-cash-discount"].type);
    }
    if (pricing.deductibles["special-cash-discount"]) {
        let val = pricing.deductibles["special-cash-discount"].amount;
        $('#special_cash_discount').val(val > 0 ? val : 'N/A');
        $('#special_cash_discount_type').val(pricing.deductibles["special-cash-discount"].type);
    }

    // ---- Hide rows with N/A or 0 values ----
    toggleRowVisibility();

    // ---- Final Calculation ----
    calculateQuotation();

    Swal.fire({
        icon: 'success',
        title: 'Enquiry Loaded',
        text: `Enquiry ${no}: ${enquiry.customer.name} - ${enquiry.vehicle.model_name}`
    });
});

// ---- Reset Mock Data ----
$('#btnResetMock').click(function () {
    $('#mock_enquiry_no').val('');
    $('#customer_name').val('');
    $('#mobile').val('');
    $('#careof').val('').trigger('change');
    $('#careofname').val('');
    $('#segment').val('');
    $('#model').val('');
    $('#variant').val('');
    $('#color').val('');
    $('#oem_code').val('');
    $('#oem_code_hidden').val('');
    $('#ex_showroom_price').val('');
    $('#insurance_amount').val('');
    $('#registration_amount').val('');
    $('#accessories_amount').val('0.00');
    $('#maxicare').val('');
    $('#vltd_device').val('');
    $('#coating_price').val('');
    $('#ppf').val('');
    $('#rto_yellow_tape').val('');
    $('#kazam_charging_kit').val('');
    $('#incidental_charges').val('');
    $('#shield_price').val('');
    $('#rsa_amount').val('');
    $('#fastag').val('');
    $('#cod_charges').val('');
    $('#charger_swapping_amount').val('');
    $('#tcs').val('');
    $('#total_receivable').val('');
    $('#total_discount_amount').val('');
    $('#net_receivable_summary').val('');
    $('#fi_total_receivable').val('');
    $('#less_inv_discount').val('');
    $('#finvoice_amount').val('');
    $('#invoiced_discount').val('');
    $('#credit_note_discount').val('');
    $('#total_discount_summary').val('');
    $('#dealer_discount').val('');
    $('#accessories_discount').val('');
    $('#shield_scheme').val('');
    $('#group_b_amount').val('');
    $('#group_c_amount').val('');
    $('#accessories_spl_disc').val('');
    $('#ceramic_discount').val('');
    $('#ppf_discount').val('');
    $('#charger_swapping_discount').val('');
    $('#other_cash_discount').val('');
    $('#special_cash_discount').val('');
    $('#insurance_covers').empty();
    $('#insurance_print').text('');
    $('#accessories_print').text('');
    $('#accessories').val([]).trigger('change');
    $('#permit').empty().append('<option value="">Select Permit</option>');
    $('#insurance_company').empty().append('<option value="">Select Company</option>');
    $('#group_a_dynamic_container').empty();
    
    // Show all rows again
    $('.price-grid tbody tr, .discount-grid tbody tr').show();
    
    calculateQuotation();
});

// ============================================================
// 7. EVENT HANDLERS
// ============================================================

// ---- Permit change ----
$("#permit").on("change", function () {
    loadInsuranceByPermit();
    updateRegistrationAmount();
    calculateQuotation();
    toggleRowVisibility();
});

// ---- Registration Type change ----
$('#registration_type').on('change', function () {
    let hasValue = $(this).val() !== '';
    $('#registration_amount')
        .prop('disabled', !hasValue)
        .val(hasValue ? $('#registration_amount').val() : '');
    calculateQuotation();
    toggleRowVisibility();
});

// ---- Insurance Company change ----
$("#insurance_company").on("change", function () {
    let companyName = $(this).val();
    if (!companyName || !currentInsurance) return;
    let company = currentInsurance.companies.find(x => x.insCo === companyName);
    if (company) {
        loadInsurance(company);
    }
    calculateQuotation();
    toggleRowVisibility();
});

// ---- Insurance Covers change ----
$("#insurance_covers").on("change", function () {
    let total = 0;
    $('#insurance_covers option:selected').each(function () {
        total += Number($(this).data('price') || 0);
    });
    $('#insurance_amount').val(total);
    updateInsurancePrintText();
    calculateQuotation();
});

// ---- Accessories change ----
$('#accessories').on('change', function () {
    updateAccessoriesAmount();
    updateAccessoriesPrintText();
    toggleRowVisibility();
});

// ---- Numeric-only filter ----
$(document).on('input', '.numeric-only', function () {
    let value = $(this).val();
    value = value.replace(/[^\d.]/g, '');
    value = value.replace(/(\..*)\./g, '$1');
    $(this).val(value);
});

// ---- Coating change ----
$('#coating').on('change', function () {
    let value = $(this).val();
    if (value === '' || value === 'No Coating') {
        $('#coating_price').val('N/A').prop('disabled', true);
        $('#ceramic_discount').val('N/A').prop('disabled', true);
        $('#ceramic_discount_type').val('').prop('disabled', true);
    } else {
        $('#coating_price').val('').prop('disabled', false);
        $('#ceramic_discount').val('').prop('disabled', false);
        $('#ceramic_discount_type').prop('disabled', false);
    }
    calculateQuotation();
    toggleRowVisibility();
});

// ---- Shield change ----
$('#shield').on('change', function () {
    let value = $(this).val();
    if (value === '' || value === 'No Shield') {
        $('#shield_price').val('N/A').prop('disabled', true);
    } else {
        $('#shield_price').val('').prop('disabled', false);
    }
    calculateQuotation();
    toggleRowVisibility();
});

// ---- RSA change ----
$('#rsa').on('change', function () {
    let value = $(this).val();
    if (value === '' || value === 'No RSA') {
        $('#rsa_amount').val('N/A').prop('disabled', true);
    } else {
        $('#rsa_amount').val('').prop('disabled', false);
    }
    calculateQuotation();
    toggleRowVisibility();
});

// ---- Charger Swapping change ----
$('#charger_swapping').on('change', function () {
    let selectedOption = $(this).find('option:selected');
    let amount = selectedOption.data('amount');
    if (amount !== undefined && amount > 0) {
        $('#charger_swapping_amount').val(amount).prop('disabled', false);
        $('#charger_swapping_discount').prop('disabled', false);
        $('#charger_swapping_discount_type').prop('disabled', false);
    } else {
        $('#charger_swapping_amount').val('N/A').prop('disabled', true);
        $('#charger_swapping_discount').val('N/A').prop('disabled', true);
        $('#charger_swapping_discount_type').val('').prop('disabled', true);
    }
    calculateQuotation();
    toggleRowVisibility();
});

// ---- Update Coating Discount Label ----
function updateCoatingDiscountLabel() {
    let coating = $('#coating').val();
    let label = 'Coating Special Discount';
    if (coating === 'Ceramic') {
        label = 'Ceramic Coating Special Discount';
    } else if (coating === 'Graphene') {
        label = 'Graphene Coating Special Discount';
    }
    $('#coating_discount_label').text(label);
}
$(document).on('change', '#coating', updateCoatingDiscountLabel);

// ============================================================
// 8. GROUP DISCOUNT SETUP
// ============================================================

// ============================================================
// 8. GROUP DISCOUNT SETUP - FIXED
// ============================================================

function setupGroupDiscount(groupPrefix, fieldNames) {
    function sync() {
        let selected = $('#' + groupPrefix + '_select').val();
        let type = $('#' + groupPrefix + '_type').val();
        let amount = $('#' + groupPrefix + '_amount').val();
        
        console.log('Sync called for:', groupPrefix, 'Selected:', selected, 'Type:', type, 'Amount:', amount);
        
        fieldNames.forEach(function (name) {
            if (name === selected) {
                $('#' + name).val(amount);
                $('#' + name + '_type').val(type);
                console.log('Set hidden field:', name, '=', amount, 'type:', type);
            } else {
                $('#' + name).val('');
                $('#' + name + '_type').val('');
            }
        });
        calculateQuotation();
        toggleRowVisibility();
    }
    
    $(document).on('change', '#' + groupPrefix + '_select', sync);
    $(document).on('change', '#' + groupPrefix + '_type', sync);
    $(document).on('keyup change', '#' + groupPrefix + '_amount', sync);
}

// Setup all groups
setupGroupDiscount('group_a', ['cash_scheme_oem', 'csd_discount', 'fame_subsidy']);
// Group B - Corporate Discount (static label)
$(document).on('keyup change', '#group_b_amount', function() {
    let amount = $(this).val();
    $('#corporate_discount').val(amount);
    $('#corporate_discount_type').val($('#group_b_type').val());
    calculateQuotation();
    toggleRowVisibility();
});
setupGroupDiscount('group_c', ['exchange_bonus', 'green_bonus', 'welcome_bonus', 'loyalty_bonus']);

// ---- Group A Select change (static fallback) ----
$('#group_a_select').on('change', function () {

    const value = $(this).val();

    // Rebuilds #group_a_type as a dropdown (cash_scheme_oem) or a plain
    // readonly field (csd_discount / fame_subsidy — single type only).
    const $type = renderGroupAType(value, null);

    // setupGroupDiscount ko dobara sync karne ke liye
    $type.trigger('change');
    $('#group_a_amount').trigger('change');
});

// ============================================================
// ============================================================
// 9. CALCULATION FUNCTIONS - MATCHING EXCEL EXACTLY
// ============================================================

function num(id) {
    let value = $('#' + id).val();
    if (value === 'N/A' || value === '' || value == null) {
        return 0;
    }
    return parseFloat(value) || 0;
}

// All discount fields with their type fields - matches Excel D25:D41
const DISCOUNT_TYPE_PAIRS = [
    ['cash_scheme_oem', 'cash_scheme_oem_type'],
    ['csd_discount', 'csd_discount_type'],
    ['fame_subsidy', 'fame_subsidy_type'],
    ['dealer_discount', 'dealer_discount_type'],
    ['accessories_discount', 'accessories_discount_type'],
    ['shield_scheme', 'shield_scheme_type'],
    ['corporate_discount', 'corporate_discount_type'],
    ['loyalty_bonus', 'loyalty_bonus_type'],
    ['exchange_bonus', 'exchange_bonus_type'],
    ['green_bonus', 'green_bonus_type'],
    ['welcome_bonus', 'welcome_bonus_type'],
    ['accessories_spl_disc', 'accessories_spl_disc_type'],
    ['ceramic_discount', 'ceramic_discount_type'],
    ['ppf_discount', 'ppf_discount_type'],
    ['charger_swapping_discount', 'charger_swapping_discount_type'],
    ['other_cash_discount', 'other_cash_discount_type'],
    ['special_cash_discount', 'special_cash_discount_type']
];

function calculateDiscountBifurcation() {
    let invoicedDiscount = 0;
    let creditNoteDiscount = 0;

    DISCOUNT_TYPE_PAIRS.forEach(function (pair) {
        let amount = num(pair[0]);
        let type = $('#' + pair[1]).val();
        
        if (type === 'INV') {
            invoicedDiscount += amount;  // ✅ Sirf INV wale count ho rahe hain
        } else if (type && (type === 'CN' || type === 'CN1' || type === 'CN2')) {
            creditNoteDiscount += amount;
        }
    });

    return { invoicedDiscount: invoicedDiscount, creditNoteDiscount: creditNoteDiscount };
}

function calculateQuotation() {
    // 1. Subtotal = SUM of all Additions (Excel D3:D19)
    let subtotal = 
        num('ex_showroom_price') +
        num('insurance_amount') +
        num('registration_amount') +
        num('accessories_amount') +
        num('maxicare') +
        num('vltd_device') +
        num('coating_price') +
        num('ppf') +
        num('rto_yellow_tape') +
        num('kazam_charging_kit') +
        num('incidental_charges') +
        num('shield_price') +
        num('rsa_amount') +
        num('fastag') +
        num('cod_charges') +
        num('charger_swapping_amount');

    $('#subtotal_value').val(subtotal.toFixed(2));

    // 2. Calculate Discount Bifurcation
    let bifurcation = calculateDiscountBifurcation();
    let totalInvoicedDiscount = bifurcation.invoicedDiscount;
    let totalCreditNoteDiscount = bifurcation.creditNoteDiscount;

    // 3. Finvoice Amount (for display only - not used for TCS)
    let finvoiceAmount = subtotal - totalInvoicedDiscount;

    // 4. TCS = IF((Ex-Showroom - INV Discount) >= 1000000, (Ex-Showroom - INV Discount) * 1%, 0)
    let exShowroom = num('ex_showroom_price');
    let tcsBaseAmount = exShowroom - totalInvoicedDiscount;
    let tcs = 0;
    if (tcsBaseAmount >= 1000000) {
        tcs = tcsBaseAmount * 0.01;
        $('#tcs').val(tcs.toFixed(2)).prop('readonly', true).prop('disabled', false);
    } else {
        $('#tcs').val('N/A').prop('readonly', true).prop('disabled', true);
    }

    // 5. Total Receivables = Subtotal + TCS
    let totalReceivable = subtotal + tcs;
    $('#total_receivable').val(totalReceivable.toFixed(2));

    // 6. Total Discount = SUM of ALL discounts (Excel D43)
    let totalDiscount = 
        num('cash_scheme_oem') +
        num('csd_discount') +
        num('fame_subsidy') +
        num('dealer_discount') +
        num('accessories_discount') +
        num('shield_scheme') +
        num('corporate_discount') +
        num('loyalty_bonus') +
        num('exchange_bonus') +
        num('green_bonus') +
        num('welcome_bonus') +
        num('accessories_spl_disc') +
        num('ceramic_discount') +
        num('ppf_discount') +
        num('charger_swapping_discount') +
        num('other_cash_discount') +
        num('special_cash_discount');

    $('#total_discount_amount').val(totalDiscount.toFixed(2));
    $('#total_discount').val(totalDiscount.toFixed(2));

    // 7. On Road Price = Total Receivables - Total Discount
    let netReceivable = totalReceivable - totalDiscount;
    $('#net_receivable_summary').val(netReceivable.toFixed(2));

    $('#net_receivable_words').text(numberToIndianWords(netReceivable));
    

    // ============================================================
    // 8. FINANCIER INVOICE BOX - ✅ FIXED
    // ============================================================
    // 8. Discount Bifurcation by Type - Display in new box
let bifurcationByType = calculateDiscountBifurcationByType();
let invTotal = bifurcationByType.invTotal;
let cnTotal = bifurcationByType.cnTotal;
let cn1Total = bifurcationByType.cn1Total;
let cn2Total = bifurcationByType.cn2Total;

// Hidden fields
$('#invoiced_discount_summary').val(invTotal.toFixed(2));
$('#credit_note_discount_summary').val(cnTotal.toFixed(2));
$('#cn1_discount_summary').val(cn1Total.toFixed(2));
$('#cn2_discount_summary').val(cn2Total.toFixed(2));

// Display in bifurcation box
$('#inv_discount_display').val(invTotal.toFixed(2));
$('#cn_discount_display').val(cnTotal.toFixed(2));
$('#cn1_discount_display').val(cn1Total.toFixed(2));
$('#cn2_discount_display').val(cn2Total.toFixed(2));

// Display total in bifurcation box
let totalBifurcation = invTotal + cnTotal + cn1Total + cn2Total;
$('#total_discount_bifurcation_display').val(totalBifurcation.toFixed(2));
    
    // 10. Toggle row visibility
    toggleRowVisibility();
    
    // Debug - console mein check karo
    console.log('=== QUOTATION CALCULATION ===');
    console.log('Subtotal:', subtotal);
    console.log('TCS:', tcs);
    console.log('Total Receivable (with TCS):', totalReceivable);
    console.log('INV Discount:', totalInvoicedDiscount);
    console.log('CN Discount:', totalCreditNoteDiscount);
    console.log('Finvoice Amount:', finvoiceAmount);
    console.log('Total Discount:', totalDiscount);
    console.log('Net Receivable:', netReceivable);
}

// ---- Event Listeners for recalculation ----
$(document).on('keyup change',
    '#ex_showroom_price, #insurance_amount, #registration_amount, #accessories_amount, ' +
    '#maxicare, #vltd_device, #coating_price, #ppf, #rto_yellow_tape, #kazam_charging_kit, ' +
    '#incidental_charges, #shield_price, #rsa_amount, #fastag, #cod_charges, #charger_swapping_amount, ' +
    '#dealer_discount, #accessories_discount, #ceramic_discount, #ppf_discount, ' +
    '#charger_swapping_discount, #shield_scheme, #accessories_spl_disc, #other_cash_discount, ' +
    '#special_cash_discount',
    function() {
        calculateQuotation();
    }
);

// ---- Also trigger on TYPE dropdown changes ----
$(document).on('change',
    '#dealer_discount_type, #accessories_discount_type, #shield_scheme_type, ' +
    '#accessories_spl_disc_type, #ceramic_discount_type, #ppf_discount_type, ' +
    '#charger_swapping_discount_type, #other_cash_discount_type, #special_cash_discount_type',
    function() {
        calculateQuotation();
    }
);


const STATIC_DISCOUNT_FIELDS = [
    { amount: 'dealer_discount', type: 'dealer_discount_type' },
    { amount: 'accessories_discount', type: 'accessories_discount_type' },
    { amount: 'shield_scheme', type: 'shield_scheme_type' },
    { amount: 'accessories_spl_disc', type: 'accessories_spl_disc_type' },
    { amount: 'ceramic_discount', type: 'ceramic_discount_type' },
    { amount: 'ppf_discount', type: 'ppf_discount_type' },
    { amount: 'charger_swapping_discount', type: 'charger_swapping_discount_type' },
    { amount: 'other_cash_discount', type: 'other_cash_discount_type' },
    { amount: 'special_cash_discount', type: 'special_cash_discount_type' }
];

// Add event listeners for static discount fields
STATIC_DISCOUNT_FIELDS.forEach(function(field) {
    $(document).on('keyup change', '#' + field.amount, function() {
        calculateQuotation();
        toggleRowVisibility();
    });
    $(document).on('change', '#' + field.type, function() {
        calculateQuotation();
        toggleRowVisibility();
    });
});

// ============================================================
// 10. PRINT FUNCTIONS
// ============================================================

let printLabelRestoreList = [];

function prepareOptionLabelsForPrint() {
    printLabelRestoreList = [];
    $('.quotation-grid td.cell-option select').not('#accessories').each(function () {
        let $select = $(this);
        let selectId = $select.attr('id');

        // Registration Dropdowns aur Insurance Covers ko generic loop se exclude karein
        if (selectId === 'insurance_covers' || selectId === 'registration_no_type' || selectId === 'registration_category') {
            return;
        }

        let selectedText = $select.find('option:selected').first().text().trim();
        if (!selectedText || selectedText.toLowerCase() === 'select') {
            return;
        }

        let $label = $select.closest('tr').find('td.cell-label').first();
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
        let $row = $(this);
        let amountCells = $row.find('td.cell-amount');
        let priceValue = amountCells.eq(0).find('input').val();
        let discountValue = amountCells.length > 1 ? amountCells.eq(1).find('input').first().val() : '';

        if (isEmptyGridValue(priceValue) && isEmptyGridValue(discountValue)) {
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
// 11. DOCUMENT READY
// ============================================================

$(document).ready(function () {
    // Initialize Select2 for Accessories
    // Initialize Select2 for Accessories with checkbox
// Initialize Select2 for Accessories with checkbox - NO CHIPS
// Initialize Select2 for Accessories with checkbox - SELECTED ITEMS ON TOP
$('#accessories').select2({
    placeholder: 'Search & select accessories...',
    width: '100%',
    closeOnSelect: false,
    allowClear: false,
    sorter: function(data) {
        // Selected items ko upar lao
        var selected = [];
        var notSelected = [];
        
        data.forEach(function(item) {
            if (item.selected) {
                selected.push(item);
            } else {
                notSelected.push(item);
            }
        });
        
        // Selected items pehle, phir baki
        return selected.concat(notSelected);
    },
    templateResult: function (data) {
        if (data.loading) return data.text;
        
        var $container = $('<span class="select2-checkbox-label"></span>');
        var $checkbox = $('<input type="checkbox" class="select2-checkbox">');
        
        if (data.element && data.element.selected) {
            $checkbox.prop('checked', true);
        }
        
        $container.append($checkbox);
        $container.append($(document.createTextNode(' ' + data.text)));
        
        return $container;
    },
    templateSelection: function (data) {
        return data.text || 'Search & select accessories...';
    }
}).on('select2:select select2:unselect', function (e) {
    setTimeout(function() {
        $('#accessories').trigger('change');
        updateAccessoriesAmount();
        updateAccessoriesPrintText();
        toggleRowVisibility();
    }, 50);
});


// Initialize Select2 for Insurance Covers - SELECTED ITEMS ON TOP
$('#insurance_covers').select2({
    placeholder: 'Search insurance covers...',
    width: '100%',
    closeOnSelect: false,
    allowClear: false,
    sorter: function(data) {
        // Selected items ko upar lao
        var selected = [];
        var notSelected = [];
        
        data.forEach(function(item) {
            if (item.selected) {
                selected.push(item);
            } else {
                notSelected.push(item);
            }
        });
        
        // Mandatory items ko selected mein force karo
        // Selected items pehle, phir baki
        return selected.concat(notSelected);
    },
    templateResult: function (data) {
        if (data.loading) return data.text;
        
        var $container = $('<span class="select2-checkbox-label"></span>');
        var $checkbox = $('<input type="checkbox" class="select2-checkbox">');
        
        if (data.element && data.element.selected) {
            $checkbox.prop('checked', true);
        }
        
        if (data.element && data.element.disabled) {
            $checkbox.prop('disabled', true);
            $container.css('opacity', '0.7');
        }
        
        $container.append($checkbox);
        $container.append($(document.createTextNode(' ' + data.text)));
        
        return $container;
    },
    templateSelection: function (data) {
        return data.text || 'Search insurance covers...';
    }
}).on('select2:select select2:unselect', function (e) {
    if (e.params && e.params.data && e.params.data.disabled) {
        var val = $('#insurance_covers').val() || [];
        if (!val.includes(e.params.data.id)) {
            val.push(e.params.data.id);
            $('#insurance_covers').val(val).trigger('change');
        }
        return;
    }
    setTimeout(function() {
        $('#insurance_covers').trigger('change');
        calculateQuotation();
        toggleRowVisibility();
    }, 50);
});

    // Initial calculations
    calculateQuotation();
    updateCoatingDiscountLabel();
    updateAccessoriesPrintText();
    updateInsurancePrintText();

    // Auto-load first enquiry for demo
    $('#mock_enquiry_no').val('005');
    $('#btnFetchMock').click();
});

function calculateDiscountBifurcationByType() {
    let invTotal = 0;
    let cnTotal = 0;
    let cn1Total = 0;
    let cn2Total = 0;

    DISCOUNT_TYPE_PAIRS.forEach(function (pair) {
        let amount = num(pair[0]);
        let type = $('#' + pair[1]).val();
        
        if (type === 'INV') {
            invTotal += amount;
        } else if (type === 'CN') {
            cnTotal += amount;
        } else if (type === 'CN1') {
            cn1Total += amount;
        } else if (type === 'CN2') {
            cn2Total += amount;
        }
    });

    return { invTotal, cnTotal, cn1Total, cn2Total };
}

// ======================================
// Validation before saving quotation
// Rule:
// Total CN Discount >= Cash OEM Scheme
// when Cash OEM Scheme Type = INV
// ======================================

$('form').on('submit', function (e) {

    let cashOemAmount = 0;
    let cashOemType = '';

    if ($('#group_a_select').val() === 'cash_scheme_oem') {
        cashOemAmount = parseFloat($('#group_a_amount').val()) || 0;
        cashOemType = $('#group_a_type').val();
    } else {
        cashOemAmount = num('cash_scheme_oem');
        cashOemType = $('#cash_scheme_oem_type').val();
    }

    let bifurcation = calculateDiscountBifurcation();
    let totalCNDiscount = bifurcation.creditNoteDiscount;

    if (
        cashOemType === 'INV' &&
        totalCNDiscount < cashOemAmount
    ) {

        e.preventDefault();

        Swal.fire({
            icon: 'error',
            title: 'Cannot Save Quotation',
            html: `
                Total <b>CN Discount</b> should be
                <b>equal to or greater than</b>
                <b>Cash OEM Scheme</b> when
                <b>Cash OEM Scheme Type</b> is <b>INV</b>.

                <br><br>

                <b>Cash OEM Scheme :</b>
                ₹${cashOemAmount.toFixed(2)}

                <br>

                <b>Total CN Discount :</b>
                ₹${totalCNDiscount.toFixed(2)}
            `,
            confirmButtonText: 'Understood'
        });

        return false;
    }

});
// Function to toggle RTO Charges text visibility based on In-House Radio selection
// Function to dynamically append/remove 9th note point based on In-House RTO selection
function toggleRtoChargesNote() {
    let inHouseValue = $('input[name="in_house_rto"]:checked').val();
    let $rtoNoteItem = $('#rto_charges_note_item');

    if (inHouseValue === "1") {
        if ($rtoNoteItem.length === 0) {
            $('#quotation_notes_list').append('<li id="rto_charges_note_item">RTO Charges are subject to the vehicle\'s registration category.</li>');
        }
    } else {
        // Radio No (0) hone par 9th point remove karein
        $rtoNoteItem.remove();
    }
}

// Event listener for In-House RTO Radio Buttons
$(document).on('change', 'input[name="in_house_rto"]', function () {
    toggleRtoChargesNote();
});

// Document Ready par Initial State Check Karne Ke Liye Call Karein
$(document).ready(function () {
    toggleRtoChargesNote();
});

function updateRegistrationPrintText() {
    let typeText = $('#registration_no_type option:selected').text().trim();
    let categoryText = $('#registration_category option:selected').text().trim();
    let inHouseVal = $('input[name="in_house_rto"]:checked').val();
    let inHouseText = (inHouseVal === "1") ? "Yes" : "No";

    let parts = [];

    if (typeText && typeText.toLowerCase() !== 'select type') {
        parts.push('(' + typeText + ')');
    }
    if (categoryText && categoryText.toLowerCase() !== 'select category') {
        parts.push('(' + categoryText + ')');
    }
    parts.push('(In-House: ' + inHouseText + ')');

    $('#registration_details_print').text(parts.join(' '));
}

// Event Listeners
$(document).on('change', '#registration_no_type, #registration_category, input[name="in_house_rto"]', function () {
    updateRegistrationPrintText();
});

$(document).ready(function () {
    updateRegistrationPrintText();
});
</script>
@endpush