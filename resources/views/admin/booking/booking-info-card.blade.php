<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">
            <i class="la la-info-circle"></i> Booking Information (Readonly)
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-3">
                <strong>Booking Date</strong>
                <p class="mb-0">{{ $booking->booking_date ?
                    \Carbon\Carbon::parse($booking->booking_date)->format('d-M-Y') : 'N/A' }}</p>
            </div>

            <div class="col-md-3">
                <strong>Customer Name</strong>
                <p class="mb-0">{{ $booking->name ?? 'N/A' }}</p>
            </div>

            <div class="col-md-3">
                <strong>Branch</strong>
                <p class="mb-0">{{ $branch_name ?? 'N/A' }}</p>
            </div>

            <div class="col-md-3">
                <strong>Location</strong>
                <p class="mb-0">
                    {{ $location_name ?? 'N/A' }}
                    @if($booking->location_other)
                    <br><small class="text-muted">(Other: {{ $booking->location_other }})</small>
                    @endif
                </p>
            </div>

            <div class="col-md-3">
                <strong>Model</strong>
                <p class="mb-0">{{ $booking->model_code ?? 'N/A' }}</p>
            </div>

            <div class="col-md-3">
                <strong>Variant</strong>
                <p class="mb-0">{{ $booking->variant_code ?? 'N/A' }}</p>
            </div>

            <div class="col-md-3">
                <strong>Color</strong>
                <p class="mb-0">{{ $booking->color_code ?? 'N/A' }}</p>
            </div>

            <!-- अगर बाद में और fields चाहिए तो यहीं extend कर सकते हो -->

        </div>
    </div>
</div>