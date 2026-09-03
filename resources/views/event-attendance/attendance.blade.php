
@php
    $attendanceTitle = $attendanceTitle
        ?? ($attendanceSession->workshop ?? 'Sesi Semakan Prototype & Dokumen URS dan SFS Permit-Permit KKM Bahagian Kawalan Penyakit (BKP) 26 - 27 Ogos 2026');

    $attendanceLocation = $attendanceLocation
        ?? ($attendanceSession->location ?? 'Bilik Anjung');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Form</title>

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #203a91, #5742ef, #12aee0);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 26px 16px;
            color: #111827;
        }

        .page {
            width: 100%;
            max-width: 680px;
            text-align: center;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 28px 30px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.22);
        }

        h1 {
            font-size: 26px;
            line-height: 1.28;
            margin: 0 0 8px;
        }

        .subtitle {
            margin: 0 0 24px;
            color: #6b7280;
            font-size: 16px;
        }

        .field {
            margin-bottom: 17px;
            text-align: left;
        }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 7px;
            color: #374151;
        }

        .required {
            color: #ef4444;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 13px 15px;
            font-size: 16px;
            outline: none;
            background: white;
        }

        input:focus {
            border-color: #4f46e5;
        }

        input[readonly] {
            background: #f8fafc;
            border-style: dashed;
            color: #374151;
        }

        .signature-box {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }

        canvas {
            display: block;
            width: 100%;
            height: 210px;
            cursor: crosshair;
        }

        .signature-footer {
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            padding: 10px 13px;
            color: #6b7280;
        }

        .clear {
            border: 0;
            background: transparent;
            color: #dc2626;
            font-weight: 700;
            cursor: pointer;
        }

        .submit {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 15px;
            margin-top: 6px;
            background: linear-gradient(90deg, #5a3df0, #12aee0);
            color: white;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        footer {
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 700;
        }
    </style>
</head>

<body>
<div class="page">
    <div class="card">
        <h1>{{ $attendanceTitle }}</h1>

        <p class="subtitle">Please register your attendance</p>

        <form method="POST" action="#" onsubmit="alert('Attendance submitted'); return false;">
            @csrf

            <div class="field">
                <label>Full Name <span class="required">*</span></label>
                <input name="full_name" required>
            </div>

            <div class="field">
                <label>Position <span class="required">*</span></label>
                <input name="position" required>
            </div>

            <div class="field">
                <label>Unit / Division <span class="required">*</span></label>
                <input name="unit_division" required>
            </div>

            <div class="field">
                <label>Phone Number <span class="required">*</span></label>
                <input name="phone" required>
            </div>

            <div class="field">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" required>
            </div>

            <div class="field">
                <label>Workshop <span class="required">*</span></label>
                <input value="{{ $attendanceTitle }}" readonly>
            </div>

            <div class="field">
                <label>Location</label>
                <input value="{{ $attendanceLocation }}" readonly>
            </div>

            <div class="field">
                <label>Signature <span class="required">*</span></label>

                <div class="signature-box">
                    <canvas id="signatureCanvas"></canvas>

                    <div class="signature-footer">
                        <span>Sign above</span>
                        <button class="clear" type="button" id="clearSignature">Clear</button>
                    </div>
                </div>

                <input type="hidden" name="signature" id="signatureInput" required>
            </div>

            <button class="submit" type="submit">Submit Attendance</button>
        </form>
    </div>

    <footer>© 2026</footer>
</div>

<script>
    const canvas = document.getElementById('signatureCanvas');
    const input = document.getElementById('signatureInput');
    const clearButton = document.getElementById('clearSignature');
    const context = canvas.getContext('2d');

    let drawing = false;

    function resizeCanvas() {
        canvas.width = canvas.offsetWidth;
        canvas.height = 210;

        context.lineWidth = 3;
        context.lineCap = 'round';
        context.strokeStyle = '#111827';
    }

    function getPoint(event) {
        const rect = canvas.getBoundingClientRect();
        const pointer = event.touches ? event.touches[0] : event;

        return {
            x: pointer.clientX - rect.left,
            y: pointer.clientY - rect.top,
        };
    }

    function startDrawing(event) {
        drawing = true;

        const point = getPoint(event);

        context.beginPath();
        context.moveTo(point.x, point.y);
    }

    function draw(event) {
        if (!drawing) {
            return;
        }

        event.preventDefault();

        const point = getPoint(event);

        context.lineTo(point.x, point.y);
        context.stroke();

        input.value = canvas.toDataURL('image/png');
    }

    function stopDrawing() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);

    clearButton.addEventListener('click', function () {
        context.clearRect(0, 0, canvas.width, canvas.height);
        input.value = '';
    });

    window.addEventListener('resize', resizeCanvas);

    resizeCanvas();
</script>
</body>
</html>