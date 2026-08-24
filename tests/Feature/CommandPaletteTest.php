<?php

use App\Models\User;
use App\Support\CommandPalette;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Ctrl+M — the jump-to menu, built from the same config/menu.php as the sidebar.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');
});

it('renders the palette and its shortcut on every page', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="commandPalette"', false)
        ->assertSee('id="commandPaletteFilter"', false)
        ->assertSee('data-command-palette-open', false)
        // The keys are printed on the trigger, so the shortcut is discoverable.
        ->assertSee('Ctrl + M');
});

it('gives every sidebar group a heading of its own', function () {
    $this->actingAs($this->admin);

    $titles = collect(CommandPalette::groups())->pluck('title');

    // Sections lead with their loose links; then every group, flattened one level
    // so nothing in the palette has to be expanded.
    expect($titles)->toContain('Main', 'Stock', 'Orders', 'Estimates', 'Repairs',
        'Dispatch', 'Suppliers', 'Masters', 'Settings', 'Administration')
        // Manage holds nothing but groups, so it contributes no heading of its own.
        ->and($titles)->not->toContain('Manage');
});

it('puts a section\'s loose links ahead of that section\'s groups', function () {
    $this->actingAs($this->admin);

    $titles = collect(CommandPalette::groups())->pluck('title')->all();

    // Dashboard and Daily Rates sit under Main, above the Stock group they feed.
    expect(array_search('Main', $titles, true))->toBeLessThan(array_search('Stock', $titles, true))
        ->and(array_search('Suppliers', $titles, true))->toBeLessThan(array_search('Masters', $titles, true));
});

it('lists every entry with a url, an icon and its group as the hint', function () {
    $this->actingAs($this->admin);

    $stock = collect(CommandPalette::groups())->firstWhere('title', 'Stock');

    $items = collect($stock['items']);

    expect($items->pluck('label'))->toContain('Items', 'Item Photos', 'Internal Stock')
        ->and($items->pluck('hint')->unique()->all())->toBe(['Stock'])
        ->and($items->pluck('url'))->toContain(route('items.index'))
        // Children carry no icon of their own, so they borrow the group's.
        ->and($items->pluck('icon')->unique()->all())->toBe(['ri-price-tag-3-fill']);
});

it('inherits the sidebar permission filtering', function () {
    $labels = fn () => collect(CommandPalette::groups())
        ->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))
        ->all();

    $this->actingAs($this->admin);
    expect($labels())->toContain('Users');

    // Sales cannot reach user administration, so the palette must not offer it —
    // the point of building on SidebarMenu rather than on config directly.
    $this->actingAs($this->sales);
    expect($labels())->not->toContain('Users');
});

it('drops a group whose every entry is out of reach', function () {
    $this->actingAs($this->sales);

    $titles = collect(CommandPalette::groups())->pluck('title')->all();

    expect($titles)->not->toContain('Administration');
});

it('marks the page you are already on', function () {
    $html = $this->actingAs($this->admin)->get(route('items.index'))->getContent();

    expect($html)->toContain('is-current');
});

it('exposes searchable text for the filter', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        // Lower-cased label plus group, so typing "stock items" or "masters"
        // narrows without the JS having to walk the DOM text.
        ->assertSee('data-palette-text="items stock"', false);
});
