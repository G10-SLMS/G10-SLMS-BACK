<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
            'leave_request_id' => LeaveRequest::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'edited_at' => null,
        ];
    }
}