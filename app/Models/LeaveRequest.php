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
    public const HOURLY_DURATION_STEP = 0.5;

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

    public static function isValidHourlyDuration(float $hours): bool
    {
        if ($hours < self::MIN_HOURLY_DURATION || $hours > self::MAX_HOURLY_DURATION) {
            return false;
        }

        $steps = $hours / self::HOURLY_DURATION_STEP;

        return abs($steps - round($steps)) < 0.0001;
    }

    public static function calculateHoursFromTimes(string $startTime, string $endTime): float
    {
        $start = Carbon::createFromFormat(strlen($startTime) > 5 ? 'H:i:s' : 'H:i', $startTime);
        $end = Carbon::createFromFormat(strlen($endTime) > 5 ? 'H:i:s' : 'H:i', $endTime);

        $minutes = $start->diffInMinutes($end, false);

        return round($minutes / 60, 1);
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