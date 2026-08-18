{{-- =========================== ENQUIRY FORM =========================== --}}

@extends(backpack_view('blank'))

@section('title', isset($enquiry) ? 'Edit Enquiry' : 'Add New Enquiry')

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .enquiry-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 2rem;
            background: #fff;
        }

        .enquiry-card .card-header {
            background: #fff;
            border-bottom: 1px solid #edf2f9;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 1.25rem 1.5rem;
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
        
        .section-sub-header {
            font-size: 1.1rem;
            font-weight: bold;
            border-bottom: 2px solid #edf2f9;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            margin-top: 1rem;
        }
    </style>
@endpush

@section('content')
    @php
        // Strict Virtual Enquiry Detection based on current_origin
        $isVirtual = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'VIRTUAL';
    @endphp

    <div class="container-fluid pb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h2 class="mb-0 fw-bold">
                {{ isset($enquiry) ? 'Edit Enquiry : XENQ-'.$enquiry->id : 'Add New Enquiry' }}
            </h2>
        </div>

        <form method="POST" id="enquiryForm" action="{{ isset($enquiry) ? backpack_url('enquiry/' . $enquiry->id) : backpack_url('enquiry') }}" enctype="multipart/form-data">
            @csrf
            @if (isset($enquiry))
                @method('PUT')
            @endif

            {{-- =========================== ENQUIRY PLATFORM DETAILS (EDIT ONLY) =========================== --}}
            @if(isset($enquiry) && !$isVirtual)
            <div class="card enquiry-card">
                <div class="card-header"><h4 class="mb-0 fw-bold">Enquiry Platform Details</h4></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle" style="background-color: #e9ecef;">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Enquiry Platform</th>
                                    <th>Enquiry No.</th>
                                    <th>Enquiry Date</th>
                                    <th>Assignment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold align-middle bg-white text-start px-3">OEM Quick</td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->quick_enquiry_no ?: '—' }}</div></td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->quick_enquiry_date ? \Carbon\Carbon::parse($enquiry->quick_enquiry_date)->format('d-M-Y') : '—' }}</div></td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->quick_enq_assign_date ? \Carbon\Carbon::parse($enquiry->quick_enq_assign_date)->format('d-M-Y') : '—' }}</div></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold align-middle bg-white text-start px-3">OEM Long</td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->enquiry_no ?: '—' }}</div></td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->enquiry_date ? \Carbon\Carbon::parse($enquiry->enquiry_date)->format('d-M-Y') : '—' }}</div></td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->enq_assign_date ? \Carbon\Carbon::parse($enquiry->enq_assign_date)->format('d-M-Y') : '—' }}</div></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold align-middle bg-white text-start px-3">Xceler8</td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ 'XENQ-'.$enquiry->id }}</div></td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->created_at ? \Carbon\Carbon::parse($enquiry->created_at)->format('d-M-Y') : '—' }}</div></td>
                                    <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry->enq_assign_date ? \Carbon\Carbon::parse($enquiry->enq_assign_date)->format('d-M-Y') : '—' }}</div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- =========================== VIRTUAL CALL DETAILS =========================== --}}
            @if($isVirtual)
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Virtual Call Details</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Virtual Number</label>
                                <input type="text" name="virtual_no" class="form-control" value="{{ $enquiry->virtual_no ?? '' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Call Date</label>
                                <input type="text" name="virtual_call_date" class="form-control" value="{{ !empty($enquiry->virtual_call_date) ? \Carbon\Carbon::parse($enquiry->virtual_call_date)->format('d-M-Y H:i') : '' }}" readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Call Duration</label>
                                <input type="text" name="call_duration" class="form-control" value="{{ $enquiry->call_duration ?? '' }}" readonly>
                            </div>
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
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Remarks <small class="text-muted">(Optional)</small></label>
                                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $enquiry->remarks ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- WRAPPER FOR FULL FORM --}}
            <div id="full_enquiry_form" style="{{ $isVirtual ? 'display: none;' : '' }}">

                {{-- =========================== CUSTOMER INFORMATION =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Customer Information</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Segment <span class="text-danger">*</span></label>
                                <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                    <option value="">Select Segment</option>
                                    @foreach ($segments as $code => $name)
                                        <option value="{{ $code }}" {{ old('segment_code', $enquiry->segment_code ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Model <span class="text-danger">*</span></label>
                                <select name="model_code" id="model_code" class="form-control form-select" required>
                                    <option value="">Select Model</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Variant <span class="text-danger">*</span></label>
                                <select name="variant_code" id="variant_code" class="form-control form-select" required>
                                    <option value="">Select Variant</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $enquiry->first_name ?? '') }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $enquiry->last_name ?? '') }}">
                            </div>
                            @if(!$isVirtual)
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" id="mobile" name="mobile" maxlength="10" class="form-control" value="{{ old('mobile', $enquiry->mobile ?? '') }}" required>
                            </div>
                            @endif
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email ID <small class="text-muted">(Optional)</small></label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $enquiry->email ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender <small class="text-muted">(Optional)</small></label>
                                <select name="gender" class="form-control form-select">
                                    <option value="">Select Gender</option>
                                    @foreach ($genders as $item)
                                        <option value="{{ $item['code'] }}" {{ old('gender', $enquiry->gender ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== ENQUIRY INFORMATION =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Enquiry Information</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Type <span class="text-danger">*</span></label>
                                <select name="enquiry_type" id="enquiry_type" class="form-control form-select" required>
                                    <option value="">Select Enquiry Type</option>
                                    @foreach ($enquiry_types as $etype)
                                        <option value="{{ $etype['code'] }}" {{ old('enquiry_type', $enquiry->enquiry_type ?? '') == $etype['code'] ? 'selected' : '' }}>{{ $etype['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Source <span class="text-danger">*</span></label>
                                <select name="source_code" id="source_code" class="form-control form-select" required>
                                    <option value="">Select Enquiry Source</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Sub Source <span class="text-danger">*</span></label>
                                <select name="sub_source" id="sub_source" class="form-control form-select" disabled>
                                    <option value="">Select Enquiry Sub Source</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Planned Campaign</label>
                                <select name="planned_campaign" id="planned_campaign" class="form-control form-select">
                                    <option value="">Select Planned Campaign</option>
                                    @foreach ($campaigns as $name)
                                        <option value="{{ $name }}" {{ old('planned_campaign', $enquiry->planned_campaign ?? '') == $name ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row d-none w-100 m-0 p-0" id="referenceFields">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Referred By <span class="text-danger">*</span></label>
                                    <select name="referred_by" id="referred_by" class="form-control form-select">
                                        <option value="">Select Referred By</option>
                                        <option value="Customer" {{ old('referred_by', $enquiry->referred_by ?? '') == 'Customer' ? 'selected' : '' }}>Customer</option>
                                        <option value="Team Member" {{ old('referred_by', $enquiry->referred_by ?? '') == 'Team Member' ? 'selected' : '' }}>Team Member</option>
                                        <option value="Promoter" {{ old('referred_by', $enquiry->referred_by ?? '') == 'Promoter' ? 'selected' : '' }}>Promoter</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Referee Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="referee_phone" id="referee_phone" class="form-control" maxlength="10" value="{{ old('referee_phone', $enquiry->referee_phone ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Referee Name <span class="text-danger">*</span></label>
                                    <input type="text" name="referee_name" id="referee_name" class="form-control" value="{{ old('referee_name', $enquiry->referee_name ?? '') }}">
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Likely Purchase In Days <span class="text-danger">*</span></label>
                                <select name="likely_purchase_date" class="form-control form-select">
                                    <option value="">Select Likely Purchase In Days</option>
                                    @foreach ($likely_purchase_dates as $item)
                                        <option value="{{ $item['code'] }}" {{ old('likely_purchase_date', $enquiry->likely_purchase_date ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== VEHICLE DETAILS =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Vehicle Details</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Color <span class="text-danger">*</span></label>
                                <select name="color_code" id="color_code" class="form-control form-select" required>
                                    <option value="">Select Color</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fuel Type <span class="text-danger">*</span></label>
                                <input type="text" id="fuel_type" class="form-control" readonly>
                                <input type="hidden" id="fuel_type_id" name="fuel_type">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Transmission <span class="text-danger">*</span></label>
                                <input type="text" id="transmission" name="transmission" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Drivetrain <span class="text-danger">*</span></label>
                                <input type="text" id="drivetrain" name="drivetrain" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Seating <span class="text-danger">*</span></label>
                                <input type="text" id="seating" name="seating" class="form-control" readonly>
                            </div>

                            {{-- Commercial/LMM Only --}}
                            <div class="row w-100 m-0 p-0" id="commercialSection">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Usage Area</label>
                                    <select name="usage_area" class="form-control form-select">
                                        <option value="">Select Usage Area</option>
                                        @foreach ($usage_areas as $item)
                                            <option value="{{ $item['code'] }}" {{ old('usage_area', $enquiry->usage_area ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">KM Travelled Daily</label>
                                    <select name="km_travelled_daily" class="form-control form-select">
                                        <option value="">Select KM Travelled Daily</option>
                                        @foreach ($km_travelled_daily as $item)
                                            <option value="{{ $item['code'] }}" {{ old('km_travelled_daily', $enquiry->km_travelled_daily ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application Type</label>
                                    <select name="application_type" id="application_type" class="form-control form-select">
                                        <option value="">Select Application Type</option>
                                        @foreach ($application_types as $item)
                                            <option value="{{ $item['code'] }}" {{ old('application_type', $enquiry->application_type ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application</label>
                                    <select name="application" id="application" class="form-control form-select">
                                        <option value="">Select Application</option>
                                        @foreach ($applications as $item)
                                            <option value="{{ $item['code'] }}" {{ old('application', $enquiry->application ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== CUSTOMER ADDRESS DETAILS =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Customer Address Details</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Pin Code <small class="text-muted">(Optional)</small></label>
                                <input type="text" id="zipcode" name="zipcode" maxlength="6" class="form-control" value="{{ old('zipcode', $enquiry->zipcode ?? '') }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">VPO <small class="text-muted">(Optional)</small></label>
                                <select id="vpo_select" class="form-control form-select"><option value="">Select VPO</option></select>
                                <input type="text" id="vpo_input" class="form-control mt-2 d-none" placeholder="Enter VPO Manually" value="{{ $enquiry->vpo ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Tehsil <span class="text-danger">*</span></label>
                                <select id="tehsil_select" class="form-control form-select" required><option value="">Select Tehsil</option></select>
                                <input type="text" id="tehsil_input" class="form-control mt-2 d-none" placeholder="Enter Tehsil Manually" value="{{ $enquiry->tehsil ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">District <span class="text-danger">*</span></label>
                                <select id="district_select" class="form-control form-select" required><option value="">Select District</option></select>
                                <input type="text" id="district_input" class="form-control mt-2 d-none" placeholder="Enter District Manually" value="{{ $enquiry->district ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">State <small class="text-muted">(Optional)</small></label>
                                <select id="state_select" class="form-control form-select"><option value="">Select State</option></select>
                                <input type="text" id="state_input" class="form-control mt-2 d-none" placeholder="Enter State Manually" value="{{ $enquiry->city ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Territory <small class="text-muted">(Optional)</small></label>
                                <select id="territory" name="territory" class="form-control form-select">
                                    <option value="">Select Territory</option>
                                    <option value="OWN TERRITORY" {{ old('territory', $enquiry->territory ?? '') == 'OWN TERRITORY' ? 'selected' : '' }}>OWN TERRITORY</option>
                                    <option value="OTHER TERRITORY" {{ old('territory', $enquiry->territory ?? '') == 'OTHER TERRITORY' ? 'selected' : '' }}>OTHER TERRITORY</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== DEALER DETAILS =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Dealer Details</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Select Dealer Branch <span class="text-danger">*</span></label>
                                <select name="dealer_branch" id="dealer_branch" class="form-control form-select" required>
                                    <option value="">Select Dealer Branch</option>
                                    @foreach ($branches as $code => $name)
                                        <option value="{{ $code }}" {{ old('dealer_branch', $enquiry->dealer_branch ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Select Dealer Location <span class="text-danger">*</span></label>
                                <select name="dealer_location" id="dealer_location" class="form-control form-select" required>
                                    <option value="">Select Dealer Location</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Select SC <span class="text-danger">*</span></label>
                                <select name="sc_code" class="form-control form-select" required>
                                    <option value="">Select Sales Consultant</option>
                                    @foreach ($saleconsultants as $consultant)
                                        <option value="{{ $consultant['person_code'] }}" {{ old('sc_code', $enquiry->sc_code ?? '') == $consultant['person_code'] ? 'selected' : '' }}>
                                            {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== LONG ENQUIRY CUSTOMER DETAILS =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Long Enquiry - Customer Details</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Occupation Type</label>
                                <select name="occupation_type" class="form-control form-select">
                                    <option value="">Select Occupation Type</option>
                                    @foreach ($occupation_types as $item)
                                        <option value="{{ $item['code'] }}" {{ old('occupation_type', $enquiry->occupation_type ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer Type <small class="text-muted">(Optional)</small></label>
                                <select name="customer_type" class="form-control form-select">
                                    <option value="">Select Customer Type</option>
                                    @foreach ($customer_types as $item)
                                        <option value="{{ $item['code'] }}" {{ old('customer_type', $enquiry->customer_type ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Occupation Sub Type <small class="text-muted">(Optional)</small></label>
                                <select name="occupation_sub_type" class="form-control form-select">
                                    <option value="">Select Occupation Sub Type</option>
                                    @foreach ($occupation_sub_types as $item)
                                        <option value="{{ $item['code'] }}" {{ old('occupation_sub_type', $enquiry->occupation_sub_type ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Company Name <small class="text-muted">(Optional)</small></label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $enquiry->company_name ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">D.O.B. <small class="text-muted">(Optional)</small></label>
                                <input type="text" id="dob" name="dob" class="form-control" value="{{ old('dob', $enquiry->dob ?? '') }}" placeholder="Select D.O.B.">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-control form-select">
                                    <option value="">Select Marital Status</option>
                                    @foreach ($marital_statuses as $item)
                                        <option value="{{ $item['code'] }}" {{ old('marital_status', $enquiry->marital_status ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Marriage <small class="text-muted">(Optional)</small></label>
                                <input type="text" id="marriage_date" name="marriage_date" class="form-control" value="{{ old('marriage_date', $enquiry->marriage_date ?? '') }}" placeholder="Select Marriage Date">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age Group <small class="text-muted">(Optional)</small></label>
                                <select name="age_group" class="form-control form-select">
                                    <option value="">Select Age Group</option>
                                    @foreach ($age_groups as $item)
                                        <option value="{{ $item['code'] }}" {{ old('age_group', $enquiry->age_group ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== ADDITIONAL DETAILS =========================== --}}
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Additional Details</h4></div>
                    <div class="card-body">
                        
                        {{-- CRE Purchase Details --}}
                        <h4 class="mb-3">CRE - Purchase Details</h4>
                        <div class="row mb-4">
                            <div class="row d-none w-100 m-0 p-0" id="bevSection">
                                <div class="col-md-4 mb-3">
                                    <label>Do you have an EV?</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="has_ev" value="Yes" {{ old('has_ev', $enquiry->has_ev ?? '') == 'Yes' ? 'checked' : '' }}>
                                            <label class="form-check-label">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="has_ev" value="No" {{ old('has_ev', $enquiry->has_ev ?? '') == 'No' ? 'checked' : '' }}>
                                            <label class="form-check-label">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Purchase Type <small class="text-muted">(enq dump)</small></label>
                                <select name="purchase_type" id="purchase_type" class="form-control form-select">
                                    <option value="">Select Purchase Type</option>
                                    <option value="First Time Buy" {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'First Time Buy' ? 'selected' : '' }}>First Time Buy</option>
                                    <option value="Exchange Buy" {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'Exchange Buy' ? 'selected' : '' }}>Exchange Buy</option>
                                    <option value="Additional Buy" {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'Additional Buy' ? 'selected' : '' }}>Additional Buy</option>
                                    <option value="Scrappage" {{ old('purchase_type', $enquiry->purchase_type ?? '') == 'Scrappage' ? 'selected' : '' }}>Scrappage</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">CRM Purchase Type <small class="text-muted">(enq dump)</small></label>
                                <select name="purchase_type_crm" id="purchase_type_crm" class="form-control form-select">
                                    <option value="">Select CRM Purchase Type</option>
                                    <option value="First Time Buy" {{ old('purchase_type_crm', $enquiry->purchase_type_crm ?? '') == 'First Time Buy' ? 'selected' : '' }}>First Time Buy</option>
                                    <option value="Exchange Buy" {{ old('purchase_type_crm', $enquiry->purchase_type_crm ?? '') == 'Exchange Buy' ? 'selected' : '' }}>Exchange Buy</option>
                                    <option value="Additional Buy" {{ old('purchase_type_crm', $enquiry->purchase_type_crm ?? '') == 'Additional Buy' ? 'selected' : '' }}>Additional Buy</option>
                                    <option value="Scrappage" {{ old('purchase_type_crm', $enquiry->purchase_type_crm ?? '') == 'Scrappage' ? 'selected' : '' }}>Scrappage</option>
                                </select>
                            </div>

                            {{-- Readonly Data Display for Edit Mode (Processed by Exchange Team) --}}
                            @if(isset($enquiry) && in_array($enquiry->purchase_type, ['Exchange Buy', 'Scrappage']) && ($enquiry->brand_make || $enquiry->expected_price || $enquiry->lost_reason))
                            <div class="col-md-12 mt-3">
                                <div class="bg-light p-3 rounded border">
                                    <h6 class="mb-3 text-secondary">Exchange Valuation Details (Processed by Exchange Team)</h6>
                                    <div class="row">
                                        <div class="col-md-3 mb-3"><label class="form-label">Make:</label> <input class="form-control" disabled value="{{ $enquiry->brand_make }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Model:</label> <input class="form-control" disabled value="{{ $enquiry->brand_model }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Reg No:</label> <input class="form-control" disabled value="{{ $enquiry->vehicle_no }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Mfg Year:</label> <input class="form-control" disabled value="{{ $enquiry->make_year }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Odometer:</label> <input class="form-control" disabled value="{{ $enquiry->odo_reading }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Expected Price:</label> <input class="form-control" disabled value="{{ $enquiry->expected_price }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Offered Price:</label> <input class="form-control" disabled value="{{ $enquiry->offered_price }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Exchange Bonus:</label> <input class="form-control" disabled value="{{ $enquiry->exchange_bonus }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label text-danger">Reason for Case Lost:</label> <input class="form-control" disabled value="{{ $enquiry->lost_reason }}"></div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Finance Details --}}
                        <h4 class="mt-4 mb-3">Finance Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Finance Mode <span class="text-danger">*</span></label>
                                <select name="fin_mode" id="fin_mode" class="form-control form-select">
                                    <option value="" disabled selected>-- Select Finance Mode --</option>
                                    <option value="In-house" {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'In-house' ? 'selected' : '' }}>In-house</option>
                                    <option value="Customer Self" {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Customer Self' ? 'selected' : '' }}>Customer Self</option>
                                    <option value="Cash" {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="Yet To Decide" {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Yet To Decide' ? 'selected' : '' }}>Yet To Decide</option>
                                    <option value="Purchase Plan Cancelled" {{ old('fin_mode', $enquiry->fin_mode ?? '') == 'Purchase Plan Cancelled' ? 'selected' : '' }}>Purchase Plan Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="financierbox" style="display:none;">
                                <label class="form-label">Financier <small class="text-muted">(Optional)</small></label>
                                <select name="financier" id="financier" class="form-control form-select">
                                    <option value="">Select Financier</option>
                                    @foreach ($financiers ?? [] as $financier)
                                        <option value="{{ $financier->id }}" {{ old('financier', $enquiry->financier ?? '') == $financier->id ? 'selected' : '' }}>{{ $financier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Consideration Set --}}
                        <h4 class="mt-4 mb-3">Consideration Set</h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set - Brand</label>
                                <select id="consider_make" name="consider_make" class="form-control form-select">
                                    <option value="No Consideration" {{ old('consider_make', $enquiry->consider_make ?? 'No Consideration') == 'No Consideration' ? 'selected' : '' }}>No Consideration</option>
                                    @foreach ($existing_car_oems as $item)
                                        <option value="{{ $item['code'] }}" {{ old('consider_make', $enquiry->consider_make ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set - Model</label>
                                <input type="text" id="consider_model" name="consider_model" class="form-control" value="{{ old('consider_model', $enquiry->consider_model ?? '') }}" disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Consideration Set - Variant</label>
                                <input type="text" id="consider_variant" name="consider_variant" class="form-control" value="{{ old('consider_variant', $enquiry->consider_variant ?? '') }}" disabled>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- =========================== FOLLOW-UP DATA (HIDDEN ON CREATE, READ-ONLY ON EDIT) =========================== --}}
                @if(isset($enquiry))
                
                {{-- SC FOLLOW UP --}}
                <div class="card enquiry-card border">
                    <div class="card-header bg-light"><h4 class="mb-0 fw-bold">SC Follow Up (Read Only)</h4></div>
                    <div class="card-body">
                        
                        <h4 class="mb-3">Basic Follow-Up</h4>
                        @if(strtoupper($enquiry->current_origin ?? '') === 'LONG')
                            <div class="table-responsive">
                                <table class="table table-bordered text-center align-middle" style="background-color: #e9ecef;">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Fup Count</th>
                                            <th>Planned Date</th>
                                            <th>Actual Date</th>
                                            <th>Fup Status</th>
                                            <th>Deviation Stage</th>
                                            <th>Remarks</th>
                                            <th>Remarks Type</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($fups) && count($fups) > 0)
                                            @foreach($fups as $index => $fup)
                                            <tr>
                                                <td><div class="form-control bg-light h-auto border-0">{{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? ($index+1).'th' }} Fup</div></td>
                                                <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $fup?->planned_followup_date ? \Carbon\Carbon::parse($fup->planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $fup?->actual_followup_date ? \Carbon\Carbon::parse($fup->actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-light h-auto border-0">{{ $fup?->enquiry_status ?: '—' }}</div></td>
                                                <td><div class="form-control bg-light h-auto border-0">{{ $fup?->deviation_stage ?: '—' }}</div></td>
                                                <td><div class="form-control bg-light h-auto border-0 text-wrap text-start" style="min-width: 150px;">{{ $fup?->remarks ?: '—' }}</div></td>
                                                <td><div class="form-control bg-light h-auto border-0 text-wrap text-start" style="min-width: 120px;">{{ $fup?->remark_type ?: '—' }}</div></td>
                                                <td><div class="form-control bg-light h-auto border-0 text-wrap text-start" style="min-width: 150px;">{{ $fup?->comments ?: '—' }}</div></td>
                                            </tr>
                                            @endforeach
                                        @else
                                            <tr><td colspan="8" class="text-muted py-3 bg-white">No Follow-up Data Found</td></tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered text-center align-middle" style="background-color: #e9ecef;">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th></th>
                                            <th>Planned Date</th>
                                            <th>Actual Date</th>
                                            <th>Next Fup Date</th>
                                            <th>Status</th>
                                            <th>Follow Up Type</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold align-middle bg-white">First Fup</td>
                                            <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry?->first_planned_followup_date ? \Carbon\Carbon::parse($enquiry->first_planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry?->first_actual_followup_date ? \Carbon\Carbon::parse($enquiry->first_actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry?->next_planned_followup_date ? \Carbon\Carbon::parse($enquiry->next_planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0">{{ $enquiry?->stage ?: '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0">{{ $enquiry?->followup_type ?: '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0 text-wrap text-start" style="min-width: 200px;">{{ $enquiry?->remarks ?: '—' }}</div></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold align-middle bg-white">Recent Fup</td>
                                            <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry?->recent_planned_followup_date ? \Carbon\Carbon::parse($enquiry->recent_planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0 text-nowrap">{{ $enquiry?->recent_actual_followup_date ? \Carbon\Carbon::parse($enquiry->recent_actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0 text-nowrap">—</div></td>
                                            <td><div class="form-control bg-light h-auto border-0">{{ $enquiry?->quick_status ?? $enquiry?->stage ?? '—' }}</div></td>
                                            <td><div class="form-control bg-light h-auto border-0">—</div></td>
                                            <td><div class="form-control bg-light h-auto border-0 text-wrap text-start" style="min-width: 200px;">{{ $enquiry?->recent_fup_comments ?? $enquiry?->remarks ?? '—' }}</div></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <h4 class="mt-4 mb-3">Enquiry / Sales Progress</h4>
                        <div class="row" style="opacity: 0.8; pointer-events:none;">
                            <div class="col-md-3 mb-3"><label class="form-label">Enquiry Stage</label><input type="text" class="form-control" value="{{ $enquiry?->dms_enquiry_stage ?? $enquiry?->stage ?? '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Test Drive Count</label><input type="text" class="form-control" value="{{ $enquiry?->td_count ?? '0' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Test Drive No.</label><input type="text" class="form-control" value="{{ $enquiry?->test_drive_no ?? '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Test Drive Date</label><input type="text" class="form-control" value="{{ isset($enquiry) && $enquiry->td_date ? \Carbon\Carbon::parse($enquiry->td_date)->format('d-M-Y') : '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Booking Date</label><input type="text" class="form-control" value="{{ isset($enquiry) && ($enquiry->x8_booking_date || $enquiry->booking_date) ? \Carbon\Carbon::parse($enquiry->x8_booking_date ?? $enquiry->booking_date)->format('d-M-Y') : '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Booking No.</label><input type="text" class="form-control" value="{{ $enquiry?->x8_booking_no ?? $enquiry?->booking_no ?? '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">OTF No.</label><input type="text" class="form-control" value="{{ $enquiry?->oem_otf_no ?? '—' }}" readonly></div>
                        </div>

                        <h4 class="mt-4 mb-3">Lost Information</h4>
                        <div class="row" style="opacity: 0.8; pointer-events:none;">
                            <div class="col-md-3 mb-3"><label class="form-label">Lost Reason</label><input type="text" class="form-control" value="{{ $enquiry?->lost_reason ?? '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Lost Sub Reason</label><input type="text" class="form-control" value="{{ $enquiry?->lost_sub_reason ?? '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Lost Detail Reason</label><input type="text" class="form-control" value="{{ $enquiry?->lost_detail_reason ?? '—' }}" readonly></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Lost Remarks</label><input type="text" class="form-control" value="{{ $enquiry?->lost_remarks ?? '—' }}" readonly></div>
                        </div>
                    </div>
                </div>

                {{-- CRE FOLLOW UP --}}
                <div class="card enquiry-card border">
                    <div class="card-header bg-light"><h4 class="mb-0 fw-bold">CRE Follow Up (Editable)</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">CRE Enquiry Stage</label>
                                <input type="text" name="cre_enquiry_stage" class="form-control" value="{{ old('cre_enquiry_stage', $enquiry->cre_enquiry_stage ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Next CRE FUP Date</label>
                                <input type="text" id="cre_next_fup_date" name="cre_next_fup_date" class="form-control" value="{{ old('cre_next_fup_date', $enquiry->cre_next_fup_date ?? '') }}" placeholder="DD-MMM-YYYY">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Next CRE FUP Time</label>
                                <input type="time" name="cre_next_fup_time" class="form-control" value="{{ old('cre_next_fup_time', $enquiry->cre_next_fup_time ?? '') }}">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">CRE Follow Up Remarks</label>
                                <textarea name="cre_next_fup_remarks" class="form-control" rows="2">{{ old('cre_next_fup_remarks', $enquiry->cre_next_fup_remarks ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- =========================== REMARKS =========================== --}}
                @if(!$isVirtual)
                <div class="card enquiry-card">
                    <div class="card-header"><h4 class="mb-0 fw-bold">Remarks</h4></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">
                                    Additional Remarks <small class="text-muted">(Optional)</small>
                                </label>
                                <textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $enquiry?->remarks ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
            </div> 

            <div class="d-flex justify-content-center mt-3 mb-5">
                <button type="submit" class="btn btn-success btn-lg px-5 py-2 shadow-sm fw-bold">
                    {{ isset($enquiry) ? 'Update Enquiry' : 'Save Enquiry' }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const debounce = (func, delay = 500) => {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        };

        function loadKeywordDropdown(keyword, parent, $target, placeholder = 'Select Option', selected = '') {
            if (!parent) return $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true);
            const url = "{{ route('admin.master.keyword-values', ['keyword' => '__K__', 'parent' => '__P__']) }}".replace('__K__', encodeURIComponent(keyword)).replace('__P__', encodeURIComponent(parent));
            $.ajax({
                url: url, type: "GET",
                beforeSend: () => $target.html('<option>Loading...</option>').prop('disabled', true),
                success: (response) => {
                    let html = `<option value="">${placeholder}</option>`;
                    $.each(response, (_, item) => { html += `<option value="${item.code}" ${selected == item.code ? 'selected' : ''}>${item.value}</option>`; });
                    $target.html(html).prop('disabled', false);
                },
                error: () => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true)
            });
        }

        function fetchDropdown(url, $target, placeholder, selected = '', callback = null) {
            $target.html('<option value="">Loading...</option>').prop('disabled', true);
            $.get(url).done((response) => {
                let html = `<option value="">${placeholder}</option>`;
                $.each(response, (code, item) => {
                    let val = typeof item === 'object' ? item.name : item;
                    let attrs = typeof item === 'object' ? `data-fuel="${item.fuel_type || ''}" data-fuel-id="${item.fuel_type_id || ''}" data-transmission="${item.transmission || ''}" data-drivetrain="${item.drivetrain || ''}" data-seating="${item.seating || ''}"` : '';
                    html += `<option value="${code}" ${selected == code ? 'selected' : ''} ${attrs}>${val}</option>`;
                });
                $target.html(html).prop('disabled', false);
                if (callback) callback();
            }).fail(() => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true));
        }

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
            vpo: @json(old('vpo', $enquiry->vpo ?? '')),
            tehsil: @json(old('tehsil', $enquiry->tehsil ?? '')),
            district: @json(old('district', $enquiry->district ?? '')),
            city: @json(old('city', $enquiry->city ?? '')),
            territory: @json(old('territory', $enquiry->territory ?? ''))
        };

        $(function() {
            const isVirtual = @json($isVirtual);
            if (isVirtual && $('#call_nature').length > 0) {
                const $fullForm = $('#full_enquiry_form');
                function toggleVirtualForm() {
                    const text = $('#call_nature').find('option:selected').text().trim().toUpperCase();
                    if (text.includes('SALES')) {
                        $fullForm.show();
                        $fullForm.find('input, select, textarea').prop('disabled', false);
                        setTimeout(() => { $('#segment_code').trigger('change'); $('#source_code').trigger('change'); }, 50);
                    } else {
                        $fullForm.hide();
                        $fullForm.find('input, select, textarea').prop('disabled', true);
                    }
                }
                $('#call_nature').on('change', toggleVirtualForm);
                toggleVirtualForm(); 
            }
            
            const $segmentCode = $('#segment_code');
            const $modelCode = $('#model_code');
            const $variantCode = $('#variant_code');
            const $colorCode = $('#color_code');
            const $sourceCode = $('#source_code');
            const $subSource = $('#sub_source');
            const $plannedCampaign = $('#planned_campaign');

            let maxDob = new Date(); maxDob.setFullYear(maxDob.getFullYear() - 18);
            flatpickr("#dob", { dateFormat: "d-M-Y", maxDate: maxDob, allowInput: false });
            flatpickr("#cre_next_fup_date", { dateFormat: "d-M-Y", allowInput: false });
            flatpickr("#marriage_date", { dateFormat: "d-M-Y", maxDate: "today", allowInput: false });

            $('#fin_mode').on('change', function() {
                const isInHouse = $(this).val() === 'In-house';
                $('#financierbox').toggle(isInHouse);
                $('#financier').val(isInHouse ? $('#financier').val() : ''); 
            }).trigger('change');

            $('#consider_make').on('change', function() {
                const isValid = $(this).val() && $(this).val() !== 'No Consideration';
                $('#consider_model, #consider_variant').prop('disabled', !isValid).val(isValid ? undefined : '');
            }).trigger('change');

            $('#enquiry_type').on('change', function() {
                loadKeywordDropdown('ENQ_SOURCE', $(this).val(), $sourceCode, 'Select Enquiry Source', currentEnquiry.source);
                $subSource.html('<option value="">Select Enquiry Sub Source</option>').prop('disabled', true);
                if (currentEnquiry.isEdit) setTimeout(() => $sourceCode.trigger('change'), 300);
            });

            const toggleReferenceFields = () => {
                const isRef = $sourceCode.val() === 'REFERENCE';
                $('#referenceFields').toggleClass('d-none', !isRef).toggleClass('d-flex', isRef);
                $('#referred_by, #referee_phone, #referee_name').prop('required', isRef).val(isRef ? undefined : '');
            };

            $sourceCode.on('change', function() {
                const source = $(this).val();
                toggleReferenceFields();
                if (source === 'HYPERLOCAL') {
                    $subSource.prop('disabled', false);
                    loadKeywordDropdown('ENQUIRY_SUB_SOURCE', source, $subSource, 'Select Enquiry Sub Source', currentEnquiry.subSource);
                } else {
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop('disabled', true);
                }
                $plannedCampaign.prop('disabled', source !== 'ACTIVATIONS').prop('required', source === 'ACTIVATIONS').val(source === 'ACTIVATIONS' ? $plannedCampaign.val() : '');
            }).trigger('change');

            $('#application_type').on('change', function() {
                loadKeywordDropdown('APPLICATION', $(this).val(), $('#application'), 'Select Application', currentEnquiry.application);
            }).trigger('change');

            $('#dealer_branch').on('change', function() {
                fetchDropdown("{{ backpack_url('enquiry/locations') }}/" + $(this).val(), $('#dealer_location'), 'Select Dealer Location', currentEnquiry.isEdit ? currentEnquiry.dealerLocation : '');
            });

            $segmentCode.on('change', function() {
                const segmentCode = $(this).val();
                const segmentText = $(this).find('option:selected').text().trim().toUpperCase();
                checkDuplicateEnquiry();
                $('#bevSection').toggle(segmentText === 'BEV');
                const isCommercial = ['LMM', 'COMMERCIAL'].includes(segmentText);
                $('#commercialSection').toggle(isCommercial);
                if (!isCommercial) $('#commercialSection').find('select,input').val('').prop('required', false);
                $modelCode.add($variantCode).add($colorCode).html('<option value="">Select Option</option>').prop('disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');
                if (segmentCode) {
                    fetchDropdown("{{ backpack_url('enquiry/models') }}/" + segmentCode, $modelCode, 'Select Model', currentEnquiry.isEdit ? currentEnquiry.model : '', () => {
                        if (currentEnquiry.isEdit && currentEnquiry.model) $modelCode.trigger('change');
                    });
                }
            });

            $modelCode.on('change', function() {
                const modelCode = $(this).val();
                $variantCode.add($colorCode).html('<option value="">Select Option</option>').prop('disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');
                if (modelCode) {
                    fetchDropdown("{{ backpack_url('enquiry/variants') }}/" + modelCode, $variantCode, 'Select Variant', currentEnquiry.isEdit ? currentEnquiry.variant : '', () => {
                        if (currentEnquiry.isEdit && currentEnquiry.variant) $variantCode.trigger('change');
                    });
                }
            });

            $variantCode.on('change', function() {
                const variantCode = $(this).val();
                const $selected = $(this).find(':selected');
                $('#fuel_type').val($selected.data('fuel') || '');
                $('#fuel_type_id').val($selected.data('fuel-id') || '');
                $('#transmission').val($selected.data('transmission') || '');
                $('#drivetrain').val($selected.data('drivetrain') || '');
                $('#seating').val($selected.data('seating') || '');
                $colorCode.html('<option value="">Loading...</option>').prop('disabled', true);
                if (variantCode) {
                    fetchDropdown("{{ backpack_url('enquiry/colors') }}/" + variantCode, $colorCode, 'Select Color', currentEnquiry.isEdit ? currentEnquiry.color : '');
                }
            });

            const checkDuplicateEnquiry = debounce(() => {
                if (currentEnquiry.isEdit) return;
                const mobile = $('#mobile').val();
                const segment = $segmentCode.val();
                if (mobile.length !== 10 || !segment) return;
                $.get("{{ route('enquiry.check-duplicate') }}", { mobile, segment_code: segment }, function(response) {
                    if (response.exists) { Swal.fire({ icon: 'warning', title: 'Duplicate Enquiry', html: `Enquiry No. : <b>${response.enquiry_no}</b>` }); }
                });
            });
            $('#mobile').on('input', checkDuplicateEnquiry);

            function setupDynamicLocation(selectId, inputId, inputName) {
                const $select = $(`#${selectId}`);
                const $input = $(`#${inputId}`);
                $select.on('change', function() {
                    if ($(this).val() === 'OTHER') {
                        $input.removeClass('d-none').attr('name', inputName);
                        if($select.prop('required')) $input.prop('required', true);
                        $select.removeAttr('name');
                    } else {
                        $input.addClass('d-none').removeAttr('name').prop('required', false);
                        $select.attr('name', inputName);
                    }
                });
            }

            setupDynamicLocation('vpo_select', 'vpo_input', 'vpo');
            setupDynamicLocation('tehsil_select', 'tehsil_input', 'tehsil');
            setupDynamicLocation('district_select', 'district_input', 'district');
            setupDynamicLocation('state_select', 'state_input', 'city');
            
            $('#zipcode').on('input blur', debounce(function() {
                const pincode = $('#zipcode').val().trim();
                const $bpoSelect = $('#vpo_select');
                const $tehsilSelect = $('#tehsil_select');
                const $districtSelect = $('#district_select');
                const $citySelect = $('#state_select');
                const $territorySelect = $('#territory');

                if (pincode.length !== 6) {
                    $bpoSelect.html('<option value="">Select VPO</option>');
                    $tehsilSelect.html('<option value="">Select Tehsil</option>');
                    $districtSelect.html('<option value="">Select District</option>');
                    $citySelect.html('<option value="">Select State</option>');
                    return;
                }

                $bpoSelect.html('<option value="">Loading...</option>');
                $tehsilSelect.html('<option value="">Loading...</option>');
                $districtSelect.html('<option value="">Loading...</option>');
                $citySelect.html('<option value="">Loading...</option>');

                fetch(`https://api.postalpincode.in/pincode/${pincode}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data[0] && data[0].Status === 'Success') {
                            const postOffices = data[0].PostOffice;
                            let bpos = [], tehsils = [], districts = [], cities = [];
                            postOffices.forEach(po => {
                                if (po.Name && !bpos.includes(po.Name)) bpos.push(po.Name);
                                let block = (po.Block && po.Block !== "NA") ? po.Block : po.District;
                                if (block && !tehsils.includes(block)) tehsils.push(block);
                                if (po.District && !districts.includes(po.District)) districts.push(po.District);
                                if (po.State && !cities.includes(po.State)) cities.push(po.State); 
                            });

                            const buildOptions = (arr, placeholder, currentValue) => {
                                let html = `<option value="">${placeholder}</option>`;
                                let valueFound = false;
                                let matchedValue = null;
                                let safeCurrent = (currentValue || '').toString().trim().toLowerCase();

                                arr.forEach(val => {
                                    if (val.toString().trim().toLowerCase() === safeCurrent) {
                                        valueFound = true; matchedValue = val;
                                    }
                                });

                                if(!valueFound && safeCurrent.length > 0) {
                                    arr.forEach(val => {
                                        let safeVal = val.toString().trim().toLowerCase();
                                        if (safeVal.includes(safeCurrent) || safeCurrent.includes(safeVal)) {
                                            valueFound = true; matchedValue = val;
                                        }
                                    });
                                }

                                arr.forEach(val => {
                                    const selected = (valueFound && val === matchedValue) ? 'selected' : '';
                                    html += `<option value="${val}" ${selected}>${val}</option>`;
                                });
                                
                                const otherSelected = (!valueFound && currentValue) ? 'selected' : '';
                                html += `<option value="OTHER" ${otherSelected}>Other</option>`;
                                return { html, valueFound, matchedValue: (valueFound ? matchedValue : currentValue) };
                            };

                            const handleRender = (selectId, inputId, inputName, optionsArr, placeholder, currentValue) => {
                                const renderData = buildOptions(optionsArr, placeholder, currentValue);
                                $(`#${selectId}`).html(renderData.html);
                                if(!renderData.valueFound && currentValue) {
                                    $(`#${inputId}`).val(currentValue).removeClass('d-none').attr('name', inputName);
                                    if($(`#${selectId}`).prop('required')) $(`#${inputId}`).prop('required', true);
                                    $(`#${selectId}`).removeAttr('name');
                                } else {
                                    $(`#${inputId}`).addClass('d-none').removeAttr('name');
                                    $(`#${selectId}`).attr('name', inputName);
                                }
                            };

                            handleRender('vpo_select', 'vpo_input', 'vpo', bpos, 'Select VPO', currentEnquiry.vpo);
                            handleRender('tehsil_select', 'tehsil_input', 'tehsil', tehsils, 'Select Tehsil', currentEnquiry.tehsil);
                            handleRender('district_select', 'district_input', 'district', districts, 'Select District', currentEnquiry.district);
                            handleRender('state_select', 'state_input', 'city', cities, 'Select State', currentEnquiry.city);

                            if(districts.length > 0) {
                                const dist = districts[0].toUpperCase();
                                if(['BIKANER', 'CHURU', 'SUJANGARH'].includes(dist)) $territorySelect.val('OWN TERRITORY');
                                else $territorySelect.val('OTHER TERRITORY');
                            }
                            if (currentEnquiry.territory) $territorySelect.val(currentEnquiry.territory);

                        } else {
                            $bpoSelect.html('<option value="">No VPO Found</option>');
                            $tehsilSelect.html('<option value="">No Tehsil Found</option>');
                            $districtSelect.html('<option value="">No District Found</option>');
                            $citySelect.html('<option value="">No State Found</option>');
                        }
                    })
                    .catch(error => {
                        $bpoSelect.html('<option value="">Select VPO</option>');
                        $tehsilSelect.html('<option value="">Select Tehsil</option>');
                        $districtSelect.html('<option value="">Select District</option>');
                        $citySelect.html('<option value="">Select State</option>');
                    });
            }));

            $('#enquiry_type').trigger('change');
            if (currentEnquiry.isEdit && currentEnquiry.dealerBranch) $('#dealer_branch').trigger('change');
            $segmentCode.trigger('change');
            if ($('#zipcode').val().length === 6) $('#zipcode').trigger('blur');
        });
    </script>
@endpush