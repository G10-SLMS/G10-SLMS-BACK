<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LeaveRequestRejectedNotification extends Notification
{
    public function __construct(protected LeaveRequest $leaveRequest)
    {
        //
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $leave = $this->leaveRequest;
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject('Your Leave Request Has Been Rejected')
            ->markdown('emails.leave.rejected', [
                'notifiable' => $notifiable,
                'leave' => $leave,
                'dateRange' => $this->dateRangeLabel(),
                'url' => $frontendUrl.'/leave-requests?request='.$leave->id,
            ]);
    }

    protected function dateRangeLabel(): string
    {
        $leave = $this->leaveRequest;
        $start = $leave->start_date?->format('M j, Y');
        $end = $leave->end_date?->format('M j, Y');

        return $start === $end ? $start : "{$start} – {$end}";
    }
}
