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
        $range = $request->query('range', '30d');
        [$startDate, $endDate] = $this->resolveRange($range);

        $baseQuery = LeaveRequest::query()
            ->whereBetween('leave_requests.created_at', [$startDate, $endDate]);

        $this->scopeToViewer($baseQuery, $request);

        return response()->json([
            'success' => true,
            'message' => 'Report data retrieved successfully.',
            'data' => [
                'range' => $range,
                'summary' => $this->buildSummary(clone $baseQuery),
                'by_leave_type' => $this->buildByLeaveType(clone $baseQuery),
                'monthly' => $this->buildMonthly(clone $baseQuery, $startDate, $endDate),
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

    private function resolveRange(?string $range): array
    {
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
