<?php

use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('offers the account shortcuts in the side menu', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Account')
        ->assertSee('Change Password')
        ->assertSee('Logout')
        ->assertSee(route('profile.edit').'#change-password', false);
});

it('posts the logout rather than linking to it', function () {
    // The sidebar link submits the topbar's form; a plain GET would 405.
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee("document.getElementById('logout-form').submit()", false)
        ->assertSee('id="logout-form"', false);
});

it('shows every sidebar icon from an icon set the theme actually ships', function () {
    $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();
    $css = file_get_contents(public_path('theme/assets/css/icons.min.css'));

    preg_match_all('/class="(ri-[a-z0-9-]+)"/', $html, $matches);

    expect($matches[1])->not->toBeEmpty();

    $missing = collect($matches[1])->unique()
        // The Remix build bundled with this theme has no rupee icons, which is how
        // the Daily Rates entry ended up rendering a blank square.
        ->reject(fn (string $icon) => str_contains($css, ".{$icon}:"))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('links every masters entry an admin can reach', function () {
    $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

    foreach ([
        'rates.today', 'metal-types.index', 'purities.index', 'item-groups.index',
        'stones.index', 'diamonds.index', 'making-charges.index', 'suppliers.index',
        'app-settings.edit', 'label-settings.edit',
    ] as $name) {
        $response->assertSee(route($name), false);
    }
});
