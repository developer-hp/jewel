<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\DashboardData;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardData $data) {}

    public function __invoke(): View
    {
        // Which sections this viewer should get: config order, less anything hidden
        // on Appearance and anything their role cannot reach.
        $sections = AppSetting::current()->visibleDashboardSections();

        return view('dashboard.index', [
            'sections' => $sections,
            // Only the survivors are built, so a section switched off costs nothing.
            // Any that turn out to have nothing to show drop out here too.
            'data' => $this->data->for($sections),
        ]);
    }
}
