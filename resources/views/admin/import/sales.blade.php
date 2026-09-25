@extends(backpack_view('blank'))

@section('title', 'Process Exchange / Scrappage')

@push('after_styles')
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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
                <div class="card">
                    <div class="card-header text-black">
                        <h2 class="mb-0">Sales Imports</h2>
                    </div>

                </div>

                <div class="card">

                    {{-- HEADER --}}
                    <div
                        class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                            Int in Finance Dashboard
                        </h2>
                    </div>
                    <a href="{{ backpack_url('finance/import') }}"
                        class="btn btn-success btn-sm d-flex align-items-center gap-2"
                        onclick="return confirm('Are you sure you want to import latest data from Google Sheet?')">
                        <i class="la la-cloud-download"></i>
                        <span>Import Now</span>
                    </a>


                </div>
                <div class="card">

                    {{-- HEADER --}}
                    <div
                        class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                            Int in insurance Dashboard
                        </h2>
                    </div>
                    <a href="{{ backpack_url('insurance/import') }}"
                        class="btn btn-success btn-sm d-flex align-items-center gap-2"
                        onclick="return confirm('Are you sure you want to import latest data from Google Sheet?')">
                        <i class="la la-cloud-download"></i>
                        <span>Import Now</span>
                    </a>
                </div>
                <div class="card">

                    {{-- HEADER --}}
                    <div
                        class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                            Int in rto Dashboard
                        </h2>
                    </div>
                    <a href="{{ backpack_url('rto/import') }}"
                        class="btn btn-success btn-sm d-flex align-items-center gap-2"
                        onclick="return confirm('Are you sure you want to import latest data from Google Sheet?')">
                        <i class="la la-cloud-download"></i>
                        <span>Import Now</span>
                    </a>
                </div>
                <div class="card">
                    {{-- HEADER --}}
                    <div
                        class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                            enquiry Dashboard
                        </h2>
                    </div>
                    {{-- Optional Import Section --}}
                    <div class="p-3 border-bottom bg-white">
                        <div class="row align-items-end">
                            <div class="col-md-8">
                                <h5 class="mb-2 text-dark">
                                    <i class="la la-file-excel-o"></i> Import Enquiries from Excel
                                </h5>
                                <small class="text-muted">
                                    Upload Excel file containing enquiry data. First row should contain headers.
                                </small>
                            </div>
                            <div class="col-md-4">
                                <form action="{{ route('sales.enquiry.import') }}" method="POST"
                                    enctype="multipart/form-data" class="d-flex gap-2">
                                    @csrf
                                    <input type="file" name="excel_file" class="form-control form-control-sm"
                                        accept=".xlsx,.xls" required>
                                    <button type="submit" class="btn btn-success btn-sm px-4 text-nowrap">
                                        <i class="la la-upload"></i> Import
                                    </button>
                                </form>
                            </div>
                            <!-- Import Status Panel -->
                            <div class="p-3 border-bottom bg-white" id="importStatusPanel" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong id="importStatusTitle">Import in progress…</strong>
                                    <span class="text-muted small" id="importStatusPercent">0%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" id="importProgressBar" role="progressbar"
                                        style="width: 0%"></div>
                                </div>
                                <div class="small text-muted mt-2" id="importStatusDetail"></div>
                            </div>

                            <!-- Recent Imports -->
                            <div class="p-3 border-bottom bg-white">
                                <h6 class="text-muted mb-2">Recent Imports</h6>
                                <table class="table table-sm mb-0" id="importHistoryTable">
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
        (function() {
            const statusUrlBase = "{{ url('/' . config('backpack.base.route_prefix') . '/enquiry/import/status') }}";
            const historyUrl = "{{ route('sales.enquiry.import.history') }}";
            let pollTimer = null;

            function renderHistory(rows) {
                const tbody = document.querySelector('#importHistoryTable tbody');
                if (!rows.length) {
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
