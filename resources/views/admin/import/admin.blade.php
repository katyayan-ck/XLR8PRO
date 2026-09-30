@extends(backpack_view('blank'))

@section('title', 'Admin Imports')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Main Header -->
                <h2 class="mb-4 text-body fw-bold">Admin Imports</h2>

                <!-- Vehicle Import Card -->
                <div class="card mb-4">
                    <div class="card-header bg-surface border-bottom">
                        <h4 class="card-title mb-0 fw-bold text-body">
                            <i class="la la-car me-2"></i>Vehicle Import
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <!-- Left: Last Imported At -->
                            <div>
                                <small class="text-muted d-block">Last Imported At</small>
                                <span class="fw-semibold text-body">
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
