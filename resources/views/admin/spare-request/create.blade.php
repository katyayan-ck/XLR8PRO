@extends(backpack_view('blank'))
@section('title', 'Add New Spare Order Request')

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .required-mark {
        color: red;
    }

    .dropdown-menu {
        max-height: 300px;
        overflow-y: auto;
        width: 100%;
        z-index: 9999;
    }

    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 5px rgba(220, 53, 69, 0.5);
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Add New Spare Order Request</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('spare-request') }}">
                        @csrf

                        <h5 class="mb-3">Order Information</h5>
                        <div class="row">

                            <!-- Service Branch -->
                            <div class="col-sm-3 form-group">
                                <label for="srv_brnch_id">Service Branch <span class="required-mark">*</span></label>
                                <select name="srv_brnch_id" class="form-control form-select" required>
                                    <option value="">Select Branch</option>
                                    @foreach($data['branch'] ?? [] as $branch)
                                    <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Service Category -->
                            <div class="col-sm-3 form-group">
                                <label for="srv_vh_cat_id">Service Category <span class="required-mark">*</span></label>
                                <select name="srv_vh_cat_id" class="form-control form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="19704">Vehicle in Workshop</option>
                                    <option value="19709">Vehicle with Customer</option>
                                    <option value="19711">Counter Sale</option>
                                </select>
                            </div>

                            <!-- Workshop Type -->
                            <div class="col-sm-3 form-group">
                                <label for="workshop_type_id">Workshop Type <span class="required-mark">*</span></label>
                                <select name="workshop_type_id" class="form-control form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="19705">Bodyshop</option>
                                    <option value="19706">Workshop</option>
                                </select>
                            </div>

                            <!-- Model -->
                            <div class="col-sm-3 form-group">
                                <label for="model">Model <span class="required-mark">*</span></label>
                                <select name="model" id="model" class="form-control select2" required>
                                    <option value="">Select Model</option>
                                    @foreach($data['models'] ?? [] as $model)
                                    <option value="{{ $model['name'] }}">{{ $model['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Variant -->
                            <div class="col-sm-3 form-group">
                                <label for="variant">Variant <span class="required-mark">*</span></label>
                                <select name="variant" id="variant" class="form-control select2" required>
                                    <option value="">Select Variant</option>
                                </select>
                            </div>

                            <!-- Customer Name -->
                            <div class="col-sm-3 form-group">
                                <label for="cust_name">Customer Name <span class="required-mark">*</span></label>
                                <input type="text" name="cust_name" id="cust_name" class="form-control" required>
                            </div>

                            <!-- Mobile -->
                            <div class="col-sm-3 form-group">
                                <label for="cust_mobile">Mobile Number <span class="required-mark">*</span></label>
                                <input type="text" name="cust_mobile" id="cust_mobile" class="form-control" required>
                            </div>

                            <!-- Regn No -->
                            <div class="col-sm-3 form-group">
                                <label for="regn_no">Vehicle / Chassis No <span class="required-mark">*</span></label>
                                <input type="text" name="regn_no" id="regn_no" class="form-control" required>
                            </div>

                            <!-- RO Number -->
                            <div class="col-sm-3 form-group">
                                <label for="ro_number">R.O. Number <span class="required-mark">*</span></label>
                                <input type="text" name="ro_number" id="ro_number" class="form-control" required>
                                <div id="ro_no_warning" class="text-danger mt-1" style="display:none;">RO Number already
                                    exists!</div>
                            </div>

                            <!-- RO Date -->
                            <div class="col-sm-3 form-group">
                                <label for="ro_date">R.O. Date <span class="required-mark">*</span></label>
                                <input type="text" name="ro_date" id="ro_date" class="form-control flatpickr" required>
                            </div>

                            <!-- Remarks -->
                            <div class="col-sm-12 form-group">
                                <label for="remark">Remarks</label>
                                <textarea name="remark" id="remark" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- Spare Parts Section -->
                        <h5 class="mb-3 mt-5">Spare Parts Information</h5>
                        <button type="button" class="btn btn-success mb-3" id="add-part-row">
                            <i class="fa fa-plus"></i> Add Part
                        </button>

                        <div id="parts-container"></div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">Save Spare Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {

    flatpickr(".flatpickr", { dateFormat: "Y-m-d", allowInput: true });
    $('.select2').select2({ placeholder: "Select...", allowClear: true });

    // ==================== ADD PART ROW ====================
    $('#add-part-row').on('click', function() {
        let rowHtml = `
        <div class="row part-row mt-3 border p-3 rounded bg-light">
            <div class="col-sm-3">
                <label>Part Number <span class="required-mark">*</span></label>
                <input type="text" name="part_no[]" class="form-control part-no-input" autocomplete="off" required>
                <ul class="dropdown-menu"></ul>
                <input type="hidden" name="part_id[]" class="part-id-input">
            </div>
            <div class="col-sm-4">
                <label>Part Description <span class="required-mark">*</span></label>
                <input type="text" name="part_name[]" class="form-control part-name-input" autocomplete="off" required>
                <ul class="dropdown-menu"></ul>
            </div>
            <div class="col-sm-2">
                <label>Order Type <span class="required-mark">*</span></label>
                <select name="order_type[]" class="form-control order-type-select" required>
                    <option value="">Select</option>
                    <option value="Customer Paid">Customer Paid</option>
                    <option value="Warranty">Warranty</option>
                    <option value="Goodwill">Goodwill</option>
                    <option value="FOC">FOC</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label>Req. Qty <span class="required-mark">*</span></label>
                <input type="number" name="req_quan[]" class="form-control" min="1" required>
            </div>
            <div class="col-sm-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger remove-part-row"><i class="fa fa-trash"></i></button>
            </div>
        </div>`;

        $('#parts-container').append(rowHtml);
    });

    // Remove Row
    $(document).on('click', '.remove-part-row', function() {
        $(this).closest('.part-row').remove();
    });

    // ==================== AUTOCOMPLETE ====================
    $(document).on('input', '.part-no-input, .part-name-input', function() {
        let input = $(this);
        let query = input.val().trim();
        let dropdown = input.siblings('.dropdown-menu');
        let type = input.hasClass('part-no-input') ? 'part_no' : 'name';

        if (query.length < 2) {
            dropdown.hide();
            return;
        }

        $.ajax({
            url: "{{ url('admin/fetch-parts') }}",
            method: "GET",
            data: { query: query, type: type },
            success: function(data) {
                let html = '';
                if (data.length > 0) {
                    data.forEach(part => {
                        html += `<li class="dropdown-item" data-id="${part.id}" data-part="${part.part_no}" data-name="${part.name}">
                                    ${part.part_no} - ${part.name}
                                 </li>`;
                    });
                } else {
                    html = `<li class="dropdown-item disabled">No results found</li>`;
                }
                dropdown.html(html).show();
            }
        });
    });

    // Select from dropdown
    $(document).on('click', '.dropdown-item', function() {
        let item = $(this);
        let row = item.closest('.part-row');
        let partNo = item.data('part');
        let partName = item.data('name');
        let partId = item.data('id');

        row.find('.part-no-input').val(partNo);
        row.find('.part-name-input').val(partName);
        row.find('.part-id-input').val(partId);

        item.closest('.dropdown-menu').hide();
    });

    // Hide dropdown on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.part-no-input, .part-name-input, .dropdown-menu').length) {
            $('.dropdown-menu').hide();
        }
    });

    // ==================== DUPLICATE CHECK (Part No + Order Type) ====================
    function isDuplicate(currentRow) {
        let partNo = currentRow.find('.part-no-input').val().trim().toUpperCase();
        let orderType = currentRow.find('.order-type-select').val();

        if (!partNo || !orderType) return false;

        let duplicate = false;
        $('.part-row').each(function() {
            if ($(this)[0] === currentRow[0]) return true;

            let existingNo = $(this).find('.part-no-input').val().trim().toUpperCase();
            let existingType = $(this).find('.order-type-select').val();

            if (existingNo === partNo && existingType === orderType) {
                duplicate = true;
                return false;
            }
        });
        return duplicate;
    }

    // Check on order type change and part selection
    $(document).on('change', '.order-type-select', function() {
        let row = $(this).closest('.part-row');
        if (isDuplicate(row)) {
            Swal.fire({
                icon: 'warning',
                title: 'Duplicate Entry',
                text: 'This Part Number with same Order Type is already added!',
                confirmButtonColor: '#d33'
            });
        }
    });

    // ==================== RO NUMBER CHECK ====================
    $('#ro_no').on('blur', function() {
        let ro = $(this).val().trim();
        if (ro) {
            $.get("{{ url('admin/check-ro-number') }}/" + encodeURIComponent(ro), function(exists) {
                if (exists) {
                    $('#ro_no_warning').show();
                } else {
                    $('#ro_no_warning').hide();
                }
            });
        }
    });

    // ==================== MODEL → VARIANT ====================
    $('#model').on('change', function() {
        let model = $(this).val();
        if (model) {
            $.get("{{ url('admin/get-variants') }}/" + encodeURIComponent(model), function(data) {
                $('#variant').empty().append('<option value="">Select Variant</option>');
                data.forEach(v => {
                    $('#variant').append(`<option value="${v.name}">${v.name}</option>`);
                });
            });
        }
    });

});
</script>
@endpush