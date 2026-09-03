<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Helpdesk')</title>

    <style>
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

        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #222;
        }

        .app-box {
            width: 100%;
            max-width: 600px;
            background: white;
            border: 1px solid #ddd;
            padding: 32px;
            box-sizing: border-box;
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
    </style>
</head>

<body>

<div class="app-box @yield('container_class')">
    @yield('content')
</div>

</body>
</html>
