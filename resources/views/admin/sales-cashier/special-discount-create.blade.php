@extends(backpack_view('blank'))

@section('title', 'Add Special Discount')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title mb-0">Add Special Discount</h3>
            </div>

            <form method="POST" action="{{ backpack_url('special-discount') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="row">

                        <div class=" col-md-3 mb-3">
                            <label for="deposit_date" class="form-label ">Deposit Date</label>
                            <input type="text" name="deposit_date" id="deposit_date" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="xceler8_booking_no" class="form-label ">Xceler8 Booking No.</label>
                            <input type="text" name="xceler8_booking_no" id="xceler8_booking_no" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="votf_no" class="form-label ">VOTF No.</label>
                            <input type="text" name="votf_no" id="votf_no" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="invoice_date" class="form-label ">Invoice Date</label>
                            <input type="text" name="invoice_date" id="invoice_date" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="customer_name" class="form-label ">Customer Name</label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="care_of" class="form-label ">Care Of</label>
                            <input type="text" name="care_of" id="care_of" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="contact_no" class="form-label ">Contact No.</label>
                            <input type="text" name="contact_no" id="contact_no" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="address" class="form-label ">Address</label>
                            <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="tehsil" class="form-label ">Tehsil</label>
                            <input type="text" name="tehsil" id="tehsil" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="district" class="form-label ">District</label>
                            <input type="text" name="district" id="district" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="contact_no_2" class="form-label ">Contact No.</label>
                            <input type="text" name="contact_no_2" id="contact_no_2" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="model" class="form-label ">Model</label>
                            <input type="text" name="model" id="model" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="variant" class="form-label ">Variant</label>
                            <input type="text" name="variant" id="variant" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="amount" class="form-label ">Amount</label>
                            <input type="number" name="amount" id="amount" class="form-control">
                        </div>
                        <div class=" col-md-3 mb-3">
                            <label for="attached_voucher" class="form-label ">Attached Voucher</label>
                            <input type="file" name="attached_voucher" id="attached_voucher" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-start gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="la la-save me-1"></i>Save Special Discount
                    </button>
                    <a href="{{ backpack_url('special-discount') }}" class="btn btn-secondary">Cancel</a>
                    
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
.card { border-radius:12px; overflow:hidden; }
.form-control:focus { border-color:#86b7fe; box-shadow:0 0 0 .2rem rgba(13,110,253,.15); }
</style>
@endpush

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="date"]').forEach(function (el) {
        if (typeof flatpickr !== 'undefined') flatpickr(el, { dateFormat:'d-m-Y', allowInput:true });
    });
    flatpickr("#deposit_date", {
        dateFormat: "Y-m-d",
        allowInput: true
    });

    flatpickr("#invoice_date", {
        dateFormat: "Y-m-d",
        allowInput: true
    });
});

</script>
@endpush
