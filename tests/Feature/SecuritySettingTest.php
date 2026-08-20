<?php

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * @return array<string, mixed>
 */
function securityPayload(array $overrides = []): array
{
    return array_merge([
        'single_device_login' => '1',
        'idle_timeout_minutes' => 20,
        'idle_warning_seconds' => 60,
    ], $overrides);
}

it('renders the security screen', function () {
    $this->actingAs($this->admin)->get(route('security-settings.edit'))
        ->assertOk()
        ->assertSee('Single Device Sign-in')
        ->assertSee('Idle Timeout');
});

it('saves the settings', function () {
    $this->actingAs($this->admin)->put(route('security-settings.update'), securityPayload())
        ->assertRedirect(route('security-settings.edit'));

    $settings = AppSetting::current();

    expect($settings->single_device_login)->toBeTrue()
        ->and($settings->idle_timeout_minutes)->toBe(20)
        ->and($settings->idle_warning_seconds)->toBe(60)
        ->and($settings->idleTimeoutSeconds())->toBe(1200);
});

it('treats zero minutes as off', function () {
    $this->actingAs($this->admin)->put(route('security-settings.update'), securityPayload(['idle_timeout_minutes' => 0]))
        ->assertRedirect();

    expect(AppSetting::current()->idleTimeoutEnabled())->toBeFalse();
});

it('unticking single device saves as false', function () {
    AppSetting::current()->update(['single_device_login' => true]);

    $this->actingAs($this->admin)->put(route('security-settings.update'), securityPayload(['single_device_login' => '0']))
        ->assertRedirect();

    expect(AppSetting::current()->single_device_login)->toBeFalse();
});

it('rejects a warning that is not shorter than the timeout', function () {
    $this->actingAs($this->admin)
        ->put(route('security-settings.update'), securityPayload(['idle_timeout_minutes' => 1, 'idle_warning_seconds' => 60]))
        ->assertSessionHasErrors('idle_warning_seconds');

    $this->actingAs($this->admin)
        ->put(route('security-settings.update'), securityPayload(['idle_timeout_minutes' => 2, 'idle_warning_seconds' => 60]))
        ->assertSessionHasNoErrors();
});

it('rejects out-of-range values', function () {
    $this->actingAs($this->admin)
        ->put(route('security-settings.update'), securityPayload(['idle_timeout_minutes' => 5000]))
        ->assertSessionHasErrors('idle_timeout_minutes');

    $this->actingAs($this->admin)
        ->put(route('security-settings.update'), securityPayload(['idle_warning_seconds' => 5]))
        ->assertSessionHasErrors('idle_warning_seconds');
});

it('warns when the session driver cannot support single device', function () {
    config(['session.driver' => 'file']);

    $this->actingAs($this->admin)->get(route('security-settings.edit'))
        ->assertOk()
        ->assertSee('needs');
});

it('gates the screen by permission', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('security-settings.edit'))->assertOk();
    $this->actingAs($sales)->put(route('security-settings.update'), securityPayload())->assertForbidden();

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get(route('security-settings.edit'))->assertForbidden();
});

it('links security from the sidebar', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('security-settings.edit'), false);
});
