<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\User;
use App\Models\Ticket;

class HomeController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return view('guest-home');
        }

        $activeSessions = AttendanceEvent::where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->count();

        $todaySessions = AttendanceEvent::whereDate('starts_at', today())
            ->count();

        $todayAttendance = AttendanceEvent::whereDate('starts_at', today())
            ->withCount('attendances')
            ->get()
            ->sum('attendances_count');

        $totalUsers = User::count();

        $openTickets = Ticket::whereIn('status', [
            'Pending',
            'In Progress',
        ])->count();

        $recentSessions = AttendanceEvent::latest('starts_at')
            ->take(2)
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
