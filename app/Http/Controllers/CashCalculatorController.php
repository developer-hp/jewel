<?php

namespace App\Http\Controllers;

use App\Models\CashCalculator;
use App\Services\CashMath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The till calculator behind the topbar button.
 *
 * Two jobs: hand back what the books say should be in the drawers, and remember what
 * this user last counted. The difference between the two is the whole point of the
 * screen, and it is worked out on the client from figures this endpoint supplies —
 * nothing derived is stored.
 *
 * Gated on cash_entry.view: the drawer position is the shop's money, and someone who
 * cannot open the cash listing should not read it out of a modal instead.
 */
class CashCalculatorController extends Controller implements HasMiddleware
{
    public function __construct(private readonly CashMath $math) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:cash_entry.view')];
    }

    /**
     * The drawer position and this user's saved count.
     */
    public function show(Request $request): JsonResponse
    {
        $position = $this->math->position();

        $saved = CashCalculator::where('user_id', $request->user()->id)->first();
        $grid = $saved?->grid() ?? CashCalculator::normalise([]);

        return response()->json([
            'expected' => [
                'cash' => $position->cash,
                'gold' => $position->gold,
            ],
            'counts' => $grid,
            'totals' => CashCalculator::totals($grid),
            'saved_at' => $saved?->updated_at?->format('d-m-Y H:i'),
        ]);
    }

    /**
     * Replace this user's saved count.
     *
     * One row per user, replaced in place rather than accumulated. The payload is
     * normalised rather than trusted, so a stale denomination posted by an old tab
     * cannot survive into a total.
     *
     * Written with forceFill rather than updateOrCreate: updateOrCreate mass-assigns
     * its match array, and user_id is guarded precisely so a request cannot set it —
     * so it would be dropped on insert and the row would fail its NOT NULL. Guarded
     * and explicit is the right pair here; fillable and convenient is not.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['array'],
            'counts.*.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $grid = CashCalculator::normalise($request->input('counts', []));
        $userId = $request->user()->id;

        $saved = CashCalculator::where('user_id', $userId)->first() ?? new CashCalculator;

        // From the session, never the request body.
        $saved->forceFill(['user_id' => $userId, 'counts' => $grid])->save();

        return response()->json([
            'counts' => $grid,
            'totals' => CashCalculator::totals($grid),
            'saved_at' => $saved->updated_at->format('d-m-Y H:i'),
        ]);
    }
}
