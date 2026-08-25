<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\RepairForm;
use App\Support\PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The two printed outputs: the repair form itself — customer copy and office copy
 * side by side — and the sticker that goes on the bag.
 *
 * Both arrive by POST because the listing sends a set of ticked ids that would not
 * survive a query string. Neither stamps anything.
 */
class RepairFormPrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:repair_form.print')];
    }

    public function forms(Request $request): Response|RedirectResponse
    {
        $forms = $this->requested($request);

        if ($forms instanceof RedirectResponse) {
            return $forms;
        }

        $settings = AppSetting::current();

        return PdfDocument::stream('repair-forms.print', [
            'forms' => $forms,
            'firm' => [
                'phone' => (string) ($settings->repair_contact_no ?: $settings->firm_phone),
                'office_phone' => (string) ($settings->firm_office_phone ?: ($settings->repair_contact_no ?: $settings->firm_phone)),
                'website' => (string) ($settings->firm_website ?? ''),
            ],
            // Kept as settings so the shop edits its own terms without a deploy.
            'terms' => $this->termLines($settings->repair_terms),
            // Landscape: the customer and office copies sit side by side, and
            // portrait squeezes both into unreadable columns.
        ], 'repair-'.now()->format('Y-m-d-His').'.pdf', PdfDocument::paper('A3', 'L'));
    }

    public function stickers(Request $request): Response|RedirectResponse
    {
        $forms = $this->requested($request);

        if ($forms instanceof RedirectResponse) {
            return $forms;
        }

        // 105 x 160 mm. dompdf wanted that as a box of points; mPDF takes the
        // millimetres directly.
        return PdfDocument::stream('repair-forms.stickers', ['forms' => $forms],
            'repair-stickers-'.now()->format('Y-m-d-His').'.pdf', PdfDocument::size(105, 160));
    }

    /**
     * The forms named by the request, ordered as they were entered.
     */
    private function requested(Request $request): mixed
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:repair_forms,id'],
        ]);

        $forms = RepairForm::whereIn('id', $validated['ids'])
            ->with(['lines', 'salesPersons'])
            ->orderBy('ref_no')
            ->get();

        if ($forms->isEmpty()) {
            return back()->with('error', 'Those repair forms no longer exist.');
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
