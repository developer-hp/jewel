<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\DashboardData;
use App\Support\CommandPalette;
use Illuminate\View\View;

/**
 * The landing page after signing in.
 *
 * Two quite different pages behind one route. Someone with `dashboard.view` gets the
 * overview — the figures. Someone without it gets the jump-to menu instead, rather
 * than a 403: this is where every sign-in lands, and a landing page that only says
 * "forbidden" leaves a counter clerk with nowhere to go.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardData $data) {}

    public function __invoke(): View
    {
        // The figures are the thing being gated, not the page. Checked before any of
        // them are built, so a viewer who cannot see them costs no queries at all.
        if (! auth()->user()?->can('dashboard.view')) {
            return view('dashboard.index', [
                'overview' => false,
                'groups' => CommandPalette::groups(),
            ]);
        }

        // Which sections this viewer should get: config order, less anything hidden
        // on Appearance and anything their role cannot reach.
        $sections = AppSetting::current()->visibleDashboardSections();

        return view('dashboard.index', [
            'overview' => true,
            'sections' => $sections,
            // Only the survivors are built, so a section switched off costs nothing.
            // Any that turn out to have nothing to show drop out here too.
            'data' => $this->data->for($sections),
        ]);
    }
}
