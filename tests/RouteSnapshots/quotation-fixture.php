<?php

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\CRM\QuoteAction;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\Variant;

/*
 * Fixture for `php artisan dev:route-snapshot … --setup=tests/RouteSnapshots/quotation-fixture.php` (W15 BT-series).
 * Neither database has quotations yet, so each rolled-back request gets one quotation (id 990001) with two history
 * versions, on the latest enquiry, whose vehicle codes are pointed at a real segment / model / variant / colour so the
 * name lookups find rows. Fixed ids keep the before / after responses comparable. Everything is rolled back.
 * Spec: tests/RouteSnapshots/quotation.txt.
 */
return function (): void {
    $color = Color::query()->whereIn('variant_code', Variant::query()->select('code'))->orderBy('id')->firstOrFail();
    $segment = (string) Variant::query()->where('code', $color->variant_code)->value('segment_code');
    $enquiry = Enquiry::query()->withoutGlobalScopes()->whereNotNull('enquiry_no')->orderByDesc('id')->firstOrFail();
    $enquiry->forceFill([
        'segment_code' => $segment, 'model_code' => $color->model_code, 'variant_code' => $color->variant_code, 'color_code' => $color->code,
    ])->saveQuietly();

    $data = [
        'enquiry_id' => $enquiry->id, 'enquiry_no' => $enquiry->enquiry_no, 'remarks' => 'snapshot fixture',
        'segment_code' => $segment, 'model_code' => $color->model_code, 'variant_code' => $color->variant_code, 'color_code' => $color->code,
    ];
    Quotation::query()->forceCreate([
        'id' => 990001, 'enquiry_no' => $enquiry->id, 'revision' => 1, 'status' => 'raised', 'onroad_price' => 1000000,
        'invoice_price' => 900000, 'standard_data' => $data, 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00',
    ]);
    foreach ([1 => 'RAISED', 2 => 'REVISED'] as $version => $action) {
        QuoteAction::query()->forceCreate([
            'id' => 990000 + $version, 'quotation_no' => 990001, 'action_by' => 1, 'action' => $action,
            'requested' => $data, 'onroad' => 1000000, 'status' => 'raised', 'remarks' => 'v'.$version,
            'created_at' => "2026-10-01 10:0{$version}:00", 'updated_at' => "2026-10-01 10:0{$version}:00",
        ]);
    }
};
