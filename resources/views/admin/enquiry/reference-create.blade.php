@extends(backpack_view('blank'))

@section('title', 'Add Reference Enquiry')

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
                    <h2 class="mb-0">Add Reference Enquiry</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('enquiry.reference.store') }}" enctype="multipart/form-data">
                        @csrf
                        
                        {{-- =========================== REQUESTED VISIBLE FIELDS =========================== --}}
                        
                        <!-- Referee Details -->
                        <h3 class="mb-0 ms-3" style="color: #000 !important;">Referee Details</h3>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Referee Contact No. <span class="text-danger">*</span></label>
                                    <!-- Changed to text, strictly restricted to 10 numbers -->
                                    <input type="text" name="referee_phone" id="referee_phone" class="form-control" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Referee Name <span class="text-danger">*</span></label>
                                    <input type="text" name="referee_name" id="referee_name" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Details -->
                        <h3 class="mb-0 ms-3" style="color: #000 !important;">Customer Information</h3>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Customer First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Customer Last Name <small class="text-muted"></small></label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Customer Contact No. <span class="text-danger">*</span></label>
                                    <!-- Changed to text, strictly restricted to 10 numbers -->
                                    <input type="text" id="mobile" name="mobile" class="form-control" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" required>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Details -->
                        <h3 class="mb-0 ms-3" style="color: #000 !important;">Vehicle Details</h3>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Segment <span class="text-danger">*</span></label>
                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                        <option value="">Select Segment</option>
                                        @foreach ($segments as $code => $name)
                                            <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Model <span class="text-danger">*</span></label>
                                    <select name="model_code" id="model_code" class="form-control form-select" required disabled>
                                        <option value="">Select Model</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Variant <small class="text-muted"></small></label>
                                    <select name="variant_code" id="variant_code" class="form-control form-select" disabled>
                                        <option value="">Select Variant</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-start flex-wrap px-4 mt-2 mb-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="la la-save"></i> Save Reference Enquiry
                            </button>
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
        // Segment -> Model Logic
        $('#segment_code').on('change', function() {
            let segmentCode = $(this).val();
            $('#model_code, #variant_code').html('<option value="">Select Option</option>').prop('disabled', true);
            
            if(segmentCode) {
                $.get("{{ backpack_url('enquiry/models') }}/" + segmentCode, function(response) {
                    let html = '<option value="">Select Model</option>';
                    $.each(response, function(code, value) { 
                        html += `<option value="${code}">${value}</option>`; 
                    });
                    $('#model_code').html(html).prop('disabled', false);
                });
            }
        });

        // Model -> Variant Logic
        $('#model_code').on('change', function() {
            let modelCode = $(this).val();
            $('#variant_code').html('<option value="">Select Option</option>').prop('disabled', true);
            
            if(modelCode) {
                $.get("{{ backpack_url('enquiry/variants') }}/" + modelCode, function(response) {
                    let html = '<option value="">Select Variant</option>';
                    $.each(response, function(code, item) { 
                        html += `<option value="${code}">${item.name}</option>`; 
                    });
                    $('#variant_code').html(html).prop('disabled', false);
                });
            }
        });
    });
</script>
@endpush