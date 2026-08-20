<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierHisabPaymentRequest;
use App\Models\SupplierHisab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Settling a hisab: the gold rows handed over against what the supplier is owed.
 *
 * Whatever fine is left once these rows are counted becomes cash at the day's rate,
 * which is why saving here also snapshots the rate.
 */
class SupplierHisabPaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:supplier_hisab.view', only: ['edit']),
            new Middleware('permission:supplier_hisab.edit', only: ['update']),
        ];
    }

    public function edit(SupplierHisab $hisab): View
    {
        return view('supplier-hisabs.payment', [
            'hisab' => $hisab,
            'rows' => $hisab->payments()->get(),
            'ratePerGram' => $hisab->ratePerGram(),
        ]);
    }

    public function update(SupplierHisabPaymentRequest $request, SupplierHisab $hisab): RedirectResponse
    {
        $rows = $request->validated()['rows'];

        DB::transaction(function () use ($hisab, $rows) {
            // Replaced wholesale rather than diffed — the rows carry no outside
            // references, exactly as the hallmark lines do.
            $hisab->payments()->delete();
            $hisab->payments()->createMany($rows);

            // The rate is pinned at settlement, so changing today's rate afterwards
            // cannot rewrite a slip that has already printed.
            $hisab->forceFill([
                'rate_per_gram' => SupplierHisab::currentRatePerGram(),
                'settled_at' => now(),
            ])->save();
        });

        return redirect()->route('supplier-hisabs.index', ['date' => $hisab->hisab_date->toDateString()])
            ->with('success', "Hisab for \"{$hisab->supplier_label}\" has been settled.");
    }
}
