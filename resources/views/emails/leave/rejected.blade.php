<x-mail::message>
# Your Leave Request Has Been Rejected

Hello, {{ $notifiable->name }}!

Your leave request has been reviewed and was not approved.

<x-mail::table>
| | |
|:---|:---|
| **Leave Type** | {{ $leave->leaveType->name ?? 'N/A' }} |
| **Dates** | {{ $dateRange }} |
| **Duration** | {{ $leave->duration_label }} |
| **Status** | Rejected |
@if($leave->reviewer)
| **Reviewed By** | {{ $leave->reviewer->name }} |
@endif
</x-mail::table>

<x-mail::panel>
**Reason:** {{ $leave->review_note ?: 'No reason was provided.' }}
</x-mail::panel>

<x-mail::button :url="$url">
View My Leave Requests
</x-mail::button>

If you have questions about this decision, please reach out to your educator or contact administration.

Regards,<br>
The SLMS Team
</x-mail::message>
