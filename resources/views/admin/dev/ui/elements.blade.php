@extends('admin.dev.ui._layout')

@section('kit')
@php $colours = ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'dark']; @endphp
<div class="row row-cards">

    {{-- Buttons --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Buttons</h3></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($colours as $c) <button type="button" class="btn btn-{{ $c }}">{{ ucfirst($c) }}</button> @endforeach
                    <button type="button" class="btn">Default</button>
                    <button type="button" class="btn btn-link">Link</button>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($colours as $c) <button type="button" class="btn btn-outline-{{ $c }}">{{ ucfirst($c) }}</button> @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($colours as $c) <button type="button" class="btn btn-ghost-{{ $c }}">{{ ucfirst($c) }}</button> @endforeach
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <button type="button" class="btn btn-primary"><i class="la la-plus me-1"></i>With icon</button>
                    <button type="button" class="btn btn-icon btn-primary" aria-label="Add"><i class="la la-plus"></i></button>
                    <button type="button" class="btn btn-icon" aria-label="Settings"><i class="la la-cog"></i></button>
                    <button type="button" class="btn btn-pill btn-success">Pill</button>
                    <button type="button" class="btn btn-square">Square</button>
                    <button type="button" class="btn btn-primary btn-loading">Loading</button>
                    <button type="button" class="btn btn-primary" disabled>Disabled</button>
                    <button type="button" class="btn btn-sm">Small</button>
                    <button type="button" class="btn btn-lg">Large</button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="btn-group" role="group" aria-label="Period">
                        <button type="button" class="btn active">Day</button><button type="button" class="btn">Week</button><button type="button" class="btn">Month</button>
                    </div>
                    <div class="btn-list">
                        <button type="button" class="btn btn-facebook btn-icon" aria-label="Facebook"><i class="la la-facebook"></i></button>
                        <button type="button" class="btn btn-twitter btn-icon" aria-label="Twitter"><i class="la la-twitter"></i></button>
                        <button type="button" class="btn btn-google btn-icon" aria-label="Google"><i class="la la-google"></i></button>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" type="button">Split actions</button>
                        <div class="dropdown-menu">
                            <span class="dropdown-header">Quick actions</span>
                            <a class="dropdown-item" href="#"><i class="la la-file-pdf me-2"></i>Download PDF <span class="badge bg-primary-lt ms-auto">new</span></a>
                            <a class="dropdown-item" href="#"><i class="la la-whatsapp me-2"></i>Share on WhatsApp</a>
                            <div class="dropdown-divider"></div>
                            <label class="dropdown-item"><input class="form-check-input m-0 me-2" type="checkbox">Include accessories</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badges, status, tags --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Badges, status, tags</h3></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (['blue', 'azure', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'cyan'] as $c)
                        <span class="badge bg-{{ $c }} text-{{ $c }}-fg">{{ $c }}</span>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (['blue', 'azure', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'cyan'] as $c)
                        <span class="badge bg-{{ $c }}-lt">{{ $c }}</span>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge badge-outline text-primary">Outline</span>
                    <span class="badge badge-pill bg-red">12</span>
                    <span class="badge bg-green badge-dot" aria-label="online"></span>
                    <span class="status status-green"><span class="status-dot status-dot-animated"></span>Live</span>
                    <span class="status status-orange"><span class="status-dot"></span>Pending</span>
                    <span class="status status-red">Rejected</span>
                </div>
                <div class="tags-list">
                    <span class="tag">Nexon<a href="#" class="btn-close" aria-label="Remove"></a></span>
                    <span class="tag"><span class="legend bg-green me-1"></span>Finance</span>
                    <span class="tag"><span class="avatar avatar-xs bg-primary-lt me-1">PM</span>Priya</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Avatars --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Avatars</h3></div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $i => $size)
                        <span class="avatar avatar-{{ $size }} bg-{{ ['blue', 'green', 'purple', 'orange', 'red'][$i] }}-lt">RS</span>
                    @endforeach
                    <span class="avatar avatar-md rounded bg-primary-lt"><i class="la la-car fs-1"></i></span>
                    <span class="avatar avatar-md bg-azure-lt xl-avatar">PM<span class="badge bg-success"></span></span>
                </div>
                <div class="avatar-list avatar-list-stacked mb-3">
                    @foreach (['RS', 'AV', 'MI', 'SJ', 'VR'] as $i => $ini)
                        <span class="avatar avatar-sm rounded-circle bg-{{ ['blue', 'green', 'purple', 'orange', 'red'][$i] }}-lt">{{ $ini }}</span>
                    @endforeach
                    <span class="avatar avatar-sm rounded-circle">+8</span>
                </div>
                <div class="d-flex align-items-center">
                    <span class="avatar me-3 bg-primary-lt">KS</span>
                    <div><div class="fw-medium">Karan Singh</div><div class="text-secondary small">Sales Manager · Jaipur</div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Alerts</h3></div>
            <div class="card-body">
                @foreach (['success' => ['la-check-circle', 'Booking confirmed', 'Receipt RCPT/0192 was issued.'], 'info' => ['la-info-circle', 'Price list updated', 'New prices apply from tomorrow.'], 'warning' => ['la-exclamation-triangle', 'KYC pending', 'Aadhaar copy is missing.'], 'danger' => ['la-times-circle', 'Payment failed', 'The bank rejected the mandate.']] as $type => [$icon, $title, $text])
                    <div class="alert alert-{{ $type }} {{ $type === 'danger' ? 'alert-dismissible' : '' }}" role="alert">
                        <div class="d-flex gap-2">
                            <i class="la {{ $icon }} fs-2"></i>
                            <div><h4 class="alert-title">{{ $title }}</h4><div class="text-secondary">{{ $text }}</div></div>
                        </div>
                        @if ($type === 'danger') <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a> @endif
                    </div>
                @endforeach
                <div class="alert alert-important alert-primary mb-0" role="alert">Important (solid) alert for a single critical message.</div>
            </div>
        </div>
    </div>

    {{-- Tabs + accordion --}}
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                    <li class="nav-item" role="presentation"><a href="#kit-tab-1" class="nav-link active" data-bs-toggle="tab" role="tab" aria-selected="true">Overview</a></li>
                    <li class="nav-item" role="presentation"><a href="#kit-tab-2" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false" tabindex="-1">Documents <span class="badge bg-secondary-lt ms-1">4</span></a></li>
                    <li class="nav-item ms-auto" role="presentation"><a href="#kit-tab-3" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false" tabindex="-1" aria-label="Settings"><i class="la la-cog"></i></a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane active show" id="kit-tab-1" role="tabpanel"><h4>Overview</h4><p class="text-secondary mb-0">Tabs inside a card header — use for record sections.</p></div>
                    <div class="tab-pane" id="kit-tab-2" role="tabpanel"><h4>Documents</h4><p class="text-secondary mb-0">PAN, Aadhaar, invoice, insurance.</p></div>
                    <div class="tab-pane" id="kit-tab-3" role="tabpanel"><h4>Settings</h4><p class="text-secondary mb-0">Icon-only tab aligned right.</p></div>
                </div>
            </div>
        </div>
        <div class="accordion" id="kit-accordion">
            @foreach (['How is on-road price calculated?' => 'Ex-showroom + RTO + insurance + TCS + dealer charges, from the pricing engine.', 'Who approves extra discount?' => 'The approval engine, following the power sheet for the model and branch.'] as $q => $a)
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#kit-acc-{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">{{ $q }}</button></h2>
                    <div id="kit-acc-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#kit-accordion"><div class="accordion-body text-secondary">{{ $a }}</div></div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Cards --}}
    <div class="col-md-6 col-xl-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="avatar bg-primary text-white"><i class="la la-car fs-2"></i></span></div>
                    <div class="col"><div class="fw-medium">132 bookings</div><div class="text-secondary">12 waiting delivery</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-status-top bg-green"></div>
            <div class="card-body"><h3 class="card-title">Status top</h3><p class="text-secondary mb-0">Coloured strip for state.</p></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="ribbon ribbon-top bg-yellow"><i class="la la-star"></i></div>
            <div class="card-body"><h3 class="card-title">Ribbon</h3><p class="text-secondary mb-0">Highlight a featured card.</p></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-primary-lt">
            <div class="card-body"><h3 class="card-title">Tinted card</h3><p class="mb-0">Use <code>bg-*-lt</code>, never a hex colour.</p></div>
        </div>
    </div>

    {{-- Progress, steps, timeline --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Progress &amp; steps</h3></div>
            <div class="card-body">
                <div class="mb-3"><div class="d-flex mb-1"><span>Booking completeness</span><span class="ms-auto text-secondary">75%</span></div><div class="progress"><div class="progress-bar" style="width: 75%" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" aria-label="75%"></div></div></div>
                <div class="progress progress-separated mb-3">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 44%" aria-label="Petrol"></div>
                    <div class="progress-bar bg-info" role="progressbar" style="width: 19%" aria-label="Diesel"></div>
                    <div class="progress-bar bg-success" role="progressbar" style="width: 9%" aria-label="EV"></div>
                </div>
                <div class="progress progress-sm mb-4"><div class="progress-bar progress-bar-indeterminate bg-green"></div></div>
                <ul class="steps steps-green steps-counter my-2">
                    <li class="step-item">Enquiry</li><li class="step-item">Quote</li><li class="step-item active">Booking</li><li class="step-item">Delivery</li>
                </ul>
                <ul class="steps my-4">
                    <li class="step-item" data-bs-toggle="tooltip" title="Done">KYC</li><li class="step-item" title="Done">Finance</li><li class="step-item active">RTO</li><li class="step-item">PDI</li>
                </ul>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="spinner-border text-primary" role="status" aria-label="Loading"></div>
                    <div class="spinner-grow text-green" role="status" aria-label="Loading"></div>
                    <div class="spinner-border spinner-border-sm" role="status" aria-label="Loading"></div>
                    <span class="animated-dots">Saving</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Timeline</h3></div>
            <div class="card-body">
                <ul class="timeline">
                    @foreach ([['la-user-plus', 'bg-blue', 'Enquiry created', 'Walk-in at Jaipur showroom', '2026-09-20 11:05'], ['la-file-invoice', 'bg-purple', 'Quote shared', 'Nexon Creative + · ₹ 9.42 L on-road', '2026-09-22 16:40'], ['la-gavel', 'bg-orange', 'Extra discount approved', 'GM countered ₹ 6,000', '2026-09-23 12:10'], ['la-check', 'bg-green', 'Booked', 'Receipt RCPT/0192 · ₹ 21,000', '2026-09-26 18:25']] as [$icon, $bg, $title, $text, $at])
                        <li class="timeline-event">
                            <div class="timeline-event-icon {{ $bg }} text-white"><i class="la {{ $icon }}"></i></div>
                            <div class="card timeline-event-card">
                                <div class="card-body">
                                    <div class="text-secondary float-end small">@sitedatetime($at)</div>
                                    <h4 class="mb-1">{{ $title }}</h4>
                                    <p class="text-secondary mb-0">{{ $text }}</p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- Overlays --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Modals, off-canvas, toasts, tooltips</h3></div>
            <div class="card-body d-flex flex-wrap gap-2 align-content-start">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kit-modal">Modal</button>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#kit-modal-danger">Confirm delete</button>
                <button type="button" class="btn" data-bs-toggle="offcanvas" data-bs-target="#kit-offcanvas">Off-canvas</button>
                <button type="button" class="btn" data-kit-toast="#kit-toast">Toast</button>
                <button type="button" class="btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Tooltip text">Tooltip</button>
                <button type="button" class="btn" data-bs-toggle="popover" data-bs-placement="top" data-bs-content="Popovers hold a little more text." title="Popover">Popover</button>
                <kbd>Ctrl</kbd> + <kbd>K</kbd>
            </div>
        </div>
    </div>

    {{-- Placeholders --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Loading placeholders</h3></div>
            <div class="card-body placeholder-glow">
                <div class="d-flex align-items-center mb-3"><span class="avatar placeholder me-3"></span><div class="flex-fill"><div class="placeholder col-6 mb-1"></div><div class="placeholder placeholder-xs col-4"></div></div></div>
                <div class="placeholder col-12 mb-2"></div><div class="placeholder col-10 mb-2"></div><div class="placeholder col-8"></div>
            </div>
        </div>
    </div>

    {{-- Typography --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Typography &amp; colours</h3></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h1>Heading 1</h1><h2>Heading 2</h2><h3>Heading 3</h3><h4>Heading 4</h4>
                        <p>Body text with <strong>bold</strong>, <em>italic</em>, <a href="#">a link</a>, <code>inline code</code> and <mark>marked</mark> text.</p>
                        <p class="text-secondary">Secondary text · <span class="text-muted">muted</span> · <span class="small">small</span></p>
                        <div class="hr-text">section divider</div>
                        <div class="subheader">Subheader</div>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-2">
                            @foreach (['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'blue', 'azure', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'cyan'] as $c)
                                <div class="col-4 col-sm-3 col-lg-2">
                                    <div class="xl-swatch bg-{{ $c }}"></div>
                                    <div class="small text-secondary text-truncate mt-1">{{ $c }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Overlay markup --}}
<div class="modal modal-blur fade" id="kit-modal" tabindex="-1" aria-labelledby="kit-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="kit-modal-title">Assign enquiry</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="xl-kit_modal_to">Assign to</label>
                <x-ui.select name="kit_modal_to" :options="['1' => 'Priya Mehta', '2' => 'Karan Singh', '3' => 'Deepak Rao']" placeholder="Pick an executive" />
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Assign</button></div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="kit-modal-danger" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-status bg-danger"></div>
            <div class="modal-body text-center py-4">
                <i class="la la-exclamation-triangle text-danger display-6 mb-2"></i>
                <h3>Delete this quote?</h3>
                <div class="text-secondary">Quote Q/JPR/0142 and its approvals will be removed.</div>
            </div>
            <div class="modal-footer">
                <div class="w-100"><div class="row"><div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal">Cancel</button></div><div class="col"><button type="button" class="btn btn-danger w-100" data-bs-dismiss="modal">Delete</button></div></div></div>
            </div>
        </div>
    </div>
</div>
<div class="offcanvas offcanvas-end" tabindex="-1" id="kit-offcanvas" aria-labelledby="kit-offcanvas-title">
    <div class="offcanvas-header"><h2 class="offcanvas-title h3" id="kit-offcanvas-title">Filters</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
    <div class="offcanvas-body">
        <label class="form-label" for="xl-kit_oc_branch">Branch</label>
        <x-ui.select name="kit_oc_branch[]" :options="['JPR' => 'Jaipur', 'AJM' => 'Ajmer', 'KOT' => 'Kota']" multiple placeholder="All branches" id="xl-kit_oc_branch" />
        <button type="button" class="btn btn-primary w-100 mt-3" data-bs-dismiss="offcanvas">Apply</button>
    </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast" id="kit-toast" role="status" aria-live="polite" aria-atomic="true">
        <div class="toast-header"><span class="avatar avatar-xs bg-green-lt me-2"><i class="la la-check"></i></span><strong class="me-auto">Saved</strong><small class="text-secondary">just now</small><button type="button" class="ms-2 btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>
        <div class="toast-body">Enquiry XENQ-4201 updated.</div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var bs = window.bootstrap || (window.tabler && window.tabler.bootstrap);
    if (!bs) { return; }
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) { bs.Popover.getOrCreateInstance(el); });
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { bs.Tooltip.getOrCreateInstance(el); });
    document.querySelectorAll('[data-kit-toast]').forEach(function (el) {
        el.addEventListener('click', function () { bs.Toast.getOrCreateInstance(document.querySelector(el.getAttribute('data-kit-toast'))).show(); });
    });
});
</script>
@endpush
