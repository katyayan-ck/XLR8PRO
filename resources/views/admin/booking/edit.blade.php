@extends(backpack_view('blank'))

@section('header')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

<style>
    .required-mark {
        color: #dc3545;
        margin-left: 2px;
    }

    .form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
    }

    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }

    .card-body h2 {
        font-weight: 600;
        color: #495057;
    }

    input.uppercase {
        text-transform: uppercase;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-header flex-nowrap">
        <div class="row">
            <div class="col-lg-8">
                <div class="page-header-title">
                    <i class="ik ik-car bg-blue"></i>
                    <h3 class="d-inline-block mb-0">Edit Booking</h3>
                    <small class="text-muted ml-3">ID: {{ $entry->id }}</small>
                </div>
            </div>
            <div class="col-lg-4 text-end">
                <nav class="breadcrumb-container" aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-end">
                        <li class="breadcrumb-item"><a href="{{ backpack_url('dashboard') }}"><i class="ik ik-home"></i>
                                Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ backpack_url('booking') }}">Bookings</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="row">
        @include(backpack_view('inc.alerts'))

        <div class="col-md-12">
            <form id="bookingForm" method="POST" action="{{ backpack_url('booking/'.$entry->id) }}"
                enctype="multipart/form-data" class="forms-sample">
                @csrf
                @method('PUT')

                <div class="card mt-0">
                    <div class="card-body">
                        <h2 class="mb-4">Payment Details (Locked)</h2>
                        <div class="row">
                            <div class="col-sm-2 form-group">
                                <label>Customer Type</label>
                                <input type="text" class="form-control"
                                    value="{{ $entry->b_type == 'Active' ? 'Actual' : 'Dummy' }}" readonly>
                                <input type="hidden" name="customer_type" value="{{ $entry->b_type }}">
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Customer Category</label>
                                <input type="text" class="form-control" value="{{ $entry->b_cat }}" readonly>
                            </div>
                            <div class="col-sm-2 form-group">
                                <label>Booking Date</label>
                                <input type="text" class="form-control"
                                    value="{{ \Carbon\Carbon::parse($entry->booking_date)->format('d-M-Y') }}" readonly>
                                <input type="hidden" name="booking_date_actual" value="{{ $entry->booking_date }}">
                            </div>
                            <div class="col-sm-2 form-group">
                                <label>Collection Type</label>
                                <input type="text" class="form-control" readonly
                                    value="{{ $entry->col_type == 1 ? 'Receipt' : ($entry->col_type == 2 ? 'Field Collection By Sales Team' : ($entry->col_type == 3 ? 'Field Collection By DSA' : 'Used Car Purchase')) }}">
                                <input type="hidden" name="col_type" value="{{ $entry->col_type }}">
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Collected By</label>
                                <input type="text" class="form-control" value="{{ $data['collector_name'] ?? 'N/A' }}"
                                    readonly>
                                <input type="hidden" name="user" value="{{ $entry->col_by }}">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Booking Amount</label>
                                <input type="text" class="form-control" value="{{ $entry->booking_amount }}" readonly>
                                <input type="hidden" name="booking_amount" value="{{ $entry->booking_amount }}">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Receipt/Voucher No.</label>
                                <input type="text" class="form-control" value="{{ $entry->receipt_no }}" readonly>
                                <input type="hidden" name="receipt_no" value="{{ $entry->receipt_no }}">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Receipt/Voucher Date</label>
                                <input type="text" class="form-control"
                                    value="{{ $entry->receipt_date ? \Carbon\Carbon::parse($entry->receipt_date)->format('d-M-Y') : '' }}"
                                    readonly>
                                <input type="hidden" name="receipt_date_actual" value="{{ $entry->receipt_date }}">
                            </div>
                        </div>
                    </div>
                </div>


                <div class="card mt-3">
                    <div class="card-body">
                        <h2 class="mb-4">Customer Details</h2>
                        <div class="row">
                            <div class="col-sm-3 form-group">
                                <label for="name">
                                    {{ $entry->b_cat == 'Corporate' ? 'Corporate Name' : 'Customer Name' }}
                                    <span class="required-mark">*</span>
                                </label>
                                <input type="text" name="name" id="name" class="form-control uppercase"
                                    value="{{ $entry->name }}" required>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="care_of">Care Of <span class="required-mark">*</span></label>
                                <select name="care_of" id="care_of" class="form-control form-select" required>
                                    <option value="">Please Select...</option>
                                    <option value="1" {{ $entry->care_of_type == 1 ? 'selected' : '' }}>Son of</option>
                                    <option value="2" {{ $entry->care_of_type == 2 ? 'selected' : '' }}>Daughter of
                                    </option>
                                    <option value="3" {{ $entry->care_of_type == 3 ? 'selected' : '' }}>Married to
                                    </option>
                                    <option value="4" {{ $entry->care_of_type == 4 ? 'selected' : '' }}>Guardian Name
                                    </option>
                                    <option value="5" id="ownedByOption" {{ $entry->care_of_type == 5 ? 'selected' : ''
                                        }}>Owned By</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label id="careofnamelabel">
                                    {{ $entry->b_cat === 'Firm' ? 'Owner Name' : 'Care Of Name' }} <span
                                        class="required-mark">*</span>
                                </label>
                                <input type="text" name="care_of_name" id="care_of_name" class="form-control uppercase"
                                    value="{{ $entry->care_of }}" required>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="mobile">Contact No. <span class="required-mark">*</span></label>
                                <input type="text" name="mobile" id="mobile" class="form-control"
                                    value="{{ $entry->mobile }}" required>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="alt_mobile">Alternate Contact No.</label>
                                <input type="text" name="alt_mobile" id="alt_mobile" class="form-control"
                                    value="{{ $entry->alt_mobile }}">
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="gender">Gender <span class="required-mark">*</span></label>
                                <select name="gender" id="gender" class="form-control form-select" required>
                                    <option value="Male" {{ $entry->gender == 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ $entry->gender == 'Female' ? 'selected' : '' }}>Female
                                    </option>
                                    <option value="Transgender" {{ $entry->gender == 'Transgender' ? 'selected' : ''
                                        }}>Transgender</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="occupation">Occupation <span class="required-mark">*</span></label>
                                <select name="occupation" id="occupation" class="form-control form-select" required>
                                    <option value="">Please Select...</option>

                                    @php
                                    $occ = trim($entry->occ ?? '');
                                    @endphp

                                    <option value="Agriculture" {{ $occ==='Agriculture' ? 'selected' : '' }}>Agriculture
                                    </option>
                                    <option value="Business" {{ in_array($occ, ['Business', 'business' ]) ? 'selected'
                                        : '' }}>Business</option>
                                    <option value="Salaried (Govt.)" {{ $occ==='Salaried (Govt.)' ? 'selected' : '' }}>
                                        Salaried (Govt.)</option>
                                    <option value="Salaried (Pvt.)" {{ $occ==='Salaried (Pvt.)' ? 'selected' : '' }}>
                                        Salaried (Pvt.)</option>
                                    <option value="Self Employed (Professional)" {{
                                        $occ==='Self Employed (Professional)' ? 'selected' : '' }}>Self Employed
                                        (Professional)</option>
                                    <option value="Pensioner" {{ $occ==='Pensioner' ? 'selected' : '' }}>Pensioner
                                    </option>
                                    <option value="Other" {{ $occ==='Other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="pan_no">PAN Card No.</label>
                                <input type="text" name="pan_no" id="pan_no" class="form-control uppercase"
                                    value="{{ $entry->pan_no }}">
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="adhar_no">Aadhar No.</label>
                                <input type="text" name="adhar_no" id="adhar_no" class="form-control"
                                    value="{{ $entry->adhar_no }}">
                            </div>

                            <div class="col-sm-3 form-group" id="gstn-group">
                                <label for="gstn">GSTN
                                    <span class="required-mark" id="gstn-required"
                                        style="display: {{ ($entry->gstn == '0' || $entry->gstn === null) ? 'none' : 'inline' }};">*</span>
                                </label>
                                <input type="text" name="gstn" id="gstn" class="form-control uppercase"
                                    value="{{ ($entry->gstn != '0' && $entry->gstn !== null) ? $entry->gstn : '' }}"
                                    placeholder="Enter GSTN No." {{ ($entry->gstn == '0' || $entry->gstn === null) ?
                                'readonly' : '' }}>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="gst_unregistered" class="form-check-input"
                                        id="gst_unregistered" value="1" {{ ($entry->gstn == '0' || $entry->gstn ===
                                    null) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="gst_unregistered">GST Unregistered</label>
                                </div>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="customer_dob">Customer D.O.B. <span class="required-mark">*</span></label>
                                <input type="text" name="customer_dob" id="customer_dob" class="form-control flatpickr"
                                    value="{{ $entry->c_dob ? \Carbon\Carbon::parse($entry->c_dob)->format('d-M-Y') : '' }}"
                                    placeholder="dd-mmm-yyyy" required>
                                <input type="hidden" name="hidden_customer_dob" id="hidden_customer_dob"
                                    value="{{ $entry->c_dob }}">
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="customer_age">Customer Age</label>
                                <input type="text" name="customer_age" id="customer_age" class="form-control"
                                    value="{{ $entry->c_dob ? \Carbon\Carbon::parse($entry->c_dob)->age : '' }}"
                                    readonly>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="branch">Branch <span class="required-mark">*</span></label>
                                <select name="branch" id="branch" class="form-select" required>
                                    <option value="">Please Select...</option>
                                    @foreach($data['branches'] as $branch)
                                    <option value="{{ $branch->code }}" {{ $entry->branch_code == $branch->code ?
                                        'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="location">Location <span class="required-mark">*</span></label>
                                <select name="location_id" id="location" class="form-select" required>
                                    <option value="">Please Select...</option>
                                    @foreach($data['locations'] as $location)
                                    {{-- Line 272 — change value to code --}}
                                    <option value="{{ $location->code ?? '' }}" {{ (string)$entry->location_code ===
                                        (string)($location->code ?? '') ? 'selected' : '' }}>
                                        {{ ($location->name ?? '') . ' - ' . ($location->code ?? '') }}
                                    </option>

                                    @endforeach
                                    <option value="0" {{ $entry->location_code == 0 ? 'selected' : '' }}>OTHER</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group" id="location_other_group"
                                style="{{ $entry->location_code == 0 ? '' : 'display:none;' }}">
                                <label for="location_other">Other Location</label>
                                <input type="text" name="location_other" id="location_other"
                                    class="form-control uppercase" value="{{ $entry->location_other }}" {{
                                    $entry->location_code == 0 ? '' : 'disabled' }}>
                            </div>


                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <div class="col-sm-12 mt-4">
                            <h2 class="mb-3">Referred By Details</h2>
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="referred_by_checkbox"
                                    name="referred_by" {{ $entry->r_name ? 'checked' : '' }}>
                                <label class="form-check-label" for="referred_by_checkbox">Referred By</label>


                                <div class="row" id="referred_by_fields"
                                    style="{{ $entry->r_name ? '' : 'display:none;' }}">
                                    <div class="col-sm-2 form-group">
                                        <label for="ref_customer_name">Customer Name <span class="required-mark"
                                                style="display: {{ $entry->r_name ? 'inline' : 'none' }};">*</span></label>
                                        <input type="text" name="ref_customer_name" id="ref_customer_name"
                                            class="form-control uppercase" value="{{ $entry->r_name ?? '' }}" {{
                                            $entry->r_name ? 'required' : '' }}>
                                    </div>
                                    <div class="col-sm-2 form-group">
                                        <label for="ref_mobile_no">Mobile No. <span class="required-mark"
                                                style="display: {{ $entry->r_name ? 'inline' : 'none' }};">*</span></label>
                                        <input type="text" name="ref_mobile_no" id="ref_mobile_no" class="form-control"
                                            value="{{ $entry->r_mobile ?? '' }}" maxlength="10" pattern="[0-9]{10}"
                                            inputmode="numeric" {{ $entry->r_name ? 'required' : '' }}>
                                    </div>
                                    <div class="col-sm-2 form-group">
                                        <label for="ref_existing_model">Existing Model <span class="required-mark"
                                                style="display: {{ $entry->r_name ? 'inline' : 'none' }};">*</span></label>
                                        <input type="text" name="ref_existing_model" id="ref_existing_model"
                                            class="form-control uppercase" value="{{ $entry->r_model ?? '' }}" {{
                                            $entry->r_name ? 'required' : '' }}>
                                    </div>
                                    <div class="col-sm-2 form-group">
                                        <label for="ref_variant">Variant <span class="required-mark"
                                                style="display: {{ $entry->r_name ? 'inline' : 'none' }};">*</span></label>
                                        <input type="text" name="ref_variant" id="ref_variant"
                                            class="form-control uppercase" value="{{ $entry->r_variant ?? '' }}" {{
                                            $entry->r_name ? 'required' : '' }}>
                                    </div>
                                    <div class="col-sm-2 form-group">
                                        <label for="ref_chassis_reg_no">Chassis / Regn. No. <span class="required-mark"
                                                style="display: {{ $entry->r_name ? 'inline' : 'none' }};">*</span></label>
                                        <input type="text" name="ref_chassis_reg_no" id="ref_chassis_reg_no"
                                            class="form-control uppercase" value="{{ $entry->r_chassis ?? '' }}" {{
                                            $entry->r_name ? 'required' : '' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <div class="card mt-3">
                    <div class="card-body">
                        <h2 class="mb-4">Purchase Type Details</h2>
                        <div class="row">
                            <div class="col-sm-2 form-group">
                                <label for="buyer_type">Purchase Type <span class="required-mark">*</span></label>
                                <select name="buyer_type" id="buyer_type" class="form-select" required>
                                    <option value="First time Buyer" {{ $entry->buyer_type == 'First time Buyer' ?
                                        'selected' : '' }}>First Time Buyer</option>
                                    <option value="Additional Buy" {{ $entry->buyer_type == 'Additional Buy' ?
                                        'selected' : '' }}>Additional Buy</option>
                                    <option value="Exchange Buy" {{ $entry->buyer_type == 'Exchange Buy' ? 'selected' :
                                        '' }}>Exchange Buy</option>
                                    <option value="Scrappage" {{ $entry->buyer_type == 'Scrappage' ? 'selected' : ''
                                        }}>Scrappage</option>
                                </select>
                            </div>

                            <div class="col-sm-2 form-group">
                                <label for="enummaster1">Brand (Make 1) <span class="required-mark" id="make1-required"
                                        style="display: none;">*</span></label>
                                <select name="enummaster1" id="enummaster1" class="form-select">
                                    <option value="">Please Select...</option>
                                    @foreach($data['enum_master'] ?? [] as $enum)
                                    <option value="{{ $enum->code ?? $enum['code'] ?? '' }}" {{ ($entry->exist_oem1 ==
                                        ($enum->code ?? $enum['code'] ?? '')) ? 'selected' : '' }}>
                                        {{ $enum->value ?? $enum['value'] ?? '' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="vehicle_details">Model & Variant 1 <span class="required-mark"
                                        id="veh1-required" style="display: none;">*</span></label>
                                <input type="text" name="vehicle_details" id="vehicle_details"
                                    class="form-control uppercase" value="{{ $entry->vh1_detail }}" disabled>
                            </div>

                            <div class="col-sm-2 form-group">
                                <label for="enummaster2">Brand (Make 2) <span class="required-mark" id="make2-required"
                                        style="display: none;">*</span></label>
                                <select name="enummaster2" id="enummaster2" class="form-select">
                                    <option value="">Please Select...</option>
                                    @foreach($data['enum_master'] ?? [] as $enum)
                                    <option value="{{ $enum->code ?? $enum['code'] ?? '' }}" {{ ($entry->exist_oem2 ==
                                        ($enum->code ?? $enum['code'] ?? '')) ? 'selected' : '' }}>
                                        {{ $enum->value ?? $enum['value'] ?? '' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="vehicle_details2">Model & Variant 2 <span class="required-mark"
                                        id="veh2-required" style="display: none;">*</span></label>
                                <input type="text" name="vehicle_details2" id="vehicle_details2"
                                    class="form-control uppercase" value="{{ $entry->vh2_detail }}" disabled>
                            </div>

                            <div class="col-sm-4 form-group">
                                <label for="registration_no">Vehicle Registration No. <span class="required-mark"
                                        style="display: none;">*</span></label>
                                <input type="text" name="registration_no" id="registration_no"
                                    class="form-control uppercase" value="{{ $entry->registration_no }}" disabled>
                            </div>

                            <div class="col-sm-4 form-group">
                                <label for="manufacturing_year">Vehicle Manufacturing Year <span class="required-mark"
                                        style="display: none;">*</span></label>
                                <input type="number" name="manufacturing_year" id="manufacturing_year"
                                    class="form-control" value="{{ $entry->make_year }}" disabled>
                            </div>

                            <div class="col-sm-4 form-group">
                                <label for="odometer_reading">Vehicle Odometer Reading <span class="required-mark"
                                        style="display: none;">*</span></label>
                                <input type="text" name="odometer_reading" id="odometer_reading" class="form-control"
                                    value="{{ $entry->odo_reading }}" disabled>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="expected_price">Used Vehicle Expected Price <span class="required-mark"
                                        style="display: none;">*</span></label>
                                <input type="number" name="expected_price" id="expected_price" class="form-control"
                                    value="{{ $entry->expected_price }}" disabled>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="offered_price">Used Vehicle Offered Price <span class="required-mark"
                                        style="display: none;">*</span></label>
                                <input type="number" name="offered_price" id="offered_price" class="form-control"
                                    value="{{ $entry->offered_price }}" disabled>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="exchange_bonus">New Vehicle Exchange Bonus <span class="required-mark"
                                        style="display: none;">*</span></label>
                                <input type="number" name="exchange_bonus" id="exchange_bonus" class="form-control"
                                    value="{{ $entry->exchange_bonus }}" disabled>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="difference">Price Gap</label>
                                <input type="text" name="difference" id="difference" class="form-control"
                                    value="{{ $entry->expected_price - ($entry->offered_price + $entry->exchange_bonus) }}"
                                    readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h2 class="mb-4">Vehicle Details</h2>
                        <div class="row">
                            <input type="hidden" name="vh_id" id="vh_id" value="{{ $entry->vh_id }}">

                            @php
                            \Log::info('🔍 [EDIT-SEGMENT] Pre-selection debug', [
                            'entry_segment_code' => $entry->segment_code,
                            'entry_segment_code_type' => gettype($entry->segment_code),
                            'segments_count' => count($data['segments']),
                            'all_segment_ids' => collect($data['segments'])->pluck('code')->toArray(),
                            'all_segment_values' => collect($data['segments'])->pluck('name')->toArray(),
                            'strict_match_found' => collect($data['segments'])->contains(fn($s) => $s->code ===
                            $entry->segment_code),
                            'loose_match_found' => collect($data['segments'])->contains(fn($s) => $s->code ==
                            $entry->segment_code),
                            'matched_segment' => collect($data['segments'])->first(fn($s) => (string)$s->code ===
                            (string)$entry->segment_code),
                            ]);
                            @endphp
                            <div class="col-sm-3 form-group">
                                <label for="segment_id">Segment <span class="required-mark">*</span></label>
                                <select name="segment_id" id="segment" class="form-select" required
                                    data-selected-segment="{{ $entry->segment_code }}">
                                    <option value="">Please Select...</option>
                                    @foreach($data['segments'] as $seg)
                                    @php
                                    $segSelected = (string)$entry->segment_code === (string)$seg->code;
                                    @endphp
                                    <option value="{{ $seg->code }}" {{ $segSelected ? 'selected' : '' }}>
                                        {{ $seg->name }}
                                    </option>
                                    @endforeach
                                </select>

                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="model">Model <span class="required-mark">*</span></label>
                                <select name="model" id="model" class="form-select" required>
                                    <option value="">Please Select...</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="variant">Variant <span class="required-mark">*</span></label>
                                <select name="variant" id="variant" class="form-select" required>
                                    <option value="">Please Select...</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="color">Color <span class="required-mark">*</span></label>
                                <select name="color" id="color" class="form-select" required>
                                    <option value="">Please Select...</option>
                                </select>
                            </div>

                            <div class="col-sm-2 form-group">
                                <label for="seating">Seating</label>
                                <input type="text" name="seating" id="seating" class="form-control"
                                    value="{{ $entry->seating }}" readonly>
                            </div>

                            @php
                            $selectedAccessories = array_filter(explode(',', $entry->accessories ?? ''));
                            @endphp

                            <div class="col-sm-4 form-group">
                                <label for="accessories">Select Accessories</label>

                                <select name="accessories[]" id="accessories" class="form-control select2" multiple>

                                    @foreach($data['accessories_dropdown'] as $acc)

                                    <option value="{{ $acc['part_no'] }}" data-price="{{ $acc['ndp'] ?? 0 }}" {{
                                        in_array($acc['part_no'], $selectedAccessories) ? 'selected' : '' }}>
                                        {{ $acc['item'] ?? $acc['item'] }}
                                    </option>

                                    @endforeach

                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="apack_amount">Accessories Amount</label>
                                <input type="text" name="apack_amount" id="apack_amount" class="form-control"
                                    value="{{ $entry->apack_amount }}" readonly>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="chassis">Allotted Chassis No.</label>
                                <select name="chassis" id="chassis" class="form-select" disabled>
                                    <option value="">Please Select...</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h2 class="mb-4">Booking Details</h2>
                        <div class="row">

                            <div class="col-sm-3 form-group">
                                <label for="booking_mode">Booking Mode <span class="required-mark">*</span></label>
                                <select name="booking_mode" id="booking_mode" class="form-select" required>
                                    <option value="">Please Select...</option>
                                    <option value="Dealer" {{ $entry->b_mode == 'Dealer' ? 'selected' : '' }}>Dealer
                                    </option>
                                    <option value="Online" {{ $entry->b_mode == 'Online' ? 'selected' : '' }}>Online
                                    </option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group" id="refrence_no_group"
                                style="display: {{ $entry->b_mode == 'Online' ? 'block' : 'none' }};">
                                <label for="refrence_no">Online Book Ref No. <span
                                        class="required-mark">*</span></label>
                                <input type="text" name="refrence_no" id="refrence_no" class="form-control uppercase"
                                    value="{{ $entry->online_bk_ref_no }}" {{ $entry->b_mode == 'Online' ? 'required' :
                                'disabled' }}>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="booking_source">Booking Source <span class="required-mark">*</span></label>
                                <select name="booking_source" id="booking_source" class="form-select" required>
                                    <option value="">Please Select...</option>
                                    <option value="Dealer" {{ $entry->b_source == 'Dealer' ? 'selected' : '' }}>Dealer
                                        Sourcing</option>
                                    <option value="DSA" {{ $entry->b_source == 'DSA' ? 'selected' : '' }}>DSA</option>
                                </select>
                            </div>

                            <div class="col-sm-3 form-group" id="dsa_details_group"
                                style="display: {{ $entry->b_source == 'DSA' ? 'block' : 'none' }};">
                                <label for="dsa_details">Select DSA <span class="required-mark">*</span></label>
                                <select name="dsa_details" id="dsa_details" class="form-select" {{ $entry->b_source ==
                                    'DSA' ? 'required' : 'disabled' }}>
                                    <option value="">Please Select...</option>
                                    @foreach($data['dsa_details'] as $dsa)
                                    <option value="{{ $dsa->id }}" {{ $entry->dsa_id == $dsa->id ? 'selected' : '' }}>
                                        {{ $dsa->name }} - {{ $dsa->mobile }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="saleconsultant">Sales Consultant <span
                                        class="required-mark">*</span></label>
                                <select name="saleconsultant" id="saleconsultant" class="form-select" required>
                                    <option value="">Please Select...</option>
                                    @foreach($data['saleconsultants'] as $consultant)
                                    <option value="{{ $consultant->person_code }}" {{ $entry->consultant ==
                                        $consultant->person_code ? 'selected' : '' }}>
                                        {{ $consultant->display_name ?? $consultant->username ?? '' }}
                                        - ({{ $consultant->employee_code ?? '' }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Delivery Date Type <span class="required-mark">*</span></label>
                                <div class="mt-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="delivery_type"
                                            id="delivery_expected" value="Expected" {{ old('delivery_type',
                                            $entry->del_type) == 'Expected' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="delivery_expected">Expected</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="delivery_type"
                                            id="delivery_confirmed" value="Confirmed" {{ old('delivery_type',
                                            $entry->del_type) == 'Confirmed' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="delivery_confirmed">Confirmed</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3 form-group">
                                <label for="expected_del_date">Delivery Date <span
                                        class="required-mark">*</span></label>
                                <input type="text" name="expected_del_date" id="expected_del_date"
                                    class="form-control flatpickr"
                                    value="{{ $entry->del_date ? \Carbon\Carbon::parse($entry->del_date)->format('d-M-Y') : '' }}"
                                    placeholder="dd-mmm-yyyy" required>
                                <input type="hidden" name="expected_del_date_actual" id="hidden_expected_del_date"
                                    value="{{ $entry->del_date }}">
                            </div>
                            <div class="col-sm-3 form-group">
                                <label for="fin_mode">Finance Mode <span class="required-mark">*</span></label>
                                <select name="fin_mode" id="fin_mode" class="form-select" required>
                                    <option value="">Please Select...</option>
                                    <option value="In-house" {{ $entry->fin_mode == 'In-house' ? 'selected' : ''
                                        }}>In-house</option>
                                    <option value="Customer Self" {{ $entry->fin_mode == 'Customer Self' ? 'selected' :
                                        '' }}>Customer Self</option>
                                    <option value="Cash" {{ $entry->fin_mode == 'Cash' ? 'selected' : '' }}>Cash
                                    </option>
                                    <option value="Yet To Decide" {{ $entry->fin_mode == 'Yet To Decide' ? 'selected' :
                                        '' }}>Yet To Decide</option>
                                </select>
                            </div>

                            <!-- Financier -->
                            <div class="col-sm-3 form-group" id="financier_box"
                                style="display: {{ $entry->fin_mode == 'In-house' ? 'block' : 'none' }};">
                                <label for="financier">Financier <span class="required-mark">*</span></label>
                                <select name="financier" id="financier" class="form-select" {{ $entry->fin_mode
                                    == 'In-house' ? 'required' : 'disabled' }}>
                                    <option value="">Select Financier</option>
                                    @foreach($data['financiers'] as $fin)
                                    <option value="{{ $fin->id }}" data-short_name="{{ $fin->short_name }}" {{ $entry->
                                        financier == $fin->id ? 'selected' : '' }}>
                                        {{ $fin->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-sm-3 form-group" id="financier_short_name_box"
                                style="display: {{ $entry->fin_mode == 'In-house' ? 'block' : 'none' }};">
                                <label for="financier_short_name">Financier Short Name</label>
                                <input type="text" id="financier_short_name" class="form-control" readonly
                                    value="{{ $entry->financier ? collect($data['financiers'])->firstWhere('id', $entry->fin_mode == 'In-house' ? $entry->financier : null)->short_name ?? '' : '' }}">
                            </div>

                            <div class="col-sm-3 form-group" id="loan_status_box"
                                style="display: {{ $entry->fin_mode == 'In-house' ? 'block' : 'none' }};">
                                <label for="loan_status">Loan File Status <span class="required-mark">*</span></label>
                                <select name="loan_status" id="loan_status" class="form-select" {{ $entry->fin_mode ==
                                    'In-house' ? 'required' : 'disabled' }}>
                                    <option value="">Please Select...</option>
                                    <option value="Pending" {{ $entry->loan_status == 'Pending' ? 'selected' : ''
                                        }}>Pending</option>
                                    <option value="Complete" {{ $entry->loan_status == 'Complete' ? 'selected' : ''
                                        }}>Complete</option>
                                </select>
                            </div>

                        </div>

                        @php
                        $matchedSegmentValue = collect($data['segments'])->first(fn($s) => (string)$s->code ===
                        (string)$entry->segment_code)?->name ?? '';
                        $showMakeOrder = in_array($matchedSegmentValue, ['Personal', 'BEV']);
                        \Log::info('🔍 [EDIT-MAKEORDER] Make Order visibility check', [
                        'entry_segment_code' => $entry->segment_code,
                        'matched_segment_value' => $matchedSegmentValue,
                        'show_make_order' => $showMakeOrder,
                        ]);
                        @endphp
                        <div class="row mt-3" id="make_order_container"
                            style="display: {{ $showMakeOrder ? 'block' : 'none' }};">
                            <div class="col-sm-12">
                                <div class="form-check">
                                    <input type="checkbox" name="make_order" id="make_order" value="1"
                                        class="form-check-input" {{ $entry->order == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold" for="make_order">
                                        Do you want to create a new sales order (SO Number) against this booking?
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-sm-12 form-group">
                                <label for="details">Remarks <span class="required-mark">*</span></label>
                                <textarea name="details" id="details" class="form-control" rows="3"
                                    required>{{ $entry->details }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-end">
                    <a href="{{ backpack_url('booking') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" id="submitBtn" class="btn btn-primary ms-2">Update Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/additional-methods.min.js"></script>

<script>
    $(document).ready(function() {
    console.log('🚀 [EDIT-INIT] Document ready — initializing Select2');
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

    
    const segVal = $('#segment').val();
    console.log('🔍 [EDIT-SEGMENT] After Select2 init — #segment val:', segVal,
                '| PHP data-selected-segment attr:', $('#segment').data('selected-segment'));

    if (!segVal) {
        // PHP selected attr not picked up — try to force it from data attribute
        const fallback = $('#segment').data('selected-segment');
        if (fallback) {
            console.warn('⚠️ [EDIT-SEGMENT] val() is empty but data-selected-segment =', fallback,
                         '— forcing #segment value manually');
            $('#segment').val(String(fallback)).trigger('change.select2');
            console.log('🔍 [EDIT-SEGMENT] After manual set — #segment val:', $('#segment').val());
        } else {
            console.error('❌ [EDIT-SEGMENT] Both val() and data-selected-segment are empty! Check controller segment IDs vs DB segment_code.');
        }
    } else {
        console.log('✅ [EDIT-SEGMENT] Segment pre-selected OK =', segVal);
    }

    function toArray(data) {
        if (Array.isArray(data)) return data;
        if (data && typeof data === 'object') return Object.values(data);
        return [];
    }

    function populateSelect2(selectId, rawData, valueKey, textKey = valueKey, extraData = {}) {
        const $select = $(selectId);
        const data = toArray(rawData);

        $select.val(null).trigger('change.select2');
        $select.empty().append('<option value="">Please Select...</option>');

        data.forEach(item => {
            if (!item) return;

            let value = item[valueKey] || item.name || item.colr_name || item.id || item.chassis_no || item.value;
            let text  = item[textKey] || item.name || item.colr_name || item.chassis_no || item.value || item.id;

            if (!value || !text) return;

            const option = new Option(text, value, false, false);

            if (extraData.color) {
                option.dataset.code = item.model_code || item.code || '';
                option.dataset.vid  = item.vid || '';
            }

            if (extraData.variant) {
                option.dataset.seating = item.seating_capacity || item.seating || '';
            }

            if (extraData.accessory) {
                option.dataset.price = item.ndp || 0;
            }

            $select.append(option);
        });

        $select.prop('disabled', data.length === 0);
    }

    

    function updateAccessoriesAmount() {
        let total = 0;
        $('#accessories option:selected').each(function() {
            total += parseFloat($(this).data('price')) || 0;
        });
        $('#apack_amount').val(total);
    }


    $('#customercat').on('change', function() {
        const isFirm = this.value === 'Firm';
        $('#ownedByOption').toggle(isFirm);

        if (!isFirm && $('#care_of').val() === '5') {
            $('#care_of').val('').trigger('change');
        }

        $('#careofnamelabel').html(isFirm ? 'Owner Name <span class="required-mark">*</span>' : 'Care Of Name <span class="required-mark">*</span>');
    }).trigger('change');


    
    $('#segment').on('change', function() {
        const segmentId = $(this).val();
        console.log('🔄 [SEGMENT-CHANGE] User changed segment → val:', segmentId,
                    '| text:', $(this).find(':selected').text());

        populateSelect2('#model', []);
        populateSelect2('#variant', []);
        populateSelect2('#color', []);
        populateSelect2('#chassis', []);
        populateSelect2('#accessories', []);
        $('#seating').val('');
        $('#apack_amount').val(0);

        if (!segmentId) {
            console.warn('⚠️ [SEGMENT-CHANGE] No segmentId — aborting model fetch');
            return;
        }

        const url = '{{ route("get.models", ":segment_id") }}'.replace(':segment_id', segmentId);
        console.log('📡 [SEGMENT-CHANGE] Fetching models from:', url);
        $.get(url)
            .done(function(data) {
                console.log('✅ [SEGMENT-CHANGE] Models received:', Array.isArray(data) ? data.length : Object.keys(data).length, 'items', data);
                populateSelect2('#model', data, 'code', 'name');
                $('#model').prop('disabled', false);
            })
            .fail(function(xhr) {
                console.error('❌ [SEGMENT-CHANGE] get-models XHR failed', xhr.status, xhr.responseText);
            });
    });

    $('#model').on('change', function() {
        const model = $(this).val();
        populateSelect2('#variant', []);
        populateSelect2('#color', []);
        populateSelect2('#chassis', []);
        populateSelect2('#accessories', []);
        $('#seating').val('');
        $('#apack_amount').val(0);

        if (!model) return;

        const url = '{{ route("get.variants", ":model") }}'.replace(':model', encodeURIComponent(model));
        $.get(url).done(function(data) {
            populateSelect2('#variant', data, 'code', 'name', { variant: true });
            $('#variant').prop('disabled', false);
        });
    });

    $('#variant').on('change', function() {
        const variant = $(this).val();
        const segmentId = $('#segment').val();
        const modelVal = $('#model').val();
        populateSelect2('#color', []);
        populateSelect2('#chassis', []);
        populateSelect2('#accessories', []);
        // Seating comes from variant data-seating (set during populateSelect2)
        $('#seating').val($(this).find(':selected').attr('data-seating') || '');

        $('#apack_amount').val(0);

        if (!variant) return;

        const colorUrl = '{{ route("get.colors", ":variant") }}'.replace(':variant', encodeURIComponent(variant));
        $.get(colorUrl).done(function(data) {
            populateSelect2('#color', data, 'code', 'name', { color: true });
            $('#color').prop('disabled', false);
            updateFromColor();
        });

        if (segmentId && modelVal) {
            const accUrl = '{{ route("get.accessories", [":segment", ":model", ":variant"]) }}'
                .replace(':segment',  encodeURIComponent(segmentId))
                .replace(':model',    encodeURIComponent(modelVal))
                .replace(':variant',  encodeURIComponent(variant));

            $.get(accUrl).done(function(data) {
                populateSelect2('#accessories', data, 'part_no', 'item', { accessory: true });
                $('#accessories').prop('disabled', false);

                const savedAcc = {!! json_encode(array_filter(explode(',', trim($entry->accessories ?? '')))) !!};
                if (savedAcc.length > 0) {
                    $('#accessories').val(savedAcc).trigger('change.select2');
                }
                updateAccessoriesAmount();
            });
        }
    });

    $('#color').on('change', function() {
        const code = $(this).find(':selected').attr('data-code');
        updateFromColor();
        populateSelect2('#chassis', []);

        if (!code) return;

        const url = '{{ route("get.chasis", ":modelCode") }}'.replace(':modelCode', encodeURIComponent(code));
        $.get(url).done(function(data) {
            populateSelect2('#chassis', data, 'chassis_no', 'chassis_no');
            $('#chassis').prop('disabled', false);
        });
    });

    $('#accessories').on('change', updateAccessoriesAmount);

    
    async function restoreVehicleDetails() {
        const saved = {
            segment:  '{{ $entry->segment_code ?? '' }}',
            model:    '{{ addslashes($entry->model_code ?? '') }}',
            variant:  '{{ addslashes($entry->variant_code ?? '') }}',
            color:    '{{ addslashes($entry->color_code ?? '') }}',
            chassis:  '{{ addslashes($entry->chassis_no ?? '') }}',
            accessories: {!! json_encode(array_filter(explode(',', trim($entry->accessories ?? '')))) !!}
        };

        console.log('🔄 [RESTORE] Starting restoreVehicleDetails with saved:', saved);

        if (!saved.segment) {
            console.error('❌ [RESTORE] saved.segment is empty — cannot restore cascade. Check $entry->segment_code in DB vs segment IDs from XpricingHelper::getSegments().');
            return;
        }

        const currentSegVal = $('#segment').val();
        console.log('🔍 [RESTORE-STEP1] #segment val:', currentSegVal, '| expected:', saved.segment, '| match:', String(currentSegVal) === String(saved.segment));
        if (String(currentSegVal) !== String(saved.segment)) {
            console.warn('⚠️ [RESTORE-STEP1] Select2 not reflecting PHP selected attr — forcing val to:', saved.segment);
            $('#segment').val(String(saved.segment)).trigger('change');
            console.log('🔍 [RESTORE-STEP1] After force — #segment val:', $('#segment').val());
        } else {
            console.log('✅ [RESTORE-STEP1] Segment matched — no force needed');
        }

        const modelsUrl = '{{ route("get.models", ":segment_id") }}'.replace(':segment_id', saved.segment);
        console.log('📡 [RESTORE-STEP2] GET', modelsUrl);
        await $.get(modelsUrl)
            .done(function(data) {
                const count = Array.isArray(data) ? data.length : Object.keys(data).length;
                console.log('✅ [RESTORE-STEP2] Models received:', count, 'items. First 3:', JSON.stringify(toArray(data).slice(0,3)));
                if (count === 0) console.warn('⚠️ [RESTORE-STEP2] 0 models — check get-models controller for segment_id:', saved.segment);
                populateSelect2('#model', data, 'code', 'name');
                $('#model').val(saved.model).prop('disabled', false);
                const actualModel = $('#model').val();
                console.log('🔍 [RESTORE-STEP2] #model → set to:', saved.model, '| got:', actualModel);
                if (actualModel !== saved.model) {
                    const available = $('#model option').map(function(){ return $(this).val(); }).get();
                    console.warn('⚠️ [RESTORE-STEP2] model val mismatch! Available options:', available);
                }
            })
            .fail(function(xhr) {
                console.error('❌ [RESTORE-STEP2] get-models XHR failed', xhr.status, xhr.statusText, xhr.responseText.substring(0, 200));
            });

        if (!saved.model) {
            console.warn('⚠️ [RESTORE] saved.model empty — stopping after step 2');
            return;
        }

        const variantsUrl = '{{ route("get.variants", ":model") }}'.replace(':model', encodeURIComponent(saved.model));
        console.log('📡 [RESTORE-STEP3] GET', variantsUrl);
        await $.get(variantsUrl)
            .done(function(data) {
                const count = Array.isArray(data) ? data.length : Object.keys(data).length;
                console.log('✅ [RESTORE-STEP3] Variants received:', count, 'items');
                if (count === 0) console.warn('⚠️ [RESTORE-STEP3] 0 variants for model:', saved.model);
                populateSelect2('#variant', data, 'code', 'name', { variant: true });
                $('#variant').val(saved.variant).prop('disabled', false);
                // Set seating from the restored variant option
                const seating = $('#variant option:selected').attr('data-seating') || '';
                $('#seating').val(seating);
                console.log('🔍 [RESTORE-STEP3] #variant → set to:', saved.variant, '| got:', $('#variant').val(), '| seating:', seating);
            })
            .fail(function(xhr) {
                console.error('❌ [RESTORE-STEP3] get-variants XHR failed', xhr.status, xhr.statusText);
            });

        if (!saved.variant) {
            console.warn('⚠️ [RESTORE] saved.variant empty — stopping after step 3');
            return;
        }

        const colorsUrl = '{{ route("get.colors", ":variant") }}'.replace(':variant', encodeURIComponent(saved.variant));
        console.log('📡 [RESTORE-STEP4] GET', colorsUrl);
        await $.get(colorsUrl)
            .done(function(data) {
                const count = Array.isArray(data) ? data.length : Object.keys(data).length;
                console.log('✅ [RESTORE-STEP4] Colors received:', count, 'items');
                if (count === 0) console.warn('⚠️ [RESTORE-STEP4] 0 colors for variant:', saved.variant);
                populateSelect2('#color', data, 'code', 'name', { color: true });
                $('#color').val(saved.color).prop('disabled', false).trigger('change');
                console.log('🔍 [RESTORE-STEP4] #color → set to:', saved.color, '| got:', $('#color').val());
                updateFromColor();
                console.log('🔍 [RESTORE-STEP4] vh_id:', $('#vh_id').val(), '| seating:', $('#seating').val());
            })
            .fail(function(xhr) {
                console.error('❌ [RESTORE-STEP4] get-colors XHR failed', xhr.status, xhr.statusText);
            });

        if (saved.segment && saved.model && saved.variant) {
            const accUrl = '{{ route("get.accessories", [":segment", ":model", ":variant"]) }}'
                .replace(':segment',  encodeURIComponent(saved.segment))
                .replace(':model',    encodeURIComponent(saved.model))
                .replace(':variant',  encodeURIComponent(saved.variant));

            console.log('📡 [RESTORE-STEP5] GET', accUrl);
            await $.get(accUrl)
                .done(function(data) {
                    const count = Array.isArray(data) ? data.length : Object.keys(data).length;
                    console.log('✅ [RESTORE-STEP5] Accessories received:', count, 'items');
                    populateSelect2('#accessories', data, 'part_no', 'item', { accessory: true });
                    $('#accessories').prop('disabled', false);
                    if (saved.accessories.length > 0) {
                        $('#accessories').val(saved.accessories).trigger('change.select2');
                        console.log('🔍 [RESTORE-STEP5] After .val() — actual selection:', $('#accessories').val());
                    } else {
                        console.log('🔍 [RESTORE-STEP5] No saved accessories');
                    }
                    updateAccessoriesAmount();
                })
                .fail(function(xhr) {
                    console.error('❌ [RESTORE-STEP5] get-accessories XHR failed', xhr.status, xhr.statusText);
                });
        }

        console.log('🎉 [RESTORE] restoreVehicleDetails complete');
    }

    restoreVehicleDetails();
});
</script>

<script>
    function calculatePriceGap() {
        const expected = parseFloat($('#expected_price').val()) || 0;
        const offered = parseFloat($('#offered_price').val()) || 0;
        const bonus = parseFloat($('#exchange_bonus').val()) || 0;
        $('#difference').val(Math.round(expected - offered - bonus));
    }

    $(document).ready(function() {
        $('#buyer_type').on('change', function() {
            const type = this.value;
            const isAdditional = type === 'Additional Buy';
            const isExchange   = type === 'Exchange Buy';
            const isScrappage  = type === 'Scrappage';

const baseRequired = ['#enummaster1', '#vehicle_details'];
const extraMake    = ['#enummaster2', '#vehicle_details2'];

const exchangeFields = [
    '#registration_no',
    '#manufacturing_year',
    '#odometer_reading',
    '#expected_price',
    '#offered_price',
    '#exchange_bonus'
];

const scrappageFields = [
    '#registration_no',
    '#manufacturing_year'
];

            const allFields = baseRequired.concat(extraMake, exchangeFields, scrappageFields);
            allFields.forEach(id => {
                const $f = $(id);
                if (!$f.length) return;
                $f.prop('disabled', true).prop('required', false);
                $f.closest('.form-group').find('.required-mark').hide();
            });

            function enableRequired(ids) {
                ids.forEach(id => {
                    const $f = $(id);
                    if (!$f.length) return;
                    $f.prop('disabled', false).prop('required', true);
                    $f.closest('.form-group').find('.required-mark').show();
                    if ($f.hasClass('select2-hidden-accessible') || $f.hasClass('select2')) {
                        $f.trigger('change.select2');
                    }
                });
            }

            function enableOptional(ids) {
                ids.forEach(id => {
                    const $f = $(id);
                    if (!$f.length) return;
                    $f.prop('disabled', false).prop('required', false);
                    $f.closest('.form-group').find('.required-mark').hide();
                    if ($f.hasClass('select2-hidden-accessible') || $f.hasClass('select2')) {
                        $f.trigger('change.select2');
                    }
                });
            }

            if (isAdditional) {
                enableRequired(baseRequired);
                enableOptional(extraMake);
            } else if (isExchange) {
                enableRequired(baseRequired.concat(exchangeFields));
                calculatePriceGap();
            } else if (isScrappage) {
                enableRequired(baseRequired.concat(scrappageFields));
            }

            calculatePriceGap();
        });

        $('#buyer_type').trigger('change');

        calculatePriceGap();

        $('#expected_price, #offered_price, #exchange_bonus').on('input', calculatePriceGap);

        const $refFields = $('#ref_customer_name, #ref_mobile_no, #ref_existing_model, #ref_variant, #ref_chassis_reg_no');

        $('#referred_by_checkbox').on('change', function() {
            const isChecked = this.checked;

            if (isChecked) {
                $('#referred_by_fields').show();
                $refFields.prop('required', true);
                $('#referred_by_fields .required-mark').show();
            } else {
                // Hide section, clear values, remove required
                $('#referred_by_fields').hide();
                $refFields.val('').prop('required', false);
                $('#referred_by_fields .required-mark').hide();
            }
        });
    });
</script>
<script>
    $(document).ready(function() {

    $('#booking_mode').on('change', function() {
        const isOnline = this.value === 'Online';
        $('#refrence_no_group').toggle(isOnline);
        $('#refrence_no').prop('disabled', !isOnline).prop('required', isOnline);
        if (!isOnline) $('#refrence_no').val('');
    }).trigger('change');

    $('#booking_source').on('change', function() {
        const isDSA = this.value === 'DSA';
        $('#dsa_details_group').toggle(isDSA);
        $('#dsa_details').prop('disabled', !isDSA).prop('required', isDSA);
        if (!isDSA) $('#dsa_details').val('').trigger('change');
    }).trigger('change');


    $('#fin_mode').on('change', function() {
        const isInHouse = this.value === 'In-house';

        $('#financier_box, #loan_status_box, #financier_short_name_box').toggle(isInHouse);

        $('#financier, #loan_status').prop('disabled', !isInHouse).prop('required', isInHouse);

        $('#financier_box .required-mark, #loan_status_box .required-mark').toggle(isInHouse);

        if (!isInHouse) {
            $('#financier').val('').trigger('change');
            $('#loan_status').val('').trigger('change');
            $('#financier_short_name').val('');
        } else {
            const selectedOption = $('#financier option:selected');
            if (selectedOption.length && selectedOption.val()) {
                $('#financier_short_name').val(selectedOption.data('short_name') || '');
            }
        }
    }).trigger('change');

    $('#financier').on('change', function() {
        const shortName = $(this).find(':selected').data('short_name') || '';
        $('#financier_short_name').val(shortName);
    }).trigger('change');

    flatpickr('#expected_del_date', {
        dateFormat: "d-M-Y",
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates[0]) {
                $('#hidden_expected_del_date').val(selectedDates[0].toISOString().slice(0,10));
            }
        }
    });

});
</script>
<script>
    $(document).ready(function() {

    $('input[type="text"].uppercase').on('input', function() {
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(start, end);
    });

    $('#pan_no').mask('AAAAA0000A', { placeholder: 'ABCDE1234F' });
    $('#adhar_no').mask('0000-0000-0000', { placeholder: '1234-5678-9012' });
    
function toggleGSTField() {

    const checked = $('#gst_unregistered').is(':checked');
    const $gst = $('#gstn');

    if (checked) {

        $gst
            .val('')
            .prop('readonly', true)
            .css({
                'pointer-events': 'none',
                'background-color': '#e9ecef'
            });

        $('#gstn-required').hide();

    } else {

        $gst
            .prop('readonly', false)
            .css({
                'pointer-events': 'auto',
                'background-color': ''
            });

        $('#gstn-required').show();
    }

    $gst.valid();
}

    

    $.validator.addMethod('panFormat', function(value, element) {
        return this.optional(element) || /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(value);
    }, 'Please enter a valid PAN number e.g., ABCDE1234F');

    $.validator.addMethod('udaiFormat', function(value, element) {
        return this.optional(element) || /^\d{4}-\d{4}-\d{4}$/.test(value);
    }, 'Please enter a valid Aadhar No. e.g., 1234-5678-9012');

    $.validator.addMethod('gstnFormat', function(value, element) {

    if ($('#gst_unregistered').is(':checked')) {
        return true;
    }

    return this.optional(element) ||
        /^\d{2}[A-Z]{5}\d{4}[A-Z]{1}\d[A-Z0-9]{1}Z[A-Z0-9]$/.test(value);

}, 'Please enter a valid GSTIN e.g., 27ABCDE1234F1Z5');

    function calculateAge(dobDate) {
        const today = new Date();
        let age = today.getFullYear() - dobDate.getFullYear();
        const m = today.getMonth() - dobDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dobDate.getDate())) {
            age--;
        }
        return age;
    }

    flatpickr('#customer_dob', {
        dateFormat: "d-M-Y",
        allowInput: true,
        maxDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates[0]) {
                const age = calculateAge(selectedDates[0]);
                $('#customer_age').val(age);
                $('#hidden_customer_dob').val(instance.formatDate(selectedDates[0], 'Y-m-d'));

                if (age < 18) {
                    alert('Customer age cannot be below 18 years.');
                    instance.clear();
                    $('#customer_age').val('');
                    $('#hidden_customer_dob').val('');
                }
            }
        }
    });

    @if($entry->c_dob)
        const initialDob = moment('{{ \Carbon\Carbon::parse($entry->c_dob)->format('d-M-Y') }}', 'DD-MMM-YYYY');
        $('#customer_age').val(calculateAge(initialDob.toDate()));
    @endif

    $('#bookingForm').validate({
            rules: {
                name: { required: true },
                care_of: { required: true },
                mobile: { required: true, digits: true, minlength: 10, maxlength: 10 },
                alt_mobile: {
                    digits: true,
                    minlength: 10,
                    maxlength: 10
                },
                ref_mobile_no: {
        digits: true,
        minlength: 10,
        maxlength: 10
    },
                gender: { required: true },
                occupation: { required: true },
                pan_no: { panFormat: true },
                adhar_no: { udaiFormat: true },
                gstn: {
                    gstnFormat: true
                },
                customer_dob: { required: true },
                branch: { required: true },
                location_id: { required: true },
                'details': { required: true },
            },
            messages: {
                name: 'Please enter customer name',
                mobile: 'Please enter a valid 10-digit mobile number',
                alt_mobile: 'Please enter a valid 10-digit alternate mobile number',
                ref_mobile_no: {
                    digits: 'Only numbers are allowed',
                    minlength: 'Mobile number must be 10 digits',
                    maxlength: 'Mobile number must be 10 digits'
                },
                pan_no: 'Please enter a valid PAN (e.g., ABCDE1234F)',
                adhar_no: 'Please enter a valid Aadhar (e.g., 1234-5678-9012)',
                gstn: {
            required: 'Please enter GSTIN',
            gstnFormat: 'Please enter a valid GSTIN (e.g., 27ABCDE1234F1Z5)'
            },
                customer_dob: 'Please select customer date of birth',
            },
            errorElement: 'span',
            errorClass: 'text-danger',
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            }
        });
        toggleGSTField();
        $('#gst_unregistered').on('change', toggleGSTField);

    });
$(document).ready(function() {

    const isFirm = '{{ $entry->b_cat }}' === 'Firm';

    if (isFirm) {
        $('#care_of option[value=""], #care_of option[value="1"], #care_of option[value="2"], #care_of option[value="3"], #care_of option[value="4"]').remove();

        if ($('#care_of option[value="5"]').length === 0) {
            $('#care_of').append('<option value="5" selected>Owned By</option>');
        } else {
            $('#care_of').val('5').trigger('change');
        }

        $('#careofnamelabel').html('Owner Name <span class="required-mark">*</span>');
    } else {
        $('#care_of option[value="5"]').remove();

        if ($('#care_of').val() === '5') {
            $('#care_of').val('').trigger('change');
        }

        $('#careofnamelabel').html('Care Of Name <span class="required-mark">*</span>');
    }

    
});

    $(document).ready(function () {

    

    let selectedLocation = "{{ $entry->location_code }}";
    let otherLocation = "{{ $entry->location_other }}";

    $('#branch').on('change', function () {

        let branchCode = $(this).val();

        $('#location').html('<option value="">Loading...</option>');

        if (!branchCode) {
            $('#location').html('<option value="">Please Select...</option>');
            return;
        }

        $.ajax({
            url: "{{ url('admin/get-locations') }}/" + branchCode,
            type: "GET",
            success: function(response) {

    console.log('response=', response);

    let options = '<option value="">Please Select...</option>';

    $.each(response, function(key, location) {

        console.log(
            'DB Location =',
            selectedLocation,
            'API Code =',
            location.code
        );

        let selected =
            String(selectedLocation).trim() === String(location.code).trim()
                ? 'selected'
                : '';

        options += `
            <option value="${location.code}" ${selected}>
                ${location.name} - ${location.code}
            </option>
        `;
    });

    options += `<option value="0">OTHER</option>`;

    $('#location').html(options);

    console.log('Final selected=', $('#location').val());
}

                // OTHER option
                let otherSelected = selectedLocation == 0 ? 'selected' : '';

                options += `<option value="0" ${otherSelected}>OTHER</option>`;

                $('#location').html(options).trigger('change');
            }
        });
    });

    $('#location').on('change', function () {

        if ($(this).val() == '0') {

            $('#location_other_group').show();

            $('#location_other')
                .prop('disabled', false)
                .val(otherLocation);

        } else {

            $('#location_other_group').hide();

            $('#location_other')
                .prop('disabled', true)
                .val('');
        }
        if ($('#location option:not([value=""])').length === 0) {
            $('#branch').trigger('change');
        } else {
            $('#location').trigger('change.select2');
        }
    });


    $('#branch').trigger('change');
});


</script>
@endsection