<?php

/*
|--------------------------------------------------------------------------
| Dashboard widgets (DEC-072)
|--------------------------------------------------------------------------
| The dashboard shows every widget the signed-in user's permissions allow (`permission`, checked with can()), grouped
| by `group`, in this order. Each widget is computed by App\Services\Dashboard\DashboardService::{method}() for the
| chosen period, inside the user's data scope, and fetched lazily from /admin/dashboard/widget/{key}.
|
| type:  kpi (value + detail lines) | list (rows) | chart (ApexCharts: bar, donut, funnel)
| size:  Bootstrap column classes
| Designations are never named here — a new role gets the right cards through its permissions.
*/

return [
    'groups' => [
        'work' => 'My work',
        'sales' => 'Sales',
        'bookings' => 'Bookings & deliveries',
        'accounts' => 'Accounts',
        'stock' => 'Vehicles & stock',
    ],

    'widgets' => [
        // My work — per user, not per scope
        'approvals' => ['title' => 'Approvals to act on', 'group' => 'work', 'type' => 'kpi', 'permission' => 'UTL_APPR_VIEW', 'method' => 'approvals', 'icon' => 'la-gavel', 'link' => 'utils.approvals.index', 'size' => 'col-sm-6 col-xl-3'],
        'tasks' => ['title' => 'My open tasks', 'group' => 'work', 'type' => 'kpi', 'permission' => 'UTL_TASK_VIEW', 'method' => 'tasks', 'icon' => 'la-tasks', 'link' => 'utils.tasks.index', 'size' => 'col-sm-6 col-xl-3'],
        'tickets' => ['title' => 'My open tickets', 'group' => 'work', 'type' => 'kpi', 'permission' => 'UTL_TCKT_VIEW', 'method' => 'tickets', 'icon' => 'la-life-ring', 'link' => 'utils.tickets.index', 'size' => 'col-sm-6 col-xl-3'],
        'notifications' => ['title' => 'Unread notifications', 'group' => 'work', 'type' => 'kpi', 'permission' => null, 'method' => 'notifications', 'icon' => 'la-bell', 'link' => 'utils.inbox.index', 'size' => 'col-sm-6 col-xl-3'],

        // Sales
        'enquiries' => ['title' => 'Open enquiries', 'group' => 'sales', 'type' => 'kpi', 'permission' => 'SLS_ENQR_VIEW', 'method' => 'enquiries', 'icon' => 'la-user-friends', 'link' => 'sales.enquiry.index', 'size' => 'col-sm-6 col-xl-3'],
        'followups' => ['title' => "Today's follow-ups", 'group' => 'sales', 'type' => 'kpi', 'permission' => 'SLS_ENQR_VIEW', 'method' => 'followups', 'icon' => 'la-phone', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'test_drives' => ['title' => 'Test drives', 'group' => 'sales', 'type' => 'kpi', 'permission' => 'SLS_ENQR_VIEW', 'method' => 'testDrives', 'icon' => 'la-car-side', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'quotations' => ['title' => 'Quotations', 'group' => 'sales', 'type' => 'kpi', 'permission' => 'SLS_QUOT_VIEW', 'method' => 'quotations', 'icon' => 'la-file-invoice', 'link' => 'sales.quotation.index', 'size' => 'col-sm-6 col-xl-3'],
        'funnel' => ['title' => 'Sales funnel', 'group' => 'sales', 'type' => 'chart', 'chart' => 'funnel', 'permission' => 'SLS_ENQR_VIEW', 'method' => 'funnel', 'size' => 'col-lg-6'],
        'sources' => ['title' => 'New enquiries by origin', 'group' => 'sales', 'type' => 'chart', 'chart' => 'donut', 'permission' => 'SLS_ENQR_VIEW', 'method' => 'enquirySources', 'size' => 'col-lg-6'],
        'followup_list' => ['title' => 'Follow-ups due', 'group' => 'sales', 'type' => 'list', 'permission' => 'SLS_ENQR_VIEW', 'method' => 'followupList', 'size' => 'col-12'],

        // Bookings & deliveries
        'bookings_live' => ['title' => 'Live bookings', 'group' => 'bookings', 'type' => 'kpi', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'liveBookings', 'icon' => 'la-clipboard-check', 'link' => 'sales.booking.index', 'size' => 'col-sm-6 col-xl-3'],
        'bookings_new' => ['title' => 'Bookings made', 'group' => 'bookings', 'type' => 'kpi', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'newBookings', 'icon' => 'la-calendar-check', 'link' => 'sales.booking.index', 'size' => 'col-sm-6 col-xl-3'],
        'deliveries' => ['title' => 'Deliveries lined up', 'group' => 'bookings', 'type' => 'kpi', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'deliveries', 'icon' => 'la-shipping-fast', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'booking_pending' => ['title' => 'Pending steps', 'group' => 'bookings', 'type' => 'kpi', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'pendingSteps', 'icon' => 'la-hourglass-half', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'bookings_by_model' => ['title' => 'Bookings by model', 'group' => 'bookings', 'type' => 'chart', 'chart' => 'bar', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'bookingsByModel', 'size' => 'col-lg-12'],

        // Accounts
        'receipts' => ['title' => 'Receipts', 'group' => 'accounts', 'type' => 'kpi', 'permission' => 'ACC_RCPT_VIEW', 'method' => 'receipts', 'icon' => 'la-receipt', 'link' => 'accounts.receipt.index', 'size' => 'col-sm-6 col-xl-3'],
        'journal_vouchers' => ['title' => 'Journal vouchers', 'group' => 'accounts', 'type' => 'kpi', 'permission' => 'ACC_JRVCH_VIEW', 'method' => 'journalVouchers', 'icon' => 'la-book', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'refunds' => ['title' => 'Refunds', 'group' => 'accounts', 'type' => 'kpi', 'permission' => 'ACC_RCPT_VIEW', 'method' => 'refunds', 'icon' => 'la-undo-alt', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'receipt_modes' => ['title' => 'Receipts by mode', 'group' => 'accounts', 'type' => 'chart', 'chart' => 'donut', 'permission' => 'ACC_RCPT_VIEW', 'method' => 'receiptModes', 'size' => 'col-sm-6 col-xl-3'],
        'receipt_list' => ['title' => 'Latest receipts', 'group' => 'accounts', 'type' => 'list', 'permission' => 'ACC_RCPT_VIEW', 'method' => 'receiptList', 'size' => 'col-12'],

        // Vehicles & stock (stock is not data-scoped yet — its location column holds legacy ids)
        'stock' => ['title' => 'Vehicles in stock', 'group' => 'stock', 'type' => 'kpi', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'stock', 'icon' => 'la-warehouse', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'catalogue' => ['title' => 'Vehicle catalogue', 'group' => 'stock', 'type' => 'kpi', 'permission' => 'VEH_VAR_VIEW', 'method' => 'catalogue', 'icon' => 'la-car', 'link' => null, 'size' => 'col-sm-6 col-xl-3'],
        'stock_by_model' => ['title' => 'Free stock by model', 'group' => 'stock', 'type' => 'chart', 'chart' => 'bar', 'permission' => 'SLS_BKNG_VIEW', 'method' => 'stockByModel', 'size' => 'col-lg-6'],
    ],

    /** seconds a widget's numbers are cached (per user + data scope + period) */
    'cache_seconds' => 300,

    /** enquiry stages counted as open (user decision 28-09) */
    'open_enquiry_stages' => ['Enquiry', 'Test Drive', 'Quotation', 'Booking', 'Postponed'],
];
