<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveApprovalTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function makePendingLeaveRequest(User $student): LeaveRequest
    {
        $leaveType = LeaveType::factory()->create(['requires_attachment' => false]);

        return LeaveRequest::factory()->create([
            'user_id' => $student->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
        ]);
    }

    public function test_approving_a_leave_request_records_approver_details_and_history(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create(['name' => 'Dara Vann']);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
            'review_note' => 'Looks good.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $educator->id)
            ->assertJsonPath('data.reviewer.id', $educator->id)
            ->assertJsonPath('data.approval_history.0.status', 'approved')
            ->assertJsonPath('data.approval_history.0.reason', 'Looks good.')
            ->assertJsonPath('data.approval_history.0.approver.id', $educator->id)
            ->assertJsonPath('data.approval_history.0.approver.name', 'Dara Vann')
            ->assertJsonPath('data.approval_history.0.approver.role', 'educator');

        $this->assertDatabaseHas('leave_request_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'approver_id' => $educator->id,
            'approver_name' => 'Dara Vann',
            'approver_role' => 'educator',
            'status' => 'approved',
            'reason' => 'Looks good.',
        ]);
    }

    public function test_rejecting_a_leave_request_records_the_rejection_reason(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->admin()->create(['name' => 'System Admin']);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'rejected',
            'review_note' => 'Not enough notice given.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approval_history.0.status', 'rejected')
            ->assertJsonPath('data.approval_history.0.reason', 'Not enough notice given.')
            ->assertJsonPath('data.approval_history.0.approver.role', 'admin');

        $this->assertDatabaseHas('leave_request_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'approver_id' => $admin->id,
            'status' => 'rejected',
            'reason' => 'Not enough notice given.',
        ]);
    }

    public function test_approval_history_survives_after_the_approver_account_is_deleted(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create(['name' => 'Sophal Chan']);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
        ])->assertOk();

        $educator->delete();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertOk()
            ->assertJsonPath('data.approval_history.0.approver.id', null)
            ->assertJsonPath('data.approval_history.0.approver.name', 'Sophal Chan')
            ->assertJsonPath('data.approval_history.0.approver.role', 'educator');
    }

    public function test_a_leave_request_cannot_be_reviewed_twice(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
        ])->assertOk();

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'rejected',
        ]);

        $response->assertStatus(422);

        $this->assertSame(
            1,
            LeaveRequestApproval::where('leave_request_id', $leaveRequest->id)->count()
        );
    }
}
