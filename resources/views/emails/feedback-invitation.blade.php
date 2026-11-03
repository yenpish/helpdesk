<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Share Your Feedback</title>
</head>
<body>
<h2>We'd Love Your Feedback!</h2>

<p>Hello {{ $invitation->participant_name }},</p>

<p>
    Thank you for attending
    <strong>{{ $invitation->event->name }}</strong>.
    We'd appreciate it if you could share your experience.
</p>

<p>
    <a href="{{ route('feedback.invitation.show', [
            'event' => $invitation->event_id,
            'token' => $token,
        ]) }}">
        Submit Your Feedback
    </a>
</p>

<p>This personal link expires in 7 days and can only be used once.</p>

<p>Thank you!</p>
</body>
</html>
