<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EventSession;
use App\Models\Registration;

class HomeController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return view('guest-home');
        }

        if (auth()->user()->role === 'user') {
            return view('guest-home');
        }

        $now = now();
        $recentStart = $now->copy()->subDays(7);
        $scopeEvents = function ($query) {
            if (auth()->user()->role === 'organizer') {
                $query->where('organizer_id', auth()->id());
            }
        };

        $sessions = EventSession::query()->whereHas('event', $scopeEvents);
        $publishedEvents = function ($query) use ($scopeEvents) {
            $scopeEvents($query);
            $query->where('status', 'published');
        };
        $operationalSessions = EventSession::query()->whereHas('event', $publishedEvents);

        $sessionsInProgress = (clone $operationalSessions)->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with('event.location')
            ->orderBy('starts_at')
            ->get();

        $sessionsStartingSoon = (clone $operationalSessions)->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->copy()->addDay())
            ->with('event.location')
            ->orderBy('starts_at')
            ->get();

        $todaySessions = (clone $operationalSessions)->whereDate('starts_at', today())
            ->count();

        $todayAttendance = (clone $operationalSessions)->whereDate('starts_at', today())
            ->withCount('attendances')
            ->get()
            ->sum('attendances_count');

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

        $recentSessions = (clone $operationalSessions)->with('event.location')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->take(5)
            ->get();

        $recentSessionStats = (clone $sessions)
            ->where('ends_at', '>=', $recentStart)
            ->where('ends_at', '<=', $now)
            ->get();
        $recentSessionsHeld = $recentSessionStats->count();
        $recentCheckIns = Attendance::query()
            ->whereBetween('created_at', [$recentStart, $now])
            ->whereHas('session', fn ($query) => $query->whereHas('event', $scopeEvents))
            ->count();
        $recentPreRegistrations = Registration::query()
            ->whereBetween('registered_at', [$recentStart, $now])
            ->whereHas('event', $scopeEvents)
            ->count();

        return view('home', compact(
            'sessionsInProgress',
            'sessionsStartingSoon',
            'todaySessions',
            'todayAttendance',
            'pendingPreRegistrations',
            'pendingPreRegistrationEvents',
            'recentSessions',
            'recentSessionsHeld',
            'recentCheckIns',
            'recentPreRegistrations'
        ));
    }
}
