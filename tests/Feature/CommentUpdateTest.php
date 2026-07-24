<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that an authenticated user can update their own comment.
     */
    public function test_authenticated_user_can_update_own_comment(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
            'body' => 'Original comment body',
        ]);

        $newBody = 'Updated comment body';

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => $newBody,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment updated successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'body',
                    'edited_at',
                    'user',
                    'leave_request_id',
                ],
            ]);

        // Verify the comment was updated in the database
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => $newBody,
        ]);

        // Verify edited_at is set
        $this->assertNotNull($comment->fresh()->edited_at);
    }

    /**
     * Test that an admin can update any comment.
     */
    public function test_admin_can_update_any_comment(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
            'body' => 'Original comment body',
        ]);

        $newBody = 'Admin updated comment';

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => $newBody,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment updated successfully',
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => $newBody,
        ]);
    }

    /**
     * Test that a user cannot update another user's comment.
     */
    public function test_user_cannot_update_other_users_comment(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $otherUser->id,
            'leave_request_id' => $leaveRequest->id,
            'body' => 'Original comment body',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => 'Trying to update',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to update this comment. You can only update your own comments.',
                'data' => null,
            ]);

        // Verify the comment was NOT updated
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Original comment body',
        ]);
    }

    /**
     * Test that unauthenticated user cannot update a comment.
     */
    public function test_unauthenticated_user_cannot_update_comment(): void
    {
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'leave_request_id' => $leaveRequest->id,
        ]);

        $response = $this->putJson("/api/comments/{$comment->id}", [
            'body' => 'Trying to update',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that body field is required.
     */
    public function test_body_field_is_required(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                // No body provided
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The body field is required.',
            ])
            ->assertJsonValidationErrors(['body']);
    }

    /**
     * Test that body cannot exceed 1000 characters.
     */
    public function test_body_cannot_exceed_1000_characters(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
        ]);

        $longBody = str_repeat('a', 1001);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => $longBody,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /**
     * Test that updating a comment sets the edited_at timestamp.
     */
    public function test_updating_comment_sets_edited_at_timestamp(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
            'body' => 'Original comment',
            'edited_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => 'Updated comment',
            ]);

        $this->assertNotNull($comment->fresh()->edited_at);
    }

    /**
     * Test that an educator (non-admin) cannot update another user's comment.
     */
    public function test_educator_cannot_update_other_users_comment(): void
    {
        /** @var User $educator */
        $educator = User::factory()->educator()->create();
        /** @var User $student */
        $student = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $student->id,
            'leave_request_id' => $leaveRequest->id,
            'body' => 'Student comment',
        ]);

        $response = $this->actingAs($educator, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => 'Educator trying to update',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to update this comment. You can only update your own comments.',
                'data' => null,
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'body' => 'Student comment',
        ]);
    }

    /**
     * Test that the response includes the updated comment data.
     */
    public function test_update_response_includes_comment_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
            'body' => 'Original comment',
        ]);

        $newBody = 'Updated comment body';

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => $newBody,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment updated successfully',
            ])
            ->assertJsonPath('data.body', $newBody)
            ->assertJsonPath('data.id', $comment->id)
            ->assertJsonPath('data.leave_request_id', $leaveRequest->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'body',
                    'leave_request_id',
                    'edited_at',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    /**
     * Test that updating a deleted comment returns a friendly error message.
     */
    public function test_updating_deleted_comment_returns_friendly_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
        ]);

        // Soft delete the comment
        $comment->delete();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => 'Trying to update deleted comment',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Comment not found. It may have been deleted.',
                'data' => null,
            ]);
    }

    /**
     * Test that showing a deleted comment returns a friendly error message.
     */
    public function test_showing_deleted_comment_returns_friendly_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create();
        $comment = Comment::factory()->create([
            'user_id' => $user->id,
            'leave_request_id' => $leaveRequest->id,
        ]);

        // Soft delete the comment
        $comment->delete();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/comments/{$comment->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Comment not found. It may have been deleted.',
                'data' => null,
            ]);
    }
}
