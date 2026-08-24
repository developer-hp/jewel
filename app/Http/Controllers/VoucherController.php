<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherRequest;
use App\Models\AppSetting;
use App\Models\OrderForm;
use App\Models\SalesPerson;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class VoucherController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:voucher.view', only: ['index']),
            new Middleware('permission:voucher.create', only: ['create', 'store', 'copy']),
            new Middleware('permission:voucher.edit', only: ['edit', 'update']),
            new Middleware('permission:voucher.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('vouchers.index');
    }

    private function data(): JsonResponse
    {
        $query = Voucher::query()->select('vouchers.*')->with('orderForm');
        $prefix = Voucher::refPrefix();

        return DataTables::eloquent($query)
            ->addColumn('select', fn (Voucher $v) => view('vouchers.partials.select-cell', ['voucher' => $v])->render())
            ->addColumn('ref', fn (Voucher $v) => '<strong>'.e(trim($prefix.' '.$v->ref_no)).'</strong>')
            ->editColumn('voucher_date', fn (Voucher $v) => $v->voucher_date->format('d-m-Y'))
            ->addColumn('order_ref', fn (Voucher $v) => e($v->orderReferenceLabel()))
            ->editColumn('description', fn (Voucher $v) => e($v->description))
            ->addColumn('mode', fn (Voucher $v) => e($v->modeLabel()))
            ->editColumn('amount', fn (Voucher $v) => number_format((float) $v->amount, 2))
            ->addColumn('sales_person', fn (Voucher $v) => e($v->sales_person_name ?: '—'))
            ->addColumn('action', fn (Voucher $v) => view('vouchers.partials.actions', ['voucher' => $v])->render())
            ->filterColumn('ref', fn ($q, $keyword) => $q->where('ref_no', 'like', '%'.trim($keyword, $prefix.' ').'%'))
            ->orderColumn('ref', 'ref_no $1')
            ->rawColumns(['select', 'ref', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('vouchers.create', $this->formData() + [
            'voucher' => new Voucher(['voucher_date' => today()]),
            'nextRef' => trim(Voucher::refPrefix().' '.AppSetting::current()->voucher_next_ref_no),
        ]);
    }

    public function store(VoucherRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $voucher = DB::transaction(function () use ($data, $request) {
            $voucher = new Voucher($data);
            $voucher->ref_no = Voucher::nextRefNo();
            $voucher->created_by = $request->user()->id;
            $this->applySalesPerson($voucher, $data['sales_person_id']);
            $voucher->save();

            return $voucher;
        });

        if ($request->boolean('print_after_save')) {
            return redirect()->route('vouchers.index')
                ->with('printAfterSave', $voucher->id)
                ->with('success', "Voucher {$voucher->reference()} saved — printing.");
        }

        return redirect()->route('vouchers.index')
            ->with('success', "Voucher {$voucher->reference()} has been created.");
    }

    public function edit(Voucher $voucher): View
    {
        return view('vouchers.edit', $this->formData() + [
            'voucher' => $voucher,
            'nextRef' => $voucher->reference(),
        ]);
    }

    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $data = $request->validated();

        $this->applySalesPerson($voucher, $data['sales_person_id']);
        $voucher->update($data);

        return redirect()->route('vouchers.index')
            ->with('success', "Voucher {$voucher->reference()} has been updated.");
    }

    public function copy(Voucher $voucher): RedirectResponse
    {
        $copy = DB::transaction(function () use ($voucher) {
            $copy = $voucher->replicate(['ref_no', 'created_by']);
            $copy->ref_no = Voucher::nextRefNo();
            $copy->voucher_date = today();
            $copy->created_by = auth()->id();
            $copy->save();

            return $copy;
        });

        return redirect()->route('vouchers.edit', $copy)
            ->with('success', "Copied {$voucher->reference()} to {$copy->reference()}.");
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $reference = $voucher->reference();
        $voucher->delete();

        return redirect()->route('vouchers.index')
            ->with('success', "Voucher {$reference} has been deleted.");
    }

    private function applySalesPerson(Voucher $voucher, int $id): void
    {
        $voucher->sales_person_id = $id;
        $voucher->sales_person_name = SalesPerson::find($id)?->name;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'salesPersons' => SalesPerson::active()->ordered()->get(['id', 'name', 'city']),
            'orderForms' => OrderForm::query()->orderByDesc('ref_no')->get(['id', 'ref_no', 'customer_name', 'contact_no']),
        ];
    }
}
