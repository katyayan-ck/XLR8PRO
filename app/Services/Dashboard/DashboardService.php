<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\CRM\Enquiry;
use App\Models\CRM\EnquiryFollowup;
use App\Models\CRM\Quotation;
use App\Models\CRM\TestDrive;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\Stock;
use App\Models\Module\Booking\Xl_Refunds;
use App\Models\Module\Booking\XlDelivery;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Insurance\XlInsurance;
use App\Models\User;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Platform\Approval\ApprovalService;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Task\TaskService;
use App\Services\Platform\Ticket\TicketService;
use App\Support\Facades\DataScope;
use Illuminate\Database\Query\Builder;

/**
 * Dashboard widget data (DEC-072). One public method per widget in config/dashboard.php; each returns a plain array:
 *
 *   kpi   ['value' => int|float, 'format' => 'number'|'money', 'lines' => list<['label', 'value', 'format'?, 'tone'?]>, 'hint' => ?string]
 *   chart ['labels' => list<string>, 'series' => list<['name', 'data' => list<int|float>]>, 'format' => 'number'|'money']
 *   list  ['columns' => list<string>, 'rows' => list<list<string>>, 'empty' => string]
 *
 * Business rows go through data-scoped models (HasDataScope) or DataScope::apply() for raw joins, so every number is
 * inside the viewer's scope. Personal queues (approvals, tasks, tickets, notifications) are per user. Stock is not
 * data-scoped yet (legacy location ids).
 */
class DashboardService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly TaskService $tasks,
        private readonly TicketService $tickets,
        private readonly NotifyService $notify,
    ) {}

    // ---------------------------------------------------------------- my work

    /** @return array<string, mixed> */
    public function approvals(DashboardPeriod $p, User $u): array
    {
        return $this->kpi($this->approvals->inbox($u->id, 'TO_ACT', 1)->total(), [
            ['label' => 'Raised by me, open', 'value' => $this->approvals->inbox($u->id, 'RAISED', 1)->total()],
        ]);
    }

    /** @return array<string, mixed> */
    public function tasks(DashboardPeriod $p, User $u): array
    {
        $c = $this->tasks->inboxCounts($u->id);

        return $this->kpi((int) ($c['ASSIGNED'] ?? 0), [
            ['label' => 'Created by me', 'value' => (int) ($c['CREATED'] ?? 0)],
            ['label' => 'Following', 'value' => (int) ($c['FOLLOWED'] ?? 0)],
        ], 'Assigned to me, not closed');
    }

    /** @return array<string, mixed> */
    public function tickets(DashboardPeriod $p, User $u): array
    {
        $c = $this->tickets->inboxCounts($u->id);

        return $this->kpi((int) ($c['ASSIGNED'] ?? 0), [
            ['label' => 'Raised by me', 'value' => (int) ($c['REQUESTED'] ?? 0)],
            ['label' => 'Following', 'value' => (int) ($c['FOLLOWED'] ?? 0)],
        ], 'Assigned to me');
    }

    /** @return array<string, mixed> */
    public function notifications(DashboardPeriod $p, User $u): array
    {
        $c = $this->notify->counts($u->id);

        return $this->kpi($c['notifications']['unread'], [
            ['label' => 'Unread alerts', 'value' => $c['alerts']['unread']],
            ['label' => 'Unread messages', 'value' => $c['messages']['unread']],
        ]);
    }

    // ---------------------------------------------------------------- sales

    /** @return array<string, mixed> */
    public function enquiries(DashboardPeriod $p, User $u): array
    {
        $open = $this->openStages();
        $marks = implode(',', array_fill(0, count($open), '?'));
        [$from, $to] = $p->between();
        $row = Enquiry::query()->toBase()->selectRaw(
            "SUM(CASE WHEN stage IN ({$marks}) THEN 1 ELSE 0 END) AS open_count,
             SUM(CASE WHEN enquiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS new_count,
             SUM(CASE WHEN stage = 'Lost' AND enquiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS lost_count",
            [...$open, $from, $to, $from, $to]
        )->first();

        return $this->kpi((int) ($row->open_count ?? 0), [
            ['label' => 'New '.lcfirst($p->label()), 'value' => (int) ($row->new_count ?? 0), 'tone' => 'green'],
            ['label' => 'Lost (enquired '.lcfirst($p->label()).')', 'value' => (int) ($row->lost_count ?? 0), 'tone' => 'red'],
        ], 'Stage: '.implode(', ', $open));
    }

    /** @return array<string, mixed> */
    public function followups(DashboardPeriod $p, User $u): array
    {
        $today = now()->startOfDay();
        $row = $this->openFollowups()->selectRaw(
            'SUM(CASE WHEN f.planned_followup_date >= ? AND f.planned_followup_date < ? THEN 1 ELSE 0 END) AS today_count,
             SUM(CASE WHEN f.planned_followup_date < ? THEN 1 ELSE 0 END) AS overdue_count,
             SUM(CASE WHEN f.planned_followup_date >= ? AND f.planned_followup_date < ? THEN 1 ELSE 0 END) AS week_count',
            [$today, $today->copy()->addDay(), $today, $today->copy()->addDay(), $today->copy()->addDays(8)]
        )->first();

        return $this->kpi((int) ($row->today_count ?? 0), [
            ['label' => 'Overdue', 'value' => (int) ($row->overdue_count ?? 0), 'tone' => 'red'],
            ['label' => 'Next 7 days', 'value' => (int) ($row->week_count ?? 0)],
        ], 'Open follow-ups planned for today');
    }

    /** @return array<string, mixed> */
    public function testDrives(DashboardPeriod $p, User $u): array
    {
        [$from, $to] = $p->between();
        $row = $this->testDriveQuery()->selectRaw(
            "SUM(CASE WHEN t.td_created_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS booked_count,
             SUM(CASE WHEN t.td_created_date BETWEEN ? AND ? AND t.stage = 'TEST_DRIVE_COMPLETED' THEN 1 ELSE 0 END) AS done_count,
             SUM(CASE WHEN t.scheduled_td_start_time >= ? AND t.scheduled_td_start_time < ? THEN 1 ELSE 0 END) AS today_count",
            [$from, $to, $from, $to, now()->startOfDay(), now()->startOfDay()->addDay()]
        )->first();

        return $this->kpi((int) ($row->booked_count ?? 0), [
            ['label' => 'Completed', 'value' => (int) ($row->done_count ?? 0), 'tone' => 'green'],
            ['label' => 'Scheduled today', 'value' => (int) ($row->today_count ?? 0)],
        ], 'Created '.lcfirst($p->label()));
    }

    /** @return array<string, mixed> */
    public function quotations(DashboardPeriod $p, User $u): array
    {
        return $this->kpi(Quotation::query()->whereBetween('created_at', $p->between())->count(), [
            ['label' => 'Pending approval', 'value' => Quotation::query()->where('status', 'pending_approval')->count(), 'tone' => 'orange'],
            ['label' => 'Approved '.lcfirst($p->label()), 'value' => Quotation::query()->where('status', 'approved')->whereBetween('updated_at', $p->between())->count(), 'tone' => 'green'],
        ], 'Raised '.lcfirst($p->label()));
    }

    /** @return array<string, mixed> */
    public function funnel(DashboardPeriod $p, User $u): array
    {
        $between = $p->between();
        $data = [
            Enquiry::query()->whereBetween('enquiry_date', $between)->count(),
            (int) $this->testDriveQuery()->whereBetween('t.td_created_date', $between)->count(),
            Quotation::query()->whereBetween('created_at', $between)->count(),
            Booking::query()->whereBetween('booking_date', $between)->where('status', '!=', '3')->count(),
            $this->alignedDeliveries($p)->whereIn('id', $this->deliveredIds())->count(),
        ];

        return ['labels' => ['Enquiry', 'Test drive', 'Quotation', 'Booking', 'Delivery'], 'series' => [['name' => 'Count', 'data' => $data]], 'format' => 'number'];
    }

    /** @return array<string, mixed> */
    public function enquirySources(DashboardPeriod $p, User $u): array
    {
        $rows = Enquiry::query()->whereBetween('enquiry_date', $p->between())
            ->toBase()->selectRaw("COALESCE(NULLIF(current_origin, ''), 'Other') AS origin_label, COUNT(*) AS n")
            ->groupByRaw("COALESCE(NULLIF(current_origin, ''), 'Other')")->orderByDesc('n')->get();

        return ['labels' => $rows->pluck('origin_label')->map(fn ($o) => ucfirst(strtolower((string) $o)))->all(),
            'series' => [['name' => 'Enquiries', 'data' => $rows->pluck('n')->map(fn ($n) => (int) $n)->all()]], 'format' => 'number'];
    }

    /** @return array<string, mixed> */
    public function followupList(DashboardPeriod $p, User $u): array
    {
        $rows = $this->openFollowups()
            ->where('f.planned_followup_date', '<', now()->startOfDay()->addDay())
            ->orderBy('f.planned_followup_date')
            ->limit(10)
            ->get(['f.planned_followup_date', 'e.name', 'e.enquiry_no', 'e.model', 'e.sc_name', 'f.followup_type']);

        return [
            'columns' => ['Due', 'Customer', 'Enquiry', 'Model', 'Consultant', 'Type'],
            'rows' => $rows->map(fn ($r) => [
                $r->planned_followup_date ? site_date($r->planned_followup_date) : '—',
                (string) ($r->name ?? '—'), (string) ($r->enquiry_no ?? '—'), (string) ($r->model ?? '—'),
                (string) ($r->sc_name ?? '—'), (string) ($r->followup_type ?? '—'),
            ])->all(),
            'empty' => 'No follow-ups due today or overdue.',
        ];
    }

    // ---------------------------------------------------------------- bookings

    /** @return array<string, mixed> */
    public function liveBookings(DashboardPeriod $p, User $u): array
    {
        return $this->kpi(Booking::query()->live()->count(), [
            ['label' => 'Pending data', 'value' => Booking::query()->pendingData()->count(), 'tone' => 'orange'],
            ['label' => 'On hold', 'value' => Booking::query()->onHold()->count()],
        ], 'Active, not invoiced');
    }

    /** @return array<string, mixed> */
    public function newBookings(DashboardPeriod $p, User $u): array
    {
        $between = $p->between();

        return $this->kpi(Booking::query()->whereBetween('booking_date', $between)->count(), [
            ['label' => 'Invoiced', 'value' => Booking::query()->whereBetween('inv_date', $between)->count(), 'tone' => 'green'],
            ['label' => 'Cancelled', 'value' => Booking::query()->whereBetween('cancel_date', $between)->count(), 'tone' => 'red'],
        ], lcfirst($p->label()));
    }

    /** Invoiced bookings whose planned delivery date falls in the period — delivered vs still pending (user decision 28-09). @return array<string, mixed> */
    public function deliveries(DashboardPeriod $p, User $u): array
    {
        $total = $this->alignedDeliveries($p)->count();
        $delivered = $this->alignedDeliveries($p)->whereIn('id', $this->deliveredIds())->count();

        return $this->kpi($total, [
            ['label' => 'Delivered', 'value' => $delivered, 'tone' => 'green'],
            ['label' => 'Pending', 'value' => $total - $delivered, 'tone' => 'orange'],
        ], 'Invoiced, delivery date '.lcfirst($p->label()));
    }

    /** @return array<string, mixed> */
    public function pendingSteps(DashboardPeriod $p, User $u): array
    {
        $invoiced = fn () => Booking::query()->where('status', '2');

        return $this->kpi(Booking::query()->pendingKYC()->count(), [
            ['label' => 'Insurance pending', 'value' => $invoiced()->whereNotIn('id', XlInsurance::withoutDataScope()->whereNotNull('bid')->select('bid'))->count(), 'tone' => 'orange'],
            ['label' => 'RTO pending', 'value' => $invoiced()->whereNotIn('id', XlRto::withoutDataScope()->whereNotNull('bid')->select('bid'))->count(), 'tone' => 'orange'],
            ['label' => 'Delivery pending', 'value' => $invoiced()->whereNotIn('id', $this->deliveredIds())->count(), 'tone' => 'orange'],
        ], 'KYC pending on live bookings');
    }

    /** @return array<string, mixed> */
    public function bookingsByModel(DashboardPeriod $p, User $u): array
    {
        $rows = Booking::query()->whereBetween('booking_date', $p->between())
            ->toBase()->selectRaw("COALESCE(NULLIF(model_code, ''), 'Not set') AS model, COUNT(*) AS n")
            ->groupBy('model')->orderByDesc('n')->limit(12)->get();

        return ['labels' => $rows->pluck('model')->all(), 'series' => [['name' => 'Bookings', 'data' => $rows->pluck('n')->map(fn ($n) => (int) $n)->all()]], 'format' => 'number'];
    }

    // ---------------------------------------------------------------- accounts

    /** @return array<string, mixed> */
    public function receipts(DashboardPeriod $p, User $u): array
    {
        $today = [now()->startOfDay()->toDateString(), now()->toDateString()];
        $sum = fn ($q) => (float) $q->selectRaw('SUM(CAST(amount AS DECIMAL(15,2))) as total')->value('total');

        return $this->kpi($sum($this->receiptQuery()->whereBetween('date', $this->dates($p))), [
            ['label' => 'Receipts', 'value' => $this->receiptQuery()->whereBetween('date', $this->dates($p))->count()],
            ['label' => 'Today', 'value' => $sum($this->receiptQuery()->whereBetween('date', $today)), 'format' => 'money', 'tone' => 'green'],
        ], 'Amount received '.lcfirst($p->label()), 'money');
    }

    /** @return array<string, mixed> */
    public function journalVouchers(DashboardPeriod $p, User $u): array
    {
        $q = fn () => Bookingamount::query()->where('type', 2)->whereBetween('date', $this->dates($p));

        return $this->kpi($q()->count(), [
            ['label' => 'Amount', 'value' => (float) $q()->selectRaw('SUM(CAST(amount AS DECIMAL(15,2))) as total')->value('total'), 'format' => 'money'],
        ], lcfirst($p->label()));
    }

    /** @return array<string, mixed> */
    public function refunds(DashboardPeriod $p, User $u): array
    {
        return $this->kpi(Booking::query()->where('status', '4')->count(), [
            ['label' => 'Refunded '.lcfirst($p->label()), 'value' => Booking::query()->whereBetween('refund_date', $this->dates($p))->count(), 'tone' => 'green'],
            ['label' => 'Amount refunded', 'value' => (float) Xl_Refunds::query()->whereBetween('ref_date', $this->dates($p))->selectRaw('SUM(CAST(amount AS DECIMAL(15,2))) as total')->value('total'), 'format' => 'money'],
            ['label' => 'Rejected', 'value' => Booking::query()->where('status', '7')->whereBetween('refund_rejection_date', $this->dates($p))->count(), 'tone' => 'red'],
        ], 'Queued for refund');
    }

    /** @return array<string, mixed> */
    public function receiptModes(DashboardPeriod $p, User $u): array
    {
        $rows = $this->receiptQuery()->whereBetween('date', $this->dates($p))->toBase()
            ->selectRaw("COALESCE(NULLIF(mode, ''), 'Other') AS m, SUM(CAST(amount AS DECIMAL(15,2))) AS total")
            ->groupBy('m')->orderByDesc('total')->get();

        return ['labels' => $rows->pluck('m')->all(), 'series' => [['name' => 'Amount', 'data' => $rows->pluck('total')->map(fn ($v) => round((float) $v, 2))->all()]], 'format' => 'money'];
    }

    /** @return array<string, mixed> */
    public function receiptList(DashboardPeriod $p, User $u): array
    {
        $rows = $this->receiptQuery()->orderByDesc('date')->orderByDesc('id')->limit(8)
            ->get(['date', 'type_number', 'name', 'mode', 'amount']);

        return [
            'columns' => ['Date', 'Receipt no.', 'Customer', 'Mode', 'Amount'],
            'rows' => $rows->map(fn ($r) => [
                $r->date ? site_date($r->date) : '—', (string) ($r->type_number ?: '—'), (string) ($r->name ?: '—'),
                (string) ($r->mode ?: '—'), '₹ '.number_format((float) $r->amount, 2),
            ])->all(),
            'empty' => 'No receipts yet.',
        ];
    }

    // ---------------------------------------------------------------- stock

    /** @return array<string, mixed> */
    public function stock(DashboardPeriod $p, User $u): array
    {
        $row = Stock::query()->toBase()->where('status', 1)->selectRaw(
            "SUM(CASE WHEN v_status = 'Received' AND (alot_id IS NULL OR alot_id = '') AND (inv_id IS NULL OR inv_id = '') THEN 1 ELSE 0 END) AS free_count,
             SUM(CASE WHEN v_status = 'In Transit' THEN 1 ELSE 0 END) AS transit_count,
             SUM(CASE WHEN alot_id IS NOT NULL AND alot_id != '' AND (inv_id IS NULL OR inv_id = '') THEN 1 ELSE 0 END) AS allotted_count"
        )->first();

        return $this->kpi((int) ($row->free_count ?? 0), [
            ['label' => 'In transit', 'value' => (int) ($row->transit_count ?? 0)],
            ['label' => 'Allotted, not invoiced', 'value' => (int) ($row->allotted_count ?? 0), 'tone' => 'orange'],
        ], 'Received and free (all locations)');
    }

    /** @return array<string, mixed> */
    public function catalogue(DashboardPeriod $p, User $u): array
    {
        $count = fn (string $model) => $model::query()->where('is_active', 1)->count();

        return $this->kpi($count(Variant::class), [
            ['label' => 'Models', 'value' => $count(VehicleModel::class)],
            ['label' => 'Segments', 'value' => $count(Segment::class)],
        ], 'Active variants (per colour)');
    }

    /** @return array<string, mixed> */
    public function stockByModel(DashboardPeriod $p, User $u): array
    {
        // aliased join: scopes off, soft-delete filter written out
        $rows = Stock::query()->withoutGlobalScopes()->from((new Stock)->getTable().' as s')
            ->leftJoin((new Variant)->getTable().' as v', fn ($j) => $j->whereRaw('v.code COLLATE utf8mb4_unicode_ci = s.model_code COLLATE utf8mb4_unicode_ci'))
            ->whereNull('s.deleted_at')->where('s.status', 1)->where('s.v_status', 'Received')
            ->where(fn ($q) => $q->whereNull('s.alot_id')->orWhere('s.alot_id', ''))
            ->where(fn ($q) => $q->whereNull('s.inv_id')->orWhere('s.inv_id', ''))
            ->selectRaw("COALESCE(v.model_code, 'Unmapped') AS model, COUNT(*) AS n")
            ->groupBy('model')->orderByDesc('n')->limit(12)->toBase()->get();

        return ['labels' => $rows->pluck('model')->all(), 'series' => [['name' => 'Free stock', 'data' => $rows->pluck('n')->map(fn ($n) => (int) $n)->all()]], 'format' => 'number'];
    }

    // ---------------------------------------------------------------- helpers

    /** @return list<string> */
    private function openStages(): array
    {
        return array_values((array) config('dashboard.open_enquiry_stages', []));
    }

    /** Open follow-ups joined to their enquiry, filtered by the viewer's enquiry scope. */
    private function openFollowups(): Builder
    {
        $q = EnquiryFollowup::query()->withoutGlobalScopes()->from((new EnquiryFollowup)->getTable().' as f')
            ->join((new Enquiry)->getTable().' as e', 'e.enquiry_no', '=', 'f.enquiry_no')   // same collation — keeps the index
            ->whereNull('f.deleted_at')->whereNull('e.deleted_at')
            ->where('f.followup_status', 'Open')
            ->toBase();

        return DataScope::apply($q, Enquiry::class, 'e');
    }

    /** Test drives, filtered through their enquiry's scope (a test drive without an enquiry counts as unassigned). */
    private function testDriveQuery(): Builder
    {
        $q = TestDrive::query()->withoutGlobalScopes()->from((new TestDrive)->getTable().' as t')
            ->leftJoin((new Enquiry)->getTable().' as e', 'e.enquiry_no', '=', 't.enquiry_no')
            ->whereNull('t.deleted_at')
            ->toBase();

        return DataScope::apply($q, Enquiry::class, 'e');
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Booking> */
    private function alignedDeliveries(DashboardPeriod $p): \Illuminate\Database\Eloquent\Builder
    {
        return Booking::query()->where('status', '2')->whereBetween('del_date', $this->dates($p));
    }

    private function deliveredIds(): \Illuminate\Database\Eloquent\Builder
    {
        return XlDelivery::withoutDataScope()->where('status', 1)->whereNotNull('bid')->select('bid');
    }

    /** Receipts: type 1, plus legacy rows whose type was never set. @return \Illuminate\Database\Eloquent\Builder<Bookingamount> */
    private function receiptQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Bookingamount::query()->where(fn ($q) => $q->where('type', 1)->orWhereNull('type'));
    }

    /** @return array{0: string, 1: string} */
    private function dates(DashboardPeriod $p): array
    {
        return [$p->from->toDateString(), $p->to->toDateString()];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function kpi(int|float $value, array $lines = [], ?string $hint = null, string $format = 'number'): array
    {
        return ['value' => $value, 'format' => $format, 'lines' => $lines, 'hint' => $hint];
    }
}
