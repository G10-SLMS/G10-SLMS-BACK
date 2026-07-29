<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->reason,
            'action_at' => $this->action_at,
            'approver' => [
                'id' => $this->approver_id,
                // Snapshotted at the time of the action, so this stays
                // accurate even if the user is later deleted or renamed.
                'name' => $this->approver_name,
                'role' => $this->approver_role,
            ],
        ];
    }
}
