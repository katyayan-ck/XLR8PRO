@extends(backpack_view('blank'))

@section('title', $title)

@push('after_styles')
    <style>
        .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); }
        .form-control:focus, .form-select:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25); }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">{{ $title }}</h2>
                </div>
                
                <div class="card-body">
                    <form method="POST" action="{{ isset($entry) ? backpack_url('testdrive/'.$entry->id) : backpack_url('testdrive') }}">
                        @csrf
                        @if (isset($entry))
                            @method('PUT')
                        @endif

                        <div class="row">
                            @if(isset($entry))
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Test Drive No.</label>
                                <input type="text" class="form-control" value="{{ $entry->test_drive_no }}" readonly>
                            </div>
                            @endif

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Enquiry No. <span class="text-danger">*</span></label>
                                <input type="text" name="enquiry_no" class="form-control" value="{{ old('enquiry_no', $entry->enquiry_no ?? '') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name', $entry->customer_name ?? '') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Customer Phone <span class="text-danger">*</span></label>
                                <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $entry->customer_phone ?? '') }}" required maxlength="15">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control" value="{{ old('model', $entry->model ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Variant</label>
                                <input type="text" name="variant" class="form-control" value="{{ old('variant', $entry->variant ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stage <span class="text-danger">*</span></label>
                                <select name="stage" class="form-control form-select" required>
                                    <option value="">Select Stage</option>
                                    <option value="Scheduled" {{ old('stage', $entry->stage ?? '') == 'Scheduled' ? 'selected' : '' }}>Scheduled</option>
                                    <option value="Completed" {{ old('stage', $entry->stage ?? '') == 'Completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="Cancelled" {{ old('stage', $entry->stage ?? '') == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    <option value="No Show" {{ old('stage', $entry->stage ?? '') == 'No Show' ? 'selected' : '' }}>No Show</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sales Consultant Code</label>
                                <input type="text" name="sc_code" class="form-control" value="{{ old('sc_code', $entry->sc_code ?? '') }}">
                            </div>
                        </div>

                        <hr class="mt-2 mb-4">
                        <h4 class="mb-3 text-primary">Timings</h4>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Scheduled Start</label>
                                <input type="datetime-local" name="scheduled_td_start_time" class="form-control" value="{{ old('scheduled_td_start_time', $entry->scheduled_td_start_time ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Scheduled End</label>
                                <input type="datetime-local" name="scheduled_td_end_time" class="form-control" value="{{ old('scheduled_td_end_time', $entry->scheduled_td_end_time ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Actual Start</label>
                                <input type="datetime-local" name="actual_td_start_time" class="form-control" value="{{ old('actual_td_start_time', $entry->actual_td_start_time ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Actual End</label>
                                <input type="datetime-local" name="actual_td_end_time" class="form-control" value="{{ old('actual_td_end_time', $entry->actual_td_end_time ?? '') }}">
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="la la-save"></i> {{ isset($entry) ? 'Update' : 'Schedule' }}
                            </button>
                            <a href="{{ backpack_url('testdrive') }}" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection