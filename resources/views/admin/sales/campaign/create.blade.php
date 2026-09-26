@extends(backpack_view('blank'))

@section('title', isset($campaign) ? 'Edit Campaign' : 'Add New Campaign')

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
        /* Make read-only flatpickr inputs look clickable */
        .flatpickr-input[readonly] {
            background-color: var(--tblr-card-bg);
            cursor: pointer;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            {{ $title }}
                        </h4>
                    </div>

                    <form method="POST" action="{{ isset($campaign) ? backpack_url('sales/campaign/' . $campaign->id) : backpack_url('sales/campaign') }}">
                        @csrf
                        @if (isset($campaign))
                            @method('PUT')
                        @endif

                        <div class="card-body">
                            <div class="row">

                                {{-- Activity Name --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Activity Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control" required value="{{ old('name', $campaign->name ?? '') }}">
                                </div>

                                {{-- Segment --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Segment <span class="text-danger">*</span>
                                    </label>
                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                        <option value="">Select Segment</option>
                                        @foreach ($segments as $code => $name)
                                            <option value="{{ $code }}" @selected(old('segment_code', $campaign->segment_code ?? '') == $code)>
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
                                        @foreach ($models as $code => $name)
                                            <option value="{{ $code }}" @selected(old('model_code', $campaign->model_code ?? '') == $code)>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Activity Type --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Activity Type <span class="text-danger">*</span>
                                    </label>
                                    <select name="activity_code" class="form-control form-select" required>
                                        <option value="">Select Activity Type</option>
                                        @foreach ($activity_types as $item)
                                            <option value="{{ $item['code'] }}" @selected(old('activity_code', $campaign->activity_code ?? '') == $item['code'])>
                                                {{ $item['value'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Start Date (Flatpickr) --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Start Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="start_date" name="start_date" class="form-control" required placeholder="YYYY-MM-DD"
                                        value="{{ old('start_date', isset($campaign) && $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('Y-m-d') : '') }}">
                                </div>

                                {{-- End Date (Flatpickr) --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        End Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="end_date" name="end_date" class="form-control" required placeholder="YYYY-MM-DD"
                                        value="{{ old('end_date', isset($campaign) && $campaign->end_date ? \Carbon\Carbon::parse($campaign->end_date)->format('Y-m-d') : '') }}">
                                </div>

                                {{-- NEW: Forever Checkbox (Aligned nicely, labelled 'Is Forever') --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label d-block">&nbsp;</label> <!-- Spacer to align with End Date -->
                                    <div class="form-check d-flex align-items-center">
                                        <input type="hidden" name="forever" value="0">
                                        <input class="form-check-input mb-0 me-2" type="checkbox" name="forever" id="forever" value="1" 
                                            {{ old('forever', $campaign->forever ?? 0) == 1 ? 'checked' : '' }} 
                                            style="width: 1.5rem; height: 1.5rem; cursor: pointer; margin-top: 0;">
                                        <label class="form-check-label mb-0" for="forever" style="cursor: pointer; padding-top: 3px;">
                                            Is Forever ?
                                        </label>
                                    </div>
                                </div>

                                {{-- Dealer Branch --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        Dealer Branch <span class="text-danger">*</span>
                                    </label>
                                    <select name="branch_code" id="branch_code" class="form-control form-select" required>
                                        <option value="">Select Dealer Branch</option>
                                        @foreach ($branches as $code => $name)
                                            <option value="{{ $code }}" @selected(old('branch_code', $campaign->branch_code ?? '') == $code)>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Dealer Location --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        Dealer Location <span class="text-danger">*</span>
                                    </label>
                                    <select name="location_code" id="location_code" class="form-control form-select" required>
                                        <option value="">Select Dealer Location</option>
                                        @foreach ($locations as $code => $name)
                                            <option value="{{ $code }}" @selected(old('location_code', $campaign->location_code ?? '') == $code)>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                Save Campaign
                            </button>
                            <a href="{{ backpack_url('sales/campaign') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(function() {
            // Track old values to re-select if validation fails on Create
            let pendingModelCode = "{{ old('model_code', '') }}";
            let pendingLocationCode = "{{ old('location_code', '') }}";

            /*
            |--------------------------------------------------------------------------
            | Reusable AJAX Dropdown Helper
            |--------------------------------------------------------------------------
            */
            function fetchDropdownData(url, targetSelector, placeholder, selectedValue, clearPendingVarCallback) {
                const $target = $(targetSelector);
                $target.html('<option>Loading...</option>').prop('disabled', true);

                $.get(url, function(response) {
                    let html = `<option value="">${placeholder}</option>`;
                    $.each(response, function(code, name) {
                        let selected = (code == selectedValue) ? 'selected' : '';
                        html += `<option value="${code}" ${selected}>${name}</option>`;
                    });
                    
                    $target.html(html).prop('disabled', false);
                    if (typeof clearPendingVarCallback === 'function') clearPendingVarCallback();
                }).fail(function() {
                    $target.html(`<option value="">${placeholder}</option>`).prop('disabled', false);
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Smart Flatpickr Initialization
            |--------------------------------------------------------------------------
            */
            const startPicker = flatpickr("#start_date", {
                dateFormat: "Y-m-d",
                allowInput: true,
                onChange: function(selectedDates, dateStr) {
                    endPicker.set('minDate', dateStr); // Prevent end date before start date
                }
            });

            const endPicker = flatpickr("#end_date", {
                dateFormat: "Y-m-d",
                allowInput: true,
                onChange: function(selectedDates, dateStr) {
                    startPicker.set('maxDate', dateStr); // Prevent start date after end date
                }
            });

            // Set constraints on load if values exist
            if ($('#start_date').val()) endPicker.set('minDate', $('#start_date').val());
            if ($('#end_date').val()) startPicker.set('maxDate', $('#end_date').val());

            /*
            |--------------------------------------------------------------------------
            | Segment -> Model
            |--------------------------------------------------------------------------
            */
            $('#segment_code').change(function() {
                let segment = $(this).val();
                if (!segment) {
                    $('#model_code').html('<option value="">Select Model</option>').prop('disabled', true);
                    return;
                }
                fetchDropdownData(
                    "{{ backpack_url('sales/campaign/models') }}/" + segment, 
                    '#model_code', 
                    'Select Model', 
                    pendingModelCode, 
                    () => { pendingModelCode = ''; }
                );
            });

            /*
            |--------------------------------------------------------------------------
            | Branch -> Location
            |--------------------------------------------------------------------------
            */
            $('#branch_code').change(function() {
                let branch = $(this).val();
                if (!branch) {
                    $('#location_code').html('<option value="">Select Dealer Location</option>').prop('disabled', true);
                    return;
                }
                fetchDropdownData(
                    "{{ backpack_url('sales/campaign/locations') }}/" + branch, 
                    '#location_code', 
                    'Select Dealer Location', 
                    pendingLocationCode, 
                    () => { pendingLocationCode = ''; }
                );
            });

            /*
            |--------------------------------------------------------------------------
            | Execute on Validation Fails (Create Mode Only)
            |--------------------------------------------------------------------------
            */
            @if (!isset($campaign))
                if ($('#segment_code').val()) $('#segment_code').trigger('change');
                if ($('#branch_code').val()) $('#branch_code').trigger('change');
            @endif
        });
    </script>
@endpush