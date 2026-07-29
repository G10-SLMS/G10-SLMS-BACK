<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendLeaveEmailNotification extends Command
{
    protected $signature = 'leave:send-email {type} {leaveRequestId}';

    protected $description = 'Sends a single leave request email notification. Intended to run as a detached background process, not called directly during a request.';

    public function handle(NotificationService $notifications): int
    {
        $leaveRequest = LeaveRequest::with(['user', 'leaveType'])->find($this->argument('leaveRequestId'));

        if (!$leaveRequest) {
            $this->error('Leave request #' . $this->argument('leaveRequestId') . ' not found.');

            return self::FAILURE;
        }

        match ($this->argument('type')) {
            'submitted' => $notifications->sendLeaveSubmittedEmailNow($leaveRequest),
            'under_review' => $notifications->sendLeaveUnderReviewEmailNow($leaveRequest),
            'approved' => $notifications->sendLeaveApprovedEmailNow($leaveRequest),
            'rejected' => $notifications->sendLeaveRejectedEmailNow($leaveRequest),
            default => $this->error('Unknown notification type: ' . $this->argument('type')),
        };

        return self::SUCCESS;
    }
}
