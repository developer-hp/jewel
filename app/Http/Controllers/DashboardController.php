<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Purity;
use App\Models\StoneMaster;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'itemCount' => Item::count(),
            'netWeight' => (float) Item::active()->sum('net_weight'),
            'stoneCount' => StoneMaster::count(),
            // Purities still missing today's rate — the first thing to fix each morning.
            'puritiesWithoutRate' => Purity::active()
                ->whereDoesntHave('rates', fn ($q) => $q->whereDate('effective_date', today()))
                ->count(),
            'ratedToday' => Purity::active()
                ->whereHas('rates', fn ($q) => $q->whereDate('effective_date', today()))
                ->count(),
        ]);
    }
}
