@extends(backpack_view('blank'))

@php
use App\Services\OrgService;
@endphp

@section('title', 'Quotation Form')

@push('after_styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
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

    @media print {

        /* Form me select hide */
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

    /* ================= Quotation Grid (Price / Discount) — real table so rows always
       stay aligned across both sides. Each <tr> pairs one price item with one
       discount item (when a discount item exists for that row); if a side has no
       value we simply leave that cell blank instead of collapsing independently,
       so the borders/rows never go out of sync between the two halves. ================= */

    .quotation-box {
        margin-bottom: 15px;
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
    .quotation-grid th:nth-child(1) {
        width: 16%;
    }

    .quotation-grid th:nth-child(2) {
        width: 20%;
    }

    .quotation-grid th:nth-child(3) {
        width: 14%;
    }

    .quotation-grid th:nth-child(4) {
        width: 16.5%;
    }

    .quotation-grid th:nth-child(5) {
        width: 16.5%;
    }

    .quotation-grid th:nth-child(6) {
        width: 17%;
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
    .quotation-summary {
        display: flex;
        font-weight: bold;
        border: solid 1px #000;

    }

    .quotation-summary .total-row-cell {
        background: #f2f2f2;
        border: solid 1px #000;
    }

    .quotation-summary .total-receivable-label {
        flex: 0 0 36%;
    }

    .quotation-summary .total-receivable-amount {
        flex: 0 0 14%;
    }

    .quotation-summary .total-discount-label {
        flex: 0 0 33%;
    }

    .quotation-summary .total-discount-amount {
        flex: 1 1 17%;
    }

    .quotation-summary .onroad-row-cell {
        background: #abb8ca;
        color: #000000;
    }

    .quotation-summary .onroad-label {
        flex: 0 0 83%;
    }

    .quotation-summary .onroad-amount {
        flex: 1 1 17%;
    }

    .quotation-summary input {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 10px;
        font-weight: bold;
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
    .accessories-note-row {
        display: block;
        padding: 4px 5px;
        font-size: 10px;
        font-weight: bold;
        margin-bottom: 8px;
    }

    #accessories_print {
        font-weight: normal;
        white-space: normal;
        word-break: break-word;
    }

    @media print {

        /* OPTION and TYPE columns are always folded into the label / omitted for print */
        .quotation-grid th:nth-child(2),
        .quotation-grid td:nth-child(2),
        .quotation-grid th:nth-child(5),
        .quotation-grid td:nth-child(5) {
            display: none !important;
        }

        /* Hiding 2 of the 6 fixed-width columns above would otherwise leave the
           table only using ~63% of the page width (blank space on the right),
           so the grid box would look "shrunk" compared to the full-width Total /
           On Road Price bars below it. Re-assign the widths of the 4 remaining
           columns (on the <th> cells, since that's what actually drives
           table-layout:fixed column sizing) so they always add up to 100%
           while printing. */
        .quotation-grid th:nth-child(1) {
            width: 25% !important;
        }

        .quotation-grid th:nth-child(3) {
            width: 22% !important;
        }

        .quotation-grid th:nth-child(4) {
            width: 26% !important;
        }

        .quotation-grid th:nth-child(6) {
            width: 27% !important;
        }

        /* A row collapses completely (no gap left behind) only when BOTH its price
           side and discount side have no value */
        .quotation-grid tr.print-hide {
            display: none !important;
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
    }

    .quotation-grid input.numeric-only,
    .quotation-grid input.amount-field,
    .quotation-summary input,
    .financier-discount-grid input {
        text-align: right !important;
    }
</style>

@endpush

@section('content')

<div class="quotation-form">
    <div class="container-fluid">

        <div class="card shadow-sm mb-3">

            <div class="card-body p-3">

                <div class="row align-items-center">

                    <div class="col-md-2 text-center">

                        <img src="{{ asset('images/bikaner_logo.jpg') }}" style="height:75px;">

                    </div>

                    <div class="col-md-10 text-center">

                        <h2 class="mb-1 fw-bold">
                            BIKANER MOTORS PRIVATE LIMITED
                        </h2>

                        <div style="font-size:14px">

                            <strong>Regd. Office :</strong>

                            Sunderi Chhabil Mansion,
                            NH-11,
                            Jaipur Road,
                            P.O. Udasar,
                            Bikaner-334022

                        </div>

                        <div style="font-size:14px">

                            <strong>Branch Office :</strong>

                            6th KM Stone,
                            Ratangarh Road,
                            Churu (Raj.)

                        </div>

                        <h4 class="mt-2 text-uppercase">

                            Vehicle Quotation

                        </h4>

                    </div>

                </div>

            </div>

        </div>

        <form method="POST" action="{{ route('quotation.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="quotation-sheet">

                <div class="form-section">

                    {{-- ================= Customer Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="18%">Enquiry No.</td>
                            <td width="32%">
                                <input type="text" class="form-control border-0 shadow-none"
                                    value="{{ optional($selectedEnquiry)->enquiry_no }}" readonly>

                                <input type="hidden" name="enquiry_no"
                                    value="{{ optional($selectedEnquiry)->enquiry_no }}">
                            </td>

                            <td class="title" width="18%">Customer Name</td>
                            <td width="32%">
                                <input type="text" id="customer_name" class="form-control border-0 shadow-none"
                                    value="{{ optional($selectedEnquiry)->full_name }}" readonly>
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Mobile Number</td>
                            <td>
                                <input type="text" id="mobile" class="form-control border-0 shadow-none"
                                    value="{{ optional($selectedEnquiry)->mobile }}" readonly>
                            </td>


                        </tr>

                    </table>

                    {{-- ================= Vehicle Details ================= --}}

                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="18%">Segment</td>
                            <td width="32%">
                                <input type="text"
                                    value="{{ optional($selectedEnquiry->segment)->name ?? $selectedEnquiry->segment_code }}"
                                    readonly>

                                <input type="hidden" name="segment_code"
                                    value="{{ optional($selectedEnquiry)->segment_code }}">
                            </td>

                            <td class="title" width="18%">Model</td>
                            <td width="32%">
                                <input type="text"
                                    value="{{ optional($selectedEnquiry->model)->name ?? $selectedEnquiry->model_code }}"
                                    readonly>

                                <input type="hidden" name="model_code"
                                    value="{{ optional($selectedEnquiry)->model_code }}">
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Variant</td>
                            <td>

                                <input type="text" value="{{ optional($selectedEnquiry->variant)->display_name
                ?? optional($selectedEnquiry->variant)->custom_name
                ?? $selectedEnquiry->variant_code }}" readonly>


                                <input type="hidden" name="variant_code"
                                    value="{{ optional($selectedEnquiry)->variant_code }}">
                            </td>

                            <td class="title">Color</td>
                            <td>
                                <input type="text"
                                    value="{{ optional($selectedEnquiry->color)->name ?? $selectedEnquiry->color_code }}"
                                    readonly>

                                <input type="hidden" name="color_code"
                                    value="{{ optional($selectedEnquiry)->color_code }}">
                            </td>
                        </tr>

                    </table>

                    <div class="quotation-box">

                        <table class="quotation-grid">
                            <thead>
                                <tr>
                                    <th>PRICE DETAILS</th>
                                    <th>OPTION</th>
                                    <th>AMOUNT</th>
                                    <th>DISCOUNT DETAILS</th>
                                    <th>TYPE</th>
                                    <th>AMOUNT</th>
                                </tr>
                            </thead>

                            <tbody>

                                {{-- Row 1: Ex-Showroom Price | Group A discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Ex-Showroom Price</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input name="ex_showroom_price" id="ex_showroom_price" class="numeric-only">
                                    </td>
                                    <td class="cell-label">
                                        <select id="group_a_select" class="group-select">
                                            <option value="cash_scheme_oem">Cash Scheme OEM</option>
                                            <option value="csd_discount">CSD Discount</option>
                                            <option value="fame_subsidy" id="fame_subsidy_option">Fame Subsidy (LMM)
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
                                        <input type="text" id="group_a_amount" class="numeric-only" placeholder="0.00">
                                        <input type="hidden" id="cash_scheme_oem" name="cash_scheme_oem">
                                        <input type="hidden" id="cash_scheme_oem_type" name="cash_scheme_oem_type">
                                        <input type="hidden" id="csd_discount" name="csd_discount">
                                        <input type="hidden" id="csd_discount_type" name="csd_discount_type">
                                        <input type="hidden" id="fame_subsidy" name="fame_subsidy">
                                        <input type="hidden" id="fame_subsidy_type" name="fame_subsidy_type">
                                    </td>
                                </tr>

                                {{-- Row 2: Insurance | Cash Scheme Dealer --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Insurance</td>
                                    <td class="cell-option">
                                        <select name="policy_type" id="policy_type">
                                            @foreach($insurance_type_map as $key=>$value)
                                            <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="insurance_amount" name="insurance_amount"
                                            class="numeric-only" placeholder="0.00">
                                    </td>
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

                                {{-- Row 3: Registration | Accessories Scheme --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Registration</td>
                                    <td class="cell-option">
                                        <select name="registration_type" id="registration_type">
                                            @foreach($registration_type_map as $key=>$value)
                                            <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="registration_amount" name="registration_amount"
                                            class="numeric-only" placeholder="0.00">
                                    </td>
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

                                {{-- Row 4: Accessories | Shield Scheme --}}
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
                                        <input id="accessories_amount" name="accessories_amount" class="numeric-only"
                                            readonly value="0.00">
                                    </td>
                                    <td class="cell-label">Shield Scheme</td>
                                    <td class="cell-type">
                                        <select id="shield_scheme_type" name="shield_scheme_type">
                                            <option value="INV">INV</option>
                                            <option value="CN">CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="shield_scheme" id="shield_scheme" class="numeric-only"
                                            placeholder="0.00">
                                    </td>
                                </tr>

                                {{-- Row 5: Maxicare | Group B discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Maxicare</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="maxicare" name="maxicare" class="numeric-only">
                                    </td>
                                    <td class="cell-label">
                                        <select id="group_b_select" class="group-select">
                                            <option value="corporate_discount">Corporate Discount</option>
                                            <option value="loyalty_bonus">Loyalty Bonus</option>
                                        </select>
                                    </td>
                                    <td class="cell-type">
                                        <select id="group_b_type">
                                            <option>INV</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="group_b_amount" class="numeric-only" placeholder="0.00">
                                        <input type="hidden" id="corporate_discount" name="corporate_discount">
                                        <input type="hidden" id="corporate_discount_type"
                                            name="corporate_discount_type">
                                        <input type="hidden" id="loyalty_bonus" name="loyalty_bonus">
                                        <input type="hidden" id="loyalty_bonus_type" name="loyalty_bonus_type">
                                    </td>
                                </tr>

                                {{-- Row 6: VLTD Device (GPS) | Group C discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">VLTD Device (GPS)</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="vltd_device" name="vltd_device" class="numeric-only">
                                    </td>
                                    <td class="cell-label">
                                        <select id="group_c_select" class="group-select">
                                            <option value="exchange_bonus">Exchange Bonus</option>
                                            <option value="green_bonus">Green Bonus</option>
                                            <option value="welcome_bonus">Welcome Bonus</option>
                                        </select>
                                    </td>
                                    <td class="cell-type">
                                        <select id="group_c_type">
                                            <option>CN1</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="group_c_amount" class="numeric-only" placeholder="0.00">
                                        <input type="hidden" id="exchange_bonus" name="exchange_bonus">
                                        <input type="hidden" id="exchange_bonus_type" name="exchange_bonus_type">
                                        <input type="hidden" id="green_bonus" name="green_bonus">
                                        <input type="hidden" id="green_bonus_type" name="green_bonus_type">
                                        <input type="hidden" id="welcome_bonus" name="welcome_bonus">
                                        <input type="hidden" id="welcome_bonus_type" name="welcome_bonus_type">
                                    </td>
                                </tr>

                                {{-- Row 7: Coating | Accessories Spl Disc --}}
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
                                    <td class="cell-label">Accessories Spl Disc</td>
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

                                {{-- Row 8: PPF | Coating Spl Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">PPF</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="ppf" name="ppf" class="numeric-only">
                                    </td>
                                    <td class="cell-label" id="coating_discount_label">
                                        Coating Spl Discount
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

                                {{-- Row 9: RTO Yellow Tape | PPF Spl Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">RTO Yellow Tape</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="rto_yellow_tape" name="rto_yellow_tape" class="numeric-only">
                                    </td>
                                    <td class="cell-label">PPF Spl Discount</td>
                                    <td class="cell-type">
                                        <select id="ppf_discount_type" name="ppf_discount_type">
                                            <option value="INV">INV</option>
                                            <option value="CN">CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="ppf_discount" id="ppf_discount" class="numeric-only"
                                            placeholder="0.00">
                                    </td>
                                </tr>

                                {{-- Row 10: Kazam Charging Kit | Charger Swapping Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Kazam Charging Kit</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="kazam_charging_kit" name="kazam_charging_kit" class="numeric-only">
                                    </td>
                                    <td class="cell-label" id="charger_discount_title">Charger Swapping Discount</td>
                                    <td class="cell-type">
                                        <select id="charger_swapping_discount_type"
                                            name="charger_swapping_discount_type" disabled>
                                            <option value="CN2">CN2</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount" id="charger_discount_cell">
                                        <input type="text" id="charger_swapping_discount"
                                            name="charger_swapping_discount" class="numeric-only" placeholder="0.00">
                                    </td>
                                </tr>

                                {{-- Row 11: Incidental Charges | Other Cash Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Incidental Charges</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="incidental_charges" name="incidental_charges" class="numeric-only">
                                    </td>
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

                                {{-- Row 12: Shield | Special Cash Discount --}}
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
                                    <td class="cell-label">Special Cash Discount</td>
                                    <td class="cell-type">
                                        <select id="special_cash_discount_type" name="special_cash_discount_type">
                                            <option value="INV">INV</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="special_cash_discount" id="special_cash_discount"
                                            class="numeric-only" placeholder="0.00">
                                    </td>
                                </tr>

                                {{-- Row 13: RSA | (no discount item — left blank, still aligned) --}}
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
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                                {{-- Row 14: Fastag --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Fastag</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="fastag" name="fastag" class="numeric-only">
                                    </td>
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>



                                {{-- Row 16: COD Charges --}}
                                <tr class="grid-row">
                                    <td class="cell-label">COD Charges</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="cod_charges" name="cod_charges" class="numeric-only">
                                    </td>
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                                {{-- Row 18: Charger Swapping --}}
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
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                                {{-- Row 17: TCS --}}
                                <tr class="grid-row">
                                    <td class="cell-label">TCS @1%</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="tcs" name="tcs" class="numeric-only" readonly>
                                    </td>
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                            </tbody>
                        </table>

                        <div class="quotation-summary">
                            <div class="total-row-cell total-receivable-label">TOTAL RECEIVABLE</div>
                            <div class="total-row-cell total-receivable-amount">
                                <input id="total_receivable" name="total_receivable" readonly>
                            </div>
                            <div class="total-row-cell total-discount-label">TOTAL DISCOUNT</div>
                            <div class="total-row-cell total-discount-amount">
                                <input id="total_discount_amount" readonly>
                                <input type="hidden" id="total_discount" name="total_discount">
                            </div>
                        </div>

                        <div class="quotation-summary">
                            <div class="onroad-row-cell onroad-label">ON ROAD PRICE</div>
                            <div class="onroad-row-cell onroad-amount">
                                <input id="net_receivable_summary" name="net_receivable_summary" readonly>
                            </div>
                        </div>

                    </div>

                    {{-- ================= Financier Invoice / Discount Bifurcation (hidden on print) =================
                    --}}
                    <div class="financier-discount-grid">

                        <div class="fd-header">FINANCIER INVOICE</div>
                        <div class="fd-header">DISCOUNT BIFURCATION</div>

                        <div class="fd-label">Total Receivable</div>
                        <div>
                            <input id="fi_total_receivable" name="fi_total_receivable" readonly>
                        </div>
                        <div class="fd-label">Invoiced Discount</div>
                        <div>
                            <input id="invoiced_discount" name="invoiced_discount" readonly>
                        </div>

                        <div class="fd-label">Less INV Discount</div>
                        <div>
                            <input id="less_inv_discount" name="less_inv_discount" readonly>
                        </div>
                        <div class="fd-label">Credit Note Discount</div>
                        <div>
                            <input id="credit_note_discount" name="credit_note_discount" readonly>
                        </div>

                        <div class="fd-label fd-bold">Finvoice Amount</div>
                        <div class="fd-bold">
                            <input id="finvoice_amount" name="finvoice_amount" readonly>
                        </div>
                        <div class="fd-label fd-bold">Total Discount</div>
                        <div class="fd-bold">
                            <input id="total_discount_summary" name="total_discount_summary" readonly>
                        </div>

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

                                <p style="
                                font-size:7px;
                                font-weight:bold;
                                line-height:1.3;
                                text-align:justify;
                                margin:0;
                                ">

                                    <b>1.</b> Vehicle shall be delivered only against payment.
                                    <b>2.</b> Interest shall be charged @ 24% P.A. in case of payments delayed over
                                    three
                                    days.
                                    <b>3.</b> No Interest shall be payable on Booking Amount.
                                    <b>4.</b> Price & Scheme of the vehicle is applicable as on the date of delivery.
                                    Price
                                    & Scheme are subjected to change without any prior notice.
                                    <b>5.</b> Self attested coloured copy of original documents is required for any
                                    claim.
                                    Claims will be rejected in absence of original documents.

                                </p>



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
{{--
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"> --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>
    $(document).on('input', '.numeric-only', function () {

    let value = $(this).val();

    // Allow only digits and one decimal
    value = value.replace(/[^\d.]/g, '');
    value = value.replace(/(\..*)\./g, '$1');

    $(this).val(value);

});
    function updateAccessoriesAmount() {

    let total = 0;

    $('#accessories option:selected').each(function () {

        total += parseFloat($(this).data('price')) || 0;

    });

    $('#accessories_amount').val(total.toFixed(2));

    calculateQuotation();

}

function updateAccessoriesPrintText() {

    let list = [];

    $('#accessories option:selected').each(function () {

        let name = $(this).text().trim();
        let price = parseFloat($(this).data('price') || 0);

        list.push(
            name.replace(/\(.*?\)/,'').trim() +
            ' (₹' + price.toLocaleString('en-IN') + ')'
        );

    });

    $('#accessories_print').text(list.join(', '));

    // Keep Select2 height fixed
    $('.select2-search__field')
        .attr('placeholder', list.length + ' Accessories Selected');
}

$('#accessories').on(
    'change',
    function () {
        updateAccessoriesAmount();
        updateAccessoriesPrintText();
    }
);

$(document).ready(function () {
    updateAccessoriesPrintText();
});

$(document).ready(function () {

    updateAccessoriesAmount();

});





// Registration amount stays locked until a Registration option is selected




// ================= Unified "pick one of N" discount groups =================
// Each group has a visible select (which discount), a visible type select (INV/CN/CN1/CN2)
// and a visible amount input. These sync into the real hidden fields that
// calculateQuotation() / the backend already understand, so nothing else has to change.
function setupGroupDiscount(groupPrefix, fieldNames) {

    function sync() {

        let selected = $('#' + groupPrefix + '_select').val();
        let type = $('#' + groupPrefix + '_type').val();
        let amount = $('#' + groupPrefix + '_amount').val();

        fieldNames.forEach(function (name) {

            if (name === selected) {
                $('#' + name).val(amount);
                $('#' + name + '_type').val(type);
            } else {
                $('#' + name).val('');
                $('#' + name + '_type').val('');
            }

        });

        calculateQuotation();
    }

    $(document).on('change', '#' + groupPrefix + '_select, #' + groupPrefix + '_type', sync);
    $(document).on('keyup change', '#' + groupPrefix + '_amount', sync);
}

setupGroupDiscount('group_a', ['cash_scheme_oem', 'csd_discount', 'fame_subsidy']);
setupGroupDiscount('group_b', ['corporate_discount', 'loyalty_bonus']);
setupGroupDiscount('group_c', ['exchange_bonus', 'green_bonus', 'welcome_bonus']);

function num(id) {

    let value = $('#' + id).val();

    if (value === 'N/A' || value === '' || value == null) {
        return 0;
    }

    return parseFloat(value) || 0;

}

// Discount fields paired with their Type select — used for the Financier Invoice /
// Discount Bifurcation boxes (INV type = Invoiced Discount, CN/CN1/CN2 = Credit Note Discount)
const DISCOUNT_TYPE_PAIRS = [
    ['cash_scheme_oem', 'cash_scheme_oem_type'],
    ['csd_discount', 'csd_discount_type'],
    ['fame_subsidy', 'fame_subsidy_type'],
    ['ppf_discount', 'ppf_discount_type'],
    ['dealer_discount', 'dealer_discount_type'],
    ['corporate_discount', 'corporate_discount_type'],
    ['loyalty_bonus', 'loyalty_bonus_type'],
    ['accessories_discount', 'accessories_discount_type'],
    ['ceramic_discount', 'ceramic_discount_type'],
    ['exchange_bonus', 'exchange_bonus_type'],
    ['green_bonus', 'green_bonus_type'],
    ['welcome_bonus', 'welcome_bonus_type'],
    ['charger_swapping_discount', 'charger_swapping_discount_type'],
    ['shield_scheme', 'shield_scheme_type'],
    ['accessories_spl_disc', 'accessories_spl_disc_type'],
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
            invoicedDiscount += amount;
        } else if (type === 'CN' || type === 'CN1' || type === 'CN2') {
            creditNoteDiscount += amount;
        }

    });

    return { invoicedDiscount: invoicedDiscount, creditNoteDiscount: creditNoteDiscount };
}

function calculateQuotation() {

    // Total Receivable
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
    
        // Financier Invoice / Discount Bifurcation must be computed BEFORE TCS,
    // because TCS is based on the Finvoice Amount (subtotal - Invoiced Discount),
    // not on the raw subtotal — matching the Excel formula chain:
    //   B24 (Total Receivable, Financier box) = subtotal (no TCS)
    //   B25 (Less INV Discount)              = SUMIF(type,"INV")
    //   B26 (Finvoice Amount)                = B24 - B25
    //   D20 (TCS)                            = IF(B26 >= 1000000, B26 * 1%, 0)
    //   D21 (Total Receivables)              = subtotal + TCS
    let bifurcation = calculateDiscountBifurcation();
    let finvoiceAmount = subtotal - bifurcation.invoicedDiscount;

    let tcs = 0;

        if (finvoiceAmount >= 1000000) {

            tcs = finvoiceAmount * 0.01;

            $('#tcs')
                .val(tcs.toFixed(2))
                .prop('readonly', true)
                .prop('disabled', false);

        } else {

            $('#tcs')
                .val('N/A')
                .prop('readonly', true)
                .prop('disabled', true);
        }
    let totalReceivable = subtotal + tcs;


    $('#total_receivable').val(totalReceivable.toFixed(2));

    // Total Discount
    let totalDiscount =
        num('cash_scheme_oem') +
        num('fame_subsidy') +
        num('exchange_bonus') +
        num('corporate_discount') +
        num('accessories_discount') +
        num('ceramic_discount') +
        num('ppf_discount') +
        num('dealer_discount') +
        num('charger_swapping_discount') +
        num('csd_discount') +
        num('shield_scheme') +
        num('loyalty_bonus') +
        num('green_bonus') +
        num('welcome_bonus') +
        num('accessories_spl_disc') +
        num('other_cash_discount') +
        num('special_cash_discount');

    let discount = totalDiscount.toFixed(2);

$('#total_discount_amount').val(discount);
$('#total_discount').val(discount);


    // Net Receivable
    let netReceivable = totalReceivable - totalDiscount;

    $('#net_receivable_summary').val(netReceivable.toFixed(2));

    // Financier Invoice box — Total Receivable here is the subtotal WITHOUT TCS
    // (matches Excel B24 = M12, not D21)
    $('#fi_total_receivable').val(subtotal.toFixed(2));
    $('#less_inv_discount').val(bifurcation.invoicedDiscount.toFixed(2));
    $('#finvoice_amount').val(finvoiceAmount.toFixed(2));

    $('#invoiced_discount').val(bifurcation.invoicedDiscount.toFixed(2));
    $('#credit_note_discount').val(bifurcation.creditNoteDiscount.toFixed(2));
    $('#total_discount_summary').val((bifurcation.invoicedDiscount + bifurcation.creditNoteDiscount).toFixed(2));

}

// Recalculate whenever any input changes
$(document).on(
    'keyup change',
    '#ex_showroom_price,' +
    '#accessories_amount,' +
    '#maxicare,' +
    '#vltd_device,' +
    '#coating_price,' +
    '#ppf,' +
    '#rto_yellow_tape,' +
    '#kazam_charging_kit,' +
    '#incidental_charges,' +
    '#shield_price,' +
    '#rsa_amount,' +
    '#fastag,' +
    '#cod_charges,' +
    '#charger_swapping_amount,' +
    '#tcs,' +
    '#cash_scheme_oem,' +
    '#cash_scheme_oem_type,' +
    '#fame_subsidy,' +
    '#fame_subsidy_type,' +
    '#exchange_bonus,' +
    '#exchange_bonus_type,' +
    '#corporate_discount,' +
    '#corporate_discount_type,' +
    '#accessories_discount,' +
    '#accessories_discount_type,' +
    '#ceramic_discount,' +
    '#ceramic_discount_type,' +
    '#ppf_discount,' +
    '#ppf_discount_type,' +
    '#dealer_discount,' +
    '#dealer_discount_type,' +
    '#charger_swapping_discount,' +
    '#charger_swapping_discount_type,' +
    '#csd_discount,' +
    '#csd_discount_type,' +
    '#shield_scheme,' +
    '#shield_scheme_type,' +
    '#loyalty_bonus,' +
    '#loyalty_bonus_type,' +
    '#green_bonus,' +
    '#green_bonus_type,' +
    '#welcome_bonus,' +
    '#welcome_bonus_type,' +
    '#accessories_spl_disc,' +
    '#accessories_spl_disc_type,' +
    '#other_cash_discount,' +
    '#other_cash_discount_type,' +
    '#special_cash_discount,' +
    '#special_cash_discount_type,' +
    '#registration_amount,' +
    '#insurance_amount',
    calculateQuotation
);

// Accessories selection change
$('#accessories').on('change', function () {
    updateAccessoriesAmount();
    calculateQuotation();
});

// Initial calculation
$(document).ready(function () {
    calculateQuotation();
});
$(document).ready(function () {

    $('#accessories').select2({
    placeholder: 'Select Accessories',
    width: '100%',
    closeOnSelect: false
}).on('change', function () {

    let count = $(this).find('option:selected').length;

    $('.select2-search__field')
        .attr('placeholder', count + ' Accessories Selected');
});

});

// Segment -> Model



// Model -> Variant





function toggleLMMFields() {

    let isLMM = "{{ optional($selectedEnquiry)->segment_code }}" === "LMM";

    const fields = [
        '#kazam_charging_kit',
        '#incidental_charges'
    ];

    fields.forEach(function (field) {

        $(field).prop('disabled', !isLMM);

        if (!isLMM) {
            $(field).val('N/A');
        } else {
            $(field).val('');
        }

    });

    $('#charger_swapping_discount_type')
    .prop('disabled', false)
    .empty()
    .append('<option value="CN2">CN2</option>')
    .val('CN2');

    // Fame Subsidy (LMM) is only available as an option in Group A when segment is LMM
    $('#fame_subsidy_option').prop('disabled', !isLMM);

    if (!isLMM && $('#group_a_select').val() === 'fame_subsidy') {
        $('#group_a_select').val('').trigger('change');
    }

    // Charger Swapping Amount
    if (!isLMM) {

        $('#charger_swapping')
            .val('N/A')
            .prop('disabled', true);

        $('#charger_swapping_amount')
            .val('N/A')
            .prop('disabled', true);

        $('#charger_swapping_discount')
            .val('N/A')
            .prop('disabled', true);

        $('#charger_swapping_discount_type')
            .val('')
            .prop('disabled', true);

    } else {

        $('#charger_swapping')
            .prop('disabled', false);

        $('#charger_swapping_amount')
            .prop('disabled', false);

        $('#charger_swapping_discount')
            .prop('disabled', false);

        $('#charger_swapping_discount_type')
            .prop('disabled', false);

    }

    calculateQuotation();
}
$(document).ready(function () {

    toggleLMMFields();

});
// Keep track of PRICE DETAILS / DISCOUNT DETAILS labels we temporarily change,
// so we can restore them after print
let printLabelRestoreList = [];

function prepareOptionLabelsForPrint() {

    printLabelRestoreList = [];

    // Merge the selected OPTION value into the PRICE DETAILS label,
    // e.g. "Insurance" + "Standard" => "Insurance(Standard)"
    // Accessories is a multi-select and is shown separately above the Note box, so skip it here.
    $('.quotation-grid td.cell-option select').not('#accessories').each(function () {

        let $select = $(this);
        let selectedText = $select.find('option:selected').first().text().trim();

        if (!selectedText || selectedText.toLowerCase() === 'select') {
            return;
        }

        let $label = $select.closest('tr').find('td.cell-label').first();

        printLabelRestoreList.push({
            el: $label,
            html: $label.html()
        });

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

    return (
        value === '' ||
        value === '0' ||
        value === '0.00' ||
        value === 'N/A'
    );
}

function prepareItemVisibilityForPrint() {

    // Each row has 2 "cell-amount" cells: [0] = price amount, [1] = discount amount
    // (discount amount may not exist for rows that have no paired discount item).
    // The row is hidden only when BOTH sides are empty — this way price and
    // discount stay perfectly row-aligned; we never hide just one half of a row.
    $('.quotation-grid tbody tr').each(function () {

        let $row = $(this);
        let amountCells = $row.find('td.cell-amount');

        let priceValue = amountCells.eq(0).find('input').val();
        let discountValue = amountCells.length > 1
            ? amountCells.eq(1).find('input').first().val()
            : '';

        $row.find('td.cell-amount input').each(function () {

            if ($(this).val() === 'N/A') {

                $(this).closest('tr').addClass('print-hide');

            }

        });    

        if (isEmptyGridValue(priceValue) && isEmptyGridValue(discountValue)) {
            $row.addClass('print-hide');
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
$('#policy_type').on('change', function () {

    let hasValue = $(this).val() !== '';

    $('#insurance_amount')
        .prop('disabled', !hasValue)
        .val(hasValue ? $('#insurance_amount').val() : '');

    calculateQuotation();
});

$('#registration_type').on('change', function () {

    let hasValue = $(this).val() !== '';

    $('#registration_amount')
        .prop('disabled', !hasValue)
        .val(hasValue ? $('#registration_amount').val() : '');

    calculateQuotation();

});

$('#coating').on('change', function () {

    let value = $(this).val();

    if (value === '') {

        $('#coating_price').val('').prop('disabled', true);

        $('#ceramic_discount')
            .val('')
            .prop('disabled', true);

        $('#ceramic_discount_type')
            .val('')
            .prop('disabled', true);
    }
    else if (value === 'No Coating') {

        $('#coating_price')
            .val('N/A')
            .prop('disabled', true);

        $('#ceramic_discount')
            .val('N/A')
            .prop('disabled', true);

        $('#ceramic_discount_type')
            .val('')
            .prop('disabled', true);
    }
    else {

        $('#coating_price')
            .val('')
            .prop('disabled', false);

        $('#ceramic_discount')
            .val('')
            .prop('disabled', false);

        $('#ceramic_discount_type')
            .prop('disabled', false);
    }

    calculateQuotation();
});

$('#shield').on('change', function () {

    let value = $(this).val();

    if (value === '') {
        $('#shield_price').val('').prop('disabled', true);
    }
    else if (value === 'No Shield') {
        $('#shield_price')
            .val('N/A')
            .prop('disabled', true);
    }
    else {
        $('#shield_price')
            .val('')
            .prop('disabled', false);
    }

    calculateQuotation();
});

$('#rsa').on('change', function () {

    let value = $(this).val();

    if (value === '') {
        $('#rsa_amount').val('').prop('disabled', true);
    }
    else if (value === 'No RSA') {
        $('#rsa_amount')
            .val('N/A')
            .prop('disabled', true);
    }
    else {
        $('#rsa_amount')
            .val('')
            .prop('disabled', false);
    }

    calculateQuotation();
});

$('#charger_swapping').on('change', function () {

    let value = $(this).val();

    if (value === '') {

        $('#charger_swapping_amount')
            .val('')
            .prop('disabled', true);

        $('#charger_swapping_discount')
            .val('')
            .prop('disabled', true);

        $('#charger_swapping_discount_type')
            .val('')
            .prop('disabled', true);

    }
    else if (value === 'N/A') {

        $('#charger_swapping_amount')
            .val('N/A')
            .prop('disabled', true);

        $('#charger_swapping_discount')
            .val('N/A')
            .prop('disabled', true);

        $('#charger_swapping_discount_type')
            .val('')
            .prop('disabled', true);

    }
    else {

    $('#charger_swapping_amount')
        .val('')
        .prop('disabled', false);

    $('#charger_swapping_discount')
        .val('')
        .prop('disabled', false);

    $('#charger_swapping_discount_type')
        .prop('disabled', false)
        .empty()
        .append('<option value="CN2">CN2</option>')
        .val('CN2');
}

    calculateQuotation();

});

    $(document).ready(function () {

        $('#policy_type').trigger('change');
        $('#registration_type').trigger('change');
        $('#coating').trigger('change');
        $('#shield').trigger('change');
        $('#rsa').trigger('change');
        $('#charger_swapping').trigger('change');
    });
    $('#group_a_select').on('change', function () {

    let value = $(this).val();
    let $type = $('#group_a_type');

    $type.empty();

    switch (value) {

        case 'cash_scheme_oem':
            $type.append('<option value="INV">INV</option>');
            $type.append('<option value="CN">CN</option>');
            break;

        case 'csd_discount':
            $type.append('<option value="INV">INV</option>');
            break;

        case 'fame_subsidy':
            $type.append('<option value="INV">INV</option>');
            break;
    }

    // Trigger sync so hidden field gets updated
    $type.trigger('change');
});

$(document).ready(function () {
    $('#group_a_select').trigger('change');
});

function updateCoatingDiscountLabel() {

    let coating = $('#coating').val();

    let label = 'Coating Spl Discount';

    if (coating === 'Ceramic') {
        label = 'Ceramic Coating Spl Discount';
    }
    else if (coating === 'Graphene') {
        label = 'Graphene Coating Spl Discount';
    }

    $('#coating_discount_label').text(label);
}
$(document).on('change', '#coating', function () {

    updateCoatingDiscountLabel();

});

$(document).ready(function () {

    updateCoatingDiscountLabel();

});

function toggleVltdField() {

    let segment = ($('input[name="segment_code"]').val() || '').trim().toUpperCase();

    if (segment === 'CV') {

        // Commercial Vehicle
        $('#vltd_device')
            .val('')
            .prop('readonly', false)
            .prop('disabled', false);

        $('#vltd_device').closest('tr').removeClass('print-hide');

    } else {

        // All other segments
        $('#vltd_device')
            .val('N/A')
            .prop('readonly', true)
            .prop('disabled', true);

        $('#vltd_device').closest('tr').addClass('print-hide');
    }

    calculateQuotation();
}

$(document).ready(function () {
    toggleVltdField();
});

$('form').on('submit', function (e) {

    let cashOemAmount = num('cash_scheme_oem');
    let cashOemType = $('#cash_scheme_oem_type').val();

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
            text: 'Total CN Discount should be equal to or greater than Cash OEM Scheme when Cash OEM Scheme Type is INV.'
        });

        return false;
    }

});



</script>
@endpush