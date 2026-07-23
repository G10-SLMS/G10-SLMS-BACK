<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_upload_a_supporting_document(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($student);
        $file = UploadedFile::fake()->create('medical-note.pdf', 512, 'application/pdf');

        Sanctum::actingAs($student);

        $response = $this->postJson(route('leave-requests.attachments.store', $leaveRequest), [
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attachment.original_name', 'medical-note.pdf');

        $attachment = Attachment::query()->firstOrFail();

        $this->assertSame($leaveRequest->id, $attachment->leave_request_id);
        $this->assertSame($student->id, $attachment->uploaded_by);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($student);
        $file = UploadedFile::fake()->create('notes.txt', 20, 'text/plain');

        Sanctum::actingAs($student);

        $response = $this->postJson(route('leave-requests.attachments.store', $leaveRequest), [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_student_cannot_upload_to_someone_else_leave_request(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create([
            'role' => 'student',
        ]);

        $otherStudent = User::factory()->create([
            'role' => 'student',
        ]);

        $leaveRequest = $this->makePendingLeaveRequest($owner);
        $file = UploadedFile::fake()->create('medical-note.pdf', 256, 'application/pdf');

        Sanctum::actingAs($otherStudent);

        $response = $this->postJson(route('leave-requests.attachments.store', $leaveRequest), [
            'file' => $file,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'You are not authorized to upload files for this leave request.');

        $this->assertDatabaseCount('attachments', 0);
    }

    protected function makePendingLeaveRequest(User $student): LeaveRequest
    {
        $leaveType = LeaveType::create([
            'name' => 'Medical Leave',
            'code' => 'MED-' . substr((string) Str::uuid(), 0, 8),
            'description' => 'Leave for medical reasons.',
            'max_days_per_year' => 10,
            'requires_attachment' => true,
            'is_active' => true,
        ]);

        return LeaveRequest::create([
            'user_id' => $student->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'I need to submit a supporting document.',
            'status' => 'pending',
        ]);
    }
}
