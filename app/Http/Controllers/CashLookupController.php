<?php

namespace App\Http\Controllers;

use App\Models\ItemEstimate;
use App\Models\OgEstimate;
use App\Models\Voucher;
use App\Services\CashMath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * What a cash entry may be booked against — the documents nobody has settled yet.
 *
 * Two endpoints rather than one with a switch: they feed two different pickers, carry
 * different figures, and one has to merge two tables.
 *
 * Gated as tightly as the screen they serve: a list of every reference, customer name
 * and amount in the shop is not public to anyone who happens to be signed in.
 */
class CashLookupController extends Controller implements HasMiddleware
{
    private const LIMIT = 20;

    public function __construct(private readonly CashMath $math) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:cash_entry.create|cash_entry.edit')];
    }

    /**
     * Item estimates and vouchers, merged, newest first.
     */
    public function documents(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        // lines.stones is not optional: summary() walks every line and every line's
        // stones, so twenty results without it is forty-one queries per keystroke.
        $estimates = ItemEstimate::query()
            ->with('lines.stones')
            ->whereDoesntHave('cashEntry')
            ->when($term !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('ref_no', 'like', '%'.$this->bareNumber($term, ItemEstimate::refPrefix()).'%')
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->orderByDesc('ref_no')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ItemEstimate $estimate) => $this->document(
                'estimate', $estimate->id, $estimate->reference(), $estimate->customer_name,
                $estimate->estimate_date, $this->math->finalAmount($estimate),
            ));

        $vouchers = Voucher::query()
            ->whereDoesntHave('cashEntry')
            ->when($term !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('ref_no', 'like', '%'.$this->bareNumber($term, Voucher::refPrefix()).'%')
                ->orWhere('description', 'like', "%{$term}%")))
            ->orderByDesc('ref_no')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Voucher $voucher) => $this->document(
                'voucher', $voucher->id, $voucher->reference(), $voucher->description,
                $voucher->voucher_date, $this->math->finalAmount($voucher),
            ));

        return response()->json([
            'documents' => $estimates->concat($vouchers)
                ->sortByDesc('date')
                ->take(self::LIMIT)
                ->values()
                ->all(),
        ]);
    }

    /**
     * OG estimates, for the gold the customer is handing over.
     */
    public function ogEstimates(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        $estimates = OgEstimate::query()
            // totals() walks the lines, same reason as above.
            ->with('lines')
            ->whereDoesntHave('cashEntry')
            ->when($term !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('ref_no', 'like', '%'.$this->bareNumber($term, OgEstimate::refPrefix()).'%')
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->orderByDesc('ref_no')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'ogEstimates' => $estimates->map(function (OgEstimate $estimate) {
                $gold = $this->math->goldFigures($estimate);

                return [
                    'id' => $estimate->id,
                    'reference' => $estimate->reference(),
                    'party' => $estimate->customer_name,
                    'gold_weight' => $gold['weight'],
                    'gold_amount' => $gold['amount'],
                    'text' => trim($estimate->reference().' — '.$estimate->customer_name)
                        .' — '.number_format($gold['weight'], 3).' g',
                ];
            })->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(string $kind, int $id, string $reference, ?string $party, $date, float $final): array
    {
        return [
            // The composite the form posts, so one control carries both columns.
            'id' => $kind.':'.$id,
            'kind' => $kind,
            'reference' => $reference,
            'party' => $party,
            'date' => $date?->format('d-m-Y'),
            'final_amount' => $final,
            'text' => trim($reference.' — '.($party ?: '—')).' — '.number_format($final, 2),
        ];
    }

    /**
     * "ES 21" and "21" should both find estimate 21 — the same prefix-stripping
     * VoucherController::data() does when filtering its reference column.
     */
    private function bareNumber(string $term, string $prefix): string
    {
        return trim($term, $prefix.' ');
    }
}
