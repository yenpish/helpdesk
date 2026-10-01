<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Attendance Management')</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f4f6;
            color: #222;
            font-size: 14px;
        }

        a {
            color: inherit;
        }

        button,
        input,
        textarea,
        select {
            font-family: inherit;
        }

        /* ---------------------------------------------------------
           Application shell
        --------------------------------------------------------- */

        .app-shell {
            min-height: 100vh;
            display: flex;
        }

        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 238px;
            background: #222;
            color: #ddd;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .app-brand {
            display: block;
            padding: 22px 22px 21px;
            border-bottom: 1px solid #383838;
            color: #fff;
            text-decoration: none;
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.1px;
        }

        .app-brand:hover {
            color: #fff;
        }

        .sidebar-content {
            flex: 1;
            padding: 18px 12px;
            overflow-y: auto;
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .sidebar-label {
            padding: 0 10px;
            margin-bottom: 7px;
            color: #888;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .sidebar-link {
            display: block;
            padding: 10px 10px;
            margin-bottom: 2px;
            border-radius: 3px;
            color: #ccc;
            text-decoration: none;
            line-height: 1.3;
        }

        .sidebar-link:hover {
            background: #303030;
            color: #fff;
        }

        .sidebar-link.active {
            background: #3a3a3a;
            color: #fff;
        }

        .sidebar-footer {
            border-top: 1px solid #383838;
            padding: 12px;
        }

        .profile-menu {
            position: relative;
        }

        .profile-menu summary {
            display: block;
            padding: 10px;
            color: #ccc;
            cursor: pointer;
            list-style: none;
            border-radius: 3px;
        }

        .profile-menu summary::-webkit-details-marker {
            display: none;
        }

        .profile-menu summary:hover,
        .profile-menu[open] summary {
            background: #303030;
            color: #fff;
        }

        .profile-summary-name {
            display: block;
            margin-bottom: 3px;
            color: #fff;
            font-size: 13px;
        }

        .profile-summary-role {
            display: block;
            color: #999;
            font-size: 12px;
        }

        .profile-dropdown {
            position: absolute;
            left: calc(100% + 8px);
            bottom: 0;
            width: 240px;
            padding: 6px;
            background: #fff;
            border: 1px solid #d5d5d5;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.16);
            z-index: 200;
        }

        .profile-details {
            padding: 10px 12px 12px;
            border-bottom: 1px solid #ddd;
        }

        .profile-name,
        .profile-email,
        .profile-role {
            display: block;
        }

        .profile-name {
            margin-bottom: 4px;
            color: #222;
            font-size: 14px;
        }

        .profile-email,
        .profile-role {
            color: #666;
            font-size: 12px;
            line-height: 1.5;
        }

        .profile-dropdown form {
            margin: 0;
        }

        .profile-action {
            display: block;
            width: 100%;
            padding: 9px 12px;
            border: 0;
            background: #fff;
            color: #222;
            text-align: left;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .profile-action:hover {
            background: #f1f2f3;
        }

        .app-main {
            width: calc(100% - 238px);
            min-height: 100vh;
            margin-left: 238px;
        }

        .app-topbar {
            min-height: 58px;
            padding: 0 34px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }

        .topbar-title {
            color: #555;
            font-size: 13px;
        }

        .topbar-user {
            color: #666;
            font-size: 13px;
        }

        .app-content {
            width: 100%;
            padding: 34px;
        }

        /* ---------------------------------------------------------
           Generic page structure
        --------------------------------------------------------- */

        .app-box {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
        }

        .wide-box,
        .dashboard-box {
            max-width: none;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 28px;
        }

        .page-heading h1 {
            margin: 0 0 7px;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }

        .page-heading p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        /* ---------------------------------------------------------
           Buttons
        --------------------------------------------------------- */

        .button,
        .dashboard-action,
        .session-primary-action,
        .event-action,
        .guest-button {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #222;
            border-radius: 3px;
            background: #222;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .button:hover,
        .dashboard-action:hover,
        .session-primary-action:hover,
        .event-action:hover,
        .guest-button:hover {
            background: #333;
            color: #fff;
        }

        /* ---------------------------------------------------------
           Tables
        --------------------------------------------------------- */

        .table-container {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 13px 14px;
            border-bottom: 1px solid #e1e1e1;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f7f7f7;
            color: #555;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        td a {
            color: #222;
        }

        /* ---------------------------------------------------------
           Forms
        --------------------------------------------------------- */

        .field {
            margin-bottom: 19px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #bbb;
            border-radius: 3px;
            background: #fff;
            color: #222;
            font-size: 14px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #555;
        }

        button {
            padding: 10px 14px;
            border: 0;
            border-radius: 3px;
            background: #222;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }

        button:hover {
            background: #333;
        }

        .error {
            margin-bottom: 20px;
            padding: 11px 13px;
            border: 1px solid #dfbcbc;
            background: #fbf1f1;
            color: #7b2e2e;
            font-size: 13px;
        }

        .success {
            margin-bottom: 24px;
            padding: 12px 14px;
            border: 1px solid #b8d5bf;
            background: #f1f8f3;
            color: #245c31;
            font-size: 14px;
        }

        /* ---------------------------------------------------------
           Dashboard
        --------------------------------------------------------- */

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 28px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .dashboard-stat {
            padding: 20px;
            border: 1px solid #ddd;
            background: #fff;
        }

        .dashboard-stat-label {
            margin-bottom: 9px;
            color: #666;
            font-size: 12px;
        }

        .dashboard-stat-value {
            font-size: 27px;
            font-weight: 600;
        }

        .dashboard-list {
            border: 1px solid #ddd;
            background: #fff;
        }

        .dashboard-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 16px 18px;
            border-bottom: 1px solid #ddd;
        }

        .dashboard-list-item:last-child {
            border-bottom: 0;
        }

        .dashboard-list-item strong,
        .dashboard-list-item span {
            display: block;
        }

        .dashboard-list-item span {
            margin-top: 4px;
            color: #666;
            font-size: 13px;
        }

        .dashboard-list-item a {
            color: #222;
            font-weight: 600;
        }

        /* ---------------------------------------------------------
           Event/session pages
        --------------------------------------------------------- */

        .event-header,
        .session-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 25px;
            margin-bottom: 28px;
        }

        .event-header h1 {
            margin: 0 0 7px;
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
            font-size: 13px;
        }

        .event-back:hover {
            text-decoration: underline;
        }

        .event-meta {
            margin: 20px 0;
            color: #555;
        }

        .event-meta p {
            margin: 8px 0;
        }

        .event-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .event-info > div {
            padding: 18px;
            border: 1px solid #ddd;
            background: #fff;
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
        }

        .session-actions-cell {
            text-align: right;
            width: 110px;
        }

        .session-actions {
            position: relative;
            display: inline-block;
        }

        .session-actions summary {
            padding: 8px 10px;
            border: 1px solid #bbb;
            background: #fff;
            color: #222;
            border-radius: 3px;
            cursor: pointer;
            list-style: none;
        }

        .session-actions summary::-webkit-details-marker {
            display: none;
        }

        .session-actions-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 5px);
            z-index: 20;
            width: 170px;
            padding: 5px;
            background: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
        }

        .session-actions-menu a,
        .session-actions-menu button {
            display: block;
            width: 100%;
            padding: 9px 10px;
            border: 0;
            background: #fff;
            color: #222;
            text-align: left;
            text-decoration: none;
            font-size: 13px;
        }

        .session-actions-menu a:hover,
        .session-actions-menu button:hover {
            background: #f0f1f2;
        }

        .session-actions-menu form {
            margin: 0;
        }

        /* ---------------------------------------------------------
           Other existing page components
        --------------------------------------------------------- */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin: 25px 0 35px;
        }

        .stat {
            padding: 20px;
            border: 1px solid #ddd;
            background: #fff;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 27px;
            font-weight: 600;
        }

        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: #222;
            font-size: 13px;
        }

        .page-link {
            display: block;
            margin-bottom: 12px;
            padding: 11px;
            border: 1px solid #bbb;
            border-radius: 3px;
            color: #222;
            text-decoration: none;
        }

        .page-link:hover {
            background: #f4f5f7;
        }

        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            min-width: 8px;
            margin-right: 7px;
            border-radius: 50%;
            vertical-align: middle;
        }

        .status-active {
            background: #16803c;
        }

        .status-ended {
            background: #c62828;
        }

        .status-upcoming {
            background: #999;
        }

        .success-page {
            text-align: center;
            padding: 40px 0;
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
            justify-content: center;
            gap: 12px;
        }

        .attendee-details {
            margin-top: 12px;
            padding: 12px;
            background: #f8f8f8;
            border: 1px solid #ddd;
        }

        .attendee-details p {
            margin: 6px 0;
        }

        /* ---------------------------------------------------------
           Guest pages
        --------------------------------------------------------- */

        .guest-home {
            max-width: 760px;
            margin: 80px auto;
            padding: 0 24px;
            text-align: center;
        }

        .guest-home h1 {
            margin-bottom: 10px;
        }

        .guest-home p {
            color: #666;
            max-width: 550px;
            margin: 0 auto 30px;
        }

        .guest-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .guest-button.secondary {
            background: #fff;
            color: #222;
            border-color: #ccc;
        }

        .guest-button.secondary:hover {
            background: #f5f5f5;
            color: #222;
        }

        /* ---------------------------------------------------------
           Responsive fallback
        --------------------------------------------------------- */

        @media (max-width: 900px) {
            .app-sidebar {
                position: static;
                width: 210px;
            }

            .app-main {
                width: calc(100% - 210px);
                margin-left: 0;
            }

            .app-topbar {
                padding: 0 22px;
            }

            .app-content {
                padding: 24px;
            }

            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .event-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .app-shell {
                display: block;
            }

            .app-sidebar {
                position: static;
                width: 100%;
            }

            .sidebar-content {
                max-height: none;
            }

            .app-main {
                width: 100%;
                margin-left: 0;
            }

            .app-topbar {
                min-height: 50px;
                padding: 0 16px;
            }

            .app-content {
                padding: 20px 16px;
            }

            .page-header,
            .dashboard-header,
            .event-header,
            .session-page-header {
                flex-direction: column;
            }

            .dashboard-stats,
            .stats {
                grid-template-columns: 1fr;
            }

            .session-search {
                flex-direction: column;
            }

            .session-search select,
            .session-search button {
                width: 100%;
            }

            .success-links,
            .guest-actions {
                flex-direction: column;
            }

            .profile-dropdown {
                position: static;
                width: 100%;
                margin-top: 6px;
            }
        }
    </style>
</head>

<body>

@auth

    <div class="app-shell">

        <aside class="app-sidebar">

            <a class="app-brand" href="{{ route('home') }}">
                Attendance Management
            </a>

            <div class="sidebar-content">

                <div class="sidebar-section">
                    <div class="sidebar-label">Overview</div>

                    <a
                        class="sidebar-link {{ request()->routeIs('home') ? 'active' : '' }}"
                        href="{{ route('home') }}"
                    >
                        Dashboard
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-label">Attendance</div>

                    <a
                        class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}"
                        href="{{ route('attendance.pin') }}"
                    >
                        Attendance
                    </a>

                    <a
                        class="sidebar-link {{ request()->routeIs('registrations.public-index') ? 'active' : '' }}"
                        href="{{ route('registrations.public-index') }}"
                    >
                        Events
                    </a>

                    @if(in_array(auth()->user()->role, ['admin', 'organizer']))
                        <a
                            class="sidebar-link {{ request()->routeIs('events.*') ? 'active' : '' }}"
                            href="{{ route('events.index') }}"
                        >
                            Manage Events
                        </a>
                    @endif
                </div>

                @if(auth()->user()->role === 'admin')

                    <div class="sidebar-section">
                        <div class="sidebar-label">Administration</div>

                        <a
                            class="sidebar-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}"
                            href="{{ route('accounts.index') }}"
                        >
                            Accounts
                        </a>

                        <a
                            class="sidebar-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"
                            href="{{ route('audit-logs.index') }}"
                        >
                            Audit Log
                        </a>
                    </div>

                    <div class="sidebar-section">
                        <div class="sidebar-label">Configuration</div>

                        <a
                            class="sidebar-link {{ request()->routeIs('event-types.*') ? 'active' : '' }}"
                            href="{{ route('event-types.index') }}"
                        >
                            Event Types
                        </a>

                        <a
                            class="sidebar-link {{ request()->routeIs('locations.*') ? 'active' : '' }}"
                            href="{{ route('locations.index') }}"
                        >
                            Locations
                        </a>
                    </div>

                @endif

            </div>

            <div class="sidebar-footer">

                <details class="profile-menu">

                    <summary>
                        <span class="profile-summary-name">
                            {{ auth()->user()->name }}
                        </span>

                        <span class="profile-summary-role">
                            {{ ucfirst(auth()->user()->role ?? 'User') }}
                        </span>
                    </summary>

                    <div class="profile-dropdown">

                        <div class="profile-details">
                            <strong class="profile-name">
                                {{ auth()->user()->name }}
                            </strong>

                            <span class="profile-email">
                                {{ auth()->user()->email }}
                            </span>

                            <span class="profile-role">
                                Role: {{ ucfirst(auth()->user()->role ?? 'User') }}
                            </span>
                        </div>

                        <a
                            class="profile-action"
                            href="{{ route('profile') }}"
                        >
                            Profile
                        </a>

                        @if(auth()->user()->role === 'admin')

                            <a
                                class="profile-action"
                                href="{{ route('accounts.index') }}"
                            >
                                Account Management
                            </a>

                            <a
                                class="profile-action"
                                href="{{ route('event-types.index') }}"
                            >
                                Event Types
                            </a>

                            <a
                                class="profile-action"
                                href="{{ route('locations.index') }}"
                            >
                                Locations
                            </a>

                            <a
                                class="profile-action"
                                href="{{ route('audit-logs.index') }}"
                            >
                                Audit Log
                            </a>

                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button
                                class="profile-action"
                                type="submit"
                            >
                                Logout
                            </button>
                        </form>

                    </div>

                </details>

            </div>

        </aside>

        <main class="app-main">

            <div class="app-topbar">
                <span class="topbar-title">
                    @yield('section', 'Attendance Management')
                </span>

                <span class="topbar-user">
                    {{ auth()->user()->name }}
                </span>
            </div>

            <div class="app-content">

                <div class="app-box @yield('container_class')">
                    @yield('content')
                </div>

            </div>

        </main>

    </div>

@else

    <div class="app-shell">

        <main class="app-main" style="width:100%; margin-left:0;">

            <div class="app-topbar">
                <a
                    href="{{ route('home') }}"
                    style="font-weight:600; text-decoration:none;"
                >
                    Attendance Management
                </a>

                <a
                    href="{{ route('login') }}"
                    style="color:#555; text-decoration:none;"
                >
                    Login
                </a>
            </div>

            <div class="app-content">

                <div class="app-box @yield('container_class')">
                    @yield('content')
                </div>

            </div>

        </main>

    </div>

@endauth

</body>
</html>
