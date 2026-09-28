@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2><span class="text-capitalize">{{ $title ?? 'Pricing Workflow' }}</span></h2>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            @if (session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <div class="card-header"><strong>Process status</strong></div>
                <div class="card-body">
                    @if ($session)
                        <p>
                            <span class="badge badge-primary">Active session #{{ $session->id }}</span>
                            Stage: <code>{{ $session->current_stage }}</code>
                            &nbsp;|&nbsp; Status: <code>{{ $session->status }}</code>
                            &nbsp;|&nbsp; WEF: <code>{{ $session->wef_date?->format('Y-m-d') }}</code>
                        </p>
                        <pre class="small bg-light p-2">{{ json_encode($session->stats, JSON_PRETTY_PRINT) }}</pre>
                        <div class="mt-3">
                            @if ($session->current_stage === 'awaiting_vehicle')
                                <a href="{{ route('pricing.workflow.vehicle-info-form') }}" class="btn btn-primary">Complete
                                    Vehicle Info</a>
                                <a href="{{ route('pricing.workflow.vehicle-info-export', $session->id) }}"
                                    class="btn btn-outline-secondary">Download Vehicle Info</a>
                            @elseif(in_array($session->current_stage, ['importing_prices', 'detecting']))
                                <a href="{{ route('pricing.workflow.prices-form') }}" class="btn btn-primary">Import
                                    Prices</a>
                            @elseif(in_array($session->current_stage, ['awaiting_addons', 'importing_addons']))
                                <a href="{{ route('pricing.workflow.addons-form') }}" class="btn btn-primary">Addons &amp;
                                    Discounts</a>
                                <a href="{{ route('pricing.workflow.impact-summary-view', $session->id) }}"
                                    class="btn btn-info">Impact Summary</a>
                            @elseif($session->current_stage === 'awaiting_rules')
                                <a href="{{ route('pricing.workflow.rules-form') }}" class="btn btn-primary">Insurance
                                    &amp; RTO</a>
                                <a href="{{ route('pricing.workflow.impact-summary-view', $session->id) }}"
                                    class="btn btn-info">Impact Summary</a>
                            @elseif(in_array($session->current_stage, ['calculating', 'summary']))
                                <a href="{{ route('pricing.workflow.impact-summary-view', $session->id) }}"
                                    class="btn btn-info">Impact Summary</a>
                            @else
                                <a href="{{ route('pricing.workflow.impact-summary-view', $session->id) }}"
                                    class="btn btn-info">Impact Summary</a>
                            @endif

                            <form action="{{ route('pricing.workflow.discard') }}" method="POST" class="d-inline"
                                onsubmit="return confirm('Discard this process and roll back session-tagged data?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">Discard process</button>
                            </form>
                        </div>
                    @else
                        <p class="text-muted mb-3">No pricing process is running. Start a new one by uploading Price List
                            sheets.</p>
                        <a href="{{ route('pricing.workflow.start-form') }}" class="btn btn-success">Start Pricing
                            Process</a>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong>FRS v3.1 flow</strong></div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li>Upload Pricing.xlsx — multi-select Price List sheets (PV / CV / LMM / BEV / CSD)</li>
                        <li>System detects new Model Codes → FRESH incomplete profiles</li>
                        <li>Download Vehicle Info → complete or mark Inactive → re-upload</li>
                        <li>Import prices with WEF (same or updated file)</li>
                        <li>Review Addons / optional Insurance &amp; RTO</li>
                        <li>Impact Summary → Calculate &amp; Publish → Reopen holds</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection
