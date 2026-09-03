

@php
    $attendanceTitle = $attendanceTitle
        ?? 'Sesi Semakan Prototype & Dokumen URS dan SFS Permit-Permit KKM Bahagian Kawalan Penyakit (BKP) 26 - 27 Ogos 2026';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance PIN</title>

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #203a91, #5742ef, #12aee0);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 28px 16px;
            color: #111827;
        }

        .page {
            width: 100%;
            max-width: 660px;
            text-align: center;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.22);
        }

        .icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #eef2ff;
            display: grid;
            place-items: center;
            margin: 0 auto 18px;
            font-size: 14px;
            font-weight: 700;
            color: #4f46e5;
        }

        h1 {
            font-size: 27px;
            line-height: 1.28;
            margin: 0 0 10px;
        }

        .subtitle {
            margin: 0 0 24px;
            color: #6b7280;
            font-size: 16px;
        }

        label {
            display: block;
            text-align: left;
            font-weight: 700;
            margin-bottom: 8px;
            color: #374151;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 18px;
            outline: none;
        }

        input:focus {
            border-color: #4f46e5;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 15px;
            margin-top: 22px;
            background: linear-gradient(90deg, #5a3df0, #12aee0);
            color: white;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        footer {
            margin-top: 22px;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 700;
        }
    </style>
</head>

<body>
<div class="page">
    <div class="card">
        <div class="icon">PIN</div>

        <h1>{{ $attendanceTitle }}</h1>

        <p class="subtitle">Please enter the Check-in PIN to continue.</p>

        <form method="GET" action="{{ route('event-attendance.form') }}">
            <label for="pin">Check-in PIN</label>

            <input
                id="pin"
                name="pin"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{4}"
                maxlength="4"
                placeholder="Enter 4-digit PIN"
                required
            >

            <button type="submit">
                Continue
            </button>
        </form>
    </div>

    <footer>© 2026</footer>
</div>
</body>
</html>