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
                        <h4 class="mt-2 mb-0 fw-bold text-uppercase">Vehicle Quotation</h4>
                    </div>
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

                    <div class="row g-2">

                        {{-- ================= LEFT COLUMN ================= --}}
                        <div class="col-md-6">

                            {{-- Customer Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Customer Details</td>
                                </tr>
                                <tr>
                                    <td class="title">GST Number</td>
                                    <td><input type="text" name="gstn"
                                            value="{{ old('gstn', $otfData['gstn'] ?? $booking->gstn ?? '') }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Registration Type</td>
                                    <td>
                                        <select name="registration_no_type" id="registration_no_type">
                                            <option value="">Select Registration Type</option>
                                            @foreach($reg_no_type_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('registration_no_type',
                                                $otfData['registration_no_type'] ?? $rto?->rgn_no_type ?? '') == $key ?
                                                'selected' : '' }}>
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
                                            <option value="">Select Registration Category</option>
                                            <option value="Exempted" {{ old('registration_category',
                                                $otfData['registration_category'] ?? $rto?->registration_category ?? '')
                                                == 'Exempted' ? 'selected' : '' }}>Exempted</option>
                                            <option value="Standard" {{ old('registration_category',
                                                $otfData['registration_category'] ?? $rto?->registration_category ?? '')
                                                == 'Standard' ? 'selected' : '' }}>Standard</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Permit</td>
                                    <td>
                                        <select name="permit" id="permit">
                                            <option value="">Select Permit</option>
                                            @foreach($permit_map as $key => $value)
                                            <option value="{{ $key }}" {{ old('permit', $otfData['permit'] ?? $rto?->
                                                permit ?? '') == $key ? 'selected' : '' }}>
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
                                        <select name="sale_consultant" id="saleconsultant">
                                            <option value="">Select Sales Consultant</option>
                                            @foreach($salesconsultants as $consultant)
                                            <option value="{{ $consultant['person_code'] }}" {{
                                                ($otfData['sale_consultant'] ?? $booking->sale_consultant ?? '') ==
                                                $consultant['person_code'] ? 'selected' : '' }}>
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
                                    <td><input type="text" name="dms_no" id="dms_no"
                                            value="{{ old('dms_no', $otfData['dms_no'] ?? $booking->dms_no ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DMS OTF Number</td>
                                    <td><input type="text" name="dms_otf" id="dms_otf"
                                            value="{{ old('dms_otf', $otfData['dms_otf'] ?? $booking->dms_otf ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Xcler8 Booking ID</td>
                                    <td><input type="text" value="{{ $booking->id }}" readonly></td>
                                </tr>
                            </table>

                            {{-- KYC & Nominee --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">KYC & Nominee Details</td>
                                </tr>
                                <tr>
                                    <td class="title">PAN No.</td>
                                    <td><input type="text" name="pan_no" id="pan_no"
                                            value="{{ old('pan_no', $otfData['pan_no'] ?? $booking->pan_no ?? '') }}"
                                            maxlength="10" style="text-transform:uppercase"
                                            oninput="this.value=this.value.toUpperCase();"></td>
                                </tr>
                                <tr>
                                    <td class="title">Aadhaar No.</td>
                                    <td><input type="text" name="adhar_no" id="adhar_no"
                                            value="{{ old('adhar_no', $otfData['adhar_no'] ?? $booking->adhar_no ?? '') }}"
                                            maxlength="12" inputmode="numeric"
                                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,12);"></td>
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
                                    <td><input type="number" name="nominee_age" id="nominee_age" min="0" max="120"
                                            value="{{ old('nominee_age', $otfData['nominee_age'] ?? '') }}"></td>
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
                                    <td><input type="text" id="dsa_location" readonly disabled></td>
                                </tr>
                                <tr>
                                    <td class="title">Exchange</td>
                                    <td>
                                        <select name="exchange" id="exchange">
                                            <option value="NA" {{ old('exchange', $otfData['exchange'] ?? $booking->
                                                buyer_type ?? '') == 'NA' ? 'selected' : '' }}>NA</option>
                                            <option value="In-House" {{ old('exchange', $otfData['exchange'] ??
                                                $booking->buyer_type ?? '') == 'In-House' ? 'selected' : '' }}>In-House
                                            </option>
                                            <option value="Third Party" {{ old('exchange', $otfData['exchange'] ??
                                                $booking->buyer_type ?? '') == 'Third Party' ? 'selected' : '' }}>Third
                                                Party</option>
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
                                        <div id="accessories_print"></div>
                                        <select name="accessories[]" id="accessories" multiple style="display:none;">
                                            @foreach($accessoryList as $accessory)
                                            <option value="{{ $accessory->part_no }}" data-price="{{ $accessory->ndp }}"
                                                {{ in_array($accessory->part_no, $selectedAccessories ?? []) ?
                                                'selected' : '' }}>
                                                {{ $accessory->item }} (₹{{ number_format($accessory->ndp,2) }})
                                            </option>
                                            @endforeach
                                        </select>
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
                                    <td><input type="text" name="votf_no" id="votf_no"
                                            value="{{ old('votf_no', $otfData['votf_no'] ?? '') }}"></td>
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
                                    <td><input type="text" name="customer_tehsil" id="customer_tehsil"
                                            value="{{ old('customer_tehsil', $otfData['customer_tehsil'] ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Customer District</td>
                                    <td><input type="text" name="customer_district" id="customer_district"
                                            value="{{ old('customer_district', $otfData['customer_district'] ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Pincode</td>
                                    <td><input type="text" name="pincode" id="pincode"
                                            value="{{ old('pincode', $otfData['pincode'] ?? $booking->pincode ?? '') }}"
                                            maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,6);"></td>
                                </tr>
                                <tr>
                                    <td class="title">Customer Contact Number</td>
                                    <td><input type="text" name="customer_mobile" id="customer_mobile"
                                            value="{{ $booking->mobile ?? '' }}" readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Customer Category</td>
                                    <td>
                                        <select name="b_cat" id="b_cat">
                                            <option value="Individual" {{ old('b_cat', $otfData['b_cat'] ?? $booking->
                                                b_cat ?? '') == 'Individual' ? 'selected' : '' }}>Individual</option>
                                            <option value="CSD-CPC" {{ old('b_cat', $otfData['b_cat'] ?? $booking->b_cat
                                                ?? '') == 'CSD-CPC' ? 'selected' : '' }}>CSD-CPC</option>
                                            <option value="Corporate" {{ old('b_cat', $otfData['b_cat'] ?? $booking->
                                                b_cat ?? '') == 'Corporate' ? 'selected' : '' }}>Corporate</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            {{-- Contact & Personal Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">CONTACT & PERSONAL DETAILS</td>
                                </tr>
                                <tr>
                                    <td class="title">Contact Person (If Any Other)</td>
                                    <td><input type="text" name="contact_person" id="contact_person"
                                            value="{{ old('contact_person', $otfData['contact_person'] ?? $booking->contact_person ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Contact Person Phone No.</td>
                                    <td><input type="text" name="contact_person_mobile" id="contact_person_mobile"
                                            value="{{ old('contact_person_mobile', $otfData['contact_person_mobile'] ?? $booking->contact_person_mobile ?? '') }}"
                                            maxlength="10" inputmode="numeric"
                                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);"></td>
                                </tr>
                                <tr>
                                    <td class="title">Email ID</td>
                                    <td><input type="email" name="email" id="email"
                                            value="{{ old('email', $otfData['email'] ?? $booking->email ?? '') }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Date of Birth</td>
                                    <td><input type="date" name="dob" id="dob"
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
                                <tr>
                                    <td class="title">Date of Anniversary</td>
                                    <td><input type="date" name="anniversary_date" id="anniversary_date"
                                            value="{{ old('anniversary_date', $otfData['anniversary_date'] ?? $booking->anniversary_date ?? '') }}">
                                    </td>
                                </tr>
                            </table>

                            {{-- Vehicle Details --}}
                            <table class="bill-table">
                                <tr>
                                    <td colspan="2" class="section-title">Vehicle Details</td>
                                </tr>
                                <tr>
                                    <td class="title">Retail Category</td>
                                    <td>
                                        <select name="retail_category" id="retail_category">
                                            <option value="">Select Retail Category</option>
                                            <option value="Normal" {{ old('retail_category', $otfData['retail_category']
                                                ?? '' )=='Normal' ? 'selected' : '' }}>Normal</option>
                                            <option value="ZACO" {{ old('retail_category', $otfData['retail_category']
                                                ?? '' )=='ZACO' ? 'selected' : '' }}>ZACO</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Segment</td>
                                    <td><input type="text" id="segment_name" value="{{ $segment?->name ?? '' }}"
                                            readonly></td>
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
                                            <option value="{{ $key }}" {{ old('body_type', $otfData['body_type'] ??
                                                $rto?->body_type ?? '') == $key ? 'selected' : '' }}>
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
                                            <option value="{{ $key }}" {{ old('sale_type', $otfData['sale_type'] ??
                                                $rto?->sale_type ?? '') == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                            @endforeach
                                        </select>
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
                                    <td><input type="text" id="chassis_no_display"
                                            value="{{ $booking->chassis_no ?? '' }}"></td>
                                </tr>
                                <tr>
                                    <td class="title">Engine Number</td>
                                    <td><input type="text" name="engine_no" id="engine_no"
                                            value="{{ old('engine_no', $otfData['engine_no'] ?? $insurance?->engine_no ?? '') }}"
                                            readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Chassis Image</td>
                                    <td><input type="file" id="chassis_image" name="chassis_image"></td>
                                </tr>
                                <tr>
                                    <td class="title">OEM Model Code</td>
                                    <td><input type="text" name="oem_model_code" id="oem_model_code"
                                            value="{{ old('oem_model_code', $otfData['oem_model_code'] ?? $variant?->oem_name ?? '') }}"
                                            readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">GST Slab</td>
                                    <td><input type="text" name="gst_slab" id="gst_slab"
                                            value="{{ old('gst_slab', $otfData['gst_slab'] ?? $variant?->gst_slab ?? '') }}"
                                            readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Invoice No.</td>
                                    <td><input type="text" name="inv_no" id="inv_no"
                                            value="{{ old('inv_no', $otfData['inv_no'] ?? $booking->inv_no ?? '') }}"
                                            readonly></td>
                                </tr>
                                <tr>
                                    <td class="title">Invoice Date</td>
                                    <td><input type="date" name="inv_date" id="inv_date"
                                            value="{{ old('inv_date', $otfData['inv_date'] ?? ($booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('Y-m-d') : '')) }}"
                                            readonly></td>
                                </tr>
                            </table>

                        </div>

                    </div> <!-- /.row -->

                    <div class="row g-2">
                        <div class="col-md-6">
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
                                        @php
                                        $exShowroom = $otfData['ex_showroom_price'] ?? '';
                                        $hasExShowroom = !empty($exShowroom) && $exShowroom !== 'N/A' && $exShowroom !==
                                        '0' && $exShowroom !== '0.00';
                                        @endphp
                                        @if($hasExShowroom || $exShowroom === '0' || $exShowroom === '0.00')
                                        <tr class="grid-row">
                                            <td class="ql-label">Ex-Showroom Price</td>
                                            <td class="ql-amount">
                                                <input name="ex_showroom_price" id="ex_showroom_price"
                                                    class="numeric-only"
                                                    value="{{ old('ex_showroom_price', $exShowroom) }}">
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Insurance --}}
                                        @php
                                        $insuranceAmount = $otfData['insurance_amount'] ?? '';
                                        $policyType = $otfData['policy_type'] ?? '';
                                        // Only show if amount has value (not 0, 0.00, N/A, or empty)
                                        $hasInsurance = !empty($insuranceAmount) &&
                                        $insuranceAmount !== 'N/A' &&
                                        $insuranceAmount !== '0' &&
                                        $insuranceAmount !== '0.00';
                                        @endphp
                                        @if($hasInsurance)
                                        <tr class="grid-row">
                                            <td class="ql-label">Insurance <span id="insurance_option_label"></span>
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="insurance_amount" name="insurance_amount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('insurance_amount', $insuranceAmount) }}">
                                                <select name="policy_type" id="policy_type" style="display:none;">
                                                    @foreach($insurance_type_map as $key=>$value)
                                                    <option value="{{ $key }}" {{ old('policy_type', $policyType)==$key
                                                        ? 'selected' : '' }}>{{ $value }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Registration --}}
                                        @php
                                        $registrationAmount = $otfData['registration_amount'] ?? '';
                                        $registrationType = $otfData['registration_type'] ?? '';
                                        $hasRegistration = (!empty($registrationAmount) && $registrationAmount !== 'N/A'
                                        && $registrationAmount !== '0' && $registrationAmount !== '0.00') ||
                                        !empty($registrationType);
                                        @endphp
                                        @if($hasRegistration)
                                        <tr class="grid-row">
                                            <td class="ql-label">Registration <span
                                                    id="registration_option_label"></span></td>
                                            <td class="ql-amount">
                                                <input type="text" id="registration_amount" name="registration_amount"
                                                    class="numeric-only" placeholder="0.00"
                                                    value="{{ old('registration_amount', $registrationAmount) }}">
                                                <select name="registration_type" id="registration_type"
                                                    style="display:none;">
                                                    @foreach($registration_type_map as $key=>$value)
                                                    <option value="{{ $key }}" {{ old('registration_type',
                                                        $registrationType)==$key ? 'selected' : '' }}>{{ $value }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Accessories --}}
                                        @php
                                        $accessoriesAmount = $otfData['accessories_amount'] ?? '0.00';
                                        $selectedAccessories = $selectedAccessories ?? [];
                                        $hasAccessories = !empty($selectedAccessories) || (!empty($accessoriesAmount) &&
                                        $accessoriesAmount !== 'N/A' && $accessoriesAmount !== '0' && $accessoriesAmount
                                        !== '0.00');
                                        @endphp
                                        @if($hasAccessories)
                                        <tr class="grid-row">
                                            <td class="ql-label">Accessories <span id="accessories_price_label"></span>
                                            </td>
                                            <td class="ql-amount">
                                                <input id="accessories_amount" name="accessories_amount"
                                                    class="numeric-only" readonly
                                                    value="{{ old('accessories_amount', $accessoriesAmount) }}">
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Maxicare --}}
                                        @php
                                        $maxicare = $otfData['maxicare'] ?? '';
                                        $hasMaxicare = !empty($maxicare) && $maxicare !== 'N/A' && $maxicare !== '0' &&
                                        $maxicare !== '0.00';
                                        @endphp
                                        @if($hasMaxicare)
                                        <tr class="grid-row">
                                            <td class="ql-label">Maxicare</td>
                                            <td class="ql-amount"><input id="maxicare" name="maxicare"
                                                    class="numeric-only" value="{{ old('maxicare', $maxicare) }}"></td>
                                        </tr>
                                        @endif

                                        {{-- VLTD Device --}}
                                        @php
                                        $vltdDevice = $otfData['vltd_device'] ?? '';
                                        $hasVltd = !empty($vltdDevice) && $vltdDevice !== 'N/A' && $vltdDevice !== '0'
                                        && $vltdDevice !== '0.00';
                                        @endphp
                                        @if($hasVltd)
                                        <tr class="grid-row">
                                            <td class="ql-label">VLTD Device (GPS)</td>
                                            <td class="ql-amount"><input id="vltd_device" name="vltd_device"
                                                    class="numeric-only" value="{{ old('vltd_device', $vltdDevice) }}">
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Coating --}}
                                        @php
                                        $coatingPrice = $otfData['coating_price'] ?? '';
                                        $coating = $otfData['coating'] ?? '';
                                        // Only show if price has actual value and coating is not "No Coating"
                                        $hasCoating = !empty($coatingPrice) &&
                                        $coatingPrice !== 'N/A' &&
                                        $coatingPrice !== '0' &&
                                        $coatingPrice !== '0.00' &&
                                        $coating !== 'No Coating';
                                        @endphp
                                        @if($hasCoating)
                                        <tr class="grid-row">
                                            <td class="ql-label">Coating <span id="coating_option_label"></span></td>
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
                                        @endif


                                        {{-- PPF --}}
                                        @php
                                        $ppf = $otfData['ppf'] ?? '';
                                        $hasPpf = !empty($ppf) && $ppf !== 'N/A' && $ppf !== '0' && $ppf !== '0.00';
                                        @endphp
                                        @if($hasPpf)
                                        <tr class="grid-row">
                                            <td class="ql-label">PPF</td>
                                            <td class="ql-amount"><input id="ppf" name="ppf" class="numeric-only"
                                                    value="{{ old('ppf', $ppf) }}"></td>
                                        </tr>
                                        @endif

                                        {{-- RTO Yellow Tape --}}
                                        @php
                                        $rtoYellowTape = $otfData['rto_yellow_tape'] ?? '';
                                        $hasRtoYellowTape = !empty($rtoYellowTape) && $rtoYellowTape !== 'N/A' &&
                                        $rtoYellowTape !== '0' && $rtoYellowTape !== '0.00';
                                        @endphp
                                        @if($hasRtoYellowTape)
                                        <tr class="grid-row">
                                            <td class="ql-label">RTO Yellow Tape</td>
                                            <td class="ql-amount"><input id="rto_yellow_tape" name="rto_yellow_tape"
                                                    class="numeric-only"
                                                    value="{{ old('rto_yellow_tape', $rtoYellowTape) }}"></td>
                                        </tr>
                                        @endif

                                        {{-- Kazam Charging Kit --}}
                                        @php
                                        $kazamChargingKit = $otfData['kazam_charging_kit'] ?? '';
                                        $hasKazam = !empty($kazamChargingKit) && $kazamChargingKit !== 'N/A' &&
                                        $kazamChargingKit !== '0' && $kazamChargingKit !== '0.00';
                                        @endphp
                                        @if($hasKazam)
                                        <tr class="grid-row">
                                            <td class="ql-label">Kazam Charging Kit</td>
                                            <td class="ql-amount"><input id="kazam_charging_kit"
                                                    name="kazam_charging_kit" class="numeric-only"
                                                    value="{{ old('kazam_charging_kit', $kazamChargingKit) }}"></td>
                                        </tr>
                                        @endif

                                        {{-- Incidental Charges --}}
                                        @php
                                        $incidentalCharges = $otfData['incidental_charges'] ?? '';
                                        $hasIncidental = !empty($incidentalCharges) && $incidentalCharges !== 'N/A' &&
                                        $incidentalCharges !== '0' && $incidentalCharges !== '0.00';
                                        @endphp
                                        @if($hasIncidental)
                                        <tr class="grid-row">
                                            <td class="ql-label">Incidental Charges</td>
                                            <td class="ql-amount"><input id="incidental_charges"
                                                    name="incidental_charges" class="numeric-only"
                                                    value="{{ old('incidental_charges', $incidentalCharges) }}"></td>
                                        </tr>
                                        @endif


                                        {{-- Shield --}}
                                        @php
                                        $shieldPrice = $otfData['shield_price'] ?? '';
                                        $shield = $otfData['shield'] ?? '';
                                        // Only show if price has actual value and shield is not "No Shield"
                                        $hasShield = !empty($shieldPrice) &&
                                        $shieldPrice !== 'N/A' &&
                                        $shieldPrice !== '0' &&
                                        $shieldPrice !== '0.00' &&
                                        $shield !== 'No Shield';
                                        @endphp
                                        @if($hasShield)
                                        <tr class="grid-row">
                                            <td class="ql-label">Shield <span id="shield_option_label"></span></td>
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
                                        @endif

                                        {{-- RSA --}}
                                        @php
                                        $rsaAmount = $otfData['rsa_amount'] ?? '';
                                        $rsa = $otfData['rsa'] ?? '';
                                        // Only show if amount has actual value and RSA is not "No RSA"
                                        $hasRsa = !empty($rsaAmount) &&
                                        $rsaAmount !== 'N/A' &&
                                        $rsaAmount !== '0' &&
                                        $rsaAmount !== '0.00' &&
                                        $rsa !== 'No RSA';
                                        @endphp
                                        @if($hasRsa)
                                        <tr class="grid-row">
                                            <td class="ql-label">RSA <span id="rsa_option_label"></span></td>
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
                                        @endif

                                        {{-- Fastag --}}
                                        @php
                                        $fastag = $otfData['fastag'] ?? '';
                                        $hasFastag = !empty($fastag) && $fastag !== 'N/A' && $fastag !== '0' && $fastag
                                        !== '0.00';
                                        @endphp
                                        @if($hasFastag)
                                        <tr class="grid-row">
                                            <td class="ql-label">Fastag</td>
                                            <td class="ql-amount"><input id="fastag" name="fastag" class="numeric-only"
                                                    value="{{ old('fastag', $fastag) }}"></td>
                                        </tr>
                                        @endif

                                        {{-- COD Charges --}}
                                        @php
                                        $codCharges = $otfData['cod_charges'] ?? '';
                                        $hasCod = !empty($codCharges) && $codCharges !== 'N/A' && $codCharges !== '0' &&
                                        $codCharges !== '0.00';
                                        @endphp
                                        @if($hasCod)
                                        <tr class="grid-row">
                                            <td class="ql-label">COD Charges</td>
                                            <td class="ql-amount"><input id="cod_charges" name="cod_charges"
                                                    class="numeric-only" value="{{ old('cod_charges', $codCharges) }}">
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Charger Swapping --}}
                                        @php
                                        $chargerSwappingAmount = $otfData['charger_swapping_amount'] ?? '';
                                        $chargerSwapping = $otfData['charger_swapping'] ?? '';
                                        $hasChargerSwapping = (!empty($chargerSwappingAmount) && $chargerSwappingAmount
                                        !== 'N/A' && $chargerSwappingAmount !== '0' && $chargerSwappingAmount !==
                                        '0.00') || (!empty($chargerSwapping) && $chargerSwapping !== 'N/A');
                                        @endphp
                                        @if($hasChargerSwapping)
                                        <tr class="grid-row">
                                            <td class="ql-label">Charger Swapping <span
                                                    id="charger_swapping_option_label"></span></td>
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
                                        @endif

                                        {{-- TCS --}}
                                        @php
                                        $tcs = $otfData['tcs'] ?? '';
                                        $hasTcs = !empty($tcs) && $tcs !== 'N/A' && $tcs !== '0' && $tcs !== '0.00';
                                        @endphp
                                        @if($hasTcs)
                                        <tr class="grid-row">
                                            <td class="ql-label">TCS @1%</td>
                                            <td class="ql-amount"><input id="tcs" name="tcs" class="numeric-only"
                                                    readonly value="{{ old('tcs', $tcs) }}"></td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>

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
                                        $hasGroupA = (!empty($cashSchemeOem) && $cashSchemeOem !== 'N/A' &&
                                        $cashSchemeOem !== '0' && $cashSchemeOem !== '0.00') ||
                                        (!empty($csdDiscount) && $csdDiscount !== 'N/A' && $csdDiscount !== '0' &&
                                        $csdDiscount !== '0.00') ||
                                        (!empty($fameSubsidy) && $fameSubsidy !== 'N/A' && $fameSubsidy !== '0' &&
                                        $fameSubsidy !== '0.00');
                                        @endphp
                                        @if($hasGroupA)
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                <select id="group_a_select" class="group-select">
                                                    <option value="cash_scheme_oem" {{ old('group_a_select',
                                                        $groupASelected ?? '' )=='cash_scheme_oem' ? 'selected' : '' }}>
                                                        Cash Scheme OEM</option>
                                                    <option value="csd_discount" {{ old('group_a_select',
                                                        $groupASelected ?? '' )=='csd_discount' ? 'selected' : '' }}>CSD
                                                        Discount</option>
                                                    <option value="fame_subsidy" id="fame_subsidy_option" {{
                                                        old('group_a_select', $groupASelected ?? '' )=='fame_subsidy'
                                                        ? 'selected' : '' }}>Fame Subsidy (LMM)</option>
                                                </select>
                                            </td>
                                            <td class="ql-amount">
                                                <input type="text" id="group_a_amount" class="numeric-only"
                                                    placeholder="0.00"
                                                    value="{{ old('group_a_amount', $cashSchemeOem ?: $csdDiscount ?: $fameSubsidy ?: '') }}">
                                                <select id="group_a_type" style="display:none;">
                                                    <option value="INV">INV</option>
                                                    <option value="CN">CN</option>
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
                                        @endif

                                        {{-- Cash Scheme Dealer --}}
                                        @php
                                        $dealerDiscount = $otfData['dealer_discount'] ?? '';
                                        $hasDealerDiscount = !empty($dealerDiscount) && $dealerDiscount !== 'N/A' &&
                                        $dealerDiscount !== '0' && $dealerDiscount !== '0.00';
                                        @endphp
                                        @if($hasDealerDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label">Cash Scheme Dealer</td>
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
                                        @endif

                                        {{-- Accessories Scheme --}}
                                        @php
                                        $accessoriesDiscount = $otfData['accessories_discount'] ?? '';
                                        $hasAccessoriesDiscount = !empty($accessoriesDiscount) && $accessoriesDiscount
                                        !== 'N/A' && $accessoriesDiscount !== '0' && $accessoriesDiscount !== '0.00';
                                        @endphp
                                        @if($hasAccessoriesDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label">Accessories Scheme</td>
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
                                        @endif

                                        {{-- Shield Scheme --}}
                                        @php
                                        $shieldScheme = $otfData['shield_scheme'] ?? '';
                                        $hasShieldScheme = !empty($shieldScheme) && $shieldScheme !== 'N/A' &&
                                        $shieldScheme !== '0' && $shieldScheme !== '0.00';
                                        @endphp
                                        @if($hasShieldScheme)
                                        <tr class="grid-row">
                                            <td class="ql-label">Shield Scheme</td>
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
                                        @endif

                                        {{-- Group B --}}
                                        @php
                                        $corporateDiscount = $otfData['corporate_discount'] ?? '';
                                        $loyaltyBonus = $otfData['loyalty_bonus'] ?? '';
                                        $hasGroupB = (!empty($corporateDiscount) && $corporateDiscount !== 'N/A' &&
                                        $corporateDiscount !== '0' && $corporateDiscount !== '0.00') ||
                                        (!empty($loyaltyBonus) && $loyaltyBonus !== 'N/A' && $loyaltyBonus !== '0' &&
                                        $loyaltyBonus !== '0.00');
                                        @endphp
                                        @if($hasGroupB)
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                <select id="group_b_select" class="group-select">
                                                    <option value="corporate_discount" {{ old('group_b_select',
                                                        $groupBSelected ?? '' )=='corporate_discount' ? 'selected' : ''
                                                        }}>Corporate Discount</option>
                                                    <option value="loyalty_bonus" {{ old('group_b_select',
                                                        $groupBSelected ?? '' )=='loyalty_bonus' ? 'selected' : '' }}>
                                                        Loyalty Bonus</option>
                                                </select>
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
                                        @endif

                                        {{-- Group C --}}
                                        @php
                                        $exchangeBonus = $otfData['exchange_bonus'] ?? '';
                                        $greenBonus = $otfData['green_bonus'] ?? '';
                                        $welcomeBonus = $otfData['welcome_bonus'] ?? '';
                                        $hasGroupC = (!empty($exchangeBonus) && $exchangeBonus !== 'N/A' &&
                                        $exchangeBonus !== '0' && $exchangeBonus !== '0.00') ||
                                        (!empty($greenBonus) && $greenBonus !== 'N/A' && $greenBonus !== '0' &&
                                        $greenBonus !== '0.00') ||
                                        (!empty($welcomeBonus) && $welcomeBonus !== 'N/A' && $welcomeBonus !== '0' &&
                                        $welcomeBonus !== '0.00');
                                        @endphp
                                        @if($hasGroupC)
                                        <tr class="grid-row">
                                            <td class="ql-label">
                                                <select id="group_c_select" class="group-select">
                                                    <option value="exchange_bonus" {{ old('group_c_select',
                                                        $groupCSelected ?? '' )=='exchange_bonus' ? 'selected' : '' }}>
                                                        Exchange Bonus</option>
                                                    <option value="green_bonus" {{ old('group_c_select', $groupCSelected
                                                        ?? '' )=='green_bonus' ? 'selected' : '' }}>Green Bonus</option>
                                                    <option value="welcome_bonus" {{ old('group_c_select',
                                                        $groupCSelected ?? '' )=='welcome_bonus' ? 'selected' : '' }}>
                                                        Welcome Bonus</option>
                                                </select>
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
                                        @endif

                                        {{-- Accessories Spl Disc --}}
                                        @php
                                        $accessoriesSplDisc = $otfData['accessories_spl_disc'] ?? '';
                                        $hasAccessoriesSplDisc = !empty($accessoriesSplDisc) && $accessoriesSplDisc !==
                                        'N/A' && $accessoriesSplDisc !== '0' && $accessoriesSplDisc !== '0.00';
                                        @endphp
                                        @if($hasAccessoriesSplDisc)
                                        <tr class="grid-row">
                                            <td class="ql-label">Accessories Spl Disc</td>
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
                                        @endif

                                        {{-- Coating Spl Discount --}}
                                        @php
                                        $ceramicDiscount = $otfData['ceramic_discount'] ?? '';
                                        $hasCeramicDiscount = !empty($ceramicDiscount) && $ceramicDiscount !== 'N/A' &&
                                        $ceramicDiscount !== '0' && $ceramicDiscount !== '0.00';
                                        @endphp
                                        @if($hasCeramicDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label" id="coating_discount_label">Coating Spl Discount</td>
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
                                        @endif

                                        {{-- PPF Spl Discount --}}
                                        @php
                                        $ppfDiscount = $otfData['ppf_discount'] ?? '';
                                        $hasPpfDiscount = !empty($ppfDiscount) && $ppfDiscount !== 'N/A' && $ppfDiscount
                                        !== '0' && $ppfDiscount !== '0.00';
                                        @endphp
                                        @if($hasPpfDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label">PPF Spl Discount</td>
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
                                        @endif

                                        {{-- Charger Swapping Discount --}}
                                        @php
                                        $chargerSwappingDiscount = $otfData['charger_swapping_discount'] ?? '';
                                        $hasChargerSwappingDiscount = !empty($chargerSwappingDiscount) &&
                                        $chargerSwappingDiscount !== 'N/A' && $chargerSwappingDiscount !== '0' &&
                                        $chargerSwappingDiscount !== '0.00';
                                        @endphp
                                        @if($hasChargerSwappingDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label" id="charger_discount_title">Charger Swapping Discount
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
                                        @endif

                                        {{-- Other Cash Discount --}}
                                        @php
                                        $otherCashDiscount = $otfData['other_cash_discount'] ?? '';
                                        $hasOtherCashDiscount = !empty($otherCashDiscount) && $otherCashDiscount !==
                                        'N/A' && $otherCashDiscount !== '0' && $otherCashDiscount !== '0.00';
                                        @endphp
                                        @if($hasOtherCashDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label">Other Cash Discount</td>
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
                                        @endif

                                        {{-- Special Cash Discount --}}
                                        @php
                                        $specialCashDiscount = $otfData['special_cash_discount'] ?? '';
                                        $hasSpecialCashDiscount = !empty($specialCashDiscount) && $specialCashDiscount
                                        !== 'N/A' && $specialCashDiscount !== '0' && $specialCashDiscount !== '0.00';
                                        @endphp
                                        @if($hasSpecialCashDiscount)
                                        <tr class="grid-row">
                                            <td class="ql-label">Special Cash Discount</td>
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
                                        @endif

                                    </tbody>
                                </table>

                                {{-- TOTAL DISCOUNT & TOTAL RECEIVABLE --}}
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
                                            value="{{ $financierName ?? '' }}" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Financier Branch</td>
                                    <td>
                                        <input type="text" id="financier_branch" name="financier_branch"
                                            class="form-control"
                                            value="{{ old('financier_branch', $finance?->branch ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Loan Amount</td>
                                    <td>
                                        <input type="number" id="loan_amount" name="loan_amount" class="form-control"
                                            value="{{ old('loan_amount', $finance?->loan_amount ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Deduction</td>
                                    <td>
                                        <input type="number" id="deduction" name="deduction" class="form-control"
                                            value="{{ old('deduction') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Margin Money Deposited By Financier</td>
                                    <td>
                                        <input type="number" id="margin_money" name="margin_money" class="form-control"
                                            value="{{ old('margin_money', $finance?->margin ?? '') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Amount</td>
                                    <td>
                                        <input type="number" id="do_amount" name="do_amount"
                                            class="form-control bg-light"
                                            value="{{ old('do_amount', $finance?->loan_amount ?? '') }}" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Financier Subvention Amount</td>
                                    <td>
                                        <input type="number" id="financier_subvention" name="financier_subvention"
                                            class="form-control"
                                            value="{{ old('financier_subvention', $finance?->subvention_amount ?? '') }}">
                                    </td>
                                </tr>
                            </table>
                            {{-- Receipt Table --}}
                            <div class="col-12">
                                <div class="form-section">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm mb-0 receipt-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Receipt No.</th>
                                                    <th>Receipt Date</th>
                                                    <th>Amount</th>
                                                    <th width="70">View</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($receiptLogs ?? [] as $receipt)
                                                <tr>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ $receipt->reciept }}" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control form-control-sm"
                                                            value="{{ $receipt->date }}" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ $receipt->amount }}" readonly>
                                                    </td>
                                                    <td class="text-center">
                                                        @php
                                                        $receiptImage = $receipt->getFirstMediaUrl('amount-proof');
                                                        @endphp
                                                        @if($receiptImage)
                                                        <a href="{{ $receiptImage }}" data-lightbox="receipt-images"
                                                            data-title="Receipt {{ $receipt->reciept }}">
                                                            <i class="la la-eye text-primary"
                                                                style="font-size:20px;"></i>
                                                        </a>
                                                        @else
                                                        <i class="la la-eye-slash text-muted"></i>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">No Receipt Found</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <table class="bill-table mt-2">
                                <tr>
                                    <td class="title" width="33%">DO Settlement Difference</td>
                                    <td width="67%">
                                        <input type="number" id="do_settlement_difference"
                                            name="do_settlement_difference" class="form-control"
                                            value="{{ old('do_settlement_difference') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Expected Balance</td>
                                    <td>
                                        <input type="number" id="expected_balance" name="expected_balance" readonly
                                            class="form-control bg-light" value="{{ old('expected_balance') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">Final Balance</td>
                                    <td>
                                        <input type="number" id="final_balance" name="final_balance" readonly
                                            class="form-control bg-light" value="{{ old('final_balance') }}">
                                    </td>
                                </tr>
                            </table>

                            {{-- Financier Verified, Delivery, DO Details --}}
                            <table class="bill-table mt-2">
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Financier Verified</td>
                                    <td>
                                        <select name="financier_verified" id="financier_verified" style="width:100%;">
                                            <option value="Please Select">Please Select</option>
                                            <option value="Yes">Yes</option>
                                            <option value="No">No</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Vehicle To Be Delivered On</td>
                                    <td>
                                        <select id="vehicle_delivery_on" name="vehicle_delivery_on" style="width:100%;">
                                            <option value="">Select</option>
                                            <option value="DO">DO</option>
                                            <option value="Payment">Payment</option>
                                            <option value="Mail">Mail</option>
                                            <option value="Whatsapp">Whatsapp</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Number (Delivery Time)</td>
                                    <td>
                                        <input type="text" id="do_number" name="do_number" class="form-control"
                                            style="width:100%;" disabled>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Number (TA Statement)</td>
                                    <td>
                                        <input type="text" id="do_number_ta" name="do_number_ta" class="form-control"
                                            style="width:100%;">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Amount (TA Statement)</td>
                                    <td>
                                        <input type="number" id="do_amount_ta" name="do_amount_ta"
                                            class="form-control bg-light" style="width:100%;" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title">DO Voucher Date</td>
                                    <td>
                                        <input type="date" id="do_voucher_date" name="do_voucher_date"
                                            class="form-control" style="width:100%;" readonly>
                                    </td>
                                </tr>
                            </table>

                            {{-- Brokerage & Other Discount Receivable --}}
                            <table class="bill-table mt-2">
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Brokerage Amount</td>
                                    <td>
                                        <input type="number" id="brokerage_amount" name="brokerage_amount"
                                            class="form-control" style="width:100%;" min="0" step="0.01"
                                            value="{{ old('brokerage_amount') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Other Discount Receivable</td>
                                    <td>
                                        <input type="number" id="other_discount_receivable"
                                            name="other_discount_receivable" class="form-control" style="width:100%;"
                                            min="0" step="0.01" value="{{ old('other_discount_receivable') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Other Discount Receivable - M&amp;M
                                        Support</td>
                                    <td>
                                        <input type="number" id="mm_support_receivable" name="mm_support_receivable"
                                            class="form-control" style="width:100%;" min="0" step="0.01"
                                            value="{{ old('mm_support_receivable') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Other Discount Receivable -
                                        Liquidation Scheme</td>
                                    <td>
                                        <input type="number" id="liquidation_scheme_receivable"
                                            name="liquidation_scheme_receivable" class="form-control"
                                            style="width:100%;" min="0" step="0.01"
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
                                        <input type="number" id="registration_service_charge_receivable"
                                            name="registration_service_charge_receivable" class="form-control"
                                            style="width:100%;" min="0" step="0.01"
                                            value="{{ old('registration_service_charge_receivable') }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="title" style="white-space:nowrap;">Registration Service Charge - Received
                                    </td>
                                    <td>
                                        <input type="number" id="registration_service_charge_received"
                                            name="registration_service_charge_received" class="form-control"
                                            style="width:100%;" min="0" step="0.01"
                                            value="{{ old('registration_service_charge_received') }}">
                                    </td>
                                </tr>
                            </table>

                        </div>
                    </div>

                    <div class="accessories-note-row">
                        Accessories:
                        <span id="accessories_print"
                            style="font-weight:normal; display:inline-block; min-width:70%; border-bottom:1px solid #000;">&nbsp;</span>
                    </div>

                    {{-- ================= NOTE ================= --}}
                    <table class="bill-table note-box flex-grow-1">
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
        <i class="la la-save"></i> Save Quotation
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

<script>
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
        list.push(name.replace(/\(.*?\)/,'').trim() + ' (₹' + price.toLocaleString('en-IN') + ')');
    });
    $('#accessories_print').text(list.join(', '));
    $('.select2-search__field').attr('placeholder', list.length + ' Accessories Selected');
}

$('#accessories').on('change', function () {
    updateAccessoriesAmount();
    updateAccessoriesPrintText();
});

$(document).ready(function () {
    updateAccessoriesPrintText();
    updateAccessoriesAmount();
});

// ================= GROUP DISCOUNT SYNC =================
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

// ================= PRE-POPULATE GROUP DISCOUNTS ON PAGE LOAD =================
$(document).ready(function() {
    // Group A - set selected option based on which field has value
    var groupAFields = {
        'cash_scheme_oem': $('#cash_scheme_oem').val(),
        'csd_discount': $('#csd_discount').val(),
        'fame_subsidy': $('#fame_subsidy').val()
    };
    
    for (var key in groupAFields) {
        if (groupAFields[key] && groupAFields[key] !== '') {
            $('#group_a_select').val(key);
            $('#group_a_type').val($('#' + key + '_type').val() || 'INV');
            break;
        }
    }
    
    // Group B
    var groupBFields = {
        'corporate_discount': $('#corporate_discount').val(),
        'loyalty_bonus': $('#loyalty_bonus').val()
    };
    
    for (var key in groupBFields) {
        if (groupBFields[key] && groupBFields[key] !== '') {
            $('#group_b_select').val(key);
            $('#group_b_type').val($('#' + key + '_type').val() || 'INV');
            break;
        }
    }
    
    // Group C
    var groupCFields = {
        'exchange_bonus': $('#exchange_bonus').val(),
        'green_bonus': $('#green_bonus').val(),
        'welcome_bonus': $('#welcome_bonus').val()
    };
    
    for (var key in groupCFields) {
        if (groupCFields[key] && groupCFields[key] !== '') {
            $('#group_c_select').val(key);
            $('#group_c_type').val($('#' + key + '_type').val() || 'CN1');
            break;
        }
    }
    
    // Trigger sync for all groups to populate hidden fields
    $('#group_a_select, #group_a_type, #group_a_amount').trigger('change');
    $('#group_b_select, #group_b_type, #group_b_amount').trigger('change');
    $('#group_c_select, #group_c_type, #group_c_amount').trigger('change');
    
    // Recalculate after pre-fill
    setTimeout(function() {
        calculateQuotation();
    }, 100);
});

function num(id) {
    let value = $('#' + id).val();
    if (value === 'N/A' || value === '' || value == null) return 0;
    return parseFloat(value) || 0;
}

// Discount type pairs for bifurcation — kept for JS calc, but UI hidden
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
        if (type === 'INV') invoicedDiscount += amount;
        else if (type === 'CN' || type === 'CN1' || type === 'CN2') creditNoteDiscount += amount;
    });
    return { invoicedDiscount: invoicedDiscount, creditNoteDiscount: creditNoteDiscount };
}

function calculateQuotation() {
    let subtotal = 0;
    let fields = [
        'ex_showroom_price',
        'insurance_amount',
        'registration_amount',
        'accessories_amount',
        'maxicare',
        'vltd_device',
        'coating_price',
        'ppf',
        'rto_yellow_tape',
        'kazam_charging_kit',
        'incidental_charges',
        'shield_price',
        'rsa_amount',
        'fastag',
        'cod_charges',
        'charger_swapping_amount'
    ];
    
    fields.forEach(function(field) {
        subtotal += num(field);
    });

    let bifurcation = calculateDiscountBifurcation();
    let finvoiceAmount = subtotal - bifurcation.invoicedDiscount;

    let tcs = 0;
    if (finvoiceAmount >= 1000000) {
        tcs = finvoiceAmount * 0.01;
        $('#tcs').val(tcs.toFixed(2)).prop('readonly', true).prop('disabled', false);
    } else {
        $('#tcs').val('N/A').prop('readonly', true).prop('disabled', true);
    }
    let totalReceivable = subtotal + tcs;
    $('#total_receivable').val(totalReceivable.toFixed(2));

    let totalDiscount = 0;
    let discountFields = [
        'cash_scheme_oem',
        'fame_subsidy',
        'exchange_bonus',
        'corporate_discount',
        'accessories_discount',
        'ceramic_discount',
        'ppf_discount',
        'dealer_discount',
        'charger_swapping_discount',
        'csd_discount',
        'shield_scheme',
        'loyalty_bonus',
        'green_bonus',
        'welcome_bonus',
        'accessories_spl_disc',
        'other_cash_discount',
        'special_cash_discount'
    ];
    
    discountFields.forEach(function(field) {
        totalDiscount += num(field);
    });

    let discount = totalDiscount.toFixed(2);
    $('#total_discount_amount').val(discount);
    $('#total_discount').val(discount);

    let netReceivable = totalReceivable - totalDiscount;
    $('#net_receivable_summary').val(netReceivable.toFixed(2));

    // Bifurcation values still calculated but hidden from UI
    // No longer displaying Financier Invoice / Discount Bifurcation box
}

// Recalculate on changes - only for visible fields
$(document).on('keyup change', 
    '#ex_showroom_price, #insurance_amount, #registration_amount, #accessories_amount, ' +
    '#maxicare, #vltd_device, #coating_price, #ppf, #rto_yellow_tape, #kazam_charging_kit, ' +
    '#incidental_charges, #shield_price, #rsa_amount, #fastag, #cod_charges, #charger_swapping_amount, ' +
    '#tcs, #cash_scheme_oem, #csd_discount, #fame_subsidy, ' +
    '#dealer_discount, #corporate_discount, #loyalty_bonus, ' +
    '#accessories_discount, #shield_scheme, #exchange_bonus, #green_bonus, #welcome_bonus, ' +
    '#accessories_spl_disc, #ceramic_discount, #ppf_discount, #charger_swapping_discount, ' +
    '#other_cash_discount, #special_cash_discount',
    calculateQuotation
);

$('#accessories').on('change', function () {
    updateAccessoriesAmount();
    calculateQuotation();
});

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

    calculateQuotation();
}

$(document).ready(function () {
    toggleLMMFields();
});

// ================= PRINT LABEL FOLDING =================
let printLabelRestoreList = [];

function prepareOptionLabelsForPrint() {
    printLabelRestoreList = [];
    $('.quotation-stacked-table td.ql-label select').not('#accessories').each(function () {
        let $select = $(this);
        let selectedText = $select.find('option:selected').first().text().trim();
        if (!selectedText || selectedText.toLowerCase() === 'select') return;
        let $label = $select.closest('tr').find('td.ql-label').first();
        printLabelRestoreList.push({ el: $label, html: $label.html() });
        if ($select.hasClass('group-select')) {
            $label.text(selectedText);
        } else {
            let baseLabel = $label.text().replace(/\(.*\)/, '').trim();
            $label.html(baseLabel + ' (' + selectedText + ')');
        }
    });
    $('.quotation-stacked-table td.ql-amount select').each(function () {
        let $select = $(this);
        let selectedText = $select.find('option:selected').first().text().trim();
        if (!selectedText || selectedText.toLowerCase() === 'select' || selectedText === 'N/A') return;
        let $label = $select.closest('tr').find('td.ql-label').first();
        if (!$label.text().includes('(')) {
            let baseLabel = $label.text().replace(/\(.*\)/, '').trim();
            printLabelRestoreList.push({ el: $label, html: $label.html() });
            $label.html(baseLabel + ' (' + selectedText + ')');
        }
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
    syncOptionLabel('#policy_type', 'insurance_option_label');
    syncOptionLabel('#registration_type', 'registration_option_label');
    syncOptionLabel('#coating', 'coating_option_label');
    syncOptionLabel('#shield', 'shield_option_label');
    syncOptionLabel('#rsa', 'rsa_option_label');
    syncOptionLabel('#charger_swapping', 'charger_swapping_option_label');

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
        calculateQuotation();
    }
    toggleVltdField();

    // Policy/Registration/Coating/Shield/RSA/ChargerSwapping — enable/disable on change
    $('#policy_type').on('change', function () {
        let hasValue = $(this).val() !== '';
        $('#insurance_amount').prop('disabled', !hasValue).val(hasValue ? $('#insurance_amount').val() : '');
        calculateQuotation();
    });
    $('#registration_type').on('change', function () {
        let hasValue = $(this).val() !== '';
        $('#registration_amount').prop('disabled', !hasValue).val(hasValue ? $('#registration_amount').val() : '');
        calculateQuotation();
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
        calculateQuotation();
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
        calculateQuotation();
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
        calculateQuotation();
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
        calculateQuotation();
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

// Form submit validation (Cash OEM INV + CN check)
$('form').on('submit', function (e) {
    let cashOemAmount = num('cash_scheme_oem');
    let cashOemType = $('#cash_scheme_oem_type').val();
    let bifurcation = calculateDiscountBifurcation();
    let totalCNDiscount = bifurcation.creditNoteDiscount;
    if (cashOemType === 'INV' && totalCNDiscount < cashOemAmount) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Cannot Save Quotation',
            text: 'Total CN Discount should be equal to or greater than Cash OEM Scheme when Cash OEM Scheme Type is INV.'
        });
        return false;
    }
});

$(document).ready(function () {
    function toggleDONumber() {
        var delivery = $('#vehicle_delivery_on').val();
        if (delivery === 'DO') {
            $('#do_number').prop('disabled', false);
        } else {
            $('#do_number').val('').prop('disabled', true);
        }
    }

    toggleDONumber();
    $('#vehicle_delivery_on').on('change', function () {
        toggleDONumber();
    });
});

// DO Number (TA Statement) — fetch DO Amount & Date
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
            $('#do_amount_ta').val(res.amount);
            $('#do_voucher_date').val(res.date);
        }
    });
});
</script>
@endpush