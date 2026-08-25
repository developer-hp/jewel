<?php

namespace App\Http\Controllers;

use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Services\StockFigures;
use App\Support\PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * How stock moved on one day: opening, what came in, what went, and what is left.
 */
class StockReportController extends Controller implements HasMiddleware
{
    public function __construct(private readonly StockFigures $figures) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:stock.report')];
    }

    public function index(Request $request): View
    {
        return view('stock.daily', $this->sheet($request));
    }

    public function export(Request $request): Response
    {
        $data = $this->sheet($request);

        return PdfDocument::stream('stock.daily-export', $data, 'stock-daily-'.$data['date']->format('Y-m-d').'.pdf', PdfDocument::a4('L'));
    }

    /**
     * Choose which item groups the report shows.
     *
     * Saved on the groups themselves, so it holds for everyone and for every day
     * until it is changed again — not a per-user or per-visit preference.
     */
    public function updateGroups(Request $request): RedirectResponse
    {
        // The form posts one empty value so "none ticked" still arrives as an array
        // rather than as nothing at all; drop it before the integer rule sees it.
        $request->merge([
            'item_group_ids' => array_values(array_filter(
                (array) $request->input('item_group_ids', []),
                fn ($id) => $id !== '' && $id !== null,
            )),
        ]);

        $validated = $request->validate([
            'item_group_ids' => ['present', 'array'],
            'item_group_ids.*' => ['integer', 'exists:item_groups,id'],
        ]);

        $chosen = $validated['item_group_ids'];

        // Both directions in two statements rather than a write per group.
        ItemGroup::whereIn('id', $chosen)->update(['show_in_daily_report' => true]);
        ItemGroup::whereNotIn('id', $chosen ?: [0])->update(['show_in_daily_report' => false]);

        $hidden = ItemGroup::active()->where('show_in_daily_report', false)->count();

        return back()->with('success', $hidden === 0
            ? 'The report now shows every item group.'
            : "The report now hides {$hidden} item ".str('group')->plural($hidden).'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sheet(Request $request): array
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();

        $metalTypeId = $request->integer('metal_type_id') ?: null;

        $rows = $this->figures->daily($date, $metalTypeId);

        return [
            'date' => $date,
            // Every active group, ticked or not, so the panel can offer them all.
            'allGroups' => ItemGroup::active()->ordered()->get(),
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
            'metalTypeId' => $metalTypeId,
            'metalTypeName' => $metalTypeId ? MetalType::find($metalTypeId)?->name : null,
            'rows' => $rows,
            'totals' => $this->figures->totals($rows, [
                'opening_pcs', 'opening_wt', 'add_pcs', 'add_wt',
                'less_pcs', 'less_wt', 'closing_pcs', 'closing_wt',
            ]),
        ];
    }
}
