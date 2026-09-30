<?php

declare(strict_types=1);

namespace App\Services\IAM\DataScope;

use App\Models\Admin\Location;
use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;

/**
 * Fills the scope codes a record is filtered on (DEC-071) — only codes that are still empty, never overwriting.
 * Used when a booking / enquiry is saved and by `php artisan data-scope:backfill`.
 *
 * Sources, in order: the record's own links (enquiry, quotation snapshot), the acting employee's primary branch /
 * location (enquiries only), then the masters: a variant gives its model / sub-segment / segment, a model gives its
 * sub-segment / segment, a location gives its branch. All lookups run without data scoping.
 */
class ScopeCodeFiller
{
    /** Fills empty booking codes from its enquiry and quotation; returns the columns it set. @return list<string> */
    public function fillBooking(Booking $booking): array
    {
        $before = $this->snapshot($booking, ['branch_code', 'location_code', 'segment_code', 'sub_segment_code', 'model_code', 'variant_code']);

        $enquiry = $this->enquiryFor($booking);
        if ($enquiry) {
            $this->fillEmpty($booking, [
                'branch_code' => $enquiry->dealer_branch,
                'location_code' => $enquiry->dealer_location,
                'segment_code' => $enquiry->segment_code,
                'model_code' => $enquiry->model_code,
                'variant_code' => $enquiry->variant_code,
            ]);
        }

        if (! empty($booking->quotation_id)) {
            $quotation = Quotation::withoutDataScope()->find($booking->quotation_id);
            $data = is_array($quotation?->standard_data) ? $quotation->standard_data : [];
            $this->fillEmpty($booking, [
                'segment_code' => $data['segment_code'] ?? null,
                'model_code' => $data['model_code'] ?? null,
                'variant_code' => $data['variant_code'] ?? ($data['oem_code'] ?? null),
            ]);
        }

        $this->deriveVehicle($booking, 'segment_code', 'sub_segment_code', 'model_code', 'variant_code');
        $this->deriveBranch($booking, 'branch_code', 'location_code');

        return $this->changed($booking, $before);
    }

    /** Fills empty enquiry codes (acting employee's branch / location, vehicle parents); returns the columns set. @return list<string> */
    public function fillEnquiry(Enquiry $enquiry, ?User $actor = null): array
    {
        $before = $this->snapshot($enquiry, ['dealer_branch', 'dealer_location', 'segment_code', 'model_code', 'variant_code']);

        $employee = $actor?->employee;
        if ($employee) {
            $this->fillEmpty($enquiry, [
                'dealer_branch' => $employee->primary_branch_code,
                'dealer_location' => $employee->primary_loc_code,
            ]);
        }

        $this->deriveVehicle($enquiry, 'segment_code', null, 'model_code', 'variant_code');
        $this->deriveBranch($enquiry, 'dealer_branch', 'dealer_location');

        return $this->changed($enquiry, $before);
    }

    private function enquiryFor(Booking $booking): ?Enquiry
    {
        $ref = trim((string) $booking->enq_no);
        if ($ref === '') {
            return null;
        }

        return Enquiry::withoutDataScope()
            ->where(fn ($q) => $q->where('enquiry_no', $ref)->orWhere('quick_enquiry_no', $ref)
                ->when(ctype_digit($ref), fn ($w) => $w->orWhere('id', (int) $ref))
                ->when(preg_match('/^XENQ-(\d+)$/i', $ref, $m) === 1, fn ($w) => $w->orWhere('id', (int) ($m[1] ?? 0))))
            ->first();
    }

    /** Variant → model / sub-segment / segment; model → sub-segment / segment. */
    private function deriveVehicle(object $record, string $segmentCol, ?string $subSegmentCol, string $modelCol, string $variantCol): void
    {
        $variant = $this->code($record->{$variantCol} ?? null);
        if ($variant !== null) {
            $row = Variant::query()->where('code', $variant)->toBase()
                ->first(['segment_code', 'sub_segment_code', 'model_code']);
            if ($row) {
                $this->fillEmpty($record, array_filter([
                    $modelCol => $row->model_code,
                    $segmentCol => $row->segment_code,
                    $subSegmentCol => $row->sub_segment_code,
                ], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH));
            }
        }

        $model = $this->code($record->{$modelCol} ?? null);
        if ($model !== null) {
            $row = VehicleModel::query()->where('code', $model)->toBase()
                ->first(['segment_code', 'sub_segment_code']);
            if ($row) {
                $this->fillEmpty($record, array_filter([
                    $segmentCol => $row->segment_code,
                    $subSegmentCol => $row->sub_segment_code,
                ], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH));
            }
        }
    }

    /** Location → branch. */
    private function deriveBranch(object $record, string $branchCol, string $locationCol): void
    {
        $location = $this->code($record->{$locationCol} ?? null);
        if ($location !== null && $this->code($record->{$branchCol} ?? null) === null) {
            $branch = Location::query()->where('code', $location)->value('branch_code');
            $this->fillEmpty($record, [$branchCol => $branch]);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function fillEmpty(object $record, array $values): void
    {
        foreach ($values as $column => $value) {
            $value = $this->code($value);
            if ($column !== '' && $value !== null && $this->code($record->{$column} ?? null) === null) {
                $record->{$column} = $value;
            }
        }
    }

    private function code(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['', '-', 'NULL', 'N/A', '0'], true) ? null : $value;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function snapshot(object $record, array $columns): array
    {
        $out = [];
        foreach ($columns as $column) {
            $out[$column] = $record->{$column} ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $before
     * @return list<string>
     */
    private function changed(object $record, array $before): array
    {
        return array_values(array_filter(array_keys($before), fn ($column) => ($record->{$column} ?? null) !== $before[$column]));
    }
}
