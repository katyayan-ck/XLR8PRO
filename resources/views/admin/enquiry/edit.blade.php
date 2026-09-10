@extends(backpack_view('blank'))

@section('title', isset($enquiry) ? 'Edit Enquiry' : 'Add New Enquiry')

@push('after_styles')
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .25);
        }

        .required-mark {
            color: red;
        }
    </style>
@endpush

@section('content')

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card">

                    <div class="card-header text-black">
                        <h2 class="mb-0">
                            {{ isset($enquiry) ? 'Edit Hot Enquiry' : 'Add Hot Enquiry' }}
                        </h2>
                    </div>

                    <div class="card-body">

                        <form method="POST"
                            action="{{ isset($enquiry) ? backpack_url('enquiry/' . $enquiry->id) : backpack_url('enquiry') }}"
                            enctype="multipart/form-data">

                            @csrf

                            @if (isset($enquiry))
                                @method('PUT')
                            @endif

                            <div class="row"></div>

                            <div class="card mb-4">

                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Customer Information</h5>
                                </div>

                                <div class="card-body">

                                    <div class="row"></div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Enquiry No.
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="enquiry_no" class="form-control"
                                        value="{{ old('enquiry_no', $enquiry->enquiry_no ?? '') }}" required>

                                </div>

                                {{-- Customer First Name --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Customer First Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="first_name" class="form-control"
                                        value="{{ old('first_name', $enquiry->first_name ?? '') }}" required>

                                </div>

                                {{-- Customer Last Name --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Customer Last Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="last_name" class="form-control"
                                        value="{{ old('last_name', $enquiry->last_name ?? '') }}" required>

                                </div>

                                {{-- Phone Number --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Phone Number
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="mobile" maxlength="10" class="form-control"
                                        value="{{ old('mobile', $enquiry->mobile ?? '') }}" required>

                                </div>

                                {{-- Email --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Email ID
                                        <small class="text-muted">(Optional)</small>
                                    </label>

                                    <input type="email" name="email" class="form-control"
                                        value="{{ old('email', $enquiry->email ?? '') }}">

                                </div>

                            </div>

                    </div>
                </div>

                {{-- =========================== SHORT ENQUIRY Enquiry Information =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">

                        <h5 class="mb-0">

                            Enquiry Information

                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            {{-- Enquiry Type --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Enquiry Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="enquiry_type" id="enquiry_type" class="form-control form-select" required>

                                    <option value="">Select Enquiry Type</option>

                                    @foreach ($enquiry_types as $etype)
                                        <option value="{{ $etype['code'] }}">
                                            {{ $etype['value'] }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            {{-- Enquiry Source --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Enquiry Source
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="source_code" id="source_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Enquiry Source
                                    </option>

                                </select>

                            </div>

                            {{-- Enquiry Sub Source --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Enquiry Sub Source
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="sub_source" id="sub_source" class="form-control form-select" disabled>

                                    <option value="">
                                        Select Enquiry Sub Source
                                    </option>

                                </select>

                            </div>

                            <div class="row d-none" id="referenceSection">

                                {{-- Referred By --}}
                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Referred By
                                    </label>

                                    <select name="referred_by" id="referred_by" class="form-control form-select">

                                        <option value="">Select Referred By</option>

                                        <option value="Customer">Customer</option>

                                        <option value="Team Member">Team Member</option>

                                        <option value="Promoter">Promoter</option>

                                    </select>

                                </div>

                                {{-- Referee Phone Number --}}
                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Referee Phone Number
                                    </label>

                                    <input type="text" name="referee_phone" maxlength="10" class="form-control"
                                        value="{{ old('referee_phone') }}">

                                </div>

                                {{-- Referee Name --}}
                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Referee Name
                                    </label>

                                    <input type="text" name="referee_name" class="form-control"
                                        value="{{ old('referee_name') }}">

                                </div>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Planned Campaign
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="planned_campaign" id="planned_campaign" class="form-control form-select"
                                    required>

                                    <option value="">Select Planned Campaign</option>

                                    @foreach ($campaigns as $name)
                                        <option value="{{ $name }}">
                                            {{ $name }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            {{-- Further Details --}}
                            <div class="col-md-5 mb-3">

                                <label class="form-label">

                                    Further Details in case of Reference

                                    <span class="text-danger">*</span>

                                </label>

                                <input type="text" name="reference_details" class="form-control"
                                    value="{{ old('reference_details') }}" required>

                            </div>

                            {{-- Likely Purchase Date --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">

                                    Likely Purchase Date

                                    <span class="text-danger">*</span>

                                </label>

                                <select name="likely_purchase_date" class="form-control form-select">

                                    <option value="">Select Likely Purchase Date</option>

                                    @foreach ($likely_purchase_dates as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                        </div>

                    </div>

                </div>
                {{-- ===========================  SHORT ENQUIRY Vehicle Details =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">
                        <h5 class="mb-0">Vehicle Details</h5>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            {{-- Segment --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">

                                    Segment

                                    <span class="text-danger">*</span>

                                </label>

                                <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Segment
                                    </option>

                                    @foreach ($segments as $code => $name)
                                        <option value="{{ $code }}"
                                            {{ old('segment_code') == $code ? 'selected' : '' }}>

                                            {{ $name }}

                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            {{-- Model --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Model
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="model_code" id="model_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Model
                                    </option>

                                </select>
                            </div>

                            {{-- Variant --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Variant
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="variant_code" id="variant_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Variant
                                    </option>

                                </select>

                            </div>

                            {{-- Color --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Color
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="color_code" id="color_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Color
                                    </option>

                                </select>

                            </div>

                            {{-- Fuel Type --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Fuel Type
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" id="fuel_type" class="form-control" readonly>

                                <input type="hidden" id="fuel_type_id" name="fuel_type">

                            </div>

                            {{-- Transmission --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Transmission
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" id="transmission" name="transmission" class="form-control"
                                    readonly>

                            </div>

                            {{-- Drivetrain --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Drivetrain
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" id="drivetrain" name="drivetrain" class="form-control" readonly>

                            </div>

                            {{-- Seating --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Seating
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" id="seating" name="seating" class="form-control" readonly>

                            </div>

                            {{-- Commercial/LMM Only --}}
                            <div class="row" id="commercialSection">

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">
                                        Usage Area
                                    </label>

                                    <select name="usage_area" class="form-control form-select">

                                        <option value="">Select Usage Area</option>

                                        @foreach ($usage_areas as $item)
                                            <option value="{{ $item['code'] }}">
                                                {{ $item['value'] }}
                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">
                                        KM Travelled Daily
                                    </label>

                                    <select name="km_travelled_daily" class="form-control form-select">

                                        <option value="">Select KM Travelled Daily</option>

                                        @foreach ($km_travelled_daily as $item)
                                            <option value="{{ $item['code'] }}">
                                                {{ $item['value'] }}
                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">
                                        Application Type
                                    </label>

                                    <select name="application_type" id="application_type"
                                        class="form-control form-select">

                                        <option value="">Select Application Type</option>

                                        @foreach ($application_types as $item)
                                            <option value="{{ $item['code'] }}">
                                                {{ $item['value'] }}
                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">
                                        Application
                                    </label>

                                    <select name="application" id="application" class="form-control form-select">

                                        <option value="">Select Application</option>

                                        @foreach ($applications as $item)
                                            <option value="{{ $item['code'] }}">
                                                {{ $item['value'] }}
                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                {{-- =========================== SHORT ENQUIRY Dealer Details =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">

                        <h5 class="mb-0">
                            Dealer Details
                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            {{-- Dealer Branch --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Select Dealer Branch
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="dealer_branch" id="dealer_branch" class="form-control form-select"
                                    required>

                                    <option value="">Select Dealer Branch</option>

                                    @foreach ($branches as $code => $name)
                                        <option value="{{ $code }}">
                                            {{ $name }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            {{-- Dealer Location --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Select Dealer Location
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="dealer_location" id="dealer_location" class="form-control form-select"
                                    required>

                                    <option value="">Select Dealer Location</option>

                                </select>

                            </div>

                            {{-- Sales Consultant --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Select SC
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="sc_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Sales Consultant
                                    </option>

                                    @foreach ($saleconsultants as $consultant)
                                        <option value="{{ $consultant['person_code'] }}">

                                            {{ $consultant['display_name'] }}
                                            -
                                            {{ $consultant['employee_code'] }}

                                        </option>
                                    @endforeach

                                </select>

                            </div>

                        </div>

                    </div>

                </div>

                {{-- =========================== SHORT ENQUIRY Follow Up =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">

                        <h5 class="mb-0">
                            Follow Up
                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            {{-- Follow Up Type --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Follow Up Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="followup_type" class="form-control form-select">

                                    <option value="">Select Follow Up Type</option>

                                    @foreach ($follow_up_types as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            {{-- Follow Up Date --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Follow Up Date
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="date" name="followup_date" class="form-control"
                                    value="{{ old('followup_date') }}" required>

                            </div>

                            {{-- Follow Up Time --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Follow Up Time
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="time" name="followup_time" class="form-control"
                                    value="{{ old('followup_time') }}" required>

                            </div>

                        </div>

                    </div>

                </div>
                {{-- =========================== LONG ENQUIRY Customer Details =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">
                        <h5 class="mb-0">Long Enquiry - Customer Details</h5>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            {{-- Occupation Type --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Occupation Type
                                </label>

                                <select name="occupation_type" class="form-control form-select">

                                    <option value="">Select Occupation Type</option>

                                    @foreach ($occupation_types as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            {{-- Customer Type --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Customer Type
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <select name="customer_type" class="form-control form-select">

                                    <option value="">Select Customer Type</option>

                                    @foreach ($customer_types as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            {{-- Occupation Sub Type --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Occupation Sub Type
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <select name="occupation_sub_type" class="form-control form-select">

                                    <option value="">Select Occupation Sub Type</option>

                                    @foreach ($occupation_sub_types as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            {{-- Company Name --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Company Name
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="text" name="company_name" class="form-control"
                                    value="{{ old('company_name') }}">
                            </div>

                            {{-- Gender --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Gender
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <select name="gender" class="form-control form-select">

                                    <option value="">Select Gender</option>

                                    @foreach ($genders as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            {{-- D.O.B. --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    D.O.B.
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="date" name="dob" class="form-control" value="{{ old('dob') }}">
                            </div>

                            {{-- Marital Status --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Marital Status
                                </label>

                                <select name="marital_status" class="form-control form-select">

                                    <option value="">Select Marital Status</option>

                                    @foreach ($marital_statuses as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                            {{-- Date of Marriage --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Date of Marriage
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="date" name="marriage_date" class="form-control"
                                    value="{{ old('marriage_date') }}">
                            </div>

                            {{-- Age Group --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    Age Group
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <select name="age_group" class="form-control form-select">

                                    <option value="">Select Age Group</option>

                                    @foreach ($age_groups as $item)
                                        <option value="{{ $item['code'] }}">
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>

                        </div>

                    </div>

                </div>

                {{-- =========================== LONG ENQUIRY Address Details =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">
                        <h5 class="mb-0">Address Details</h5>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            {{-- Zip Code --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Zip Code
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="text" name="zipcode" class="form-control" value="{{ old('zipcode') }}">
                            </div>

                            {{-- Tehsil --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    Tehsil
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="text" name="tehsil" class="form-control" value="{{ old('tehsil') }}">
                            </div>

                            {{-- District --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    District
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="text" name="district" class="form-control"
                                    value="{{ old('district') }}">
                            </div>

                            {{-- City --}}
                            <div class="col-md-3 mb-3">

                                <label class="form-label">
                                    City
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                            </div>

                        </div>

                    </div>

                </div>

                {{-- =========================== LONG ENQUIRY Purchase Details =========================== --}}

                <div class="card mb-4">

                    <div class="card-header bg-light">
                        <h5 class="mb-0">Purchase Details</h5>
                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="row d-none" id="bevSection">

                                <div class="col-md-4 mb-3">

                                    <label>Do you have an EV?</label>

                                    <div>

                                        <div class="form-check form-check-inline">

                                            <input class="form-check-input" type="radio" name="has_ev"
                                                value="Yes">

                                            <label class="form-check-label">
                                                Yes
                                            </label>

                                        </div>

                                        <div class="form-check form-check-inline">

                                            <input class="form-check-input" type="radio" name="has_ev"
                                                value="No">

                                            <label class="form-check-label">
                                                No
                                            </label>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            {{-- Purchase Type --}}
                            <div class="col-md-4 mb-3">

                                <label class="form-label">
                                    Purchase Type
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <select name="purchase_type" id="purchase_type" class="form-control form-select">

                                    <option value="">Select Purchase Type</option>

                                    <option value="First Time Buy"
                                        {{ old('purchase_type') == 'First Time Buy' ? 'selected' : '' }}>
                                        First Time Buy
                                    </option>

                                    <option value="Exchange Buy"
                                        {{ old('purchase_type') == 'Exchange Buy' ? 'selected' : '' }}>
                                        Exchange Buy
                                    </option>

                                    <option value="Additional Buy"
                                        {{ old('purchase_type') == 'Additional Buy' ? 'selected' : '' }}>
                                        Additional Buy
                                    </option>

                                    <option value="Scrappage" {{ old('purchase_type') == 'Scrappage' ? 'selected' : '' }}>
                                        Scrappage
                                    </option>

                                </select>

                            </div>

                            <div class="row d-none" id="exchangeFields">

                                {{-- Make --}}
                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Make
                                    </label>

                                    <input type="text" id="exchange_make" name="exchange_make" class="form-control"
                                        value="{{ old('exchange_make') }}">

                                </div>

                                {{-- Model --}}
                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Model
                                    </label>

                                    <input type="text" id="exchange_model" name="exchange_model" class="form-control"
                                        value="{{ old('exchange_model') }}">

                                </div>

                                {{-- Vehicle No. --}}
                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Vehicle No.
                                    </label>

                                    <input type="text" id="vehicle_no" name="vehicle_no" class="form-control"
                                        value="{{ old('vehicle_no') }}">

                                </div>

                            </div>

                            {{-- Remarks --}}
                            <div class="col-md-8 mb-3">

                                <label class="form-label">
                                    Remarks
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <textarea name="remarks" rows="3" class="form-control">{{ old('remarks') }}</textarea>

                            </div>

                        </div>

                    </div>

                </div>

                {{-- =========================== FORM ACTIONS =========================== --}}

                <div class="card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center flex-wrap">

                            <div>

                                <button type="submit" class="btn btn-success btn-lg">

                                    <i class="la la-save"></i>

                                    Save Enquiry

                                </button>

                            </div>

                        </div>

                    </div>

                </div>

                </form>

            </div>
        </div>
    </div>
    </div>
    </div>

@endsection

@push('after_scripts')
    <script>
        function loadKeywordDropdown(keyword, parent, target, placeholder = 'Select Option', selected = '') {

            if (!parent) {

                $(target)
                    .html(`<option value="">${placeholder}</option>`)
                    .prop('disabled', true);

                return;
            }

            $.ajax({

                url: "{{ route('admin.master.keyword-values', ['keyword' => '__KEYWORD__', 'parent' => '__PARENT__']) }}"
                    .replace('__KEYWORD__', encodeURIComponent(keyword))
                    .replace('__PARENT__', encodeURIComponent(parent)),

                type: "GET",

                beforeSend: function() {

                    $(target)
                        .html('<option>Loading...</option>')
                        .prop('disabled', true);

                },

                success: function(response) {

                    let html = `<option value="">${placeholder}</option>`;

                    $.each(response, function(_, item) {

                        html += `
                        <option value="${item.code}"
                            ${selected == item.code ? 'selected' : ''}>
                            ${item.value}
                        </option>
                    `;

                    });

                    $(target)
                        .html(html)
                        .prop('disabled', false);

                },

                error: function() {

                    $(target)
                        .html(`<option value="">${placeholder}</option>`)
                        .prop('disabled', true);

                }

            });

        }

        $(function() {

            /*
            |--------------------------------------------------------------------------
            | Initial State
            |--------------------------------------------------------------------------
            */

            $('#model_code').prop('disabled', true);
            $('#variant_code').prop('disabled', true);
            $('#color_code').prop('disabled', true);

            $('#bevSection').hide();
            // $('#commercialSection').hide();

            $('#exchangeVehicleSection').hide();

            $('#source_code').prop('disabled', true);
            $('#sub_source').prop('disabled', true);
            $('#application').prop('disabled', true);


            /*
            |--------------------------------------------------------------------------
            | Enquiry Type -> Enquiry Source
            |--------------------------------------------------------------------------
            */

            $('#enquiry_type').on('change', function() {

                let enquiryType = $(this).val();

                loadKeywordDropdown(
                    'ENQ_SOURCE',
                    enquiryType,
                    '#source_code',
                    'Select Enquiry Source'
                );

                $('#sub_source')
                    .html('<option value="">Select Enquiry Sub Source</option>')
                    .prop('disabled', true);

            });


            /*
            |--------------------------------------------------------------------------
            | Enquiry Source -> Enquiry Sub Source
            |--------------------------------------------------------------------------
            */

            $('#source_code').on('change', function() {

                let source = $(this).val();

                loadKeywordDropdown(
                    'ENQUIRY_SUB_SOURCE',
                    source,
                    '#sub_source',
                    'Select Enquiry Sub Source'
                );

            });


            /*
            |--------------------------------------------------------------------------
            | Purchase Type
            |--------------------------------------------------------------------------
            */

            $(document).ready(function() {

                function toggleExchangeFields() {

                    let purchaseType = $('#purchase_type').val();

                    if (
                        purchaseType === 'Exchange Buy' ||
                        purchaseType === 'Additional Buy' ||
                        purchaseType === 'Scrappage'
                    ) {

                        $('#exchangeFields').show();

                        $('#exchange_make').prop('required', true);
                        $('#exchange_model').prop('required', true);
                        $('#vehicle_no').prop('required', true);

                    } else {

                        $('#exchangeFields').hide();

                        $('#exchange_make,#exchange_model,#vehicle_no')
                            .val('')
                            .prop('required', false);

                    }
                }

                $('#purchase_type').on('change', toggleExchangeFields);

                toggleExchangeFields();

            });


            /*
            |--------------------------------------------------------------------------
            | Application Type -> Application
            |--------------------------------------------------------------------------
            */

            $('#application_type').on('change', function() {

                let applicationType = $(this).val();

                loadKeywordDropdown(
                    'APPLICATION',
                    applicationType,
                    '#application',
                    'Select Application'
                );

            });


            /*
            |--------------------------------------------------------------------------
            | Page Load Triggers
            |--------------------------------------------------------------------------
            */

            $('#purchase_type').trigger('change');
            $('#application_type').trigger('change');
            $('#enquiry_type').trigger('change');


            /*
            |--------------------------------------------------------------------------
            | Segment -> Model
            |--------------------------------------------------------------------------
            */

            $('#segment_code').on('change', function() {

                let segmentCode = $(this).val();
                let segmentText = $('#segment_code option:selected').text().trim().toUpperCase();

                $('#bevSection').toggle(segmentText === 'BEV');
                // $('#commercialSection').toggle(segmentText === 'LMM' || segmentText === 'COMMERCIAL');

                // Reset fields
                $('#model_code')
                    .html('<option value="">Select Model</option>')
                    .prop('disabled', true);

                $('#variant_code')
                    .html('<option value="">Select Variant</option>')
                    .prop('disabled', true);

                $('#color_code')
                    .html('<option value="">Select Color</option>')
                    .prop('disabled', true);

                $('#fuel_type').val('');
                $('#fuel_type_id').val('');

                $('#transmission').val('');
                $('#drivetrain').val('');
                $('#seating').val('');

                if (!segmentCode) {
                    return;
                }

                $.get(
                    "{{ backpack_url('enquiry/models') }}/" + segmentCode,
                    function(response) {

                        let html = '<option value="">Select Model</option>';

                        $.each(response, function(code, value) {

                            html += `<option value="${code}">${value}</option>`;

                        });

                        $('#model_code')
                            .html(html)
                            .prop('disabled', false);

                    }
                );

            });


            /*
            |--------------------------------------------------------------------------
            | Model -> Variant
            |--------------------------------------------------------------------------
            */

            $('#model_code').on('change', function() {

                let modelCode = $(this).val();

                $('#variant_code')
                    .html('<option value="">Loading...</option>')
                    .prop('disabled', true);

                $('#color_code')
                    .html('<option value="">Select Color</option>')
                    .prop('disabled', true);

                $('#fuel_type').val('');
                $('#fuel_type_id').val('');

                $('#transmission').val('');
                $('#drivetrain').val('');
                $('#seating').val('');

                if (!modelCode) {
                    return;
                }

                $.get(
                    "{{ backpack_url('enquiry/variants') }}/" + modelCode,

                    function(response) {

                        let html = '<option value="">Select Variant</option>';

                        $.each(response, function(code, item) {

                            html += `
                    <option
                        value="${code}"
                        data-fuel="${item.fuel_type ?? ''}"
                        data-fuel-id="${item.fuel_type_id ?? ''}"
                        data-transmission="${item.transmission ?? ''}"
                        data-drivetrain="${item.drivetrain ?? ''}"
                        data-seating="${item.seating ?? ''}">
                        ${item.name}
                    </option>
                `;

                        });

                        $('#variant_code')
                            .html(html)
                            .prop('disabled', false);

                    }

                );

            });


            /*
            |--------------------------------------------------------------------------
            | Variant -> Auto Fill + Color
            |--------------------------------------------------------------------------
            */

            $('#variant_code').on('change', function() {

                let variantCode = $(this).val();

                let selected = $(this).find(':selected');

                // Auto Fill
                $('#fuel_type').val(selected.data('fuel'));
                $('#fuel_type_id').val(selected.data('fuel-id'));

                $('#transmission').val(selected.data('transmission'));
                $('#drivetrain').val(selected.data('drivetrain'));
                $('#seating').val(selected.data('seating'));

                $('#color_code')
                    .html('<option value="">Loading...</option>')
                    .prop('disabled', true);

                if (!variantCode) {
                    return;
                }

                $.get(
                    "{{ backpack_url('enquiry/colors') }}/" + variantCode,

                    function(response) {

                        let html = '<option value="">Select Color</option>';

                        $.each(response, function(code, value) {

                            html += `<option value="${code}">${value}</option>`;

                        });

                        $('#color_code')
                            .html(html)
                            .prop('disabled', false);

                    }

                );

            });

            $(document).on('change', '#dealer_branch', function() {

                console.log('Branch Changed');

                let branchCode = $(this).val();

                $('#dealer_location').html('<option>Loading...</option>');

                $.get(
                    "{{ backpack_url('enquiry/locations') }}/" + branchCode,
                    function(response) {

                        console.log(response);

                        let html = '<option value="">Select Dealer Location</option>';

                        $.each(response, function(code, name) {
                            html += `<option value="${code}">${name}</option>`;
                        });

                        $('#dealer_location').html(html);
                    }
                );
            });

            function toggleExchangeFields() {

                let purchaseType = $('#purchase_type').val();

                if (['Exchange Buy', 'Additional Buy', 'Scrappage'].includes(purchaseType)) {

                    $('#exchangeFields').removeClass('d-none');

                    $('#exchange_make,#exchange_model,#vehicle_no').prop('required', true);

                } else {

                    $('#exchangeFields').addClass('d-none');

                    $('#exchange_make,#exchange_model,#vehicle_no')
                        .val('')
                        .prop('required', false);

                }
            }

            $('#purchase_type').on('change', toggleExchangeFields);
            toggleExchangeFields();

            /*
            |--------------------------------------------------------------------------
            | Final Page Load
            |--------------------------------------------------------------------------
            */

            $('#purchase_type').trigger('change');
            $('#application_type').trigger('change');
            $('#enquiry_type').trigger('change');
            $('#segment_code').trigger('change');

        });
    </script>
@endpush
