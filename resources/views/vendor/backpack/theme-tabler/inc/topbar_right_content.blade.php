<div class="d-flex align-items-center gap-3 me-3">

    {{-- ALERTS --}}
    <div class="dropdown">
        <a class="nav-link position-relative p-1 text-dark" data-bs-toggle="dropdown" href="#" role="button" title="Alerts">
            <i class="la la-exclamation-triangle fs-3"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger px-1.5 py-px fs-10 fw-bold text-white">6</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0 rounded-3" style="width: 340px;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-danger text-white fs-6">
                <div class="d-flex align-items-center">
                    <i class="la la-exclamation-triangle me-2"></i>
                    <strong>Alerts (6 unread)</strong>
                </div>
                <button class="btn btn-sm btn-light text-danger px-2 py-px fs-11 fw-medium mark-all-read">Mark all read</button>
            </div>
            <div class="dropdown-body py-1" style="max-height: 265px; overflow-y: auto;">
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-exclamation-triangle text-danger fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">System maintenance scheduled it is not good</div>
                        <small class="text-muted">2 mins ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-exclamation-triangle text-danger fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">High CPU usage detected, it is for testing and production</div>
                        <small class="text-muted">1 hour ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-exclamation-triangle text-danger fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Security update available</div>
                        <small class="text-muted">3 hours ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-exclamation-triangle text-danger fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Database backup failed</div>
                        <small class="text-muted">5 hours ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-exclamation-triangle text-danger fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">License expiration warning</div>
                        <small class="text-muted">Yesterday</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-exclamation-triangle text-danger fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Server disk space critical</div>
                        <small class="text-muted">2 days ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
            </div>
            <div class="px-3 py-2 border-top text-center">
                <a href="#" class="btn btn-sm btn-light w-100 rounded-2 fw-medium">See All Alerts</a>
            </div>
        </div>
    </div>

    {{-- NOTIFICATIONS --}}
    <div class="dropdown">
        <a class="nav-link position-relative p-1 text-dark" data-bs-toggle="dropdown" href="#" role="button" title="Notifications">
            <i class="la la-bell fs-3"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning px-1.5 py-px fs-10 fw-bold">5</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0 rounded-3" style="width: 340px;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-warning text-white fs-6">
                <div class="d-flex align-items-center">
                    <i class="la la-bell me-2"></i>
                    <strong>Notifications (5 unread)</strong>
                </div>
                <button class="btn btn-sm btn-light text-warning px-2 py-px fs-11 fw-medium mark-all-read">Mark all read</button>
            </div>
            <div class="dropdown-body py-1" style="max-height: 265px; overflow-y: auto;">
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-info-circle text-warning fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">New user registered</div>
                        <small class="text-muted">5 mins ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-clock text-warning fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Task deadline approaching</div>
                        <small class="text-muted">45 mins ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-check-circle text-success fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">System update completed</div>
                        <small class="text-muted">2 hours ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-info-circle text-warning fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">New comment on ticket #4872</div>
                        <small class="text-muted">3 hours ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-clock text-warning fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Weekly report is ready</div>
                        <small class="text-muted">Yesterday</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
            </div>
            <div class="px-3 py-2 border-top text-center">
                <a href="#" class="btn btn-sm btn-light w-100 rounded-2 fw-medium">See All Notifications</a>
            </div>
        </div>
    </div>

    {{-- INBOX --}}
    <div class="dropdown">
        <a class="nav-link position-relative p-1 text-dark" data-bs-toggle="dropdown" href="#" role="button" title="Inbox">
            <i class="la la-envelope fs-3"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success px-1.5 py-px fs-10 fw-bold text-white">6</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0 rounded-3" style="width: 340px;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-success text-white fs-6">
                <div class="d-flex align-items-center">
                    <i class="la la-envelope me-2"></i>
                    <strong>Inbox (6 unread)</strong>
                </div>
                <button class="btn btn-sm btn-light text-success px-2 py-px fs-11 fw-medium mark-all-read">Mark all read</button>
            </div>
            <div class="dropdown-body py-1" style="max-height: 265px; overflow-y: auto;">
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-envelope-open text-success fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Welcome to the team!</div>
                        <small class="text-muted">10 mins ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-calendar text-info fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Meeting reminder</div>
                        <small class="text-muted">1 hour ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-file-text text-primary fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Project update</div>
                        <small class="text-muted">4 hours ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-envelope-open text-success fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Invoice #INV-3924 attached</div>
                        <small class="text-muted">Yesterday</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-calendar text-info fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Client feedback received</div>
                        <small class="text-muted">2 days ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
                <a href="#" class="dropdown-item d-flex align-items-start gap-2 px-3 py-2.5 hover-bg-light mx-1 rounded-2">
                    <i class="la la-file-text text-primary fs-4 mt-px"></i>
                    <div class="flex-grow-1 pe-2">
                        <div class="fw-medium text-wrap">Contract renewal notice</div>
                        <small class="text-muted">3 days ago</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary px-2 py-1 rounded-pill fs-10">Read</button>
                </a>
            </div>
            <div class="px-3 py-2 border-top text-center">
                <a href="#" class="btn btn-sm btn-light w-100 rounded-2 fw-medium">See All Messages</a>
            </div>
        </div>
    </div>

</div>

<style>
    @media (max-width: 991.98px) {
        .dropdown-menu {
            width: 92vw !important;
            max-width: 340px !important;
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
            margin-top: 8px !important;
        }
    }
    .hover-bg-light:hover { background: #f8f9fa !important; }
    .mark-all-read { font-size: 12px; line-height: 1; }
</style>