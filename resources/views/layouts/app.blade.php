<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Helpdesk')</title>

    <style>
        .profile-menu {
            position: relative;
        }

        .profile-menu summary {
            padding: 10px 12px;
            color: #ddd;
            font-size: 14px;
            cursor: pointer;
            list-style: none;
        }

        .profile-menu summary::-webkit-details-marker {
            display: none;
        }

        .profile-menu summary::after {
            content: " ▼";
            font-size: 10px;
        }

        .profile-menu summary:hover,
        .profile-menu[open] summary {
            background: #3a3a3a;
            color: white;
        }

        .profile-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 10;
            width: 160px;
            padding: 6px;
            background: white;
            border: 1px solid #ddd;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
        }

        .profile-dropdown form {
            margin: 0;
        }

        .profile-action {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 0;
            background: white;
            color: #000;
            text-align: left;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .profile-action:hover {
            background: #f0f1f2;
            color: #222;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            margin: 0;
            min-height: 100vh;
            color: #222;
        }

        .navbar {
            width: 100%;
            min-height: 64px;
            padding: 0 32px;
            box-sizing: border-box;
            background: #222;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-brand {
            color: white;
            font-size: 20px;
            font-weight: bold;
            text-decoration: none;
        }

        .navbar-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-links > a,
        .navbar-logout {
            width: auto;
            margin: 0;
            padding: 10px 12px;
            border: 0;
            background: transparent;
            color: #ddd;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        .navbar-links > a:hover,
        .navbar-links > a.active,
        .navbar-logout:hover {
            background: #3a3a3a;
            color: white;
        }

        .navbar-links form {
            margin: 0;
        }

        .app-box {
            width: calc(100% - 32px);
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border: 1px solid #ddd;
            padding: 32px;
            box-sizing: border-box;
        }

        .login-box {
            max-width: 400px;
        }

        @media (max-width: 700px) {
            .navbar {
                padding: 16px;
                align-items: flex-start;
                gap: 16px;
            }

            .navbar-links {
                flex-wrap: wrap;
                justify-content: flex-end;
            }
        }
        .status-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            min-width: 9px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .status-active {
            background-color: #16803c;
        }

        .status-ended {
            background: #c62828;
        }

        .status-upcoming {
            background-color: #999;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        h2 {
            margin: 30px 0 20px;
            font-size: 20px;
        }

        .subtitle {
            margin: 0 0 28px;
            color: #666;
            font-size: 14px;
        }

        .field {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #bbb;
            font-family: Arial, sans-serif;
            font-size: 15px;
            border-radius: 3px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #555;
        }

        button {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 3px;
            background: #222;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #333;
        }

        .error {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            background: #f8f8f8;
            font-size: 14px;
        }

        .event-meta {
            margin: 20px 0;
            color: #555;
        }

        .event-meta p {
            margin: 8px 0;
        }

        details {
            cursor: pointer;
        }

        details summary {
            color: #222;
        }

        .attendee-details {
            margin-top: 12px;
            padding: 12px;
            background: #f8f8f8;
            border: 1px solid #ddd;
            min-width: 220px;
        }

        .attendee-details p {
            margin: 6px 0;
        }

        .success {
            margin-bottom: 24px;
            padding: 14px 16px;
            border: 1px solid #a8d5b5;
            background: #eaf7ed;
            color: #176b2c;
            font-size: 15px;
            font-weight: bold;
        }

        .success-page {
            text-align: center;
            padding: 20px 0;
        }

        .success-icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 18px;
            border: 2px solid #16803c;
            border-radius: 50%;
            color: #16803c;
            font-size: 32px;
            line-height: 48px;
            font-weight: bold;
        }

        .success-page h1 {
            margin-bottom: 10px;
        }

        .success-page p {
            color: #666;
            margin-bottom: 28px;
        }

        .success-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .success-links a {
            color: #222;
        }

        .wide-box {
            max-width: 1100px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin: 25px 0 35px;
        }

        .stat {
            border: 1px solid #ddd;
            padding: 20px;
            background: #fafafa;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            font-size: 13px;
            color: #666;
            font-weight: bold;
        }

        td a {
            color: #222;
        }

        @media (max-width: 700px) {
            .stats {
                grid-template-columns: 1fr;
            }
        }

        .back-link {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: #222;
            font-size: 14px;
        }

        .page-link {
            display: block;
            margin-bottom: 12px;
            padding: 11px;
            border: 1px solid #bbb;
            border-radius: 3px;
            color: #222;
            text-decoration: none;
            text-align: center;
        }

        .page-link:hover {
            background: #f4f5f7;
        }

        .dashboard-box {
            max-width: 1100px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .dashboard-action {
            padding: 11px 16px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }

        .dashboard-action:hover {
            background: #333;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 35px;
        }

        .dashboard-stat {
            padding: 22px;
            border: 1px solid #ddd;
            background: #fafafa;
            border-radius: 6px;
        }

        .dashboard-stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .dashboard-stat-value {
            font-size: 30px;
            font-weight: bold;
        }

        .dashboard-list {
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }

        .dashboard-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid #ddd;
        }

        .dashboard-list-item:last-child {
            border-bottom: none;
        }

        .dashboard-list-item strong,
        .dashboard-list-item span {
            display: block;
        }

        .dashboard-list-item span {
            margin-top: 5px;
            color: #666;
            font-size: 14px;
        }

        .dashboard-list-item a {
            color: #222;
            font-weight: bold;
        }

        .guest-home {
            text-align: center;
            padding: 70px 20px;
        }

        .guest-home h1 {
            margin-bottom: 10px;
        }

        .guest-home p {
            color: #666;
            max-width: 500px;
            margin: 0 auto 30px;
        }

        .guest-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .guest-button {
            display: inline-block;
            padding: 11px 18px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .guest-button:hover {
            background: #333;
        }

        .guest-button.secondary {
            background: white;
            color: #222;
            border: 1px solid #ccc;
        }

        .guest-button.secondary:hover {
            background: #f5f5f5;
        }

        @media (max-width: 600px) {
            .guest-actions {
                flex-direction: column;
            }
        }

        .session-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .session-primary-action {
            padding: 11px 16px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
        }

        .session-primary-action:hover {
            background: #333;
        }

        .session-search {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .session-search input {
            flex: 1;
        }

        .session-search select {
            width: 190px;
        }

        .session-search button {
            width: auto;
            padding: 11px 20px;
        }

        .session-actions-cell {
            text-align: right;
            width: 100px;
        }

        .session-actions {
            position: relative;
            display: inline-block;
        }

        .session-actions summary {
            padding: 8px 10px;
            border: 1px solid #bbb;
            background: white;
            color: #222;
            border-radius: 3px;
            font-size: 14px;
            cursor: pointer;
            list-style: none;
        }

        .session-actions summary::-webkit-details-marker {
            display: none;
        }

        .session-actions summary::after {
            content: " ▼";
            font-size: 9px;
        }

        .session-actions-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 5px);
            z-index: 20;
            width: 170px;
            padding: 5px;
            background: white;
            border: 1px solid #ddd;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
        }

        .session-actions-menu a,
        .session-actions-menu button {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 9px 10px;
            border: 0;
            background: white;
            color: #222;
            text-align: left;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .session-actions-menu a:hover,
        .session-actions-menu button:hover {
            background: #f0f1f2;
        }

        .session-actions-menu form {
            margin: 0;
        }

        .bulk-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            gap: 10px;
        }

        .bulk-actions button {
            width: auto;
            padding: 9px 14px;
        }

        .session-checkbox {
            width: auto;
            margin-right: 8px;
        }

        @media (max-width: 700px) {
            .session-page-header {
                flex-direction: column;
            }

            .session-search {
                flex-direction: column;
            }

            .session-search button {
                width: 100%;
            }
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 25px;
            margin-bottom: 25px;
        }

        .event-header h1 {
            margin: 0 0 6px;
        }

        .event-subtitle {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .event-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .event-back {
            color: #333;
            text-decoration: none;
            font-size: 14px;
            margin-right: 6px;
        }

        .event-back:hover {
            text-decoration: underline;
        }

        .event-action {
            display: inline-block;
            padding: 9px 13px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
        }

        .event-action:hover {
            background: #333;
        }

        @media (max-width: 700px) {
            .event-header {
                flex-direction: column;
            }

            .event-actions {
                width: 100%;
            }
        }

        .event-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            margin: 25px 0 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fafafa;
        }

        .event-meta p {
            margin: 0 0 8px;
        }

        .event-meta p:last-child {
            margin-bottom: 0;
        }

        .event-qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .event-qr img {
            display: block;
        }

        .event-qr span {
            color: #666;
            font-size: 12px;
        }

        @media (max-width: 700px) {
            .event-info {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
    <a class="navbar-brand" href="{{ route('home') }}">
        Helpdesk
    </a>

    <div class="navbar-links">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}"
           href="{{ route('home') }}">
            Home
        </a>

        <a class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}"
           href="{{ route('attendance.pin') }}">
            Attendance
        </a>

        @auth
            @if (auth()->user()->role === 'organizer')
                <a class="{{ request()->routeIs('attendance-events.*') ? 'active' : '' }}"
                   href="{{ route('attendance-events.index') }}">
                    Attendance Sessions
                </a>
            @endif

            <details class="profile-menu">
                <summary>
                    {{ ucfirst(auth()->user()->role ?? 'User') }}
                </summary>

                <div class="profile-dropdown">
                    <a class="profile-action" href="{{ route('profile') }}">
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button class="profile-action" type="submit">
                            Logout
                        </button>
                    </form>
                </div>
            </details>
        @else
            <a class="{{ request()->routeIs('login') ? 'active' : '' }}"
               href="{{ route('login') }}">
                Login
            </a>
        @endauth
    </div>
</nav>

<div class="app-box @yield('container_class')">
    @yield('content')
</div>

</body>
</html>
