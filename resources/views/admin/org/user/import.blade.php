@extends(backpack_view('blank'))

@section('header')
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-10">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="card-title mb-0 fw-bold">Bulk import users</h2>
                <div class="d-flex gap-2">
                    @if (backpack_user()->can('ORG_USER_EXPORT'))
                        <a href="{{ route('org.user.export') }}" class="btn btn-primary btn-sm">
                            <i class="la la-file-excel me-1"></i> Export users &amp; RBAC
                        </a>
                    @endif
                    <a href="{{ route('org.user.import.template') }}" class="btn btn-outline-primary btn-sm">
                        <i class="la la-download me-1"></i> Download template
                    </a>
                    <a href="{{ backpack_url('org/user') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="la la-arrow-left me-1"></i> Back to users
                    </a>
                </div>
            </div>

            <div class="card-body">
                <p class="text-body-secondary mb-3">
                    Creates or updates the person, employee and user account (with contacts, address, bank
                    details, scopes and designation role) for each row of the <strong>Users_Import</strong> sheet.
                    Existing employees are matched by <strong>Emp Code</strong>, so re-importing the same file is safe.
                    Columns marked * are mandatory; designation, department, branch and location must match existing masters.
                </p>
                <p class="text-body-secondary mb-3">
                    To change existing users, use <strong>Export users &amp; RBAC</strong>, edit the file (every master
                    value is a dropdown) and upload it here. In its <strong>User_Scopes</strong> sheet each row is one
                    branch, location, department, division, vertical, segment, sub segment, model or variant; for every
                    user listed there, the listed rows replace that user's scopes. The file's Instructions sheet has the details.
                </p>

                <form method="POST" action="{{ route('org.user.import.process') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-8">
                        <label for="file" class="form-label fw-semibold">Workbook (.xlsx / .xls, max 10 MB)</label>
                        <input type="file" id="file" name="file" accept=".xlsx,.xls" required
                               class="form-control @error('file') is-invalid @enderror">
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="submit" class="btn btn-primary w-100" id="importButton">
                            <i class="la la-upload me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if ($result)
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0 fw-bold">Result</h3>
                </div>
                <div class="card-body">
                    @if ($result['error'])
                        <div class="alert alert-danger mb-3" role="alert">{{ $result['error'] }}</div>
                    @endif

                    <div class="row g-2 mb-3">
                        @foreach (['created' => 'success', 'updated' => 'primary', 'skipped' => 'warning', 'failed' => 'danger'] as $key => $tone)
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <div class="text-body-secondary small text-capitalize">{{ $key }}</div>
                                    <div class="fs-3 fw-bold text-{{ $tone }}">{{ $result['summary'][$key] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($result['scopes'])
                        <p class="mb-3">
                            <strong>User_Scopes:</strong>
                            {{ $result['scopes']['users'] }} users updated ·
                            {{ $result['scopes']['inserted'] }} scopes added ·
                            {{ $result['scopes']['activated'] }} re-activated ·
                            {{ $result['scopes']['deactivated'] }} removed ·
                            <span class="{{ $result['scopes']['skipped_users'] ? 'text-warning' : '' }}">{{ $result['scopes']['skipped_users'] }} users skipped</span> ·
                            <span class="{{ $result['scopes']['failed_rows'] ? 'text-danger' : '' }}">{{ $result['scopes']['failed_rows'] }} invalid rows</span>
                        </p>
                    @endif

                    @if (count($result['issues']))
                        <h4 class="fw-semibold">Rows needing attention</h4>
                        <ul class="list-group list-group-flush small">
                            @foreach ($result['issues'] as $issue)
                                <li class="list-group-item px-0">{{ $issue }}</li>
                            @endforeach
                        </ul>
                    @elseif (! $result['error'])
                        <p class="text-success mb-0"><i class="la la-check-circle me-1"></i> All rows imported without issues.</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function () {
        const button = document.getElementById('importButton');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Importing…';
    });
</script>
@endpush
