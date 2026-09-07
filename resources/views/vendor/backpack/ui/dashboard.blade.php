@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">

    {{-- MY PROFILE & ACCESS CARD --}}
{{-- MY PROFILE & ACCESS CARD --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="la la-user me-2"></i>
                <h5 class="mb-0">My Profile & Access</h5>
                <span class="badge bg-white text-primary ms-auto">{{ $current_user_details['user_type'] ?? 'Emp' }}</span>
            </div>
            <div class="card-body">
                <div class="row g-4 align-items-center">

                    <!-- Avatar + Basic Info -->
                    <div class="col-md-3 text-center">
                        <div class="avatar avatar-xl mb-2 mx-auto" style="width:80px;height:80px;background:#0d6efd;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:600;">
                            {{ $current_user_details['avatar_initials'] ?? 'U' }}
                        </div>
                        <h5 class="mb-0">{{ $current_user_details['name'] }}</h5>
                        <small class="text-muted">{{ $current_user_details['username'] }}</small>
                        <div class="mt-2">
                            <strong>Designation:</strong><br>
                            {{ $current_user_details['designation'] ?? '—' }}
                        </div>
                        <div><strong>Mile ID:</strong> {{ $current_user_details['mile_id'] ?? '—' }}</div>
                    </div>

                    <!-- Primary -->
                    <div class="col-md-3">
                        <strong class="text-muted d-block mb-1">Primary</strong>
                        <div>Branch: <strong>{{ $current_user_details['primary_branch'] ?? '—' }}</strong></div>
                        <div>Location: <strong>{{ $current_user_details['primary_location'] ?? '—' }}</strong></div>
                        <div>Department: <strong>{{ $current_user_details['primary_department'] ?? '—' }}</strong></div>
                        <div>Division: <strong>{{ $current_user_details['primary_division'] ?? '—' }}</strong></div>
                        <div>Vertical: <strong>{{ $current_user_details['primary_vertical'] ?? '—' }}</strong></div>
                        <div>Segment: <strong>{{ $current_user_details['primary_segment'] ?? '—' }}</strong></div>
                        <div>Sub Segment: <strong>{{ $current_user_details['primary_sub_segment'] ?? '—' }}</strong></div>
                    </div>

                    <!-- Primary Contact -->
                    <div class="col-md-3">
                        <strong class="text-muted d-block mb-1">Primary Contact</strong>
                        <div>Mobile: <strong>{{ $current_user_details['primary_mobile'] ?? '—' }}</strong></div>
                        <div>Email: <strong>{{ $current_user_details['primary_email'] ?? '—' }}</strong></div>
                        <div>Address: <strong>{{ $current_user_details['primary_address'] ?? '—' }}</strong></div>
                        <div>Banking: <strong>{{ $current_user_details['primary_banking'] ?? '—' }}</strong></div>
                    </div>

                    <!-- All Access -->
                    <div class="col-md-3">
                        <strong class="text-muted d-block mb-1">All Access</strong>
                        @foreach($current_user_details['all_scopes'] ?? [] as $type => $codes)
                            <div><strong>{{ ucfirst($type) }}:</strong> {{ implode(', ', $codes) }}</div>
                        @endforeach
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Bikaner Motors Analytics</h2>
            <small class="text-muted">System performance overview</small>
        </div>
        <span class="badge bg-success px-3 py-2">Auto Refresh</span>
    </div>

    {{-- BE 6 SPORTEQ BANNER (Full Image - No Crop, No Metric Cards) --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="banner-container position-relative" style="width: 100%; overflow: hidden; border-radius: 14px;">
                        <img src="{{ asset('images/be6-sporteq-banner.webp') }}" alt="BE 6 SPORTEQ" class="w-100" style="display: block; height: auto;">
                        <div class="banner-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-end p-4" style="background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 50%);">
                            <div class="text-white">
                                <h2 class="fw-bold mb-1">BE 6 SPORTEQ</h2>
                                <p class="mb-0 opacity-75">Experience the future of driving</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('after_styles')
<style>
    .banner-container {
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }
</style>
@endpush

@push('after_scripts')
<script>
    // No chart scripts needed - charts removed
</script>
@endpush