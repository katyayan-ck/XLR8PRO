@extends(backpack_view('blank'))

@section('title', 'Edit Spare Order Request')

@push('after_styles')
<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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
                <div class="card-header bg-warning text-dark">
                    <h2 class="mb-0">Edit Spare Order Request</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('spare-request/'.$spareRequest->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Service Branch <span class="required-mark">*</span></label>
                                <select name="srv_brnch_id" class="form-control" required>
                                    @foreach($data['branch'] ?? [] as $branch)
                                    <option value="{{ $branch['id'] }}" {{ $spareRequest->srv_brnch_id == $branch['id']
                                        ? 'selected' : '' }}>
                                        {{ $branch['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Service Category <span class="required-mark">*</span></label>
                                <select name="srv_vh_cat_id" class="form-control" required>
                                    <option value="19704" {{ $spareRequest->srv_vh_cat_id == 19704 ? 'selected' : ''
                                        }}>Vehicle in Workshop</option>
                                    <option value="19709" {{ $spareRequest->srv_vh_cat_id == 19709 ? 'selected' : ''
                                        }}>Vehicle with Customer</option>
                                    <option value="19711" {{ $spareRequest->srv_vh_cat_id == 19711 ? 'selected' : ''
                                        }}>Counter Sale</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Workshop Type <span class="required-mark">*</span></label>
                                <select name="workshop_type_id" class="form-control" required>
                                    <option value="19705" {{ $spareRequest->workshop_type_id == 19705 ? 'selected' : ''
                                        }}>Bodyshop</option>
                                    <option value="19706" {{ $spareRequest->workshop_type_id == 19706 ? 'selected' : ''
                                        }}>Workshop</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Model <span class="required-mark">*</span></label>
                                <select name="model" id="model" class="form-control select2" required>
                                    <option value="">Select Model</option>
                                    @foreach($data['models'] ?? [] as $model)
                                    <option value="{{ $model['name'] }}" {{ $spareRequest->model == $model['name'] ?
                                        'selected' : '' }}>
                                        {{ $model['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Variant <span class="required-mark">*</span></label>
                                <select name="variant" id="variant" class="form-control select2" required>
                                    <option value="{{ $spareRequest->variant }}">{{ $spareRequest->variant }}</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Customer Name <span class="required-mark">*</span></label>
                                <input type="text" name="cust_name" class="form-control"
                                    value="{{ $spareRequest->cust_name }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Mobile Number <span class="required-mark">*</span></label>
                                <input type="text" name="cust_mobile" class="form-control"
                                    value="{{ $spareRequest->cust_mobile }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Vehicle / Chassis No <span class="required-mark">*</span></label>
                                <input type="text" name="regn_no" class="form-control"
                                    value="{{ $spareRequest->regn_no }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>R.O. Number <span class="required-mark">*</span></label>
                                <input type="text" name="ro_number" id="ro_no" class="form-control"
                                    value="{{ $spareRequest->ro_number }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>R.O. Date <span class="required-mark">*</span></label>
                                <input type="text" name="ro_date" class="form-control flatpickr"
                                    value="{{ $spareRequest->ro_date?->format('Y-m-d') }}" required>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label>Remarks</label>
                                <textarea name="remark" class="form-control"
                                    rows="3">{{ $spareRequest->remark }}</textarea>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Request
                            </button>
                            <a href="{{ backpack_url('spare-request') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
<script>
    flatpickr(".flatpickr", { dateFormat: "Y-m-d" });

    // Model → Variant Fetch (Safe Version)
    $('#model').on('change', function() {
        let modelName = $(this).val().trim();

        if (modelName) {
            $.get("{{ url('admin/get-variants') }}/" + encodeURIComponent(modelName), function(data) {
                $('#variant').empty().append('<option value="">Select Variant</option>');

                if (data && data.length > 0) {
                    $.each(data, function(key, v) {
                        $('#variant').append(`<option value="${v.name}">${v.name}</option>`);
                    });
                } else {
                    $('#variant').append('<option value="">No variants found</option>');
                }
            }).fail(function() {
                console.error('Failed to load variants');
                $('#variant').empty().append('<option value="">Error loading variants</option>');
            });
        } else {
            $('#variant').empty().append('<option value="">Select Variant</option>');
        }
    });
</script>
@endpush