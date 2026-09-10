@extends(backpack_view('blank'))
@php
$chassisImage = $booking->getFirstMediaUrl('chassis_image');
@endphp
@php
use App\Services\OrgService;
use Illuminate\Support\Facades\DB;
@endphp


@section('title', 'OTF Form')

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.5/css/lightbox.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .form-section {
        margin-bottom: 20px;
    }

    .form-section h4 {
        font-weight: 600;
        margin-bottom: 15px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    #otfPreviewPage {
        background: #eef1f4;
        padding: 24px 0;
        margin: 0;
    }

    .print-page {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto;
        padding: 9mm 10mm 12mm;
        background: #fff;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        box-sizing: border-box;
        font-family: 'Inter', Arial, sans-serif;
        color: #111827;
    }

    #printScaleWrapper {
        width: 100%;
    }

    .otf-top {
        display: grid;
        grid-template-columns: 1.7fr .9fr;
        gap: 8px;
        margin-bottom: 8px;
    }

    .otf-box {
        border: 1px solid #d9dee5;
        background: #fff;
    }

    .otf-brand {
        padding: 8px 10px;
    }

    .otf-brand h1 {
        margin: 0;
        font-size: 15px;
        letter-spacing: .2px;
        font-weight: 700;
    }

    .otf-brand p {
        margin: 2px 0 0;
        font-size: 9px;
        color: #6b7280;
        line-height: 1.35;
    }

    .otf-meta {
        padding: 8px 10px;
        background: #f8fafc;
    }

    .otf-meta .otf-tag {
        font-size: 9px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 4px;
    }

    .otf-meta .otf-row {
        font-size: 10px;
        line-height: 1.45;
    }

    .otf-grid3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        margin-bottom: 8px;
    }

    .otf-grid2 {
        display: grid;
        grid-template-columns: 1.15fr .85fr;
        gap: 8px;
        margin-bottom: 8px;
    }

    .otf-section {
        border: 1px solid #d9dee5;
        padding: 7px 8px;
        background: #fff;
        break-inside: avoid;
    }

    .otf-section h3 {
        margin: 0 0 5px;
        font-size: 9px;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: #4b5563;
    }

    .otf-kv {
        display: grid;
        grid-template-columns: 78px 1fr;
        gap: 6px;
        padding: 1.5px 0;
        font-size: 10px;
        line-height: 1.35;
    }

    .otf-kv .otf-k {
        color: #6b7280;
    }

    .otf-kv .otf-v {
        font-weight: 500;
        word-break: break-word;
    }

    .otf-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .otf-chip {
        font-size: 9px;
        line-height: 1;
        padding: 4px 6px;
        border: 1px solid #d9dee5;
        background: #f9fafb;
        color: #1f2937;
    }

    .otf-empty {
        font-size: 9px;
        color: #9ca3af;
    }

    .otf-table {
        width: 100%;
        border-collapse: collapse;
    }

    .otf-table th,
    .otf-table td {
        border-bottom: 1px solid #e5e7eb;
        padding: 4px 3px;
        font-size: 9.5px;
        line-height: 1.25;
        text-align: left;
        vertical-align: top;
    }

    .otf-table th {
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #6b7280;
        font-weight: 600;
    }

    .otf-table .otf-amt {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .otf-totals .otf-trow {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 4px 0;
        border-bottom: 1px solid #eceff3;
        font-size: 10px;
        line-height: 1.3;
    }

    .otf-totals .otf-trow:last-child {
        border-bottom: 0;
    }

    .otf-totals .otf-grand {
        font-weight: 700;
        font-size: 11px;
        padding-top: 6px;
    }

    .otf-note {
        font-size: 8.3px;
        line-height: 1.35;
        color: #4b5563;
        margin: 0;
        padding-left: 14px;
    }

    .otf-signs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-top: 22px;
    }

    .otf-sign {
        border-top: 1px solid #9ca3af;
        padding-top: 4px;
        font-size: 9px;
        color: #6b7280;
        text-align: center;
    }

    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body,
        html {
            margin: 0 !important;
            padding: 0 !important;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        .no-print,
        #otfFormSection,
        .page-header,
        .navbar,
        .sidebar {
            display: none !important;
        }

        #otfPreviewPage {
            background: #fff !important;
            padding: 0 !important;
        }

        .print-page {
            width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 !important;
            box-shadow: none !important;
            padding: 9mm 10mm 12mm !important;
        }
    }

    .disclaimer-box {
        border: 2px solid #000;
        padding: 6px;
        font-size: 8px;
        line-height: 1.3;
    }

    .disclaimer-box h4 {
        margin: 0 0 4px;
        font-size: 9px;
        font-weight: 700;
    }

    .disclaimer-box ol {
        margin: 0;
        padding-left: 15px;
    }

    .disclaimer-box li {
        margin-bottom: 2px;
    }

    .top-info-row {
        display: flex;
        gap: 8px;
        margin-bottom: 4px;
    }

    .top-box {
        flex: 1;
    }

    .top-box-title {
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .top-box-value {
        border: 1.5px solid #000;
        min-height: 28px;
        font-size: 14px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .customer-signature {
        margin-top: 30px;
        padding-top: 5px;
        border-top: 1px solid #000;
        width: 180px;
        margin-left: auto;
        text-align: center;
        font-size: 10px;
        font-weight: 700;
    }

    #imageEditorPopup {
        display: none;

        position: fixed;
        inset: 0;

        background: rgba(0, 0, 0, .65);

        z-index: 999999;

        justify-content: center;
        align-items: center;
    }

    .editor-container {

        width: 900px;
        max-width: 95vw;

        height: 90vh;

        display: flex;
        flex-direction: column;

    }

    .editor-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        padding: 12px 20px;

        border-bottom: 1px solid #ddd;

    }

    .editor-body {

        flex: 1;

        overflow: hidden;

    }

    .editor-body img {

        max-width: 100%;

    }

    .editor-footer {

        padding: 15px;

        border-top: 1px solid #ddd;

        text-align: center;

    }

    #closeEditor {

        border: none;

        background: none;

        font-size: 24px;

        cursor: pointer;

    }

    .receipt-table {
        border: 3px solid #000 !important;
    }

    .receipt-table th,
    .receipt-table td {
        border: 2px solid #000 !important;
    }
</style>
@endpush

@section('content')

<div id="otfFormSection">

    <div class="container-fluid">

        <div class="page-header">

            <div class="row align-items-center">

                <div class="col-md-6">

                    <h1 class="fw-bold mb-0">
                        OTF Form
                    </h1>

                </div>
            </div>

        </div>

        <form method="POST" action="{{ route('booking.otf.save', $booking->id) }}" enctype="multipart/form-data">

            @csrf
            @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            <div class="card">

                <div class="card-body">

                    <div class="form-section">



                        <div class="row">
                            {{-- GST --}}
                            <div class="col-md-3 mb-3">

                                <label>GST Number</label>

                                <input type="text" name="gstn" id="gstn" class="form-control"
                                    value="{{ old('gstn', $booking->gstn) }}" placeholder="Enter GST Number">

                            </div>

                            {{-- Registration Type --}}
                            <div class="col-md-3 mb-3">
                                <label>Registration Type</label>

                                <select name="registration_no_type" id="registration_no_type"
                                    class="form-control form-select">

                                    <option value="">Select Registration Type</option>

                                    @foreach($reg_no_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('registration_no_type', $rto?->rgn_no_type) ==
                                        $key ? 'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach

                                </select>
                            </div>


                            {{-- Registration Category --}}
                            <div class="col-md-3 mb-3">
                                <label>Registration Category</label>

                                <select name="registration_category" id="registration_category"
                                    class="form-control form-select">

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
                            </div>

                            {{-- Permit --}}
                            <div class="col-md-3 mb-3">
                                <label>Permit</label>

                                <select name="permit" id="permit" class="form-control form-select">
                                    <option value="">Select Permit</option>

                                    @foreach($permit_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('permit', $rto?->permit) == $key ? 'selected' : ''
                                        }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- FSC Name --}}
                            <div class="col-md-3 mb-3">
                                <label>SC Name</label>

                                <select name="sale_consultant" id="saleconsultant" class="form-control form-select">

                                    <option value="">Select Sales Consultant</option>

                                    @foreach($salesconsultants as $consultant)
                                    <option value="{{ $consultant['person_code'] }}" {{ $booking->sale_consultant ==
                                        $consultant['person_code'] ? 'selected' : '' }}>
                                        {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- FSC Mile ID --}}
                            <div class="col-md-3 mb-3">
                                <label>SC Mile ID</label>

                                <input type="text" id="sc_mile_id" class="form-control" readonly>
                            </div>

                            {{-- FSC Branch --}}
                            <div class="col-md-3 mb-3">
                                <label>SC Branch</label>

                                <input type="text" id="sc_branch" class="form-control" readonly>
                            </div>
                            {{-- FSC Location --}}
                            <div class="col-md-3 mb-3">
                                <label>SC Location</label>

                                <input type="text" id="sc_location" class="form-control" readonly>
                            </div>


                            {{-- DMS Enquiry No. --}}
                            <div class="col-md-3 mb-3">

                                <label>DMS Enquiry Number</label>

                                <input type="text" name="dms_no" id="dms_no" class="form-control"
                                    value="{{ old('dms_no', $booking->dms_no) }}">

                            </div>

                            {{-- DMS OTF No. --}}
                            <div class="col-md-3 mb-3">

                                <label>DMS OTF Number</label>

                                <input type="text" name="dms_otf" id="dms_otf" class="form-control"
                                    value="{{ old('dms_otf', $booking->dms_otf) }}">

                            </div>

                            {{-- Xcler8 Booking ID --}}
                            <div class="col-md-3 mb-3">
                                <label>Xcler8 Booking ID</label>
                                <input type="text" class="form-control" value="{{ $booking->id }}" readonly>
                            </div>

                            {{-- VOTF Number --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    VOTF Number
                                </label>

                                <input type="text" name="votf_no" id="votf_no" class="form-control">
                            </div>

                            {{-- Customer Name --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Customer Name
                                </label>

                                <input type="text" name="customer_name" id="customer_name" class="form-control"
                                    value="{{ $booking->name }}" readonly>
                            </div>

                            {{-- Registration Address --}}
                            <div class="col-md-9 mb-3">
                                <label>Registration Address</label>

                                <textarea name="registration_address" id="registration_address" rows="3"
                                    class="form-control">{{ old('registration_address', $booking->address ?? '') }}</textarea>
                            </div>

                            {{-- Customer Tehsil --}}
                            <div class="col-md-3 mb-3">
                                <label>Customer Tehsil</label>

                                <input type="text" name="customer_tehsil" id="customer_tehsil" class="form-control"
                                    value="{{ old('customer_tehsil') }}">
                            </div>

                            {{-- Customer District --}}
                            <div class="col-md-3 mb-3">
                                <label>Customer District</label>

                                <input type="text" name="customer_district" id="customer_district" class="form-control"
                                    value="{{ old('customer_district') }}">
                            </div>

                            {{-- Pincode --}}
                            <div class="col-md-3 mb-3">
                                <label>Pincode</label>

                                <input type="text" name="pincode" id="pincode" class="form-control"
                                    value="{{ old('pincode', $booking->pincode) }}" maxlength="6" pattern="[0-9]{6}"
                                    inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').slice(0,6);">
                            </div>

                            {{-- Customer Contact Number --}}
                            <div class="col-md-3 mb-3">
                                <label>Customer Contact Number</label>
                                <input type="text" name="customer_mobile" id="customer_mobile" class="form-control"
                                    value="{{ $booking->mobile }}" readonly>
                            </div>

                            {{-- Customer Category --}}
                            <div class="col-md-3 mb-3">
                                <label>Customer Category</label>

                                <select name="b_cat" id="b_cat" class="form-control form-select">

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
                            </div>

                            {{-- Contact Person (If Any Other) --}}
                            <div class="col-md-3 mb-3">
                                <label>Contact Person (If Any Other)</label>

                                <input type="text" name="contact_person" id="contact_person" class="form-control"
                                    value="{{ old('contact_person', $booking->contact_person) }}">
                            </div>

                            {{-- Contact Person Phone No. (If Any Other) --}}
                            <div class="col-md-3 mb-3">
                                <label>Contact Person Phone No. (If Any Other)</label>

                                <input type="text" name="contact_person_mobile" id="contact_person_mobile"
                                    class="form-control"
                                    value="{{ old('contact_person_mobile', $booking->contact_person_mobile) }}"
                                    maxlength="10" inputmode="numeric"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);">
                            </div>

                            {{-- Email ID --}}
                            <div class="col-md-3 mb-3">
                                <label>Email ID</label>

                                <input type="email" name="email" id="email" class="form-control"
                                    value="{{ old('email', $booking->email) }}">
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                            </div>

                            {{-- D.O.B. --}}
                            <div class="col-md-3 mb-3">
                                <label>D.O.B.</label>

                                <input type="date" name="dob" id="dob" class="form-control"
                                    value="{{ old('dob', $booking->c_dob) }}">
                            </div>

                            {{-- Marital Status --}}
                            <div class="col-md-3 mb-3">
                                <label>Marital Status</label>

                                <select name="marital_status" id="marital_status" class="form-control form-select">

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
                            </div>

                            {{-- Date of Anniversary --}}
                            <div class="col-md-3 mb-3">
                                <label>Date of Anniversary</label>

                                <input type="date" name="anniversary_date" id="anniversary_date" class="form-control"
                                    value="{{ old('anniversary_date', $booking->anniversary_date) }}">
                            </div>

                            {{-- PAN No. --}}
                            <div class="col-md-3 mb-3">
                                <label>PAN No.</label>

                                <input type="text" name="pan_no" id="pan_no" class="form-control"
                                    value="{{ old('pan_no', $booking->pan_no) }}" maxlength="10"
                                    style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase();">
                            </div>

                            {{-- Aadhaar No. --}}
                            <div class="col-md-3 mb-3">
                                <label>Aadhaar No.</label>

                                <input type="text" name="adhar_no" id="adhar_no" class="form-control"
                                    value="{{ old('adhar_no', $booking->adhar_no) }}" maxlength="12" inputmode="numeric"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,12);">
                            </div>

                            {{-- Driving License No. --}}
                            <div class="col-md-3 mb-3">
                                <label>Driving License No.</label>

                                <input type="text" name="driving_license_no" id="driving_license_no"
                                    class="form-control" value="{{ old('driving_license_no') }}"
                                    style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase();">
                            </div>

                            {{-- Voter ID No. --}}
                            <div class="col-md-3 mb-3">
                                <label>Voter ID No.</label>

                                <input type="text" name="voter_id_no" id="voter_id_no" class="form-control"
                                    value="{{ old('voter_id_no') }}" style="text-transform:uppercase"
                                    oninput="this.value=this.value.toUpperCase();">
                            </div>

                            {{-- Nominee Name (For Insurance) --}}
                            <div class="col-md-3 mb-3">
                                <label>Nominee Name (For Insurance)</label>

                                <input type="text" name="nominee_name" id="nominee_name" class="form-control"
                                    value="{{ old('nominee_name') }}">
                            </div>

                            {{-- Relation with Nominee --}}
                            <div class="col-md-3 mb-3">
                                <label>Relation with Nominee</label>

                                <select name="nominee_relation" id="nominee_relation" class="form-control form-select">

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
                            </div>

                            {{-- Age of Nominee --}}
                            <div class="col-md-3 mb-3">
                                <label>Age of Nominee</label>

                                <input type="number" name="nominee_age" id="nominee_age" class="form-control" min="0"
                                    max="120" value="{{ old('nominee_age') }}">
                            </div>

                            {{-- Retail Category --}}
                            <div class="col-md-3 mb-3">

                                <label>Retail Category</label>

                                <select name="retail_category" id="retail_category" class="form-control form-select">

                                    <option value="">Select Retail Category</option>

                                    <option value="Normal" {{ old('retail_category')=='Normal' ? 'selected' : '' }}>
                                        Normal
                                    </option>

                                    <option value="ZACO" {{ old('retail_category')=='ZACO' ? 'selected' : '' }}>
                                        ZACO
                                    </option>

                                </select>

                            </div>

                            {{-- Segment --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Segment
                                </label>

                                <input type="text" id="segment_name" class="form-control" value="{{ $segment?->name }}"
                                    readonly>

                            </div>

                            {{-- Model --}}
                            <div class="col-md-3 mb-3">
                                <label>Model</label>
                                <input type="text" id="model_name" class="form-control" value="{{ $model?->name }}"
                                    readonly>
                            </div>

                            {{-- Variant --}}
                            <div class="col-md-3 mb-3">
                                <label>Variant</label>
                                <input type="text" id="variant_name" class="form-control"
                                    value="{{ $variant?->display_name ?? $variant?->custom_name ?? $variant?->oem_name }}"
                                    readonly>
                            </div>

                            {{-- Color --}}
                            <div class="col-md-3 mb-3">
                                <label>Color</label>
                                <input type="text" id="color_name" class="form-control" value="{{ $color?->name }}"
                                    readonly>
                            </div>

                            {{-- Body Type --}}
                            <div class="col-md-3 mb-3">
                                <label>Body Type</label>

                                <select name="body_type" id="body_type" class="form-control form-select">
                                    <option value="">Select Body Type</option>

                                    @foreach($body_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('body_type', $rto?->body_type) == $key ?
                                        'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Sale Type --}}
                            <div class="col-md-3 mb-3">
                                <label>Sale Type</label>

                                <select name="sale_type" id="sale_type" class="form-control form-select">
                                    <option value="">Select Sale Type</option>

                                    @foreach($sale_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('sale_type', $rto?->sale_type) == $key ?
                                        'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>DSA Retail</label>

                                <select name="dsa_retail" id="dsa_retail" class="form-control form-select">

                                    <option value="No" {{ empty($booking->dsa_id) ? 'selected' : '' }}>
                                        No
                                    </option>

                                    <option value="Yes" {{ !empty($booking->dsa_id) ? 'selected' : '' }}>
                                        Yes
                                    </option>

                                </select>

                            </div>


                            <div class="col-md-3 mb-3">

                                <label>DSA Name</label>

                                <select name="dsa_id" id="dsa_id" class="form-control form-select">

                                    <option value="">Select DSA</option>

                                    @foreach($dsaList as $dsa)

                                    <option value="{{ $dsa->id }}" data-location="{{ $dsa->dlocation }}" {{ $booking->
                                        dsa_id == $dsa->id ? 'selected' : '' }}>
                                        {{ $dsa->name }}
                                    </option>

                                    @endforeach

                                </select>

                            </div>


                            <div class="col-md-3 mb-3">

                                <label>DSA Location</label>

                                <input type="text" id="dsa_location" class="form-control" readonly disabled>
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Exchange</label>

                                <select name="exchange" id="exchange" class="form-control form-select">

                                    <option value="NA" {{ old('exchange', $booking->buyer_type == 'First time Buyer' ?
                                        'NA' :
                                        ($booking->buyer_type == 'Exchange Buy' ? 'In-House' : 'Third Party')
                                        ) == 'NA' ? 'selected' : '' }}>
                                        NA
                                    </option>

                                    <option value="In-House" {{ old('exchange', $booking->buyer_type == 'First time
                                        Buyer' ? 'NA' :
                                        ($booking->buyer_type == 'Exchange Buy' ? 'In-House' : 'Third Party')
                                        ) == 'In-House' ? 'selected' : '' }}>
                                        In-House
                                    </option>

                                    <option value="Third Party" {{ old('exchange', $booking->buyer_type == 'First time
                                        Buyer' ? 'NA' :
                                        ($booking->buyer_type == 'Exchange Buy' ? 'In-House' : 'Third Party')
                                        ) == 'Third Party' ? 'selected' : '' }}>
                                        Third Party
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>In House RTO</label>

                                <select name="in_house_rto" id="in_house_rto" class="form-control form-select" disabled>

                                    <option value="1" {{ $rto ? 'selected' : '' }}>Yes</option>

                                    <option value="0" {{ !$rto ? 'selected' : '' }}>No</option>

                                </select>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label>Accessories</label>

                                <select name="accessories[]" id="accessories" row="3" class="form-control form-select"
                                    multiple>

                                    @foreach($accessoryList as $accessory)

                                    <option value="{{ $accessory->part_no }}" data-price="{{ $accessory->ndp }}" {{
                                        in_array($accessory->part_no, $selectedAccessories) ? 'selected' : '' }}>

                                        {{ $accessory->item }}
                                        (₹{{ number_format($accessory->ndp,2) }})

                                    </option>

                                    @endforeach

                                </select>

                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Accessories Amount</label>

                                <input type="text" id="accessories_amount" name="accessories_amount"
                                    class="form-control bg-light" value="0.00" readonly>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>
                                    Chassis Number
                                </label>

                                <input type="text" id="chassis_no_display" class="form-control"
                                    value="{{ $booking->chassis_no }}">
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Chassis Image</label>

                                <input type="file" name="chassis_image" id="chassis_image" class="form-control">

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

                            </div>

                            {{-- Engine Number --}}
                            <div class="col-md-3 mb-3">
                                <label>Engine Number</label>

                                <input type="text" name="engine_no" id="engine_no" class="form-control"
                                    value="{{ old('engine_no', $insurance?->engine_no) }}" readonly>
                            </div>

                            {{-- OEM Model Code --}}
                            <div class="col-md-3 mb-3">
                                <label>OEM Model Code</label>

                                <input type="text" name="oem_model_code" id="oem_model_code" class="form-control"
                                    value="{{ old('oem_model_code', $variant?->oem_name) }}" readonly>
                            </div>

                            {{-- GST Slab --}}
                            <div class="col-md-3 mb-3">
                                <label>GST Slab</label>

                                <input type="text" name="gst_slab" id="gst_slab" class="form-control"
                                    value="{{ old('gst_slab', $variant?->gst_slab ?? '') }}" readonly>
                            </div>

                            {{-- Invoice No. --}}
                            <div class="col-md-3 mb-3">
                                <label>Invoice No.</label>

                                <input type="text" name="inv_no" id="inv_no" class="form-control"
                                    value="{{ old('inv_no', $booking->inv_no) }}" readonly>
                            </div>

                            {{-- Invoice Date --}}
                            <div class="col-md-3 mb-3">
                                <label>Invoice Date</label>

                                <input type="date" name="inv_date" id="inv_date" class="form-control"
                                    value="{{ old('inv_date', optional($booking->inv_date)->format('Y-m-d') ?? ($booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('Y-m-d') : '')) }}"
                                    readonly>
                            </div>

                            {{-- Ex-Showroom Price --}}
                            <div class="col-md-3 mb-3">
                                <label>Ex-Showroom Price</label>

                                <input type="number" name="ex_showroom_price" id="ex_showroom_price"
                                    class="form-control numeric-only"
                                    value="{{ old('ex_showroom_price', $selectedExShowroomPrice) }}">
                            </div>

                            {{-- Insurance --}}
                            <div class="col-md-3 mb-3">
                                <label>Insurance</label>

                                <select name="policy_type" id="policy_type" class="form-control form-select">
                                    <option value="">Select Insurance</option>

                                    @foreach($insurance_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('policy_type', $selectedPolicyType)==$key
                                        ? 'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Registration</label>

                                <select name="registration_type" id="registration_type"
                                    class="form-control form-select">

                                    <option value="">Select Registration</option>

                                    @foreach($registration_type_map as $key => $value)
                                    <option value="{{ $key }}" {{ old('registration_type',
                                        $selectedRegistrationType)==$key ? 'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                    @endforeach

                                </select>
                            </div>

                            {{-- Maxicare --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Maxicare
                                </label>

                                <input type="number" name="maxicare" id="maxicare" class="form-control"
                                    value="{{ old('maxicare', $quotationData['maxicare'] ?? '') }}">
                            </div>

                            {{-- VLTD Device (GPS) --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    VLTD Device (GPS)
                                </label>

                                <input type="number" name="vltd_device" id="vltd_device" class="form-control"
                                    value="{{ old('vltd_device', $quotationData['vltd_device'] ?? '') }}">
                            </div>

                            {{-- Coating --}}
                            <div class="col-md-3 mb-3">

                                <label>Coating</label>

                                <select id="coating" name="coating" class="form-control form-select">

                                    <option value="">Select Coating</option>

                                    <option value="Ceramic" {{ old('coating', $quotationData['coating'] ?? ''
                                        )=='Ceramic' ? 'selected' : '' }}>
                                        Ceramic
                                    </option>

                                    <option value="Graphene" {{ old('coating', $quotationData['coating'] ?? ''
                                        )=='Graphene' ? 'selected' : '' }}>
                                        Graphene
                                    </option>

                                    <option value="No Coating" {{ old('coating', $quotationData['coating'] ?? ''
                                        )=='No Coating' ? 'selected' : '' }}>
                                        No Coating
                                    </option>

                                </select>

                            </div>

                            {{-- Coating Price --}}
                            <div class="col-md-3 mb-3">

                                <label>Coating Price</label>

                                <input type="number" id="coating_price" name="coating_price" class="form-control"
                                    value="{{ old('coating_price', $quotationData['coating_price'] ?? '') }}">
                            </div>


                            {{-- PPF --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    PPF
                                </label>

                                <input type="number" name="ppf" id="ppf" class="form-control"
                                    value="{{ old('ppf', $quotationData['ppf'] ?? '') }}">
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    RTO Yellow Tape
                                </label>

                                <input type="number" name="rto_yellow_tape" id="rto_yellow_tape" class="form-control"
                                    value="{{ old('rto_yellow_tape', $quotationData['rto_yellow_tape'] ?? '') }}">
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Kazam Charging Kit(LMM)
                                </label>

                                <input type="number" name="kazam_charging_kit" id="kazam_charging_kit"
                                    class="form-control"
                                    value="{{ old('kazam_charging_kit', $quotationData['kazam_charging_kit'] ?? '') }}">
                            </div>
                            {{-- Incidental Charges (LMM) --}}
                            <div class="col-md-3 mb-3">

                                <label>Incidental Charges (LMM)</label>

                                <input type="number" name="incidental_charges" id="incidental_charges"
                                    class="form-control"
                                    value="{{ old('incidental_charges', $quotationData['incidental_charges'] ?? '') }}">
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Shield</label>

                                <select id="shield" name="shield" class="form-control form-select">

                                    <option value="">Select Shield</option>

                                    <option value="4th Year" {{ old('shield', $quotationData['shield'] ?? ''
                                        )=='4th Year' ? 'selected' : '' }}>
                                        4th Year
                                    </option>

                                    <option value="4th + 5th Year" {{ old('shield', $quotationData['shield'] ?? ''
                                        )=='4th + 5th Year' ? 'selected' : '' }}>
                                        4th + 5th Year
                                    </option>

                                    <option value="No Shield" {{ old('shield', $quotationData['shield'] ?? ''
                                        )=='No Shield' ? 'selected' : '' }}>
                                        No Shield
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Shield Price</label>

                                <input type="number" id="shield_price" name="shield_price" class="form-control"
                                    value="{{ old('shield_price', $quotationData['shield_price'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>RSA</label>

                                <select id="rsa" name="rsa" class="form-control form-select">

                                    <option value="">Select RSA</option>

                                    @foreach(['1 Year','2 Year','3 Year','4 Year','5 Year','No RSA'] as $rsa)

                                    <option value="{{ $rsa }}" {{ old('rsa', $quotationData['rsa'] ?? '' )==$rsa
                                        ? 'selected' : '' }}>
                                        {{ $rsa }}
                                    </option>

                                    @endforeach

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>RSA Amount</label>

                                <input type="number" id="rsa_amount" name="rsa_amount" class="form-control"
                                    value="{{ old('rsa_amount', $quotationData['rsa_amount'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Fastag</label>

                                <input type="number" name="fastag" id="fastag" class="form-control"
                                    value="{{ old('fastag', $quotationData['fastag'] ?? '') }}">
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    COD Charges
                                </label>

                                <input type="number" name="cod_charges" id="cod_charges" class="form-control"
                                    value="{{ old('cod_charges', $quotationData['cod_charges'] ?? '') }}">
                            </div>

                            {{-- Charger Swapping --}}
                            <div class="col-md-3 mb-3">

                                <label>Charger Swapping</label>

                                <select id="charger_swapping" name="charger_swapping" class="form-control form-select">

                                    <option value="">Select</option>

                                    <option value="Not Applicable" {{ old('charger_swapping',
                                        $quotationData['charger_swapping'] ?? '' )=='Not Applicable' ? 'selected' : ''
                                        }}>
                                        Not Applicable
                                    </option>

                                    <option value="NCH to 7.2 kW" {{ old('charger_swapping',
                                        $quotationData['charger_swapping'] ?? '' )=='NCH to 7.2 kW' ? 'selected' : ''
                                        }}>
                                        NCH to 7.2 kW
                                    </option>

                                    <option value="NCH to 11.2 kW" {{ old('charger_swapping',
                                        $quotationData['charger_swapping'] ?? '' )=='NCH to 11.2 kW' ? 'selected' : ''
                                        }}>
                                        NCH to 11.2 kW
                                    </option>

                                    <option value="7.2 kW to 11.2 kW" {{ old('charger_swapping',
                                        $quotationData['charger_swapping'] ?? '' )=='7.2 kW to 11.2 kW' ? 'selected'
                                        : '' }}>
                                        7.2 kW to 11.2 kW
                                    </option>

                                </select>

                            </div>

                            {{-- Charger Swapping Amount --}}
                            <div class="col-md-3 mb-3">

                                <label>Charger Swapping Amount</label>

                                <input id="charger_swapping_amount" name="charger_swapping_amount"
                                    class="form-control numeric-only"
                                    value="{{ old('charger_swapping_amount', $quotationData['charger_swapping_amount'] ?? '') }}"
                                    placeholder="Enter Amount" min="0" step="0.01" disabled>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>TCS @ 1%</label>

                                <input type="number" id="tcs" name="tcs" class="form-control"
                                    value="{{ old('tcs', $quotationData['tcs'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Total Receivable
                                </label>

                                <input type="number" readonly class="form-control bg-light">

                            </div>


                            <div class="col-md-3 mb-3">

                                <label>OEM Scheme / CSD Discount</label>

                                <input type="number" name="oem_scheme_discount" id="oem_scheme_discount"
                                    class="form-control"
                                    value="{{ old('oem_scheme_discount', $quotationData['oem_scheme_discount'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Fame Subsidy (LMM)</label>

                                <input type="number" name="fame_subsidy" id="fame_subsidy" class="form-control"
                                    value="{{ old('fame_subsidy', $quotationData['fame_subsidy'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Exchange Bonus / Green Bonus / Loyalty Bonus</label>

                                <input type="number" name="exchange_bonus" id="exchange_bonus" class="form-control"
                                    value="{{ old('exchange_bonus', $quotationData['exchange_bonus'] ?? '') }}">

                            </div>

                            {{-- Corporate Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Corporate Discount</label>

                                <input type="number" name="corporate_discount" id="corporate_discount"
                                    class="form-control"
                                    value="{{ old('corporate_discount', $quotationData['corporate_discount'] ?? '') }}">

                            </div>

                            {{-- Accessories Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Accessories Discount</label>

                                <input type="number" name="accessories_discount" id="accessories_discount"
                                    class="form-control"
                                    value="{{ old('accessories_discount', $quotationData['accessories_discount'] ?? '') }}">

                            </div>

                            {{-- Ceramic Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>Ceramic Discount</label>

                                <input type="number" name="ceramic_discount" id="ceramic_discount" class="form-control"
                                    value="{{ old('ceramic_discount', $quotationData['ceramic_discount'] ?? '') }}">

                            </div>

                            {{-- PPF Discount --}}
                            <div class="col-md-3 mb-3">

                                <label>PPF Discount</label>

                                <input type="number" name="ppf_discount" id="ppf_discount" class="form-control"
                                    value="{{ old('ppf_discount', $quotationData['ppf_discount'] ?? '') }}">

                            </div>

                            {{-- Other Discount - Dealer --}}
                            <div class="col-md-3 mb-3">

                                <label>Other Discount - Dealer</label>

                                <input type="number" name="dealer_discount" id="dealer_discount" class="form-control"
                                    value="{{ old('dealer_discount', $quotationData['dealer_discount'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Charger Swapping Discount</label>

                                <input id="charger_swapping_discount" name="charger_swapping_discount"
                                    class="form-control numeric-only"
                                    value="{{ old('charger_swapping_discount', $quotationData['charger_swapping_discount'] ?? '') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Total Discount
                                </label>

                                <input type="number" class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Net Receivable
                                </label>

                                <input type="number" id="net_receivable_summary" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Financier Name
                                </label>

                                <input type="text" id="financier_name_display" class="form-control"
                                    value="{{ $financierName }}" readonly>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Financier Branch
                                </label>

                                <input type="text" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Loan Amount
                                </label>

                                <input type="number" id="loan_amount" class="form-control"
                                    value="{{ $finance?->loan_amount }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Deduction</label>

                                <input type="number" id="deduction" name="deduction" class="form-control"
                                    value="{{ old('deduction') }}">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Margin Money Deposited By Financier
                                </label>

                                <input type="number" id="margin_money" class="form-control"
                                    value="{{ $finance?->margin }}">

                            </div>

                            <div class="col-md-3 mb-3">
                                <label>DO Amount</label>

                                <input type="number" id="do_amount" name="do_amount" class="form-control bg-light"
                                    value="{{ $finance?->loan_amount }}" readonly>
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Financier Subvention Amount</label>

                                <input type="number" id="financier_subvention" name="financier_subvention"
                                    class="form-control"
                                    value="{{ old('financier_subvention', $finance?->subvention_amount) }}">

                            </div>

                            <div class="row">



                                <div class="col-md-9">

                                    <div class="form-section mb-3">

                                        <div class="table-responsive text-2xl">

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

                                                    @forelse($receiptLogs as $receipt)

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
                                                        <td colspan="4" class="text-center">
                                                            No Receipt Found
                                                        </td>
                                                    </tr>

                                                    @endforelse

                                                </tbody>

                                            </table>



                                        </div>

                                    </div>

                                </div>
                                <div class="col-md-3 ms-auto">

                                    <label class="fw-bold">Total Receipt Amount</label>

                                    <input type="text" class="form-control fw-bold bg-light"
                                        value="₹ {{ number_format($receiptTotal, 2) }}" readonly>

                                </div>

                            </div>

                            {{-- Do settlement difference --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    DO Settlement Difference
                                </label>

                                <input type="number" class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Expected Balance
                                </label>

                                <input type="number" id="expected_balance" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>
                                    Final Balance
                                </label>

                                <input type="number" id="final_balance" readonly class="form-control bg-light">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Financier Verified</label>

                                <select name="financier_verified" id="financier_verified"
                                    class="form-control form-select">

                                    <option value="Please Select">Please Select</option>

                                    <option value="Yes">
                                        Yes
                                    </option>

                                    <option value="No">
                                        No
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label>Vehicle To Be Delivered On</label>

                                <select id="vehicle_delivery_on" name="vehicle_delivery_on"
                                    class="form-control form-select">

                                    <option value="">Select</option>
                                    <option value="DO">DO</option>
                                    <option value="Payment">Payment</option>
                                    <option value="Mail">Mail</option>
                                    <option value="Whatsapp">Whatsapp</option>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3" id="do_number_div">

                                <label>DO Number (Delivery Time)</label>

                                <input type="text" id="do_number" name="do_number" class="form-control" disabled>

                            </div>

                            <div class="col-md-3 mb-3">
                                <label>DO Number (TA Statement)</label>

                                <input type="text" id="do_number_ta" name="do_number_ta" class="form-control">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>DO Amount (TA Statement)</label>

                                <input type="number" id="do_amount_ta" name="do_amount_ta" class="form-control bg-light"
                                    readonly>
                            </div>



                            <div class="col-md-3 mb-3">

                                <label>DO Voucher Date</label>

                                <input type="date" id="do_voucher_date" name="do_voucher_date" class="form-control"
                                    readonly>

                            </div>

                            {{-- Brokerage Amount --}}
                            <div class="col-md-3 mb-3">
                                <label>Brokerage Amount</label>

                                <input type="number" id="brokerage_amount" name="brokerage_amount" class="form-control"
                                    min="0" step="0.01">
                            </div>

                            {{-- Other Discount Receivable --}}
                            <div class="col-md-3 mb-3">
                                <label>Other Discount Receivable</label>

                                <input type="number" id="other_discount_receivable" name="other_discount_receivable"
                                    class="form-control" min="0" step="0.01">
                            </div>

                            {{-- Other Discount Receivable - M&M Support --}}
                            <div class="col-md-3 mb-3">
                                <label>Other Discount Receivable - M&amp;M Support</label>

                                <input type="number" id="mm_support_receivable" name="mm_support_receivable"
                                    class="form-control" min="0" step="0.01">
                            </div>

                            {{-- Other Discount Receivable - Liquidation Scheme --}}
                            <div class="col-md-3 mb-3">
                                <label>Other Discount Receivable - Liquidation Scheme</label>

                                <input type="number" id="liquidation_scheme_receivable"
                                    name="liquidation_scheme_receivable" class="form-control" min="0" step="0.01">
                            </div>

                            {{-- Registration Service Charge - Receivable --}}
                            <div class="col-md-3 mb-3">
                                <label>Registration Service Charge - Receivable</label>

                                <input type="number" id="registration_service_charge_receivable"
                                    name="registration_service_charge_receivable" class="form-control" min="0"
                                    step="0.01">
                            </div>

                            {{-- Registration Service Charge - Received --}}
                            <div class="col-md-3 mb-3">
                                <label>Registration Service Charge - Received</label>

                                <input type="number" id="registration_service_charge_received"
                                    name="registration_service_charge_received" class="form-control" min="0"
                                    step="0.01">
                            </div>

                        </div>

                    </div>

                </div>

            </div>

    </div>

</div>

<div class="text-center mt-5 mb-5">

    <button type="button" id="previewBtn" class="btn btn-primary btn-lg px-5 py-2">

        <i class="la la-eye"></i>
        Form Preview

    </button>
    <button type="submit" class="btn btn-success btn-lg px-5 py-2">
        <i class="la la-save"></i>
        Save OTF
    </button>

</div>

</form>

</div>
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


<div id="imageEditorPopup">

    <div class="editor-container">

        <div class="editor-header">

            <h5>Edit Chassis Image</h5>

            <button type="button" id="closeEditor">✕</button>

        </div>

        <div class="editor-body">

            <img id="cropperImage">

        </div>

        <div class="editor-footer">

            <button class="btn btn-secondary" id="zoomIn">+</button>

            <button class="btn btn-secondary" id="zoomOut">-</button>

            <button class="btn btn-secondary" id="rotateLeft">↺</button>

            <button class="btn btn-secondary" id="rotateRight">↻</button>

            <button class="btn btn-secondary" id="flipX">Flip X</button>

            <button class="btn btn-secondary" id="flipY">Flip Y</button>

            <button class="btn btn-danger" id="resetCrop">Reset</button>

            <button class="btn btn-success" id="saveCrop">Save</button>

        </div>

    </div>

</div>


@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.5/js/lightbox.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).on('input', '.numeric-only', function () {

    let value = $(this).val();

    // Allow only digits and one decimal
    value = value.replace(/[^\d.]/g, '');
    value = value.replace(/(\..*)\./g, '$1');

    $(this).val(value);

});
    lightbox.option({
        resizeDuration: 200,
        wrapAround: true,
        fadeDuration: 200,
        imageFadeDuration: 200
    });

    $('#accessories').select2({
    placeholder: 'Select Accessories',
    width: '100%',
    closeOnSelect: false
});

function updateAccessoriesAmount() {

    let total = 0;

    $('#accessories option:selected').each(function () {

        total += parseFloat($(this).data('price')) || 0;

    });

    $('#accessories_amount').val(total.toFixed(2));

    if (typeof calculateQuotation === 'function') {
        calculateQuotation();
    }
}

$('#accessories').on('change select2:select select2:unselect', function () {
    updateAccessoriesAmount();
});

$(document).ready(function () {

    updateAccessoriesAmount();

});
    function num(id) {
        return parseFloat(
            $('#' + id).val()
        ) || 0;
    }

    function receiptTotal() {
        let total = 0;

        $('.receipt-amount').each(function() {

            total += parseFloat(
                $(this).val()
            ) || 0;

        });

        return total;
    }

    function calculateFinance() {
        const loanAmount =
            num('loan_amount');

        const deduction =
            num('deduction');

        const marginMoney =
            num('margin_money');

        const doAmount =
            loanAmount -
            deduction +
            marginMoney;

        $('#do_amount').val(
            doAmount.toFixed(2)
        );

        const netReceivable =
            num('net_receivable');

        const receiptAmount =
            receiptTotal();

        const expectedBalance =
            netReceivable -
            doAmount -
            receiptAmount;

        $('#expected_balance').val(
            expectedBalance.toFixed(2)
        );

        const doAmountTa =
            num('do_amount_ta');

        const financierSubvention =
            num('financier_subvention');

        const finalBalance =
            expectedBalance +
            doAmount -
            doAmountTa -
            financierSubvention;

        $('#final_balance').val(
            finalBalance.toFixed(2)
        );

        $('#net_receivable_summary').val(
            netReceivable.toFixed(2)
        );

        $('#final_balance_summary').val(
            finalBalance.toFixed(2)
        );
    }

    $('#addReceiptRow').on(
        'click',
        function() {

            $('#receiptTableBody').append(`

                                            <tr>

                                                <td>
                                                    <input
                                                        type="text"
                                                        name="receipt_number[]"
                                                        class="form-control">
                                                </td>

                                                <td>
                                                    <input
                                                        type="date"
                                                        name="receipt_date[]"
                                                        class="form-control">
                                                </td>

                                                <td>
                                                    <input
                                                        type="number"
                                                        name="receipt_amount[]"
                                                        class="form-control receipt-amount">
                                                </td>

                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-danger remove-row">
                                                        Remove
                                                    </button>
                                                </td>

                                            </tr>

                                        `);

            calculateFinance();

            
        }
    );

    $(document).on(
        'click',
        '.remove-row',
        function() {

            $(this)
                .closest('tr')
                .remove();

            calculateFinance();
        }
    );

    $(document).on(
        'input change',
        '#loan_amount, #deduction, #margin_money, #net_receivable, #do_amount_ta, #financier_subvention, .receipt-amount',
        function() {

            calculateFinance();

        }
    );

    calculateFinance();

    let cropper;
    let flipX = 1;
    let flipY = 1;

    $('#chassis_image').on('change', function () {

        const file = this.files[0];

        if (!file) return;

        const reader = new FileReader();

        reader.onload = function(e){

            $('#chassis_preview')
                .attr('src',e.target.result)
                .show();

            $('#editImageBtn').show();

        }

        reader.readAsDataURL(file);

    });

    $('#editImageBtn').click(function(){

    if (cropper) {
        cropper.destroy();
        cropper = null;
    }

    $('#cropperImage').attr(
        'src',
        $('#chassis_preview').attr('src')
    );

    $('#imageEditorPopup').css({
        display: 'flex'
    }).hide().fadeIn(200);

    cropper = new Cropper(document.getElementById('cropperImage'), {
        viewMode: 1,
        autoCropArea: 1,
        movable: true,
        zoomable: true,
        scalable: true,
        rotatable: true
    });

});

    $('#closeEditor').click(function(){

    if(cropper){

        cropper.destroy();

        cropper=null;

    }

    $('#imageEditorPopup').fadeOut(200);

});

    

    $('#zoomIn').click(()=>{
    cropper.zoom(0.1);
    });

    $('#zoomOut').click(()=>{
        cropper.zoom(-0.1);
    });

    $('#rotateLeft').click(()=>{
        cropper.rotate(-90);
    });

    $('#rotateRight').click(()=>{
        cropper.rotate(90);
    });

    $('#flipX').click(()=>{
        flipX = -flipX;
        cropper.scaleX(flipX);
    });

    $('#flipY').click(()=>{
        flipY = -flipY;
        cropper.scaleY(flipY);
    });

    $('#resetCrop').click(()=>{
        cropper.reset();
    });

    $('#saveCrop').click(function(){

    const canvas = cropper.getCroppedCanvas();

    canvas.toBlob(function(blob){

        const file = new File(
            [blob],
            "chassis.jpg",
            {
                type:"image/jpeg"
            }
        );

        const dt = new DataTransfer();

        dt.items.add(file);

        $('#chassis_image')[0].files = dt.files;

        $('#chassis_preview').attr(
            'src',
            URL.createObjectURL(blob)
        );

        cropper.destroy();
        cropper = null;

        $('#imageEditorPopup').fadeOut(200);

    },'image/jpeg');

});





    
    
    $('#charger_swapping').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'Not Applicable') {

        $('#charger_swapping_amount')
            .val('')
            .prop('disabled', true);

        $('#charger_swapping_discount')
            .val('')
            .prop('disabled', true);

    } else {

        $('#charger_swapping_amount').prop('disabled', false);

        $('#charger_swapping_discount').prop('disabled', false);
    }

}).trigger('change');

    $('#coating').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'No Coating') {

        $('#coating_price')
            .val('')
            .prop('disabled', true);

    }
});

    $('#shield').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'No Shield') {

        $('#shield_price')
            .val('')
            .prop('disabled', true);

    }
});


    function loadSalesConsultant() {

    if (typeof salesUsers === 'undefined') {
        return;
    }

    const personCode = $('#saleconsultant').val();

    const consultant = salesUsers.find(x => x.person_code == personCode);

    if (!consultant) {

        $('#sc_mile_id').val('');
        $('#sc_branch').val('');
        $('#sc_location').val('');
        return;

    }

    $('#sc_mile_id').val(consultant.mile_id ?? '');
    $('#sc_branch').val(consultant.primary_branch_code ?? '');
    $('#sc_location').val(consultant.primary_loc_code ?? '');

}

$('#saleconsultant').on('change', loadSalesConsultant);

// Page Load
loadSalesConsultant();
function toggleAnniversary() {

    if ($('#marital_status').val() === 'Married') {

        $('#anniversary_date')
            .prop('disabled', false);

    } else {

        $('#anniversary_date')
            .val('')
            .prop('disabled', true);

    }
}

    $(document).ready(function () {

        toggleAnniversary();

        $('#marital_status').on('change', function () {
            toggleAnniversary();
        });

    });

    

    

function toggleDSAFields() {

    let yes = $('#dsa_retail').val() === 'Yes';

    $('#dsa_id').prop('disabled', !yes);

    $('#dsa_location').prop('disabled', !yes);

    if (!yes) {

        $('#dsa_id').val('');

        $('#dsa_location').val('');

    } else {

        loadDSALocation();

    }
}

function loadDSALocation() {

    let location = $('#dsa_id option:selected').data('location') || '';

    $('#dsa_location').val(location);

}

$('#dsa_retail').on('change', toggleDSAFields);

$('#dsa_id').on('change', loadDSALocation);

$(function () {

    toggleDSAFields();

});

$(document).ready(function () {

    toggleDSAFields();

    loadDSALocation();

});

    toggleDSAFields();

    $('#dsa_id').trigger('change');

    $('#rsa').on('change', function () {

    if ($(this).val() === '' || $(this).val() === 'No RSA') {

        $('#rsa_amount')
            .val('')
            .prop('disabled', true);

    }
});

    $('#email').on('blur', function () {

    let email = $(this).val().trim();

    if (email === '') {
        $(this).removeClass('is-invalid');
        return;
    }

    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailRegex.test(email)) {

        $(this).addClass('is-invalid');

    } else {

        $(this).removeClass('is-invalid');

    }

});
$(document).ready(function () {

    function toggleDONumber() {

        var delivery = $('#vehicle_delivery_on').val();

        if (delivery === 'DO') {

            $('#do_number').prop('disabled', false);

        } else {

            $('#do_number')
                .val('')
                .prop('disabled', true);
        }
    }

    // Initial page load
    toggleDONumber();

    // On dropdown change
    $('#vehicle_delivery_on').on('change', function () {
        toggleDONumber();
    });

});

$(window).on('load', function () {

    function toggleDONumber() {

        let isDO = $('#vehicle_delivery_on').val() === 'DO';

        $('#do_number').prop('disabled', !isDO);

        if (!isDO) {
            $('#do_number').val('');
        }
    }

    toggleDONumber();

    $('#vehicle_delivery_on').on('change', toggleDONumber);

});

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
        data: {
            do_no: doNo
        },
        success: function (res) {

            $('#do_amount_ta').val(res.amount);
            $('#do_voucher_date').val(res.date);

        }
    });

});




/* =========================================================
   OTF SINGLE-PAGE PRINT PREVIEW
   ========================================================= */

// Server-side lookups not present as simple DOM fields
const otfServerData = {
    bookingId: {{ $booking->id }},
    dsaLocation: @json($dsa?->dlocation ?? ''),
    accessoriesSummary: @json($accessories),
};

function otfVal(id) {
    const el = document.getElementById(id);
    if (!el) return '';
    return (el.value || '').toString().trim();
}

function otfSelectText(id) {
    const el = document.getElementById(id);
    if (!el) return '';
    if (el.tagName === 'SELECT') {
        const opt = el.options[el.selectedIndex];
        return opt ? opt.text.trim() : '';
    }
    return otfVal(id);
}

function otfEsc(str) {
    return $('<div>').text(str == null ? '' : str).html();
}

function otfMoney(n) {
    n = parseFloat(n);
    if (isNaN(n)) return '';
    return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function otfFormatDate(d) {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return parts[2] + '/' + parts[1] + '/' + parts[0];
    return d;
}

// Build a section box with key-value rows; only non-empty rows are shown.
// fields: array of [label, rawValue, isDate]
function otfSection(title, fields) {
    let rows = '';
    fields.forEach(function (f) {
        let label = f[0], value = f[1], isDate = f[2] || false;
        if (isDate) value = otfFormatDate(value);
        if (value === '' || value === null || value === undefined) return;
        rows += '<div class="otf-kv"><div class="otf-k">' + otfEsc(label) +
            '</div><div class="otf-v">' + otfEsc(value) + '</div></div>';
    });
    if (!rows) rows = '<div class="otf-empty">No data entered</div>';
    return '<div class="otf-section"><h3>' + otfEsc(title) + '</h3>' + rows + '</div>';
}

function otfBuildChips() {
    // Only show a chip when the field has a meaningful, non-default value
    const candidates = [
        ['dsa_retail', 'DSA Retail'],
        ['exchange', 'Exchange'],
        ['in_house_rto', 'In House RTO'],
        ['policy_type', ''],
        ['registration_type', ''],
        ['shield', ''],
        ['rsa', ''],
        ['coating', ''],
        ['charger_swapping', ''],
        ['financier_verified', 'Financier Verified'],
        ['retail_category', ''],
        ['sale_type', ''],
    ];
    const skipValues = ['', 'No', 'NO', 'Not Applicable', 'No Shield', 'No RSA', 'No Coating'];
    let chips = '';
    candidates.forEach(function (c) {
        const id = c[0], prefix = c[1];
        const text = otfSelectText(id);
        if (!text || skipValues.includes(text)) return;
        const label = prefix ? (prefix + ': ' + text) : text;
        chips += '<span class="otf-chip">' + otfEsc(label) + '</span>';
    });
    if (!chips) return '<div class="otf-empty">No selections</div>';
    return '<div class="otf-chips">' + chips + '</div>';
}

function otfBuildAccessoriesTable() {
    const sel = document.getElementById('accessories');
    let rows = '', total = 0, i = 0;
    if (sel) {
        Array.from(sel.selectedOptions).forEach(function (opt) {
            i++;
            const price = parseFloat($(opt).data('price')) || 0;
            total += price;
            const name = opt.text.replace(/\s*\(₹[\d,.]+\)\s*$/, '').trim();
            rows += '<tr><td>' + i + '</td><td>' + otfEsc(name) +
                '</td><td class="otf-amt">' + price.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + '</td></tr>';
        });
    }
    if (!rows) return '';
    return '<div class="otf-section"><h3>Accessories</h3><table class="otf-table">' +
        '<thead><tr><th>#</th><th>Item</th><th class="otf-amt">Amount</th></tr></thead>' +
        '<tbody>' + rows +
        '<tr><td></td><td><strong>Total</strong></td><td class="otf-amt"><strong>' +
        total.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + '</strong></td></tr>' +
        '</tbody></table></div>';
}

function otfBuildReceiptsTable() {
    let rows = '', total = 0, count = 0;
    $('.receipt-table tbody tr').each(function () {
        const inputs = $(this).find('input');
        if (inputs.length < 3) return;
        const no = (inputs.eq(0).val() || '').trim();
        const date = inputs.eq(1).val() || '';
        const amt = parseFloat(inputs.eq(2).val()) || 0;
        if (!no && !date && !amt) return;
        count++;
        total += amt;
        rows += '<tr><td>' + otfEsc(no) + '</td><td>' + otfEsc(otfFormatDate(date)) +
            '</td><td class="otf-amt">' + amt.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + '</td></tr>';
    });
    if (!count) return '';
    return '<div class="otf-section"><h3>Receipts</h3><table class="otf-table">' +
        '<thead><tr><th>Receipt</th><th>Date</th><th class="otf-amt">Amount</th></tr></thead>' +
        '<tbody>' + rows +
        '<tr><td colspan="2"><strong>Total Received</strong></td><td class="otf-amt"><strong>' +
        total.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + '</strong></td></tr>' +
        '</tbody></table></div>';
}

function otfBuildReceivable() {
    // [id, label] - numeric receivable / discount fields.
    // Amounts are shown as positive receivables, discount fields as negative.
    const receivables = [
        ['ex_showroom_price', 'Ex-Showroom Price'],
        ['maxicare', 'Maxicare'],
        ['vltd_device', 'VLTD Device'],
        ['coating_price', 'Coating'],
        ['ppf', 'PPF'],
        ['rto_yellow_tape', 'RTO Yellow Tape'],
        ['kazam_charging_kit', 'Kazam Charging Kit'],
        ['incidental_charges', 'Incidental Charges'],
        ['shield_price', 'Shield'],
        ['rsa_amount', 'RSA'],
        ['fastag', 'Fastag'],
        ['cod_charges', 'COD Charges'],
        ['charger_swapping_amount', 'Charger Swapping'],
        ['tcs', 'TCS @ 1%'],
        ['accessories_amount', 'Accessories'],
        ['brokerage_amount', 'Brokerage Amount'],
        ['other_discount_receivable', 'Other Discount Receivable'],
        ['mm_support_receivable', 'M&M Support Receivable'],
        ['liquidation_scheme_receivable', 'Liquidation Scheme Receivable'],
        ['registration_service_charge_receivable', 'Reg. Service Charge Receivable'],
    ];
    const discounts = [
        ['oem_scheme_discount', 'OEM Scheme / CSD Discount'],
        ['fame_subsidy', 'Fame Subsidy'],
        ['exchange_bonus', 'Exchange / Green / Loyalty Bonus'],
        ['corporate_discount', 'Corporate Discount'],
        ['accessories_discount', 'Accessories Discount'],
        ['ceramic_discount', 'Ceramic Discount'],
        ['ppf_discount', 'PPF Discount'],
        ['dealer_discount', 'Other Discount - Dealer'],
        ['charger_swapping_discount', 'Charger Swapping Discount'],
    ];

    let rows = '', total = 0;
    receivables.forEach(function (f) {
        const v = parseFloat(otfVal(f[0]));
        if (!v) return;
        total += v;
        rows += '<div class="otf-trow"><span>' + otfEsc(f[1]) + '</span><strong>' + otfMoney(v) + '</strong></div>';
    });
    discounts.forEach(function (f) {
        const v = parseFloat(otfVal(f[0]));
        if (!v) return;
        total -= v;
        rows += '<div class="otf-trow"><span>' + otfEsc(f[1]) + '</span><strong>-' + otfMoney(v) + '</strong></div>';
    });

    const summary = parseFloat(otfVal('net_receivable_summary'));
    const grand = !isNaN(summary) && summary !== 0 ? summary : total;

    if (!rows) rows = '<div class="otf-empty">No receivable items entered</div>';

    return '<div class="otf-section"><h3>Receivable</h3><div class="otf-totals">' + rows +
        '<div class="otf-trow otf-grand"><span>Net Receivable</span><span>' + otfMoney(grand) + '</span></div>' +
        '</div></div>';
}

function otfBuildFinance() {
    const rows = [];
    const fin = [
        ['loan_amount', 'Loan Amount'],
        ['deduction', 'Deduction'],
        ['margin_money', 'Margin Money'],
        ['do_amount', 'DO Amount'],
        ['do_amount_ta', 'DO Amount (TA)'],
        ['do_number', 'DO Number'],
        ['do_number_ta', 'DO Number (TA)'],
        ['do_voucher_date', 'DO Voucher Date', true],
        ['financier_subvention', 'Financier Subvention'],
        ['expected_balance', 'Expected Balance'],
        ['final_balance', 'Final Balance'],
    ];
    fin.forEach(function (f) {
        rows.push(f);
    });
    return rows;
}

function otfBuildPreview() {

    const consultant = otfSelectText('saleconsultant');
    const dsaText = otfSelectText('dsa_id');
    const registrationTypeLabel = otfSelectText('registration_no_type');
    const permitLabel = otfSelectText('permit');

    const modelName = otfVal('model_name');
    const custName = otfVal('customer_name');
    const votf = otfVal('votf_no');

    let html = '';

    // Top: brand + meta
    html += '<div class="otf-top">';
    html += '<div class="otf-box otf-brand">';
    html += '<h1>Bikaner Motors Pvt. Ltd. - Vehicle Order Taking Form</h1>';
    html += '<p>Customer: ' + otfEsc(custName || '-') + ' | Model: ' + otfEsc(modelName || '-') +
        ' | Date: ' + otfEsc(otfFormatDate(otfVal('inv_date')) || new Date().toLocaleDateString('en-GB')) + '</p>';
    html += '</div>';
    html += '<div class="otf-box otf-meta">';
    html += '<div class="otf-tag">Ref</div>';
    if (votf) html += '<div class="otf-row">VOTF: <strong>' + otfEsc(votf) + '</strong></div>';
    if (otfVal('dms_no')) html += '<div class="otf-row">DMS Enq: <strong>' + otfEsc(otfVal('dms_no')) + '</strong></div>';
    if (otfVal('dms_otf')) html += '<div class="otf-row">DMS OTF: <strong>' + otfEsc(otfVal('dms_otf')) + '</strong></div>';
    if (registrationTypeLabel) html += '<div class="otf-row">Regn: <strong>' + otfEsc(registrationTypeLabel) + '</strong></div>';
    if (permitLabel) html += '<div class="otf-row">Permit: <strong>' + otfEsc(permitLabel) + '</strong></div>';
    html += '</div>';
    html += '</div>';

    // Customer / Address-Nominee / Selected chips
    html += '<div class="otf-grid3">';
    html += otfSection('Customer', [
        ['Name', otfVal('customer_name')],
        ['Mobile', otfVal('customer_mobile')],
        ['D.O.B.', otfVal('dob'), true],
        ['PAN', otfVal('pan_no')],
        ['Aadhaar', otfVal('adhar_no')],
        ['DL No.', otfVal('driving_license_no')],
        ['Voter ID', otfVal('voter_id_no')],
        ['Email', otfVal('email')],
        ['Marital Status', otfSelectText('marital_status')],
        ['Contact Person', otfVal('contact_person')],
    ]);
    html += otfSection('Address / Nominee', [
        ['Address', otfVal('registration_address')],
        ['Tehsil', otfVal('customer_tehsil')],
        ['District', otfVal('customer_district')],
        ['PIN', otfVal('pincode')],
        ['GST No.', otfVal('gstn')],
        ['Category', otfSelectText('b_cat')],
        ['Nominee', otfVal('nominee_name')],
        ['Relation', otfSelectText('nominee_relation') + (otfVal('nominee_age') ? (', ' + otfVal('nominee_age')) : '')],
    ]);
    html += '<div class="otf-section"><h3>Selected</h3>' + otfBuildChips() + '</div>';
    html += '</div>';

    // Vehicle/Finance + Receivable
    html += '<div class="otf-grid2">';
    html += otfSection('Vehicle / Finance', [
        ['Model', otfVal('model_name')],
        ['Variant', otfVal('variant_name')],
        ['Colour', otfVal('color_name')],
        ['Consultant', consultant],
        ['DSA', dsaText && dsaText !== 'Select DSA' ? dsaText : ''],
        ['Chassis', otfVal('chassis_no_display')],
        ['Engine No.', otfVal('engine_no')],
        ['Ex. SR Price', otfVal('ex_showroom_price') ? otfMoney(otfVal('ex_showroom_price')) : ''],
        ['Hypo By', otfVal('financier_name_display')],
        ['Invoice No.', otfVal('inv_no')],
        ['Delivery On', otfSelectText('vehicle_delivery_on')],
    ]);
    html += otfBuildReceivable();
    html += '</div>';

    // Accessories + Receipts
    const accTable = otfBuildAccessoriesTable();
    const recTable = otfBuildReceiptsTable();
    if (accTable || recTable) {
        html += '<div class="otf-grid2">';
        html += accTable || '<div class="otf-section"><h3>Accessories</h3><div class="otf-empty">None selected</div></div>';
        html += recTable || '<div class="otf-section"><h3>Receipts</h3><div class="otf-empty">No receipts recorded</div></div>';
        html += '</div>';
    }

    // Notes + signatures
    html += '<div class="otf-section">';
    html += '<ol class="otf-note">';
    html += '<li>Vehicle shall be delivered only against payment.</li>';
    html += '<li>Interest shall be charged @ 24% P.A. in case of payments delayed over three days.</li>';
    html += '<li>No interest shall be payable on Booking Amount.</li>';
    html += '<li>Price &amp; Scheme of the vehicle is applicable as on the date of delivery. Price &amp; Scheme are subject to change without any prior notice.</li>';
    html += '<li>Self attested coloured copy of original documents is required for any Claim. Claims will be rejected in absence of required documents.</li>';
    html += '</ol>';
    html += '<div class="otf-signs">';
    html += '<div class="otf-sign">Customer Signature</div>';
    html += '<div class="otf-sign">Sales Consultant</div>';
    html += '<div class="otf-sign">Accounts / Manager</div>';
    html += '</div>';
    html += '</div>';

    $('#previewContent').html(html);
}

$('#previewBtn').on('click', function () {
    otfBuildPreview();
    $('#otfFormSection').hide();
    $('.page-header').hide();
    $('#otfPreviewPage').css('display', 'block');
    window.scrollTo(0, 0);
});

$('#backToForm, #backToForm2').on('click', function () {
    $('#otfPreviewPage').hide();
    $('#otfFormSection').show();
    $('.page-header').show();
});

$('#printFormBtn, #printFormBtn2').on('click', function () {
    window.print();
});

</script>
@endpush