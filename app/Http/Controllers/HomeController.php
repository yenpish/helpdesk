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

        $activeSessions = EventSession::where('attendance_opens_at', '<=', now())
            ->where('attendance_closes_at', '>=', now())
            ->count();

        $todaySessions = EventSession::whereDate('starts_at', today())
            ->count();

        $todayAttendance = EventSession::whereDate('starts_at', today())
            ->withCount('attendances')
            ->get()
            ->sum('attendances_count');

        $totalUsers = User::count();

        $openTickets = 0;

        $recentSessions = EventSession::with('event')
            ->latest('starts_at')
            ->take(5)
            ->get();

        return view('home', compact(
            'activeSessions',
            'todaySessions',
            'todayAttendance',
            'totalUsers',
            'openTickets',
            'recentSessions'
        ));
    }
}
