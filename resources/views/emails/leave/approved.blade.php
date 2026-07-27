<x-mail::message>
# Your Leave Request Has Been Approved

Hello, {{ $notifiable->name }}!

Good news — your leave request has been approved.

<x-mail::table>
| | |
|:---|:---|
| **Leave Type** | {{ $leave->leaveType->name ?? 'N/A' }} |
| **Dates** | {{ $dateRange }} |
| **Duration** | {{ $leave->duration_label }} |
| **Status** | Approved |
@if($leave->reviewer)
| **Reviewed By** | {{ $leave->reviewer->name }} |
@endif
@if($leave->review_note)
| **Note** | {{ $leave->review_note }} |
@endif
</x-mail::table>

<x-mail::button :url="$url">
View My Leave Requests
</x-mail::button>

Enjoy your time off!

Regards,<br>
The SLMS Team
</x-mail::message>
