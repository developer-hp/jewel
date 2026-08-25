<?php

use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * Every entry in config/menu.php, groups flattened into their children.
 *
 * @return array<int, array<string, mixed>>
 */
function menuLeaves(): array
{
    return collect(config('menu'))
        ->flatMap(fn (array $section) => $section['items'])
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->all();
}

it('offers the account shortcuts in the topbar', function () {
    // These live in the topbar dropdown, not the side menu — the sidebar's own
    // account block was removed when the menu became data-driven.
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

// --- the config itself ------------------------------------------------------------

it('names a route that exists on every menu entry', function () {
    $broken = collect(menuLeaves())
        ->reject(fn (array $item) => Route::has($item['route']))
        ->pluck('route')
        ->all();

    expect($broken)->toBe([]);
});

it('guards every menu entry with a permission that is actually seeded', function () {
    $seeded = Permission::pluck('name')->all();

    $unknown = collect(menuLeaves())
        ->pluck('can')
        ->filter()
        ->unique()
        ->reject(fn (string $permission) => in_array($permission, $seeded, true))
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

it('gives every collapsible group an icon and a leaf none', function () {
    foreach (config('menu') as $section) {
        foreach ($section['items'] as $item) {
            expect($item['icon'] ?? null)->not->toBeNull("{$item['label']} has no icon");

            // Children render as plain text in the second level, so an icon there
            // would simply never be drawn.
            foreach ($item['children'] ?? [] as $child) {
                expect($child['icon'] ?? null)->toBeNull("{$child['label']} carries an unused icon");
            }
        }
    }
});

// --- grouping ---------------------------------------------------------------------

it('groups the operational screens rather than listing them flat', function () {
    $groups = collect(config('menu'))
        ->firstWhere('title', 'Main')['items'];

    $byLabel = collect($groups)->keyBy('label');

    // Stock is where new screens keep landing, so this asserts what must be in it
    // rather than pinning the exact list — twice now the strict version has failed
    // for nothing worse than the group growing. Repairs and Dispatch are settled,
    // so those stay exact.
    expect($byLabel->get('Stock')['children'] ?? null)->not->toBeNull()
        ->and(collect($byLabel['Stock']['children'])->pluck('label')->all())->toContain('Items', 'Item Lots')
        ->and(collect($byLabel['Repairs']['children'])->pluck('label')->all())->toBe(['Repair Forms', 'Repair Items'])
        ->and(collect($byLabel['Dispatch']['children'])->pluck('label')->all())->toBe(['Angadiya', 'Hallmark'])
        ->and(collect($byLabel['Estimates']['children'])->pluck('label')->all())->toBe(['OG Estimate', 'Item Estimate', 'Voucher']);
});

it('opens only the group holding the page being viewed', function (string $route, string $open) {
    $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

    $id = 'menu-'.Str::slug($open);

    expect($html)->toContain('<div class="collapse show" id="'.$id.'"');

    foreach ([
        'Stock', 'Orders', 'Estimates', 'Repairs', 'Dispatch', 'Suppliers',
        'Masters', 'Settings', 'Administration',
    ] as $label) {
        if ($label === $open) {
            continue;
        }

        expect($html)->toContain('<div class="collapse " id="menu-'.Str::slug($label).'"');
    }
})->with([
    ['items.index', 'Stock'],
    ['order-forms.index', 'Orders'],
    ['og-estimates.index', 'Estimates'],
    ['item-estimates.index', 'Estimates'],
    ['vouchers.index', 'Estimates'],
    ['lots.index', 'Stock'],
    ['repair-forms.index', 'Repairs'],
    ['angadiyas.index', 'Dispatch'],
    ['hallmarks.index', 'Dispatch'],
    ['suppliers.index', 'Masters'],
    ['users.index', 'Administration'],
]);

it('leaves every group closed on a page that belongs to none of them', function () {
    $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('class="collapse show"');
});

// --- what each role sees -----------------------------------------------------------

it('links every masters entry an admin can reach', function () {
    $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

    foreach ([
        'rates.today', 'metal-types.index', 'purities.index', 'item-groups.index',
        'stock-groups.index', 'stones.index', 'diamonds.index', 'making-charges.index',
        'suppliers.index', 'customers.index', 'sales-persons.index',
        // Label templates are a listing now, not a single row, so the menu points
        // at the index — a parameterless route() on .edit would throw site-wide.
        'app-settings.edit', 'label-settings.index',
    ] as $name) {
        $response->assertSee(route($name), false);
    }
});

/*
 * These assert on markup only the menu can emit — the group ids from
 * SidebarMenu::group() and the section-title element — rather than on bare labels.
 * The dashboard's own copy says "Stone & Diamond Masters" and "Masters and the item
 * register are live", so assertDontSee('Masters') matches page content the test was
 * never aimed at, and assertSee('Masters') would pass with the menu wholly broken.
 */

it('shows a sales user the masters but not administration', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="menu-masters"', false)
        ->assertSee(route('suppliers.index'), false)
        ->assertDontSee('id="menu-administration"', false)
        ->assertDontSee(route('users.index'), false)
        ->assertDontSee(route('roles.index'), false);
});

it('drops a section entirely when the user can reach none of it', function () {
    $none = User::factory()->create();

    $this->actingAs($none)->get(route('dashboard'))
        ->assertOk()
        // Dashboard carries no permission, so Main survives; Manage should not.
        ->assertSee('<li class="side-nav-title mt-1">Main</li>', false)
        ->assertSee(route('dashboard'), false)
        ->assertDontSee('<li class="side-nav-title mt-1">Manage</li>', false)
        ->assertDontSee('id="menu-masters"', false)
        ->assertDontSee('id="menu-settings"', false)
        ->assertDontSee('id="menu-administration"', false);
});
