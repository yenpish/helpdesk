<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EventSession;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        if (!auth()->check() || auth()->user()->role === 'user') {
            return view('guest-home');
        }

        $now = now();
        $recentStart = $now->copy()->subDays(7);
        $user = auth()->user();
        $scopeEvents = static function ($query) use ($user): void {
            if ($user->role === 'organizer') {
                $query->where('organizer_id', $user->id);
            }
        };

        $publishedEvents = static function ($query) use ($scopeEvents): void {
            $scopeEvents($query);
            $query->where('status', 'published');
        };
        $operationalSessions = EventSession::query()->whereHas('event', $publishedEvents);

        $sessionsInProgress = (clone $operationalSessions)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with('event.location')
            ->orderBy('starts_at')
            ->get();

        $sessionsStartingSoon = (clone $operationalSessions)
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->copy()->addDay())
            ->with('event.location')
            ->orderBy('starts_at')
            ->get();

        $todaySessions = (clone $operationalSessions)
            ->whereDate('starts_at', $now->toDateString())
            ->count();

        $attendanceTimestamp = 'COALESCE(attendances.clock_in_at, attendances.client_submitted_at, attendances.created_at)';
        $attendanceScope = static fn () => Attendance::query()
            ->whereHas('session.event', $scopeEvents);

        $todayAttendance = $attendanceScope()
            ->whereRaw("{$attendanceTimestamp} BETWEEN ? AND ?", [
                $now->copy()->startOfDay(),
                $now,
            ])
            ->count();

        $pendingPreRegistrationsQuery = Registration::query()
            ->where('status', 'pending')
            ->whereHas('event', $publishedEvents);
        $pendingPreRegistrations = (clone $pendingPreRegistrationsQuery)->count();
        $pendingPreRegistrationEvents = (clone $pendingPreRegistrationsQuery)
            ->select('event_id')
            ->selectRaw('COUNT(*) as pending_count')
            ->groupBy('event_id')
            ->with('event:id,name')
            ->orderByDesc('pending_count')
            ->get();

        $recentSessions = (clone $operationalSessions)
            ->with('event.location')
            ->where('ends_at', '>=', $now)
            ->orderBy('starts_at')
            ->take(5)
            ->get();

        $recentSessionsEnded = EventSession::query()
            ->whereBetween('ends_at', [$recentStart, $now])
            ->whereHas('event', static function ($query) use ($scopeEvents): void {
                $scopeEvents($query);
                $query->whereIn('status', ['published', 'completed']);
            })
            ->count();

        $recentCheckIns = $attendanceScope()
            ->whereRaw("{$attendanceTimestamp} BETWEEN ? AND ?", [$recentStart, $now])
            ->count();

        $recentPreRegistrations = Registration::query()
            ->whereBetween('registered_at', [$recentStart, $now])
            ->whereHas('event', $scopeEvents)
            ->count();

        $range = $request->query('range', '7d');
        if (!in_array($range, ['7d', '30d', '90d', 'custom'], true)) {
            $range = '7d';
        }

        $timezone = config('app.timezone');
        $rangeError = null;
        if ($range === 'custom') {
            $validator = Validator::make($request->query(), [
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);
            if ($validator->fails()) {
                $rangeError = 'Enter a valid start and end date, with the end date on or after the start date.';
                $range = '7d';
            } else {
                $dates = $validator->validated();
                $from = Carbon::createFromFormat('!Y-m-d', $dates['from'], $timezone);
                $to = Carbon::createFromFormat('!Y-m-d', $dates['to'], $timezone);

                if ($from->diffInDays($to) > 365) {
                    $rangeError = 'Choose a date range of one year or less.';
                    $range = '7d';
                }
            }
        }

        if ($range !== 'custom') {
            $days = ['7d' => 7, '30d' => 30, '90d' => 90][$range];
            $to = $now->copy()->startOfDay();
            $from = $to->copy()->subDays($days - 1);
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $trendCounts = $attendanceScope()
            ->whereRaw("{$attendanceTimestamp} BETWEEN ? AND ?", [$from, $to])
            ->selectRaw("DATE({$attendanceTimestamp}) as attendance_date, COUNT(*) as attendance_count")
            ->groupByRaw("DATE({$attendanceTimestamp})")
            ->pluck('attendance_count', 'attendance_date');

        $attendanceTrend = [];
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();
            $attendanceTrend[] = [
                'date' => $key,
                'label' => $date->format($range === '90d' || $from->diffInDays($to) > 30 ? 'd M Y' : 'd M'),
                'count' => (int) ($trendCounts[$key] ?? 0),
            ];
        }
        $attendanceTrendTotal = array_sum(array_column($attendanceTrend, 'count'));

        $plot = ['left' => 48, 'right' => 744, 'top' => 14, 'bottom' => 158];
        $maxTrendCount = max(1, ...array_column($attendanceTrend, 'count'));
        $lastIndex = max(1, count($attendanceTrend) - 1);
        $attendanceTrendPoints = [];
        foreach ($attendanceTrend as $index => $point) {
            $attendanceTrendPoints[] = [
                'x' => round($plot['left'] + ($index / $lastIndex) * ($plot['right'] - $plot['left']), 1),
                'y' => round($plot['bottom'] - ($point['count'] / $maxTrendCount) * ($plot['bottom'] - $plot['top']), 1),
                'count' => $point['count'],
                'date' => $point['date'],
            ];
        }

        $labelIndexes = array_unique(array_map(
            static fn (float $fraction): int => (int) round((count($attendanceTrend) - 1) * $fraction),
            [0, 0.25, 0.5, 0.75, 1]
        ));
        $attendanceTrendLabels = array_map(
            static fn (int $index): array => [
                'x' => $attendanceTrendPoints[$index]['x'],
                'label' => $attendanceTrend[$index]['label'],
            ],
            $labelIndexes
        );
        $attendanceTrendYTicks = array_values(array_unique([0, (int) ceil($maxTrendCount / 2), $maxTrendCount]));

        return view('home', compact(
            'sessionsInProgress',
            'sessionsStartingSoon',
            'todaySessions',
            'todayAttendance',
            'pendingPreRegistrations',
            'pendingPreRegistrationEvents',
            'recentSessions',
            'recentSessionsEnded',
            'recentCheckIns',
            'recentPreRegistrations',
            'range',
            'rangeError',
            'from',
            'to',
            'attendanceTrend',
            'attendanceTrendTotal',
            'attendanceTrendPoints',
            'attendanceTrendLabels',
            'attendanceTrendYTicks',
            'maxTrendCount',
            'plot'
        ));
    }
}
