<?php

use App\Models\Comms\CommOutbox;
use App\Models\Comms\CommSandbox;
use App\Models\Module\Booking\Bookingamount;

/*
 * Fixture for `php artisan dev:route-snapshot … --setup=tests/RouteSnapshots/accounts-comms-fixture.php` (W15 BT-006 /
 * comms). The test copy has no outbox messages, so each rolled-back request gets one sent SMS (id 990401) and its
 * sandbox record (id 990501). Fixed ids and times keep the before / after responses comparable.
 * Spec: tests/RouteSnapshots/accounts-comms.txt.
 */
return function (): void {
    CommOutbox::query()->forceCreate([
        'id' => 990401, 'channel' => 'SMS', 'status' => 'SENT', 'driver' => 'sandbox', 'to_address' => '+919800000001',
        'body_preview' => 'Snapshot fixture', 'payload' => ['text' => 'Snapshot fixture'], 'category' => 'OPERATIONAL',
        'idempotency_key' => 'snapshot.990401', 'attempts' => 1, 'sent_at' => '2026-10-01 10:00:00',
        'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00',
    ]);
    CommSandbox::query()->insert([
        'id' => 990501, 'outbox_id' => 990401, 'channel' => 'SMS', 'driver' => 'sandbox', 'to_address' => '+919800000001',
        'payload' => json_encode(['text' => 'Snapshot fixture']), 'created_at' => '2026-10-01 10:00:00',
    ]);
    // one receipt (type 1) and one journal voucher (type 2) on booking 22 / enquiry 60922 — the test copy has none
    foreach ([[990601, 1, 'GENZQF2701'], [990602, 2, 'JVZQF2701']] as [$id, $type, $number]) {
        Bookingamount::query()->insert([
            'id' => $id, 'type' => $type, 'type_number' => $number, 'bid' => 22, 'enq_id' => 60922, 'amount' => '25000',
            'date' => '2026-10-01', 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00',
        ]);
    }
};
