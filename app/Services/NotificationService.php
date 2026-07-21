<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct()
    {
        //
    }

    public function notifyLeaveSubmitted(LeaveRequest $leaveRequest): void
    {
        $student = $leaveRequest->user;

        $recipients = $this->reviewersFor($student);

        $this->createForMany($recipients, [
            'leave_request_id' => $leaveRequest->id,
            'type' => 'leave_submitted',
            'title' => 'New leave request',
            'message' => "{$student->name} submitted a leave request for review.",
            'priority' => 'normal',
            'created_by' => $student->id,
        ]);
    }

    public function notifyLeaveApproved(LeaveRequest $leaveRequest, User $reviewer): void
    {
        $this->createFor($leaveRequest->user_id, [
            'leave_request_id' => $leaveRequest->id,
            'type' => 'leave_approved',
            'title' => 'Leave request approved',
            'message' => "Your {$this->rangeLabel($leaveRequest)} leave request was approved.",
            'priority' => 'normal',
            'created_by' => $reviewer->id,
        ]);
    }

    public function notifyLeaveRejected(LeaveRequest $leaveRequest, User $reviewer): void
    {
        $this->createFor($leaveRequest->user_id, [
            'leave_request_id' => $leaveRequest->id,
            'type' => 'leave_rejected',
            'title' => 'Leave request rejected',
            'message' => "Your {$this->rangeLabel($leaveRequest)} leave request was rejected.",
            'priority' => 'high',
            'created_by' => $reviewer->id,
        ]);
    }

    public function notifyLeaveCancelled(LeaveRequest $leaveRequest): void
    {
        $student = $leaveRequest->user;

        $recipients = $this->reviewersFor($student);

        $this->createForMany($recipients, [
            'leave_request_id' => $leaveRequest->id,
            'type' => 'leave_cancelled',
            'title' => 'Leave request cancelled',
            'message' => "{$student->name} cancelled their pending leave request.",
            'priority' => 'low',
            'created_by' => $student->id,
        ]);
    }

    protected function reviewersFor(User $student): array
    {
        $ids = User::query()
            ->whereIn('role', ['admin', 'trainer'])
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    protected function rangeLabel(LeaveRequest $leaveRequest): string
    {
        $start = $leaveRequest->start_date?->format('M j');
        $end = $leaveRequest->end_date?->format('M j');

        return $start === $end ? $start : "{$start} – {$end}";
    }

    protected function createFor(int $userId, array $attributes): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'is_read' => false,
            ...$attributes,
        ]);
    }

    protected function createForMany(array $userIds, array $attributes): void
    {
        if (empty($userIds)) {
            return;
        }

        $now = now();

        $rows = array_map(fn (int $userId) => [
            'user_id' => $userId,
            'leave_request_id' => $attributes['leave_request_id'],
            'type' => $attributes['type'],
            'title' => $attributes['title'],
            'message' => $attributes['message'],
            'is_read' => false,
            'priority' => $attributes['priority'] ?? 'normal',
            'created_by' => $attributes['created_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $userIds);

        DB::table('notifications')->insert($rows);
    }
}
