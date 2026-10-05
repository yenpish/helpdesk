<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('disables public account registration while keeping login and admin account management available', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/login')->assertOk();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('accounts.index'))
        ->assertOk();
});

it('uses session attendance without retaining the legacy attendance event column', function () {
    expect(Schema::hasColumn('attendances', 'attendance_event_id'))->toBeFalse()
        ->and(Schema::hasColumn('attendances', 'session_id'))->toBeTrue();
});
