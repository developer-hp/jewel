<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ItemEstimate;
use App\Models\OgEstimate;
use App\Models\Voucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The two printed documents.
 *
 * POST because the listings send a set of ticked ids that would not survive a query
 * string. Neither stamps anything.
 */
class EstimatePrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:og_estimate.print', only: ['estimates']),
            new Middleware('permission:voucher.print', only: ['vouchers']),
            new Middleware('permission:item_estimate.print', only: ['itemEstimates']),
        ];
    }

    public function estimates(Request $request): Response|RedirectResponse
    {
        $estimates = $this->requested($request, OgEstimate::query()->with(['lines', 'orderForm']), 'og_estimates');

        if ($estimates instanceof RedirectResponse) {
            return $estimates;
        }

        $settings = AppSetting::current();

        return Pdf::loadView('og-estimates.print', [
            'estimates' => $estimates,
            'firm' => [
                'name' => (string) ($settings->firm_name ?? ''),
                'phone' => (string) ($settings->firm_phone ?? ''),
            ],
        ])->setPaper('a4', 'portrait')
            ->stream('og-estimate-'.now()->format('Y-m-d-His').'.pdf', ['Attachment' => false]);
    }

    public function itemEstimates(Request $request): Response|RedirectResponse
    {
        $estimates = $this->requested(
            $request,
            // ogEstimate.lines feeds the attached page; without it that page would
            // query per estimate.
            ItemEstimate::query()->with(['lines.stones', 'lines.item', 'ogEstimate.lines']),
            'item_estimates',
        );

        if ($estimates instanceof RedirectResponse) {
            return $estimates;
        }

        $settings = AppSetting::current();

        return Pdf::loadView('item-estimates.print', [
            'estimates' => $estimates,
            'firm' => [
                'name' => (string) ($settings->firm_name ?? ''),
                'phone' => (string) ($settings->firm_phone ?? ''),
            ],
        ])->setPaper('a4', 'portrait')
            ->stream('item-estimate-'.now()->format('Y-m-d-His').'.pdf', ['Attachment' => false]);
    }

    public function vouchers(Request $request): Response|RedirectResponse
    {
        $vouchers = $this->requested($request, Voucher::query()->with(['orderForm.lines.purity']), 'vouchers');

        if ($vouchers instanceof RedirectResponse) {
            return $vouchers;
        }

        return Pdf::loadView('vouchers.print', ['vouchers' => $vouchers])
            ->setPaper('a4', 'portrait')
            ->stream('voucher-'.now()->format('Y-m-d-His').'.pdf', ['Attachment' => false]);
    }

    /**
     * The records named by the request, in the order they were entered.
     */
    private function requested(Request $request, Builder $query, string $table): mixed
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', "exists:{$table},id"],
        ]);

        $records = $query->whereIn('id', $validated['ids'])->orderBy('ref_no')->get();

        if ($records->isEmpty()) {
            return back()->with('error', 'Those records no longer exist.');
        }

        return $records;
    }
}
