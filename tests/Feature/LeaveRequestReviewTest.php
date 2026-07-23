<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_can_approve_a_pending_leave_request(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $trainer = User::factory()->create([
            'role' => 'trainer',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($trainer);

        $response = $this->patchJson(route('leave-requests.approve', $leaveRequest), [
            'comment' => 'Approved after checking the timetable.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Leave request approved successfully.')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewed_by', $trainer->id)
            ->assertJsonPath('data.review_note', 'Approved after checking the timetable.')
            ->assertJsonPath('data.comment', 'Approved after checking the timetable.')
            ->assertJsonPath('data.reviewer.id', $trainer->id);

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($trainer->id, $leaveRequest->reviewed_by);
        $this->assertNotNull($leaveRequest->reviewed_at);
        $this->assertSame('Approved after checking the timetable.', $leaveRequest->review_note);
    }

    public function test_trainer_can_reject_a_pending_leave_request_without_comment(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $trainer = User::factory()->create([
            'role' => 'trainer',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($trainer);

        $response = $this->patchJson(route('leave-requests.reject', $leaveRequest));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Leave request rejected successfully.')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reviewed_by', $trainer->id)
            ->assertJsonPath('data.review_note', null)
            ->assertJsonPath('data.comment', null);

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame($trainer->id, $leaveRequest->reviewed_by);
        $this->assertNotNull($leaveRequest->reviewed_at);
        $this->assertNull($leaveRequest->review_note);
    }

    public function test_a_reviewed_leave_request_cannot_be_reviewed_twice(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $trainer = User::factory()->create([
            'role' => 'trainer',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($trainer);

        $this->patchJson(route('leave-requests.approve', $leaveRequest), [
            'comment' => 'Approved once.',
        ])->assertOk();

        $response = $this->patchJson(route('leave-requests.reject', $leaveRequest), [
            'comment' => 'Trying to reject after approval.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only pending leave requests can be reviewed.');

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($trainer->id, $leaveRequest->reviewed_by);
        $this->assertSame('Approved once.', $leaveRequest->review_note);
    }

    public function test_student_cannot_review_leave_requests(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($student);

        Sanctum::actingAs($student);

        $response = $this->patchJson(route('leave-requests.approve', $leaveRequest));

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You are not authorized to perform this action.');
    }

    protected function makePendingLeaveRequest(User $student): LeaveRequest
    {
        $leaveType = LeaveType::create([
            'name' => 'General Leave',
            'code' => 'LT-' . Str::upper(Str::random(8)),
            'description' => 'General leave request used for tests.',
            'max_days_per_year' => 10,
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        return LeaveRequest::create([
            'user_id' => $student->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'I need time away from class.',
            'status' => 'pending',
        ]);
    }
}
