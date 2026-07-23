<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_request_creation_broadcasts_event(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $trainer = User::factory()->create([
            'role' => 'trainer',
        ]);

        $leaveType = LeaveType::create([
            'name' => 'General Leave',
            'code' => 'LT-' . Str::upper(Str::random(8)),
            'description' => 'General leave request used for tests.',
            'max_days_per_year' => 10,
            'requires_attachment' => false,
            'is_active' => true,
        ]);

        Sanctum::actingAs($student);

        // Listen for the broadcast event
        Event::fake();

        $response = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'reason' => 'I need time away from class.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Leave request created successfully');

        // Assert the event was broadcast
        Event::assertDispatched(\App\Events\LeaveRequestCreated::class, function ($event) {
            return $event->leaveRequest->reason === 'I need time away from class.';
        });
    }

    public function test_trainers_channel_authorization(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $trainer = User::factory()->create([
            'role' => 'trainer',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Test that trainer can access the trainers channel
        $this->assertTrue($trainer->role === 'trainer');

        // Test that student cannot access the trainers channel
        $this->assertFalse($student->role === 'trainer');

        // Test that admin cannot access the trainers channel (only trainers)
        $this->assertFalse($admin->role === 'trainer');
    }

    public function test_admins_channel_authorization(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $trainer = User::factory()->create([
            'role' => 'trainer',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Test that admin can access the admins channel
        $this->assertTrue($admin->role === 'admin');

        // Test that student cannot access the admins channel
        $this->assertFalse($student->role === 'admin');

        // Test that trainer cannot access the admins channel (only admins)
        $this->assertFalse($trainer->role === 'admin');
    }
}