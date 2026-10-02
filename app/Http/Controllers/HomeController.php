<?php

namespace App\Http\Controllers;

use App\Models\EventSession;
use App\Models\User;

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

        $sessions = EventSession::query()->whereHas('event', function ($query) {
            if (auth()->user()->role === 'organizer') {
                $query->where('organizer_id', auth()->id());
            }
        });

        $activeSessions = (clone $sessions)->where('attendance_opens_at', '<=', now())
            ->where('attendance_closes_at', '>=', now())
            ->count();

        $todaySessions = (clone $sessions)->whereDate('starts_at', today())
            ->count();

        $todayAttendance = (clone $sessions)->whereDate('starts_at', today())
            ->withCount('attendances')
            ->get()
            ->sum('attendances_count');

        $totalUsers = auth()->user()->role === 'admin' ? User::count() : 0;

        $recentSessions = (clone $sessions)->with('event')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->take(5)
            ->get();

        return view('home', compact(
            'activeSessions',
            'todaySessions',
            'todayAttendance',
            'totalUsers',
            'recentSessions'
        ));
    }
}
