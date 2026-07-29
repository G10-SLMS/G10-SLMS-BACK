<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestUnderReviewTest extends TestCase
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

    public function test_educator_can_mark_a_pending_leave_request_as_under_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create(['name' => 'Dara Vann']);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Leave request marked as under review.')
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.reviewed_by', $educator->id)
            ->assertJsonPath('data.reviewer.id', $educator->id)
            ->assertJsonPath('data.approval_history.0.status', 'under_review')
            ->assertJsonPath('data.approval_history.0.approver.id', $educator->id);

        $leaveRequest->refresh();

        $this->assertSame('under_review', $leaveRequest->status);
        $this->assertSame($educator->id, $leaveRequest->reviewed_by);
        // Only the final approve/reject decision stamps reviewed_at.
        $this->assertNull($leaveRequest->reviewed_at);

        $this->assertDatabaseHas('leave_request_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'approver_id' => $educator->id,
            'status' => 'under_review',
        ]);
    }

    public function test_admin_can_mark_a_pending_leave_request_as_under_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->admin()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($admin);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ])->assertOk()->assertJsonPath('data.status', 'under_review');
    }

    public function test_marking_under_review_notifies_the_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'leave_request_id' => $leaveRequest->id,
            'type' => 'leave_under_review',
        ]);
    }

    public function test_an_under_review_leave_request_can_still_be_approved(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ])->assertOk();

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
            'review_note' => 'All good after review.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $educator->id);

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertNotNull($leaveRequest->reviewed_at);

        // Both the under_review and approved actions should be on record.
        $this->assertSame(
            2,
            LeaveRequestApproval::where('leave_request_id', $leaveRequest->id)->count()
        );
    }

    public function test_an_under_review_leave_request_can_still_be_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ])->assertOk();

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'rejected',
            'review_note' => 'Not enough notice given.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_a_pending_leave_request_can_still_be_approved_directly_without_under_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_an_already_reviewed_leave_request_cannot_be_marked_under_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
        ])->assertOk();

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only pending leave requests can be marked as under review.');
    }

    public function test_a_leave_request_already_under_review_cannot_be_marked_under_review_again(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);

        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ])->assertOk();

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only pending leave requests can be marked as under review.');
    }

    public function test_student_cannot_mark_their_own_leave_request_as_under_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($student);

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ]);

        // Rejected by form-request validation (not a valid status for a
        // student to set), before it ever reaches the controller.
        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame('Status must be cancelled.', $response->json('errors.status.0'));

        $leaveRequest->refresh();
        $this->assertSame('pending', $leaveRequest->status);
    }

    public function test_student_cannot_edit_a_leave_request_that_is_under_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $educator = User::factory()->educator()->create();

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($educator);
        $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'status' => 'under_review',
        ])->assertOk();

        Sanctum::actingAs($student);

        $response = $this->putJson("/api/leave-requests/{$leaveRequest->id}", [
            'reason' => 'Trying to change the reason.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot edit a request that has already been reviewed (under_review).');
    }

    public function test_stats_endpoint_includes_under_review_count(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->admin()->create();

        LeaveRequest::factory()->for($student, 'user')->create([
            'leave_type_id' => LeaveType::factory(),
            'status' => 'under_review',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/leave-requests/stats');

        $response->assertOk()->assertJsonPath('data.under_review', 1);
    }

    public function test_leave_history_can_be_filtered_by_under_review_status(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $leaveType = LeaveType::factory()->create();

        LeaveRequest::factory()->create([
            'user_id' => $student->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'under_review',
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $student->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/leave-history?status=under_review');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('under_review', $response->json('data.0.status'));
    }
}
