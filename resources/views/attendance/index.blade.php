<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 40px 20px;
            color: #222;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 32px;
        }

        .card {
            background: white;
            border: 1px solid #ddd;
            padding: 28px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 24px;
        }

        .status {
            font-size: 19px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .success {
            color: #16803c;
        }

        .warning {
            color: #996600;
        }

        .danger {
            color: #b42318;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: #ddd;
            border: 1px solid #ddd;
            margin-top: 20px;
        }

        .detail {
            background: #fff;
            padding: 16px;
        }

        .detail-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
        }

        .detail-value {
            font-weight: bold;
            font-size: 16px;
        }

        button {
            border: none;
            border-radius: 3px;
            padding: 11px 20px;
            font-size: 15px;
            cursor: pointer;
            margin-top: 20px;
        }

        #clockIn,
        #verifySite {
            background: #222;
            color: white;
        }

        #clockOut {
            background: #b42318;
            color: white;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #siteSelection {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 18px;
            margin-top: 20px;
        }

        select {
            padding: 10px;
            border: 1px solid #bbb;
            font-size: 15px;
            margin-top: 8px;
        }

        #status {
            margin-top: 20px;
            font-weight: 500;
        }

        .muted {
            color: #666;
        }

        @media (max-width: 600px) {
            .details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Attendance</h1>
        <p class="muted">
            Welcome, {{ auth()->user()->name }}
        </p>
    </div>


    <div class="card">

        <h2>Today's Attendance</h2>


        @if ($attendance)

            <div class="status success">
                🟢 Checked In
            </div>

            <div class="details">

                <div class="detail">
                    <div class="detail-label">
                        Clocked In
                    </div>

                    <div class="detail-value">
                        {{ $attendance->clock_in_at->format('h:i A') }}
                    </div>
                </div>


                <div class="detail">
                    <div class="detail-label">
                        Location
                    </div>

                    <div class="detail-value">
                        {{ $attendance->location->name }}
                    </div>
                </div>


                <div class="detail">
                    <div class="detail-label">
                        Distance From Site
                    </div>

                    <div class="detail-value">
                        {{ $attendance->distance_from_site }}m
                    </div>
                </div>


                <div class="detail">
                    <div class="detail-label">
                        Allowed Radius
                    </div>

                    <div class="detail-value">
                        {{ $attendance->location->allowed_radius }}m
                    </div>
                </div>


                <div class="detail">
                    <div class="detail-label">
                        GPS Accuracy
                    </div>

                    <div class="detail-value">
                        ±{{ $attendance->accuracy }}m
                    </div>
                </div>


                <div class="detail">
                    <div class="detail-label">
                        Location Verification
                    </div>

                    <div class="detail-value">
                        {{ $attendance->within_site ? 'Accepted' : 'Outside Allowed Area' }}
                    </div>
                </div>

            </div>


            <button id="clockOut">
                Clock Out
            </button>


        @else

            <div class="status">
                ⚪ Not Checked In
            </div>

            <p class="muted">
                Start your attendance by checking your current location.
            </p>

            <button id="clockIn">
                Clock In
            </button>


            <div id="siteSelection" style="display: none;">

                <div class="status warning">
                    🟡 Outside Office
                </div>

                <p>
                    You appear to be away from the office.
                    If you are working at a client site,
                    select the site below.
                </p>

                <label for="locationSelect">
                    <strong>Client Site</strong>
                </label>

                <br>

                <select id="locationSelect">

                    <option value="">
                        -- Select client site --
                    </option>

                    @foreach ($locations as $location)

                        @if ($location->id !== 1)

                            <option value="{{ $location->id }}">
                                {{ $location->name }}
                            </option>

                        @endif

                    @endforeach

                </select>

                <button id="verifySite">
                    Verify Location
                </button>

            </div>

        @endif


        <p id="status"></p>

    </div>


    @if (session('success'))

        <div class="card">
            <p class="success">
                {{ session('success') }}
            </p>
        </div>

    @endif

</div>


<script>

    document.addEventListener('DOMContentLoaded', function () {

        const status =
            document.getElementById('status');

        const clockInButton =
            document.getElementById('clockIn');

        const clockOutButton =
            document.getElementById('clockOut');

        const siteSelection =
            document.getElementById('siteSelection');

        const locationSelect =
            document.getElementById('locationSelect');

        const verifySiteButton =
            document.getElementById('verifySite');

        // =========================
        // CHECK LOCATION
        // =========================

        function checkLocation(locationId) {

            status.textContent =
                'Getting your location...';


            navigator.geolocation.getCurrentPosition(

                function (position) {

                    const latitude =
                        position.coords.latitude;

                    const longitude =
                        position.coords.longitude;

                    const accuracy =
                        position.coords.accuracy;


                    status.textContent =
                        'Location obtained. Checking location...';


                    fetch('{{ route('attendance.clock-in') }}', {

                        method: 'POST',

                        headers: {

                            'Content-Type':
                                'application/json',

                            'X-CSRF-TOKEN':
                                '{{ csrf_token() }}',

                            'Accept':
                                'application/json'

                        },

                        body: JSON.stringify({

                            location_id: locationId,

                            latitude: latitude,

                            longitude: longitude,

                            accuracy: accuracy

                        })

                    })

                        .then(function (response) {

                            return response.json();

                        })

                        .then(function (data) {


                            // =========================
                            // OUTSIDE OFFICE
                            // =========================

                            if (data.outside_office) {

                                status.textContent =
                                    '🟡 Outside office — ' +
                                    data.distance_from_site +
                                    'm away.';


                                if (siteSelection) {

                                    siteSelection.style.display =
                                        'block';

                                }


                                if (clockInButton) {

                                    clockInButton.disabled =
                                        false;

                                }


                                return;

                            }


                            // =========================
                            // OUTSIDE SELECTED SITE
                            // =========================

                            if (!data.within_site) {

                                status.textContent =
                                    '🔴 Outside selected location — ' +
                                    data.distance_from_site +
                                    'm away (allowed: ' +
                                    data.allowed_radius +
                                    'm).';


                                if (verifySiteButton) {

                                    verifySiteButton.disabled =
                                        false;

                                }


                                return;

                            }


                            // =========================
                            // ATTENDANCE RECORDED
                            // =========================

                            status.textContent =
                                '🟢 Location accepted. ' +
                                data.location +
                                ' — ' +
                                data.distance_from_site +
                                'm from site (allowed: ' +
                                data.allowed_radius +
                                'm, accuracy: ±' +
                                data.accuracy +
                                'm).';


                            setTimeout(function () {

                                window.location.reload();

                            }, 1500);

                        })

                        .catch(function (error) {

                            console.error(error);

                            status.textContent =
                                'Something went wrong.';


                            if (clockInButton) {

                                clockInButton.disabled =
                                    false;

                            }


                            if (verifySiteButton) {

                                verifySiteButton.disabled =
                                    false;

                            }

                        });

                },


                function (error) {

                    status.textContent =
                        'Location error: ' +
                        error.message;


                    if (clockInButton) {

                        clockInButton.disabled =
                            false;

                    }


                    if (verifySiteButton) {

                        verifySiteButton.disabled =
                            false;

                    }


                    console.error(error);

                }

            );

        }


        // =========================
        // OFFICE CLOCK IN
        // =========================

        if (clockInButton) {

            clockInButton.addEventListener(
                'click',
                function () {

                    clockInButton.disabled =
                        true;

                    // Location #1 = office
                    checkLocation(1);

                }
            );

        }


        // =========================
        // CLIENT SITE
        // =========================

        if (verifySiteButton) {

            verifySiteButton.addEventListener(
                'click',
                function () {

                    const locationId =
                        locationSelect.value;


                    if (!locationId) {

                        status.textContent =
                            'Please select a client site first.';

                        return;

                    }


                    verifySiteButton.disabled =
                        true;

                    checkLocation(locationId);

                }
            );

        }


        // =========================
        // CLOCK OUT
        // =========================

        if (clockOutButton) {

            clockOutButton.addEventListener(
                'click',
                function () {

                    status.textContent =
                        'Clocking out...';

                    clockOutButton.disabled =
                        true;


                    fetch(
                        '{{ route('attendance.clock-out') }}',
                        {

                            method: 'POST',

                            headers: {

                                'Content-Type':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    '{{ csrf_token() }}',

                                'Accept':
                                    'application/json'

                            }

                        }
                    )

                        .then(function (response) {

                            return response.json();

                        })

                        .then(function (data) {

                            if (
                                data.message ===
                                'Clocked out successfully.'
                            ) {

                                status.textContent =
                                    '🟢 Clocked out successfully at ' +
                                    data.clock_out_at +
                                    '.';


                                setTimeout(function () {

                                    window.location.reload();

                                }, 1000);

                            } else {

                                status.textContent =
                                    data.message;

                                clockOutButton.disabled =
                                    false;

                            }

                        })

                        .catch(function (error) {

                            console.error(error);

                            status.textContent =
                                'Something went wrong.';

                            clockOutButton.disabled =
                                false;

                        });

                }
            );

        }

    });

</script>

</body>
</html>
