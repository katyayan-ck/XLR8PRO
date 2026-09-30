<?php

/*
|--------------------------------------------------------------------------
| Settings interface catalogue (DEC-091, to-do W13)
|--------------------------------------------------------------------------
| Utilities → Settings shows these tabs in order; each tab has sections, each section lists setting keys with the input
| to use. Values, defaults and types stay with SettingsService / config('platform.settings') (the only writer); this
| file only decides where a key is shown and how it is edited. A key stored or seeded but not listed here appears in
| the "Other" tab, so nothing is hidden.
|
| Tab `permissions`: any one of them opens the tab (DEC-091: UTL_SETTINGS_MANAGE for all; pricing also PRC_WKFL_MANAGE).
| Key options: input = text | url | email | number | switch | select | textarea | secret | image | json | readonly;
| `options` (select), `min` / `max` (number), `help`.
*/

$manage = ['UTL_SETTINGS_MANAGE'];

return [

    'tabs' => [

        'site' => [
            'label' => 'Site / dealership', 'icon' => 'la-store', 'permissions' => $manage,
            'sections' => [
                'dealership' => ['label' => 'Dealership', 'keys' => [
                    'dealership.name' => ['input' => 'text', 'help' => 'Browser title ("<dealership> | Xceler8 DMS"), footer "Made for", menu text logo, mails.'],
                    'dealership.tagline' => ['input' => 'text', 'help' => 'Shown when the footer link is hovered.'],
                    'dealership.legal_name' => ['input' => 'text', 'help' => 'Printed on receipts, OTF forms, quotations and PDFs.'],
                    'dealership.url' => ['input' => 'url'],
                    'branding.logo' => ['input' => 'image', 'default' => 'images/Logo-108x75.png', 'help' => 'Menu, login page and PDFs / prints.'],
                    'dealership.favicon' => ['input' => 'image', 'default' => 'images/favicon-32x32.png', 'help' => 'Browser tab icon (square image).'],
                    'dealership.address' => ['input' => 'textarea'],
                    'dealership.email' => ['input' => 'email'],
                    'dealership.phone' => ['input' => 'text'],
                    'dealership.gstin' => ['input' => 'text'],
                ]],
                'display' => ['label' => 'Display', 'keys' => [
                    'branding.menu_logo' => ['input' => 'select', 'options' => ['logo' => 'Logo image', 'text' => 'Dealership name (text)'], 'help' => 'What the menu shows at the top left.'],
                    'display.date_format' => ['input' => 'select', 'options' => ['d-M-Y' => '23-Sep-2026', 'd-m-Y' => '23-09-2026', 'd/m/Y' => '23/09/2026', 'Y-m-d' => '2026-09-23', 'M d, Y' => 'Sep 23, 2026']],
                    'display.time_format' => ['input' => 'select', 'options' => ['H:i' => '14:30', 'h:i A' => '02:30 PM']],
                ]],
            ],
        ],

        'communication' => [
            'label' => 'Communication', 'icon' => 'la-envelope', 'permissions' => $manage,
            'sections' => [
                'channels' => ['label' => 'Channels (global on / off)', 'keys' => [
                    'comms.enabled.mail' => ['input' => 'switch'],
                    'comms.enabled.sms' => ['input' => 'switch'],
                    'comms.enabled.whatsapp' => ['input' => 'switch'],
                    'comms.enabled.push' => ['input' => 'switch'],
                ]],
                'smtp' => ['label' => 'Mail server (SMTP)', 'keys' => [
                    'mail.smtp.host' => ['input' => 'text', 'help' => 'Blank = use the server configuration (.env).'],
                    'mail.smtp.port' => ['input' => 'number', 'min' => 1, 'max' => 65535],
                    'mail.smtp.encryption' => ['input' => 'select', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None']],
                    'mail.smtp.username' => ['input' => 'text'],
                    'mail.smtp.password' => ['input' => 'secret'],
                    'mail.smtp.from_address' => ['input' => 'email'],
                    'mail.smtp.from_name' => ['input' => 'text'],
                ]],
                'mail' => ['label' => 'Mail', 'keys' => [
                    'mail.signature' => ['input' => 'textarea', 'help' => 'Added to the end of every e-mail.'],
                    'mail.driver' => ['input' => 'select', 'options' => ['laravel' => 'Send (laravel)', 'log' => 'Log only (no send)']],
                    'mail.redirect_to' => ['input' => 'email'],
                    'mail.allowed_from' => ['input' => 'text'],
                    'mail.identities' => ['input' => 'json'],
                ]],
                'sms' => ['label' => 'SMS', 'keys' => [
                    'sms.driver' => ['input' => 'text'], 'sms.failover_driver' => ['input' => 'text'],
                    'sms.default_header' => ['input' => 'text'], 'sms.dlt_required' => ['input' => 'switch'],
                    'sms.otp_ttl_seconds' => ['input' => 'number', 'min' => 30, 'max' => 3600],
                    'sms.otp_max_per_15min' => ['input' => 'number', 'min' => 1, 'max' => 20],
                ]],
                'whatsapp' => ['label' => 'WhatsApp & calls', 'keys' => [
                    'whatsapp.driver' => ['input' => 'text'], 'whatsapp.session_hours' => ['input' => 'number', 'min' => 1, 'max' => 72],
                    'whatsapp.webhook_secret' => ['input' => 'secret'], 'telephony.driver' => ['input' => 'text'],
                    'telephony.mask' => ['input' => 'switch'], 'telephony.recording_grace_minutes' => ['input' => 'number', 'min' => 0, 'max' => 1440],
                ]],
                'delivery' => ['label' => 'Delivery rules', 'keys' => [
                    'notify.quiet_hours' => ['input' => 'text'], 'comms.promo_window' => ['input' => 'text'],
                    'comms.max_attempts' => ['input' => 'number', 'min' => 1, 'max' => 10], 'comms.webhook_secret' => ['input' => 'secret'],
                    'templates.strict_locale' => ['input' => 'switch'],
                ]],
            ],
        ],

        'pricing' => [
            'label' => 'Pricing', 'icon' => 'la-rupee-sign', 'permissions' => ['UTL_SETTINGS_MANAGE', 'PRC_WKFL_MANAGE'],
            'sections' => [
                'insurance' => ['label' => 'Insurance', 'keys' => [
                    'pricing.insurance.od_discount_pct' => ['input' => 'number', 'min' => 0, 'max' => 100],
                    'pricing.insurance.gst_pct' => ['input' => 'number', 'min' => 0, 'max' => 100],
                    'pricing.insurance.goods_tp_gst_pct' => ['input' => 'number', 'min' => 0, 'max' => 100],
                ]],
                'onroad' => ['label' => 'RTO & dealer charges', 'keys' => [
                    'pricing.rto.round_up_to' => ['input' => 'number', 'min' => 1, 'max' => 100000],
                    'pricing.dealer_charges.include_cod' => ['input' => 'switch'],
                    'pricing.last_updated_at' => ['input' => 'readonly'],
                ]],
            ],
        ],

        'users' => [
            'label' => 'User behaviour', 'icon' => 'la-user-cog', 'permissions' => $manage,
            'sections' => [
                'appearance' => ['label' => 'Appearance', 'keys' => [
                    'ui.appearance_enabled' => ['input' => 'switch'],
                    'ui.density.text' => ['input' => 'select', 'options' => ['xs' => 'Extra small', 'sm' => 'Small', 'md' => 'Standard', 'lg' => 'Large']],
                    'ui.density.space' => ['input' => 'select', 'options' => ['compact' => 'Compact', 'cozy' => 'Cozy', 'comfortable' => 'Comfortable']],
                ]],
                'profile' => ['label' => 'Users may change their own …', 'keys' => [
                    'account.can_change_display_name' => ['input' => 'switch'], 'account.can_change_email' => ['input' => 'switch'],
                    'account.can_change_photo' => ['input' => 'switch'], 'account.can_change_mobile' => ['input' => 'switch'],
                    'account.can_change_aadhaar' => ['input' => 'switch'], 'account.can_change_pan' => ['input' => 'switch'],
                    'account.can_change_password' => ['input' => 'switch'], 'account.can_change_dob' => ['input' => 'switch'],
                    'account.can_change_doj' => ['input' => 'switch'], 'account.can_change_marital_status' => ['input' => 'switch'],
                    'account.can_change_gender' => ['input' => 'switch'],
                ]],
                'password' => ['label' => 'Password policy', 'keys' => [
                    'account.password_min_length' => ['input' => 'number', 'min' => 8, 'max' => 64],
                    'account.password_require_mixed_case' => ['input' => 'switch'],
                    'account.password_require_symbols' => ['input' => 'switch'],
                ]],
            ],
        ],

        'security' => [
            'label' => 'Security', 'icon' => 'la-shield-alt', 'permissions' => $manage,
            'sections' => [
                'session' => ['label' => 'Sessions & screen lock', 'keys' => [
                    'security.idle_logout_minutes' => ['input' => 'number', 'min' => 0, 'max' => 1440],
                    'security.idle_lock_minutes' => ['input' => 'number', 'min' => 0, 'max' => 1440],
                    'security.idle_warning_seconds' => ['input' => 'number', 'min' => 10, 'max' => 600],
                    'security.screen_lock_enabled' => ['input' => 'switch'],
                    'security.unlock_max_attempts' => ['input' => 'number', 'min' => 1, 'max' => 20],
                    'security.csp_mode' => ['input' => 'select', 'options' => ['off' => 'Off', 'report' => 'Report only', 'enforce' => 'Enforce']],
                ]],
                'data' => ['label' => 'Data access', 'keys' => [
                    'scope.enabled' => ['input' => 'switch'],
                    'scope.unassigned_rows' => ['input' => 'select', 'options' => ['visible' => 'Visible', 'hidden' => 'Hidden']],
                ]],
            ],
        ],

        'modules' => [
            'label' => 'Modules & utilities', 'icon' => 'la-th-large', 'permissions' => $manage,
            'sections' => [
                'documents' => ['label' => 'Documents', 'keys' => [
                    'docs.max_upload_kb' => ['input' => 'number', 'min' => 100, 'max' => 102400],
                    'docs.allowed_mimes' => ['input' => 'text'],
                    'docs.purge_after_days' => ['input' => 'number', 'min' => 1, 'max' => 3650],
                ]],
                'tickets' => ['label' => 'Tickets & SLA', 'keys' => [
                    'sla.ticket.p1_hours' => ['input' => 'number', 'min' => 1, 'max' => 720], 'sla.ticket.p2_hours' => ['input' => 'number', 'min' => 1, 'max' => 720],
                    'sla.ticket.p3_hours' => ['input' => 'number', 'min' => 1, 'max' => 720], 'sla.ticket.p4_hours' => ['input' => 'number', 'min' => 1, 'max' => 720],
                    'ticket.autoclose_enabled' => ['input' => 'switch'], 'ticket.autoclose_days' => ['input' => 'number', 'min' => 1, 'max' => 90],
                ]],
                'collaboration' => ['label' => 'Chat, notifications & approvals', 'keys' => [
                    'chat.edit_window_minutes' => ['input' => 'number', 'min' => 0, 'max' => 1440],
                    'feature.live_chat' => ['input' => 'switch'], 'feature.notifications' => ['input' => 'switch'],
                    'approval.auto_accept_own_power' => ['input' => 'switch'],
                ]],
            ],
        ],
    ],

    /* Legacy keys superseded by the ones above; never shown (owner 30-09: no site name / slogan). */
    'hidden' => ['site.name', 'site.slogan', 'site.logo', 'brand.name'],

    /* Keys that exist but are not listed above land here (same permission as `site`). */
    'other' => ['label' => 'Other', 'icon' => 'la-ellipsis-h', 'permissions' => $manage],
];
