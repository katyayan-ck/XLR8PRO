{{-- =========================== ENQUIRY FORM =========================== --}}

@extends(backpack_view('blank'))

@section('title', 'Add New Enquiry')

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        /* --- REMOVE NUMBER ARROWS --- */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
@endpush

@section('content')
    @php
        // Strict Virtual Enquiry Detection based on current_origin
        $isVirtual = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'VIRTUAL';
    @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header text-black">
                        <h2 class="mb-0">
                            {{ isset($enquiry) ? 'Edit Enquiry' : 'Add Enquiry' }}
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="enquiryForm"
                            action="{{ isset($enquiry) ? backpack_url('enquiry/' . $enquiry->id) : backpack_url('enquiry') }}"
                            enctype="multipart/form-data">

                            @csrf

                            @if (isset($enquiry))
                                @method('PUT')
                            @endif

                            {{-- =========================== VIRTUAL CALL DETAILS =========================== --}}
                            @if($isVirtual)
                                <h3 class="mb-0 ms-3 mt-3">Virtual Number Call Details</h3>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Virtual Number</label>
                                            <input type="text" name="virtual_no" class="form-control" value="{{ $enquiry->virtual_no ?? '' }}" readonly>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Call Date</label>
                                            <input type="text" name="virtual_call_date" class="form-control" value="{{ !empty($enquiry->virtual_call_date) ? \Carbon\Carbon::parse($enquiry->virtual_call_date)->format('d-m-Y H:i') : '' }}" readonly>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Call Duration</label>
                                            <input type="text" name="call_duration" class="form-control" value="{{ $enquiry->call_duration ?? '' }}" readonly>
                                        </div>
                                        
                                        {{-- Mobile Field for Virtual Block --}}
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Customer Mobile <span class="text-danger">*</span></label>
                                            <input type="text" id="mobile" name="mobile" maxlength="10" class="form-control" value="{{ old('mobile', $enquiry->mobile ?? '') }}" required>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Call Nature <span class="text-danger">*</span></label>
                                            <select name="call_nature" id="call_nature" class="form-control form-select" required>
                                                <option value="">Select Option</option>
                                                @foreach ($call_nature_virtual as $item)
                                                    <option value="{{ $item['code'] }}" {{ old('call_nature', $enquiry->call_nature ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- WRAPPER FOR FULL FORM --}}
                            <div id="full_enquiry_form" style="{{ $isVirtual ? 'display: none;' : '' }}">

                                <h3 class="mb-0 ms-3">Customer Information</h3>

                                <div class="card-body">

                                    <div class="row">

                                        {{-- Segment --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Segment <span class="text-danger">*</span>
                                            </label>
                                            <select name="segment_code" id="segment_code" class="form-control form-select"
                                                required>
                                                <option value="">Select Segment</option>
                                                @foreach ($segments as $code => $name)
                                                    <option value="{{ $code }}"
                                                        {{ old('segment_code', $enquiry->segment_code ?? '') == $code ? 'selected' : '' }}>
                                                        {{ $name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Model --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Model <span class="text-danger">*</span>
                                            </label>
                                            <select name="model_code" id="model_code" class="form-control form-select" required>
                                                <option value="">Select Model</option>
                                            </select>
                                        </div>

                                        {{-- Variant --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Variant <span class="text-danger">*</span>
                                            </label>
                                            <select name="variant_code" id="variant_code" class="form-control form-select"
                                                required>
                                                <option value="">Select Variant</option>
                                            </select>
                                        </div>

                                        {{-- Customer First Name --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Customer First Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="first_name" class="form-control"
                                                value="{{ old('first_name', $enquiry->first_name ?? '') }}" required>
                                        </div>

                                        {{-- Customer Last Name --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Customer Last Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="last_name" class="form-control"
                                                value="{{ old('last_name', $enquiry->last_name ?? '') }}">
                                        </div>

                                        {{-- Phone Number for Standard Enquiries --}}
                                        @if(!$isVirtual)
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Phone Number <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="mobile" name="mobile" maxlength="10"
                                                class="form-control" value="{{ old('mobile', $enquiry->mobile ?? '') }}"
                                                required>
                                        </div>
                                        @endif

                                        {{-- Email --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Email ID <small class="text-muted">(Optional)</small>
                                            </label>
                                            <input type="email" name="email" class="form-control"
                                                value="{{ old('email', $enquiry->email ?? '') }}">
                                        </div>

                                        {{-- Gender --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Gender <small class="text-muted">(Optional)</small>
                                            </label>
                                            <select name="gender" class="form-control form-select">
                                                <option value="">Select Gender</option>
                                                @foreach ($genders as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('gender', $enquiry->gender ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>

                                </div>

                                {{-- =========================== SHORT ENQUIRY Enquiry Information =========================== --}}

                                <h3 class="mb-0 ms-3">Enquiry Information</h3>

                                <div class="card-body">

                                    <div class="row">

                                        {{-- Enquiry Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Enquiry Type <span class="text-danger">*</span>
                                            </label>
                                            <select name="enquiry_type" id="enquiry_type" class="form-control form-select"
                                                required>
                                                <option value="">Select Enquiry Type</option>
                                                @foreach ($enquiry_types as $etype)
                                                    <option value="{{ $etype['code'] }}"
                                                        {{ old('enquiry_type', $enquiry->enquiry_type ?? '') == $etype['code'] ? 'selected' : '' }}>
                                                        {{ $etype['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Enquiry Source --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Enquiry Source <span class="text-danger">*</span>
                                            </label>
                                            <select name="source_code" id="source_code" class="form-control form-select"
                                                required>
                                                <option value="">Select Enquiry Source</option>
                                            </select>
                                        </div>

                                        {{-- Enquiry Sub Source --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Enquiry Sub Source <span class="text-danger">*</span>
                                            </label>
                                            <select name="sub_source" id="sub_source" class="form-control form-select" disabled>
                                                <option value="">Select Enquiry Sub Source</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Planned Campaign</label>
                                            <select name="planned_campaign" id="planned_campaign"
                                                class="form-control form-select">
                                                <option value="">Select Planned Campaign</option>
                                                @foreach ($campaigns as $name)
                                                    <option value="{{ $name }}"
                                                        {{ old('planned_campaign', $enquiry->planned_campaign ?? '') == $name ? 'selected' : '' }}>
                                                        {{ $name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="row d-none" id="referenceFields">
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">
                                                    Referred By <span class="text-danger">*</span>
                                                </label>
                                                <select name="referred_by" id="referred_by" class="form-control form-select">
                                                    <option value="">Select Referred By</option>
                                                    <option value="Customer"
                                                        {{ old('referred_by', $enquiry->referred_by ?? '') == 'Customer' ? 'selected' : '' }}>
                                                        Customer</option>
                                                    <option value="Team Member"
                                                        {{ old('referred_by', $enquiry->referred_by ?? '') == 'Team Member' ? 'selected' : '' }}>
                                                        Team Member</option>
                                                    <option value="Promoter"
                                                        {{ old('referred_by', $enquiry->referred_by ?? '') == 'Promoter' ? 'selected' : '' }}>
                                                        Promoter</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">
                                                    Referee Phone Number <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" name="referee_phone" id="referee_phone"
                                                    class="form-control" maxlength="10"
                                                    value="{{ old('referee_phone', $enquiry->referee_phone ?? '') }}">
                                            </div>

                                            <input type="hidden" name="referee_name" id="referee_name">

                                            <div class="col-md-4 mb-3" id="referee_name_dropdown" style="display:none;">
                                                <label class="form-label">
                                                    Referee Name <span class="text-danger">*</span>
                                                </label>
                                                <select name="person_code" id="person_code" class="form-control form-select">
                                                    <option value="">Select Name</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3" id="referee_name_manual" style="display:none;">
                                                <label class="form-label">
                                                    Referee Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" id="referee_name_manual_input" class="form-control">
                                            </div>
                                        </div>

                                        {{-- Likely Purchase In Days --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Likely Purchase In Days <span class="text-danger">*</span>
                                            </label>
                                            <select name="likely_purchase_date" class="form-control form-select">
                                                <option value="">Select Likely Purchase In Days</option>
                                                @foreach ($likely_purchase_dates as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('likely_purchase_date', $enquiry->likely_purchase_date ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== SHORT ENQUIRY Vehicle Details =========================== --}}

                                <h3 class="mb-0 ms-3">Vehicle Details</h3>

                                <div class="card-body">
                                    <div class="row">

                                        {{-- Color --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Color <span class="text-danger">*</span>
                                            </label>
                                            <select name="color_code" id="color_code" class="form-control form-select"
                                                required>
                                                <option value="">Select Color</option>
                                            </select>
                                        </div>

                                        {{-- Fuel Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Fuel Type <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="fuel_type" class="form-control" readonly>
                                            <input type="hidden" id="fuel_type_id" name="fuel_type">
                                        </div>

                                        {{-- Transmission --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Transmission <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="transmission" name="transmission" class="form-control"
                                                readonly>
                                        </div>

                                        {{-- Drivetrain --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Drivetrain <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="drivetrain" name="drivetrain" class="form-control"
                                                readonly>
                                        </div>

                                        {{-- Seating --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Seating <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="seating" name="seating" class="form-control"
                                                readonly>
                                        </div>

                                        {{-- Commercial/LMM Only --}}
                                        <div class="row" id="commercialSection">
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Usage Area</label>
                                                <select name="usage_area" class="form-control form-select">
                                                    <option value="">Select Usage Area</option>
                                                    @foreach ($usage_areas as $item)
                                                        <option value="{{ $item['code'] }}"
                                                            {{ old('usage_area', $enquiry->usage_area ?? '') == $item['code'] ? 'selected' : '' }}>
                                                            {{ $item['value'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">KM Travelled Daily</label>
                                                <select name="km_travelled_daily" class="form-control form-select">
                                                    <option value="">Select KM Travelled Daily</option>
                                                    @foreach ($km_travelled_daily as $item)
                                                        <option value="{{ $item['code'] }}"
                                                            {{ old('km_travelled_daily', $enquiry->km_travelled_daily ?? '') == $item['code'] ? 'selected' : '' }}>
                                                            {{ $item['value'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Application Type</label>
                                                <select name="application_type" id="application_type"
                                                    class="form-control form-select">
                                                    <option value="">Select Application Type</option>
                                                    @foreach ($application_types as $item)
                                                        <option value="{{ $item['code'] }}"
                                                            {{ old('application_type', $enquiry->application_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                                            {{ $item['value'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Application</label>
                                                <select name="application" id="application" class="form-control form-select">
                                                    <option value="">Select Application</option>
                                                    @foreach ($applications as $item)
                                                        <option value="{{ $item['code'] }}"
                                                            {{ old('application', $enquiry->application ?? '') == $item['code'] ? 'selected' : '' }}>
                                                            {{ $item['value'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h3 class="mb-0 ms-3">Customer Address Details</h3>

                                <div class="card-body">
                                    <div class="row">
                                        {{-- Zip Code --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Pin Code <small class="text-muted">(Optional)</small>
                                            </label>
                                            <input type="text" id="zipcode" name="zipcode" maxlength="6"
                                                class="form-control" value="{{ old('zipcode', $enquiry->zipcode ?? '') }}">
                                        </div>

                                        {{-- Tehsil --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Tehsil<span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="tehsil" name="tehsil" class="form-control"
                                                value="{{ old('tehsil', $enquiry->tehsil ?? '') }}" readonly required>
                                        </div>

                                        {{-- District --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                District<span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="district" name="district" class="form-control"
                                                value="{{ old('district', $enquiry->district ?? '') }}" readonly required>
                                        </div>

                                        {{-- City --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                City <small class="text-muted">(Optional)</small>
                                            </label>
                                            <input type="text" id="city" name="city" class="form-control"
                                                value="{{ old('city', $enquiry->city ?? '') }}" readonly>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== SHORT ENQUIRY Dealer Details =========================== --}}

                                <h3 class="mb-0 ms-3">Dealer Details</h3>

                                <div class="card-body">
                                    <div class="row">
                                        {{-- Dealer Branch --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Select Dealer Branch <span class="text-danger">*</span>
                                            </label>
                                            <select name="dealer_branch" id="dealer_branch" class="form-control form-select"
                                                required>
                                                <option value="">Select Dealer Branch</option>
                                                @foreach ($branches as $code => $name)
                                                    <option value="{{ $code }}"
                                                        {{ old('dealer_branch', $enquiry->dealer_branch ?? '') == $code ? 'selected' : '' }}>
                                                        {{ $name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Dealer Location --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Select Dealer Location <span class="text-danger">*</span>
                                            </label>
                                            <select name="dealer_location" id="dealer_location"
                                                class="form-control form-select" required>
                                                <option value="">Select Dealer Location</option>
                                            </select>
                                        </div>

                                        {{-- Sales Consultant --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Select SC <span class="text-danger">*</span>
                                            </label>
                                            <select name="sc_code" class="form-control form-select" required>
                                                <option value="">Select Sales Consultant</option>
                                                @foreach ($saleconsultants as $consultant)
                                                    <option value="{{ $consultant['person_code'] }}"
                                                        {{ old('sc_code', $enquiry->sc_code ?? '') == $consultant['person_code'] ? 'selected' : '' }}>
                                                        {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== SHORT ENQUIRY Follow Up =========================== --}}

                                <h3 class="mb-0 ms-3">Follow Up</h3>

                                <div class="card-body">
                                    <div class="row">
                                        {{-- Follow Up Type --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Follow Up Type <span class="text-danger">*</span>
                                            </label>
                                            <select name="followup_type" class="form-control form-select">
                                                <option value="">Select Follow Up Type</option>
                                                @foreach ($follow_up_types as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('followup_type', $enquiry->followup_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Follow Up Date --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Follow Up Date <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="followup_date" name="followup_date"
                                                class="form-control"
                                                value="{{ old('followup_date', $enquiry->followup_date ?? '') }}"
                                                placeholder="Select Follow Up Date">
                                        </div>

                                        {{-- Follow Up Time --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Follow Up Time 
                                            </label>
                                            <input type="time" name="followup_time" class="form-control"
                                                value="{{ old('followup_time', $enquiry->followup_time ?? '') }}" >
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== LONG ENQUIRY Customer Details =========================== --}}

                                <h3 class="mb-0 ms-3">Long Enquiry - Customer Details</h3>

                                <div class="card-body">
                                    <div class="row">
                                        {{-- Occupation Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Occupation Type</label>
                                            <select name="occupation_type" class="form-control form-select">
                                                <option value="">Select Occupation Type</option>
                                                @foreach ($occupation_types as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('occupation_type', $enquiry->occupation_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Customer Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Customer Type <small class="text-muted">(Optional)</small>
                                            </label>
                                            <select name="customer_type" class="form-control form-select">
                                                <option value="">Select Customer Type</option>
                                                @foreach ($customer_types as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('customer_type', $enquiry->customer_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Occupation Sub Type --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Occupation Sub Type <small class="text-muted">(Optional)</small>
                                            </label>
                                            <select name="occupation_sub_type" class="form-control form-select">
                                                <option value="">Select Occupation Sub Type</option>
                                                @foreach ($occupation_sub_types as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('occupation_sub_type', $enquiry->occupation_sub_type ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Company Name --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Company Name <small class="text-muted">(Optional)</small>
                                            </label>
                                            <input type="text" name="company_name" class="form-control"
                                                value="{{ old('company_name', $enquiry->company_name ?? '') }}">
                                        </div>

                                        {{-- D.O.B. --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                D.O.B. <small class="text-muted">(Optional)</small>
                                            </label>
                                            <input type="text" id="dob" name="dob" class="form-control"
                                                value="{{ old('dob', $enquiry->dob ?? '') }}" placeholder="Select D.O.B.">
                                        </div>

                                        {{-- Marital Status --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Marital Status</label>
                                            <select name="marital_status" class="form-control form-select">
                                                <option value="">Select Marital Status</option>
                                                @foreach ($marital_statuses as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('marital_status', $enquiry->marital_status ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Date of Marriage --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Date of Marriage <small class="text-muted">(Optional)</small>
                                            </label>
                                            <input type="text" id="marriage_date" name="marriage_date"
                                                class="form-control"
                                                value="{{ old('marriage_date', $enquiry->marriage_date ?? '') }}"
                                                placeholder="Select Marriage Date">
                                        </div>

                                        {{-- Age Group --}}
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">
                                                Age Group <small class="text-muted">(Optional)</small>
                                            </label>
                                            <select name="age_group" class="form-control form-select">
                                                <option value="">Select Age Group</option>
                                                @foreach ($age_groups as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('age_group', $enquiry->age_group ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== LONG ENQUIRY Purchase Details =========================== --}}

                                <h3 class="mb-0 ms-3">Purchase Details</h3>

                                <div class="card-body">
                                    <div class="row">
                                        <div class="row d-none" id="bevSection">
                                            <div class="col-md-4 mb-3">
                                                <label>Do you have an EV?</label>
                                                <div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="has_ev"
                                                            value="Yes"
                                                            {{ old('has_ev', $enquiry->has_ev ?? '') == 'Yes' ? 'checked' : '' }}>
                                                        <label class="form-check-label">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="has_ev"
                                                            value="No"
                                                            {{ old('has_ev', $enquiry->has_ev ?? '') == 'No' ? 'checked' : '' }}>
                                                        <label class="form-check-label">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Purchase Type --}}
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">
                                                Purchase Type <small class="text-muted">(Optional)</small>
                                            </label>
                                            <select name="purchase_type" id="purchase_type" class="form-control form-select"
                                                style="width: auto; display: inline-block;">
                                                <option value="">Select Purchase Type</option>
                                                <option value="First Time Buy"
                                                    {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'First Time Buy' ? 'selected' : '' }}>
                                                    First Time Buy</option>
                                                <option value="Exchange Buy"
                                                    {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'Exchange Buy' ? 'selected' : '' }}>
                                                    Exchange Buy</option>
                                                <option value="Additional Buy"
                                                    {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'Additional Buy' ? 'selected' : '' }}>
                                                    Additional Buy</option>
                                                <option value="Scrappage"
                                                    {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'Scrappage' ? 'selected' : '' }}>
                                                    Scrappage</option>
                                            </select>
                                        </div>

                                        <div class="row d-none bg-light p-3 rounded" id="exchangeFields">
                                            {{-- Make --}}
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Make</label>
                                                <select id="brand_make" name="brand_make" class="form-control form-select">
                                                    <option value="">Select Make</option>
                                                    @foreach ($existing_car_oems as $item)
                                                        <option value="{{ $item['code'] }}"
                                                            {{ old('brand_make', $enquiry->brand_make ?? '') == $item['code'] ? 'selected' : '' }}>
                                                            {{ $item['value'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            {{-- Model --}}
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Model</label>
                                                <input type="text" id="brand_model" name="brand_model"
                                                    class="form-control"
                                                    value="{{ old('brand_model', $enquiry->brand_model ?? '') }}">
                                            </div>

                                            {{-- Vehicle Registration No. --}}
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Vehicle Registration No.</label>
                                                <input type="text" id="vehicle_no" name="vehicle_no" class="form-control"
                                                    value="{{ old('vehicle_no', $enquiry->vehicle_no ?? '') }}">
                                            </div>

                                            {{-- Added Exchange Fields --}}
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Vehicle Manufacturing Year</label>
                                                <input type="number" name="make_year" id="make_year" class="form-control"
                                                    value="{{ old('make_year', $enquiry->make_year ?? '') }}">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Vehicle Odometer Reading</label>
                                                <input type="number" name="odo_reading" id="odo_reading"
                                                    class="form-control"
                                                    value="{{ old('odo_reading', $enquiry->odo_reading ?? '') }}">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Used Vehicle Expected Price</label>
                                                <input type="number" name="expected_price" id="expected_price"
                                                    class="form-control"
                                                    value="{{ old('expected_price', $enquiry->expected_price ?? '') }}">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Used Vehicle Offered Price</label>
                                                <input type="number" name="offered_price" id="offered_price"
                                                    class="form-control"
                                                    value="{{ old('offered_price', $enquiry->offered_price ?? '') }}">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">New Vehicle Exchange Bonus</label>
                                                <input type="number" name="exchange_bonus" id="exchange_bonus"
                                                    class="form-control"
                                                    value="{{ old('exchange_bonus', $enquiry->exchange_bonus ?? '') }}">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Price Gap</label>
                                                <input type="text" id="difference" class="form-control"
                                                    value="{{ ($enquiry->expected_price ?? 0) - ($enquiry->offered_price ?? 0) - ($enquiry->exchange_bonus ?? 0) }}"
                                                    readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== FINANCE DETAILS =========================== --}}
                                <h3 class="mb-0 ms-3 mt-4">Finance Details</h3>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Finance Mode <span class="text-danger">*</span></label>
                                            <select name="fin_mode" id="fin_mode" class="form-control form-select">
                                                <option value="" disabled selected>-- Select Finance Mode --</option>
                                                <option value="In-house"
                                                    {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'In-house' ? 'selected' : '' }}>
                                                    In-house</option>
                                                <option value="Customer Self"
                                                    {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Customer Self' ? 'selected' : '' }}>
                                                    Customer Self</option>
                                                <option value="Cash"
                                                    {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Cash' ? 'selected' : '' }}>
                                                    Cash</option>
                                                <option value="Yet To Decide"
                                                    {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Yet To Decide' ? 'selected' : '' }}>
                                                    Yet To Decide</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3" id="financierbox" style="display:none;">
                                            <label class="form-label">Financier <span class="text-danger">*</span></label>
                                            <select name="financier" id="financier" class="form-control form-select">
                                                <option value="">Select Financier</option>
                                                @foreach ($financiers ?? [] as $financier)
                                                    <option value="{{ $financier->id }}"
                                                        {{ old('financier', $enquiry->financier ?? '') == $financier->id ? 'selected' : '' }}>
                                                        {{ $financier->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================== CONSIDERATION SET =========================== --}}
                                <h3 class="mb-0 ms-3">Consideration Set</h3>
                                <div class="card-body">
                                    <div class="row">
                                        {{-- Consideration Brand --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Consideration Set - Brand</label>
                                            <select id="consider_make" name="consider_make" class="form-control form-select">
                                                <option value="No Consideration"
                                                    {{ old('consider_make', $enquiry->consider_make ?? 'No Consideration') == 'No Consideration' ? 'selected' : '' }}>
                                                    No Consideration
                                                </option>
                                                @foreach ($existing_car_oems as $item)
                                                    <option value="{{ $item['code'] }}"
                                                        {{ old('consider_make', $enquiry->consider_make ?? '') == $item['code'] ? 'selected' : '' }}>
                                                        {{ $item['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Consideration Model --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Consideration Set - Model</label>
                                            <input type="text" id="consider_model" name="consider_model"
                                                class="form-control"
                                                value="{{ old('consider_model', $enquiry->consider_model ?? '') }}" disabled>
                                        </div>

                                        {{-- Consideration Variant --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Consideration Set - Variant</label>
                                            <input type="text" id="consider_variant" name="consider_variant"
                                                class="form-control"
                                                value="{{ old('consider_variant', $enquiry->consider_variant ?? '') }}"
                                                disabled>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Remarks for Non-Virtual --}}
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">
                                        Remarks <small class="text-muted">(Optional)</small>
                                    </label>
                                    <textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $enquiry->remarks ?? '') }}</textarea>
                                </div>

                                <div id="duplicateEnquiry" style="display:none;"></div>
                                
                            </div> {{-- End Full Enquiry Form wrapper --}}

                            {{-- =========================== FORM ACTIONS =========================== --}}
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="la la-save"></i>
                                        {{ isset($enquiry) ? 'Update Enquiry' : 'Save Enquiry' }}
                                    </button>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- UTILITY FUNCTIONS ---

        const debounce = (func, delay = 500) => {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        };

        function loadKeywordDropdown(keyword, parent, $target, placeholder = 'Select Option', selected = '') {
            if (!parent) return $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true);

            const url = "{{ route('admin.master.keyword-values', ['keyword' => '__K__', 'parent' => '__P__']) }}"
                .replace('__K__', encodeURIComponent(keyword))
                .replace('__P__', encodeURIComponent(parent));

            $.ajax({
                url: url,
                type: "GET",
                beforeSend: () => $target.html('<option>Loading...</option>').prop('disabled', true),
                success: (response) => {
                    let html = `<option value="">${placeholder}</option>`;
                    $.each(response, (_, item) => {
                        html +=
                            `<option value="${item.code}" ${selected == item.code ? 'selected' : ''}>${item.value}</option>`;
                    });
                    $target.html(html).prop('disabled', false);
                },
                error: () => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true)
            });
        }

        function fetchDropdown(url, $target, placeholder, selected = '', callback = null) {
            $target.html('<option value="">Loading...</option>').prop('disabled', true);

            $.get(url)
                .done((response) => {
                    let html = `<option value="">${placeholder}</option>`;
                    $.each(response, (code, item) => {
                        let val = typeof item === 'object' ? item.name : item;
                        let attrs = typeof item === 'object' ?
                            `data-fuel="${item.fuel_type || ''}" data-fuel-id="${item.fuel_type_id || ''}" data-transmission="${item.transmission || ''}" data-drivetrain="${item.drivetrain || ''}" data-seating="${item.seating || ''}"` :
                            '';

                        html +=
                            `<option value="${code}" ${selected == code ? 'selected' : ''} ${attrs}>${val}</option>`;
                    });
                    $target.html(html).prop('disabled', false);
                    if (callback) callback();
                })
                .fail(() => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true));
        }

        // --- STATE OBJECT ---
        const currentEnquiry = {
            isEdit: @json(isset($enquiry)),
            enquiryType: @json(old('enquiry_type', $enquiry->enquiry_type ?? '')),
            source: @json(old('source_code', $enquiry->source_code ?? '')),
            subSource: @json(old('sub_source', $enquiry->sub_source ?? '')),
            segment: @json(old('segment_code', $enquiry->segment_code ?? '')),
            model: @json(old('model_code', $enquiry->model_code ?? '')),
            variant: @json(old('variant_code', $enquiry->variant_code ?? '')),
            color: @json(old('color_code', $enquiry->color_code ?? '')),
            dealerBranch: @json(old('dealer_branch', $enquiry->dealer_branch ?? '')),
            dealerLocation: @json(old('dealer_location', $enquiry->dealer_location ?? '')),
            applicationType: @json(old('application_type', $enquiry->application_type ?? '')),
            application: @json(old('application', $enquiry->application ?? '')),
            personCode: @json(old('person_code', $enquiry->person_code ?? ''))
        };

        $(function() {
            
            // --- VIRTUAL ENQUIRY TOGGLE LOGIC ---
            const isVirtual = @json($isVirtual);
            if (isVirtual && $('#call_nature').length > 0) {
                const $fullForm = $('#full_enquiry_form');
                
                function toggleVirtualForm() {
                    const text = $('#call_nature').find('option:selected').text().trim().toUpperCase();
                    if (text === 'SALES') {
                        $fullForm.show();
                        $fullForm.find('input, select, textarea').prop('disabled', false);
                        
                        // Trigger changes to re-apply any local disabled states
                        setTimeout(() => {
                            $('#segment_code').trigger('change');
                            $('#source_code').trigger('change');
                            $('#purchase_type').trigger('change');
                            $('#referred_by').trigger('change');
                        }, 50);
                    } else {
                        $fullForm.hide();
                        $fullForm.find('input, select, textarea').prop('disabled', true);
                    }
                }
                
                $('#call_nature').on('change', toggleVirtualForm);
                toggleVirtualForm(); 
            }
            
            // --- DOM CACHE ---
            const $segmentCode = $('#segment_code');
            const $modelCode = $('#model_code');
            const $variantCode = $('#variant_code');
            const $colorCode = $('#color_code');
            const $sourceCode = $('#source_code');
            const $subSource = $('#sub_source');
            const $plannedCampaign = $('#planned_campaign');
            const $purchaseType = $('#purchase_type');

            /* Initial State */
            $modelCode.add($variantCode).add($colorCode).add($plannedCampaign).add($sourceCode).add($subSource).add(
                '#application').prop('disabled', true);
            $('#bevSection, #commercialSection, #exchangeFields')
                .hide();

            /* Date Pickers */
            let maxDob = new Date();
            maxDob.setFullYear(maxDob.getFullYear() - 18);

            flatpickr("#dob", {
                dateFormat: "Y-m-d",
                maxDate: maxDob,
                allowInput: false
            });
            flatpickr("#followup_date", {
                dateFormat: "Y-m-d",
                minDate: "today",
                allowInput: false
            });
            flatpickr("#marriage_date", {
                dateFormat: "Y-m-d",
                maxDate: "today",
                allowInput: false
            });

            /* Logic Handlers */

            // Finance Mode
            $('#fin_mode').on('change', function() {
                const isInHouse = $(this).val() === 'In-house';
                $('#financierbox').toggle(isInHouse);
                $('#financier').prop('required', isInHouse).val(isInHouse ? $('#financier').val() : '');
            }).trigger('change');

            // Price Gap Calculation
            $('#expected_price, #offered_price, #exchange_bonus').on('input', function() {
                const expected = parseFloat($('#expected_price').val()) || 0;
                const offered = parseFloat($('#offered_price').val()) || 0;
                const bonus = parseFloat($('#exchange_bonus').val()) || 0;
                $('#difference').val(Math.round(expected - offered - bonus));
            });

            // Consideration Set
            $('#consider_make').on('change', function() {
                const isValid = $(this).val() && $(this).val() !== 'No Consideration';
                $('#consider_model, #consider_variant').prop('disabled', !isValid).val(isValid ? undefined :
                    '');
            }).trigger('change');

            // Enquiry Type
            $('#enquiry_type').on('change', function() {
                loadKeywordDropdown('ENQ_SOURCE', $(this).val(), $sourceCode, 'Select Enquiry Source',
                    currentEnquiry.source);
                $subSource.html('<option value="">Select Enquiry Sub Source</option>').prop('disabled',
                    true);
                if (currentEnquiry.isEdit) setTimeout(() => $sourceCode.trigger('change'), 300);
            });

            // Reference Toggle
            const toggleReferenceFields = () => {
                const isRef = $sourceCode.val() === 'REFERENCE';
                $('#referenceFields').toggleClass('d-none', !isRef);
                $('#referred_by, #referee_phone, #referee_name').prop('required', isRef).val(isRef ? undefined :
                    '');
            };

            // Enquiry Source
            $sourceCode.on('change', function() {
                const source = $(this).val();
                toggleReferenceFields();

                if (source === 'HYPERLOCAL') {
                    $subSource.prop('disabled', false);
                    loadKeywordDropdown('ENQUIRY_SUB_SOURCE', source, $subSource,
                        'Select Enquiry Sub Source', currentEnquiry.subSource);
                } else {
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop(
                        'disabled', true);
                }

                $plannedCampaign.prop('disabled', source !== 'ACTIVATIONS')
                    .prop('required', source === 'ACTIVATIONS')
                    .val(source === 'ACTIVATIONS' ? $plannedCampaign.val() : '');
            }).trigger('change');

            // Purchase Type & Exchange Logic
            $purchaseType.on('change', function(e) {
                const isExchange = ['Exchange Buy', 'Additional Buy', 'Scrappage'].includes($(this).val());

                $('#exchangeFields').toggleClass('d-none', !isExchange).toggleClass('d-flex', isExchange);

                const $exchangeInputs = $(
                    '#brand_make, #brand_model, #vehicle_no, #make_year, #odo_reading, #expected_price, #offered_price, #exchange_bonus'
                    );

                $exchangeInputs.prop('required', isExchange);

                if (!isExchange && e.originalEvent) {
                    $exchangeInputs.val('');
                    $('#difference').val('');
                }
            }).trigger('change');

            // Application Type
            $('#application_type').on('change', function() {
                loadKeywordDropdown('APPLICATION', $(this).val(), $('#application'), 'Select Application',
                    currentEnquiry.application);
            }).trigger('change');

            // --- CASCADING DROPDOWNS ---

            // Dealer Branch -> Location
            $(document).on('change', '#dealer_branch', function() {
                fetchDropdown("{{ backpack_url('enquiry/locations') }}/" + $(this).val(), $(
                        '#dealer_location'), 'Select Dealer Location', currentEnquiry.isEdit ?
                    currentEnquiry.dealerLocation : '');
            });

            // Segment -> Model
            $segmentCode.on('change', function() {
                const segmentCode = $(this).val();
                const segmentText = $(this).find('option:selected').text().trim().toUpperCase();

                checkDuplicateEnquiry();

                $('#bevSection').toggle(segmentText === 'BEV');
                const isCommercial = ['LMM', 'COMMERCIAL'].includes(segmentText);
                $('#commercialSection').toggle(isCommercial);
                if (!isCommercial) $('#commercialSection').find('select,input').val('').prop('required',
                    false);

                // Reset downstream
                $modelCode.add($variantCode).add($colorCode).html('<option value="">Select Option</option>')
                    .prop('disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');

                if (segmentCode) {
                    fetchDropdown("{{ backpack_url('enquiry/models') }}/" + segmentCode, $modelCode,
                        'Select Model', currentEnquiry.isEdit ? currentEnquiry.model : '', () => {
                            if (currentEnquiry.isEdit && currentEnquiry.model) $modelCode.trigger(
                                'change');
                        });
                }
            });

            // Model -> Variant
            $modelCode.on('change', function() {
                const modelCode = $(this).val();

                // Reset downstream
                $variantCode.add($colorCode).html('<option value="">Select Option</option>').prop(
                    'disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');

                if (modelCode) {
                    fetchDropdown("{{ backpack_url('enquiry/variants') }}/" + modelCode, $variantCode,
                        'Select Variant', currentEnquiry.isEdit ? currentEnquiry.variant : '', () => {
                            if (currentEnquiry.isEdit && currentEnquiry.variant) $variantCode.trigger(
                                'change');
                        });
                }
            });

            // Variant -> Color & Auto-fill
            $variantCode.on('change', function() {
                const variantCode = $(this).val();
                const $selected = $(this).find(':selected');

                // Auto Fill Attributes
                $('#fuel_type').val($selected.data('fuel') || '');
                $('#fuel_type_id').val($selected.data('fuel-id') || '');
                $('#transmission').val($selected.data('transmission') || '');
                $('#drivetrain').val($selected.data('drivetrain') || '');
                $('#seating').val($selected.data('seating') || '');

                $colorCode.html('<option value="">Loading...</option>').prop('disabled', true);

                if (variantCode) {
                    fetchDropdown("{{ backpack_url('enquiry/colors') }}/" + variantCode, $colorCode,
                        'Select Color', currentEnquiry.isEdit ? currentEnquiry.color : '');
                }
            });

            // --- AJAX LOOKUPS ---

            // Reference Users Lookup (Debounced)
            $('#referee_phone').on('keyup change', debounce(function() {
                const mobile = $(this).val();
                const type = $('#referred_by').val();

                if (mobile.length !== 10 || !type) return;

                $.get("{{ route('enquiry.reference-users') }}", {
                    type,
                    mobile
                }, function(response) {
                    const isEmpty = $.isEmptyObject(response);

                    $('#referee_name_dropdown').toggle(!isEmpty);
                    $('#referee_name_manual').toggle(isEmpty);

                    if (isEmpty) {
                        $('#person_code').html('<option value="">Select Name</option>');
                    } else {
                        let html = '<option value="">Select Name</option>';
                        $.each(response, (code, name) => html +=
                            `<option value="${code}">${name}</option>`);
                        $('#person_code').html(html).trigger('change');
                    }
                });
            }));

            $('#person_code').on('change', function() {
                $('#referee_name').val($(this).val() ? $(this).find('option:selected').text() : '');
            });

            $('#referee_name_manual_input').on('input', function() {
                $('#referee_name').val($(this).val());
            });

            // Duplicate Check (Debounced)
            const checkDuplicateEnquiry = debounce(() => {
                if (currentEnquiry.isEdit) return;

                const mobile = $('#mobile').val();
                const segment = $segmentCode.val();

                if (mobile.length !== 10 || !segment) return;

                $.get("{{ route('enquiry.check-duplicate') }}", {
                    mobile,
                    segment_code: segment
                }, function(response) {
                    if (response.exists) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Duplicate Enquiry',
                            html: `Enquiry No. : <b>${response.enquiry_no}</b>`
                        });
                    }
                });
            });

            $('#mobile').on('input', checkDuplicateEnquiry);
            
            // Zipcode Lookup (Debounced)
            $('#zipcode').on('input blur', debounce(function() {
                const pincode = $(this).val();
                const $geoFields = $('#tehsil, #district, #city');

                if (pincode.length !== 6) {
                    $geoFields.val('').prop('readonly', false);
                    return;
                }

                $.get("{{ route('enquiry.location-by-pincode') }}", {
                    pincode
                }, function(response) {
                    if (response.success) {
                        $('#tehsil').val(response.tehsil);
                        $('#district').val(response.district);
                        $('#city').val(response.city);
                        $geoFields.prop('readonly', true);
                    } else {
                        $geoFields.val('').prop('readonly', false);
                    }
                });
            }));

            /* Trigger Initial Load */
            $('#enquiry_type').trigger('change');
            if (currentEnquiry.isEdit && currentEnquiry.dealerBranch) {
                $('#dealer_branch').trigger('change');
            }
            $segmentCode.trigger('change');
        });
    </script>
@endpush