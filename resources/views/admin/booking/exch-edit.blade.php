@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">

</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">


        <form id="purchaseTypeForm" class="forms-sample" method="POST"
            action="{{ route('exchange.update', $booking->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="mb-0">{{ __('Purchase Type Details') }}</h2>
                </div>
                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-sm-2 form-group">
                            <label class="form-label">Purchase Type <span class="required-mark">*</span></label>
                            <select name="buyer_type" id="buyer_type" class="form-control select2 form-select" required>
                                <option value="First time Buyer" {{ $booking->buyer_type == 'First time Buyer' ?
                                    'selected' : '' }}>First time
                                    Buyer</option>
                                <option value="Additional Buy" {{ $booking->buyer_type == 'Additional Buy' ?
                                    'selected' : '' }}>Additional Buy
                                </option>
                                <option value="Exchange Buy" {{ $booking->buyer_type == 'Exchange Buy' ?
                                    'selected' : '' }}>Exchange Buy
                                </option>
                                <option value="Scrappage" {{ $booking->buyer_type == 'Scrappage' ? 'selected' :
                                    '' }}>Scrappage</option>
                            </select>
                            @error('buyer_type') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-2 form-group">
                            <label class="form-label">Brand (Make 1) <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <select name="enum_master1" id="enum_master1" class="form-select select2">
                                <option value="0">Please Select...</option>
                                @foreach($data['enum_master'] as $enum)
                                <option value="{{ $enum->code }}" {{ ($booking->exist_oem1 ?? '') == ($enum->code ?? '')
                                    ? 'selected' : '' }}>
                                    {{ $enum->value }}
                                </option>
                                @endforeach
                            </select>
                            @error('enum_master1') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-3 form-group">
                            <label class="form-label">Model & Variant 1 <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="text" name="vehicle_details" id="vehicle_details" class="form-control"
                                value="{{ old('vehicle_details', $exchange->vehicle_details ?? $booking->vh1_detail ?? '') }}">
                            @error('vehicle_details') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-2 form-group">
                            <label class="form-label">Brand (Make 2) <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <select name="enum_master2" id="enum_master2" class="form-select select2">
                                <option value="0">Please Select...</option>
                                @foreach($data['enum_master'] as $enum)
                                <option value="{{ $enum->code }}" {{ ($booking->exist_oem2 ?? '') == ($enum->code ?? '')
                                    ? 'selected' : '' }}>
                                    {{ $enum->value }}
                                </option>
                                @endforeach
                            </select>
                            @error('enum_master2') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-3 form-group">
                            <label class="form-label">Model & Variant 2 <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="text" name="vehicle_details2" id="vehicle_details2" class="form-control"
                                value="{{ old('vehicle_details2', $exchange->vehicle_details2 ?? $booking->vh2_detail ?? '') }}">
                            @error('vehicle_details2') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-4 form-group">
                            <label class="form-label">Vehicle Registration No. <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="text" name="registration_no" id="registration_no"
                                class="form-control uppercase"
                                value="{{ old('registration_no', $exchange->registration_no ?? $booking->registration_no ?? '') }}">
                            @error('registration_no') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-4 form-group">
                            <label class="form-label">Vehicle Manufacturing Year <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="number" name="manufacturing_year" id="manufacturing_year" class="form-control"
                                value="{{ old('manufacturing_year', $exchange->manufacturing_year ?? $booking->make_year ?? '') }}">
                            @error('manufacturing_year') <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-sm-4 form-group">
                            <label class="form-label">Vehicle Odometer Reading <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="number" name="odometer_reading" id="odometer_reading" class="form-control"
                                value="{{ old('odometer_reading', $exchange->odometer_reading ?? $booking->odo_reading ?? '') }}">
                            @error('odometer_reading') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-3 form-group">
                            <label class="form-label">Used Vehicle Expected Price <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="number" name="expected_price" id="expected_price" class="form-control"
                                value="{{ old('expected_price', $exchange->expected_price ?? $booking->expected_price ?? '') }}">
                            @error('expected_price') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-3 form-group">
                            <label class="form-label">Used Vehicle Offered Price <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="number" name="offered_price" id="offered_price" class="form-control"
                                value="{{ old('offered_price', $exchange->offered_price ?? $booking->offered_price ?? '') }}">
                            @error('offered_price') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-3 form-group">
                            <label class="form-label">New Vehicle Exchange Bonus <span class="required-mark"
                                    style="display: none;">*</span></label>
                            <input type="number" name="exchange_bonus" id="exchange_bonus" class="form-control"
                                value="{{ old('exchange_bonus', $exchange->exchange_bonus ?? $booking->exchange_bonus ?? '') }}">
                            @error('exchange_bonus') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-3 form-group">
                            <label class="form-label">Price Gap</label>
                            <input type="text" id="difference" class="form-control readonly-field"
                                value="{{ ($booking->expected_price ?? 0) - (($booking->offered_price ?? 0) + ($booking->exchange_bonus ?? 0)) }}"
                                readonly>
                        </div>

                        <div class="col-sm-2 form-group">
                            <label class="form-label">{{ __('Verification Status') }} <span
                                    class="required-mark">*</span></label>
                            <select name="update" id="update" class="form-select">
                                <option value="1" {{ !$exchange || $exchange->verification_status == 1 ?
                                    'selected' : '' }}>
                                    Unverified</option>
                                <option value="2" {{ $exchange && $exchange->verification_status == 2 ?
                                    'selected' : '' }}>
                                    Verified (Data Match)</option>
                                <option value="3" {{ $exchange && $exchange->verification_status == 3 ?
                                    'selected' : '' }}>
                                    Verified (Data Mismatch)</option>
                            </select>
                            @error('update') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-2 form-group">
                            <label class="form-label">{{ __('Case Status') }} <span
                                    class="required-mark">*</span></label>
                            <select name="case_status" id="case_status" class="form-select">
                                <option value="1" {{ !$exchange || $exchange->case_status == 1 ? 'selected' : ''
                                    }}>In-Process
                                </option>
                                <option value="2" {{ $exchange && $exchange->case_status == 2 ? 'selected' : ''
                                    }}>Exchange Done
                                </option>
                                <option value="3" {{ $exchange && $exchange->case_status == 3 ? 'selected' : ''
                                    }}>Case Lost
                                </option>
                            </select>
                            @error('case_status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-sm-8">
                            <label class="form-label">{{ __('Remarks') }} <span class="required-mark">*</span></label>
                            <textarea name="remark" id="remark" class="form-control" rows="4"
                                required>{{ old('remark', $exchange->remark ?? '') }}</textarea>
                            <input type="hidden" name="id" value="{{ $booking->id }}">
                            <input type="hidden" name="dept" value="{{ $data['remark'] }}">
                            <input type="hidden" name="status" value="{{ $booking->pending != 0 ? 8 : 1 }}">
                            @error('remark') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                    </div>

                </div>

                <div class="card-footer bg-white text-end">
                    <button type="submit" id="submitBtn" class="btn btn-primary">
                        <i class="la la-save"></i> Save & Submit Details
                    </button>
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                        <i class="la la-arrow-left"></i> Back
                    </a>
                </div>
            </div>

        </form>

        <div class="card border-0  mb-4 mt-4">

            <h2 class="mb-2 mt-2 " style="margin-left: 15px">Booking & Customer Information (Read-only)</h2>

            <div class="card-body">
                <div class="row g-3">
                    {{-- Booking Details --}}
                    <div class="col-sm-2">
                        <label class="form-label">Customer Type <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->b_type }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Customer Category <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->b_cat }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Booking Date <span class="text-red">*</span></label>
                        <input type="text" class="form-control"
                            value="{{ \Carbon\Carbon::parse($booking->booking_date)->format('d-M-Y') }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Collection Type <span class="text-red">*</span></label>
                        <input type="text" class="form-control"
                            value="{{ $booking->col_type == 1 ? 'Receipt' : 'Field Collection' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Collected By</label>
                        <input type="text" class="form-control" value="{{ $data['collector_name'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        @if ($booking->b_cat != 'Firm')
                        <label class="form-label">Customer Name <span class="text-red">*</span></label>
                        @else
                        <label class="form-label">Firm Name <span class="text-red">*</span></label>
                        @endif
                        <input type="text" class="form-control" value="{{ $booking->name }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Care Of</label>
                        <input type="text" class="form-control"
                            value="{{ $booking->care_of_type == 5 ? 'Owned By' : ($booking->care_of_type == 1 ? 'Son of' : ($booking->care_of_type == 2 ? 'Daughter of' : ($booking->care_of_type == 3 ? 'Married' : ($booking->care_of_type == 4 ? 'Guardian Name' : 'N/A')))) }}"
                            readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Enter Name</label>
                        <input type="text" class="form-control" value="{{ $booking->care_of }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Contact No. <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->mobile }}" readonly>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Alternate Contact No.</label>
                        <input type="text" class="form-control" value="{{ $booking->alt_mobile ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Gender</label>
                        <input type="text" class="form-control" value="{{ $booking->gender ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Occupation</label>
                        <input type="text" class="form-control" value="{{ $booking->occ ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Buyer Type</label>
                        <input type="text" class="form-control" value="{{ $booking->buyer_type ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">PAN Card No.</label>
                        <input type="text" class="form-control" value="{{ $booking->pan_no ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Aadhar No.</label>
                        <input type="text" class="form-control" value="{{ $booking->adhar_no ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Customer D.O.B.</label>
                        <input type="text" class="form-control"
                            value="{{ \Carbon\Carbon::parse($booking->c_dob)->format('d-M-Y') ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">{{ __('Branch') }} <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $data['branch'] ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">{{ __('Location') }} <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $data['location'] ?? 'N/A' }}" readonly>
                    </div>
                </div>
            </div>

        </div>
        <div class="card border-0  mb-4 mt-4">

            <h2 class="mb-2 mt-2 " style="margin-left: 15px">Reffered by Details (Read-only)</h2>

            <div class="card-body">
                <div class="row g-3">
                    {{-- Referred By Details --}}
                    <div class="col-sm-4">
                        <label class="form-label">Referred By (Customer Name)</label>
                        <input type="text" class="form-control" value="{{ $booking->r_name ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Mobile No.</label>
                        <input type="text" class="form-control" value="{{ $booking->r_mobile ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Existing Model</label>
                        <input type="text" class="form-control" value="{{ $booking->r_model ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Variant</label>
                        <input type="text" class="form-control" value="{{ $booking->r_variant ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Chassis / Regn. No.</label>
                        <input type="text" class="form-control" value="{{ $booking->r_chassis ?? 'N/A' }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0  mb-4 mt-4">

            <h2 class="mb-2 mt-2 " style="margin-left: 15px">Vehicles Details (Read-only)</h2>

            <div class="card-body">
                <div class="row g-3">
                    {{-- Vehicle Details --}}
                    <div class="col-sm-3">
                        <label class="form-label">Segment <span class="text-red">*</span></label>
                        <input type="text" class="form-control"
                            value="{{ isset($data['segments'][$booking->segment_id]) ? (is_array($data['segments'][$booking->segment_id]) ? $data['segments'][$booking->segment_id]['name'] ?? 'N/A' : $data['segments'][$booking->segment_id]) : 'N/A' }}"
                            readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Model <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->model_code }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Variant <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->variant_code }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Color <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->color_code }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Seating</label>
                        <input type="text" class="form-control" value="{{ $booking->seating }}" readonly>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">{{ __('Accessories') }}</label>
                        <textarea class="form-control" rows="2" readonly>{{ $data['accessories'] }}</textarea>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Acces. Pack Amount</label>
                        <input type="text" class="form-control" value="{{ $booking->apack_amount ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Allotted Chassis Number</label>
                        <input type="text" class="form-control"
                            value="{{ $booking->chassis_no ?? ($data['bchasis'] ?? 'N/A') }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0  mb-2 mt-4">

            <h2 class="mb-2 mt-2 " style="margin-left: 15px">Booking Type & Source</h2>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-2">
                        <label class="form-label">Booking Mode <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->b_mode ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">{{ __('Online Book Ref No.') }}</label>
                        <input type="text" class="form-control" value="{{ $booking->online_bk_ref_no ?? 'N/A' }}"
                            readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Sales Consultant</label>
                        <input type="text" class="form-control"
                            value="{{ optional(collect($data['saleconsultants'])->firstWhere('id', $booking->consultant))['name'] }} - {{ optional(collect($data['saleconsultants'])->firstWhere('id', $booking->consultant))['mile_id'] }}"
                            readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Booking Source <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->b_source ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Selected DSA</label>
                        <input type="text" class="form-control" value="{{ $dsaname }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Delivery Date Type <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->del_type }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Date <span class="text-red">*</span></label>
                        <input type="text" class="form-control"
                            value="{{ \Carbon\Carbon::parse($booking->del_date)->format('d-M-Y') }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Booking Amount <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->booking_amount ?? 'N/A' }}"
                            readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Receipt No. <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->receipt_no ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Receipt Date <span class="text-red">*</span></label>
                        <input type="text" class="form-control"
                            value="{{ \Carbon\Carbon::parse($booking->receipt_date)->format('d-M-Y') }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">CPD Date <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->cpd ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Finance Mode <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->fin_mode }}" readonly>
                    </div>
                    <div class="col-sm-3" id="financier_box">
                        <label class="form-label">Financier</label>
                        <input type="text" class="form-control" value="{{ $booking->financier ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3" id="loan_status_box">
                        <label class="form-label">Loan File Status</label>
                        <input type="text" class="form-control" value="{{ $booking->loan_status ?? 'N/A' }}" readonly>
                    </div>
                    @if ($booking->status == 2)
                    <div class="col-sm-3">
                        <label class="form-label">Invoice Number <span class="text-red">*</span></label>
                        <input type="text" class="form-control" value="{{ $booking->inv_no }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Invoice Date <span class="text-red">*</span></label>
                        <input type="text" class="form-control"
                            value="{{ \Carbon\Carbon::parse($booking->inv_date)->format('d-M-Y') }}" readonly>
                    </div>
                    @endif
                    @if ($booking->status == 8)
                    <div class="col-sm-12">
                        <label class="form-label">{{ __('Pending Remarks') }}</label>
                        <textarea class="form-control" rows="2" readonly>{{ $booking->pending_remark }}</textarea>
                    </div>
                    @endif

                    <div class="col-sm-3">
                        <label class="form-label">SAP Booking No.</label>
                        <input type="text" class="form-control" value="{{ $booking->sap_no ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">DMS Booking Number</label>
                        <input type="text" class="form-control" value="{{ $booking->dms_no ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">DMS OTF No.</label>
                        <input type="text" class="form-control" value="{{ $booking->dms_otf ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">DMS OTF Date</label>
                        <input type="text" class="form-control" value="{{ $booking->otf_date ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">DMS SO No.</label>
                        <input type="text" class="form-control" value="{{ $booking->dms_so ?? 'N/A' }}" readonly>
                    </div>
                </div>
            </div>
        </div>


    </div>


    <div class="row mt-2">
        <div class="col-12">
            @include('partials.entity-history', [
            'threads' => $bookingHistory ?? collect()
            ])
        </div>
    </div>

</div>
</div>
@endsection

@push('after_styles')
<link rel="stylesheet" href="{{ asset('plugins/select2/dist/css/select2.min.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
    }

    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 5px rgba(220, 53, 69, 0.5);
    }

    .required-mark {
        color: #dc3545;
        margin-left: 2px;
    }

    .readonly-field {
        background-color: #f8f9fa;
        border-color: #ced4da;
        pointer-events: none;
    }

    .form-control[readonly],
    .form-select[readonly] {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }

    .uppercase {
        text-transform: uppercase;
    }

    .text-red {
        color: #dc3545;
    }
</style>
@endpush

@push('after_scripts')
<script src="{{ asset('plugins/select2/dist/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    (function($) {
        'use strict';
        function initPurchaseTypeForm() {
            initSelect2();
            initValidation();
            setInitialState();
            bindEventListeners();
        }
        function initSelect2() {
            $('.select2').select2();
            $('#enum_master1').val('{{ $booking->exist_oem1 ?? 0 }}').trigger('change');
            $('#enum_master2').val('{{ $booking->exist_oem2 ?? 0 }}').trigger('change');
        }
        function initValidation() {
            $('#purchaseTypeForm').validate({
                ignore: ':disabled',
                rules: {
                    buyer_type: {
                        required: true
                    },
                    enum_master1: {
                        required: function() {
                            return ['Additional Buy', 'Exchange Buy', 'Scrappage'].includes($(
                                '#buyer_type').val());
                        }
                    },
                    vehicle_details: {
                        required: function() {
                            return ['Additional Buy', 'Exchange Buy', 'Scrappage'].includes($(
                                '#buyer_type').val());
                        }
                    },
                    enum_master2: {
                        required: function() {
                            return $('#buyer_type').val() === 'Additional Buy';
                        }
                    },
                    vehicle_details2: {
                        required: function() {
                            return $('#buyer_type').val() === 'Additional Buy';
                        }
                    },
                    registration_no: {
                        required: function() {
                            return ['Exchange Buy', 'Scrappage'].includes($('#buyer_type').val());
                        }
                    },
                    manufacturing_year: {
                        required: function() {
                            return ['Exchange Buy', 'Scrappage'].includes($('#buyer_type').val());
                        }
                    },
                    odometer_reading: {
                        required: function() {
                            return $('#buyer_type').val() === 'Exchange Buy';
                        }
                    },
                    expected_price: {
                        required: function() {
                            return $('#buyer_type').val() === 'Exchange Buy';
                        }
                    },
                    offered_price: {
                        required: function() {
                            return $('#buyer_type').val() === 'Exchange Buy';
                        }
                    },
                    exchange_bonus: {
                        required: function() {
                            return $('#buyer_type').val() === 'Exchange Buy';
                        }
                    },
                    update: {
                        required: true
                    },
                    remark: {
                        required: true
                    }
                },
                messages: {
                    buyer_type: 'Please select purchase type',
                    enum_master1: 'Please select brand (Make 1)',
                    vehicle_details: 'Please enter model & variant 1',
                    enum_master2: 'Please select brand (Make 2)',
                    vehicle_details2: 'Please enter model & variant 2',
                    registration_no: 'Please enter vehicle registration number',
                    manufacturing_year: 'Please enter manufacturing year',
                    odometer_reading: 'Please enter odometer reading',
                    expected_price: 'Please enter expected price',
                    offered_price: 'Please enter offered price',
                    exchange_bonus: 'Please enter exchange bonus',
                    update: 'Please select verification status',
                    remark: 'Please enter remarks'
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('text-danger');
                    error.insertAfter(element);
                },
                highlight: function(element) {
                    $(element).removeClass('is-valid').addClass('is-invalid');
                },
                unhighlight: function(element) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                },
                submitHandler: function(form) {
                    if ($('#purchaseTypeForm').valid()) {
                        form.submit();
                    }
                }
            });
        }
        function setInitialState() {
            const initialBuyerType = $('#buyer_type').val();
            togglePurchaseFields(initialBuyerType, true); // Preserve values on initial load
        }
        function bindEventListeners() {
            $('#buyer_type').on('change', function() {
                togglePurchaseFields($(this).val(), false); // Clear values on change
            });
            $('#expected_price, #offered_price, #exchange_bonus').on('input', calculatePriceGap);
        }
        function togglePurchaseFields(type, preserveValues = false) {
            const fields = {
                base: ['#enum_master1', '#vehicle_details', '#enum_master2', '#vehicle_details2'],
                exchange: ['#registration_no', '#manufacturing_year', '#odometer_reading', '#expected_price',
                    '#offered_price', '#exchange_bonus'
                ],
                scrappage: ['#registration_no', '#manufacturing_year']
            };
            function toggleFields(enable, ids) {
                ids.forEach(id => {
                    $(id).prop('disabled', !enable).prop('required', enable);
                    toggleRequiredMark(id, enable);
                    if (!enable && !preserveValues) {
                        if (id === '#enum_master1' || id === '#enum_master2') {
                            $(id).val('0').trigger('change');
                        } else {
                            $(id).val('');
                        }
                    }
                });
            }
            toggleFields(false, [...fields.base, ...fields.exchange]);
            if (type === 'Additional Buy') toggleFields(true, fields.base);
            else if (type === 'Exchange Buy') toggleFields(true, [fields.base[0], fields.base[1], ...fields
                .exchange
            ]);
            else if (type === 'Scrappage') toggleFields(true, [fields.base[0], fields.base[1], ...fields
                .scrappage
            ]);
            calculatePriceGap();
        }
function calculatePriceGap() {
    const expected = $('#expected_price').val() || 0;
    const offered = $('#offered_price').val() || 0;
    const bonus = $('#exchange_bonus').val() || 0;

    $.ajax({
        url: '/api/v1/pricing/calculate-exchange',
        method: 'POST',
        data: {
            expected_price: expected,
            offered_price: offered,
            exchange_bonus: bonus,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if(response.success) {
                $('#difference').val(response.difference);
            }
        },
        error: function(xhr) {
            console.error("Pricing Engine Error", xhr);
        }
    });
}
        function toggleRequiredMark(selector, show) {
            $(selector).siblings('label').find('.required-mark').css('display', show ? 'inline' : 'none');
        }
        $(document).ready(initPurchaseTypeForm);
    })(jQuery);
</script>
@endpush