<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use Carbon\Carbon;
use Database\Factories\Admin\EmpPostAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee ↔ Post assignment history.
 * One record per assignment period. Current = to_date IS NULL.
 * Workspace Rule: NO SQL FKs — Eloquent-only relations via emp_code / post_code.
 */
class EmpPostAssignment extends BaseModel
{
    use SoftDeletes, HasFactory;   // ← HasFactory ADDED HERE

    protected $table = 'xlr8_admin_emp_post_assignments';

    protected $fillable = [
        'emp_code',
        'post_code',
        'assignment_type',
        'from_date',
        'to_date',
        'relieving_type',
        'remarks',
        'relieved_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
    'from_date' => 'date:Y-m-d',
    'to_date'   => 'date:Y-m-d',
];

    // ── Factory ───────────────────────────────────────────────────────────

    protected static function newFactory(): EmpPostAssignmentFactory
    {
        return EmpPostAssignmentFactory::new();
    }

    // ── Relations (code-based, NO SQL FKs) ───────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_code', 'code');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(\App\Models\IAM\Post::class, 'post_code', 'post_code');
    }

    public function relievedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'relieved_by', 'id');
    }

    // ── Named Scopes ──────────────────────────────────────────────────────

    /** Active (not yet relieved) */
    public function scopeCurrent(Builder $q): Builder
    {
        return $q->whereNull('to_date');
    }

    public function scopePrimary(Builder $q): Builder
    {
        return $q->where('assignment_type', 'primary');
    }

    public function scopeAdditional(Builder $q): Builder
    {
        return $q->where('assignment_type', 'additional');
    }

    /**
     * Assignments active on a specific date.
     * from_date <= $date AND (to_date IS NULL OR to_date >= $date)
     */
    public function scopeOnDate(Builder $q, string|Carbon $date): Builder
    {
        $d = Carbon::parse($date)->toDateString();
        return $q->where('from_date', '<=', $d)
                 ->where(function (Builder $q) use ($d) {
                     $q->whereNull('to_date')
                       ->orWhere('to_date', '>=', $d);
                 });
    }

    public function scopeForEmployee(Builder $q, string $empCode): Builder
    {
        return $q->where('emp_code', $empCode);
    }

    public function scopeForPost(Builder $q, string $postCode): Builder
    {
        return $q->where('post_code', $postCode);
    }

    public function scopeChronological(Builder $q): Builder
    {
        return $q->orderBy('from_date');
    }

    public function scopeReverseChronological(Builder $q): Builder
    {
        return $q->orderByDesc('from_date');
    }

    public function scopeByRelievingType(Builder $q, string $type): Builder
    {
        return $q->where('relieving_type', $type);
    }

    // ── Computed Helpers ──────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->to_date === null;
    }

    public function getDurationDaysAttribute(): ?int
    {
        $end = $this->to_date ?? now();
        return $this->from_date->diffInDays($end);
    }
}