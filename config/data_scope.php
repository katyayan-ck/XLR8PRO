<?php

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Location;
use App\Models\Admin\Vertical;
use App\Models\CRM\Campaign;
use App\Models\CRM\Enquiry;
use App\Models\CRM\Lead;
use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\XExchange;
use App\Models\Module\Booking\Xl_Refunds;
use App\Models\Module\Booking\XlDelivery;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Finance\XFinance;
use App\Models\Module\Insurance\XlInsurance;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;

/*
|--------------------------------------------------------------------------
| User data scoping (DEC-071)
|--------------------------------------------------------------------------
| One place that says how a user's scope rows (xlr8_admin_user_scopes) become row filters.
|
| trees     — master hierarchies, top level first. Each level names its master model (`'model' => Class::class`,
|             DEC-093) and, for every ancestor level, the column on that model's table holding the ancestor's code. A parent covers all its
|             children unless the user holds codes at a child level; a child restriction applies within the
|             nearest assigned ancestor (PV + THAR → only THAR under PV).
| entities  — the business models that are filtered, with the column that carries each scope level
|             (codes, not ids) or `via` a parent model whose own scope then applies (satellites).
|             A model is scoped only when it uses App\Models\Traits\HasDataScope AND is listed here.
|             Department / division / vertical are listed only where a table actually carries them.
|
| Settings (Utilities → Settings): scope.enabled (master switch), scope.unassigned_rows (visible|hidden —
| what a scoped user sees for a row whose scope column is empty).
*/

return [

    'trees' => [
        'org_branch' => [
            'branch' => ['model' => Branch::class, 'ancestors' => []],
            'location' => ['model' => Location::class, 'ancestors' => ['branch' => 'branch_code']],
        ],
        'org_department' => [
            'department' => ['model' => Department::class, 'ancestors' => []],
            'division' => ['model' => Division::class, 'ancestors' => ['department' => 'dept_code']],
        ],
        'vertical' => [
            'vertical' => ['model' => Vertical::class, 'ancestors' => []],
        ],
        'vehicle' => [
            'segment' => ['model' => Segment::class, 'ancestors' => []],
            'sub_segment' => ['model' => SubSegment::class, 'ancestors' => ['segment' => 'segment_code']],
            'model' => ['model' => VehicleModel::class, 'ancestors' => ['segment' => 'segment_code', 'sub_segment' => 'sub_segment_code']],
            'variant' => ['model' => Variant::class, 'ancestors' => ['segment' => 'segment_code', 'sub_segment' => 'sub_segment_code', 'model' => 'model_code']],
        ],
    ],

    'entities' => [
        Enquiry::class => [
            'branch' => 'dealer_branch',
            'location' => 'dealer_location',
            'segment' => 'segment_code',
            'model' => 'model_code',
            'variant' => 'variant_code',
        ],
        Lead::class => [
            'segment' => 'segment_code',
            'model' => 'model_code',
            'variant' => 'variant_code',
        ],
        Campaign::class => [
            'branch' => 'branch_code',
            'location' => 'location_code',
            'segment' => 'segment_code',
            'model' => 'model_code',
        ],
        // quotations store the enquiry id in enquiry_no
        Quotation::class => [
            'via' => ['column' => 'enquiry_no', 'parent' => Enquiry::class, 'parent_key' => 'id'],
        ],
        Booking::class => [
            'branch' => 'branch_code',
            'location' => 'location_code',
            'segment' => 'segment_code',
            'sub_segment' => 'sub_segment_code',
            'model' => 'model_code',
            'variant' => 'variant_code',
        ],
        Bookingamount::class => [
            'via' => ['column' => 'bid', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
        XFinance::class => [
            'via' => ['column' => 'bid', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
        XExchange::class => [
            'via' => ['column' => 'bid', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
        XlDelivery::class => [
            'via' => ['column' => 'bid', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
        XlInsurance::class => [
            'via' => ['column' => 'bid', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
        XlRto::class => [
            'via' => ['column' => 'bid', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
        Xl_Refunds::class => [
            'via' => ['column' => 'entity_id', 'parent' => Booking::class, 'parent_key' => 'id'],
        ],
    ],

    /** master lists are cached this long (seconds); scope rows themselves are read fresh once per request */
    'master_cache_seconds' => 600,
];
