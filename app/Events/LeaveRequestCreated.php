<?php

namespace App\Events;

use App\Models\LeaveRequest;
use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The leave request instance.
     *
     * @var LeaveRequest
     */
    public $leaveRequest;

    /**
     * The notifications created for this leave request.
     *
     * @var array<int, Notification>
     */
    public array $notifications;

    /**
     * Create a new event instance.
     */
    public function __construct(LeaveRequest $leaveRequest, array $notifications = [])
    {
        $this->leaveRequest = $leaveRequest->load(['user', 'leaveType']);
        $this->notifications = $notifications;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to all trainers
        $channels[] = new PrivateChannel('trainers');

        // Broadcast to all admins
        $channels[] = new PrivateChannel('admins');

        // Broadcast to the specific student who created the request
        $channels[] = new PrivateChannel('student.' . $this->leaveRequest->user_id);

        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // If notifications are provided, broadcast the first one (for trainers/admins)
        if (!empty($this->notifications)) {
            $notification = $this->notifications[0];
            
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'leave_request_id' => $notification->leave_request_id,
                'actor' => $notification->creator ? [
                    'id' => $notification->creator->id,
                    'name' => $notification->creator->name,
                    'avatar_id' => $notification->creator->avatar_id,
                ] : null,
                'read' => !$notification->is_read,
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        }

        // Fallback to leave request data if no notifications provided
        return [
            'id' => $this->leaveRequest->id,
            'type' => 'leave_submitted',
            'title' => 'New leave request',
            'message' => "{$this->leaveRequest->user->name} submitted a leave request for review.",
            'leave_request_id' => $this->leaveRequest->id,
            'actor' => [
                'id' => $this->leaveRequest->user->id,
                'name' => $this->leaveRequest->user->name,
                'avatar_id' => $this->leaveRequest->user->avatar_id,
            ],
            'read' => false,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'leave-request.created';
    }
}
