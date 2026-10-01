<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\CRM\EnquiryFollowup;
use App\Models\Module\Booking\Booking;
use App\Services\IAM\DataScope\ScopeCodeFiller;
use App\Services\OrgScopeService;
use App\Services\Utils\SynonymService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Fills empty scope codes on existing rows so user data scoping can filter them (DEC-071). Only empty codes are
 * written. Without --apply it only reports what it would fill.
 *
 *   php artisan data-scope:backfill                     report for bookings and enquiries
 *   php artisan data-scope:backfill --entity=booking --apply
 *
 * Bookings: from the linked enquiry and quotation, then the masters (ScopeCodeFiller).
 * Enquiries: branch / location from the consultant's employee record (x8_sc_code → employee.code, sc_mile_id /
 * x8_sc_mile_id → employee.mile_id), else from the enquiry's follow-up dealer_location names; vehicle parents from
 * the masters. Free-text model / variant names are not mapped (no reliable key) — they stay unassigned.
 */
class DataScopeBackfill extends Command
{
    protected $signature = 'data-scope:backfill {--entity=all : booking | enquiry | all} {--apply : write the codes (default: report only)}';

    protected $description = 'Fill empty branch / location / vehicle scope codes on bookings and enquiries (DEC-071)';

    public function handle(ScopeCodeFiller $filler, SynonymService $synonyms): int
    {
        $entity = strtolower((string) $this->option('entity'));
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Writing codes.' : 'Report only — add --apply to write.');

        if (in_array($entity, ['all', 'booking'], true)) {
            $this->bookings($filler, $apply);
        }
        if (in_array($entity, ['all', 'enquiry'], true)) {
            $this->enquiries($apply, $synonyms);
        }

        return self::SUCCESS;
    }

    private function bookings(ScopeCodeFiller $filler, bool $apply): void
    {
        $filled = [];
        $total = 0;
        Booking::withoutDataScope()->chunkById(200, function ($bookings) use ($filler, $apply, &$filled, &$total) {
            foreach ($bookings as $booking) {
                $total++;
                foreach ($filler->fillBooking($booking) as $column) {
                    $filled[$column] = ($filled[$column] ?? 0) + 1;
                }
                if ($apply && $booking->isDirty()) {
                    $booking->saveQuietly();
                }
            }
        });

        $this->report('Bookings', $total, $filled, Booking::class, ['branch_code', 'location_code', 'segment_code', 'sub_segment_code', 'model_code', 'variant_code']);
    }

    private function enquiries(bool $apply, SynonymService $synonyms): void
    {
        $table = (new Enquiry)->getTable();
        // DEC-093: plain query builders from the models (scopes off, explicit deleted_at filters, no updated_at on update)
        $enquiries = fn (string $alias = '') => Enquiry::withoutGlobalScopes()->from($alias === '' ? $table : "{$table} as {$alias}")->toBase();
        $filled = [];
        $total = $enquiries()->whereNull('deleted_at')->count();

        // 1. consultant's employee → primary branch / location
        $byCode = $enquiries('e')
            ->join('xlr8_admin_employee as emp', fn ($j) => $j->whereRaw('emp.code COLLATE utf8mb4_unicode_ci = e.x8_sc_code COLLATE utf8mb4_unicode_ci'));
        $byMile = $enquiries('e')
            ->join('xlr8_admin_employee as emp', function ($j) {
                $j->whereRaw('emp.mile_id COLLATE utf8mb4_unicode_ci = e.sc_mile_id COLLATE utf8mb4_unicode_ci')
                    ->orWhereRaw('emp.mile_id COLLATE utf8mb4_unicode_ci = e.x8_sc_mile_id COLLATE utf8mb4_unicode_ci');
            });
        foreach (['consultant code' => $byCode, 'consultant mile id' => $byMile] as $label => $join) {
            foreach (['dealer_branch' => 'primary_branch_code', 'dealer_location' => 'primary_loc_code'] as $col => $empCol) {
                $q = (clone $join)->whereNull('e.deleted_at')
                    ->where(fn ($w) => $w->whereNull("e.{$col}")->orWhere("e.{$col}", ''))
                    ->whereNotNull("emp.{$empCol}")->where("emp.{$empCol}", '!=', '');
                $n = $apply ? $q->update(["e.{$col}" => $q->raw("emp.{$empCol}")]) : $q->count();
                $filled["{$col} ({$label})"] = $n;
            }
        }

        // 2. follow-up dealer_location names → location code (and its branch)
        $names = EnquiryFollowup::withoutGlobalScopes()->toBase()->whereNotNull('dealer_location')->where('dealer_location', '!=', '')
            ->distinct()->pluck('dealer_location');
        $map = [];
        $unmatched = [];
        foreach ($names as $name) {
            // Utilities → Synonyms (type "Location") first, then the location master's code / name
            $candidate = $synonyms->getSynonym('Location', (string) $name) ?? (string) $name;
            $code = OrgScopeService::resolveCode('location', $candidate);
            if ($code !== null && $code !== 'ALL') {
                $map[(string) $name] = $code;
            } else {
                $unmatched[] = (string) $name;
            }
        }
        if ($unmatched !== []) {
            $this->warn('Follow-up location names with no match — add them as "Location" synonyms and re-run: '.implode(' | ', $unmatched));
        }
        $branchOf = Location::withoutGlobalScopes()->toBase()->whereNull('deleted_at')->pluck('branch_code', 'code')
            ->mapWithKeys(fn ($b, $c) => [strtoupper((string) $c) => strtoupper((string) $b)])->all();
        $fromFollowUp = 0;
        foreach ($map as $name => $code) {
            $q = $enquiries('e')->whereNull('e.deleted_at')
                ->where(fn ($w) => $w->whereNull('e.dealer_location')->orWhere('e.dealer_location', ''))
                ->whereIn('e.enquiry_no', EnquiryFollowup::withoutGlobalScopes()->toBase()->where('dealer_location', $name)->select('enquiry_no'));
            $fromFollowUp += $apply ? $q->update(['e.dealer_location' => $code]) : $q->count();
        }
        $filled['dealer_location (follow-up names: '.count($map).' of '.$names->count().' names matched)'] = $fromFollowUp;

        // 3. location → branch
        $n = 0;
        foreach ($branchOf as $loc => $branch) {
            $q = $enquiries()->whereNull('deleted_at')->where('dealer_location', $loc)
                ->where(fn ($w) => $w->whereNull('dealer_branch')->orWhere('dealer_branch', ''));
            $n += $apply ? $q->update(['dealer_branch' => $branch]) : $q->count();
        }
        $filled['dealer_branch (from location)'] = $n;

        $this->report('Enquiries', $total, $filled, Enquiry::class, ['dealer_branch', 'dealer_location', 'segment_code', 'model_code', 'variant_code']);
    }

    /**
     * @param  array<string, int>  $filled
     * @param  class-string<Model>  $model
     * @param  list<string>  $columns
     */
    private function report(string $label, int $total, array $filled, string $model, array $columns): void
    {
        $this->newLine();
        $this->info("{$label}: {$total} rows");
        $this->table(['Filled (this run)', 'Rows'], collect($filled)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $coverage = [];
        foreach ($columns as $column) {
            $n = $model::withoutGlobalScopes()->toBase()->whereNull('deleted_at')->whereNotNull($column)->where($column, '!=', '')->count();
            $coverage[] = [$column, $n, $total > 0 ? round($n * 100 / $total, 1).'%' : '—'];
        }
        $this->table(['Column', 'Rows with a code now', 'Coverage'], $coverage);
    }
}
