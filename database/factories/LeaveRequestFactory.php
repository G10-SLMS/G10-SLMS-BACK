<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'reason' => fake()->sentence(),
            'duration_type' => 'full_day',
            'duration_hours' => null,
            'status' => 'pending',
        ];
    }

    public function hourly(?float $hours = null): static
    {
        return $this->state(function () use ($hours) {
            $date = fake()->date();

            return [
                'start_date' => $date,
                'end_date' => $date,
                'duration_type' => 'hourly',
                'duration_hours' => $hours ?? fake()->randomFloat(1, LeaveRequest::MIN_HOURLY_DURATION, LeaveRequest::MAX_HOURLY_DURATION),
            ];
        });
    }
}