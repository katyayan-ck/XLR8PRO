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
    </style>
@endpush

@section('content')
    @php
        $isVirtual = isset($enquiry) && strtoupper($enquiry->current_origin ?? '') === 'VIRTUAL';
        $isReference = isset($enquiry) && strtoupper($enquiry->source_code ?? '') === 'REFERENCE';
        $isWhatsapp = isset($enquiry) && strtoupper($enquiry->source_code ?? '') === 'WHATSAPP';

        // Dynamic Heading string
        $enqTypeStr = 'Enquiry';
        if ($isReference) {
            $enqTypeStr = 'Reference Enquiry';
        } elseif ($isVirtual) {
            $enqTypeStr = 'Virtual Enquiry';
        } elseif ($isWhatsapp) {
            $enqTypeStr = 'WhatsApp Campaign Enquiry';
        }

        // Dynamic Flexbox Ordering for the cards
        $order = [
            'whatsapp' => 0,
            'credentials' => 1,
            'vehicle' => 2,
            'customer_primary' => 3,
            'exchange' => 4,
            'sc_detail' => 5,
        ];

        if ($isReference) {
            $order['credentials'] = 1;
            $order['customer_primary'] = 2;
            $order['vehicle'] = 3;
            $order['sc_detail'] = 4;
            $order['exchange'] = 5;
        } elseif ($isVirtual) {
            $order['customer_primary'] = 1;
            $order['sc_detail'] = 2;
            $order['credentials'] = 3;
            $order['vehicle'] = 4;
            $order['exchange'] = 5;
        } elseif ($isWhatsapp) {
            $order['whatsapp'] = 1;
            $order['customer_primary'] = 2;
            $order['vehicle'] = 3;
            $order['sc_detail'] = 4;
            $order['credentials'] = 5;
            $order['exchange'] = 6;
        }

        // Display Mappings for Codes
        $devMap = collect($deviation_stages ?? [])->pluck('value', 'code')->toArray();
        $remMap = collect($sc_fup_remarks ?? [])->pluck('value', 'code')->toArray();
        $remTypeMap = collect($sc_fup_remarks_types ?? [])->pluck('value', 'code')->toArray();
        
        $enqStageMap = collect($enquiry_stages ?? [])->pluck('value', 'code')->toArray();
        $custStageMap = collect($customer_stages ?? [])->pluck('value', 'code')->toArray();
    @endphp

    <div class="container-fluid pb-5">

        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h2 class="mb-0 fw-bold">
                {{ isset($enquiry) ? 'Edit ' . $enqTypeStr . ' : XENQ-' . $enquiry->id : 'Add New ' . $enqTypeStr }}
            </h2>
        </div>

        <form method="POST" id="enquiryForm" action="{{ isset($enquiry) ? backpack_url('enquiry/' . $enquiry->id) : backpack_url('enquiry') }}" enctype="multipart/form-data">
            @csrf
            @if (isset($enquiry))
                @method('PUT')
            @endif

            {{-- ERROR DISPLAY BLOCK --}}
            @if ($errors->any())
                <div class="alert alert-danger rounded-3 shadow-sm pb-0 mb-4">
                    <ul class="mb-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Hidden fields to preserve validation for frozen OEM SC Location/Branch --}}
            <input type="hidden" name="dealer_branch" value="{{ old('dealer_branch', $enquiry->dealer_branch ?? '') }}">
            <input type="hidden" name="dealer_location" value="{{ old('dealer_location', $enquiry->dealer_location ?? '') }}">

            {{-- =========================== VIRTUAL CALL DETAILS =========================== --}}
            @if ($isVirtual)
                <div class="card enquiry-card">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Virtual Call Details</h4>
                    </div>
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

            <div id="full_enquiry_form" class="{{ $isVirtual ? 'd-none' : 'd-flex flex-column' }}">

                {{-- =========================== WHATSAPP CAMPAIGN DETAILS =========================== --}}
                @if ($isWhatsapp)
                <div class="card enquiry-card" style="order: {{ $order['whatsapp'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">WhatsApp Campaign Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Lead Date & Time</label>
                                <input type="text" class="form-control" value="{{ $enquiry->created_at ? \Carbon\Carbon::parse($enquiry->created_at)->format('d-M-Y H:i') : '—' }}" readonly style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Campaign Name</label>
                                <input type="text" class="form-control" value="{{ $enquiry->wapp_campaign_name ?? '—' }}" readonly style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Campaign Date</label>
                                <input type="text" class="form-control" value="{{ !empty($enquiry->wapp_campaign_date) ? \Carbon\Carbon::parse($enquiry->wapp_campaign_date)->format('d-M-Y') : '—' }}" readonly style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Campaign Segment</label>
                                <input type="text" class="form-control" value="{{ $enquiry->wapp_campaign_segment ?? '—' }}" readonly style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Campaign Model</label>
                                <input type="text" class="form-control" value="{{ $enquiry->wapp_campaign_model ?? '—' }}" readonly style="background-color: #e9ecef;">
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- =========================== 1. ENQUIRY CREDENTIALS =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['credentials'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Enquiry Credentials</h4>
                    </div>
                    <div class="card-body">
                        
                        @if (isset($enquiry) && !$isVirtual)
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered text-center align-middle mb-0" style="background-color: #e9ecef;">
                                    <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="text-center px-3">Enquiry Platform</th>
                                            <th class="text-center px-3">Enquiry No.</th>
                                            <th class="text-center px-3">Enquiry Date</th>
                                            <th class="text-center px-3">Assignment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (!empty($enquiry->quick_enquiry_no) || !empty($enquiry->quick_enquiry_date) || !empty($enquiry->quick_enq_assign_date))
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">OEM Quick</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->quick_enquiry_no ?: '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->quick_enquiry_date ? \Carbon\Carbon::parse($enquiry->quick_enquiry_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->quick_enq_assign_date ? \Carbon\Carbon::parse($enquiry->quick_enq_assign_date)->format('d-M-Y') : '—' }}</div></td>
                                        </tr>
                                        @endif
                                        
                                        @if (!empty($enquiry->enquiry_no) || !empty($enquiry->enquiry_date))
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">OEM Long</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->enquiry_no ?: '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->enquiry_date ? \Carbon\Carbon::parse($enquiry->enquiry_date)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->enq_assign_date ? \Carbon\Carbon::parse($enquiry->enq_assign_date)->format('d-M-Y') : '—' }}</div></td>
                                        </tr>
                                        @endif

                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Xceler8</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ 'XENQ-' . $enquiry->id }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->created_at ? \Carbon\Carbon::parse($enquiry->created_at)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry->enq_assign_date ? \Carbon\Carbon::parse($enquiry->enq_assign_date)->format('d-M-Y') : '—' }}</div></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif

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
                                <label class="form-label">Enquiry Source <span class="text-danger" id="source_code_asterisk">*</span></label>
                                <select name="source_code" id="source_code" class="form-control form-select" required>
                                    <option value="">Select Enquiry Source</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Enquiry Sub Source <span class="text-danger d-none" id="sub_source_asterisk">*</span></label>
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

                            <div class="row w-100 m-0 p-0 {{ $isReference ? 'd-flex' : 'd-none' }}" id="referenceFields">
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

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Select Dealer Branch <span class="text-danger">*</span></label>
                                <select name="dealer_branch" id="dealer_branch" class="form-control form-select" required>
                                    <option value="">Select Dealer Branch</option>
                                    @foreach ($branches as $code => $name)
                                        <option value="{{ $code }}" {{ old('dealer_branch', $enquiry->dealer_branch ?? '') == $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Select Dealer Location <span class="text-danger">*</span></label>
                                <select name="dealer_location" id="dealer_location" class="form-control form-select" required>
                                    <option value="">Select Dealer Location</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== 2. VEHICLE DETAILS =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['vehicle'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Vehicle Details</h4>
                    </div>
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
                                <label class="form-label">Color @if(!$isReference && !$isWhatsapp)<span class="text-danger">*</span>@endif</label>
                                <select name="color_code" id="color_code" class="form-control form-select" @if(!$isReference && !$isWhatsapp) required @endif>
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
                            
                            <div class="row w-100 m-0 p-0" id="bevSection" style="display: none;">
                                <div class="col-md-4 mb-3">
                                    <label>Do you have an EV? <span class="text-danger">*</span></label>
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

                            <div class="row w-100 m-0 p-0" id="commercialSection" style="display:none;">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Usage Area <span class="text-danger">*</span></label>
                                    <select name="usage_area" class="form-control form-select">
                                        <option value="">Select Usage Area</option>
                                        @foreach ($usage_areas as $item)
                                            <option value="{{ $item['code'] }}" {{ old('usage_area', $enquiry->usage_area ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">KM Travelled Daily <span class="text-danger">*</span></label>
                                    <select name="km_travelled_daily" class="form-control form-select">
                                        <option value="">Select KM Travelled Daily</option>
                                        @foreach ($km_travelled_daily as $item)
                                            <option value="{{ $item['code'] }}" {{ old('km_travelled_daily', $enquiry->km_travelled_daily ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application Type <span class="text-danger">*</span></label>
                                    <select name="application_type" id="application_type" class="form-control form-select">
                                        <option value="">Select Application Type</option>
                                        @foreach ($application_types as $item)
                                            <option value="{{ $item['code'] }}" {{ old('application_type', $enquiry->application_type ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Application <span class="text-danger">*</span></label>
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

                {{-- =========================== 3. CUSTOMER PRIMARY DETAILS =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['customer_primary'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Customer Primary Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $enquiry->first_name ?? '') }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Customer Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $enquiry->last_name ?? '') }}" required>
                            </div>
                            @if (!$isVirtual)
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" id="mobile" name="mobile" maxlength="10" class="form-control" value="{{ old('mobile', $enquiry->mobile ?? '') }}" required>
                                </div>
                            @endif
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Alternate Mobile <small class="text-muted">(Optional)</small></label>
                                <input type="text" id="alternate_mobile" name="alternate_mobile" maxlength="15" class="form-control" value="{{ old('alternate_mobile', $enquiry->alternate_mobile ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email ID <small class="text-muted">(Optional)</small></label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $enquiry->email ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-control form-select" required>
                                    <option value="">Select Gender</option>
                                    @foreach ($genders as $item)
                                        <option value="{{ $item['code'] }}" {{ old('gender', $enquiry->gender ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            {{-- Address block smoothly integrated --}}
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Pin Code <span class="text-danger">*</span></label>
                                <input type="text" id="zipcode" name="zipcode" maxlength="6" class="form-control" value="{{ old('zipcode', $enquiry->zipcode ?? '') }}" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">VPO <span class="text-danger">*</span></label>
                                <select id="vpo_select" class="form-control form-select" required>
                                    <option value="">Select VPO</option>
                                </select>
                                <input type="text" id="vpo_input" class="form-control mt-2 d-none" placeholder="Enter VPO Manually" value="{{ $enquiry->vpo ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Tehsil <span class="text-danger">*</span></label>
                                <select id="tehsil_select" class="form-control form-select" required>
                                    <option value="">Select Tehsil</option>
                                </select>
                                <input type="text" id="tehsil_input" class="form-control mt-2 d-none" placeholder="Enter Tehsil Manually" value="{{ $enquiry->tehsil ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">District <span class="text-danger">*</span></label>
                                <select id="district_select" class="form-control form-select" required>
                                    <option value="">Select District</option>
                                </select>
                                <input type="text" id="district_input" class="form-control mt-2 d-none" placeholder="Enter District Manually" value="{{ $enquiry->district ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">State <span class="text-danger">*</span></label>
                                <select id="state_select" class="form-control form-select" required>
                                    <option value="">Select State</option>
                                </select>
                                <input type="text" id="state_input" class="form-control mt-2 d-none" placeholder="Enter State Manually" value="{{ $enquiry->city ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Territory <span class="text-danger">*</span></label>
                                <select id="territory" name="territory" class="form-control form-select" required>
                                    <option value="">Select Territory</option>
                                    <option value="OWN TERRITORY" {{ old('territory', $enquiry->territory ?? '') == 'OWN TERRITORY' ? 'selected' : '' }}>OWN TERRITORY</option>
                                    <option value="OTHER TERRITORY" {{ old('territory', $enquiry->territory ?? '') == 'OTHER TERRITORY' ? 'selected' : '' }}>OTHER TERRITORY</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- =========================== 4. EXCHANGE & FINANCE =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['exchange'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Exchange & Finance</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if (isset($enquiry))
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Purchase Type <small class="text-muted">(enq dump)</small></label>
                                <select class="form-control form-select" disabled style="background-color: #e9ecef;">
                                    <option value="">Select Purchase Type</option>
                                    @foreach ($purchase_types as $item)
                                        <option value="{{ $item['code'] }}" {{ old('purchase_type', $enquiry->purchase_type ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                                {{-- Hidden field to retain the value for validation / saving --}}
                                <input type="hidden" name="purchase_type" value="{{ old('purchase_type', $enquiry->purchase_type ?? '') }}">
                            </div>
                            @endif

                            <div class="col-md-3 mb-3">
                                <label class="form-label">CRM Purchase Type <span class="text-danger">*</span></label>
                                <select name="purchase_type_crm" id="purchase_type_crm" class="form-control form-select" required>
                                    <option value="">Select CRM Purchase Type</option>
                                    @foreach ($purchase_types as $item)
                                        <option value="{{ $item['code'] }}" {{ old('purchase_type_crm', $enquiry->purchase_type_crm ?? '') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
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
                            
                            <div class="col-md-3 mb-3" id="financierbox" style="display:none;">
                                <label class="form-label">Financier <small class="text-muted">(Optional)</small></label>
                                <select name="financier" id="financier" class="form-control form-select">
                                    <option value="">Select Financier</option>
                                    @foreach ($financiers ?? [] as $financier)
                                        <option value="{{ $financier->id }}" {{ old('financier', $enquiry->financier ?? '') == $financier->id ? 'selected' : '' }}>{{ $financier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if (isset($enquiry) && in_array($enquiry->purchase_type, ['Exchange Buy', 'Scrappage']) && ($enquiry->brand_make || $enquiry->expected_price || $enquiry->lost_reason))
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
                    </div>
                </div>

                {{-- =========================== 5. SALES CONSULTANT DETAIL =========================== --}}
                <div class="card enquiry-card" style="order: {{ $order['sc_detail'] }};">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Sales Consultant Detail</h4>
                    </div>
                    <div class="card-body">
                        
                        @if (isset($enquiry) && !$isReference && !$isVirtual && !$isWhatsapp)
                        <h5 class="mb-3 fw-bold">OEM SC Details (Read-only)</h5>
                        <div class="row mb-4" style="opacity: 0.8; pointer-events:none;">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">OEM Assigned SC</label>
                                <select id="oem_sc_code_display" class="form-control form-select" readonly tabindex="-1" style="background-color: #e9ecef;">
                                    <option value="">Select OEM SC</option>
                                    @foreach ($saleconsultants as $consultant)
                                        <option value="{{ $consultant['person_code'] }}"
                                            data-mile-id="{{ $consultant['employee_code'] ?? '' }}"
                                            data-branch="{{ \App\Services\OrgService::branchName($consultant['primary_branch_code'] ?? '') }}"
                                            data-location="{{ \App\Services\OrgService::locationName($consultant['primary_loc_code'] ?? '') }}"
                                            {{ old('sc_code', $enquiry->sc_code ?? '') == $consultant['person_code'] ? 'selected' : '' }}>
                                            {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="sc_code" value="{{ old('sc_code', $enquiry->sc_code ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">OEM Assigned SC Mile ID</label>
                                <input type="text" id="oem_sc_mile_id" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">OEM Assigned SC Branch</label>
                                <input type="text" id="oem_sc_branch" class="form-control" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">OEM Assigned SC Location</label>
                                <input type="text" id="oem_sc_location" class="form-control" readonly>
                            </div>
                        </div>
                        @endif

                        <h5 class="mb-3 fw-bold">X8 SC Details</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC <span class="text-danger">*</span></label>
                                <select name="x8_sc_code" id="x8_sc_code" class="form-control form-select" required>
                                    <option value="">Select X8 SC</option>
                                    @foreach ($saleconsultants as $consultant)
                                        <option value="{{ $consultant['person_code'] }}"
                                            data-mile-id="{{ $consultant['employee_code'] ?? '' }}"
                                            data-branch="{{ \App\Services\OrgService::branchName($consultant['primary_branch_code'] ?? '') }}"
                                            data-location="{{ \App\Services\OrgService::locationName($consultant['primary_loc_code'] ?? '') }}"
                                            {{ old('x8_sc_code', $enquiry->x8_sc_code ?? '') == $consultant['person_code'] ? 'selected' : '' }}>
                                            {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC Mile ID</label>
                                <input type="text" name="x8_sc_mile_id" id="x8_sc_mile_id" class="form-control" value="{{ old('x8_sc_mile_id', $enquiry->x8_sc_mile_id ?? '') }}" readonly tabindex="-1" style="background-color: #e9ecef; pointer-events: none;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC Branch</label>
                                <input type="text" id="x8_sc_branch" class="form-control" readonly tabindex="-1" style="background-color: #e9ecef; pointer-events: none;">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">X8 Assigned SC Location</label>
                                <input type="text" id="x8_sc_location" class="form-control" readonly tabindex="-1" style="background-color: #e9ecef; pointer-events: none;">
                            </div>
                        </div>

                    </div>
                </div>

                {{-- =========================== 6. CUSTOMER SECONDARY DETAILS =========================== --}}
                <div class="card enquiry-card" style="order: 6;">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Customer Secondary Details</h4>
                    </div>
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

                {{-- =========================== 7. CONSIDERATION SET =========================== --}}
                <div class="card enquiry-card" style="order: 7;">
                    <div class="card-header">
                        <h4 class="mb-0 fw-bold">Consideration Set</h4>
                    </div>
                    <div class="card-body">
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

                {{-- =========================== EDIT ONLY: FOLLOW-UPS & STAGES =========================== --}}
                @if (isset($enquiry))

                    {{-- 8. SC FOLLOW UP DETAILS --}}
                    <div class="card enquiry-card" style="order: 8;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">SC Follow Up Details (Read Only)</h4>
                        </div>
                        <div class="card-body">
                            @if (strtoupper($enquiry->current_origin ?? '') === 'LONG')
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered text-center align-middle mb-0" style="background-color: #e9ecef;">
                                        <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                            <tr>
                                                <th class="text-center px-3">Fup Count</th>
                                                <th class="text-center px-3">Planned Date</th>
                                                <th class="text-center px-3">Actual Date</th>
                                                <th class="text-center px-3">Fup Status</th>
                                                <th class="text-center px-3">Deviation Stage</th>
                                                <th class="text-center px-3">Remarks</th>
                                                <th class="text-center px-3">Remarks Type</th>
                                                <th class="text-center px-3">Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (isset($fups) && count($fups) > 0)
                                                @foreach ($fups as $index => $fup)
                                                    <tr>
                                                        <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? $index + 1 . 'th' }} Fup</td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $fup?->planned_followup_date ? \Carbon\Carbon::parse($fup->planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $fup?->actual_followup_date ? \Carbon\Carbon::parse($fup->actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-center">{{ $fup?->enquiry_status ?: '—' }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $devMap[$fup?->deviation_stage] ?? ($fup?->deviation_stage ?: '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $remMap[$fup?->remarks] ?? ($fup?->remarks ?: '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 120px;">{{ $remTypeMap[$fup?->remark_type] ?? ($fup?->remark_type ?: '—') }}</div></td>
                                                        <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $fup?->comments ?: '—' }}</div></td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="8" class="text-muted py-3 bg-white text-center">No Follow-up Data Found</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered text-center align-middle mb-0" style="background-color: #e9ecef;">
                                        <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                            <tr>
                                                <th class="text-center px-3">Fup Count</th>
                                                <th class="text-center px-3">Planned Date</th>
                                                <th class="text-center px-3">Actual Date</th>
                                                <th class="text-center px-3">Next Fup Date</th>
                                                <th class="text-center px-3">Status</th>
                                                <th class="text-center px-3">Follow Up Type</th>
                                                <th class="text-center px-3">Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">First Fup</td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry?->first_planned_followup_date ? \Carbon\Carbon::parse($enquiry->first_planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry?->first_actual_followup_date ? \Carbon\Carbon::parse($enquiry->first_actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry?->next_planned_followup_date ? \Carbon\Carbon::parse($enquiry->next_planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">{{ $enqStageMap[$enquiry?->stage ?? ''] ?? ($enquiry?->stage ?: '—') }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">{{ $enquiry?->followup_type ?: '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 200px;">{{ $remMap[$enquiry?->remarks] ?? ($enquiry?->remarks ?: '—') }}</div></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Recent Fup</td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry?->recent_planned_followup_date ? \Carbon\Carbon::parse($enquiry->recent_planned_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enquiry?->recent_actual_followup_date ? \Carbon\Carbon::parse($enquiry->recent_actual_followup_date)->format('d-M-Y') : '—' }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">—</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">{{ $enqStageMap[$enquiry?->quick_status ?? ''] ?? ($enquiry?->quick_status ?? ($enquiry?->stage ?? '—')) }}</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-center">—</div></td>
                                                <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 200px;">{{ $remMap[$enquiry?->recent_fup_comments] ?? ($enquiry?->recent_fup_comments ?? ($enquiry?->remarks ?: '—')) }}</div></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- 9. SC ENQUIRY STAGE --}}
                    <div class="card enquiry-card" style="order: 9;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">SC Enquiry Stage</h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3 mb-3"><label class="form-label">Enquiry Stage</label><input type="text" class="form-control" value="{{ $enqStageMap[$enquiry?->dms_enquiry_stage ?? ''] ?? ($enquiry?->dms_enquiry_stage ?? ($enquiry?->stage ?? '—')) }}" readonly style="background-color: #e9ecef;"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Test Drive Count</label><input type="text" class="form-control" value="{{ $enquiry?->td_count ?? '0' }}" readonly style="background-color: #e9ecef;"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Test Drive No.</label><input type="text" class="form-control" value="{{ $enquiry?->test_drive_no ?? '—' }}" readonly style="background-color: #e9ecef;"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Test Drive Date</label><input type="text" class="form-control" value="{{ isset($enquiry) && $enquiry->td_date ? \Carbon\Carbon::parse($enquiry->td_date)->format('d-M-Y') : '—' }}" readonly style="background-color: #e9ecef;"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Booking Date</label><input type="text" class="form-control" value="{{ isset($enquiry) && ($enquiry->x8_booking_date || $enquiry->booking_date) ? \Carbon\Carbon::parse($enquiry->x8_booking_date ?? $enquiry->booking_date)->format('d-M-Y') : '—' }}" readonly style="background-color: #e9ecef;"></div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Booking No.</label>
                                    <input type="text" name="booking_no" class="form-control" value="{{ old('booking_no', $enquiry?->booking_no ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">OTF No.</label>
                                    <input type="text" name="otf_no" class="form-control" value="{{ old('otf_no', $enquiry?->otf_no ?? ($enquiry?->oem_otf_no ?? '')) }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">DMS Enquiry No.</label>
                                    <input type="text" name="dms_enq_no" class="form-control" value="{{ old('dms_enq_no', $enquiry?->dms_enq_no ?? '') }}">
                                </div>
                            </div>
                            <h5 class="fw-bold mb-3">Lost Information</h5>
                            <div class="row" style="opacity: 0.8; pointer-events:none;">
                                <div class="col-md-3 mb-3"><label class="form-label">Lost Reason</label><input type="text" class="form-control" value="{{ $enquiry?->lost_reason ?? '—' }}" readonly></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Lost Sub Reason</label><input type="text" class="form-control" value="{{ $enquiry?->lost_sub_reason ?? '—' }}" readonly></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Lost Detail Reason</label><input type="text" class="form-control" value="{{ $enquiry?->lost_detail_reason ?? '—' }}" readonly></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Lost Remarks</label><input type="text" class="form-control" value="{{ $enquiry?->lost_remarks ?? '—' }}" readonly></div>
                            </div>
                        </div>
                    </div>

                    {{-- 10. CRE ENQUIRY STAGE --}}
                    <div class="card enquiry-card" style="order: 10;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">CRE Enquiry Stage</h4>
                        </div>
                        <div class="card-body">
                            {{-- History Table --}}
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered text-center align-middle mb-0" style="background-color: #e9ecef;">
                                    <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="text-center px-3">Fup Count</th>
                                            <th class="text-center px-3">Planned Date</th>
                                            <th class="text-center px-3">Actual Date</th>
                                            <th class="text-center px-3">Call Duration</th>
                                            <th class="text-center px-3">Next Fup Date</th>
                                            <th class="text-center px-3">Deviation Stage</th>
                                            <th class="text-center px-3">Enq Stage</th>
                                            <th class="text-center px-3">Customer Stage</th>
                                            <th class="text-center px-3">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (isset($creFups) && count($creFups) > 0)
                                            @foreach ($creFups as $index => $cre)
                                                <tr>
                                                    <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">{{ ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'][$index] ?? $index + 1 . 'th' }} Fup</td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $cre?->cre_planned_fup_date ? \Carbon\Carbon::parse($cre->cre_planned_fup_date)->format('d-M-Y') : '—' }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $cre?->cre_actual_fup_date ? \Carbon\Carbon::parse($cre->cre_actual_fup_date)->format('d-M-Y') : '—' }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $cre?->cre_fup_call_duration ?: '—' }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $cre?->cre_next_fup_date ? \Carbon\Carbon::parse($cre->cre_next_fup_date)->format('d-M-Y') : '—' }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-center">{{ $devMap[$cre?->cre_fup_deviation_stage ?? ''] ?? ($cre?->cre_fup_deviation_stage ?: '—') }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-center">{{ $enqStageMap[$cre?->cre_enq_stage ?? ''] ?? ($cre?->cre_enq_stage ?: '—') }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-center">{{ $custStageMap[$cre?->cre_customer_stage ?? ''] ?? ($cre?->cre_customer_stage ?: '—') }}</div></td>
                                                    <td><div class="form-control bg-white h-auto border-0 text-wrap text-center" style="min-width: 150px;">{{ $cre?->cre_fup_remarks ?: '—' }}</div></td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="9" class="text-muted py-3 bg-white text-center">No CRE Follow-up Data Found</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            
                            {{-- Editable Input Row --}}
                            <h5 class="fw-bold mb-3">Add CRE Follow Up</h5>
                            <div class="row">
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Call Duration</label>
                                    <input type="text" id="cre_fup_call_duration" name="cre_fup_call_duration" class="form-control" placeholder="HH:MM:SS" maxlength="8" value="{{ old('cre_fup_call_duration') }}" oninput="formatDuration(this)">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Enquiry Stage @if(isset($enquiry))<span class="text-danger">*</span>@endif</label>
                                    <select name="cre_enq_stage" class="form-control form-select" @if(isset($enquiry)) required @endif>
                                        <option value="">Select Option</option>
                                        @foreach ($enquiry_stages as $item)
                                            <option value="{{ $item['code'] }}" {{ old('cre_enq_stage') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Customer Stage @if(isset($enquiry))<span class="text-danger">*</span>@endif</label>
                                    <select name="cre_customer_stage" class="form-control form-select" @if(isset($enquiry)) required @endif>
                                        <option value="">Select Option</option>
                                        @foreach ($customer_stages as $item)
                                            <option value="{{ $item['code'] }}" {{ old('cre_customer_stage') == $item['code'] ? 'selected' : '' }}>{{ $item['value'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Next Fup Date @if(isset($enquiry))<span class="text-danger">*</span>@endif</label>
                                    <input type="text" id="cre_next_fup_date" name="cre_next_fup_date" class="form-control" value="{{ old('cre_next_fup_date') }}" placeholder="DD-MMM-YYYY" @if(isset($enquiry)) required @endif>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Remarks @if(isset($enquiry))<span class="text-danger">*</span>@endif</label>
                                    <textarea name="cre_fup_remarks" class="form-control" rows="1" @if(isset($enquiry)) required @endif>{{ old('cre_fup_remarks') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 11. COMPARISON - SC Vs CRE --}}
                    <div class="card enquiry-card" style="order: 11;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">Comparison - SC Vs CRE</h4>
                        </div>
                        <div class="card-body">
                            @php
                                $scActualDate = $enquiry?->recent_actual_followup_date ?: $enquiry?->first_actual_followup_date;
                                $scNextDate = $enquiry?->next_planned_followup_date;
                                $scGap = '—';
                                if ($scActualDate && $scNextDate) {
                                    $scGap = \Carbon\Carbon::parse($scActualDate)->diffInDays(\Carbon\Carbon::parse($scNextDate)) . ' Days';
                                }

                                $lastCre = (isset($creFups) && count($creFups) > 0) ? (is_array($creFups) ? end($creFups) : $creFups->last()) : null;
                                $creActualDate = $lastCre?->cre_actual_fup_date;
                                $creNextDate = $lastCre?->cre_next_fup_date;
                                $creGap = '—';
                                if ($creActualDate && $creNextDate) {
                                    $creGap = \Carbon\Carbon::parse($creActualDate)->diffInDays(\Carbon\Carbon::parse($creNextDate)) . ' Days';
                                }
                            @endphp
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered text-center align-middle mb-0" style="background-color: #e9ecef;">
                                    <thead class="table-secondary text-uppercase" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="text-center px-3">Enq & Fup</th>
                                            <th class="text-center px-3">SC</th>
                                            <th class="text-center px-3">CRE</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Actual Fup Date</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $scActualDate ? \Carbon\Carbon::parse($scActualDate)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $creActualDate ? \Carbon\Carbon::parse($creActualDate)->format('d-M-Y') : '—' }}</div></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Next Fup Date</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $scNextDate ? \Carbon\Carbon::parse($scNextDate)->format('d-M-Y') : '—' }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $creNextDate ? \Carbon\Carbon::parse($creNextDate)->format('d-M-Y') : '—' }}</div></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Next Fup Gap</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $scGap }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $creGap }}</div></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Enq Stage</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enqStageMap[$enquiry?->dms_enquiry_stage ?? ''] ?? ($enquiry?->dms_enquiry_stage ?? ($enquiry?->stage ?? '—')) }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">{{ $enqStageMap[$lastCre?->cre_enq_stage ?? ''] ?? ($lastCre?->cre_enq_stage ?? '—') }}</div></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Call Recording</td>
                                            <td>
                                                <div class="form-control bg-white h-auto border-0 d-flex justify-content-center text-center">
                                                    @if(!empty($enquiry->call_url))
                                                        <audio controls style="height: 35px; width: 220px;">
                                                            <source src="{{ $enquiry->call_url }}" type="audio/mpeg">
                                                            Your browser does not support the audio element.
                                                        </audio>
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            </td>
                                            <td><div class="form-control bg-white h-auto border-0 text-nowrap text-center">—</div></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold align-middle table-secondary text-center px-3 text-dark">Remarks</td>
                                            <td><div class="form-control bg-white h-auto border-0 text-wrap text-center">{{ $remMap[$enquiry?->recent_fup_comments] ?? ($enquiry?->recent_fup_comments ?? ($enquiry?->remarks ?? '—')) }}</div></td>
                                            <td><div class="form-control bg-white h-auto border-0 text-wrap text-center">{{ $lastCre?->cre_fup_remarks ?? '—' }}</div></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- =========================== REMARKS =========================== --}}
                @if (!$isVirtual)
                    <div class="card enquiry-card" style="order: 12;">
                        <div class="card-header">
                            <h4 class="mb-0 fw-bold">Remarks</h4>
                        </div>
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

        function formatDuration(input) {
            let val = input.value.replace(/\D/g, '');
            if (val.length > 6) val = val.substring(0, 6);
            let h = val.substring(0, 2);
            let m = val.substring(2, 4);
            let s = val.substring(4, 6);
            if (m.length === 2 && parseInt(m) > 59) m = '59';
            if (s.length === 2 && parseInt(s) > 59) s = '59';
            let formatted = h;
            if (val.length > 2) formatted += ':' + m;
            if (val.length > 4) formatted += ':' + s;
            input.value = formatted;
        }

        function loadKeywordDropdown(keyword, parent, $target, placeholder = 'Select Option', selected = '') {
            if (!parent) return $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true);
            const url = "{{ route('admin.master.keyword-values', ['keyword' => '__K__', 'parent' => '__P__']) }}".replace(
                '__K__', encodeURIComponent(keyword)).replace('__P__', encodeURIComponent(parent));
            $.ajax({
                url: url,
                type: "GET",
                beforeSend: () => $target.html('<option>Loading...</option>').prop('disabled', true),
                success: (response) => {
                    let html = `<option value="">${placeholder}</option>`;
                    $.each(response, (_, item) => {
                        let isSelected = (String(selected).toUpperCase() === String(item.code).toUpperCase()) ? 'selected' : '';
                        html += `<option value="${item.code}" ${isSelected}>${item.value}</option>`;
                    });
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
                    let attrs = typeof item === 'object' ?
                        `data-fuel="${item.fuel_type || ''}" data-fuel-id="${item.fuel_type_id || ''}" data-transmission="${item.transmission || ''}" data-drivetrain="${item.drivetrain || ''}" data-seating="${item.seating || ''}"` :
                        '';
                    html +=
                        `<option value="${code}" ${selected == code ? 'selected' : ''} ${attrs}>${val}</option>`;
                });
                $target.html(html).prop('disabled', false);
                if (callback) callback();
            }).fail(() => $target.html(`<option value="">${placeholder}</option>`).prop('disabled', true));
        }

        function populateScDetails($select, prefix) {
            const $option = $select.find('option:selected');
            const mileId = $option.data('mile-id') || '';
            const branch = $option.data('branch') || '';
            const location = $option.data('location') || '';

            $(`#${prefix}_mile_id`).val(mileId);
            $(`#${prefix}_branch`).val(branch);
            $(`#${prefix}_location`).val(location);
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
            $('#oem_sc_code_display').on('change', function() { populateScDetails($(this), 'oem_sc'); });
            $('#x8_sc_code').on('change', function() { populateScDetails($(this), 'x8_sc'); });

            if ($('#oem_sc_code_display').val()) $('#oem_sc_code_display').trigger('change');
            if ($('#x8_sc_code').val()) $('#x8_sc_code').trigger('change');

            const isVirtual = @json($isVirtual);
            if (isVirtual && $('#call_nature').length > 0) {
                const $fullForm = $('#full_enquiry_form');

                function toggleVirtualForm() {
                    const text = $('#call_nature').find('option:selected').text().trim().toUpperCase();
                    if (text.includes('SALES')) {
                        $fullForm.removeClass('d-none').addClass('d-flex flex-column');
                        $fullForm.find('input, select, textarea').prop('disabled', false);
                        setTimeout(() => {
                            $('#segment_code').trigger('change');
                            $('#source_code').trigger('change');
                        }, 50);
                    } else {
                        $fullForm.removeClass('d-flex flex-column').addClass('d-none');
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

            let maxDob = new Date();
            maxDob.setFullYear(maxDob.getFullYear() - 18);
            flatpickr("#dob", {
                dateFormat: "d-M-Y",
                maxDate: maxDob,
                allowInput: false
            });
            flatpickr("#cre_next_fup_date", {
                dateFormat: "d-M-Y",
                allowInput: false
            });
            flatpickr("#marriage_date", {
                dateFormat: "d-M-Y",
                maxDate: "today",
                allowInput: false
            });

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
                // Get both the hidden value (code) and the visible text
                const typeVal = String($(this).val()).trim().toUpperCase();
                const typeText = $(this).find('option:selected').text().trim().toUpperCase().replace(/\s+/g, '');

                // Check for WALK_IN (value), or any text variations like WALKIN, WALK-IN
                if (typeVal === 'WALK_IN' || typeText.includes('WALKIN') || typeText.includes('WALK_IN')) {
                    // Disable Source & hide asterisk
                    $sourceCode.html('<option value="">Select Enquiry Source</option>').val('').prop('disabled', true).prop('required', false);
                    $('#source_code_asterisk').addClass('d-none');
                    
                    // Disable Sub Source & hide asterisk
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop('disabled', true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
                    
                    // Trigger change to hide any reference fields
                    $sourceCode.trigger('change');
                } else {
                    // Enable Source & show asterisk
                    $sourceCode.prop('disabled', false).prop('required', true);
                    $('#source_code_asterisk').removeClass('d-none');
                    
                    loadKeywordDropdown('ENQ_SOURCE', $(this).val(), $sourceCode, 'Select Enquiry Source', currentEnquiry.source);
                    
                    // Reset Sub Source
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').prop('disabled', true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
                    
                    if (currentEnquiry.isEdit) setTimeout(() => $sourceCode.trigger('change'), 300);
                }
            });

            // Flag to prevent wiping data during the page load and AJAX calls
            let isInitialLoad = true;

            const toggleReferenceFields = () => {
                let sourceVal = String($sourceCode.val() || '').trim().toUpperCase();
                let expectedSource = String(currentEnquiry.source || '').trim().toUpperCase();
                
                let isRef = false;
                // Force it to stay visible if it's loading a Reference enquiry
                if (sourceVal === 'REFERENCE' || (isInitialLoad && expectedSource === 'REFERENCE')) {
                    isRef = true;
                }

                if (isRef) {
                    $('#referenceFields').removeClass('d-none').addClass('d-flex');
                    $('#referred_by, #referee_phone, #referee_name').prop('required', true);
                } else {
                    $('#referenceFields').removeClass('d-flex').addClass('d-none');
                    $('#referred_by, #referee_phone, #referee_name').prop('required', false);
                    
                    // CRITICAL FIX: Ignore the "Loading..." state. 
                    // ONLY wipe if the page is fully loaded and the user picks a non-reference source.
                    if (!isInitialLoad && sourceVal && sourceVal !== 'REFERENCE' && sourceVal !== 'LOADING...') {
                        $('#referred_by, #referee_phone, #referee_name').val('');
                    }
                }
            };

            // Allow the dropdowns to finish loading before we lift the data protection
            setTimeout(() => { isInitialLoad = false; }, 1500);
            
            $sourceCode.on('change', function() {
                const source = $(this).val();
                toggleReferenceFields();
                if (source === 'HYPERLOCAL') {
                    $subSource.prop('disabled', false).prop('required', true);
                    $('#sub_source_asterisk').removeClass('d-none');
                    loadKeywordDropdown('ENQUIRY_SUB_SOURCE', source, $subSource,
                        'Select Enquiry Sub Source', currentEnquiry.subSource);
                } else {
                    $subSource.html('<option value="">Select Enquiry Sub Source</option>').val('').prop('disabled', true).prop('required', false);
                    $('#sub_source_asterisk').addClass('d-none');
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
                if (segmentText === 'BEV') {
                    $('#bevSection').find('input[type="radio"]').prop('required', true);
                } else {
                    $('#bevSection').find('input[type="radio"]').prop('required', false);
                }

                const isCommercial = ['LMM', 'COMMERCIAL', 'CV', 'HCV', 'LCV', 'SCV'].some(val => segmentText.includes(val));
                $('#commercialSection').toggle(isCommercial);
                
                if (!isCommercial) {
                    $('#commercialSection').find('select,input').val('').prop('required', false);
                } else {
                    $('#commercialSection').find('select,input').prop('required', true);
                }

                $modelCode.add($variantCode).add($colorCode).html('<option value="">Select Option</option>').prop('disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');
                if (segmentCode) {
                    fetchDropdown("{{ backpack_url('enquiry/models') }}/" + segmentCode, $modelCode,
                        'Select Model', currentEnquiry.isEdit ? currentEnquiry.model : '', () => {
                            if (currentEnquiry.isEdit && currentEnquiry.model) $modelCode.trigger('change');
                        });
                }
            });

            $modelCode.on('change', function() {
                const modelCode = $(this).val();
                $variantCode.add($colorCode).html('<option value="">Select Option</option>').prop('disabled', true);
                $('#fuel_type, #fuel_type_id, #transmission, #drivetrain, #seating').val('');
                if (modelCode) {
                    fetchDropdown("{{ backpack_url('enquiry/variants') }}/" + modelCode, $variantCode,
                        'Select Variant', currentEnquiry.isEdit ? currentEnquiry.variant : '', () => {
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

            function setupDynamicLocation(selectId, inputId, inputName) {
                const $select = $(`#${selectId}`);
                const $input = $(`#${inputId}`);
                $select.on('change', function() {
                    if ($(this).val() === 'OTHER') {
                        $input.removeClass('d-none').attr('name', inputName);
                        if ($select.prop('required')) $input.prop('required', true);
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
                                        valueFound = true;
                                        matchedValue = val;
                                    }
                                });

                                if (!valueFound && safeCurrent.length > 0) {
                                    arr.forEach(val => {
                                        let safeVal = val.toString().trim().toLowerCase();
                                        if (safeVal.includes(safeCurrent) || safeCurrent.includes(safeVal)) {
                                            valueFound = true;
                                            matchedValue = val;
                                        }
                                    });
                                }

                                arr.forEach(val => {
                                    const selected = (valueFound && val === matchedValue) ? 'selected' : '';
                                    html += `<option value="${val}" ${selected}>${val}</option>`;
                                });

                                const otherSelected = (!valueFound && currentValue) ? 'selected' : '';
                                html += `<option value="OTHER" ${otherSelected}>Other</option>`;
                                return {
                                    html,
                                    valueFound,
                                    matchedValue: (valueFound ? matchedValue : currentValue)
                                };
                            };

                            const handleRender = (selectId, inputId, inputName, optionsArr, placeholder, currentValue) => {
                                const renderData = buildOptions(optionsArr, placeholder, currentValue);
                                $(`#${selectId}`).html(renderData.html);
                                if (!renderData.valueFound && currentValue) {
                                    $(`#${inputId}`).val(currentValue).removeClass('d-none').attr('name', inputName);
                                    if ($(`#${selectId}`).prop('required')) $(`#${inputId}`).prop('required', true);
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

                            if (districts.length > 0) {
                                const dist = districts[0].toUpperCase();
                                if (['BIKANER', 'CHURU', 'SUJANGARH'].includes(dist))
                                    $territorySelect.val('OWN TERRITORY');
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