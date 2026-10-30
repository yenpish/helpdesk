<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

function makeEncryptedAttendanceFixture(string $eventStatus = 'published'): array
{
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = Event::create([
        'name' => 'Encrypted attendance event',
        'organizer_id' => $organizer->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'status' => $eventStatus,
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Active session',
        'starts_at' => now()->subMinutes(15),
        'ends_at' => now()->addMinutes(45),
        'attendance_opens_at' => now()->subMinutes(15),
        'attendance_closes_at' => now()->addMinutes(45),
    ]);

    return [$organizer, $event, $session];
}

function encryptedAttendanceTestUrl(int $eventId, int $sessionId, ?int $expiresAt = null): string
{
    $ciphertext = Crypt::encryptString(json_encode([
        'version' => 1,
        'event_id' => $eventId,
        'session_id' => $sessionId,
        'expires_at' => $expiresAt ?? now()->addMinutes(45)->timestamp,
    ], JSON_THROW_ON_ERROR));
    $token = rtrim(strtr(base64_encode($ciphertext), '+/', '-_'), '=');

    return route('attendance.encrypted', ['token' => $token]);
}

it('shows an encrypted session link only to an authorized owner while attendance is open', function () {
    [$organizer, $event, $session] = makeEncryptedAttendanceFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('/attendance-link/', false);

    $this->actingAs($admin)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('/attendance-link/', false);

    $this->actingAs($otherOrganizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertForbidden()
        ->assertDontSee('/attendance-link/', false);
});

it('generates an SVG QR code from the exact encrypted session URL', function () {
    [$organizer, $event, $session] = makeEncryptedAttendanceFixture();

    $response = $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('<section class="card mb-3 attendance-qr-utility"', false)
        ->assertSee('View QR Code')
        ->assertSee('id="attendance-qr-dialog"', false)
        ->assertSee('data-close-attendance-qr', false)
        ->assertSee('qrDialog?.showModal()', false)
        ->assertSee('width="250"', false)
        ->assertSee('Attendance QR Code')
        ->assertSee('Available during the active attendance window.')
        ->assertSee('Scan this QR code to access the attendance form for this session.')
        ->assertSee('Copy Attendance Link');

    $html = $response->getContent();
    $sessionInfoPosition = strpos($html, 'Session Information');
    $qrSectionPosition = strpos($html, 'id="attendance-qr-heading"');
    $attendancePosition = strpos($html, 'Pre-registration &amp; Attendance');
    preg_match('/<img[^>]+src="data:image\/svg\+xml;base64,([^"]+)"[^>]+data-qr-content="([^"]+)"/s', $html, $matches);
    preg_match_all('/data-copy-attendance-link="([^"]+)"/', $html, $copyLinkMatches);

    expect($sessionInfoPosition)->toBeLessThan($qrSectionPosition)
        ->and($qrSectionPosition)->toBeLessThan($attendancePosition);
    expect($matches)->toHaveCount(3);
    expect($copyLinkMatches[1])->toHaveCount(2);

    $qrContent = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);
    $copyLinks = array_map(fn (string $url) => html_entity_decode($url, ENT_QUOTES | ENT_HTML5), $copyLinkMatches[1]);
    $qrSvg = base64_decode($matches[1], true);
    $expectedSvg = (new Writer(
        new ImageRenderer(new RendererStyle(420, 4), new SvgImageBackEnd())
    ))->writeString($qrContent);

    expect($qrContent)->toContain('/attendance-link/')
        ->and($copyLinks)->toBe([$qrContent, $qrContent])
        ->and($html)->not->toContain('href="' . e($qrContent) . '"')
        ->and($qrSvg)->toBe($expectedSvg)
        ->and($qrSvg)->toContain('<svg')
        ->and($qrSvg)->toContain('<path');
});

it('shows the QR at the exact attendance opening and closing instants only', function () {
    $this->travelTo(Carbon::parse('2026-11-05 10:00:00', config('app.timezone')));
    [$organizer, $event, $session] = makeEncryptedAttendanceFixture();
    $opensAt = Carbon::parse('2026-11-05 10:10:00', config('app.timezone'));
    $closesAt = Carbon::parse('2026-11-05 10:40:00', config('app.timezone'));
    $session->update([
        'attendance_opens_at' => $opensAt,
        'attendance_closes_at' => $closesAt,
    ]);
    $url = route('events.event-sessions.show', [$event, $session]);

    $this->travelTo($opensAt->copy()->subSecond());
    $this->actingAs($organizer)->get($url)->assertOk()->assertDontSee('Attendance QR Code');

    $this->travelTo($opensAt);
    expect(now()->between(
        $session->fresh()->attendance_opens_at,
        $session->fresh()->attendance_closes_at
    ))->toBeTrue();
    $this->get($url)->assertOk()->assertSee('Attendance QR Code');

    $this->travelTo($closesAt);
    $this->get($url)->assertOk()->assertSee('Attendance QR Code');

    $this->travelTo($closesAt->copy()->addSecond());
    $this->get($url)->assertOk()->assertDontSee('Attendance QR Code');
});

it('opens a valid link, binds the attendee session, and allows reusable scans during the window', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $url = encryptedAttendanceTestUrl($event->id, $session->id);

    $firstResponse = $this->get($url)->assertRedirect();
    $firstFormPath = parse_url($firstResponse->headers->get('Location'), PHP_URL_PATH);
    $firstAccess = basename($firstFormPath);

    expect($firstFormPath)->toMatch('/\A\/attendance\/[a-f0-9]{64}\z/')
        ->and($firstAccess)->not->toBe((string) $event->id);
    expect(session("attendance_accesses.{$firstAccess}.event_id"))->toBe($event->id)
        ->and(session("attendance_accesses.{$firstAccess}.session_id"))->toBe($session->id)
        ->and(session()->has('attendance_event_id'))->toBeFalse()
        ->and(session()->has('attendance_session_id'))->toBeFalse();

    $this->get($firstFormPath)
        ->assertOk()
        ->assertSee($session->name);

    $secondResponse = $this->get($url)->assertRedirect();
    $secondFormPath = parse_url($secondResponse->headers->get('Location'), PHP_URL_PATH);
    expect($secondFormPath)->not->toBe($firstFormPath);
    $this->get($secondFormPath)->assertOk();
});

it('rejects a copied opaque form URL in an independent browser session', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $qrUrl = encryptedAttendanceTestUrl($event->id, $session->id);
    $response = $this->get($qrUrl)->assertRedirect();
    $formPath = parse_url($response->headers->get('Location'), PHP_URL_PATH);
    $sessionCookieName = config('session.cookie');
    $firstBrowserSessionId = $response->getCookie($sessionCookieName)->getValue();

    $this->get($formPath)->assertOk();

    // A different session cookie represents an independent browser session.
    $secondBrowserSessionId = bin2hex(random_bytes(20));
    expect($secondBrowserSessionId)->not->toBe($firstBrowserSessionId);

    // A browser with no matching server-side grant cannot use the copied form URL.
    $this->app['session']->forgetDrivers();
    $this->app->forgetInstance('session.store');
    $this->withCookie($sessionCookieName, $secondBrowserSessionId)
        ->get($formPath)
        ->assertRedirect(route('attendance.pin'));

    // The reusable encrypted QR can establish a separate grant for that browser.
    $newResponse = $this->get($qrUrl)->assertRedirect();
    $newFormPath = parse_url($newResponse->headers->get('Location'), PHP_URL_PATH);
    expect($newFormPath)->not->toBe($formPath);
    $this->get($newFormPath)->assertOk();
});

it('rejects direct attendance URLs containing sequential event identifiers', function () {
    [, $event] = makeEncryptedAttendanceFixture();

    $this->get('/attendance/' . $event->id)
        ->assertRedirect(route('attendance.pin'));
});

it('issues an opaque browser-bound form URL after successful PIN verification', function () {
    [, $event] = makeEncryptedAttendanceFixture();
    $event->update(['pin' => 'ABCD']);

    $response = $this->post(route('attendance.verify'), ['pin' => 'ABCD'])
        ->assertRedirect();
    $formPath = parse_url($response->headers->get('Location'), PHP_URL_PATH);
    $access = basename($formPath);

    expect($formPath)->toMatch('/\A\/attendance\/[a-f0-9]{64}\z/')
        ->and($access)->not->toBe((string) $event->id);

    $this->get($formPath)->assertOk();
});

it('rejects an attendance POST without a current-session access grant', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $access = bin2hex(random_bytes(32));

    $this->post(route('attendance.store', ['access' => $access]), [
        'session_id' => $session->id,
        'full_name' => 'Unauthorized attendee',
        'email' => 'unauthorized@example.test',
        'phone' => '60125902441',
    ])->assertRedirect(route('attendance.pin'));

    expect($event->attendances()->count())->toBe(0);
});

it('allows a QR-bound online check-in for the encrypted session', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();

    $response = $this->get(encryptedAttendanceTestUrl($event->id, $session->id))
        ->assertRedirect();
    $access = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

    $this->post(route('attendance.store', ['access' => $access]), [
        'session_id' => $session->id,
        'full_name' => 'QR Attendee',
        'email' => 'qr-attendee@example.test',
        'phone' => '60125902441',
    ])->assertOk()->assertViewIs('attendance.success');

    expect($session->attendances()->sole()->email)->toBe('qr-attendee@example.test');
});

it('rejects malformed, modified, and event-session-mismatched tokens safely', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    [, $otherEvent, $otherSession] = makeEncryptedAttendanceFixture();

    $this->get(route('attendance.encrypted', ['token' => 'not-a-valid-token']))
        ->assertNotFound();

    $validUrl = encryptedAttendanceTestUrl($event->id, $session->id);
    $validToken = basename(parse_url($validUrl, PHP_URL_PATH));
    $modifiedToken = ($validToken[0] === 'a' ? 'b' : 'a') . substr($validToken, 1);
    $this->get(route('attendance.encrypted', ['token' => $modifiedToken]))
        ->assertNotFound();

    $this->get(encryptedAttendanceTestUrl($event->id, $otherSession->id))
        ->assertNotFound();

    $this->get(encryptedAttendanceTestUrl(999999, $session->id))
        ->assertNotFound();
});

it('rate limits repeated attendance PIN attempts', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('attendance.verify'), ['pin' => 'ZZZZ'])
            ->assertRedirect();
    }

    $this->post(route('attendance.verify'), ['pin' => 'ZZZZ'])
        ->assertTooManyRequests();
});

it('rejects links after their session window has ended', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $url = encryptedAttendanceTestUrl($event->id, $session->id);

    $this->travelTo(now()->addDays(2));
    $this->get($url)->assertForbidden();
});

it('invalidates an already established form handle when its attendance window closes', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $url = encryptedAttendanceTestUrl($event->id, $session->id);
    $response = $this->get($url)->assertRedirect();
    $formPath = parse_url($response->headers->get('Location'), PHP_URL_PATH);

    $this->get($formPath)->assertOk();

    $this->travelTo($session->attendance_closes_at->copy()->addSecond());
    $this->get($formPath)->assertRedirect(route('attendance.pin'));
});

it('rejects expired tokens even if a session window is later reopened', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $expiredUrl = encryptedAttendanceTestUrl($event->id, $session->id, now()->subMinute()->timestamp);
    $session->update([
        'attendance_opens_at' => now()->subMinute(),
        'attendance_closes_at' => now()->addHour(),
    ]);

    $this->get($expiredUrl)->assertForbidden();
});

it('rejects links for draft and cancelled events even when the session window is open', function (string $status) {
    [, $event, $session] = makeEncryptedAttendanceFixture($status);

    $this->get(encryptedAttendanceTestUrl($event->id, $session->id))
        ->assertForbidden();
})->with(['draft', 'cancelled']);

it('allows a completed event while its session attendance window is still open', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture('completed');

    $response = $this->get(encryptedAttendanceTestUrl($event->id, $session->id))
        ->assertRedirect();
    $access = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
    expect(session("attendance_accesses.{$access}.session_id"))->toBe($session->id);
    $this->get(route('attendance.form', ['access' => $access]))->assertOk();
});

it('does not allow a QR-bound form to submit attendance for another session', function () {
    [, $event, $session] = makeEncryptedAttendanceFixture();
    $otherSession = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Other session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(30),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(30),
    ]);

    $response = $this->get(encryptedAttendanceTestUrl($event->id, $session->id))
        ->assertRedirect();
    $access = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

    $this->from(route('attendance.form', ['access' => $access]))
        ->post(route('attendance.store', ['access' => $access]), [
            'session_id' => $otherSession->id,
            'full_name' => 'Test Attendee',
            'email' => 'qr-bound@example.test',
            'phone' => '60125902441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access]))
        ->assertSessionHasErrors('session_id');

    expect($session->attendances()->count())->toBe(0)
        ->and($otherSession->attendances()->count())->toBe(0);
});

it('does not show a session link outside its attendance window', function () {
    [$organizer, $event, $session] = makeEncryptedAttendanceFixture();
    $session->update([
        'attendance_opens_at' => now()->subDays(2),
        'attendance_closes_at' => now()->subDay(),
    ]);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertDontSee('/attendance-link/', false);
});
