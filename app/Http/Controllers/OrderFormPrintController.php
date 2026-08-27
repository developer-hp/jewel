<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\OrderForm;
use App\Support\PdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * The order form itself and the sticker that goes on the bag.
 *
 * Neither stamps anything. The form arrives by POST so the listing can send a set of
 * ticked ids; the sticker also has a by-reference screen, because the counter more
 * often has the paper in hand than the row on screen.
 */
class OrderFormPrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:order_form.print')];
    }

    public function forms(Request $request): Response|RedirectResponse
    {
        $forms = $this->requested($request);

        if ($forms instanceof RedirectResponse) {
            return $forms;
        }

        $settings = AppSetting::current();

        return PdfDocument::stream('order-forms.print', [
            'forms' => $forms,
            'firm' => [
                'query_phone' => (string) ($settings->order_contact_no ?: $settings->firm_phone),
                'website' => (string) ($settings->firm_website ?? ''),
            ],
            'terms' => $this->termLines($settings->order_terms),
            // A string names a stock size; an array is read as explicit
            // [x0, y0, x1, y1] corner coordinates, which is why ['a4'] threw.
        ], 'order-'.now()->format('Y-m-d-His').'.pdf', PdfDocument::a4());
    }

    public function stickers(Request $request): Response|RedirectResponse
    {
        $forms = $this->requested($request);

        if ($forms instanceof RedirectResponse) {
            return $forms;
        }

        return $this->stickerPdf($forms);
    }

    /**
     * The small screen where orders are picked and their stickers print.
     *
     * The screen itself uses a multi-select, so several bags can be labelled in one
     * pass. `ref_no` is still honoured because the counter sometimes has the paper in
     * hand rather than the row on screen, and because a typed reference is a link
     * anyone may have bookmarked.
     */
    public function stickerByRef(Request $request): View|Response|RedirectResponse
    {
        if ($request->filled('ids')) {
            $validated = $request->validate([
                'ids' => ['array', 'min:1', 'max:200'],
                'ids.*' => ['integer', 'exists:order_forms,id'],
            ]);

            $forms = OrderForm::with('lines')
                ->whereIn('id', $validated['ids'])
                ->orderBy('ref_no')
                ->get();

            if ($forms->isEmpty()) {
                return back()->with('error', 'Those orders no longer exist.');
            }

            return $this->stickerPdf($forms);
        }

        if ($request->filled('ref_no')) {
            // Accept "CF 159", "cf159" or plain "159" — whatever is to hand.
            $number = (int) preg_replace('/\D+/', '', $request->string('ref_no')->toString());

            $form = OrderForm::with('lines')->where('ref_no', $number)->first();

            if (! $form) {
                return back()->with('error', 'No order with that reference.');
            }

            return $this->stickerPdf(collect([$form]));
        }

        return view('order-forms.sticker-by-ref', ['prefix' => OrderForm::refPrefix()]);
    }

    /**
     * Orders matching what has been typed, for the sticker screen's picker.
     *
     * Matches on the reference with or without its prefix — "CF 159", "cf159" and
     * "159" all find the same order — and on the customer's name, which is what the
     * counter actually remembers.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        // Read once, outside the mapping: refPrefix() goes to AppSetting, and calling
        // it per row would be twenty settings lookups per keystroke.
        $prefix = OrderForm::refPrefix();

        $digits = preg_replace('/\D+/', '', $term);

        $forms = OrderForm::query()
            ->when($term !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('customer_name', 'like', "%{$term}%")
                ->orWhere('contact_no', 'like', "%{$term}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('ref_no', 'like', "%{$digits}%"))))
            ->orderByDesc('ref_no')
            ->limit(20)
            ->get(['id', 'ref_no', 'customer_name', 'delivery_date']);

        return response()->json([
            'results' => $forms->map(fn (OrderForm $form) => [
                'id' => $form->id,
                'text' => trim($prefix.' '.$form->ref_no).' — '.$form->customer_name
                    .' ('.$form->delivery_date->format('d-m-Y').')',
            ])->all(),
        ]);
    }

    private function stickerPdf($forms): Response
    {
        $filename = 'order-stickers-'.now()->format('Y-m-d-His').'.pdf';

        // More than one sticker goes four-up on plain A4, to be guillotined apart.
        // Matches the repair sticker, which is the same job on the same paper.
        if ($forms->count() > 1) {
            return PdfDocument::stream('order-forms.stickers', [
                'forms' => $forms,
                'columns' => 2,
                'perSheet' => 4,
                'cellHeightMm' => 130,
            ], $filename, PdfDocument::paper('A4', 'P') + PdfDocument::margins(4));
        }

        // A single sticker prints on its own cut-to-size stock: 105 x 160 mm.
        // dompdf wanted that as a box of points; mPDF takes the millimetres directly.
        return PdfDocument::stream('order-forms.stickers', [
            'forms' => $forms,
            'columns' => 1,
            'perSheet' => 1,
            'cellHeightMm' => 152.0,
        ], $filename, PdfDocument::size(105, 160) + PdfDocument::margins(4));
    }

    /**
     * The orders named by the request, ordered as they were entered.
     */
    private function requested(Request $request): mixed
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:order_forms,id'],
        ]);

        // The office copy prints the whole piece — weights, purity, stones, charges
        // and its photo — so the item is loaded in full rather than by two columns.
        $forms = OrderForm::whereIn('id', $validated['ids'])
            ->with([
                'lines.item.purity',
                'lines.item.metalType',
                'lines.item.makingCharge',
                'lines.item.itemStones.stoneMaster',
                'lines.sourceItem.purity',
                'lines.sourceItem.metalType',
                'lines.sourceItem.makingCharge',
                'lines.sourceItem.itemStones.stoneMaster',
            ])
            ->orderBy('ref_no')
            ->get();

        if ($forms->isEmpty()) {
            return back()->with('error', 'Those orders no longer exist.');
        }

        return $forms;
    }

    /**
     * The terms block, one printed line per line of the setting.
     *
     * @return array<int, string>
     */
    private function termLines(?string $terms): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $terms))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
