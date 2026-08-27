<?php

use App\Models\Angadiya;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * A saved slip. Named distinctly because Pest's global helpers share one namespace
 * across the whole suite, and a redeclaration is a fatal rather than a failure.
 */
function angadiyaSlipFixture(array $overrides = []): Angadiya
{
    return Angadiya::create(array_merge([
        'angadiya_date' => now()->toDateString(),
        'name' => 'S.PAL',
        'city' => 'Surat',
        'mobile' => '7567280980',
        'insurance_amount' => 120000,
        'remark' => 'chain 18 k 6.280',
    ], $overrides));
}

it('prints the despatch list as a pdf', function () {
    $a = angadiyaSlipFixture();
    $b = angadiyaSlipFixture(['name' => 'Yamuna gold', 'city' => 'Rajkot', 'insurance_amount' => 105000]);

    $response = $this->actingAs($this->admin)
        ->post(route('angadiyas.print-list'), ['ids' => [$a->id, $b->id]]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('renders every column the list is asked for', function () {
    $slip = angadiyaSlipFixture(['remark' => 'set 22k 31.580']);

    $html = view('angadiyas.list', ['slips' => collect([$slip])])->render();

    expect($html)
        ->toContain('Angadiya')
        ->toContain(now()->format('d/m/Y'))
        ->toContain('Surat')
        ->toContain('S.PAL')
        ->toContain('7567280980')
        ->toContain('120,000')
        ->toContain('set 22k 31.580');
});

// A list is a manifest, not the slip that travels with the goods. Reprinting it to
// check something off must not look like the slips themselves went out again.
it('does not stamp the slips as printed', function () {
    $slip = angadiyaSlipFixture();

    $this->actingAs($this->admin)
        ->post(route('angadiyas.print-list'), ['ids' => [$slip->id]])
        ->assertOk();

    expect($slip->fresh()->printed_at)->toBeNull();
});

it('requires at least one existing slip', function () {
    $this->actingAs($this->admin)
        ->post(route('angadiyas.print-list'), ['ids' => []])
        ->assertSessionHasErrors('ids');

    $this->actingAs($this->admin)
        ->post(route('angadiyas.print-list'), ['ids' => [9999]])
        ->assertSessionHasErrors('ids.0');
});

it('refuses a user without the print permission', function () {
    $slip = angadiyaSlipFixture();

    $nobody = User::factory()->create();

    $this->actingAs($nobody)
        ->post(route('angadiyas.print-list'), ['ids' => [$slip->id]])
        ->assertForbidden();
});
