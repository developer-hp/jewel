<?php

use App\Models\StockGroup;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The listing-wide delete: a SweetAlert2 confirmation, then a DELETE over ajax,
 * answered as JSON by AnswerAjaxRedirectsWithJson without any controller knowing.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('answers an ajax delete with json instead of a redirect', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->admin)
        ->deleteJson(route('stock-groups.destroy', $group))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'redirect' => route('stock-groups.index'),
        ])
        ->assertJsonPath('message', fn (?string $m) => filled($m));

    expect(StockGroup::find($group->id))->toBeNull();
});

it('reports a refused delete as json with a 422 and the reason', function () {
    // A group holding item groups cannot go; the controller says so by flashing
    // an error and redirecting back, which must reach ajax as a failure.
    $group = StockGroup::create(['name' => 'Studded', 'code' => 'STD']);
    $group->itemGroups()->create(['name' => 'Test Rings', 'prefix' => 'ZZR']);

    $response = $this->actingAs($this->admin)
        ->deleteJson(route('stock-groups.destroy', $group));

    $response->assertStatus(422)->assertJson(['ok' => false]);

    expect($response->json('message'))->not->toBeEmpty()
        ->and(StockGroup::find($group->id))->not->toBeNull();
});

it('does not leave the flash behind for the next page load', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->admin)->deleteJson(route('stock-groups.destroy', $group))->assertOk();

    // Pulled, not read — otherwise the toast would fire a second time on the
    // next ordinary page the user opens.
    $this->actingAs($this->admin)->get(route('stock-groups.index'))
        ->assertOk()
        ->assertSessionMissing('success');
});

it('still redirects a plain form post', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->admin)
        ->delete(route('stock-groups.destroy', $group))
        ->assertRedirect(route('stock-groups.index'))
        ->assertSessionHas('success');
});

it('renders delete actions as ajax triggers rather than forms', function () {
    StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $action = $this->actingAs($this->admin)
        ->getJson(route('stock-groups.index', dtParams(['name', 'code'])))
        ->json('data.0.action');

    expect($action)->toContain('data-delete-url=')
        ->and($action)->toContain('data-delete-confirm=')
        // The old inline confirm() and its form are what this replaces.
        ->and($action)->not->toContain('onsubmit')
        ->and($action)->not->toContain('_method');
});

it('loads sweetalert2 and the toast plugin on every page', function () {
    $this->actingAs($this->admin)->get(route('stock-groups.index'))
        ->assertOk()
        ->assertSee('vendor/sweetalert2/sweetalert2.all.min.js', false)
        ->assertSee('vendor/jquery-toast-plugin/jquery.toast.min.js', false)
        ->assertSee('vendor/jquery-toast-plugin/jquery.toast.min.css', false);
});

it('shows a success flash as a toast rather than an alert bar', function () {
    $response = $this->actingAs($this->admin)
        ->withSession(['success' => 'Stock group saved.'])
        ->get(route('stock-groups.index'));

    $response->assertOk()
        ->assertSee("appToast('success'", false)
        ->assertSee('Stock group saved.', false)
        ->assertDontSee('alert-success', false);
});

it('keeps validation errors in the alert bar where they can be read', function () {
    $this->actingAs($this->admin)->post(route('stock-groups.store'), ['name' => '']);

    $this->actingAs($this->admin)->get(route('stock-groups.index'))
        ->assertOk()
        ->assertSee('Please fix the following:', false);
});
