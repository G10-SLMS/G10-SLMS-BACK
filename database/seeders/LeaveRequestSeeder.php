<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveRequestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Depends on UserSeeder and LeaveTypeSeeder having already run.
     */
    public function run(): void
    {
        $students = User::where('role', 'student')->get();
        $leaveTypes = LeaveType::all();

        if ($students->isEmpty() || $leaveTypes->isEmpty()) {
            $this->command?->warn(
                'Skipping LeaveRequestSeeder: no students or leave types found. Run UserSeeder and LeaveTypeSeeder first.'
            );
            return;
        }

        $statuses = ['pending', 'approved', 'rejected'];

        foreach ($students as $index => $student) {
            $reviewer = $student->trainer_id
                ? User::find($student->trainer_id)
                : User::where('role', 'admin')->first();

            $status = $statuses[$index % count($statuses)];
            $start = Carbon::now()->addDays(($index + 1) * 3);

            LeaveRequest::updateOrCreate(
                [
                    'user_id' => $student->id,
                    'leave_type_id' => $leaveTypes->random()->id,
                    'start_date' => $start->toDateString(),
                ],
                [
                    'end_date' => $start->copy()->addDays(1)->toDateString(),
                    'reason' => "Sample leave request #{$index} for {$student->name}.",
                    'status' => $status,
                    'reviewed_by' => $status === 'pending' ? null : $reviewer?->id,
                    'review_note' => $status === 'rejected' ? 'Insufficient notice given.' : null,
                    'reviewed_at' => $status === 'pending' ? null : now(),
                ]
            );
        }
    }
}