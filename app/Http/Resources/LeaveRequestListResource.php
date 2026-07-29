<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'leave_type_id' => $this->leave_type_id,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_type' => $this->duration_type,
            'duration_hours' => $this->duration_hours,
            'duration_label' => $this->duration_label,
            'reason' => $this->reason,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
            'attachments_count' => $this->whenCounted('attachments'),
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'student_id' => $this->user->student_id,
                'avatar_id' => $this->user->avatar_id,
                'avatar_url' => $this->user->avatar?->url,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
        ];
    }
}
