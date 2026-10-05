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
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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

        /* Shared attendance application styles. */
        :root {
            color-scheme: light;
            --canvas: #F3F4F2;
            --sidebar: #18202A;
            --sidebar-raised: #2B3540;
            --topbar: #202832;
            --surface: #FFFFFF;
            --surface-raised: #EEF1F3;
            --table-head: #E8ECEF;
            --line: #D7DCE1;
            --text: #1F2933;
            --muted: #66717D;
            --blue: #2563EB;
            --blue-hover: #1D4ED8;
            --green: #2F855A;
            --amber: #A87522;
            --red: #B94A48;
        }

        body { background: var(--canvas); color: var(--text); }
        .app-sidebar { background: var(--sidebar); color: var(--text); border-right: 1px solid var(--line); }
        .app-brand { color: #F3F5F7; border-color: var(--line); }
        .sidebar-label, .profile-summary-role { color: #AEB9C4; }
        .sidebar-link { color: #D6DEE6; border-radius: 5px; }
        .sidebar-link:hover, .sidebar-link.active { background: var(--sidebar-raised); color: #FFFFFF; }
        .sidebar-link.active { box-shadow: inset 2px 0 var(--blue); }
        .sidebar-footer { border-color: var(--line); }
        .profile-menu summary { color: var(--text); }
        .profile-menu summary:hover, .profile-menu[open] summary { background: var(--surface); }
        .profile-dropdown, .profile-action { background: var(--surface); border-color: var(--line); color: var(--text); }
        .profile-details { border-color: var(--line); }
        .profile-name, .profile-action { color: var(--text); }
        .profile-email, .profile-role { color: var(--muted); }
        .profile-action:hover { background: var(--surface-raised); }
        .app-topbar { background: var(--topbar); border-color: #35404B; }
        .topbar-title, .topbar-user { color: #CCD2D8; }
        .app-topbar a { color: #E7EDF2; }
        .app-topbar a:hover { color: #fff; }
        .app-content { padding: 36px clamp(20px, 3.2vw, 52px); }
        h1, h2, h3, h4, strong { color: var(--text); }
        p, .text-muted, small, .subtitle { color: var(--muted) !important; }
        a { color: var(--blue); }
        a:hover { color: var(--blue-hover); }

        .container { width: 100%; max-width: 1440px; margin: 0 auto; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }
        .gap-2 { gap: 8px; }
        .mb-0 { margin-bottom: 0; }
        .mb-3 { margin-bottom: 16px; }
        .mb-4 { margin-bottom: 24px; }
        .mt-1 { margin-top: 4px; }
        .mt-3 { margin-top: 16px; }
        .row { display: flex; flex-wrap: wrap; gap: 16px; }
        .col-md-6 { flex: 1 1 calc(50% - 16px); min-width: 240px; }
        .col-sm-3 { flex: 0 0 25%; }
        .col-sm-9 { flex: 1 1 70%; }
        .row dl, dl.row { display: grid; grid-template-columns: minmax(130px, 220px) 1fr; gap: 0 18px; }

        .card, .dashboard-stat, .dashboard-list, .table-container, .table-responsive,
        .events-table-wrapper, .events-empty, .event-info > div, .stat, .attendee-details,
        .page-link, .session-actions-menu, .events-page .events-table-wrapper {
            background: var(--surface) !important;
            border: 1px solid var(--line) !important;
            color: var(--text);
            border-radius: 7px;
        }
        .card { margin-bottom: 22px; overflow: hidden; }
        .card-header { padding: 15px 18px; background: var(--surface-raised); border-bottom: 1px solid var(--line); color: var(--text); font-weight: 600; }
        .card-body { padding: 20px; }
        .alert, .events-message { border: 1px solid var(--line); border-radius: 5px; background: var(--surface); color: var(--text); padding: 13px 15px; }
        .alert-success, .events-message { border-color: #A8D5BA; background: #EAF5EE; color: #276749; }
        .alert-danger { border-color: #E2B5B3; background: #FAEEEE; color: #9B3533; }
        .alert-light { background: var(--surface-raised) !important; border-color: var(--line) !important; color: var(--text) !important; }
        .btn, .button, .create-event-button, .dashboard-action, .session-primary-action, .guest-button {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            min-height: 38px; padding: 9px 14px; border: 1px solid var(--blue) !important;
            border-radius: 5px; background: var(--blue) !important; color: #fff !important;
            font-size: 13px; font-weight: 600; line-height: 1.2; text-decoration: none; cursor: pointer;
        }
        .btn:hover, .button:hover, .create-event-button:hover, .dashboard-action:hover, .session-primary-action:hover, .guest-button:hover { background: var(--blue-hover) !important; color: #fff !important; }
        .btn-secondary, .btn-outline-primary, .btn-outline-secondary, .event-action, .event-back, .guest-button.secondary {
            background: var(--surface) !important; color: var(--text) !important; border-color: var(--line) !important;
        }
        .btn-secondary:hover, .btn-outline-primary:hover, .btn-outline-secondary:hover, .event-action:hover, .guest-button.secondary:hover { background: var(--surface-raised) !important; }
        .btn-danger, .event-action-danger { border-color: #E2B5B3 !important; background: #FAEEEE !important; color: #9B3533 !important; }
        .table, table { width: 100%; border-collapse: collapse; color: var(--text); font-size: 13px; }
        table { background: var(--surface) !important; }
        .table th, th, .events-table th { padding: 13px 14px; background: var(--table-head) !important; border-bottom: 1px solid var(--line) !important; color: var(--muted) !important; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; text-align: left; }
        .table td, td, .events-table td { padding: 14px; border-bottom: 1px solid var(--line) !important; color: var(--text) !important; vertical-align: middle; }
        tbody tr:hover, .events-table tbody tr:hover { background: var(--surface-raised) !important; }
        .table a, td a, .event-name a { color: var(--blue) !important; }
        .form-control, .form-select, input, textarea, select { width: 100%; padding: 10px 12px; border: 1px solid var(--line) !important; border-radius: 5px; background: var(--surface) !important; color: var(--text) !important; font: inherit; }
        input::placeholder, textarea::placeholder { color: #77818B; }
        input:focus, textarea:focus, select:focus { outline: 2px solid #2563EB55; border-color: var(--blue) !important; }
        label, .form-label { display: block; margin-bottom: 7px; color: var(--text) !important; font-size: 13px; font-weight: 600; }
        .form-text, .text-muted { color: var(--muted) !important; }
        .table-responsive, .events-table-wrapper { overflow-x: auto; }
        .pagination, nav[role="navigation"] { color: var(--muted); }
        pre { overflow: auto; padding: 12px; border: 1px solid var(--line); border-radius: 5px; background: var(--surface-raised) !important; color: var(--text); }
        .dashboard-stat { padding: 20px; }
        .dashboard-stat-value, .stat-value { font-size: 30px; color: var(--text); }
        .dashboard-stat-label, .stat-label, .dashboard-list-item span { color: var(--muted) !important; }
        .dashboard-list-item { border-color: var(--line); }
        .dashboard-list-item a { color: var(--blue); }
        .event-status, .badge { border-radius: 999px; padding: 4px 9px; background: var(--surface-raised) !important; border: 1px solid var(--line) !important; color: var(--text) !important; }
        .event-status.published, .badge-success { background: #EAF5EE !important; border-color: #A8D5BA !important; color: #276749 !important; }
        .event-status.draft, .badge-warning { background: #F8F1E5 !important; border-color: #E3C995 !important; color: #805B18 !important; }
        .event-status.cancelled, .badge-danger { background: #FAEEEE !important; border-color: #E2B5B3 !important; color: #9B3533 !important; }
        .event-status.completed { background: var(--surface-raised) !important; color: var(--muted) !important; }
        .page-heading h1, .dashboard-header h1, .events-heading h1 { color: var(--text); }
        .page-heading p, .event-subtitle, .events-heading p { color: var(--muted) !important; }
        .events-page { width: 100%; max-width: 1560px; margin: 0 auto; padding: 0; }
        .events-heading h1 { font-size: 30px; }
        .events-page .events-empty { padding: 20px; }
        .events-page .events-empty h2 { color: var(--text); }
        .events-pagination { color: var(--muted); }
        .events-pagination-links a, .events-pagination-links span { background: var(--surface) !important; border-color: var(--line) !important; color: var(--text) !important; }
        .events-pagination-links span[aria-current="page"] { background: var(--blue) !important; color: #fff !important; }
        .success-icon { border-color: var(--green); color: var(--green); }
        .success-page p, .guest-home p { color: var(--muted) !important; }
        .guest-home { max-width: 780px; padding: 44px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); }
        .session-actions summary { background: var(--surface); color: var(--text); border-color: var(--line); }
        .session-actions-menu { background: var(--surface); }
        .session-actions-menu a, .session-actions-menu button { background: var(--surface); color: var(--text); }
        .session-actions-menu a:hover, .session-actions-menu button:hover { background: var(--surface-raised); }
        .app-box > .container { max-width: 1440px; }
        .content-panel { max-width: 820px; margin-inline: auto; padding: 26px; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); }
        .login-panel { margin: 7vh auto; }
        .profile-panel { margin-inline: auto; }
        .back-link { color: var(--muted) !important; }
        .attendance-pin-panel { max-width: 520px; margin: 28px auto; }
        .attendance-panel { max-width: 820px; margin: 0 auto; }
        .attendance-panel form { max-width: 700px; }
        .success-panel, .registration-success-panel { max-width: 720px; margin: 25px auto; }
        .success-panel { text-align: center; }
        .login-panel form { max-width: none; }
        .content-panel h1 { margin-top: 0; }
        .content-panel .field:last-of-type { margin-bottom: 20px; }
        .container > h1 { margin: 0 0 8px; font-size: 28px; }
        .container > .d-flex { margin-bottom: 25px; gap: 18px; }
        .event-name { min-width: 190px; }
        .event-description { color: var(--muted) !important; }
        .section-heading { margin: 0 0 14px; font-size: 17px; font-weight: 600; }
        .section-heading-row { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 14px; }
        .section-heading-row p { margin: 0; }
        .dashboard-section { margin-top: 32px; }
        .quick-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; }
        .quick-links a { display: block; padding: 15px 17px; border: 1px solid var(--line); border-radius: 6px; background: var(--surface); text-decoration: none; }
        .quick-links a:hover { background: var(--surface-raised); border-color: #59616C; }
        .quick-links strong, .quick-links span { display: block; }
        .quick-links strong { margin-bottom: 4px; color: var(--text); font-size: 13px; }
        .quick-links span { color: var(--muted); font-size: 12px; }
        .outcome { display: inline-block; padding: 4px 8px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface-raised); color: var(--muted); font-size: 12px; white-space: nowrap; }
        .outcome-attended { border-color: #A8D5BA; background: #EAF5EE; color: #276749; }
        .outcome-missed { border-color: #E2B5B3; background: #FAEEEE; color: #9B3533; }
        .status-control { display: flex; align-items: center; gap: 8px; min-width: 210px; }
        .status-control select { width: auto; min-width: 125px; flex: 1 1 auto; }
        .status-control button { flex: 0 0 auto; }
        .actions-cell { min-width: 210px; white-space: nowrap; text-align: right; }
        .inline-form { display: inline; }
        .table-actions { white-space: nowrap; }
        .table-actions .btn { margin-right: 4px; }
        .table-actions .inline-form { display: inline-block; margin: 0; vertical-align: middle; }
        .btn-sm { min-height: 30px; padding: 6px 9px; font-size: 12px; }
        .events-page .page-header { margin-bottom: 24px; }
        .events-page .create-event-button { background: var(--blue) !important; border-color: var(--blue) !important; color: #fff !important; }
        .events-page .events-table th { background: var(--table-head) !important; color: var(--muted) !important; }
        .events-page .events-table td { border-color: var(--line) !important; color: var(--text) !important; }
        .events-page .events-table-wrapper { background: var(--surface) !important; }
        .app-sidebar { width: 220px; }
        .app-main { width: calc(100% - 220px); margin-left: 220px; }
        .app-content { padding: 30px clamp(20px, 2.4vw, 38px); }
        .events-page .events-table { width: 100%; min-width: 960px; table-layout: fixed; }
        .events-page .events-table th, .events-page .events-table td { padding: 12px 10px; overflow-wrap: anywhere; }
        .events-page .events-table th:nth-child(1), .events-page .events-table td:nth-child(1) { width: 21%; }
        .events-page .events-table th:nth-child(2), .events-page .events-table td:nth-child(2) { width: 10%; }
        .events-page .events-table th:nth-child(3), .events-page .events-table td:nth-child(3) { width: 14%; }
        .events-page .events-table th:nth-child(4), .events-page .events-table td:nth-child(4) { width: 12%; }
        .events-page .events-table th:nth-child(5), .events-page .events-table td:nth-child(5) { width: 7%; }
        .events-page .events-table th:nth-child(6), .events-page .events-table td:nth-child(6) { width: 10%; }
        .events-page .events-table th:nth-child(7), .events-page .events-table td:nth-child(7) { width: 10%; }
        .events-page .events-table th:nth-child(8), .events-page .events-table td:nth-child(8) { width: 16%; }
        .events-page .actions-cell { min-width: 160px; white-space: nowrap; }
        .events-page .events-table td:nth-child(2), .events-page .events-table td:nth-child(5),
        .events-page .events-table td:nth-child(6), .events-page .events-table td:nth-child(7) { white-space: nowrap; }
        .events-page .event-name { min-width: 0; }
        .events-page .actions-cell .btn-sm { padding-inline: 7px; }
        .registrations-table { min-width: 1040px; }
        .registrations-table td:nth-child(1), .registrations-table td:nth-child(2),
        .registrations-table td:nth-child(3), .registrations-table td:nth-child(7) { white-space: nowrap; }
        .events-page .event-action, .events-page .event-actions .btn { margin-left: 3px; }
        .events-page .event-actions .inline-form { margin: 0; }
        .events-page .events-pagination { margin-top: 16px; }
        .container > form, .card-body > form { max-width: 780px; margin-inline: auto; }
        .account-form { max-width: 680px; margin-inline: auto; }
        .profile-menu summary:focus-visible, a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        button:not(.profile-action):not(.session-actions-menu button) { background: var(--blue); color: #fff; font-weight: 600; }
        button:not(.profile-action):not(.session-actions-menu button):hover { background: var(--blue-hover); }

        .app-sidebar a:focus-visible { outline-color: var(--blue); }
        .public-login-link { color: #CCD2D8; text-decoration: none; }
        .form-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 20px; }
        @media (max-width: 600px) { .attendance-form-actions { flex-wrap: wrap; } }
        .field-error { margin-top: 6px; color: var(--red); font-size: 12px; }
        .form-note { margin: 5px 0 0; padding: 12px 14px; border-left: 2px solid var(--blue); background: var(--surface-raised); color: var(--muted); font-size: 13px; }
        .empty-state { padding: 38px 24px; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); text-align: center; }
        .empty-state h2 { margin: 0 0 8px; font-size: 18px; }
        .empty-state p { margin: 0 0 18px; }
        .role-label { display: inline-block; padding: 4px 8px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface-raised); color: var(--text); font-size: 12px; }
        dl.row dd { margin: 0 0 13px; }
        dl.row dt { padding-top: 1px; color: var(--muted); }
        .nowrap { white-space: nowrap; }
        .pagination-wrap { margin-top: 18px; color: var(--muted); }
        .attendance-form-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 8px; }
        .attendance-panel .back-link { margin-top: 18px; text-decoration: none; }
        .success-links a { text-decoration: none; }
        .events-pagination-links { display: flex; align-items: center; gap: 8px; }
        .pagination-link, .pagination-disabled, .pagination-wrap nav a, .pagination-wrap nav button,
        .pagination-wrap nav [aria-current="page"], .pagination-wrap nav [aria-disabled="true"] {
            display: inline-flex; align-items: center; justify-content: center; min-height: 32px;
            padding: 5px 10px; border: 1px solid var(--line); border-radius: 5px;
            background: var(--surface-raised); color: var(--muted) !important; text-decoration: none;
        }
        .pagination-link:hover, .pagination-wrap nav a:hover { background: var(--line) !important; color: var(--text) !important; text-decoration: none; }
        .pagination-disabled { opacity: .55; }
        .pagination-wrap nav [aria-current="page"] { background: var(--blue) !important; border-color: var(--blue); color: #fff !important; }
        .btn:disabled { opacity: .55; cursor: not-allowed; background: var(--surface-raised) !important; border-color: var(--line) !important; color: var(--muted) !important; }
        .audit-action { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .04em; }
        .audit-details summary { color: var(--blue); cursor: pointer; white-space: nowrap; }
        .audit-values { width: min(760px, 65vw); max-width: 100%; margin-top: 10px; }
        .audit-changes-table { table-layout: fixed; }

        .registration-attendance-table {
            width: 100%;
            table-layout: fixed;
        }

        .registration-attendance-table th,
        .registration-attendance-table td {
            white-space: normal;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }

        .registration-attendance-table-wrap { overflow-x: auto; }

        .registration-attendance-status-headings,
        .registration-attendance-status-values {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: .4rem;
        }

        .registration-attendance-status-headings {
            color: #6c757d;
            font-size: .72rem;
            font-weight: 500;
        }

        .registration-attendance-status-values {
            align-items: center;
            font-size: .9rem;
        }

        .events-filter-form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .events-page > .events-filter-form {
            max-width: none;
            margin-inline: 0;
        }

        .events-search-field { flex: 1 1 320px; min-width: 240px; }
        .events-sort-field { flex: 0 1 230px; min-width: 190px; }
        .events-filter-action { flex: 0 0 auto; }
        .events-filter-action .btn { min-height: 40px; }

        .event-details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 36px;
        }

        .session-details-description { grid-column: 1 / -1; }

        .event-details-meta {
            display: grid;
            grid-template-columns: minmax(220px, .8fr) minmax(0, 1.2fr);
            gap: 28px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .session-details-meta { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }

        .event-details-meta-group h3 {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .event-details-meta-row {
            display: grid;
            grid-template-columns: 82px minmax(0, 1fr);
            gap: 10px;
            padding: 7px 0;
        }

        .event-details-meta-row dt {
            color: var(--muted);
            font-weight: 500;
        }

        .event-details-meta-row dd {
            min-width: 0;
            margin: 0;
            overflow-wrap: anywhere;
        }

        .event-details-meta-row dd span { color: var(--muted); padding: 0 4px; }

        .event-details-pair {
            display: grid;
            grid-template-columns: minmax(105px, 0.4fr) minmax(0, 1fr);
            align-items: start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
        }

        .event-details-pair dt {
            color: var(--muted);
            font-weight: 500;
        }

        .event-details-pair dd {
            min-width: 0;
            margin: 0;
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {
            .events-search-field,
            .events-sort-field { flex-basis: 100%; }
        }

        @media (max-width: 900px) {
            .event-details-grid { grid-template-columns: 1fr; column-gap: 0; }
            .event-details-meta { grid-template-columns: 1fr; gap: 18px; }
        }
        .audit-changes-table th:first-child { width: 125px; }
        .audit-changes-table th, .audit-changes-table td { padding: 8px 10px; font-size: 12px; letter-spacing: normal; text-transform: none; white-space: normal; overflow-wrap: anywhere; }
        .public-event-card { display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 14px; padding: 22px 24px; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); }
        .public-event-main { min-width: 0; }
        .public-event-main h2 { margin: 6px 0 8px; font-size: 20px; }
        .public-event-main p { margin: 0 0 14px; }
        .event-type-label { color: var(--blue); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .public-event-meta { display: flex; flex-wrap: wrap; gap: 24px; margin: 0; }
        .public-event-meta dt { margin-bottom: 4px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .public-event-meta dd { margin: 0; color: var(--text); font-size: 13px; }
        .public-event-meta dd span { color: var(--muted); }
        .public-event-action { flex: 0 0 auto; }
        form[onsubmit*="Delete"] button { border: 1px solid #E2B5B3 !important; background: #FAEEEE !important; color: #9B3533 !important; }

        @media (max-width: 900px) {
            .app-sidebar { position: static; width: 210px; }
            .app-main { width: calc(100% - 210px); margin-left: 0; }
        }

        @media (max-width: 700px) {
            .app-sidebar { position: static; width: 100%; }
            .app-main { width: 100%; margin-left: 0; }
            .app-content { padding: 22px 16px; }
            .container > .d-flex { align-items: flex-start; flex-direction: column; }
            .d-flex.gap-2 { flex-wrap: wrap; }
            dl.row { grid-template-columns: 1fr; }
            .col-sm-3 { font-size: 12px; }
            .col-sm-9 { margin: 0 0 12px; }
            .guest-home { margin: 28px auto; padding: 24px; }
            .section-heading-row, .public-event-card { align-items: flex-start; flex-direction: column; }
            .public-event-card { padding: 18px; }
            .public-event-action { width: 100%; }
            .public-event-action .btn { width: 100%; }
            .form-actions .btn { flex: 1 1 auto; }
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
                    <div class="sidebar-label">Attendance &amp; registration</div>

                    <a
                        class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}"
                        href="{{ route('attendance.pin') }}"
                    >
                        Record Attendance
                    </a>

                    <a
                        class="sidebar-link {{ request()->routeIs('registrations.public-index') ? 'active' : '' }}"
                        href="{{ route('registrations.public-index') }}"
                    >
                        Browse Events
                    </a>

                    @if(in_array(auth()->user()->role, ['admin', 'organizer']))
                        <a
                            class="sidebar-link {{ request()->routeIs('events.*') ? 'active' : '' }}"
                            href="{{ route('events.index') }}"
                        >
                            Events
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
                            User Accounts
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
                            {{ match(auth()->user()->role) { 'admin' => 'System Admin', 'organizer' => 'Organizer', 'user' => 'User', default => 'Legacy account' } }}
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
                                Role: {{ match(auth()->user()->role) { 'admin' => 'System Admin', 'organizer' => 'Organizer', 'user' => 'User', default => 'Legacy account' } }}
                            </span>
                        </div>

                        <a
                            class="profile-action"
                            href="{{ route('profile') }}"
                        >
                            Profile
                        </a>

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

                <a class="public-login-link" href="{{ route('login') }}">
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
