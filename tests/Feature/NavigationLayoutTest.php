<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function navigationUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

function navigationDocument(string $html): DOMDocument
{
    $document = new DOMDocument();
    $previousErrorMode = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorMode);

    return $document;
}

function findNavigationLink(DOMDocument $document, string $label, string $url): ?DOMElement
{
    foreach ($document->getElementsByTagName('a') as $link) {
        if (trim($link->textContent) === $label && $link->getAttribute('href') === $url) {
            return $link;
        }
    }

    return null;
}

function findNavigationElementById(DOMDocument $document, string $id): ?DOMElement
{
    $elements = (new DOMXPath($document))->query('//*[@id="'.$id.'"]');

    return $elements?->item(0) instanceof DOMElement ? $elements->item(0) : null;
}

it('shows the full navigation to admins and only one account menu', function () {
    $this->actingAs(navigationUser('admin'));

    $response = $this->get(route('profile'))->assertOk();

    $document = navigationDocument($response->getContent());

    foreach ([
        'Events' => route('events.index'),
        'Record Attendance' => route('attendance.pin'),
        'Browse Events' => route('registrations.public-index'),
        'Accounts' => route('accounts.index'),
        'Roles & Permissions' => route('roles.index'),
        'Audit Log' => route('audit-logs.index'),
        'Event Types' => route('event-types.index'),
        'Locations' => route('locations.index'),
        'Profile' => route('profile'),
        'Appearance' => route('settings.edit'),
    ] as $label => $url) {
        expect(findNavigationLink($document, $label, $url))->not->toBeNull();
    }

    $dashboardLink = findNavigationLink($document, 'Dashboard', route('home'));
    expect($dashboardLink)->not->toBeNull()
        ->and(preg_split('/\s+/', $dashboardLink->getAttribute('class')))->toContain('nav-link')
        ->and($dashboardLink->hasAttribute('aria-current'))->toBeFalse();

    $logoutForms = (new DOMXPath($document))->query('//form[@action="'.route('logout').'"]');
    $navigationLandmark = (new DOMXPath($document))->query('//nav[@aria-label="Main navigation"]');
    $mobileToggle = findNavigationElementById($document, 'mobile-navigation-toggle');
    $primaryNavigation = findNavigationElementById($document, 'primary-navigation');
    $accountMenus = (new DOMXPath($document))->query('//details[contains(concat(" ", normalize-space(@class), " "), " account-menu ")]');
    $legacySidebars = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " app-sidebar ")]');
    $legacyProfileMenus = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " profile-menu ")]');

    expect($logoutForms?->length)->toBe(1)
        ->and($logoutForms->item(0)?->getAttribute('method'))->toBe('POST')
        ->and((new DOMXPath($document))->query('//form[@action="'.route('logout').'"]//input[@name="_token"]')?->length)->toBe(1)
        ->and($navigationLandmark?->length)->toBe(1)
        ->and($mobileToggle?->getAttribute('aria-controls'))->toBe('primary-navigation')
        ->and($mobileToggle?->getAttribute('aria-expanded'))->toBe('false')
        ->and($primaryNavigation?->getAttribute('aria-label'))->toBe('Main navigation')
        ->and($accountMenus?->length)->toBe(1)
        ->and($legacySidebars?->length)->toBe(0)
        ->and($legacyProfileMenus?->length)->toBe(0);

    $response
        ->assertSee('@media (max-width: 760px)', false)
        ->assertSee('.app-navigation.mobile-open .primary-navigation', false)
        ->assertSee("event.key !== 'Escape'", false);
});

it('shows event tools to organizers but keeps administration links hidden', function () {
    $this->actingAs(navigationUser('organizer'));

    $response = $this->get(route('events.index'))->assertOk();
    $document = navigationDocument($response->getContent());

    expect(findNavigationLink($document, 'Dashboard', route('home')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Events', route('events.index'))?->getAttribute('aria-current'))->toBe('page')
        ->and(findNavigationLink($document, 'Record Attendance', route('attendance.pin')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Browse Events', route('registrations.public-index')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Accounts', route('accounts.index')))->toBeNull()
        ->and(findNavigationLink($document, 'Roles & Permissions', route('roles.index')))->toBeNull()
        ->and(findNavigationLink($document, 'Event Types', route('event-types.index')))->toBeNull();
});

it('uses custom-role permissions to decide which primary links are visible', function () {
    $role = Role::create([
        'slug' => 'event-viewer',
        'name' => 'Event Viewer',
        'permissions' => ['events.manage'],
        'is_system' => false,
    ]);
    $this->actingAs(navigationUser($role->slug));

    $response = $this->get(route('profile'))->assertOk();
    $document = navigationDocument($response->getContent());

    expect(findNavigationLink($document, 'Events', route('events.index')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Dashboard', route('home')))->toBeNull()
        ->and(findNavigationLink($document, 'Accounts', route('accounts.index')))->toBeNull();
});

it('keeps attendance and browse links for regular users without management links', function () {
    $this->actingAs(navigationUser('user'));

    $response = $this->get(route('profile'))->assertOk();
    $document = navigationDocument($response->getContent());

    expect(findNavigationLink($document, 'Record Attendance', route('attendance.pin')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Browse Events', route('registrations.public-index')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Profile', route('profile')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Appearance', route('settings.edit')))->not->toBeNull()
        ->and(findNavigationLink($document, 'Dashboard', route('home')))->toBeNull()
        ->and(findNavigationLink($document, 'Events', route('events.index')))->toBeNull()
        ->and(findNavigationLink($document, 'Accounts', route('accounts.index')))->toBeNull()
        ->and(findNavigationLink($document, 'Event Types', route('event-types.index')))->toBeNull();
});
