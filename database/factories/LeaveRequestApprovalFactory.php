<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestApprovalFactory extends Factory
{
    public function definition(): array
    {
        $approver = User::factory()->educator()->create();

        return [
            'leave_request_id' => LeaveRequest::factory(),
            'approver_id' => $approver->id,
            'approver_name' => $approver->name,
            'approver_role' => $approver->role,
            'status' => 'approved',
            'reason' => null,
            'action_at' => now(),
        ];
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'reason' => $reason ?? fake()->sentence(),
        ]);
    }
}
