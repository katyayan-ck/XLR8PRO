<?php

use App\Models\CRM\CreFollowup;
use App\Models\CRM\FinanceExchangeFollowup;

/*
 * Fixture for `php artisan dev:route-snapshot … --setup=tests/RouteSnapshots/enquiry-fixture.php` (W15 BT-005).
 * The test copy has no CRE follow-ups and no finance / exchange remarks, so each rolled-back request gets, for enquiry
 * 60922 (EN-0035718789, LONG, has enquiry follow-ups): two CRE follow-ups (one under each reference form, one of them a
 * soft-deleted row and one an OPEN_FOLLOW_UP placeholder) and finance + exchange remarks. Fixed ids and times keep the
 * before / after responses comparable. Spec: tests/RouteSnapshots/enquiry.txt.
 */
return function (): void {
    $cre = [
        [990201, '60922', 1, 'SAME_DAY', null],
        [990202, 'XENQ-60922', 2, '1_AND_2_DAYS', null],
        [990203, '60922', 3, 'OPEN_FOLLOW_UP', null],
        [990204, '60922', 4, '3_TO_10_DAYS', '2026-09-30 12:00:00'],   // soft-deleted
    ];
    foreach ($cre as [$id, $ref, $count, $stage, $deletedAt]) {
        CreFollowup::query()->insert([
            'id' => $id, 'enquiry_no' => 'EN-0035718789', 'x8_enq_no' => $ref, 'cre_fup_count' => $count,
            'cre_planned_fup_date' => '2026-09-2'.$count.' 10:00:00', 'cre_actual_fup_date' => '2026-09-2'.$count.' 11:00:00',
            'cre_fup_deviation_stage' => $stage, 'cre_enq_stage' => 'HOT', 'cre_customer_stage' => 'INTERESTED',
            'cre_fup_remarks' => 'fixture '.$count, 'cre_next_fup_date' => '2026-10-0'.$count.' 10:00:00',
            'created_by' => 1, 'created_at' => '2026-09-2'.$count.' 11:00:00', 'updated_at' => '2026-09-2'.$count.' 11:00:00',
            'deleted_at' => $deletedAt,
        ]);
    }
    foreach ([[990301, 1, 1], [990302, 1, 2], [990303, 2, 1]] as [$id, $type, $count]) {
        FinanceExchangeFollowup::query()->insert([
            'id' => $id, 'enq_no' => 'EN-0035718789', 'remark_type' => $type, 'fup_count' => $count, 'remarks' => "fixture {$type}/{$count}",
            'created_by' => 1, 'created_at' => "2026-09-2{$count} 12:0{$type}:00", 'updated_at' => "2026-09-2{$count} 12:0{$type}:00",
        ]);
    }
};
