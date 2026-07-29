<x-mail::message>
# Your Leave Request Is Under Review

Hello, {{ $notifiable->name }}!

Your leave request is now being reviewed by an admin or educator. No action is needed from you at this time.

<x-mail::table>
| | |
|:---|:---|
| **Leave Type** | {{ $leave->leaveType->name ?? 'N/A' }} |
| **Dates** | {{ $dateRange }} |
| **Duration** | {{ $leave->duration_label }} |
| **Status** | Under Review |
@if($leave->reviewer)
| **Reviewed By** | {{ $leave->reviewer->name }} |
@endif
</x-mail::table>

<x-mail::button :url="$url">
View My Leave Requests
</x-mail::button>

We'll notify you again as soon as a final decision has been made.

Regards,<br>
The SLMS Team
</x-mail::message>
