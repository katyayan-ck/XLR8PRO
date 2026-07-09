{{-- =========================== SHORT ENQUIRY Customer Information =========================== --}}

@extends(backpack_view('blank'))

@section('title', 'Add New Enquiry')


@push('after_styles')
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
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
                        <h2 class="mb-0">Add Hot Enquiry</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ backpack_url('enquiry') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row"></div>
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Customer Information</h5>
                                </div>

                                <div class="card-body">

                                    <div class="row">

                                        {{-- Customer First Name --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Customer First Name
                                                <span class="text-danger">*</span>
                                            </label>

                                            <input type="text" name="first_name" class="form-control"
                                                value="{{ old('first_name') }}" required>
                                        </div>

                                        {{-- Customer Last Name --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Customer Last Name
                                                <span class="text-danger">*</span>
                                            </label>

                                            <input type="text" name="last_name" class="form-control"
                                                value="{{ old('last_name') }}" required>
                                        </div>

                                        {{-- Phone Number --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Phone Number
                                                <span class="text-danger">*</span>
                                            </label>

                                            <input type="text" name="mobile" maxlength="10" class="form-control"
                                                value="{{ old('mobile') }}" required>
                                        </div>

                                        {{-- Email --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">

                                                Email ID

                                                <small class="text-muted">
                                                    (Optional)
                                                </small>

                                            </label>

                                            <input type="email" name="email" class="form-control"
                                                value="{{ old('email') }}">
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

                                        {{-- Segment --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">

                                                Segment

                                                <span class="text-danger">*</span>

                                            </label>

                                            <select name="segment_code" id="segment_code" class="form-control form-select"
                                                required>

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

                                        {{-- Enquiry Type --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Enquiry Type
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="enquiry_type" id="enquiry_type" class="form-control form-select"
                                                required>

                                                <option value="">Select Enquiry Type</option>

                                                <option value="Telephone"
                                                    {{ old('enquiry_type') == 'Telephone' ? 'selected' : '' }}>
                                                    Telephone
                                                </option>

                                                <option value="Field"
                                                    {{ old('enquiry_type') == 'Field' ? 'selected' : '' }}>
                                                    Field
                                                </option>

                                                <option value="Digital"
                                                    {{ old('enquiry_type') == 'Digital' ? 'selected' : '' }}>
                                                    Digital
                                                </option>

                                                <option value="Walk-In"
                                                    {{ old('enquiry_type') == 'Walk-In' ? 'selected' : '' }}>
                                                    Walk - In
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Enquiry Source --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Enquiry Source
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="source_code" id="source_code" class="form-control form-select"
                                                required>

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

                                            <select name="sub_source" id="sub_source" class="form-control form-select"
                                                disabled>

                                                <option value="">
                                                    Select Enquiry Sub Source
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Planned Campaign --}}
                                        <div class="col-md-4 mb-3">

                                            <label class="form-label">

                                                Planned Campaign

                                                <span class="text-danger">*</span>

                                            </label>

                                            <select name="planned_campaign" class="form-control form-select" required>

                                                <option value="">
                                                    Select Planned Campaign
                                                </option>

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

                                            <input type="date" name="likely_purchase_date" class="form-control"
                                                value="{{ old('likely_purchase_date') }}" required>

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

                                        {{-- Model --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Model
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="model_code" id="model_code" class="form-control form-select"
                                                required>

                                                <option value="">
                                                    Select Model
                                                </option>

                                            </select>
                                        </div>

                                        {{-- Fuel Type --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Fuel Type
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="fuel_type" class="form-control form-select" required>

                                                <option value="">
                                                    Select Fuel Type
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Transmission --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Transmission
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="transmission" class="form-control form-select" required>

                                                <option value="">
                                                    Select Transmission
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Drivetrain --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Drivetrain
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="drivetrain" class="form-control form-select" required>

                                                <option value="">
                                                    Select Drivetrain
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Seating --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Seating
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="seating" class="form-control form-select" required>

                                                <option value="">
                                                    Select Seating
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Color --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Color
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="color_code" id="color_code" class="form-control form-select"
                                                required>

                                                <option value="">
                                                    Select Color
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Variant --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Variant
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="variant_code" id="variant_code"
                                                class="form-control form-select" required>

                                                <option value="">
                                                    Select Variant
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Commercial/LMM Only --}}
                                        <div class="row" id="commercialSection">

                                            <div class="col-md-3 mb-3">

                                                <label class="form-label">
                                                    Usage Area
                                                </label>

                                                <select name="usage_area" id="usage_area"
                                                    class="form-control form-select">

                                                    <option value="">Select Usage Area</option>

                                                    <option value="Intra City">
                                                        Intra City (Within City Limits Only)
                                                    </option>

                                                    <option value="Inter City">
                                                        Inter City (One City to Another City)
                                                    </option>

                                                    <option value="Only Rural">
                                                        Only Rural
                                                    </option>

                                                    <option value="City to Rural">
                                                        City to Rural
                                                    </option>

                                                    <option value="City Outskirts">
                                                        City Outskirts
                                                    </option>

                                                    <option value="City Municipal Area">
                                                        City Municipal Area - LMM Only for Pass Permit
                                                    </option>

                                                    <option value="Inside City But Outside Municipal Area">
                                                        Inside City But Outside Municipal Area - LMM Only for Pass Permit
                                                    </option>

                                                </select>

                                            </div>

                                            <div class="col-md-3 mb-3">

                                                <label class="form-label">
                                                    KM Travelled Daily
                                                </label>

                                                <select name="km_travelled_daily" id="km_travelled_daily"
                                                    class="form-control form-select">

                                                    <option value="">Select KM Travelled Daily</option>

                                                    <option value="0-49">0 - 49 KM</option>

                                                    <option value="50-99">50 - 99 KM</option>

                                                    <option value="100-199">100 - 199 KM</option>

                                                    <option value=">=200">≥ 200 KM</option>

                                                </select>

                                            </div>

                                            <div class="col-md-3 mb-3">

                                                <label class="form-label">
                                                    Application Type
                                                </label>

                                                <select name="application_type" id="application_type"
                                                    class="form-control form-select">

                                                    <option value="">Select Application Type</option>

                                                    <option value="Captive">
                                                        Captive (For Goods Permit Only)
                                                    </option>

                                                    <option value="MLO (Owner Cum Driver)">
                                                        MLO (Owner Cum Driver) - For Goods Permit Only
                                                    </option>

                                                    <option value="Corporate & Institutional (Load)">
                                                        Corporate & Institutional (Load)
                                                    </option>

                                                    <option value="Corporate & Institutional (Passenger)">
                                                        Corporate & Institutional (Passenger)
                                                    </option>

                                                    <option value="Commercial">
                                                        Commercial
                                                    </option>

                                                    <option value="Personal">
                                                        Personal
                                                    </option>

                                                </select>

                                            </div>

                                            <div class="col-md-3 mb-3">

                                                <label class="form-label">
                                                    Application
                                                </label>

                                                <select name="application" id="application"
                                                    class="form-control form-select" disabled>

                                                    <option value="">Select Application</option>

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

                                            <select name="dealer_branch" class="form-control form-select" required>

                                                <option value="">
                                                    Select Dealer Branch
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Dealer Location --}}
                                        <div class="col-md-4 mb-3">

                                            <label class="form-label">
                                                Select Dealer Location
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="dealer_location" class="form-control form-select" required>

                                                <option value="">
                                                    Select Dealer Location
                                                </option>

                                            </select>

                                        </div>

                                        {{-- Sales Consultant --}}
                                        <div class="col-md-4 mb-3">

                                            <label class="form-label">
                                                Select SC
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="sales_consultant_id" class="form-control form-select" required>

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

                                            <select name="followup_type" id="followup_type"
                                                class="form-control form-select" required>

                                                <option value="">Select Follow Up Type</option>

                                                <option value="Call"
                                                    {{ old('followup_type') == 'Call' ? 'selected' : '' }}>
                                                    Call
                                                </option>

                                                <option value="Test Drive"
                                                    {{ old('followup_type') == 'Test Drive' ? 'selected' : '' }}>
                                                    Test Drive
                                                </option>

                                                <option value="Customer Location Visit"
                                                    {{ old('followup_type') == 'Customer Location Visit' ? 'selected' : '' }}>
                                                    Customer Location Visit
                                                </option>

                                                <option value="Showroom Visit"
                                                    {{ old('followup_type') == 'Showroom Visit' ? 'selected' : '' }}>
                                                    Showroom Visit
                                                </option>

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
                                                <small class="text-muted">(Optional)</small>
                                            </label>

                                            <select name="occupation_type" id="occupation_type"
                                                class="form-control form-select">

                                                <option value="">Select Occupation Type</option>

                                                <option value="Business">Business</option>

                                                <option value="Salaried Pvt.">Salaried Pvt.</option>

                                                <option value="Self Employed Pro">Self Employed Pro</option>

                                                <option value="Salaried Govt. / PSU / Pensioner">
                                                    Salaried Govt. / PSU / Pensioner
                                                </option>

                                                <option value="Agri Based">Agri Based</option>

                                                <option value="Housewife">Housewife</option>

                                                <option value="Student">Student</option>

                                                <option value="CSD - CPC">CSD - CPC</option>

                                            </select>
                                        </div>

                                        {{-- Customer Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Customer Type
                                                <small class="text-muted">(Optional)</small>
                                            </label>

                                            <select name="customer_type" id="customer_type"
                                                class="form-control form-select">

                                                <option value="">Select Customer Type</option>

                                                <option value="Individual">Individual</option>

                                                <option value="Corporate">Corporate</option>

                                            </select>
                                        </div>

                                        {{-- Occupation Sub Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Occupation Sub Type
                                                <small class="text-muted">(Optional)</small>
                                            </label>

                                            <select name="occupation_sub_type" id="occupation_sub_type"
                                                class="form-control form-select">

                                                <option value="">Select Occupation Sub Type</option>

                                                <option value="MSME">MSME</option>

                                                <option value="Retail">Retail</option>

                                                <option value="Distributor">Distributor</option>

                                                <option value="Pharma">Pharma</option>

                                                <option value="Trading">Trading</option>

                                                <option value="IT">IT</option>

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
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>

                                            </select>
                                        </div>

                                        {{-- Date of Birth --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Date of Birth
                                                <small class="text-muted">(Optional)</small>
                                            </label>

                                            <input type="date" name="dob" class="form-control"
                                                value="{{ old('dob') }}">
                                        </div>

                                        {{-- Marital Status --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Marital Status
                                                <small class="text-muted">(Optional)</small>
                                            </label>

                                            <select name="marital_status" class="form-control form-select">

                                                <option value="">
                                                    Select Marital Status
                                                </option>

                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Divorced">Divorced</option>
                                                <option value="Widowed">Widowed</option>

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

                                                <option value="">
                                                    Select Age Group
                                                </option>

                                                <option value="18-25">18-25</option>
                                                <option value="26-35">26-35</option>
                                                <option value="36-45">36-45</option>
                                                <option value="46-60">46-60</option>
                                                <option value="60+">60+</option>

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

                                            <input type="text" name="zipcode" class="form-control"
                                                value="{{ old('zipcode') }}">
                                        </div>

                                        {{-- Tehsil --}}
                                        <div class="col-md-3 mb-3">

                                            <label class="form-label">
                                                Tehsil
                                                <small class="text-muted">(Optional)</small>
                                            </label>

                                            <input type="text" name="tehsil" class="form-control"
                                                value="{{ old('tehsil') }}">
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

                                            <input type="text" name="city" class="form-control"
                                                value="{{ old('city') }}">
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

                                            <select name="purchase_type" id="purchase_type"
                                                class="form-control form-select">

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

                                                <option value="Scrappage"
                                                    {{ old('purchase_type') == 'Scrappage' ? 'selected' : '' }}>
                                                    Scrappage
                                                </option>

                                            </select>

                                        </div>

                                        <div class="row d-none" id="exchangeVehicleSection">

                                            {{-- Make --}}
                                            <div class="col-md-4 mb-3">

                                                <label class="form-label">
                                                    Make
                                                </label>

                                                <input type="text" name="exchange_make" class="form-control"
                                                    value="{{ old('exchange_make') }}">

                                            </div>

                                            {{-- Model --}}
                                            <div class="col-md-4 mb-3">

                                                <label class="form-label">
                                                    Model
                                                </label>

                                                <input type="text" name="exchange_model" class="form-control"
                                                    value="{{ old('exchange_model') }}">

                                            </div>

                                            {{-- Vehicle No --}}
                                            <div class="col-md-4 mb-3">

                                                <label class="form-label">
                                                    Vehicle No.
                                                </label>

                                                <input type="text" name="vehicle_no" class="form-control"
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

                                            <button type="submit" name="save_action" value="save_and_new"
                                                class="btn btn-primary btn-lg">

                                                <i class="la la-plus"></i>

                                                Save & New

                                            </button>

                                        </div>

                                        <div>

                                            <a href="{{ backpack_url('enquiries/hot-enquiry-list') }}"
                                                class="btn btn-secondary btn-lg">

                                                <i class="la la-times"></i>

                                                Cancel

                                            </a>

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
        $(function() {

            // ================================
            // Initial State
            // ================================

            $('#model_code').prop('disabled', true);
            $('#variant_code').prop('disabled', true);
            $('#color_code').prop('disabled', true);

            $('#bevSection').hide();
            // $('#commercialSection').hide();


            // =========================================
            // Enquiry Type -> Enquiry Source
            // =========================================

            const enquirySources = {

                Telephone: [
                    "Newspaper",
                    "Television",
                    "Hoarding",
                    "Activations",
                    "Outbound Calling",
                    "Reference",
                    "Broker",
                    "Mitra",
                    "Financier"
                ],

                Field: [
                    "Newspaper",
                    "Television",
                    "Hoarding",
                    "Activations",
                    "Outbound Calling",
                    "Reference",
                    "Broker",
                    "Mitra",
                    "Financier"
                ],

                Digital: [
                    "Activations",
                    "WAP (Workshop as Promoter)",
                    "Dealer Website",
                    "Dealer Social Media",
                    "Car Portals",
                    "Brand Website",
                    "Financier",
                    "Hyperlocal"
                ],

                "Walk-In": [
                    "Walk-In"
                ]

            };


            function populateEnquirySource(type) {

                let dropdown = $('#source_code');

                dropdown.empty();

                dropdown.append(
                    '<option value="">Select Enquiry Source</option>'
                );

                if (!type || !enquirySources[type]) {
                    return;
                }

                $.each(enquirySources[type], function(i, item) {

                    dropdown.append(
                        $('<option>', {
                            value: item,
                            text: item
                        })
                    );

                });

            }


            $('#enquiry_type').change(function() {

                populateEnquirySource($(this).val());

            });

            // ========================================
            // Enquiry Source -> Enquiry Sub Source
            // ========================================

            const hyperlocalSubSources = [

                "Google",
                "Social",
                "Organic"

            ];

            $('#source_code').on('change', function() {

                let source = $(this).val();

                let subSource = $('#sub_source');

                subSource.empty();

                subSource.append(
                    '<option value="">Select Enquiry Sub Source</option>'
                );

                if (source === "Hyperlocal") {

                    subSource.prop('disabled', false);

                    $.each(hyperlocalSubSources, function(i, item) {

                        subSource.append(

                            '<option value="' + item + '">' +
                            item +
                            '</option>'

                        );

                    });

                } else {

                    subSource.val('');

                    subSource.prop('disabled', true);

                }

            });

            // ======================================
            // Purchase Type
            // ======================================

            $('#exchangeVehicleSection').hide();

            $('#purchase_type').on('change', function() {

                let purchaseType = $(this).val();

                if (
                    purchaseType === 'Exchange Buy' ||
                    purchaseType === 'Additional Buy' ||
                    purchaseType === 'Scrappage'
                ) {

                    $('#exchangeVehicleSection').removeClass('d-none').show();

                } else {

                    $('#exchangeVehicleSection').addClass('d-none').hide();

                    $('input[name="exchange_make"]').val('');
                    $('input[name="exchange_model"]').val('');
                    $('input[name="vehicle_no"]').val('');

                }

            });

            // Trigger on page load (for old values/edit form)
            $('#purchase_type').trigger('change');

            // ======================================
            // Application Type -> Application
            // ======================================

            const applicationOptions = [

                "Agri Based",
                "Airlines & Airports",
                "Beverages",
                "BPO & ITES",
                "Caterer / Decorator",
                "Charitable Institutions",
                "Construction",
                "Consumer Durables",
                "Contract",
                "Dairy / Milk",
                "Family / Business Usage",
                "Fishery",
                "FMCG",
                "Food Chain & Restaurants",
                "Fruit & Vegetables",
                "Furniture",
                "Garments",
                "Grocery Items",
                "Hospitals",
                "Industrial Goods",
                "Logistics & Ecommerce",
                "LPG",
                "Municipal Applications",
                "Others",
                "Packers",
                "Pharma",
                "Poultry",
                "Refrigerated Products",
                "School & Colleges",
                "Staff Transportation",
                "Stand Operator",
                "Spares & Auto Ancillaries",
                "Tours & Travels",
                "Wood / Timber / Plywood"

            ];

            $('#application_type').on('change', function() {

                let type = $(this).val();

                let application = $('#application');

                application.empty();

                application.append(
                    '<option value="">Select Application</option>'
                );

                if (type === '' || type === 'Personal') {

                    application.prop('disabled', true);

                    return;

                }

                application.prop('disabled', false);

                $.each(applicationOptions, function(i, item) {

                    application.append(

                        $('<option>', {

                            value: item,
                            text: item

                        })

                    );

                });

            });

            // Trigger on page load
            $('#application_type').trigger('change');


            // =========================================
            // Segment -> Model
            // =========================================

            $('#segment_code').change(function() {

                let segmentCode = $(this).val();

                let segmentText = $('#segment_code option:selected')
                    .text()
                    .trim()
                    .toUpperCase();


                $('#bevSection').hide();
                // $('#commercialSection').hide();

                // if (segmentText === "BEV") {

                //     $('#bevSection').show();

                // }

                // if (
                //     segmentText === "LMM" ||
                //     segmentText === "COMMERCIAL"
                // ) {

                //     $('#commercialSection').show();

                // }


                $('#model_code')
                    .html('<option>Loading...</option>')
                    .prop('disabled', true);

                $('#variant_code')
                    .html('<option>Select Variant</option>')
                    .prop('disabled', true);

                $('#color_code')
                    .html('<option>Select Color</option>')
                    .prop('disabled', true);


                if (segmentCode == '') {
                    return;
                }


                $.get(
                    "{{ backpack_url('enquiry/models') }}/" + segmentCode,
                    function(response) {

                        let options =
                            '<option value="">Select Model</option>';

                        $.each(response, function(code, name) {

                            options +=
                                '<option value="' +
                                code +
                                '">' +
                                name +
                                '</option>';

                        });

                        $('#model_code')
                            .html(options)
                            .prop('disabled', false);

                    }
                );

            });


            // =========================================
            // Model -> Variant
            // =========================================

            $('#model_code').change(function() {

                let modelCode = $(this).val();

                $('#variant_code')
                    .html('<option>Loading...</option>')
                    .prop('disabled', true);

                $('#color_code')
                    .html('<option>Select Color</option>')
                    .prop('disabled', true);

                if (modelCode == '') {
                    return;
                }

                $.get(
                    "{{ backpack_url('enquiry/variants') }}/" + modelCode,
                    function(response) {

                        let options =
                            '<option value="">Select Variant</option>';

                        $.each(response, function(code, name) {

                            options +=
                                '<option value="' +
                                code +
                                '">' +
                                name +
                                '</option>';

                        });

                        $('#variant_code')
                            .html(options)
                            .prop('disabled', false);

                    }
                );

            });


            // =========================================
            // Variant -> Color
            // =========================================

            $('#variant_code').change(function() {

                let variantCode = $(this).val();

                $('#color_code')
                    .html('<option>Loading...</option>')
                    .prop('disabled', true);

                if (variantCode == '') {
                    return;
                }

                $.get(
                    "{{ backpack_url('enquiry/colors') }}/" + variantCode,
                    function(response) {

                        let options =
                            '<option value="">Select Color</option>';

                        $.each(response, function(code, name) {

                            options +=
                                '<option value="' +
                                code +
                                '">' +
                                name +
                                '</option>';

                        });

                        $('#color_code')
                            .html(options)
                            .prop('disabled', false);

                    }
                );

            });

        });
    </script>
@endpush
