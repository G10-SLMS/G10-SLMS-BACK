<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        $request->validate([
            'range' => ['nullable', 'string', 'in:30d,90d,ytd,custom'],
            'start_date' => ['nullable', 'date', 'required_if:range,custom'],
            'end_date' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:start_date'],
        ]);

        $range = $request->query('range', '30d');
        [$startDate, $endDate] = $this->resolveRange($request, $range);

        $baseQuery = LeaveRequest::query()
            ->whereBetween('leave_requests.created_at', [$startDate, $endDate]);

        $this->scopeToViewer($baseQuery, $request);

        return response()->json([
            'success' => true,
            'message' => 'Report data retrieved successfully.',
            'data' => [
                'range' => $range,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'summary' => $this->buildSummary(clone $baseQuery),
                'by_leave_type' => $this->buildByLeaveType(clone $baseQuery),
                'monthly' => $this->buildMonthly(clone $baseQuery, $startDate, $endDate),
                'top_students' => $this->buildTopStudents(clone $baseQuery),
            ],
        ]);
    }

    private function scopeToViewer(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($user && $user->role === 'trainer') {
            $query->whereHas('user', function (Builder $q) use ($user) {
                $q->where('trainer_id', $user->id);
            });
        }
    }

    private function resolveRange(Request $request, ?string $range): array
    {
        if ($range === 'custom') {
            $start = Carbon::parse($request->query('start_date'))->startOfDay();
            $end = Carbon::parse($request->query('end_date'))->endOfDay();

            return [$start, $end];
        }

        $end = Carbon::now()->endOfDay();

        $start = match ($range) {
            '90d' => Carbon::now()->subDays(90)->startOfDay(),
            'ytd' => Carbon::now()->startOfYear(),
            default => Carbon::now()->subDays(30)->startOfDay(),
        };

        return [$start, $end];
    }

    private function buildSummary(Builder $query): array
    {
        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $approved = (int) ($counts['approved'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $rejected = (int) ($counts['rejected'] ?? 0);
        $cancelled = (int) ($counts['cancelled'] ?? 0);

        return [
            'total' => $approved + $pending + $rejected + $cancelled,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
        ];
    }

    private function buildByLeaveType(Builder $query): array
    {
        return $query
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->selectRaw('leave_types.id as leave_type_id, leave_types.name as name, COUNT(leave_requests.id) as count')
            ->groupBy('leave_types.id', 'leave_types.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'leave_type_id' => (int) $row->leave_type_id,
                'name' => $row->name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function buildTopStudents(Builder $query): array
    {
        return $query
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->selectRaw('users.id as user_id, users.name as name, users.email as email, COUNT(leave_requests.id) as total_requests')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_requests')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'total_requests' => (int) $row->total_requests,
            ])
            ->values()
            ->all();
    }

    private function buildMonthly(Builder $query, Carbon $start, Carbon $end): array
    {
        $rows = $query
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, status, COUNT(*) as total")
            ->groupBy('period', 'status')
            ->orderBy('period')
            ->get();

        $months = $this->buildEmptyMonthBuckets($start, $end);

        foreach ($rows as $row) {
            if (!isset($months[$row->period])) {
                continue;
            }

            $months[$row->period]['submitted'] += (int) $row->total;

            if ($row->status === 'approved') {
                $months[$row->period]['approved'] += (int) $row->total;
            } elseif ($row->status === 'rejected') {
                $months[$row->period]['rejected'] += (int) $row->total;
            }
        }

        return (new Collection($months))
            ->values()
            ->map(function (array $month) {
                $month['approval_rate'] = $month['submitted'] > 0
                    ? round(($month['approved'] / $month['submitted']) * 100)
                    : 0;

                return $month;
            })
            ->all();
    }

    private function buildEmptyMonthBuckets(Carbon $start, Carbon $end): array
    {
        $months = [];
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $months[$cursor->format('Y-m')] = [
                'month' => $cursor->format('F Y'),
                'submitted' => 0,
                'approved' => 0,
                'rejected' => 0,
            ];

            $cursor->addMonth();
        }

        return $months;
    }
}
