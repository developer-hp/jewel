<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashEntryRequest;
use App\Models\AppSetting;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\ItemEstimate;
use App\Services\CashMath;
use App\Support\PdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CashEntryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly CashMath $math) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:cash_entry.view', only: ['index', 'export']),
            new Middleware('permission:cash_entry.create', only: ['create', 'store']),
            new Middleware('permission:cash_entry.edit', only: ['edit', 'update']),
            new Middleware('permission:cash_entry.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('cash-entries.index', [
            // What should be in the tills, and the gold that came over the counter.
            'position' => $this->math->position(),
        ]);
    }

    private function data(): JsonResponse
    {
        // Read once, outside the closures: refPrefix() reads the settings row, and
        // calling it per row is a query per row.
        $prefix = CashEntry::refPrefix();

        $query = CashEntry::query()->select('cash_entries.*')->with('drawer:id,name');

        return DataTables::eloquent($query)
            ->addColumn('ref', fn (CashEntry $entry) => '<strong>'.e(trim($prefix.' '.$entry->ref_no)).'</strong>')
            ->editColumn('entry_date', fn (CashEntry $entry) => $entry->entry_date->format('d-m-Y'))
            ->addColumn('drawer_name', fn (CashEntry $entry) => e($entry->drawer?->name ?? '—'))
            // From the snapshots, so the listing joins nothing and loads no estimates.
            ->addColumn('document', fn (CashEntry $entry) => '<strong>'.e($entry->document_reference).'</strong>'
                .($entry->party_name ? '<div class="text-muted fs-12">'.e($entry->party_name).'</div>' : ''))
            ->addColumn('event', fn (CashEntry $entry) => view('components.status-badge', [
                'active' => $entry->cash_event === CashEntry::EVENT_IN,
                'labels' => ['IN', 'OUT'],
            ])->render())
            ->editColumn('cash_amount', fn (CashEntry $entry) => number_format((float) $entry->cash_amount, 2))
            ->editColumn('cheque_amount', fn (CashEntry $entry) => number_format((float) $entry->cheque_amount, 2))
            ->editColumn('gold_amount', fn (CashEntry $entry) => number_format((float) $entry->gold_amount, 2))
            ->editColumn('final_amount', fn (CashEntry $entry) => number_format((float) $entry->final_amount, 2))
            // Touches only stored columns — no relation loads, no N+1. That is what
            // snapshotting final_amount buys.
            ->addColumn('discount', fn (CashEntry $entry) => number_format($entry->discount(), 2))
            ->addColumn('action', fn (CashEntry $entry) => view('cash-entries.partials.actions', compact('entry'))->render())
            ->filterColumn('ref', fn ($q, $keyword) => $q->where('ref_no', 'like', '%'.trim($keyword, $prefix.' ').'%'))
            ->filterColumn('document', fn ($q, $keyword) => $q->where(fn ($sub) => $sub
                ->where('document_reference', 'like', "%{$keyword}%")
                ->orWhere('party_name', 'like', "%{$keyword}%")))
            ->orderColumn('ref', 'ref_no $1')
            ->rawColumns(['ref', 'document', 'event', 'action'])
            ->toJson();
    }

    /**
     * The whole ledger as a PDF, in the order the listing shows it.
     *
     * Reads the snapshots, so it neither joins to the documents nor recomputes
     * anything — an export of a hundred entries is one query.
     */
    public function export(): Response
    {
        $entries = CashEntry::query()->with('drawer:id,name')->orderBy('ref_no')->get();

        return PdfDocument::stream('cash-entries.export', [
            'entries' => $entries,
            'position' => $this->math->position(),
            'totals' => (object) [
                'in' => round($entries->where('cash_event', CashEntry::EVENT_IN)->sum(fn (CashEntry $e) => $e->settledAmount()), 2),
                'out' => round($entries->where('cash_event', CashEntry::EVENT_OUT)->sum(fn (CashEntry $e) => $e->settledAmount()), 2),
                'gold' => round((float) $entries->sum('gold_weight'), 3),
            ],
        ], 'cash-'.now()->format('Y-m-d').'.pdf', PdfDocument::a4());
    }

    public function create(): View
    {
        return view('cash-entries.create', $this->formData() + [
            'entry' => new CashEntry(['entry_date' => today()]),
            'nextRef' => trim(CashEntry::refPrefix().' '.AppSetting::current()->cash_entry_next_ref_no),
        ]);
    }

    public function store(CashEntryRequest $request): RedirectResponse
    {
        // The whole write in one transaction: the counter's lock only holds inside
        // one, and it is what makes the unique indexes a real serialisation point
        // when two clerks settle the same estimate at the same moment.
        $entry = DB::transaction(function () use ($request) {
            $entry = new CashEntry($request->validated());

            $this->applyDocuments($entry, $request);

            $entry->ref_no = CashEntry::nextRefNo();
            $entry->created_by = $request->user()->id;
            $entry->save();

            return $entry;
        });

        return redirect()->route('cash-entries.index')
            ->with('success', "Cash entry {$entry->reference()} has been created.");
    }

    public function edit(CashEntry $cashEntry): View
    {
        return view('cash-entries.edit', $this->formData() + [
            'entry' => $cashEntry,
            'nextRef' => $cashEntry->reference(),
        ]);
    }

    public function update(CashEntryRequest $request, CashEntry $cashEntry): RedirectResponse
    {
        DB::transaction(function () use ($request, $cashEntry) {
            $cashEntry->fill($request->validated());

            // Re-resolved every time: changing the document moves the foreign key,
            // which frees the old one through the unique index.
            $this->applyDocuments($cashEntry, $request);

            $cashEntry->save();
        });

        return redirect()->route('cash-entries.index')
            ->with('success', "Cash entry {$cashEntry->reference()} has been updated.");
    }

    public function destroy(CashEntry $cashEntry): RedirectResponse
    {
        $reference = $cashEntry->reference();
        $cashEntry->delete();

        return redirect()->route('cash-entries.index')
            ->with('success', "Cash entry {$reference} has been deleted.");
    }

    /**
     * Copy onto the entry everything the documents say, read from the database
     * rather than from the request.
     *
     * This is the whole security model of the module: none of these are fillable, so
     * the amounts an entry carries can only ever come from the documents themselves.
     */
    private function applyDocuments(CashEntry $entry, CashEntryRequest $request): void
    {
        $document = $request->document();
        $og = $request->ogEstimate();
        $gold = $this->math->goldFigures($og);

        $entry->forceFill(CashEntry::splitDocumentReference($request->input('document_reference')) + [
            'final_amount' => $this->math->finalAmount($document),
            'document_reference' => $document->reference(),
            'party_name' => $document instanceof ItemEstimate
                ? $document->customer_name
                : $document->description,
            'og_estimate_id' => $og?->id,
            'og_reference' => $og?->reference(),
            'gold_weight' => $gold['weight'],
            'gold_amount' => $gold['amount'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return ['drawers' => CashDrawer::active()->ordered()->get(['id', 'name'])];
    }
}
