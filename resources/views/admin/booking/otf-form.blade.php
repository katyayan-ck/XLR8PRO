@extends(backpack_view('blank'))

@php
$chassisImage = $booking->getFirstMediaUrl('chassis_image');
@endphp

@php
use App\Services\OrgService;
@endphp

@section('title', 'Transaction Sheet')

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

    .bill-table input,
    .bill-table textarea {
        width: 100%;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        padding: 2px;
        box-sizing: border-box;
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
        display: none;
        padding: 2px 5px;
        font-size: 9px;
        font-weight: bold;
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

                    {{-- ================= Registration Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="12%">GST Number</td>
                            <td width="13%">
                                <input type="text" name="gstn" id="gstn" value="{{ old('gstn', $booking->gstn) }}">
                            </td>

                            <td class="title" width="12%">Registration Type</td>
                            <td width="13%">
                                <select name="registration_no_type" id="registration_no_type">
                                    <option value="">Select Registration Type</option>

                                    @foreach($reg_no_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('registration_no_type', $rto?->rgn_no_type) ==
                                        $key ? 'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="title" width="12%">Registration Category</td>
                            <td width="13%">
                                <select name="registration_category" id="registration_category">
                                    <option value="">Select Registration Category</option>

                                    <option value="Exempted" {{ old('registration_category', $rto?->
                                        registration_category) == 'Exempted' ? 'selected' : '' }}>
                                        Exempted
                                    </option>

                                    <option value="Standard" {{ old('registration_category', $rto?->
                                        registration_category) == 'Standard' ? 'selected' : '' }}>
                                        Standard
                                    </option>
                                </select>
                            </td>

                            <td class="title" width="12%">Permit</td>
                            <td width="13%">
                                <select name="permit" id="permit">
                                    <option value="">Select Permit</option>

                                    @foreach($permit_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('permit', $rto?->permit) == $key ? 'selected' : ''
                                        }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Sales Consultant / DMS Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="12%">SC Name</td>
                            <td width="13%">
                                <select name="sale_consultant" id="saleconsultant">
                                    <option value="">Select Sales Consultant</option>

                                    @foreach($salesconsultants as $consultant)
                                    <option value="{{ $consultant['person_code'] }}" {{ $booking->sale_consultant ==
                                        $consultant['person_code'] ? 'selected' : '' }}>
                                        {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="title" width="12%">SC Mile ID</td>
                            <td width="13%">
                                <input type="text" id="sc_mile_id" readonly>
                            </td>

                            <td class="title" width="12%">SC Branch</td>
                            <td width="13%">
                                <input type="text" id="sc_branch" readonly>
                            </td>

                            <td class="title" width="12%">SC Location</td>
                            <td width="13%">
                                <input type="text" id="sc_location" readonly>
                            </td>
                        </tr>

                        <tr>
                            <td class="title" width="12%">DMS Enquiry Number</td>
                            <td width="13%">
                                <input type="text" name="dms_no" id="dms_no"
                                    value="{{ old('dms_no', $booking->dms_no) }}">
                            </td>

                            <td class="title" width="12%">DMS OTF Number</td>
                            <td width="13%">
                                <input type="text" name="dms_otf" id="dms_otf"
                                    value="{{ old('dms_otf', $booking->dms_otf) }}">
                            </td>

                            <td class="title" width="12%">Xcler8 Booking ID</td>
                            <td width="13%">
                                <input type="text" value="{{ $booking->id }}" readonly>
                            </td>

                            <td class="title" width="12%">VOTF Number</td>
                            <td width="13%">
                                <input type="text" name="votf_no" id="votf_no" value="{{ old('votf_no') }}">
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Customer Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="12%">Customer Name</td>
                            <td width="13%">
                                <input type="text" name="customer_name" id="customer_name" value="{{ $booking->name }}"
                                    readonly>
                            </td>

                            <td class="title" width="12%">Customer Category</td>
                            <td width="13%">
                                <select name="b_cat" id="b_cat">
                                    <option value="Individual" {{ old('b_cat', $booking->b_cat) == 'Individual' ?
                                        'selected' : '' }}>
                                        Individual
                                    </option>
                                    <option value="CSD-CPC" {{ old('b_cat', $booking->b_cat) == 'CSD-CPC' ? 'selected' :
                                        '' }}>
                                        CSD-CPC
                                    </option>
                                    <option value="Corporate" {{ old('b_cat', $booking->b_cat) == 'Corporate' ?
                                        'selected' : '' }}>
                                        Corporate
                                    </option>
                                </select>
                            </td>

                            <td class="title" width="12%">Customer Tehsil</td>
                            <td width="13%">
                                <input type="text" name="customer_tehsil" id="customer_tehsil"
                                    value="{{ old('customer_tehsil') }}">
                            </td>

                            <td class="title" width="12%">Customer District</td>
                            <td width="13%">
                                <input type="text" name="customer_district" id="customer_district"
                                    value="{{ old('customer_district') }}">
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Pincode</td>
                            <td>
                                <input type="text" name="pincode" id="pincode"
                                    value="{{ old('pincode', $booking->pincode) }}" maxlength="6" pattern="[0-9]{6}"
                                    inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,6);">
                            </td>

                            <td class="title">Customer Contact Number</td>
                            <td>
                                <input type="text" name="customer_mobile" id="customer_mobile"
                                    value="{{ $booking->mobile }}" readonly>
                            </td>

                            <td class="title">Contact Person (If Any Other)</td>
                            <td>
                                <input type="text" name="contact_person" id="contact_person"
                                    value="{{ old('contact_person', $booking->contact_person) }}">
                            </td>

                            <td class="title">Contact Person Phone No.</td>
                            <td>
                                <input type="text" name="contact_person_mobile" id="contact_person_mobile"
                                    value="{{ old('contact_person_mobile', $booking->contact_person_mobile) }}"
                                    maxlength="10" inputmode="numeric"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);">
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Email ID</td>
                            <td>
                                <input type="email" name="email" id="email" value="{{ old('email', $booking->email) }}">
                            </td>

                            <td class="title">D.O.B.</td>
                            <td>
                                <input type="date" name="dob" id="dob" value="{{ old('dob', $booking->c_dob) }}">
                            </td>

                            <td class="title">Marital Status</td>
                            <td>
                                <select name="marital_status" id="marital_status">
                                    <option value="">Select</option>
                                    <option value="Single" {{ old('marital_status', $booking->marital_status) ==
                                        'Single' ? 'selected' : '' }}>
                                        Single
                                    </option>
                                    <option value="Married" {{ old('marital_status', $booking->marital_status) ==
                                        'Married' ? 'selected' : '' }}>
                                        Married
                                    </option>
                                </select>
                            </td>

                            <td class="title">Date of Anniversary</td>
                            <td>
                                <input type="date" name="anniversary_date" id="anniversary_date"
                                    value="{{ old('anniversary_date', $booking->anniversary_date) }}">
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Registration Address</td>
                            <td colspan="7">
                                <input type="text" name="registration_address" id="registration_address"
                                    value="{{ old('registration_address', $booking->address ?? '') }}">
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Identity Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="12%">PAN No.</td>
                            <td width="13%">
                                <input type="text" name="pan_no" id="pan_no"
                                    value="{{ old('pan_no', $booking->pan_no) }}" maxlength="10"
                                    style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase();">
                            </td>

                            <td class="title" width="12%">Aadhaar No.</td>
                            <td width="13%">
                                <input type="text" name="adhar_no" id="adhar_no"
                                    value="{{ old('adhar_no', $booking->adhar_no) }}" maxlength="12" inputmode="numeric"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,12);">
                            </td>

                            <td class="title" width="12%">Driving License No.</td>
                            <td width="13%">
                                <input type="text" name="driving_license_no" id="driving_license_no"
                                    value="{{ old('driving_license_no') }}" style="text-transform:uppercase"
                                    oninput="this.value=this.value.toUpperCase();">
                            </td>

                            <td class="title" width="12%">Voter ID No.</td>
                            <td width="13%">
                                <input type="text" name="voter_id_no" id="voter_id_no" value="{{ old('voter_id_no') }}"
                                    style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase();">
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Nominee Details (Insurance) ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="16%">Nominee Name (For Insurance)</td>
                            <td width="17%">
                                <input type="text" name="nominee_name" id="nominee_name"
                                    value="{{ old('nominee_name') }}">
                            </td>

                            <td class="title" width="16%">Relation with Nominee</td>
                            <td width="17%">
                                <select name="nominee_relation" id="nominee_relation">
                                    <option value="">Select Relation</option>

                                    <option value="Spouse" {{ old('nominee_relation')=='Spouse' ? 'selected' : '' }}>
                                        Spouse</option>
                                    <option value="Brother" {{ old('nominee_relation')=='Brother' ? 'selected' : '' }}>
                                        Brother</option>
                                    <option value="Mother" {{ old('nominee_relation')=='Mother' ? 'selected' : '' }}>
                                        Mother</option>
                                    <option value="Father" {{ old('nominee_relation')=='Father' ? 'selected' : '' }}>
                                        Father</option>
                                    <option value="Sister" {{ old('nominee_relation')=='Sister' ? 'selected' : '' }}>
                                        Sister</option>
                                    <option value="Son" {{ old('nominee_relation')=='Son' ? 'selected' : '' }}>Son
                                    </option>
                                    <option value="Daughter" {{ old('nominee_relation')=='Daughter' ? 'selected' : ''
                                        }}>Daughter</option>
                                </select>
                            </td>

                            <td class="title" width="16%">Age of Nominee</td>
                            <td width="18%">
                                <input name="nominee_age" id="nominee_age" min="0" max="120"
                                    value="{{ old('nominee_age') }}">
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Vehicle Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="12%">Retail Category</td>
                            <td width="13%">
                                <select name="retail_category" id="retail_category">
                                    <option value="">Select Retail Category</option>

                                    <option value="Normal" {{ old('retail_category')=='Normal' ? 'selected' : '' }}>
                                        Normal
                                    </option>

                                    <option value="ZACO" {{ old('retail_category')=='ZACO' ? 'selected' : '' }}>
                                        ZACO
                                    </option>
                                </select>
                            </td>

                            <td class="title" width="12%">Segment</td>
                            <td width="13%">
                                <input type="text" id="segment_name" value="{{ $segment?->name }}" readonly>
                            </td>

                            <td class="title" width="12%">Model</td>
                            <td width="13%">
                                <input type="text" id="model_name" value="{{ $model?->name }}" readonly>
                            </td>

                            <td class="title" width="12%">Variant</td>
                            <td width="13%">
                                <input type="text" id="variant_name"
                                    value="{{ $variant?->display_name ?? $variant?->custom_name ?? $variant?->oem_name }}"
                                    readonly>
                            </td>
                        </tr>

                        <tr>
                            <td class="title" width="12%">Color</td>
                            <td width="13%">
                                <input type="text" id="color_name" value="{{ $color?->name }}" readonly>
                            </td>

                            <td class="title" width="12%">Body Type</td>
                            <td width="13%">
                                <select name="body_type" id="body_type">
                                    <option value="">Select Body Type</option>

                                    @foreach($body_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('body_type', $rto?->body_type) == $key ?
                                        'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="title" width="12%">Sale Type</td>
                            <td width="13%">
                                <select name="sale_type" id="sale_type">
                                    <option value="">Select Sale Type</option>

                                    @foreach($sale_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('sale_type', $rto?->sale_type) == $key ?
                                        'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="title" width="12%">DSA Retail</td>
                            <td width="13%">
                                <select name="dsa_retail" id="dsa_retail">
                                    <option value="No" {{ empty($booking->dsa_id) ? 'selected' : '' }}>
                                        No
                                    </option>

                                    <option value="Yes" {{ !empty($booking->dsa_id) ? 'selected' : '' }}>
                                        Yes
                                    </option>
                                </select>
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Vehicle & Accessories Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title" width="12%">DSA Name</td>
                            <td width="13%">
                                <select name="dsa_id" id="dsa_id">
                                    <option value="">Select DSA</option>

                                    @foreach($dsaList as $dsa)
                                    <option value="{{ $dsa->id }}" data-location="{{ $dsa->dlocation }}" {{ $booking->
                                        dsa_id == $dsa->id ? 'selected' : '' }}>
                                        {{ $dsa->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="title" width="12%">DSA Location</td>
                            <td width="13%">
                                <input type="text" id="dsa_location" readonly disabled>
                            </td>

                            <td class="title" width="12%">Exchange</td>
                            <td width="13%">
                                <select name="exchange" id="exchange">
                                    <option value="NA" {{ old('exchange', $booking->buyer_type == 'First time Buyer'
                                        ? 'NA'
                                        : ($booking->buyer_type == 'Exchange Buy' ? 'In-House' : 'Third Party')) == 'NA'
                                        ? 'selected' : '' }}>
                                        NA
                                    </option>

                                    <option value="In-House" {{ old('exchange', $booking->buyer_type == 'First time
                                        Buyer'
                                        ? 'NA'
                                        : ($booking->buyer_type == 'Exchange Buy' ? 'In-House' : 'Third Party')) ==
                                        'In-House'
                                        ? 'selected' : '' }}>
                                        In-House
                                    </option>

                                    <option value="Third Party" {{ old('exchange', $booking->buyer_type == 'First time
                                        Buyer'
                                        ? 'NA'
                                        : ($booking->buyer_type == 'Exchange Buy' ? 'In-House' : 'Third Party')) ==
                                        'Third Party'
                                        ? 'selected' : '' }}>
                                        Third Party
                                    </option>
                                </select>
                            </td>

                            <td class="title" width="12%">In House RTO</td>
                            <td width="13%">
                                <select name="in_house_rto" id="in_house_rto" disabled>
                                    <option value="1" {{ $rto ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ !$rto ? 'selected' : '' }}>No</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Accessories</td>
                            <td>
                                <select name="accessories[]" id="accessories" multiple>
                                    @foreach($accessoryList as $accessory)
                                    <option value="{{ $accessory->part_no }}" data-price="{{ $accessory->ndp }}" {{
                                        in_array($accessory->part_no, $selectedAccessories) ? 'selected' : '' }}>
                                        {{ $accessory->item }}
                                        (₹{{ number_format($accessory->ndp,2) }})
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="title">Accessories Amount</td>
                            <td>
                                <input type="text" id="accessories_amount" name="accessories_amount" value="0.00"
                                    readonly>
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Chassis Number</td>
                            <td>
                                <input type="text" id="chassis_no_display" value="{{ $booking->chassis_no }}">
                            </td>

                            <td class="title">Engine Number</td>
                            <td>
                                <input type="text" name="engine_no" id="engine_no"
                                    value="{{ old('engine_no', $insurance?->engine_no) }}" readonly>
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Chassis Image</td>
                            <td colspan="3">

                                <input type="file" name="chassis_image" id="chassis_image">

                                <div class="mt-2">
                                    <img id="chassis_preview" src="{{ $chassisImage }}" style="
                            max-width:220px;
                            max-height:100px;
                            border:1px solid #000;
                            display:{{ $chassisImage ? 'block' : 'none' }};
                            ">
                                </div>

                                <div class="mt-2">
                                    <button type="button" id="editImageBtn" class="btn btn-warning btn-sm"
                                        style="display:none">
                                        <i class="la la-edit"></i>
                                        Edit Image
                                    </button>
                                </div>

                            </td>
                        </tr>

                    </table>

                    {{-- ================= Invoice / OEM Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title">OEM Model Code</td>
                            <td>
                                <input type="text" name="oem_model_code" id="oem_model_code"
                                    value="{{ old('oem_model_code', $variant?->oem_name) }}" readonly>
                            </td>

                            <td class="title">GST Slab</td>
                            <td>
                                <input type="text" name="gst_slab" id="gst_slab"
                                    value="{{ old('gst_slab', $variant?->gst_slab ?? '') }}" readonly>
                            </td>

                            <td class="title">Invoice No.</td>
                            <td>
                                <input type="text" name="inv_no" id="inv_no"
                                    value="{{ old('inv_no', $booking->inv_no) }}" readonly>
                            </td>

                            <td class="title">Invoice Date</td>
                            <td>
                                <input type="date" name="inv_date" id="inv_date"
                                    value="{{ old('inv_date', optional($booking->inv_date)->format('Y-m-d') ?? ($booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('Y-m-d') : '')) }}"
                                    readonly>
                            </td>
                        </tr>

                    </table>

                    {{-- ================= Quotation Grid (Price / Discount) =================
                    Same layout/field-ids as the Quotation form, so this is prefilled
                    straight from the saved Quotation ($quotationData) and the
                    calculateQuotation() script (already in this file) works unchanged. --}}

                    @php
                    // Pick the group-discount field that already has a saved value
                    // (from the Quotation), so the "pick one of N" selects below
                    // open pre-selected on the right option instead of blank.
                    $pickGroupValue = function (array $fields) use ($quotationData) {
                    foreach ($fields as $f) {
                    $val = old($f, $quotationData[$f] ?? null);
                    if ($val !== null && $val !== '' && $val !== '0' && $val !== '0.00' && $val !== 'N/A') {
                    return $f;
                    }
                    }
                    return null;
                    };

                    $groupASelected = old('group_a_select', $pickGroupValue(['cash_scheme_oem', 'csd_discount',
                    'fame_subsidy']));
                    $groupAAmount = $groupASelected ? old($groupASelected, $quotationData[$groupASelected] ?? '') : '';
                    $groupAType = $groupASelected ? old($groupASelected . '_type', $quotationData[$groupASelected .
                    '_type'] ?? '') : '';

                    $groupBSelected = old('group_b_select', $pickGroupValue(['corporate_discount', 'loyalty_bonus']));
                    $groupBAmount = $groupBSelected ? old($groupBSelected, $quotationData[$groupBSelected] ?? '') : '';
                    $groupBType = $groupBSelected ? old($groupBSelected . '_type', $quotationData[$groupBSelected .
                    '_type'] ?? '') : '';

                    $groupCSelected = old('group_c_select', $pickGroupValue(['exchange_bonus', 'green_bonus',
                    'welcome_bonus']));
                    $groupCAmount = $groupCSelected ? old($groupCSelected, $quotationData[$groupCSelected] ?? '') : '';
                    $groupCType = $groupCSelected ? old($groupCSelected . '_type', $quotationData[$groupCSelected .
                    '_type'] ?? '') : '';
                    @endphp

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
                                        <input name="ex_showroom_price" id="ex_showroom_price" class="numeric-only"
                                            value="{{ old('ex_showroom_price', $selectedExShowroomPrice ?? ($quotationData['ex_showroom_price'] ?? '')) }}">
                                    </td>
                                    <td class="cell-label">
                                        <select id="group_a_select" name="group_a_select" class="group-select">
                                            <option value="cash_scheme_oem" {{ $groupASelected=='cash_scheme_oem'
                                                ? 'selected' : '' }}>Cash Scheme OEM</option>
                                            <option value="csd_discount" {{ $groupASelected=='csd_discount' ? 'selected'
                                                : '' }}>CSD Discount</option>
                                            <option value="fame_subsidy" id="fame_subsidy_option" {{
                                                $groupASelected=='fame_subsidy' ? 'selected' : '' }}>Fame Subsidy (LMM)
                                            </option>
                                        </select>
                                    </td>
                                    <td class="cell-type">
                                        <select id="group_a_type" name="group_a_type">
                                            <option value="INV" {{ $groupAType=='INV' ? 'selected' : '' }}>INV</option>
                                            <option value="CN" {{ $groupAType=='CN' ? 'selected' : '' }}>CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="group_a_amount" class="numeric-only" placeholder="0.00"
                                            value="{{ $groupAAmount }}">
                                        <input type="hidden" id="cash_scheme_oem" name="cash_scheme_oem"
                                            value="{{ old('cash_scheme_oem', $quotationData['cash_scheme_oem'] ?? '') }}">
                                        <input type="hidden" id="cash_scheme_oem_type" name="cash_scheme_oem_type"
                                            value="{{ old('cash_scheme_oem_type', $quotationData['cash_scheme_oem_type'] ?? '') }}">
                                        <input type="hidden" id="csd_discount" name="csd_discount"
                                            value="{{ old('csd_discount', $quotationData['csd_discount'] ?? '') }}">
                                        <input type="hidden" id="csd_discount_type" name="csd_discount_type"
                                            value="{{ old('csd_discount_type', $quotationData['csd_discount_type'] ?? '') }}">
                                        <input type="hidden" id="fame_subsidy" name="fame_subsidy"
                                            value="{{ old('fame_subsidy', $quotationData['fame_subsidy'] ?? '') }}">
                                        <input type="hidden" id="fame_subsidy_type" name="fame_subsidy_type"
                                            value="{{ old('fame_subsidy_type', $quotationData['fame_subsidy_type'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 2: Insurance | Cash Scheme Dealer --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Insurance</td>
                                    <td class="cell-option">
                                        <select name="policy_type" id="policy_type">
                                            <option value="">Select Insurance</option>
                                            @foreach($insurance_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('policy_type', $selectedPolicyType)==$key
                                                ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="insurance_amount" name="insurance_amount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('insurance_amount', $quotationData['insurance_amount'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">Cash Scheme Dealer</td>
                                    <td class="cell-type">
                                        <select id="dealer_discount_type" name="dealer_discount_type">
                                            <option value="INV" {{ old('dealer_discount_type',
                                                $quotationData['dealer_discount_type'] ?? '' )=='INV' ? 'selected' : ''
                                                }}>INV</option>
                                            <option value="CN" {{ old('dealer_discount_type',
                                                $quotationData['dealer_discount_type'] ?? '' )=='CN' ? 'selected' : ''
                                                }}>CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="dealer_discount" id="dealer_discount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('dealer_discount', $quotationData['dealer_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 3: Registration | Accessories Scheme --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Registration</td>
                                    <td class="cell-option">
                                        <select name="registration_type" id="registration_type">
                                            <option value="">Select Registration</option>
                                            @foreach($registration_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('registration_type',
                                                $selectedRegistrationType)==$key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="registration_amount" name="registration_amount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('registration_amount', $quotationData['registration_amount'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">Accessories Scheme</td>
                                    <td class="cell-type">
                                        <select id="accessories_discount_type" name="accessories_discount_type">
                                            <option value="INV" {{ old('accessories_discount_type',
                                                $quotationData['accessories_discount_type'] ?? '' )=='INV' ? 'selected'
                                                : '' }}>INV</option>
                                            <option value="CN" {{ old('accessories_discount_type',
                                                $quotationData['accessories_discount_type'] ?? '' )=='CN' ? 'selected'
                                                : '' }}>CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="accessories_discount" id="accessories_discount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('accessories_discount', $quotationData['accessories_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 4: (Accessories shown above, in Vehicle & Accessories Details) | Shield Scheme
                                --}}
                                <tr class="grid-row">
                                    <td class="cell-label"></td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount"></td>
                                    <td class="cell-label">Shield Scheme</td>
                                    <td class="cell-type">
                                        <select id="shield_scheme_type" name="shield_scheme_type">
                                            <option value="INV" {{ old('shield_scheme_type',
                                                $quotationData['shield_scheme_type'] ?? '' )=='INV' ? 'selected' : ''
                                                }}>INV</option>
                                            <option value="CN" {{ old('shield_scheme_type',
                                                $quotationData['shield_scheme_type'] ?? '' )=='CN' ? 'selected' : '' }}>
                                                CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="shield_scheme" id="shield_scheme" class="numeric-only"
                                            placeholder="0.00"
                                            value="{{ old('shield_scheme', $quotationData['shield_scheme'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 5: Maxicare | Group B discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Maxicare</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="maxicare" name="maxicare" class="numeric-only"
                                            value="{{ old('maxicare', $quotationData['maxicare'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">
                                        <select id="group_b_select" name="group_b_select" class="group-select">
                                            <option value="corporate_discount" {{ $groupBSelected=='corporate_discount'
                                                ? 'selected' : '' }}>Corporate Discount</option>
                                            <option value="loyalty_bonus" {{ $groupBSelected=='loyalty_bonus'
                                                ? 'selected' : '' }}>Loyalty Bonus</option>
                                        </select>
                                    </td>
                                    <td class="cell-type">
                                        <select id="group_b_type" name="group_b_type">
                                            <option value="INV" {{ $groupBType=='INV' ? 'selected' : '' }}>INV</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="group_b_amount" class="numeric-only" placeholder="0.00"
                                            value="{{ $groupBAmount }}">
                                        <input type="hidden" id="corporate_discount" name="corporate_discount"
                                            value="{{ old('corporate_discount', $quotationData['corporate_discount'] ?? '') }}">
                                        <input type="hidden" id="corporate_discount_type" name="corporate_discount_type"
                                            value="{{ old('corporate_discount_type', $quotationData['corporate_discount_type'] ?? '') }}">
                                        <input type="hidden" id="loyalty_bonus" name="loyalty_bonus"
                                            value="{{ old('loyalty_bonus', $quotationData['loyalty_bonus'] ?? '') }}">
                                        <input type="hidden" id="loyalty_bonus_type" name="loyalty_bonus_type"
                                            value="{{ old('loyalty_bonus_type', $quotationData['loyalty_bonus_type'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 6: VLTD Device (GPS) | Group C discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">VLTD Device (GPS)</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="vltd_device" name="vltd_device" class="numeric-only"
                                            value="{{ old('vltd_device', $quotationData['vltd_device'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">
                                        <select id="group_c_select" name="group_c_select" class="group-select">
                                            <option value="exchange_bonus" {{ $groupCSelected=='exchange_bonus'
                                                ? 'selected' : '' }}>Exchange Bonus</option>
                                            <option value="green_bonus" {{ $groupCSelected=='green_bonus' ? 'selected'
                                                : '' }}>Green Bonus</option>
                                            <option value="welcome_bonus" {{ $groupCSelected=='welcome_bonus'
                                                ? 'selected' : '' }}>Welcome Bonus</option>
                                        </select>
                                    </td>
                                    <td class="cell-type">
                                        <select id="group_c_type" name="group_c_type">
                                            <option value="CN1" {{ $groupCType=='CN1' ? 'selected' : '' }}>CN1</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" id="group_c_amount" class="numeric-only" placeholder="0.00"
                                            value="{{ $groupCAmount }}">
                                        <input type="hidden" id="exchange_bonus" name="exchange_bonus"
                                            value="{{ old('exchange_bonus', $quotationData['exchange_bonus'] ?? '') }}">
                                        <input type="hidden" id="exchange_bonus_type" name="exchange_bonus_type"
                                            value="{{ old('exchange_bonus_type', $quotationData['exchange_bonus_type'] ?? '') }}">
                                        <input type="hidden" id="green_bonus" name="green_bonus"
                                            value="{{ old('green_bonus', $quotationData['green_bonus'] ?? '') }}">
                                        <input type="hidden" id="green_bonus_type" name="green_bonus_type"
                                            value="{{ old('green_bonus_type', $quotationData['green_bonus_type'] ?? '') }}">
                                        <input type="hidden" id="welcome_bonus" name="welcome_bonus"
                                            value="{{ old('welcome_bonus', $quotationData['welcome_bonus'] ?? '') }}">
                                        <input type="hidden" id="welcome_bonus_type" name="welcome_bonus_type"
                                            value="{{ old('welcome_bonus_type', $quotationData['welcome_bonus_type'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 7: Coating | Accessories Spl Disc --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Coating</td>
                                    <td class="cell-option">
                                        <select id="coating" name="coating">
                                            <option value="">Select Coating</option>
                                            <option value="Ceramic" {{ old('coating', $quotationData['coating'] ?? ''
                                                )=='Ceramic' ? 'selected' : '' }}>Ceramic</option>
                                            <option value="Graphene" {{ old('coating', $quotationData['coating'] ?? ''
                                                )=='Graphene' ? 'selected' : '' }}>Graphene</option>
                                            <option value="No Coating" {{ old('coating', $quotationData['coating'] ?? ''
                                                )=='No Coating' ? 'selected' : '' }}>No Coating</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input id="coating_price" name="coating_price" class="numeric-only"
                                            value="{{ old('coating_price', $quotationData['coating_price'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">Accessories Spl Disc</td>
                                    <td class="cell-type">
                                        <select id="accessories_spl_disc_type" name="accessories_spl_disc_type">
                                            <option value="INV" {{ old('accessories_spl_disc_type',
                                                $quotationData['accessories_spl_disc_type'] ?? '' )=='INV' ? 'selected'
                                                : '' }}>INV</option>
                                            <option value="CN" {{ old('accessories_spl_disc_type',
                                                $quotationData['accessories_spl_disc_type'] ?? '' )=='CN' ? 'selected'
                                                : '' }}>CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="accessories_spl_disc" id="accessories_spl_disc"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('accessories_spl_disc', $quotationData['accessories_spl_disc'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 8: PPF | Coating Spl Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">PPF</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="ppf" name="ppf" class="numeric-only"
                                            value="{{ old('ppf', $quotationData['ppf'] ?? '') }}">
                                    </td>
                                    <td class="cell-label" id="coating_discount_label">
                                        Coating Spl Discount
                                    </td>
                                    <td class="cell-type">
                                        <select id="ceramic_discount_type" name="ceramic_discount_type">
                                            <option value="INV" {{ old('ceramic_discount_type',
                                                $quotationData['ceramic_discount_type'] ?? '' )=='INV' ? 'selected' : ''
                                                }}>INV</option>
                                            <option value="CN" {{ old('ceramic_discount_type',
                                                $quotationData['ceramic_discount_type'] ?? '' )=='CN' ? 'selected' : ''
                                                }}>CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="ceramic_discount" id="ceramic_discount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('ceramic_discount', $quotationData['ceramic_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 9: RTO Yellow Tape | PPF Spl Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">RTO Yellow Tape</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="rto_yellow_tape" name="rto_yellow_tape" class="numeric-only"
                                            value="{{ old('rto_yellow_tape', $quotationData['rto_yellow_tape'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">PPF Spl Discount</td>
                                    <td class="cell-type">
                                        <select id="ppf_discount_type" name="ppf_discount_type">
                                            <option value="INV" {{ old('ppf_discount_type',
                                                $quotationData['ppf_discount_type'] ?? '' )=='INV' ? 'selected' : '' }}>
                                                INV</option>
                                            <option value="CN" {{ old('ppf_discount_type',
                                                $quotationData['ppf_discount_type'] ?? '' )=='CN' ? 'selected' : '' }}>
                                                CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="ppf_discount" id="ppf_discount" class="numeric-only"
                                            placeholder="0.00"
                                            value="{{ old('ppf_discount', $quotationData['ppf_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 10: Kazam Charging Kit | Charger Swapping Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Kazam Charging Kit</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="kazam_charging_kit" name="kazam_charging_kit" class="numeric-only"
                                            value="{{ old('kazam_charging_kit', $quotationData['kazam_charging_kit'] ?? '') }}">
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
                                            name="charger_swapping_discount" class="numeric-only" placeholder="0.00"
                                            value="{{ old('charger_swapping_discount', $quotationData['charger_swapping_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 11: Incidental Charges | Other Cash Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Incidental Charges</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="incidental_charges" name="incidental_charges" class="numeric-only"
                                            value="{{ old('incidental_charges', $quotationData['incidental_charges'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">Other Cash Discount</td>
                                    <td class="cell-type">
                                        <select id="other_cash_discount_type" name="other_cash_discount_type">
                                            <option value="INV" {{ old('other_cash_discount_type',
                                                $quotationData['other_cash_discount_type'] ?? '' )=='INV' ? 'selected'
                                                : '' }}>INV</option>
                                            <option value="CN" {{ old('other_cash_discount_type',
                                                $quotationData['other_cash_discount_type'] ?? '' )=='CN' ? 'selected'
                                                : '' }}>CN</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="other_cash_discount" id="other_cash_discount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('other_cash_discount', $quotationData['other_cash_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 12: Shield | Special Cash Discount --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Shield</td>
                                    <td class="cell-option">
                                        <select id="shield" name="shield">
                                            <option value="">Select Shield</option>
                                            <option value="4th Year" {{ old('shield', $quotationData['shield'] ?? ''
                                                )=='4th Year' ? 'selected' : '' }}>4th Year</option>
                                            <option value="4th + 5th Year" {{ old('shield', $quotationData['shield']
                                                ?? '' )=='4th + 5th Year' ? 'selected' : '' }}>4th + 5th Year</option>
                                            <option value="No Shield" {{ old('shield', $quotationData['shield'] ?? ''
                                                )=='No Shield' ? 'selected' : '' }}>No Shield</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input id="shield_price" name="shield_price" class="numeric-only"
                                            value="{{ old('shield_price', $quotationData['shield_price'] ?? '') }}">
                                    </td>
                                    <td class="cell-label">Special Cash Discount</td>
                                    <td class="cell-type">
                                        <select id="special_cash_discount_type" name="special_cash_discount_type">
                                            <option value="INV" {{ old('special_cash_discount_type',
                                                $quotationData['special_cash_discount_type'] ?? '' )=='INV' ? 'selected'
                                                : '' }}>INV</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input type="text" name="special_cash_discount" id="special_cash_discount"
                                            class="numeric-only" placeholder="0.00"
                                            value="{{ old('special_cash_discount', $quotationData['special_cash_discount'] ?? '') }}">
                                    </td>
                                </tr>

                                {{-- Row 13: RSA | (no discount item — left blank, still aligned) --}}
                                <tr class="grid-row">
                                    <td class="cell-label">RSA</td>
                                    <td class="cell-option">
                                        <select id="rsa" name="rsa">
                                            <option value="">Select RSA</option>
                                            @foreach(['1 Year','2 Year','3 Year','4 Year','5 Year','No RSA'] as $rsaOpt)
                                            <option value="{{ $rsaOpt }}" {{ old('rsa', $quotationData['rsa'] ?? ''
                                                )==$rsaOpt ? 'selected' : '' }}>
                                                {{ $rsaOpt }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input id="rsa_amount" name="rsa_amount" class="numeric-only"
                                            value="{{ old('rsa_amount', $quotationData['rsa_amount'] ?? '') }}">
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
                                        <input id="fastag" name="fastag" class="numeric-only"
                                            value="{{ old('fastag', $quotationData['fastag'] ?? '') }}">
                                    </td>
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                                {{-- Row 15: COD Charges --}}
                                <tr class="grid-row">
                                    <td class="cell-label">COD Charges</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="cod_charges" name="cod_charges" class="numeric-only"
                                            value="{{ old('cod_charges', $quotationData['cod_charges'] ?? '') }}">
                                    </td>
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                                {{-- Row 16: Charger Swapping --}}
                                <tr class="grid-row">
                                    <td class="cell-label">Charger Swapping</td>
                                    <td class="cell-option">
                                        <select id="charger_swapping" name="charger_swapping">
                                            <option value="">Select</option>
                                            <option value="Not Applicable" {{ old('charger_swapping',
                                                $quotationData['charger_swapping'] ?? '' )=='Not Applicable'
                                                ? 'selected' : '' }}>Not Applicable</option>
                                            <option value="NCH to 7.2 kW" {{ old('charger_swapping',
                                                $quotationData['charger_swapping'] ?? '' )=='NCH to 7.2 kW' ? 'selected'
                                                : '' }}>NCH to 7.2 kW</option>
                                            <option value="NCH to 11.2 kW" {{ old('charger_swapping',
                                                $quotationData['charger_swapping'] ?? '' )=='NCH to 11.2 kW'
                                                ? 'selected' : '' }}>NCH to 11.2 kW</option>
                                            <option value="7.2 kW to 11.2 kW" {{ old('charger_swapping',
                                                $quotationData['charger_swapping'] ?? '' )=='7.2 kW to 11.2 kW'
                                                ? 'selected' : '' }}>7.2 kW to 11.2 kW</option>
                                        </select>
                                    </td>
                                    <td class="cell-amount">
                                        <input id="charger_swapping_amount" name="charger_swapping_amount"
                                            class="numeric-only"
                                            value="{{ old('charger_swapping_amount', $quotationData['charger_swapping_amount'] ?? '') }}"
                                            placeholder="Enter Amount" min="0" step="0.01" disabled>
                                    </td>
                                    <td class="cell-label"></td>
                                    <td class="cell-type"></td>
                                    <td class="cell-amount"></td>
                                </tr>

                                {{-- Row 17: TCS --}}
                                <tr class="grid-row">
                                    <td class="cell-label">TCS @ 1%</td>
                                    <td class="cell-option"></td>
                                    <td class="cell-amount">
                                        <input id="tcs" name="tcs" class="numeric-only"
                                            value="{{ old('tcs', $quotationData['tcs'] ?? '') }}">
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

                    {{-- ================= Financier / Loan Details (OTF-specific, not part of Quotation)
                    ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title">Financier Name</td>
                            <td>
                                <input type="text" id="financier_name_display" value="{{ $financierName }}" readonly>
                            </td>

                            <td class="title">Financier Branch</td>
                            <td>
                                <input type="text" id="financier_branch" name="financier_branch"
                                    value="{{ old('financier_branch') }}">
                            </td>

                            <td class="title">Loan Amount</td>
                            <td>
                                <input id="loan_amount" name="loan_amount" value="{{ $finance?->loan_amount }}"
                                    readonly>
                            </td>

                            <td class="title">Deduction</td>
                            <td>
                                <input id="deduction" name="deduction" value="{{ old('deduction') }}">
                            </td>
                        </tr>

                        <tr>
                            <td class="title">Margin Money</td>
                            <td>
                                <input id="margin_money" name="margin_money" value="{{ $finance?->margin }}" readonly>
                            </td>

                            <td class="title">DO Amount</td>
                            <td>
                                <input id="do_amount" name="do_amount" class="bg-light"
                                    value="{{ $finance?->loan_amount }}" readonly>
                            </td>

                            <td class="title">Financier Subvention</td>
                            <td>
                                <input id="financier_subvention" name="financier_subvention"
                                    value="{{ old('financier_subvention', $finance?->subvention_amount) }}">
                            </td>

                            <td class="title"></td>
                            <td></td>
                        </tr>

                    </table>

                    {{-- ================= Receipt Details ================= --}}
                    <div class="form-section mb-3">

                        <div class="row">

                            {{-- ================= Receipt Table (75%) ================= --}}
                            <div class="col-md-9">


                                <div class="table-responsive">

                                    <table class="bill-table">

                                        <thead>
                                            <tr>
                                                <th>Receipt No.</th>
                                                <th>Receipt Date</th>
                                                <th>Amount</th>
                                                <th width="70">View</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            @forelse($receiptLogs as $receipt)

                                            <tr>

                                                <td>
                                                    <input type="text" value="{{ $receipt->reciept }}" readonly>
                                                </td>

                                                <td>
                                                    <input type="date" value="{{ $receipt->date }}" readonly>
                                                </td>

                                                <td>
                                                    <input type="text" value="{{ $receipt->amount }}" readonly>
                                                </td>

                                                <td class="text-center">

                                                    @php
                                                    $receiptImage = $receipt->getFirstMediaUrl('amount-proof');
                                                    @endphp

                                                    @if($receiptImage)
                                                    <a href="{{ $receiptImage }}" data-lightbox="receipt-images"
                                                        data-title="Receipt {{ $receipt->reciept }}">
                                                        <i class="la la-eye text-primary" style="font-size:20px;"></i>
                                                    </a>
                                                    @else
                                                    <i class="la la-eye-slash text-muted"></i>
                                                    @endif

                                                </td>

                                            </tr>

                                            @empty

                                            <tr>
                                                <td colspan="4" class="text-center">
                                                    No Receipt Found
                                                </td>
                                            </tr>

                                            @endforelse

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                            {{-- ================= Total Receipt (25%) ================= --}}
                            <div class="col-md-3">

                                <table class="bill-table">

                                    <tr>
                                        <td class="title text-center">
                                            Total Receipt Amount
                                        </td>
                                    </tr>

                                    <tr>
                                        <td>
                                            <input type="text" value="₹ {{ number_format($receiptTotal,2) }}" readonly
                                                class="bg-light fw-bold text-center"
                                                style="font-size:18px;height:40px;">
                                        </td>
                                    </tr>

                                </table>

                            </div>

                        </div>

                    </div>

                    {{-- ================= Delivery & Receivable Details ================= --}}
                    <table class="bill-table mb-3">

                        <tr>
                            <td class="title">DO Settlement Difference</td>
                            <td>
                                <input type="number">
                            </td>

                            <td class="title">Expected Balance</td>
                            <td>
                                <input id="expected_balance" readonly class="bg-light">
                            </td>

                            <td class="title">Final Balance</td>
                            <td>
                                <input id="final_balance" readonly class="bg-light">
                            </td>

                            <td class="title">Financier Verified</td>
                            <td>
                                <select name="financier_verified" id="financier_verified">
                                    <option value="Please Select">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </td>
                        </tr>

                        <tr>

                            <td class="title">Vehicle To Be Delivered On</td>
                            <td>
                                <select id="vehicle_delivery_on" name="vehicle_delivery_on">
                                    <option value="">Select</option>
                                    <option value="DO">DO</option>
                                    <option value="Payment">Payment</option>
                                    <option value="Mail">Mail</option>
                                    <option value="Whatsapp">Whatsapp</option>
                                </select>
                            </td>

                            <td class="title">DO Number (Delivery Time)</td>
                            <td>
                                <input type="text" id="do_number" name="do_number" disabled>
                            </td>

                            <td class="title">DO Number (TA Statement)</td>
                            <td>
                                <input type="text" id="do_number_ta" name="do_number_ta">
                            </td>

                            <td class="title">DO Amount (TA Statement)</td>
                            <td>
                                <input id="do_amount_ta" name="do_amount_ta" readonly class="bg-light">
                            </td>

                        </tr>

                        <tr>

                            <td class="title">DO Voucher Date</td>
                            <td>
                                <input type="date" id="do_voucher_date" name="do_voucher_date" readonly>
                            </td>

                            <td class="title">Brokerage Amount</td>
                            <td>
                                <input id="brokerage_amount" name="brokerage_amount" min="0" step="0.01">
                            </td>

                            <td class="title">Other Discount Receivable</td>
                            <td>
                                <input id="other_discount_receivable" name="other_discount_receivable" min="0"
                                    step="0.01">
                            </td>

                            <td class="title">M&M Support Receivable</td>
                            <td>
                                <input id="mm_support_receivable" name="mm_support_receivable" min="0" step="0.01">
                            </td>

                        </tr>

                        <tr>

                            <td class="title">Liquidation Scheme Receivable</td>
                            <td>
                                <input id="liquidation_scheme_receivable" name="liquidation_scheme_receivable" min="0"
                                    step="0.01">
                            </td>

                            <td class="title">Registration Service Charge - Receivable</td>
                            <td>
                                <input id="registration_service_charge_receivable"
                                    name="registration_service_charge_receivable" min="0" step="0.01">
                            </td>

                            <td class="title">Registration Service Charge - Received</td>
                            <td>
                                <input id="registration_service_charge_received"
                                    name="registration_service_charge_received" min="0" step="0.01">
                            </td>

                            <td class="title"></td>
                            <td></td>

                        </tr>

                    </table>


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

                </div>

                <div id="otfPreviewPage" style="display:none;">
                    <div class="no-print text-center py-2">
                        <button type="button" id="backToForm" class="btn btn-secondary">Back</button>
                        <button type="button" id="printFormBtn" class="btn btn-success">Print / Save as PDF</button>
                    </div>
                    <div class="print-page">
                        <div id="printScaleWrapper">
                            <div id="previewContent"></div>
                        </div>
                    </div>
                    <div class="text-center mt-3 mb-4 no-print">
                        <button type="button" id="backToForm2" class="btn btn-secondary">Back</button>
                        <button type="button" id="printFormBtn2" class="btn btn-success">Print / Save as PDF</button>
                    </div>
                </div>




            </div>

            @endsection

            @push('after_scripts')
            {{--
            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"> --}}
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


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
        list.push($(this).text());
    });

    $('#accessories_print').text(list.join(', '));
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
    
        let tcs = 0;

        if (subtotal > 1000000) {

            tcs = subtotal * 0.01;

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

    // Financier Invoice / Discount Bifurcation boxes
    let bifurcation = calculateDiscountBifurcation();

    $('#fi_total_receivable').val(totalReceivable.toFixed(2));
    $('#less_inv_discount').val(bifurcation.invoicedDiscount.toFixed(2));
    $('#finvoice_amount').val((totalReceivable - bifurcation.invoicedDiscount).toFixed(2));

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

    });

});

// Segment -> Model



// Model -> Variant





function toggleLMMFields() {

    let isLMM = "{{ $segment?->name }}" === "LMM";

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
            </script>
            @endpush