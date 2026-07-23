<?php

namespace App\Events;

use App\Models\LeaveRequest;
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
     * Create a new event instance.
     */
    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest->load(['user', 'leaveType']);
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
        return [
            'id' => $this->leaveRequest->id,
            'user_id' => $this->leaveRequest->user_id,
            'student_name' => $this->leaveRequest->user->name,
            'student_id' => $this->leaveRequest->user->student_id,
            'leave_type' => $this->leaveRequest->leaveType->name,
            'start_date' => $this->leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $this->leaveRequest->end_date->format('Y-m-d'),
            'start_time' => $this->leaveRequest->start_time,
            'end_time' => $this->leaveRequest->end_time,
            'reason' => $this->leaveRequest->reason,
            'status' => $this->leaveRequest->status,
            'created_at' => $this->leaveRequest->created_at->toIso8601String(),
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