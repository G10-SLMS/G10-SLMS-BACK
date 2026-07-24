<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CalendarController extends Controller
{
    /**
     * GET /api/calendar-events
     * Role: Student, Trainer, Admin
     *
     * Query params:
     * - start_date, end_date: required date range (YYYY-MM-DD)
     * - search: matches student name, student_id, generation, or class
     *   (only meaningful for trainer/admin, since students only see their own events)
     *
     * Column detection: only selects/searches columns that actually exist
     * on the `users` table, so this won't break if the schema differs
     * from environment to environment.
     */
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $userColumns = $this->userColumns();

        $query = LeaveRequest::query()
            ->with([
                'leaveType:id,name',
                'user:' . implode(',', $userColumns),
            ])
            ->whereDate('start_date', '<=', $request->end_date)
            ->whereDate('end_date', '>=', $request->start_date);

        $this->applyRoleScope($query, $user);
        $this->applySearch($query, $request->input('search'), $userColumns);

        $leaveRequests = $query->get();

        $events = $leaveRequests->map(
            fn (LeaveRequest $leave) => $this->toCalendarEvent($leave, $user->role)
        );

        return response()->json([
            'success' => true,
            'message' => 'Calendar events retrieved successfully.',
            'data' => $events,
        ]);
    }

    /**
     * Which `users` columns we want, filtered down to only the ones
     * that actually exist on this database.
     */
    protected function userColumns(): array
    {
        $desired = ['id', 'name', 'student_id', 'generation', 'class_name', 'trainer_id'];

        $existing = array_values(array_filter(
            $desired,
            fn ($col) => Schema::hasColumn('users', $col)
        ));

        // 'id' must always be present for the relationship to work
        if (!in_array('id', $existing)) {
            array_unshift($existing, 'id');
        }

        return $existing;
    }

    /**
     * Restrict the query based on the authenticated user's role.
     */
    protected function applyRoleScope($query, $user): void
    {
        match ($user->role) {
            'student' => $query->where('user_id', $user->id),

            'trainer' => Schema::hasColumn('users', 'trainer_id')
                ? $query->whereHas('user', fn ($q) => $q->where('trainer_id', $user->id))
                : $query->whereRaw('1 = 0'),

            'admin' => null, // no restriction

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Apply the search filter across whichever identity columns exist.
     */
    protected function applySearch($query, ?string $search, array $userColumns): void
    {
        if (!$search) {
            return;
        }

        $searchableFields = array_intersect(['name', 'student_id', 'generation', 'class_name'], $userColumns);

        if (empty($searchableFields)) {
            return;
        }

        $query->whereHas('user', function ($q) use ($search, $searchableFields) {
            $q->where(function ($sub) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $sub->orWhere($field, 'like', "%{$search}%");
                }
            });
        });
    }

    /**
     * Transform a LeaveRequest into a FullCalendar-compatible event object.
     */
    protected function toCalendarEvent(LeaveRequest $leave, string $role): array
    {
        $base = [
            'id' => $leave->id,
            'title' => $this->eventTitle($leave, $role),
            'start' => $leave->start_date->toDateString(),
            // FullCalendar treats `end` as exclusive, so add 1 day for all-day events
            'end' => $leave->end_date->copy()->addDay()->toDateString(),
            'allDay' => true,
            'extendedProps' => [
                'leaveRequestId' => $leave->id,
                'leaveType' => $leave->leaveType->name ?? null,
                'startDate' => $leave->start_date->toDateString(),
                'endDate' => $leave->end_date->toDateString(),
                'status' => $leave->status,
            ],
        ];

        if (in_array($role, ['trainer', 'admin'])) {
            $base['extendedProps']['studentName'] = $leave->user->name ?? null;
            $base['extendedProps']['studentId'] = $leave->user->student_id ?? null;
        }

        return $base;
    }

    protected function eventTitle(LeaveRequest $leave, string $role): string
    {
        $typeName = $leave->leaveType->name ?? 'Leave';

        if (in_array($role, ['trainer', 'admin'])) {
            $studentName = $leave->user->name ?? 'Unknown';
            return "{$studentName} — {$typeName}";
        }

        return $typeName;
    }
}