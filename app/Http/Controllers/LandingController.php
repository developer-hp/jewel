<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\MetalRate;
use App\Models\Purity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The shop's public front page: today's rates, bank details, payment QR and how to
 * get hold of them.
 *
 * The only route in the app with no middleware at all. Everything it shows is
 * configured on the Appearance screen, and it shows nothing that was not.
 */
class LandingController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $settings = AppSetting::current();

        // Off by default. Until the shop fills the page in, `/` behaves as it always
        // has, which is also what keeps the existing authentication test honest.
        if (! $settings->landing_enabled) {
            return redirect()->route(auth()->check() ? 'dashboard' : 'login');
        }

        return view('landing.index', [
            'settings' => $settings,
            'rates' => $this->todaysRates(),
        ]);
    }

    /**
     * The rates the shop has marked for publication and actually entered today.
     *
     * Deliberately *not* Purity::rateOn(), which falls back to the most recent
     * earlier rate — on a public page that would quietly republish yesterday's price
     * as today's. An inner join on today's date means a purity with no rate this
     * morning simply is not in the result.
     *
     * @return array<int, array{label: string, rate: string, per: string}>
     */
    private function todaysRates(): array
    {
        $purities = Purity::query()
            ->active()
            ->where('show_on_landing', true)
            ->whereRelation('metalType', 'is_active', true)
            ->with('metalType')
            ->ordered()
            ->get()
            ->sortBy(fn (Purity $purity) => [$purity->metalType?->sort_order, $purity->sort_order])
            ->values();

        if ($purities->isEmpty()) {
            return [];
        }

        $rates = MetalRate::whereDate('effective_date', today())
            ->whereIn('purity_id', $purities->pluck('id'))
            ->get()
            ->keyBy('purity_id');

        // A single-metal shop reads "24K"; only qualify the label when there is more
        // than one metal on the page to tell apart.
        $qualify = $purities->pluck('metal_type_id')->unique()->count() > 1;

        return $purities
            ->filter(fn (Purity $purity) => $rates->has($purity->id))
            ->map(fn (Purity $purity) => [
                'label' => $qualify ? $purity->label() : $purity->name,
                'rate' => (string) $rates[$purity->id]->rate,
                'per' => (string) $rates[$purity->id]->per_grams,
            ])
            ->values()
            ->all();
    }
}
