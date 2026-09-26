@extends(backpack_view('blank'))

@section('title', 'Sales Imports')

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
                <h2 class="mb-4 text-dark fw-bold">
                    <i class="la la-shopping-cart me-2"></i>Sales Imports
                </h2>

                <!-- Int in Finance Dashboard Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="card-title mb-0 fw-bold text-dark">
                            <i class="la la-money-bill me-2"></i>Int in Finance Dashboard
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <small class="text-muted d-block">
                                    <i class="la la-clock me-1"></i>Last Updated At
                                </small>
                                <span class="fw-semibold text-dark">{{ $lastFinanceImport ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <a href="{{ backpack_url('finance/import') }}"
                                    class="btn btn-success btn-sm d-flex align-items-center gap-2"
                                    onclick="return confirm('Are you sure you want to import latest data from Google Sheet?')">
                                    <i class="la la-cloud-download"></i>
                                    <span>Import Now</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Int in Insurance Dashboard Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="card-title mb-0 fw-bold text-dark">
                            <i class="la la-shield-alt me-2"></i>Int in Insurance Dashboard
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <small class="text-muted d-block">
                                    <i class="la la-clock me-1"></i>Last Updated At
                                </small>
                                <span class="fw-semibold text-dark">{{ $lastInsuranceImport ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <a href="{{ backpack_url('insurance/import') }}"
                                    class="btn btn-success btn-sm d-flex align-items-center gap-2"
                                    onclick="return confirm('Are you sure you want to import latest data from Google Sheet?')">
                                    <i class="la la-cloud-download"></i>
                                    <span>Import Now</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Int in RTO Dashboard Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="card-title mb-0 fw-bold text-dark">
                            <i class="la la-truck me-2"></i>Int in RTO Dashboard
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <small class="text-muted d-block">
                                    <i class="la la-clock me-1"></i>Last Updated At
                                </small>
                                <span class="fw-semibold text-dark">{{ $lastRtoImport ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <a href="{{ backpack_url('rto/import') }}"
                                    class="btn btn-success btn-sm d-flex align-items-center gap-2"
                                    onclick="return confirm('Are you sure you want to import latest data from Google Sheet?')">
                                    <i class="la la-cloud-download"></i>
                                    <span>Import Now</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enquiry Dashboard Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="card-title mb-0 fw-bold text-dark">
                            <i class="la la-question-circle me-2"></i>Enquiry Dashboard
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- File Upload Section -->
                        <div class="row align-items-center mb-4">
                            <div class="col-md-7">
                                <h6 class="mb-1 text-dark fw-bold">
                                    <i class="la la-file-excel-o me-1"></i> Import Enquiries from Excel
                                </h6>
                                <small class="text-muted d-block">
                                    Upload Excel file containing enquiry data. First row should contain headers.
                                </small>
                            </div>
                            <div class="col-md-5 mt-2 mt-md-0">
                                <form action="{{ route('sales.enquiry.import') }}" method="POST"
                                    enctype="multipart/form-data" class="d-flex gap-2">
                                    @csrf
                                    <input type="file" name="excel_file" class="form-control form-control-sm"
                                        accept=".xlsx,.xls" required>
                                    <button type="submit"
                                        class="btn btn-success btn-sm px-4 text-nowrap d-flex align-items-center gap-1">
                                        <i class="la la-upload"></i>
                                        <span>Import</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Progress Panel -->
                        <div class="border rounded p-3 mb-4 bg-light" id="importStatusPanel" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong id="importStatusTitle"><i class="la la-spinner la-spin me-1"></i>Import in
                                    progress…</strong>
                                <span class="text-muted small" id="importStatusPercent">0%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" id="importProgressBar" role="progressbar"
                                    style="width: 0%"></div>
                            </div>
                            <div class="small text-muted mt-2" id="importStatusDetail"></div>
                        </div>

                        <!-- Recent Imports Table -->
                        <div class="pt-2">
                            <h6 class="text-muted mb-3 fw-bold">
                                <i class="la la-history me-1"></i>Recent Imports
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="importHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                            <th>When</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="text-muted">Loading…</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <!-- Import Polling Script -->
    <script>
        function importWithGid() {
            const gid = document.getElementById('gidInput').value.trim();
            if (!gid) {
                alert('Please enter GID');
                return;
            }
            const url = `{{ backpack_url('rto/import') }}?gid=${gid}`;
            if (confirm('Import data from Google Sheet with GID: ' + gid + '?')) {
                window.location.href = url;
            }
        }
        (function() {
            const statusUrlBase = "{{ url('/' . config('backpack.base.route_prefix') . '/enquiry/import/status') }}";
            const historyUrl = "{{ route('sales.enquiry.import.history') }}";
            let pollTimer = null;

            function renderHistory(rows) {
                const tbody = document.querySelector('#importHistoryTable tbody');
                if (!rows || !rows.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No imports yet</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(r => {
                    const pct = r.total_rows > 0 ? Math.round((r.processed_rows / r.total_rows) * 100) : 0;
                    const badge = r.status === 'completed' ? 'success' :
                        r.status === 'failed' ? 'danger' :
                        'warning';
                    return `<tr>
                        <td>${r.file_name}</td>
                        <td><span class="badge bg-${badge}">${r.status}</span></td>
                        <td>${r.status === 'processing' ? pct + '%' : '-'}</td>
                        <td>${r.updated_at}</td>
                    </tr>`;
                }).join('');

                const newest = rows[0];
                if (newest && (newest.status === 'processing' || newest.status === 'queued')) {
                    startPolling(newest.id);
                }
            }

            function startPolling(id) {
                const panel = document.getElementById('importStatusPanel');
                panel.style.display = 'block';
                if (pollTimer) clearInterval(pollTimer);

                function tick() {
                    fetch(`${statusUrlBase}/${id}`).then(r => r.json()).then(data => {
                        document.getElementById('importProgressBar').style.width = data.percent + '%';
                        document.getElementById('importStatusPercent').innerText = data.percent + '%';
                        document.getElementById('importStatusDetail').innerText =
                            `${data.processed_rows} / ${data.total_rows} rows processed`;

                        if (data.status === 'completed') {
                            document.getElementById('importStatusTitle').innerText = 'Import completed ✅';
                            clearInterval(pollTimer);
                            setTimeout(() => location.reload(), 1500);
                        } else if (data.status === 'failed') {
                            document.getElementById('importStatusTitle').innerText = 'Import failed ❌';
                            document.getElementById('importStatusDetail').innerText = data.error_message ||
                                'Unknown error';
                            clearInterval(pollTimer);
                        }
                    }).catch(() => {});
                }

                tick();
                pollTimer = setInterval(tick, 3000);
            }

            fetch(historyUrl).then(r => r.json()).then(renderHistory);
        })();
    </script>
@endpush
