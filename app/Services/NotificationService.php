<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(protected MentionParserService $mentionParser)
    {
        //
    }

    public function notifyCommentAdded(Comment $comment, User $author): void
    {
        $leaveRequest = $comment->leaveRequest;

        if (!$leaveRequest) {
            return;
        }

        $participantIds = [];

        if ($leaveRequest->user_id !== $author->id) {
            $participantIds[] = $leaveRequest->user_id;
        }

        if ($leaveRequest->reviewed_by && $leaveRequest->reviewed_by !== $author->id) {
            $participantIds[] = $leaveRequest->reviewed_by;
        }

        if ($comment->parent_id) {
            $parentAuthorId = Comment::withTrashed()->find($comment->parent_id)?->user_id;

            if ($parentAuthorId && $parentAuthorId !== $author->id) {
                $participantIds[] = $parentAuthorId;
            }
        }

        $participantIds = array_values(array_unique($participantIds));

        $this->createForMany($participantIds, [
            'leave_request_id' => $leaveRequest->id,
            'type' => $comment->parent_id ? 'comment_reply' : 'comment_added',
            'title' => $comment->parent_id ? 'New reply' : 'New comment',
            'message' => $comment->parent_id
                ? "{$author->name} replied to a comment on a leave request."
                : "{$author->name} commented on a leave request.",
            'priority' => 'low',
            'created_by' => $author->id,
        ]);

        $mentionedIds = array_diff(
            $this->mentionParser->extractUserIds($comment->body),
            [$author->id],
            $participantIds,
        );

        $this->createForMany(array_values($mentionedIds), [
            'leave_request_id' => $leaveRequest->id,
            'type' => 'comment_mention',
            'title' => 'You were mentioned',
            'message' => "{$author->name} mentioned you in a comment.",
            'priority' => 'normal',
            'created_by' => $author->id,
        ]);
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
        return User::query()
            ->whereIn('role', ['admin', 'trainer'])
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
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