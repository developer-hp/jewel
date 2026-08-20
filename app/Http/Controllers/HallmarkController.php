<?php

namespace App\Http\Controllers;

use App\Http\Requests\HallmarkRequest;
use App\Models\AppSetting;
use App\Models\Hallmark;
use App\Models\ItemGroup;
use App\Models\Purity;
use App\Models\Supplier;
use App\Services\ItemPhotoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class HallmarkController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemPhotoStore $photos) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:hallmark.view', only: ['index']),
            new Middleware('permission:hallmark.create', only: ['create', 'store']),
            new Middleware('permission:hallmark.edit', only: ['edit', 'update']),
            new Middleware('permission:hallmark.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('hallmarks.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = Hallmark::query()
            ->select('hallmarks.*')
            ->withCount('lines')
            ->with('lines');

        return DataTables::eloquent($query)
            ->editColumn('lot_no', fn (Hallmark $h) => '<code>'.e($h->lot_no).'</code>')
            ->editColumn('hallmark_date', fn (Hallmark $h) => $h->hallmark_date->format('d M Y'))
            ->addColumn('pieces', fn (Hallmark $h) => number_format($h->totalPieces()))
            ->addColumn('gross', fn (Hallmark $h) => number_format((float) $h->gross_weight, 3))
            ->addColumn('cost', fn (Hallmark $h) => number_format($h->totalCost(), 2))
            ->addColumn('action', fn (Hallmark $h) => view('hallmarks.partials.actions', ['hallmark' => $h])->render())
            ->orderColumn('pieces', 'lot_no $1')
            ->rawColumns(['lot_no', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('hallmarks.create', $this->formData() + [
            'hallmark' => new Hallmark(['hallmark_date' => today()]),
            'lines' => collect(),
            'nextLotNo' => (int) AppSetting::current()->hallmark_next_lot_no,
        ]);
    }

    public function store(HallmarkRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $hallmark = DB::transaction(function () use ($data, $request) {
            $hallmark = new Hallmark($data);
            // Reserved inside the transaction so the settings row lock holds.
            $hallmark->lot_no = Hallmark::nextLotNo();
            $hallmark->created_by = $request->user()->id;
            $hallmark->save();

            $hallmark->lines()->createMany($data['lines']);

            return $hallmark;
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($hallmark, $request->file('photo'));
        }

        if ($request->boolean('print_after_save')) {
            return redirect()->route('hallmarks.print', $hallmark);
        }

        return redirect()->route('hallmarks.index')
            ->with('success', "Hallmark lot {$hallmark->lot_no} has been created.");
    }

    public function edit(Hallmark $hallmark): View
    {
        return view('hallmarks.edit', $this->formData() + [
            'hallmark' => $hallmark,
            'lines' => $hallmark->lines()->get(),
            'nextLotNo' => $hallmark->lot_no,
        ]);
    }

    public function update(HallmarkRequest $request, Hallmark $hallmark): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($hallmark, $data) {
            $hallmark->update($data);

            // Replace the lines wholesale — simpler than diffing, and they carry no
            // outside references.
            $hallmark->lines()->delete();
            $hallmark->lines()->createMany($data['lines']);
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($hallmark, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $this->photos->remove($hallmark);
        }

        return redirect()->route('hallmarks.index')
            ->with('success', "Hallmark lot {$hallmark->lot_no} has been updated.");
    }

    public function destroy(Hallmark $hallmark): RedirectResponse
    {
        $lotNo = $hallmark->lot_no;

        if ($hallmark->hasPhoto()) {
            $this->photos->remove($hallmark);
        }

        $hallmark->delete();

        return redirect()->route('hallmarks.index')
            ->with('success', "Hallmark lot {$lotNo} has been deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'groups' => ItemGroup::active()->ordered()->get(),
            'purities' => Purity::active()->with('metalType')->ordered()->get(),
            // The SC column: suppliers are coded V-1 … V-200 in short_name.
            'suppliers' => Supplier::active()->ordered()->get(['id', 'name', 'short_name']),
        ];
    }
}
