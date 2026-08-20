<?php

namespace App\Http\Controllers;

use App\Http\Requests\AngadiyaRequest;
use App\Models\Angadiya;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class AngadiyaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:angadiya.view', only: ['index']),
            new Middleware('permission:angadiya.create', only: ['create', 'store']),
            new Middleware('permission:angadiya.edit', only: ['edit', 'update']),
            new Middleware('permission:angadiya.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('angadiyas.index', [
            'unprintedCount' => Angadiya::unprinted()->count(),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = Angadiya::query()
            ->select('angadiyas.*')
            ->with('supplier')
            ->when(
                $request->filled('printed'),
                fn ($q) => $request->string('printed')->toString() === 'yes'
                    ? $q->whereNotNull('printed_at')
                    : $q->whereNull('printed_at')
            );

        return DataTables::eloquent($query)
            ->addColumn('select', fn (Angadiya $a) => view('angadiyas.partials.select-cell', ['angadiya' => $a])->render())
            ->editColumn('created_at', fn (Angadiya $a) => $a->created_at->format('d M Y'))
            ->addColumn('recipient', fn (Angadiya $a) => view('angadiyas.partials.recipient-cell', ['angadiya' => $a])->render())
            ->editColumn('insurance_amount', fn (Angadiya $a) => number_format((float) $a->insurance_amount, 2))
            ->editColumn('remark', fn (Angadiya $a) => e(Str::limit($a->remark, 40) ?: '—'))
            ->addColumn('printed', fn (Angadiya $a) => view('angadiyas.partials.printed-cell', ['angadiya' => $a])->render())
            ->addColumn('action', fn (Angadiya $a) => view('angadiyas.partials.actions', ['angadiya' => $a])->render())
            ->filterColumn('recipient', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('mobile', 'like', "%{$keyword}%"));
            })
            ->orderColumn('recipient', 'name $1')
            ->orderColumn('printed', 'printed_at $1')
            ->rawColumns(['select', 'recipient', 'printed', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('angadiyas.create', [
            'angadiya' => new Angadiya,
            'suppliers' => $this->supplierOptions(),
        ]);
    }

    public function store(AngadiyaRequest $request): RedirectResponse
    {
        $angadiya = Angadiya::create($request->validated() + ['created_by' => $request->user()->id]);

        // "Save & Print" jumps straight to the sheet for this one slip.
        if ($request->boolean('print_after_save')) {
            return redirect()->route('angadiyas.index')
                ->with('printAfterSave', $angadiya->id)
                ->with('success', "Slip for \"{$angadiya->name}\" saved — printing.");
        }

        return redirect()->route('angadiyas.index')
            ->with('success', "Slip for \"{$angadiya->name}\" has been saved.");
    }

    public function edit(Angadiya $angadiya): View
    {
        return view('angadiyas.edit', [
            'angadiya' => $angadiya,
            'suppliers' => $this->supplierOptions(),
        ]);
    }

    public function update(AngadiyaRequest $request, Angadiya $angadiya): RedirectResponse
    {
        $angadiya->update($request->validated());

        return redirect()->route('angadiyas.index')
            ->with('success', "Slip for \"{$angadiya->name}\" has been updated.");
    }

    public function destroy(Angadiya $angadiya): RedirectResponse
    {
        $name = $angadiya->name;
        $angadiya->delete();

        return redirect()->route('angadiyas.index')
            ->with('success', "Slip for \"{$name}\" has been deleted.");
    }

    /**
     * Suppliers carrying the fields the form prefills from.
     */
    private function supplierOptions()
    {
        return Supplier::active()->ordered()->get(['id', 'name', 'short_name', 'city', 'phone']);
    }
}
