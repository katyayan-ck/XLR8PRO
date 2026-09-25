@extends(backpack_view('blank'))

@section('title', 'Admin Imports')

@push('after_styles')
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Main Header -->
                <h2 class="mb-4 text-dark fw-bold">Admin Imports</h2>

                <!-- Vehicle Import Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="card-title mb-0 fw-bold text-dark">
                            <i class="la la-car me-2"></i>Vehicle Import
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <!-- Left: Last Imported At -->
                            <div>
                                <small class="text-muted d-block">Last Imported At</small>
                                <span class="fw-semibold text-dark">
                                    {{ $lastImportedAt ?? 'N/A' }}
                                </span>
                            </div>

                            <!-- Right: Import Button -->
                            <div>
                                <form action="{{ backpack_url('vehicle/import') }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to import latest data from Google Sheet?')"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm d-flex align-items-center gap-2">
                                        <i class="la la-cloud-download"></i>
                                        <span>Import Now</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
@endpush
