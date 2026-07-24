<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use HasFactory;

    public const MIN_HOURLY_DURATION = 0.5;
    public const MAX_HOURLY_DURATION = 8;

    public const DURATION_TYPES = ['full_day', 'hourly'];

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
        'start_time',
        'end_time',
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

    protected $appends = ['duration_label'];

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