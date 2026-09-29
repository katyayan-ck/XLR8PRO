<?php

use App\Models\Admin\Person;
use App\Models\Approval\ApprovalRequest;
use App\Models\Comms\CommCall;
use App\Models\Comms\CommTemplate;
use App\Models\Comms\WaThread;
use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\Utilities\CommHistory\CommMaster;
use App\Models\Utilities\Docs\Document;
use App\Models\Utilities\Task\Task;
use App\Models\Utilities\Ticket\Ticket;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Variant;

/*
|--------------------------------------------------------------------------
| Platform utilities (FRS v1.1, DEC-061..065)
|--------------------------------------------------------------------------
| Entity types are stable codes (Chat / Notify ref_type / Docs / Task ref). Each maps to a model
| class, an admin deep link and the permission that lets a user see the record (a model may refine it
| with `chatCanView(int $userId)`). Adding a type is a row here — no code change in the utilities.
*/

return [

    /* Dev-only UI kit at /admin/dev/ui (DEC-067): on for local environments unless XL_DEV_UI_KIT says otherwise. */
    'dev_ui_kit' => (bool) env('XL_DEV_UI_KIT', env('APP_ENV') === 'local'),

    'entities' => [
        'QUOTE' => ['model' => Quotation::class, 'url' => 'sales/quotation/{id}/edit', 'label' => 'Quotation', 'permission' => 'SLS_QUOT_VIEW'],
        'ENQUIRY' => ['model' => Enquiry::class, 'url' => 'sales/enquiry/{id}/edit', 'label' => 'Enquiry', 'permission' => 'SLS_ENQR_VIEW'],
        'BOOKING' => ['model' => Booking::class, 'url' => 'sales/booking/{id}/show', 'label' => 'Booking', 'permission' => 'SLS_BKNG_VIEW'],
        'TASK' => ['model' => Task::class, 'url' => 'utils/tasks/{id}', 'label' => 'Task', 'permission' => 'UTL_TASK_VIEW'],
        'TICKET' => ['model' => Ticket::class, 'url' => 'utils/tickets/{id}', 'label' => 'Ticket', 'permission' => 'UTL_TCKT_VIEW'],
        'APPROVAL' => ['model' => ApprovalRequest::class, 'url' => 'utils/approvals/{id}', 'label' => 'Approval', 'permission' => 'UTL_APPR_VIEW'],
        'DOC' => ['model' => Document::class, 'url' => 'utils/docs/{id}', 'label' => 'Document', 'permission' => 'UTL_DOCS_VIEW'],
        'VEHICLE' => ['model' => Variant::class, 'url' => 'vehicle/variant/{id}/edit', 'label' => 'Vehicle', 'permission' => 'VEH_VAR_VIEW'],
        'PERSON' => ['model' => Person::class, 'url' => 'org/person/{id}/edit', 'label' => 'Person', 'permission' => 'ORG_PRSN_VIEW'],
        'PRICING' => ['model' => ImportSession::class, 'url' => 'pricing/workflow', 'label' => 'Price list session', 'permission' => 'PRC_WKFL_VIEW'],
        'TEMPLATE' => ['model' => CommTemplate::class, 'url' => 'utils/templates/{id}', 'label' => 'Template', 'permission' => 'UTL_TPL_VIEW'],
        'WA_THREAD' => ['model' => WaThread::class, 'url' => 'utils/whatsapp/{id}', 'label' => 'WhatsApp conversation', 'permission' => 'UTL_COMM_WA_INBOX'],
        'CALL' => ['model' => CommCall::class, 'url' => 'utils/calls?call={id}', 'label' => 'Call', 'permission' => 'UTL_COMM_VIEW'],
        'CHAT' => ['model' => CommMaster::class, 'url' => null, 'label' => 'Conversation'],
        'SYSTEM' => ['model' => null, 'url' => null, 'label' => 'System'],
    ],

    'notify' => [
        // FRS kinds: N = notification, A = alert, M = message
        'kinds' => ['N', 'A', 'M'],
        'channels' => ['INAPP', 'FCM', 'EMAIL', 'SMS', 'WHATSAPP'],
        'default_channels' => ['INAPP', 'FCM'],
    ],

    'chat' => [
        'actions_keyword' => 'ENTITY_ACTIONS',
    ],

    'docs' => [
        'kinds' => ['IMAGE', 'DOCUMENT', 'INFORMATION'],
        'collections' => ['docs', 'kyc', 'quote-pdf', 'thread-docs', 'wa-inbound', 'call-recordings'],
        'disk' => 'public',
        'cart_group' => '_temp',
    ],

    // Settings defaults (seed pack; the admin screen shows them, `Settings::get()` falls back to them)
    'settings' => [
        'brand.name' => ['value' => 'BMPL', 'type' => 'string', 'label' => 'Brand name'],
        'chat.edit_window_minutes' => ['value' => 15, 'type' => 'int', 'label' => 'Remark edit window (minutes)'],
        'docs.max_upload_kb' => ['value' => 10240, 'type' => 'int', 'label' => 'Max upload size (KB)'],
        'docs.allowed_mimes' => ['value' => 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,mp3,mp4,wav', 'type' => 'string', 'label' => 'Allowed file types'],
        'docs.purge_after_days' => ['value' => 30, 'type' => 'int', 'label' => 'Purge deleted documents after (days)'],
        'notify.quiet_hours' => ['value' => '', 'type' => 'string', 'label' => 'Quiet hours (HH:MM-HH:MM, blank = none)'],
        'sla.ticket.p1_hours' => ['value' => 4, 'type' => 'int', 'label' => 'Ticket SLA P1 (hours)'],
        'sla.ticket.p2_hours' => ['value' => 8, 'type' => 'int', 'label' => 'Ticket SLA P2 (hours)'],
        'sla.ticket.p3_hours' => ['value' => 24, 'type' => 'int', 'label' => 'Ticket SLA P3 (hours)'],
        'sla.ticket.p4_hours' => ['value' => 72, 'type' => 'int', 'label' => 'Ticket SLA P4 (hours)'],
        'ticket.autoclose_days' => ['value' => 3, 'type' => 'int', 'label' => 'Ticket auto-close after resolved (days)'],
        'ticket.autoclose_enabled' => ['value' => true, 'type' => 'bool', 'label' => 'Auto-close resolved tickets'],
        // DEC-071 user data scoping
        'scope.enabled' => ['value' => true, 'type' => 'bool', 'label' => 'Filter data by each user\'s scope (branch / location / department / division / vertical / vehicle)'],
        'scope.unassigned_rows' => ['value' => 'visible', 'type' => 'string', 'label' => 'Rows without a scope code: visible or hidden'],
        'approval.auto_accept_own_power' => ['value' => true, 'type' => 'bool', 'label' => 'Auto-accept asks within own power'],
        'mail.driver' => ['value' => 'laravel', 'type' => 'string', 'label' => 'Email driver (laravel|log)'],
        'mail.identities' => ['value' => ['default' => null], 'type' => 'json', 'label' => 'Email sender identities (alias => address)'],
        'sms.driver' => ['value' => 'sandbox', 'type' => 'string', 'label' => 'SMS driver'],
        'sms.failover_driver' => ['value' => '', 'type' => 'string', 'label' => 'SMS failover driver'],
        'sms.otp_ttl_seconds' => ['value' => 300, 'type' => 'int', 'label' => 'OTP validity (seconds)'],
        'sms.otp_max_per_15min' => ['value' => 3, 'type' => 'int', 'label' => 'OTP sends per 15 minutes'],
        'whatsapp.driver' => ['value' => 'sandbox', 'type' => 'string', 'label' => 'WhatsApp driver'],
        'whatsapp.webhook_secret' => ['value' => '', 'type' => 'encrypted', 'label' => 'WhatsApp webhook secret'],
        'telephony.driver' => ['value' => 'sandbox', 'type' => 'string', 'label' => 'Telephony driver'],
        'telephony.mask' => ['value' => true, 'type' => 'bool', 'label' => 'Mask customer numbers'],
        'telephony.recording_grace_minutes' => ['value' => 30, 'type' => 'int', 'label' => 'Recording grace period (minutes)'],
        'comms.promo_window' => ['value' => '10:00-18:00', 'type' => 'string', 'label' => 'Promotional send window'],
        'comms.webhook_secret' => ['value' => '', 'type' => 'encrypted', 'label' => 'Comms webhook signing secret'],
        'mail.redirect_to' => ['value' => '', 'type' => 'string', 'label' => 'Redirect every email to (dev safety; blank = off)'],
        'mail.allowed_from' => ['value' => '', 'type' => 'string', 'label' => 'Extra allowed From addresses (comma separated)'],
        'sms.dlt_required' => ['value' => true, 'type' => 'bool', 'label' => 'Require DLT mapping for transactional SMS'],
        'sms.default_header' => ['value' => 'BMPLTX', 'type' => 'string', 'label' => 'Default SMS sender header'],
        'whatsapp.session_hours' => ['value' => 24, 'type' => 'int', 'label' => 'WhatsApp customer-care window (hours)'],
        'comms.max_attempts' => ['value' => 3, 'type' => 'int', 'label' => 'Send attempts before an outbox row fails'],
        'templates.strict_locale' => ['value' => false, 'type' => 'bool', 'label' => 'Fail when a template locale is missing'],
        // vehicle pricing — Calculate & Publish (DEC-080)
        'pricing.insurance.od_discount_pct' => ['value' => 30, 'type' => 'int', 'label' => 'Insurance OD discount (%)'],
        'pricing.insurance.gst_pct' => ['value' => 18, 'type' => 'int', 'label' => 'Insurance GST on OD, TP and add-ons (%)'],
        'pricing.insurance.goods_tp_gst_pct' => ['value' => 12, 'type' => 'int', 'label' => 'Insurance GST on Goods TP (%)'],
        'pricing.rto.round_up_to' => ['value' => 1000, 'type' => 'int', 'label' => 'RTO tax base rounded up to (₹)'],
        'pricing.dealer_charges.include_cod' => ['value' => false, 'type' => 'bool', 'label' => 'Add COD charges to the default on-road price (takes effect at the next Calculate & Publish)'],
        'branding.logo' => ['value' => '', 'type' => 'image', 'label' => 'Site logo — admin header / sidebar (links to the dashboard), login page and PDFs / prints; upload an image (DEC-083)'],
        'pricing.last_updated_at' => ['value' => '', 'type' => 'string', 'label' => 'Pricing last updated — set automatically on any published-price, vehicle master or accessory change; the app re-syncs its offline data when it moves (DEC-083)'],
    ],
];
