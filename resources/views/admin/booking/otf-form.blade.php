@extends(backpack_view('blank'))

@php
use App\Services\OrgService;
@endphp

@section('title', 'Quotation Form')

@push('after_styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.5/css/lightbox.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            display: block !important;
            width: 100%;
            margin: 0;
            padding: 2mm;
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
        margin-bottom: 6px !important;
    }

    .bill-table td {
        border: 1px solid #000;
        padding: 2px 5px;
        height: 26px;
        font-size: 10px;
        vertical-align: middle;
        width: 40%
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
        width: 100%;
    }

    /* Hide Backpack UI */
    .page-header,
    .navbar,
    .main-header,
    .sidebar,
    .app-footer,
    .breadcrumb,
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

    .select2-selection__choice {
        display: none !important;
    }

    .select2-search--inline {
        width: 100% !important;
    }

    .select2-search__field {
        width: 100% !important;
    }

    @media print {
        #accessories+.select2-container {
            display: none !important;
        }

        #accessories_count_print {
            display: inline !important;
        }

        #accessories_print {
            display: block !important;
            white-space: normal;
            word-break: break-word;
            font-size: 11px;
            line-height: 15px;
        }

        .quotation-stacked-table tr.print-hide {
            display: none !important;
        }

        .quotation-sheet,
        .quotation-wrapper,
        .bill-table {
            width: 100% !important;
        }
    }

    /* QUOTATION — HALF WIDTH, SIDE BY SIDE */
    .quotation-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        margin-bottom: 15px;
        border: 1px solid #000;
    }

    .quotation-half {
        flex: 0 0 50%;
        border-right: 1px solid #000;
    }

    .quotation-half:last-child {
        border-right: none;
    }

    .quotation-stacked-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .quotation-stacked-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        font-size: 10px;
        height: 26px;
        vertical-align: middle;
        overflow: hidden;
    }

    .quotation-stacked-table .ql-label {
        background: #f2f2f2;
        font-weight: 600;
        width: 45%;
    }

    .quotation-stacked-table .ql-amount {
        width: 55%;
        text-align: right;
    }

    .quotation-stacked-table .ql-label .group-select {
        background: #f2f2f2;
        font-weight: 600;
        width: 100%;
        border: none;
        font-size: 10px;
        padding: 2px;
    }

    .quotation-stacked-table input,
    .quotation-stacked-table select {
        width: 100%;
        border: none;
        background: transparent;
        font-size: 10px;
        padding: 2px;
        text-align: right;
    }

    .quotation-stacked-table input:focus,
    .quotation-stacked-table select:focus {
        outline: none;
    }

    /* OPTION / TYPE columns — permanently hidden */
    .quotation-stacked-table td.ql-option,
    .quotation-stacked-table td.ql-type {
        display: none !important;
    }

    /* QUOTATION SUMMARY */
    .quotation-summary {
        display: flex;
        font-weight: bold;
        border: solid 1px #000;
        margin-top: -1px;
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
        text-align: right;
    }

    .section-title {
        background: #d9d9d9;
        font-weight: 700;
        text-align: center;
        font-size: 11px;
    }

    .chassis-box {
        margin-top: 4px;
        border: 1px solid #000;
        padding: 4px;
        text-align: center;
        min-height: 160px;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .chassis-box h6 {
        font-size: 10px;
        margin-bottom: 8px;
        font-weight: 900;
    }

    #chassis_preview {
        max-width: 220px;
        max-height: 120px;
        object-fit: contain;
        margin: auto;
        display: block;
    }

    /* Receipt Table Styling */
    .receipt-table {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .receipt-table thead th {
        background: #f8f9fa !important;
        border-bottom: 2px solid #dee2e6 !important;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #495057;
        padding: 8px 10px;
    }

    .receipt-table tbody tr {
        transition: background 0.15s ease;
    }

    .receipt-table tbody tr:hover {
        background: #f8f9fa !important;
    }

    .receipt-table tbody td {
        padding: 6px 10px;
        vertical-align: middle;
        font-size: 10px;
        border-bottom: 1px solid #f0f0f0;
    }

    .receipt-table tbody tr:last-child td {
        border-bottom: none;
    }

    .receipt-table tfoot td {
        padding: 8px 10px;
        font-weight: 700;
        background: #f8f9fa;
        border-top: 2px solid #dee2e6;
    }

    .receipt-header {
        padding: 6px 4px 8px 4px;
        border-bottom: 1px dashed #dee2e6;
    }


    @media print {
        .chassis-box {
            min-height: 160px !important;
        }

        #chassis_preview {
            max-width: 200px !important;
            max-height: 110px !important;
        }

        .quotation-wrapper {
            border: 1px solid #000;
        }

        .quotation-half {
            border-right: 1px solid #000;
        }

        .quotation-half:last-child {
            border-right: none;
        }

        .quotation-sheet,
        .quotation-wrapper,
        .bill-table {
            width: 100% !important;
        }

    }

    @media (max-width: 576px) {

        .receipt-table thead th,
        .receipt-table tbody td {
            font-size: 9px;
            padding: 4px 6px;
        }

        .receipt-table .badge {
            font-size: 8px !important;
            padding: 2px 6px !important;
        }

        .receipt-header h5 {
            font-size: 10px !important;
        }
    }

    /* ================= Quotation Grid (Preview Style) ================= */
    .quotation-stacked-table td.ql-label .group-select {
        background: transparent;
        border: none;
        font-weight: 600;
        width: 100%;
        font-size: 10px;
        padding: 2px;
        cursor: pointer;
    }

    .quotation-stacked-table td.ql-label .group-select:focus {
        outline: none;
    }

    /* ================= Print & Preview Mode Styles ================= */
    @media print {

        .quotation-stacked-table td.ql-option,
        .quotation-stacked-table td.ql-type {
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
                $segment = strtoupper($booking->segment_code ?? '');
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
                            Regd. Office : Sunderi Chhabil Mansion, NH-11, Jaipur Road, P.O. Udasar, Bikaner-334022
                        </div>
                        <div style="font-size:13px;">
                            Branch Office : 6th KM Stone, Ratangarh Road, Churu (Raj.)
                        </div>
                        <h4 class="mt-2 mb-0 fw-bold text-uppercase">Transaction Sheet</h4>
                    </div>
                    <div class="col-2 text-center">
                        <img src="{{ $mahindraLogo }}" style="max-width:110px; max-height:60px;">
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('booking.otf.save', $booking->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="quotation-sheet">
                <div class="quotation-sheet">
                    <input type="hidden" id="insurance_print_data" value='{{ json_encode($insurancePrintData ?? []) }}'>
                    <input type="hidden" id="accessories_print_data"
                        value='{{ json_encode($accessoriesPrintData ?? []) }}'>
                    <!-- rest of the form -->
                </div>

                <div class="form-section">

                    <div class="row g-2">

                        {{-- ================= LEFT COLUMN ================= --}}
                        <div class="col-md-6">

                            {{-- Customer Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Vehicle Details</td>
                                </tr>
                                <tr>
                                    <td class="title">GST Number</td>
                                    <td><input type="text" name="gstn" value="{{ old('gstn', $booking->gstn) }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Customer Category</td>
                                    <td>
                                        <select name="b_cat" id="b_cat">
                                            <option value="Individual" {{ old('b_cat', $otfData['b_cat'] ?? $booking->
                                                b_cat ?? '') == 'Individual' ? 'selected' : '' }}>
                                                Individual
                                            </option>

                                            <option value="CSD-CPC" {{ old('b_cat', $otfData['b_cat'] ?? $booking->b_cat
                                                ?? '') == 'CSD-CPC' ? 'selected' : '' }}>
                                                CSD-CPC
                                            </option>

                                            <option value="Corporate" {{ old('b_cat', $otfData['b_cat'] ?? $booking->
                                                b_cat ?? '') == 'Corporate' ? 'selected' : '' }}>
                                                Corporate
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Retail Category</td>
                                    <td>
                                        <select name="retail_category" id="retail_category">
                                            <option value="">Select Retail Category</option>
                                            <option value="Normal" {{ old('retail_category', $otfData['retail_category']
                                                ?? '' )=='Normal' ? 'selected' : '' }}>
                                                Normal
                                            </option>
                                            <option value="ZACO" {{ old('retail_category', $otfData['retail_category']
                                                ?? '' )=='ZACO' ? 'selected' : '' }}>
                                                ZACO
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Segment</td>
                                    <td><input type="text" id="segment_code"
                                            value="{{ $segment->name ?? $booking->segment_code }}" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Model</td>
                                    <td><input type="text" id="model_name" value="{{ $model?->name ?? '' }}" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Variant</td>
                                    <td><input type="text" id="variant_name"
                                            value="{{ $variant?->display_name ?? $variant?->custom_name ?? $variant?->oem_name ?? '' }}"
                                            readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Color</td>
                                    <td><input type="text" id="color_name" value="{{ $color?->name ?? '' }}" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Body Type</td>
                                    <td>
                                        <select name="body_type" id="body_type">
                                            <option value="">Select Body Type</option>

                                            @foreach($body_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old( 'body_type' , $otfData['body_type'] ??
                                                $rto?->body_type ?? ''
                                                ) == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Sale Type</td>
                                    <td>
                                        <select name="sale_type" id="sale_type">
                                            <option value="">Select Sale Type</option>

                                            @foreach($sale_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old( 'sale_type' , $otfData['sale_type'] ??
                                                $rto?->sale_type ?? ''
                                                ) == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Registration Type</td>
                                    <td>
                                        <select name="registration_no_type" id="registration_no_type">
                                            <option value="">Select Registration Type</option>

                                            @foreach($reg_no_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('registration_no_type', $rto?->rgn_no_type
                                                ?? '') == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Registration Category</td>
                                    <td>
                                        <select name="registration_category" id="registration_category">
                                            <option value="">Select Category</option>
                                            @foreach($registration_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('registration_category',
                                                $otfData['registration_category'] ?? $rto?->registration_category ?? '')
                                                == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Permit</td>
                                    <td>
                                        <select name="permit" id="permit">
                                            <option value="">Select Permit</option>

                                            @foreach($permit_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('permit', $rto?->permit ?? '') == $key ?
                                                'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            {{-- Consultant Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Consultant Details</td>
                                </tr>
                                <tr>
                                    <td class="title">SC Name</td>
                                    <td>
                                        <select name="consultant" id="saleconsultant">
                                            <option value="">Select Sales Consultant</option>

                                            @foreach($salesconsultants as $consultant)
                                            <option value="{{ $consultant['person_code'] }}" {{ old('consultant',
                                                $booking->consultant ?? '') == $consultant['person_code'] ? 'selected' :
                                                '' }}>
                                                {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">SC Mile ID</td>
                                    <td><input type="text" id="sc_mile_id" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">SC Branch</td>
                                    <td><input type="text" id="sc_branch" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">SC Location</td>
                                    <td><input type="text" id="sc_location" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">DMS Enquiry Number</td>
                                    <td>
                                        <input type="text" name="dms_no" id="dms_no"
                                            value="{{ old('dms_no', $enquiry->oem_enquiry_no ?? '') }} " readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DMS OTF Number</td>
                                    <td><input type="text" name="dms_otf" id="dms_otf"
                                            value="{{ old('dms_otf', $booking->dms_otf ?? ($otfData['dms_otf'] ?? '')) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Xcler8 Booking ID</td>
                                    <td><input type="text" value="{{ $booking->id }}" readonly></td>
                                </tr>
                            </table>




                            {{-- Additional Sales Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Additional Sales Details</td>
                                </tr>
                                <tr>
                                    <td class="title">DSA Retail</td>
                                    <td>
                                        <select name="dsa_retail" id="dsa_retail">
                                            <option value="No" {{ empty($otfData['dsa_id'] ?? $booking->dsa_id ?? '') ?
                                                'selected' : '' }}>No</option>
                                            <option value="Yes" {{ !empty($otfData['dsa_id'] ?? $booking->dsa_id ?? '')
                                                ? 'selected' : '' }}>Yes</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DSA Name</td>
                                    <td>
                                        <select name="dsa_id" id="dsa_id">
                                            <option value="">Select DSA</option>
                                            @foreach($dsaList as $dsa)
                                            <option value="{{ $dsa->id }}" data-location="{{ $dsa->dlocation }}" {{
                                                ($otfData['dsa_id'] ?? $booking->dsa_id ?? '') == $dsa->id ? 'selected'
                                                : '' }}>
                                                {{ $dsa->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DSA Location</td>
                                    <td><input type="text" id="dsa_location" name="dsa_location" readonly
                                            value="{{ old('dsa_location', $otfData['dsa_location'] ?? '') }}">
                                </tr>
                                <tr>
                                    <td class="title">Exchange</td>
                                    <td>
                                        <select name="exchange" id="exchange">
                                            <option value="NA" {{ old('exchange', $booking->buyer_type ??
                                                ($otfData['exchange'] ?? '')) == 'NA' ? 'selected' : '' }}>
                                                NA
                                            </option>

                                            <option value="In-House" {{ old('exchange', $booking->buyer_type ??
                                                ($otfData['exchange'] ?? '')) == 'In-House' ? 'selected' : '' }}>
                                                In-House
                                            </option>

                                            <option value="Third Party" {{ old('exchange', $booking->buyer_type ??
                                                ($otfData['exchange'] ?? '')) == 'Third Party' ? 'selected' : '' }}>
                                                Third Party
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">In House RTO</td>
                                    <td>
                                        <select name="in_house_rto" id="in_house_rto" disabled>
                                            <option value="1" {{ $rto ? 'selected' : '' }}>Yes</option>
                                            <option value="0" {{ !$rto ? 'selected' : '' }}>No</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Accessories Items List</td>
                                    <td>
                                        <select name="accessories[]" id="accessories" multiple>
                                            @foreach($accessoryList as $accessory)
                                            <option value="{{ $accessory->part_no }}" data-price="{{ $accessory->ndp }}"
                                                {{ in_array($accessory->part_no, $selectedAccessories ?? []) ?
                                                'selected' : '' }}>
                                                {{ $accessory->item }} (₹{{ number_format($accessory->ndp,2) }})
                                            </option>
                                            @endforeach
                                        </select>
                                        <span id="accessories_count_print" style="display:none;"></span>
                                        <input type="hidden" id="accessories_amount" name="accessories_amount"
                                            value="{{ old('accessories_amount', $otfData['accessories_amount'] ?? '0.00') }}">
                                    </td>
                                </tr>
                            </table>

                            {{-- Chassis Box --}}
                            <div class="chassis-box">
                                <h6>Chassis Verification Image</h6>
                                <img id="chassis_preview" src="{{ $chassisImage ?? '' }}"
                                    style="display:{{ !empty($chassisImage) ? 'block':'none' }}; margin:auto;">
                            </div>

                        </div>

                        {{-- ================= RIGHT COLUMN ================= --}}
                        <div class="col-md-6">

                            {{-- Customer Information --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Customer Information</td>
                                </tr>
                                <tr>
                                    <td class="title">VOTF Number</td>
                                    <td>
                                        <div style="display:flex; gap:5px; align-items:center;">
                                            <input type="text"
                                                name="votf_no"
                                                id="votf_no"
                                                value="{{ old('votf_no', $otfData['votf_no'] ?? '') }}">

                                            <button type="button"
                                                    id="generate_votf"
                                                    class="btn btn-sm btn-primary no-print"
                                                    style="white-space:nowrap;">
                                                Generate
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Customer Name</td>
                                    <td><input type="text" name="customer_name" id="customer_name"
                                            value="{{ $booking->name ?? '' }}" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Registration Address</td>
                                    <td><input type="text" name="registration_address" id="registration_address"
                                            value="{{ old('registration_address', $otfData['registration_address'] ?? $booking->address ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Customer Tehsil</td>
                                    <td>
                                        <input type="text" name="customer_tehsil"
                                            value="{{ old('customer_tehsil', $otfData['customer_tehsil'] ?? $enquiry?->tehsil ?? '') }}">
                                    </td>
                                </tr>

                                <tr>
                                    <td class="title">Customer District</td>
                                    <td>
                                        <input type="text" name="customer_district"
                                            value="{{ old('customer_district', $otfData['customer_district'] ?? $enquiry?->district ?? '') }}">
                                    </td>
                                </tr>

                                <tr>
                                    <td class="title">Pincode</td>
                                    <td>
                                        <input type="text" name="pincode" class="numeric-only" maxlength="6"
                                            value="{{ old('pincode', $otfData['pincode'] ?? $enquiry?->zipcode ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Customer Contact No.</td>
                                    <td><input type="text" name="customer_mobile" id="customer_mobile"
                                            value="{{ $booking->mobile ?? '' }}" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Date of Birth</td>
                                    <td><input type="text" name="dob" id="dob" class="date-picker"
                                            value="{{ old('dob', $otfData['dob'] ?? $booking->c_dob ?? '') }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Marital Status</td>
                                    <td>
                                        <select name="marital_status" id="marital_status">
                                            <option value="">Select</option>
                                            <option value="Single" {{ old('marital_status', $otfData['marital_status']
                                                ?? $booking->marital_status ?? '') == 'Single' ? 'selected' : ''
                                                }}>Single</option>
                                            <option value="Married" {{ old('marital_status', $otfData['marital_status']
                                                ?? $booking->marital_status ?? '') == 'Married' ? 'selected' : ''
                                                }}>Married</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="anniversary_row">
                                    <td class="title">Date of Anniversary</td>
                                    <td><input type="text" name="anniversary_date" id="anniversary_date"
                                            class="date-picker"
                                            value="{{ old('anniversary_date', $otfData['anniversary_date'] ?? $booking->anniversary_date ?? '') }}">
                                    </td>
                                </tr> 
                                <tr>
                                    <td class="title">Email ID</td>
                                    <td><input type="email" name="email" id="email"
                                            value="{{ old('email', $otfData['email'] ?? $booking->email ?? '') }}"></td>
                                </tr>  
                            </table>

                            {{-- Contact & Personal Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Contact & Personal Details</td>
                                </tr>
                                <tr>
                                    <td class="title">Contact Person (If Any Other)</td>
                                    <td><input type="text" name="contact_person" id="contact_person"
                                            value="{{ old('contact_person', $otfData['contact_person'] ?? $booking->contact_person ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Contact Person Contact No.</td>
                                    <td><input type="text" name="contact_person_mobile" id="contact_person_mobile"
                                            value="{{ old('contact_person_mobile', $otfData['contact_person_mobile'] ?? $booking->contact_person_mobile ?? '') }}"
                                            maxlength="10" inputmode="numeric"
                                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);"></td>
                                </tr>
                                
                                
                                
                            </table>

                            {{-- KYC & Nominee --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">KYC & Nominee Details</td>
                                </tr>
                                <tr>
                                    <td class="title">PAN No.</td>
                                    <td>
                                        <input type="text" name="pan_no" id="pan_no"
                                            value="{{ old('pan_no', $booking->pan_no ?? ($otfData['pan_no'] ?? '')) }}"
                                            maxlength="10" style="text-transform:uppercase"
                                            oninput="this.value=this.value.toUpperCase();">
                                    </td>
                                </tr>

                                <tr>
                                    <td class="title">Aadhaar No.</td>
                                    <td>
                                        <input type="text" name="adhar_no" id="adhar_no"
                                            value="{{ old('adhar_no', $booking->adhar_no ?? ($otfData['adhar_no'] ?? '')) }}"
                                            maxlength="12" inputmode="numeric"
                                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,12);">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Driving License No.</td>
                                    <td><input type="text" name="driving_license_no" id="driving_license_no"
                                            value="{{ old('driving_license_no', $otfData['driving_license_no'] ?? '') }}"
                                            style="text-transform:uppercase"
                                            oninput="this.value=this.value.toUpperCase();"></td>
                                </tr>
                                <tr>
                                    <td class="title">Voter ID No.</td>
                                    <td><input type="text" name="voter_id_no" id="voter_id_no"
                                            value="{{ old('voter_id_no', $otfData['voter_id_no'] ?? '') }}"
                                            style="text-transform:uppercase"
                                            oninput="this.value=this.value.toUpperCase();"></td>
                                </tr>
                                <tr>
                                    <td class="title">Nominee Name (For Insurance)</td>
                                    <td><input type="text" name="nominee_name" id="nominee_name"
                                            value="{{ old('nominee_name', $otfData['nominee_name'] ?? '') }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Relation with Nominee</td>
                                    <td>
                                        <select name="nominee_relation" id="nominee_relation">
                                            <option value="">Select Relation</option>
                                            <option value="Spouse" {{ old('nominee_relation',
                                                $otfData['nominee_relation'] ?? '' )=='Spouse' ? 'selected' : '' }}>
                                                Spouse</option>
                                            <option value="Brother" {{ old('nominee_relation',
                                                $otfData['nominee_relation'] ?? '' )=='Brother' ? 'selected' : '' }}>
                                                Brother</option>
                                            <option value="Mother" {{ old('nominee_relation',
                                                $otfData['nominee_relation'] ?? '' )=='Mother' ? 'selected' : '' }}>
                                                Mother</option>
                                            <option value="Father" {{ old('nominee_relation',
                                                $otfData['nominee_relation'] ?? '' )=='Father' ? 'selected' : '' }}>
                                                Father</option>
                                            <option value="Sister" {{ old('nominee_relation',
                                                $otfData['nominee_relation'] ?? '' )=='Sister' ? 'selected' : '' }}>
                                                Sister</option>
                                            <option value="Son" {{ old('nominee_relation', $otfData['nominee_relation']
                                                ?? '' )=='Son' ? 'selected' : '' }}>Son</option>
                                            <option value="Daughter" {{ old('nominee_relation',
                                                $otfData['nominee_relation'] ?? '' )=='Daughter' ? 'selected' : '' }}>
                                                Daughter</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Age of Nominee</td>
                                    <td>
                                        <input type="text" name="nominee_age" id="nominee_age" class="numeric-only"
                                            min="0" max="120" maxlength="3"
                                            value="{{ old('nominee_age', $otfData['nominee_age'] ?? '') }}">
                                    </td>
                                </tr>
                            </table>



                            {{-- Vehicle Delivery / Invoice Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Vehicle Delivery / Invoice Details</td>
                                </tr>
                                <tr>
                                    <td class="title">Chassis Number</td>
                                    <td>
                                        <input type="text" name="chassis" id="chassis_no_display"
                                            value="{{ old('chassis', $booking->chassis_no ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Engine Number</td>
                                    <td><input type="text" name="engine_no" id="engine_no"
                                            value="{{ old('engine_no', $otfData['engine_no'] ?? '') }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Chassis Image</td>
                                    <td><input type="file" id="chassis_image" name="chassis_image"></td>
                                </tr>
                                <tr>
                                    <td class="title">OEM Model Code</td>
                                    <td><input type="text" name="oem_model_code" id="oem_model_code"
                                            value="{{ old('oem_model_code', $quotation->oem_model_code ?? $variant?->oem_name ?? '') }}"
                                            readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">GST Slab</td>
                                    <td>
                                        <input type="text" name="gst_slab" id="gst_slab"
                                            value="{{ old('gst_slab', $otfData['gst_slab'] ?? $variant?->gst_slab ?? '') }}"
                                            readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Invoice No.</td>
                                    <td><input type="text" name="inv_no" id="inv_no"
                                            value="{{ old('inv_no', $booking->inv_no ?? '') }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Invoice Date</td>
                                    <td>
                                        <input type="text" name="invoice_date_display" id="invoice_date"
                                            class="flatpickr" placeholder="dd-MMM-yyyy" value="{{ old('invoice_date_display',
                    $booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('d-M-Y') : '') }}">

                                        <input type="hidden" name="inv_date" id="hidden_invoice_date"
                                            value="{{ old('inv_date', $booking->inv_date) }}">
                                    </td>
                                </tr>
                            </table>

                        </div>

                    </div> <!-- /.row -->

                    <div class="row g-2">
                        <div class="col-md-6">
                            {{-- ================ PRICE DETAILS ================ --}}
                            <div class="quotation-half mt-5">
                                <table class="quotation-stacked-table">
                                    <thead>
                                        <tr>
                                            <td colspan="2"
                                                style="background:#d9d9d9; font-weight:bold; text-align:center; border:1px solid #000;">
                                                PRICE DETAILS
                                            </td>
                                        </tr>
                                    </thead>
                                    <tbody id="price-details-body">
                                        {{-- Ex-Showroom Price --}}
                                        @php $exShowroom = $otfData['ex_showroom_price'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">Ex-Showroom Price</td>
                                            <td class="ql-amount">
                                                <input name="ex_showroom_price" id="ex_showroom_price"
                                                    class="numeric-only"
                                                    value="{{ old('ex_showroom_price', $exShowroom) }}">
                                            </td>
                                        </tr>

                                        {{-- Insurance --}}
                                        @php
                                        $insuranceAmount = $otfData['insurance_amount'] ?? '';
                                        $policyType = $otfData['policy_type'] ?? '';
                                        $insuranceCompany = $otfData['insurance_company'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Insurance
                                                <span id="insurance_option_label" class="fw-bold ms-1">
                                                    @if($insuranceCompany)
                                                    ({{ $insuranceCompany }})
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="insurance_amount" name="insurance_amount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('insurance_amount', $insuranceAmount) }}">
                                                <select name="policy_type" id="policy_type" style="display:none;">
                                                    @foreach($insurance_type_map as $key=>$value)
                                                    <option value="{{ $key }}" {{ old('policy_type', $policyType)==$key
                                                        ? 'selected' : '' }}>
                                                        {{ $value }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <select name="insurance_company" id="insurance_company"
                                                    style="display:none;">
                                                    <option value="">Select</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Registration --}}
                                        @php
                                        $registrationAmount = $otfData['registration_amount'] ?? '';
                                        $registrationType = $otfData['registration_type'] ?? '';
                                        $regNoType = $otfData['registration_no_type'] ?? '';
                                        $regCategory = $otfData['registration_category'] ?? '';
                                        $inHouseRto = $otfData['in_house_rto'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Registration
                                                <span id="registration_option_label" class="fw-bold ms-1">
                                                    @if($regNoType || $regCategory || $inHouseRto !== '')
                                                    ({{ $regNoType ? $reg_no_type_map[$regNoType] ?? '' : '' }}
                                                    {{ $regCategory ? $registration_category_map[$regCategory] ?? '' :
                                                    '' }}
                                                    {{ $inHouseRto !== '' ? 'In-House: ' . ($inHouseRto == '1' ? 'Yes' :
                                                    'No') : '' }})
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="registration_amount" name="registration_amount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('registration_amount', $registrationAmount) }}">
                                                <select name="registration_type" id="registration_type"
                                                    style="display:none;">
                                                    @foreach($registration_type_map as $key=>$value)
                                                    <option value="{{ $key }}" {{ old('registration_type',
                                                        $registrationType)==$key ? 'selected' : '' }}>
                                                        {{ $value }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <select name="registration_no_type" id="registration_no_type"
                                                    style="display:none;">
                                                    @foreach($reg_no_type_map as $key=>$value)
                                                    <option value="{{ $key }}" {{ old('registration_no_type',
                                                        $regNoType)==$key ? 'selected' : '' }}>
                                                        {{ $value }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <select name="registration_category" id="registration_category"
                                                    style="display:none;">
                                                    @foreach($registration_category_map as $key=>$value)
                                                    <option value="{{ $key }}" {{ old('registration_category',
                                                        $regCategory)==$key ? 'selected' : '' }}>
                                                        {{ $value }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <input type="hidden" name="in_house_rto" id="in_house_rto"
                                                    value="{{ $inHouseRto }}">
                                            </td>
                                        </tr>

                                        {{-- Accessories --}}
                                        @php
                                        $accessoriesAmount = $otfData['accessories_amount'] ?? '0.00';
                                        $selectedAccessories = $selectedAccessories ?? [];
                                        $accessoryNames = [];
                                        foreach($selectedAccessories as $accCode) {
                                        $acc = $accessoryList->firstWhere('part_no', $accCode);
                                        if($acc) $accessoryNames[] = $acc->item;
                                        }
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Accessories
                                                <span id="accessories_price_label" class="fw-bold ms-1">
                                                    @if(count($accessoryNames) > 0)
                                                    ({{ count($accessoryNames) }} items)
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input id="accessories_amount" name="accessories_amount"
                                                    class="numeric-only" readonly
                                                    value="{{ old('accessories_amount', $accessoriesAmount) }}">
                                                <select name="accessories[]" id="accessories" multiple
                                                    style="display:none;">
                                                    @foreach($accessoryList as $accessory)
                                                    <option value="{{ $accessory->part_no }}"
                                                        data-price="{{ $accessory->ndp }}" {{ in_array($accessory->
                                                        part_no, $selectedAccessories) ? 'selected' : '' }}>
                                                        {{ $accessory->item }} (₹{{ number_format($accessory->ndp,2) }})
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Maxicare --}}
                                        @php $maxicare = $otfData['maxicare'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">Maxicare</td>
                                            <td class="ql-amount">
                                                <input id="maxicare" name="maxicare" class="numeric-only"
                                                    value="{{ old('maxicare', $maxicare) }}">
                                            </td>
                                        </tr>

                                        {{-- VLTD Device --}}
                                        @php $vltdDevice = $otfData['vltd_device'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">VLTD Device (GPS)</td>
                                            <td class="ql-amount">
                                                <input id="vltd_device" name="vltd_device" class="numeric-only"
                                                    value="{{ old('vltd_device', $vltdDevice) }}">
                                            </td>
                                        </tr>

                                        {{-- Coating --}}
                                        @php
                                        $coatingPrice = $otfData['coating_price'] ?? '';
                                        $coating = $otfData['coating'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Coating
                                                <span id="coating_option_label" class="fw-bold ms-1">
                                                    @if($coating && $coating != 'No Coating')
                                                    ({{ $coating }})
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input id="coating_price" name="coating_price" class="numeric-only"
                                                    value="{{ old('coating_price', $coatingPrice) }}">
                                                <select id="coating" name="coating" style="display:none;">
                                                    <option value="No Coating" {{ old('coating', $coating)=='No Coating'
                                                        ? 'selected' : '' }}>No Coating</option>
                                                    <option value="Ceramic" {{ old('coating', $coating)=='Ceramic'
                                                        ? 'selected' : '' }}>Ceramic</option>
                                                    <option value="Graphene" {{ old('coating', $coating)=='Graphene'
                                                        ? 'selected' : '' }}>Graphene</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- PPF --}}
                                        @php $ppf = $otfData['ppf'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">PPF</td>
                                            <td class="ql-amount">
                                                <input id="ppf" name="ppf" class="numeric-only"
                                                    value="{{ old('ppf', $ppf) }}">
                                            </td>
                                        </tr>

                                        {{-- RTO Yellow Tape --}}
                                        @php $rtoYellowTape = $otfData['rto_yellow_tape'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">RTO Yellow Tape</td>
                                            <td class="ql-amount">
                                                <input id="rto_yellow_tape" name="rto_yellow_tape" class="numeric-only"
                                                    value="{{ old('rto_yellow_tape', $rtoYellowTape) }}">
                                            </td>
                                        </tr>

                                        {{-- Kazam Charging Kit --}}
                                        @php $kazamChargingKit = $otfData['kazam_charging_kit'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">Kazam Charging Kit</td>
                                            <td class="ql-amount">
                                                <input id="kazam_charging_kit" name="kazam_charging_kit"
                                                    class="numeric-only"
                                                    value="{{ old('kazam_charging_kit', $kazamChargingKit) }}">
                                            </td>
                                        </tr>

                                        {{-- Incidental Charges --}}
                                        @php $incidentalCharges = $otfData['incidental_charges'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">Incidental Charges</td>
                                            <td class="ql-amount">
                                                <input id="incidental_charges" name="incidental_charges"
                                                    class="numeric-only"
                                                    value="{{ old('incidental_charges', $incidentalCharges) }}">
                                            </td>
                                        </tr>

                                        {{-- Shield --}}
                                        @php
                                        $shieldPrice = $otfData['shield_price'] ?? '';
                                        $shield = $otfData['shield'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Shield
                                                <span id="shield_option_label" class="fw-bold ms-1">
                                                    @if($shield && $shield != 'No Shield')
                                                    ({{ $shield }})
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input id="shield_price" name="shield_price" class="numeric-only"
                                                    value="{{ old('shield_price', $shieldPrice) }}">
                                                <select id="shield" name="shield" style="display:none;">
                                                    <option value="4th Year" {{ old('shield', $shield)=='4th Year'
                                                        ? 'selected' : '' }}>4th Year</option>
                                                    <option value="4th + 5th Year" {{ old('shield',
                                                        $shield)=='4th + 5th Year' ? 'selected' : '' }}>4th + 5th Year
                                                    </option>
                                                    <option value="No Shield" {{ old('shield', $shield)=='No Shield'
                                                        ? 'selected' : '' }}>No Shield</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- RSA --}}
                                        @php
                                        $rsaAmount = $otfData['rsa_amount'] ?? '';
                                        $rsa = $otfData['rsa'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                RSA
                                                <span id="rsa_option_label" class="fw-bold ms-1">
                                                    @if($rsa && $rsa != 'No RSA')
                                                    ({{ $rsa }})
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input id="rsa_amount" name="rsa_amount" class="numeric-only"
                                                    value="{{ old('rsa_amount', $rsaAmount) }}">
                                                <select id="rsa" name="rsa" style="display:none;">
                                                    <option value="1 Year" {{ old('rsa', $rsa)=='1 Year' ? 'selected'
                                                        : '' }}>1 Year</option>
                                                    <option value="2 Year" {{ old('rsa', $rsa)=='2 Year' ? 'selected'
                                                        : '' }}>2 Year</option>
                                                    <option value="3 Year" {{ old('rsa', $rsa)=='3 Year' ? 'selected'
                                                        : '' }}>3 Year</option>
                                                    <option value="4 Year" {{ old('rsa', $rsa)=='4 Year' ? 'selected'
                                                        : '' }}>4 Year</option>
                                                    <option value="5 Year" {{ old('rsa', $rsa)=='5 Year' ? 'selected'
                                                        : '' }}>5 Year</option>
                                                    <option value="No RSA" {{ old('rsa', $rsa)=='No RSA' ? 'selected'
                                                        : '' }}>No RSA</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Fastag --}}
                                        @php $fastag = $otfData['fastag'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">Fastag</td>
                                            <td class="ql-amount">
                                                <input id="fastag" name="fastag" class="numeric-only"
                                                    value="{{ old('fastag', $fastag) }}">
                                            </td>
                                        </tr>

                                        {{-- COD Charges --}}
                                        @php $codCharges = $otfData['cod_charges'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">COD Charges</td>
                                            <td class="ql-amount">
                                                <input id="cod_charges" name="cod_charges" class="numeric-only"
                                                    value="{{ old('cod_charges', $codCharges) }}">
                                            </td>
                                        </tr>

                                        {{-- Charger Swapping --}}
                                        @php
                                        $chargerSwappingAmount = $otfData['charger_swapping_amount'] ?? '';
                                        $chargerSwapping = $otfData['charger_swapping'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Charger Swapping
                                                <span id="charger_swapping_option_label" class="fw-bold ms-1">
                                                    @if($chargerSwapping && $chargerSwapping != 'N/A')
                                                    ({{ $chargerSwapping }})
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="ql-amount">
                                                <input id="charger_swapping_amount" name="charger_swapping_amount"
                                                    class="numeric-only"
                                                    value="{{ old('charger_swapping_amount', $chargerSwappingAmount) }}">
                                                <select id="charger_swapping" name="charger_swapping"
                                                    style="display:none;">
                                                    <option value="N/A" {{ old('charger_swapping',
                                                        $chargerSwapping)=='N/A' ? 'selected' : '' }}>N/A</option>
                                                    <option value="NCH to 7.2 kW" {{ old('charger_swapping',
                                                        $chargerSwapping)=='NCH to 7.2 kW' ? 'selected' : '' }}>NCH to
                                                        7.2 kW</option>
                                                    <option value="NCH to 11.2 kW" {{ old('charger_swapping',
                                                        $chargerSwapping)=='NCH to 11.2 kW' ? 'selected' : '' }}>NCH to
                                                        11.2 kW</option>
                                                    <option value="7.2 kW to 11.2 kW" {{ old('charger_swapping',
                                                        $chargerSwapping)=='7.2 kW to 11.2 kW' ? 'selected' : '' }}>7.2
                                                        kW to 11.2 kW</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- TCS --}}
                                        @php $tcs = $otfData['tcs'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">TCS @1%</td>
                                            <td class="ql-amount">
                                                <input id="tcs" name="tcs" class="numeric-only" readonly
                                                    value="{{ old('tcs', $tcs) }}">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                {{-- ================ DISCOUNT DETAILS ================ --}}
                                {{-- ================ DISCOUNT DETAILS ================ --}}
                                <table class="quotation-stacked-table">
                                    <thead>
                                        <tr>
                                            <td colspan="2"
                                                style="background:#d9d9d9; font-weight:bold; text-align:center; border:1px solid #000;">
                                                DISCOUNT DETAILS
                                            </td>
                                        </tr>
                                    </thead>
                                    <tbody id="discount-details-body">

                                        {{-- Group A --}}
                                        @php
                                        $cashSchemeOem = $otfData['cash_scheme_oem'] ?? '';
                                        $csdDiscount = $otfData['csd_discount'] ?? '';
                                        $fameSubsidy = $otfData['fame_subsidy'] ?? '';
                                        $groupASelected = $groupASelected ?? 'cash_scheme_oem';
                                        $groupAType = $otfData['cash_scheme_oem_type'] ?? ($otfData['csd_discount_type']
                                        ?? ($otfData['fame_subsidy_type'] ?? 'INV'));
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                <select id="group_a_select" class="group-select"
                                                    style="background:transparent; border:none; font-weight:600; width:100%;">
                                                    <option value="cash_scheme_oem" {{ old('group_a_select',
                                                        $groupASelected)=='cash_scheme_oem' ? 'selected' : '' }}>
                                                        Cash Scheme OEM
                                                    </option>
                                                    <option value="csd_discount" {{ old('group_a_select',
                                                        $groupASelected)=='csd_discount' ? 'selected' : '' }}>
                                                        CSD Discount
                                                    </option>
                                                    <option value="fame_subsidy" id="fame_subsidy_option" {{
                                                        old('group_a_select', $groupASelected)=='fame_subsidy'
                                                        ? 'selected' : '' }}>
                                                        Fame Subsidy (LMM)
                                                    </option>
                                                </select>
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="group_a_amount" class="numeric-only"
                                                    placeholder="0.00"
                                                    value="{{ old('group_a_amount', $cashSchemeOem ?: $csdDiscount ?: $fameSubsidy ?: '') }}">
                                                <select id="group_a_type" style="display:none;">
                                                    <option value="INV" {{ $groupAType=='INV' ? 'selected' : '' }}>INV
                                                    </option>
                                                    <option value="CN" {{ $groupAType=='CN' ? 'selected' : '' }}>CN
                                                    </option>
                                                </select>
                                                <input type="hidden" id="cash_scheme_oem" name="cash_scheme_oem"
                                                    value="{{ old('cash_scheme_oem', $cashSchemeOem) }}">
                                                <input type="hidden" id="cash_scheme_oem_type"
                                                    name="cash_scheme_oem_type"
                                                    value="{{ old('cash_scheme_oem_type', $otfData['cash_scheme_oem_type'] ?? '') }}">
                                                <input type="hidden" id="csd_discount" name="csd_discount"
                                                    value="{{ old('csd_discount', $csdDiscount) }}">
                                                <input type="hidden" id="csd_discount_type" name="csd_discount_type"
                                                    value="{{ old('csd_discount_type', $otfData['csd_discount_type'] ?? '') }}">
                                                <input type="hidden" id="fame_subsidy" name="fame_subsidy"
                                                    value="{{ old('fame_subsidy', $fameSubsidy) }}">
                                                <input type="hidden" id="fame_subsidy_type" name="fame_subsidy_type"
                                                    value="{{ old('fame_subsidy_type', $otfData['fame_subsidy_type'] ?? '') }}">
                                            </td>
                                        </tr>

                                        {{-- Cash Scheme Dealer --}}
                                        @php $dealerDiscount = $otfData['dealer_discount'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Cash Scheme Dealer
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="dealer_discount" id="dealer_discount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('dealer_discount', $dealerDiscount) }}">
                                                <select id="dealer_discount_type" name="dealer_discount_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('dealer_discount_type',
                                                        $otfData['dealer_discount_type'] ?? '' )=='INV' ? 'selected'
                                                        : '' }}>INV</option>
                                                    <option value="CN" {{ old('dealer_discount_type',
                                                        $otfData['dealer_discount_type'] ?? '' )=='CN' ? 'selected' : ''
                                                        }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Accessories Scheme --}}
                                        @php $accessoriesDiscount = $otfData['accessories_discount'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Accessories Scheme
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="accessories_discount" id="accessories_discount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('accessories_discount', $accessoriesDiscount) }}">
                                                <select id="accessories_discount_type" name="accessories_discount_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('accessories_discount_type',
                                                        $otfData['accessories_discount_type'] ?? '' )=='INV'
                                                        ? 'selected' : '' }}>INV</option>
                                                    <option value="CN" {{ old('accessories_discount_type',
                                                        $otfData['accessories_discount_type'] ?? '' )=='CN' ? 'selected'
                                                        : '' }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Shield Scheme --}}
                                        @php $shieldScheme = $otfData['shield_scheme'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Shield Scheme
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="shield_scheme" id="shield_scheme"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('shield_scheme', $shieldScheme) }}">
                                                <select id="shield_scheme_type" name="shield_scheme_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('shield_scheme_type',
                                                        $otfData['shield_scheme_type'] ?? '' )=='INV' ? 'selected' : ''
                                                        }}>INV</option>
                                                    <option value="CN" {{ old('shield_scheme_type',
                                                        $otfData['shield_scheme_type'] ?? '' )=='CN' ? 'selected' : ''
                                                        }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Group B --}}
                                        @php
                                        $corporateDiscount = $otfData['corporate_discount'] ?? '';
                                        $loyaltyBonus = $otfData['loyalty_bonus'] ?? '';
                                        $groupBSelected = $groupBSelected ?? 'corporate_discount';
                                        $groupBType = $otfData['corporate_discount_type'] ??
                                        ($otfData['loyalty_bonus_type'] ?? 'INV');
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                <select id="group_b_select" class="group-select"
                                                    style="background:transparent; border:none; font-weight:600; width:100%;">
                                                    <option value="corporate_discount" {{ old('group_b_select',
                                                        $groupBSelected)=='corporate_discount' ? 'selected' : '' }}>
                                                        Corporate Discount
                                                    </option>
                                                    <option value="loyalty_bonus" {{ old('group_b_select',
                                                        $groupBSelected)=='loyalty_bonus' ? 'selected' : '' }}>
                                                        Loyalty Bonus
                                                    </option>
                                                </select>
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="group_b_amount" class="numeric-only"
                                                    placeholder="0.00"
                                                    value="{{ old('group_b_amount', $corporateDiscount ?: $loyaltyBonus ?: '') }}">
                                                <select id="group_b_type" style="display:none;">
                                                    <option value="INV">INV</option>
                                                </select>
                                                <input type="hidden" id="corporate_discount" name="corporate_discount"
                                                    value="{{ old('corporate_discount', $corporateDiscount) }}">
                                                <input type="hidden" id="corporate_discount_type"
                                                    name="corporate_discount_type"
                                                    value="{{ old('corporate_discount_type', $otfData['corporate_discount_type'] ?? '') }}">
                                                <input type="hidden" id="loyalty_bonus" name="loyalty_bonus"
                                                    value="{{ old('loyalty_bonus', $loyaltyBonus) }}">
                                                <input type="hidden" id="loyalty_bonus_type" name="loyalty_bonus_type"
                                                    value="{{ old('loyalty_bonus_type', $otfData['loyalty_bonus_type'] ?? '') }}">
                                            </td>
                                        </tr>

                                        {{-- Group C --}}
                                        @php
                                        $exchangeBonus = $otfData['exchange_bonus'] ?? '';
                                        $greenBonus = $otfData['green_bonus'] ?? '';
                                        $welcomeBonus = $otfData['welcome_bonus'] ?? '';
                                        $groupCSelected = $groupCSelected ?? 'exchange_bonus';
                                        $groupCType = $otfData['exchange_bonus_type'] ?? ($otfData['green_bonus_type']
                                        ?? ($otfData['welcome_bonus_type'] ?? 'CN1'));
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                <select id="group_c_select" class="group-select"
                                                    style="background:transparent; border:none; font-weight:600; width:100%;">
                                                    <option value="exchange_bonus" {{ old('group_c_select',
                                                        $groupCSelected)=='exchange_bonus' ? 'selected' : '' }}>
                                                        Exchange Bonus
                                                    </option>
                                                    <option value="green_bonus" {{ old('group_c_select',
                                                        $groupCSelected)=='green_bonus' ? 'selected' : '' }}>
                                                        Green Bonus
                                                    </option>
                                                    <option value="welcome_bonus" {{ old('group_c_select',
                                                        $groupCSelected)=='welcome_bonus' ? 'selected' : '' }}>
                                                        Welcome Bonus
                                                    </option>
                                                </select>
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="group_c_amount" class="numeric-only"
                                                    placeholder="0.00"
                                                    value="{{ old('group_c_amount', $exchangeBonus ?: $greenBonus ?: $welcomeBonus ?: '') }}">
                                                <select id="group_c_type" style="display:none;">
                                                    <option value="CN1">CN1</option>
                                                </select>
                                                <input type="hidden" id="exchange_bonus" name="exchange_bonus"
                                                    value="{{ old('exchange_bonus', $exchangeBonus) }}">
                                                <input type="hidden" id="exchange_bonus_type" name="exchange_bonus_type"
                                                    value="{{ old('exchange_bonus_type', $otfData['exchange_bonus_type'] ?? '') }}">
                                                <input type="hidden" id="green_bonus" name="green_bonus"
                                                    value="{{ old('green_bonus', $greenBonus) }}">
                                                <input type="hidden" id="green_bonus_type" name="green_bonus_type"
                                                    value="{{ old('green_bonus_type', $otfData['green_bonus_type'] ?? '') }}">
                                                <input type="hidden" id="welcome_bonus" name="welcome_bonus"
                                                    value="{{ old('welcome_bonus', $welcomeBonus) }}">
                                                <input type="hidden" id="welcome_bonus_type" name="welcome_bonus_type"
                                                    value="{{ old('welcome_bonus_type', $otfData['welcome_bonus_type'] ?? '') }}">
                                            </td>
                                        </tr>

                                        {{-- Accessories Spl Disc --}}
                                        @php $accessoriesSplDisc = $otfData['accessories_spl_disc'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Accessories Spl Disc
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="accessories_spl_disc" id="accessories_spl_disc"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('accessories_spl_disc', $accessoriesSplDisc) }}">
                                                <select id="accessories_spl_disc_type" name="accessories_spl_disc_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('accessories_spl_disc_type',
                                                        $otfData['accessories_spl_disc_type'] ?? '' )=='INV'
                                                        ? 'selected' : '' }}>INV</option>
                                                    <option value="CN" {{ old('accessories_spl_disc_type',
                                                        $otfData['accessories_spl_disc_type'] ?? '' )=='CN' ? 'selected'
                                                        : '' }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Coating Spl Discount --}}
                                        @php $ceramicDiscount = $otfData['ceramic_discount'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label" id="coating_discount_label">
                                                {{ $coating && $coating != 'No Coating' ? $coating . ' ' : '' }}Coating
                                                Spl Discount
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="ceramic_discount" id="ceramic_discount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('ceramic_discount', $ceramicDiscount) }}">
                                                <select id="ceramic_discount_type" name="ceramic_discount_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('ceramic_discount_type',
                                                        $otfData['ceramic_discount_type'] ?? '' )=='INV' ? 'selected'
                                                        : '' }}>INV</option>
                                                    <option value="CN" {{ old('ceramic_discount_type',
                                                        $otfData['ceramic_discount_type'] ?? '' )=='CN' ? 'selected'
                                                        : '' }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- PPF Spl Discount --}}
                                        @php $ppfDiscount = $otfData['ppf_discount'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                PPF Spl Discount
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="ppf_discount" id="ppf_discount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('ppf_discount', $ppfDiscount) }}">
                                                <select id="ppf_discount_type" name="ppf_discount_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('ppf_discount_type',
                                                        $otfData['ppf_discount_type'] ?? '' )=='INV' ? 'selected' : ''
                                                        }}>INV</option>
                                                    <option value="CN" {{ old('ppf_discount_type',
                                                        $otfData['ppf_discount_type'] ?? '' )=='CN' ? 'selected' : ''
                                                        }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Charger Swapping Discount --}}
                                        @php $chargerSwappingDiscount = $otfData['charger_swapping_discount'] ?? '';
                                        @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label" id="charger_discount_title">
                                                Charger Swapping Discount
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount" id="charger_discount_cell">
                                                <input type="text" id="charger_swapping_discount"
                                                    name="charger_swapping_discount" class="numeric-only"
                                                    placeholder="0.00"
                                                    value="{{ old('charger_swapping_discount', $chargerSwappingDiscount) }}">
                                                <select id="charger_swapping_discount_type"
                                                    name="charger_swapping_discount_type" style="display:none;"
                                                    disabled>
                                                    <option value="CN2" {{ old('charger_swapping_discount_type',
                                                        $otfData['charger_swapping_discount_type'] ?? '' )=='CN2'
                                                        ? 'selected' : '' }}>CN2</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Other Cash Discount --}}
                                        @php $otherCashDiscount = $otfData['other_cash_discount'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Other Cash Discount
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="other_cash_discount" id="other_cash_discount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('other_cash_discount', $otherCashDiscount) }}">
                                                <select id="other_cash_discount_type" name="other_cash_discount_type"
                                                    style="display:none;">
                                                    <option value="INV" {{ old('other_cash_discount_type',
                                                        $otfData['other_cash_discount_type'] ?? '' )=='INV' ? 'selected'
                                                        : '' }}>INV</option>
                                                    <option value="CN" {{ old('other_cash_discount_type',
                                                        $otfData['other_cash_discount_type'] ?? '' )=='CN' ? 'selected'
                                                        : '' }}>CN</option>
                                                </select>
                                            </td>
                                        </tr>

                                        {{-- Special Cash Discount --}}
                                        @php $specialCashDiscount = $otfData['special_cash_discount'] ?? ''; @endphp
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                Special Cash Discount
                                                {{-- REMOVED: Type label --}}
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" name="special_cash_discount"
                                                    id="special_cash_discount" class="numeric-only" placeholder="0.00"
                                                    value="{{ old('special_cash_discount', $specialCashDiscount) }}">
                                                <select id="special_cash_discount_type"
                                                    name="special_cash_discount_type" style="display:none;">
                                                    <option value="INV" {{ old('special_cash_discount_type',
                                                        $otfData['special_cash_discount_type'] ?? '' )=='INV'
                                                        ? 'selected' : '' }}>INV</option>
                                                </select>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                                {{-- TOTAL DISCOUNT & TOTAL RECEIVABLE & NET RECEIVABLE --}}
                                <table class="bill-table" style="margin-top:-1px; border-top:1px solid #000;">
                                    <tr>
                                        <td class="title"
                                            style="width:50%; background:#f2f2f2; font-weight:bold; font-size:10px; border-right:1px solid #000;">
                                            TOTAL DISCOUNT
                                        </td>
                                        <td style="width:50%; padding:3px 5px;">
                                            <input id="total_discount_amount" readonly
                                                style="width:100%; border:none; background:transparent; font-size:10px; font-weight:bold; text-align:right;"
                                                value="{{ old('total_discount_amount', $otfData['total_discount_amount'] ?? '') }}">
                                            <input type="hidden" id="total_discount" name="total_discount"
                                                value="{{ old('total_discount', $otfData['total_discount'] ?? '') }}">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="title"
                                            style="width:50%; background:#f2f2f2; font-weight:bold; font-size:10px; border-right:1px solid #000;">
                                            TOTAL RECEIVABLE
                                        </td>
                                        <td style="width:50%; padding:3px 5px;">
                                            <input id="total_receivable" name="total_receivable" readonly
                                                style="width:100%; border:none; background:transparent; font-size:10px; font-weight:bold; text-align:right;"
                                                value="{{ old('total_receivable', $otfData['total_receivable'] ?? '') }}">
                                        </td>
                                    </tr>
                                </table>
                                <table class="bill-table" style="margin-top:-1px; border-top:1px solid #000;">
                                    <tr>
                                        <td class="title"
                                            style="width:50%; background:#abb8ca; font-weight:bold; font-size:10px; border-right:1px solid #000; color:#000;">
                                            NET RECEIVABLE
                                        </td>
                                        <td style="width:50%; padding:3px 5px; background:#abb8ca; color:#000;">
                                            <input id="net_receivable_summary" name="net_receivable_summary" readonly
                                                style="width:100%; border:none; background:transparent; font-size:10px; font-weight:bold; text-align:right;"
                                                value="{{ old('net_receivable_summary', $otfData['net_receivable_summary'] ?? '') }}">
                                        </td>
                                    </tr>
                                </table>
                            </div>

                        </div>
                        <div class="col-md-6">
                            {{-- Financier Details --}}
                            <table class="bill-table mt-5">
                                <tr>
                                    <td colspan="2" class="section-title">Financier Details</td>
                                </tr>
                                <tr>
                                    <td class="title">Financier Name</td>
                                    <td>
                                        <input type="text" id="financier_name_display"
                                            value="{{ $finance?->financier ? \App\Models\Module\Booking\XlFinancier::find($finance->financier)?->name : $financierName }}"
                                            readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Financier Branch</td>
                                    <td>
                                        <input type="text" id="financier_branch" name="financier_branch"
                                            value="{{ old('financier_branch', $otfData['financier_branch'] ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Loan Amount</td>
                                    <td>
                                        <input id="loan_amount" name="loan_amount"
                                            value="{{ old('loan_amount', $finance->loan_amount ?? 0) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">File Charge / Processing</td>
                                    <td>
                                        <input id="file_charge" name="file_charge"
                                            value="{{ old('file_charge', $finance->file_charge ?? 0) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Payment Made to Financier by Customer (If Any)</td>
                                    <td>
                                        <input id="margin_money" name="margin_money"
                                            value="{{ old('margin_money', $finance->margin ?? 0) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Financier Subvention Amount (If Any)</td>
                                    <td>
                                        <input id="financier_subvention" name="financier_subvention"
                                            value="{{ old('financier_subvention', $finance?->subvention_amount ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Amount</td>
                                    <td>
                                        <input type="text" id="net_settlement_amount" readonly>
                                    </td>
                                </tr>

                            </table>
                            {{-- Receipt Table --}}
                            {{-- Receipt Table --}}
                            <div class="col-12 mt-1">
                                <div class="form-section">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm mb-0 receipt-table">
                                            <thead style="background: #F2F2F2; border-bottom: 2px solid #dee2e6;">
                                                <tr>
                                                    <th
                                                        style="font-size: 10px; font-weight: 700; color: #495057; text-transform: uppercase; padding: 6px 8px; width: 30%;">
                                                        <i class="la la-hashtag me-1"></i> Receipt No.
                                                    </th>
                                                    <th
                                                        style="font-size: 10px; font-weight: 700; color: #495057; text-transform: uppercase; padding: 6px 8px; width: 25%;">
                                                        <i class="la la-calendar me-1"></i> Date
                                                    </th>
                                                    <th
                                                        style="font-size: 10px; font-weight: 700; color: #495057; text-transform: uppercase; padding: 6px 8px; width: 25%;">
                                                        <i class="la la-money me-1"></i> Amount
                                                    </th>
                                                    <th
                                                        style="font-size: 10px; font-weight: 700; color: #495057; text-transform: uppercase; padding: 6px 8px; width: 20%; text-align: center;">
                                                        <i class="la la-eye me-1"></i> View
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($receiptLogs ?? [] as $receipt)
                                                <tr style="transition: background 0.2s ease;">
                                                    <td style="padding: 5px 8px; vertical-align: middle;">
                                                        <span class="badge bg-light text-dark"
                                                            style="font-size: 10px; font-weight: 600; padding: 4px 10px; border: 1px solid #dee2e6;">
                                                            {{ $receipt->reciept }}
                                                        </span>
                                                    </td>
                                                    <td
                                                        style="padding: 5px 8px; vertical-align: middle; font-size: 10px; color: #495057;">
                                                        {{ \Carbon\Carbon::parse($receipt->date)->format('d M Y') }}
                                                    </td>
                                                    <td
                                                        style="padding: 5px 8px; vertical-align: middle; font-size: 10px; font-weight: 600; color: #28a745;">
                                                        ₹ {{ number_format($receipt->amount, 2) }}
                                                    </td>
                                                    <td
                                                        style="padding: 5px 8px; vertical-align: middle; text-align: center;">
                                                        @php
                                                        $receiptImage = $receipt->getFirstMediaUrl('amount-proof');
                                                        @endphp
                                                        @if($receiptImage)
                                                        <a href="{{ $receiptImage }}" data-lightbox="receipt-images"
                                                            data-title="Receipt {{ $receipt->reciept }}"
                                                            class="btn btn-sm btn-outline-primary"
                                                            style="padding: 2px 8px; font-size: 9px; border-radius: 4px;">
                                                            <i class="la la-eye" style="font-size: 14px;"></i>
                                                        </a>
                                                        @else
                                                        <span class="text-muted" style="font-size: 9px;">
                                                            <i class="la la-eye-slash"></i> No File
                                                        </span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="4" class="text-center py-3"
                                                        style="font-size: 11px; color: #6c757d;">
                                                        <i class="la la-inbox"
                                                            style="font-size: 24px; display: block; margin-bottom: 5px;"></i>
                                                        No Receipts Found
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                            @if($receiptLogs->count() > 0)
                                            <tfoot style="background: #f8f9fa; border-top: 2px solid #dee2e6;">
                                                <tr>
                                                    <td colspan="2"
                                                        style="padding: 5px 8px; font-size: 10px; font-weight: 700; color: #495057; text-align: right;">
                                                        TOTAL:
                                                    </td>
                                                    <td
                                                        style="padding: 5px 8px; font-size: 10px; font-weight: 700; color: #28a745;">
                                                        ₹ {{ number_format($receiptLogs->sum('amount') ?? 0, 2) }}
                                                    </td>
                                                    <td style="padding: 5px 8px;"></td>
                                                </tr>
                                            </tfoot>
                                            @endif
                                        </table>
                                    </div>

                                    {{-- Receipt Count Badge --}}
                                    @if($receiptLogs->count() > 0)
                                    <div class="mt-1 text-end">
                                        <small class="text-muted" style="font-size: 9px;">
                                            <i class="la la-file-text-o me-1"></i>
                                            {{ $receiptLogs->count() }} {{ Str::plural('receipt', $receiptLogs->count())
                                            }} found
                                        </small>
                                    </div>
                                    @endif

                                    {{-- HIDDEN FIELD FOR RECEIPT TOTAL --}}
                                    <input type="hidden" id="receipt_total" name="receipt_total"
                                        value="{{ number_format($receiptLogs->sum('amount') ?? 0, 2) }}">
                                </div>
                            </div>

                            {{-- DO Settlement Difference Table --}}


                            <table class="bill-table mt-2">
                                <tr>
                                    <td class="title">Expected Balance</td>
                                    <td>
                                        <input id="expected_balance" name="expected_balance" readonly
                                            value="{{ old('expected_balance') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" width="33%">DO Settlement Difference</td>
                                    <td width="67%">
                                        <input id="do_settlement_difference" name="do_settlement_difference"
                                            value="{{ old('do_settlement_difference') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" width="33%">Discount through JV</td>
                                    <td width="67%">
                                        <input id="discount_through_jv" name="discount_through_jv" class="numeric-only"
                                            value="{{ old('discount_through_jv', $otfData['discount_through_jv'] ?? '') }}">
                                    </td>
                                </tr>

                                <tr>
                                    <td class="title">Final Balance</td>
                                    <td>
                                        <input id="final_balance" name="final_balance" readonly
                                            value="{{ old('final_balance') }}">
                                    </td>
                                </tr>
                            </table>

                            {{-- Financier Verified, Delivery, DO Details --}}
                            <table class="bill-table mt-2">

                                <tr>
                                    <td class="title" style="white-space:nowrap;">Vehicle To Be Delivered On</td>
                                    <td>
                                        <input type="text" id="vehicle_delivery_on_display"
                                            name="vehicle_delivery_on_display"
                                            value="{{ $deliveryOptions[$otfData['vehicle_delivery_on'] ?? $finance?->instrument_type ?? ''] ?? '' }}"
                                            readonly style="background:transparent; border:none; width:100%;">
                                        <input type="hidden" name="vehicle_delivery_on"
                                            value="{{ old('vehicle_delivery_on', $otfData['vehicle_delivery_on'] ?? $finance?->instrument_type ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Number (Delivery Time)</td>
                                    <td>
                                        <input type="text" id="do_number" name="do_number" value="{{ old('do_number',
                                    $otfData['do_number']
                                    ?? $finance?->instrument_ref_no
                                    ?? ''
                                ) }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Number (TA Statement)</td>
                                    <td>
                                        <input type="text" id="do_number_ta" name="do_number_ta" style="width:100%;"
                                            placeholder="Enter DO Number to fetch details">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Amount (TA Statement)</td>
                                    <td>
                                        <input id="do_amount_ta" name="do_amount_ta" style="width:100%;" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Voucher Date</td>
                                    <td>
                                        <input type="date" id="do_voucher_date" name="do_voucher_date"
                                            style="width:100%;" readonly>
                                    </td>
                                </tr>
                            </table>

                            {{-- Brokerage & Other Discount Receivable --}}
                            <table class="bill-table mt-2">
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Brokerage Amount</td>
                                    <td>
                                        <input id="brokerage_amount" name="brokerage_amount" class="numeric-only"
                                            style="width:100%;" min="0" step="0.01"
                                            value="{{ old('brokerage_amount') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Other Discount Receivable</td>
                                    <td>
                                        <input id="other_discount_receivable" name="other_discount_receivable"
                                            class="numeric-only" style="width:100%;" min="0" step="0.01"
                                            value="{{ old('other_discount_receivable') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Other Discount Receivable - M&amp;M
                                        Support</td>
                                    <td>
                                        <input id="mm_support_receivable" name="mm_support_receivable"
                                            class="numeric-only" style="width:100%;" min="0" step="0.01"
                                            value="{{ old('mm_support_receivable') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Other Discount Receivable -
                                        Liquidation Scheme</td>
                                    <td>
                                        <input id="liquidation_scheme_receivable" name="liquidation_scheme_receivable"
                                            class="numeric-only" style="width:100%;" min="0" step="0.01"
                                            value="{{ old('liquidation_scheme_receivable') }}">
                                    </td>
                                </tr>
                            </table>
                            {{-- Registration Service Charge --}}
                            <table class="bill-table mt-2">
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Registration Service Charge -
                                        Receivable</td>
                                    <td>
                                        <input id="registration_service_charge_receivable" class="numeric-only"
                                            name="registration_service_charge_receivable" style="width:100%;" min="0"
                                            step="0.01" value="{{ old('registration_service_charge_receivable') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Registration Service Charge - Received
                                    </td>
                                    <td>
                                        <input id="registration_service_charge_received" class="numeric-only"
                                            name="registration_service_charge_received" style="width:100%;" min="0"
                                            step="0.01" value="{{ old('registration_service_charge_received') }}">
                                    </td>
                                </tr>
                            </table>

                        </div>
                    </div>

                    @php
                    $insuranceNoteText = '';
                    $insuranceCovers = $otfData['insurance_covers'] ?? [];

                    // ✅ Debug: Check data
                    \Log::info('OTF Blade - Insurance Data', [
                    'insurance_covers' => $insuranceCovers,
                    'insurance_amount' => $otfData['insurance_amount'] ?? null,
                    ]);

                    if (!empty($insuranceCovers) && is_array($insuranceCovers)) {
                    $insuranceNoteText = collect($insuranceCovers)->map(function ($cover) {
                    // Handle string cover
                    if (is_string($cover)) {
                    $price = 0;
                    $name = $cover;
                    if (preg_match('/\(₹([\d,]+\.?\d*)\)/', $cover, $matches)) {
                    $price = floatval(str_replace(',', '', $matches[1]));
                    $name = trim(preg_replace('/\(₹[\d,]+\.?\d*\)/', '', $cover));
                    }
                    return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                    }
                    // Handle array cover
                    $name = $cover['name'] ?? '';
                    $price = (float) ($cover['price'] ?? 0);
                    return $name . ($price > 0 ? ' (₹' . number_format($price, 2) . ')' : '');
                    })->filter()->implode(', ');
                    }

                    // ✅ FALLBACK: If no insurance covers, use insurance_amount
                    if (empty($insuranceNoteText) && !empty($otfData['insurance_amount'] ?? '') &&
                    ($otfData['insurance_amount'] ?? '0') != '0' && ($otfData['insurance_amount'] ?? '0.00') != '0.00')
                    {
                    $insurancePolicyLabel = $insurance_type_map[$otfData['policy_type'] ?? ''] ?? '';
                    $insuranceNoteText = trim($insurancePolicyLabel . ' (₹' . number_format((float)
                    ($otfData['insurance_amount'] ?? 0), 2) . ')');
                    }
                    @endphp
                    <div class="insurance-note-row">
                        Insurance:
                        <span id="insurance_print"
                            style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">
                            &nbsp;
                        </span>
                    </div>

                    <div class="accessories-note-row">
                        Accessories:
                        <span id="accessories_print"
                            style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">
                            &nbsp;
                        </span>
                    </div>
                    {{-- ================= NOTE ================= --}}
                    <table class="bill-table note-box flex-grow-1 mt-3">
                        <tr>
                            <td>
                                <div style="font-weight:bold; font-size:8px; margin-bottom:3px;">NOTE:</div>
                                <p
                                    style="font-size:7px; font-weight:bold; line-height:1.3; text-align:justify; margin:0;">
                                    <b>1.</b> Vehicle shall be delivered only against payment.<br>
                                    <b>2.</b> Interest shall be charged @ 24% P.A. in case of payments delayed over
                                    three days.<br>
                                    <b>3.</b> No Interest shall be payable on Booking Amount.<br>
                                    <b>4.</b> Price & Scheme of the vehicle is applicable as on the date of delivery.
                                    Price & Scheme are subjected to change without any prior notice.<br>
                                    <b>5.</b> Self attested coloured copy of original documents is required for any
                                    claim. Claims will be rejected in absence of original documents.
                                </p>
                            </td>

                            <td style="width:10%; vertical-align:bottom; text-align:center; height:90px;">


                                <div style="border-top:1px solid #000; width:85%; margin:0 auto; padding-top:3px;">
                                    <span style="font-size:7px; font-weight:bold;">
                                        Customer Signature
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

            </div> <!-- /.quotation-sheet -->

    </div> <!-- /.container-fluid -->
</div> <!-- /.quotation-form -->

<div class="card-footer text-end mt-3 no-print">
    <button type="button" class="btn btn-primary no-print" onclick="printQuotation();">
        <i class="la la-print"></i> Print / Save PDF
    </button>
    <button type="submit" class="btn btn-success">
        <i class="la la-save"></i> Save Transaction Form
    </button>
    <a href="{{ backpack_url('quotation-form') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>

</div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.5/js/lightbox.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

    const generateButton = document.getElementById('generate_votf');
    const votfInput = document.getElementById('votf_no');

    if (!generateButton || !votfInput) {
        return;
    }

    generateButton.addEventListener('click', function () {

        generateButton.disabled = true;
        generateButton.innerText = 'Generating...';

        fetch("{{ route('booking.generate-votf', $booking->id) }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {

            if (!data.success) {
                throw new Error(
                    data.message || 'Unable to generate VOTF number.'
                );
            }

            votfInput.value = data.votf_no;

        })
        .catch(error => {

            console.error('VOTF generation error:', error);

            alert(
                error.message ||
                'Unable to generate VOTF number.'
            );

        })
        .finally(() => {

            generateButton.disabled = false;
            generateButton.innerText = 'Generate';

        });

    });

});
    const consultants = @json($salesconsultants);

    document.getElementById('saleconsultant').addEventListener('change', function () {
        const personCode = this.value;
        const consultant = consultants.find(c => c.person_code === personCode);

        console.log('Selected Person Code:', personCode);
        console.log('Consultants:', consultants);
        console.log('Matched Consultant:', consultant);
        console.log('Mile ID:', consultant?.mile_id);

        if (!consultant) {
            document.getElementById('sc_mile_id').value = '';
            document.getElementById('sc_branch').value = '';
            document.getElementById('sc_location').value = '';
            return;
        }

        document.getElementById('sc_mile_id').value = consultant.mile_id ?? '';
        document.getElementById('sc_branch').value = consultant.branch_name ?? consultant.primary_branch_code ?? '';
        document.getElementById('sc_location').value = consultant.location_name ?? consultant.primary_loc_code ?? '';
    });
        
    window.addEventListener('DOMContentLoaded', function () {
        document.getElementById('saleconsultant').dispatchEvent(new Event('change'));
    });

    lightbox.option({
    resizeDuration: 200,
    wrapAround: true,
    fadeDuration: 200,
    imageFadeDuration: 200
});

$(document).on('input', '.numeric-only', function () {
    let value = $(this).val();
    value = value.replace(/[^\d.]/g, '');
    value = value.replace(/(\..*)\./g, '$1');
    $(this).val(value);
});

function updateAccessoriesCount() {
    const count = $('#accessories option:selected').length;

    $('#accessories_count_print').text(
        count === 1 ? '1 Accessory' : `${count} Accessories`
    );
}

$('#accessories').on('change', function () {
    updateAccessoriesCount();
    updateAccessoriesPrintText();
});

$(document).ready(function () {
    updateAccessoriesCount();
});

function updateAccessoriesPrintText() {

    let list = [];

    $('#accessories option:selected').each(function () {
        list.push($(this).text());
    });

    $('#accessories_print').html(
        list.length ? list.join(', ') : '&nbsp;'
    );
}

$('#accessories').on('change', function () {
    updateAccessoriesPrintText();
});

function updateInsurancePrintText() {
    let list = [];

    $('#policy_type option:selected').each(function () {
        let text = $(this).text().trim();

        if (text) {
            let amount = parseFloat($('#insurance_amount').val()) || 0;

            if (amount > 0) {
                text += ' (₹' + amount.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ')';
            }

            list.push(text);
        }
    });

    $('#insurance_print').text(
        list.length ? list.join(', ') : ''
    );
}

$('#policy_type, #insurance_amount, #insurance_company').on('change input', function () {
    updateInsurancePrintText();
});

$(document).ready(function () {
    updateInsurancePrintText();
});
$(document).ready(function () {

    $('#accessories').select2({
        placeholder: 'Select Accessories',
        closeOnSelect: false
    });

    updateAccessoriesPrintText();
});

// ================= PRICE / DISCOUNT VALUES =================
// NOTE: Price Details, Discount Details and Net Receivable now come directly
// from the quotation's data ($otfData) rendered server-side via Blade.
// No client-side recalculation is performed here anymore.

$(document).ready(function () {
    $('#accessories').select2({
        placeholder: 'Select Accessories',
        width: '100%',
        closeOnSelect: false
    }).on('change', function () {
        let count = $(this).find('option:selected').length;
        $('.select2-search__field').attr('placeholder', count + ' Accessories Selected');
    });
});

// ================= LMM FIELDS TOGGLE =================
function toggleLMMFields() {
    let isLMM = "{{ $booking->segment_code ?? '' }}" === "LMM";
    const fields = ['#kazam_charging_kit', '#incidental_charges'];

    fields.forEach(function (field) {
        $(field).prop('disabled', !isLMM);
        if (!isLMM) {
            $(field).val('N/A');
        }
    });

    $('#charger_swapping_discount_type')
        .prop('disabled', false)
        .empty()
        .append('<option value="CN2">CN2</option>')
        .val('CN2');

    $('#fame_subsidy_option').prop('disabled', !isLMM);

    if (!isLMM && $('#group_a_select').val() === 'fame_subsidy') {
        $('#group_a_select').val('').trigger('change');
    }

    if (!isLMM) {
        $('#charger_swapping').val('N/A').prop('disabled', true);
        $('#charger_swapping_amount').val('N/A').prop('disabled', true);
        $('#charger_swapping_discount').val('N/A').prop('disabled', true);
        $('#charger_swapping_discount_type').val('').prop('disabled', true);
    } else {
        $('#charger_swapping').prop('disabled', false);
        $('#charger_swapping_amount').prop('disabled', false);
        $('#charger_swapping_discount').prop('disabled', false);
        $('#charger_swapping_discount_type').prop('disabled', false);
    }
}

$(document).ready(function () {
    toggleLMMFields();
});

// ================= PRINT LABEL FOLDING =================
let printLabelRestoreList = [];

function prepareOptionLabelsForPrint() {
    printLabelRestoreList = [];
    
    // Define which select IDs should NOT show their type/label
    const discountSelectIds = ['group_a_select', 'group_b_select', 'group_c_select'];
    
    $('.quotation-stacked-table td.ql-label select').not('#accessories').each(function () {
        let $select = $(this);
        let selectId = $select.attr('id');
        let selectedText = $select.find('option:selected').first().text().trim();
        if (!selectedText || selectedText.toLowerCase() === 'select') return;
        let $label = $select.closest('tr').find('td.ql-label').first();
        printLabelRestoreList.push({ el: $label, html: $label.html() });
        
        // ✅ For discount group selects, ONLY show the selected text (no type)
        if (discountSelectIds.includes(selectId)) {
            $label.text(selectedText);
        } else {
            // For other selects (coating, shield, etc.), show option in parentheses
            let baseLabel = $label.text().replace(/\(.*\)/, '').trim();
            $label.html(baseLabel + ' (' + selectedText + ')');
        }
    });
    
}

// ================= CALCULATE TOTAL DISCOUNT =================
function calculateTotalDiscount() {
    // Get all discount amount fields
    var discountFields = [
        '#cash_scheme_oem', '#csd_discount', '#fame_subsidy',
        '#dealer_discount', '#accessories_discount', '#shield_scheme',
        '#corporate_discount', '#loyalty_bonus', '#exchange_bonus',
        '#green_bonus', '#welcome_bonus', '#accessories_spl_disc',
        '#ceramic_discount', '#ppf_discount', '#charger_swapping_discount',
        '#other_cash_discount', '#special_cash_discount'
    ];
    
    var total = 0;
    discountFields.forEach(function(field) {
        var val = $(field).val();
        if (val && val !== '' && val !== 'N/A' && val !== '0' && val !== '0.00') {
            total += parseFloat(val) || 0;
        }
    });
    
    // Update the total discount display
    $('#total_discount_amount').val(total.toFixed(2));
    $('#total_discount').val(total.toFixed(2));
    
    return total;
}

function calculateNetReceivable() {
    var totalReceivable = parseFloat($('#total_receivable').val()) || 0;
    var totalDiscount = parseFloat($('#total_discount_amount').val()) || 0;
    var netReceivable = totalReceivable - totalDiscount;
    $('#net_receivable_summary').val(netReceivable.toFixed(2));
}

// Auto-calculate when any discount field changes
$(document).on('keyup change', 
    '#dealer_discount, #accessories_discount, #shield_scheme, ' +
    '#accessories_spl_disc, #ceramic_discount, #ppf_discount, ' +
    '#charger_swapping_discount, #other_cash_discount, #special_cash_discount, ' +
    '#group_a_amount, #group_b_amount, #group_c_amount',
    function() {
        calculateTotalDiscount();
    }
);

$(document).on('change', '#group_a_select, #group_b_select, #group_c_select', function() {
    setTimeout(function() {
        calculateTotalDiscount();
    }, 100);
});

// Also recalculate when total receivable changes
$(document).on('keyup change', '#total_receivable', function() {
    calculateNetReceivable();
});

$(document).ready(function() {
    calculateTotalDiscount();
});

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
    $('.quotation-stacked-table tbody tr').each(function () {
        let $row = $(this);
        let amountInputs = $row.find('td.ql-amount input');
        let hasValue = false;
        amountInputs.each(function () {
            let val = $(this).val();
            if (!isEmptyGridValue(val)) hasValue = true;
        });
        if (!hasValue) $row.addClass('print-hide');
    });
}

function restoreItemVisibilityAfterPrint() {
    $('.quotation-stacked-table tbody tr').removeClass('print-hide');
}

function printQuotation() {
    prepareOptionLabelsForPrint();
    prepareItemVisibilityForPrint();
    window.print();
    restoreOptionLabelsAfterPrint();
    restoreItemVisibilityAfterPrint();
}

// ================= PERMANENT VISIBILITY (screen + print) =================
$(document).ready(function () {
    // Permanently fold option labels into label
    prepareOptionLabelsForPrint();
    // Permanently hide empty rows
    prepareItemVisibilityForPrint();
    // Group A type change handler
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
        $type.trigger('change');
    });
    $('#group_a_select').trigger('change');

    // ================= OPTION FIELD SYNC (show selected in label) =================
    function syncOptionLabel(selectId, labelSuffixId) {
    $(document).on('change', selectId, function () {
        let selectedText = $(this).find('option:selected').first().text().trim();
        if (selectedText && selectedText.toLowerCase() !== 'select' && selectedText !== 'N/A') {
            $('#' + labelSuffixId).text('(' + selectedText + ')');
        } else {
            $('#' + labelSuffixId).text('');
        }
    });
    $(selectId).trigger('change');
}
    
    $('#accessories').on('change', function () {
        let count = $(this).find('option:selected').length;
        $('#accessories_price_label').text(count > 0 ? '(' + count + ' items)' : '');
    });
    $('#accessories').trigger('change');

    // Coating discount label update
    function updateCoatingDiscountLabel() {
        let coating = $('#coating').val();
        let label = 'Coating Spl Discount';
        if (coating === 'Ceramic') label = 'Ceramic Coating Spl Discount';
        else if (coating === 'Graphene') label = 'Graphene Coating Spl Discount';
        $('#coating_discount_label').text(label);
    }
    $(document).on('change', '#coating', updateCoatingDiscountLabel);
    updateCoatingDiscountLabel();

    // VLTD toggle for CV segment
    function toggleVltdField() {
        let segment = ($('input[name="segment_code"]').val() || '').trim().toUpperCase();
        if (segment === 'CV') {
            $('#vltd_device').val('').prop('readonly', false).prop('disabled', false);
            $('#vltd_device').closest('tr').removeClass('print-hide');
        } else {
            $('#vltd_device').val('N/A').prop('readonly', true).prop('disabled', true);
            $('#vltd_device').closest('tr').addClass('print-hide');
        }
    }
    toggleVltdField();

    // Policy/Registration/Coating/Shield/RSA/ChargerSwapping — enable/disable on change
    $('#policy_type').on('change', function () {
        let hasValue = $(this).val() !== '';
        $('#insurance_amount').prop('disabled', !hasValue).val(hasValue ? $('#insurance_amount').val() : '');
    });
    $('#registration_type').on('change', function () {
        let hasValue = $(this).val() !== '';
        $('#registration_amount').prop('disabled', !hasValue).val(hasValue ? $('#registration_amount').val() : '');
    });
    $('#coating').on('change', function () {
        let value = $(this).val();
        if (value === '') {
            $('#coating_price').val('').prop('disabled', true);
            $('#ceramic_discount').val('').prop('disabled', true);
            $('#ceramic_discount_type').val('').prop('disabled', true);
        } else if (value === 'No Coating') {
            $('#coating_price').val('N/A').prop('disabled', true);
            $('#ceramic_discount').val('N/A').prop('disabled', true);
            $('#ceramic_discount_type').val('').prop('disabled', true);
        } else {
            $('#coating_price').prop('disabled', false);
            $('#ceramic_discount').prop('disabled', false);
            $('#ceramic_discount_type').prop('disabled', false);
        }
    });
    $('#shield').on('change', function () {
        let value = $(this).val();
        if (value === '') {
            $('#shield_price').val('').prop('disabled', true);
        } else if (value === 'No Shield') {
            $('#shield_price').val('N/A').prop('disabled', true);
        } else {
            $('#shield_price').prop('disabled', false);
        }
    });
    $('#rsa').on('change', function () {
        let value = $(this).val();
        if (value === '') {
            $('#rsa_amount').val('').prop('disabled', true);
        } else if (value === 'No RSA') {
            $('#rsa_amount').val('N/A').prop('disabled', true);
        } else {
            $('#rsa_amount').prop('disabled', false);
        }
    });
    $('#charger_swapping').on('change', function () {
        let value = $(this).val();
        if (value === '') {
            $('#charger_swapping_amount').val('').prop('disabled', true);
            $('#charger_swapping_discount').val('').prop('disabled', true);
            $('#charger_swapping_discount_type').val('').prop('disabled', true);
        } else if (value === 'N/A') {
            $('#charger_swapping_amount').val('N/A').prop('disabled', true);
            $('#charger_swapping_discount').val('N/A').prop('disabled', true);
            $('#charger_swapping_discount_type').val('').prop('disabled', true);
        } else {
            $('#charger_swapping_amount').prop('disabled', false);
            $('#charger_swapping_discount').prop('disabled', false);
            $('#charger_swapping_discount_type')
                .prop('disabled', false)
                .empty()
                .append('<option value="CN2">CN2</option>')
                .val('CN2');
        }
    });

    // Trigger all on load
    $('#policy_type').trigger('change');
    $('#registration_type').trigger('change');
    $('#coating').trigger('change');
    $('#shield').trigger('change');
    $('#rsa').trigger('change');
    $('#charger_swapping').trigger('change');

    // Chassis image preview
    $('#chassis_image').on('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e){
            $('#chassis_preview').attr('src', e.target.result).show();
        };
        reader.readAsDataURL(file);
    });
});

$(document).ready(function () {
    function toggleDONumber() {
    var deliveryValue = $('#vehicle_delivery_on_display').val();
    var isDO = (deliveryValue === 'DO');
    
    if (isDO) {
        // ✅ Auto-fill DO Number (Delivery Time) ONLY from finance data
        var doNumber = "{{ old('do_number', $otfData['do_number'] ?? $finance?->instrument_ref_no ?? '') }}";
        if (doNumber) {
            $('#do_number').val(doNumber);
            // ❌ REMOVE: Do NOT auto-fill TA Statement field
            // $('#do_number_ta').val(doNumber);
            // $('#do_number_ta').trigger('blur');
        }
        $('#do_number').prop('disabled', false);
        $('#do_number_ta').prop('disabled', false);
    } else {
        $('#do_number').val('').prop('disabled', true);
        $('#do_number_ta').val('').prop('disabled', true);
        $('#do_amount_ta').val('');
        $('#do_voucher_date').val('');
    }
}

    toggleDONumber();

    // DO Number (TA Statement) — fetch DO Amount & Date from financier statement
    $('#do_number_ta').on('blur', function () {
        let doNo = $(this).val().trim();
        if (doNo == '') {
            $('#do_amount_ta').val('');
            $('#do_voucher_date').val('');
            return;
        }
        $.ajax({
            url: "{{ url('admin/get-do-amount') }}",
            type: "GET",
            data: { do_no: doNo },
            success: function (res) {
                // ✅ If match found, populate amount and date
                if (res.amount && parseFloat(res.amount) > 0) {
                    $('#do_amount_ta').val(res.amount);
                    $('#do_voucher_date').val(res.date);
                } else {
                    // No match found - keep empty
                    $('#do_amount_ta').val('');
                    $('#do_voucher_date').val('');
                }
                
                // Also update DO Number (Delivery Time) if empty
                var deliveryValue = $('#vehicle_delivery_on_display').val();
                if (deliveryValue === 'DO' && !$('#do_number').val() && doNo) {
                    $('#do_number').val(doNo);
                }
            },
            error: function(xhr) {
                console.log('Error fetching DO details:', xhr);
                $('#do_amount_ta').val('');
                $('#do_voucher_date').val('');
            }
        });
    });

    // Sync: TA Statement changes → update Delivery Time if empty
    $('#do_number_ta').on('input', function() {
        var doNo = $(this).val().trim();
        var deliveryValue = $('#vehicle_delivery_on_display').val();
        
        if (deliveryValue === 'DO' && doNo && !$('#do_number').val()) {
            $('#do_number').val(doNo);
        }
    });

    // Sync: Delivery Time changes → update TA Statement if empty
    $('#do_number').on('change input', function() {
        var doNo = $(this).val().trim();
        var deliveryValue = $('#vehicle_delivery_on_display').val();
        
        if (deliveryValue === 'DO' && doNo && !$('#do_number_ta').val()) {
            $('#do_number_ta').val(doNo);
            $('#do_number_ta').trigger('blur');
        }
    });
});


function updateDsaLocation() {
    let location = $('#dsa_id option:selected').data('location') || '';
    $('#dsa_location').val(location);
}

$('#dsa_id').on('change', updateDsaLocation);

$(document).ready(function () {
    updateDsaLocation();
});

document.addEventListener("DOMContentLoaded", function () {
    // Configure Flatpickr with d-M-Y format (12-Aug-2026)
    const dateConfig = {
        dateFormat: "d-M-Y",
        allowInput: false,
        clickOpens: true,
        altInput: true,
        altFormat: "d-M-Y"
    };

    // Apply to all date picker inputs
    flatpickr(".date-picker", dateConfig);
    flatpickr(".flatpickr", dateConfig);
    flatpickr("#dob", dateConfig);
    flatpickr("#anniversary_date", dateConfig);
    flatpickr("#invoice_date", dateConfig);

    // Handle invoice date with hidden field
    const invoicePicker = flatpickr("#invoice_date", {
        dateFormat: "d-M-Y",
        allowInput: false,
        clickOpens: true,
        altInput: true,
        altFormat: "d-M-Y",
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                const yyyyMMdd = selectedDates[0].toISOString().split('T')[0];
                document.getElementById('hidden_invoice_date').value = yyyyMMdd;
            } else {
                document.getElementById('hidden_invoice_date').value = '';
            }
        }
    });
    
    // Handle DO Voucher Date
    flatpickr("#do_voucher_date", {
        dateFormat: "d-M-Y",
        allowInput: false,
        clickOpens: true,
        altInput: true,
        altFormat: "d-M-Y"
    });
});
function toggleAnniversaryRow() {
    const maritalStatus = document.getElementById('marital_status');
    const anniversaryRow = document.getElementById('anniversary_row');

    if (!maritalStatus || !anniversaryRow) return;

    if (maritalStatus.value === 'Single' || maritalStatus.value === '') {
        anniversaryRow.style.display = 'none';
        document.getElementById('anniversary_date').value = '';
    } else {
        anniversaryRow.style.display = '';
    }
}

document.getElementById('marital_status').addEventListener('change', toggleAnniversaryRow);
toggleAnniversaryRow();

// ================= NET SETTLEMENT CALCULATION =================
function calculateNetSettlement() {
    const loanAmount   = parseFloat($('#loan_amount').val()) || 0;
    const marginMoney  = parseFloat($('#margin_money').val()) || 0;
    const fileCharge   = parseFloat($('#file_charge').val()) || 0;
    const financierSubvention = parseFloat($('#financier_subvention').val()) || 0;

    // Formula: Loan Amount - File Charge + Margin Money - Financier Subvention
    const netSettlement = loanAmount - fileCharge + marginMoney - financierSubvention;

    $('#net_settlement_amount').val(netSettlement.toFixed(2));
}

// Auto-calculate on any change
$('#loan_amount, #margin_money, #file_charge, #financier_subvention').on('input change', function () {
    calculateNetSettlement();
});

// Initialize on page load
calculateNetSettlement();

function calculateExpectedBalance() {
    const netReceivable = parseFloat($('#net_receivable').val()) || 0;
    const doAmount      = parseFloat($('#do_amount').val()) || 0;
    const receiptTotal  = parseFloat($('#receipt_total').val()) || 0;
    const settlementDiff = parseFloat($('#do_settlement_difference').val()) || 0;

    // Expected Balance = Net Receivable - DO Amount - Receipts + DO Settlement Difference
    const expectedBalance = netReceivable - doAmount - receiptTotal + settlementDiff;

    $('#expected_balance').val(expectedBalance.toFixed(2));
}

function calculateFinalBalance() {
    const expectedBalance = parseFloat($('#expected_balance').val()) || 0;
    const discountJV      = parseFloat($('#discount_through_jv').val()) || 0;

    // Final Balance = Expected Balance - Discount through JV
    const finalBalance = expectedBalance - discountJV;

    $('#final_balance').val(finalBalance.toFixed(2));
}

// Recalculate Receipt Total from receipt table
function calculateReceiptTotal() {
    let total = 0;
    $('.receipt-table tbody tr').each(function() {
        const amountText = $(this).find('td:eq(2)').text().trim();
        const amount = parseFloat(amountText.replace(/[^0-9.]/g, '')) || 0;
        total += amount;
    });
    $('#receipt_total').val(total.toFixed(2));
    return total;
}

// Trigger on all relevant fields
$('#net_receivable, #do_amount, #do_settlement_difference, #discount_through_jv').on('input', function () {
    calculateExpectedBalance();
    calculateFinalBalance();
});

// Recalculate when receipt total changes
$('.receipt_amount').on('input', function () {
    calculateReceiptTotal();
    calculateExpectedBalance();
    calculateFinalBalance();
});

// Initialize on page load
$(document).ready(function() {
    calculateReceiptTotal();
    calculateExpectedBalance();
    calculateFinalBalance();
        
    var insuranceText = buildInsuranceTextFromData();
    if (insuranceText) {
        $('#insurance_print').text(insuranceText);
    }
    
    var accessoriesText = buildAccessoriesTextFromData();
    if (accessoriesText) {
        $('#accessories_print').text(accessoriesText);
    }
});
function buildInsuranceTextFromData() {
    var insuranceData = $('#insurance_print_data').val();
    
    if (insuranceData && insuranceData !== '[]' && insuranceData !== '""') {
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
    
    // ✅ FALLBACK: Check if insurance_amount exists in otfData
    var insuranceAmount = $('#insurance_amount').val();
    if (insuranceAmount && parseFloat(insuranceAmount) > 0) {
        return 'Insurance Amount (₹' + parseFloat(insuranceAmount).toLocaleString('en-IN') + ')';
    }
    
    return '';
}
</script>
@endpush