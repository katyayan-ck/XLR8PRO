@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <i class="la la-camera text-primary"></i> Upload Delivery Photos
        <small class="d-none d-md-inline">for Booking #{{ $booking->id }}</small>
    </h2>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Upload Delivery Photos - Booking #{{ $booking->id }}</h4>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('booking.delivery-photos.update', $booking->id) }}"
                    enctype="multipart/form-data" id="photoUploadForm">
                    @csrf
                    @method('PUT')

                    <div class="row photo-upload-group">
                        @php
                        $collections = [
                        'delivery_ceremony_with_customer' => 'Delivery Ceremony (With Customer)',
                        'bonnet' => 'Bonnet',
                        'windshield_glass' => 'Windshield Glass',
                        'vehicle_driver_side' => 'Vehicle (Driver Side)',
                        'vehicle_co_driver_side' => 'Vehicle (Co Driver Side)',
                        'vehicle_rear_side' => 'Vehicle (Rear Side)',
                        'tire_front_driver_side' => 'Tyre (Front Driver Side)',
                        'tire_front_co_driver_side' => 'Tyre (Front Co Driver Side)',
                        'tire_rear_driver_side' => 'Tyre (Rear Driver Side)',
                        'tire_rear_co_driver_side' => 'Tyre (Rear Co Driver Side)',
                        'stepney' => 'Stepney',
                        'foot_rest_driver_side' => 'Foot Rest (Driver Side)',
                        'foot_rest_co_driver_side' => 'Foot Rest (Co Driver Side)',
                        'tool_kit' => 'Tool Kit',
                        'vehicle_chassis_no_photo' => 'Vehicle Chassis No.',
                        'chassis_no_screenshot_invoice' => 'Chassis No. Screenshot (Invoice)',
                        'chassis_no_screenshot_insurance' => 'Chassis No. Screenshot (Insurance)',
                        ];
                        @endphp

                        @foreach ($collections as $key => $label)
                        <div class="col-md-4 mb-4">
                            <div class="form-group">
                                <label for="{{ $key }}">{{ $label }} <span class="text-danger">*</span></label>
                                <input type="file" name="{{ $key }}" id="{{ $key }}"
                                    class="form-control @error($key) is-invalid @enderror" accept="image/*" required
                                    onchange="previewPhoto('{{ $key }}')">
                                @error($key)
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="image-preview-container mt-2" id="{{ $key }}_preview"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="form-group mt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="chassis_no_verified"
                                id="chassis_no_verified" value="1" required>
                            <label class="form-check-label" for="chassis_no_verified">
                                I have personally verified the vehicle chassis number with invoice copy and
                                insurance copy
                            </label>
                            @error('chassis_no_verified')
                            <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <label for="remarks">Remarks <span class="text-danger">*</span></label>
                        <textarea name="remarks" id="remarks"
                            class="form-control @error('remarks') is-invalid @enderror" rows="4" required></textarea>
                        @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mt-4 text-end">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="la la-upload"></i> Upload Photos & Mark as Delivered
                        </button>
                    </div>
                </form>

                <div class="card mt-5">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">Booking Details</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-4">Customer Information</h5>
                        <div class="row">
                            <div class="col-sm-4 form-group">
                                <label>Customer Name</label>
                                <input type="text" class="form-control" value="{{ $booking->name ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Mobile</label>
                                <input type="text" class="form-control" value="{{ $booking->mobile ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Email</label>
                                <input type="text" class="form-control" value="{{ $booking->email ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label>Address</label>
                                <input type="text" class="form-control" value="{{ $booking->address ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Pincode</label>
                                <input type="text" class="form-control" value="{{ $booking->pincode ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>City</label>
                                <input type="text" class="form-control" value="{{ $booking->city ?? 'N/A' }}" readonly>
                            </div>
                        </div>

                        <h5 class="mb-4 mt-5">Vehicle Information</h5>
                        <div class="row">
                            <div class="col-sm-4 form-group">
                                <label>Branch</label>
                                <input type="text" class="form-control" value="{{ $data['branch'] ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Location</label>
                                <input type="text" class="form-control" value="{{ $data['location'] ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Segment</label>
                                <input type="text" class="form-control"
                                    value="{{ $data['segments'][$booking->segment_id]['name'] ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Model</label>
                                <input type="text" class="form-control" value="{{ $booking->model_code ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Variant</label>
                                <input type="text" class="form-control" value="{{ $booking->variant_code ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Color</label>
                                <input type="text" class="form-control" value="{{ $booking->color_code ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label>Seating</label>
                                <input type="text" class="form-control" value="{{ $booking->seating ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label>Allotted Chassis Number</label>
                                <input type="text" class="form-control" value="{{ $data['bchasis'] ?? 'N/A' }}"
                                    readonly>
                            </div>
                        </div>

                        <h5 class="mb-4 mt-5">Booking Type & Source</h5>
                        <div class="row">
                            <div class="col-sm-4 form-group">
                                <label>Sales Consultant</label>
                                <input type="text" class="form-control"
                                    value="{{ collect($data['saleconsultants'])->firstWhere('id', $booking->consultant)['name'] ?? 'N/A' }} - ({{ collect($data['saleconsultants'])->firstWhere('id', $booking->consultant)['emp_code'] ?? 'N/A' }})"
                                    readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Finance Mode</label>
                                <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Financier</label>
                                <input type="text" class="form-control" value="{{ $data['financier'] ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Invoice Number</label>
                                <input type="text" class="form-control" value="{{ $booking->inv_no ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Invoice Date</label>
                                <input type="text" class="form-control"
                                    value="{{ $booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('d-M-Y') : 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Dealer Invoice No.</label>
                                <input type="text" class="form-control" value="{{ $booking->dealer_inv_no ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Dealer Invoice Date</label>
                                <input type="text" class="form-control"
                                    value="{{ $booking->dealer_inv_date ? \Carbon\Carbon::parse($booking->dealer_inv_date)->format('d-M-Y') : 'N/A' }}"
                                    readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    function previewPhoto(inputId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(inputId + '_preview');
        preview.innerHTML = '';

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '180px';
                img.style.borderRadius = '4px';
                img.style.border = '1px solid #ddd';
                preview.appendChild(img);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endpush