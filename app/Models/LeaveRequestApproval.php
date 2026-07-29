<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_request_id',
        'approver_id',
        'approver_name',
        'approver_role',
        'status',
        'reason',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public static function record(LeaveRequest $leaveRequest, User $approver, string $status, ?string $reason = null): self
    {
        return self::create([
            'leave_request_id' => $leaveRequest->id,
            'approver_id' => $approver->id,
            'approver_name' => $approver->name,
            'approver_role' => $approver->role,
            'status' => $status,
            'reason' => $reason,
            'action_at' => now(),
        ]);
    }
}