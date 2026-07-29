<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class LeaveRequest extends Model
{
    use HasFactory;

    public const MIN_HOURLY_DURATION = 0.5;
    public const MAX_HOURLY_DURATION = 8;

    public const DURATION_TYPES = ['full_day', 'hourly'];
    public const DETAIL_RELATIONS = ['leaveType', 'user.avatar', 'reviewer', 'attachments', 'approvalHistory.approver'];

    // Full workflow: pending -> under_review -> approved/rejected, with
    // cancelled reachable from pending only (see LeaveRequestController::cancel()).
    public const STATUSES = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];

    // Statuses an Admin/Educator can move a request to from its current status.
    public const REVIEW_STATUSES = ['under_review', 'approved', 'rejected'];

    // Terminal, final-decision statuses (as opposed to 'pending'/'under_review').
    public const DECISION_STATUSES = ['approved', 'rejected'];

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'reason',
        'duration_type',
        'duration_hours',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_hours' => 'decimal:1',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = ['duration_label'];

    public function isAwaitingDecision(): bool
    {
        return in_array($this->status, ['pending', 'under_review'], true);
    }

    public static function isValidHourlyDuration(int $minutes): bool
    {
        $minMinutes = (int) round(self::MIN_HOURLY_DURATION * 60);
        $maxMinutes = (int) round(self::MAX_HOURLY_DURATION * 60);

        return $minutes >= $minMinutes && $minutes <= $maxMinutes;
    }

    public static function calculateMinutesFromTimes(string $startTime, string $endTime): int
    {
        $start = Carbon::createFromFormat(strlen($startTime) > 5 ? 'H:i:s' : 'H:i', $startTime);
        $end = Carbon::createFromFormat(strlen($endTime) > 5 ? 'H:i:s' : 'H:i', $endTime);

        return $start->diffInMinutes($end, false);
    }

    public static function calculateHoursFromTimes(string $startTime, string $endTime): float
    {
        return round(self::calculateMinutesFromTimes($startTime, $endTime) / 60, 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalHistory(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class)->orderByDesc('action_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopeWithFullDetails(Builder $query): Builder
    {
        return $query->with(self::DETAIL_RELATIONS);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'student') {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public function scopeSearchTerm(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            if (is_numeric($search)) {
                $q->orWhere('id', $search);
            }

            $q->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'LIKE', "%{$search}%"));

            $q->orWhereHas('user', function (Builder $userQuery) use ($search) {
                $userQuery->where('student_id', 'LIKE', "%{$search}%");

                // Tolerate a leading zero being typed or omitted (e.g. "0123" vs "123").
                if (preg_match('/^0(\d+)$/', $search, $matches)) {
                    $userQuery->orWhere('student_id', 'LIKE', "%{$matches[1]}%");
                }
                if (is_numeric($search) && !str_starts_with($search, '0')) {
                    $userQuery->orWhere('student_id', 'LIKE', "%0{$search}%");
                }
            });

            $q->orWhereHas('leaveType', fn (Builder $typeQuery) => $typeQuery->where('name', 'LIKE', "%{$search}%"));

            $q->orWhere('status', 'LIKE', "%{$search}%");
        });
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('end_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['submission_start_date'])) {
            $query->whereDate('created_at', '>=', $filters['submission_start_date']);
        }

        if (!empty($filters['submission_end_date'])) {
            $query->whereDate('created_at', '<=', $filters['submission_end_date']);
        }

        return $query;
    }

    private const SORT_COLUMNS = [
        'start_date_asc' => ['start_date', 'asc'],
        'start_date_desc' => ['start_date', 'desc'],
        'end_date_asc' => ['end_date', 'asc'],
        'end_date_desc' => ['end_date', 'desc'],
        'submission_date_asc' => ['created_at', 'asc'],
        'submission_date_desc' => ['created_at', 'desc'],
        'latest' => ['created_at', 'desc'],
    ];

    public function scopeApplySort(Builder $query, ?string $sortBy): Builder
    {
        [$column, $direction] = self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['latest'];

        return $query->orderBy($column, $direction);
    }

    /** Build the full index() query from a request in one line. */
    public function scopeForListing(Builder $query, Request $request): Builder
    {
        return $query
            ->visibleTo($request->user())
            ->searchTerm($request->query('search'))
            ->applyFilters($request->only([
                'status', 'leave_type_id', 'start_date', 'end_date',
                'submission_start_date', 'submission_end_date',
            ]))
            ->applySort($request->query('sort', 'latest'));
    }

    public function getStartTimeAttribute($value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }

    public function getEndTimeAttribute($value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }

    protected function formatTimeForDisplay(string $value): string
    {
        return Carbon::createFromFormat('H:i', substr($value, 0, 5))->format('g:i A');
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->duration_type === 'hourly') {
            $hours = (float) $this->duration_hours;
            $formatted = $hours == (int) $hours ? (int) $hours : $hours;
            $label = $formatted . ' ' . ($formatted == 1 ? 'hour' : 'hours');

            if ($this->start_time && $this->end_time) {
                $label .= sprintf(
                    ' (%s - %s)',
                    $this->formatTimeForDisplay($this->start_time),
                    $this->formatTimeForDisplay($this->end_time),
                );
            }

            return $label;
        }

        if (!$this->start_date || !$this->end_date) {
            return 'Full day';
        }

        $days = $this->start_date->diffInDays($this->end_date) + 1;

        return $days . ' ' . ($days == 1 ? 'day' : 'days');
    }
}
