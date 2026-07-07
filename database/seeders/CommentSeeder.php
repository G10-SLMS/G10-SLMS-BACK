<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\LeaveRequest;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Depends on LeaveRequestSeeder having already run.
     */
    public function run(): void
    {
        $leaveRequests = LeaveRequest::with('user', 'reviewer')->get();

        if ($leaveRequests->isEmpty()) {
            $this->command?->warn('Skipping CommentSeeder: no leave requests found. Run LeaveRequestSeeder first.');
            return;
        }

        foreach ($leaveRequests as $leaveRequest) {
            // The student's own comment explaining the request
            Comment::firstOrCreate([
                'leave_request_id' => $leaveRequest->id,
                'user_id' => $leaveRequest->user_id,
                'body' => 'Submitting this request, please let me know if any documents are needed.',
            ]);

            // A reviewer comment, if the request has already been reviewed
            if ($leaveRequest->reviewed_by) {
                $body = $leaveRequest->status === 'approved'
                    ? 'Approved. Please make sure to catch up on missed coursework.'
                    : 'Rejected due to insufficient notice — please resubmit with more lead time.';

                Comment::firstOrCreate([
                    'leave_request_id' => $leaveRequest->id,
                    'user_id' => $leaveRequest->reviewed_by,
                    'body' => $body,
                ]);
            }
        }
    }
}
