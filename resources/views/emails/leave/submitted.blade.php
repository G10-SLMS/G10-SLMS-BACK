<x-mail::message>
# New Leave Request Awaiting Review

Hello, {{ $notifiable->name }}!

**{{ $student->name }}** has submitted a new leave request that needs your review.

<x-mail::table>
| | |
|:---|:---|
| **Student** | {{ $student->name }} |
| **Leave Type** | {{ $leave->leaveType->name ?? 'N/A' }} |
| **Dates** | {{ $dateRange }} |
| **Duration** | {{ $leave->duration_label }} |
| **Reason** | {{ $leave->reason ?: 'No reason provided' }} |
| **Status** | Pending Review |
</x-mail::table>

<x-mail::button :url="$url">
Review Request
</x-mail::button>

Please review this request at your earliest convenience.

Regards,<br>
The SLMS Team
</x-mail::message>
