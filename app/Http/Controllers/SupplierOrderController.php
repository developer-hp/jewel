<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierOrderRequest;
use App\Models\AppSetting;
use App\Models\OrderType;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Services\ItemPhotoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SupplierOrderController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemPhotoStore $photos) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:supplier_order.view', only: ['index']),
            new Middleware('permission:supplier_order.create', only: ['create', 'store']),
            new Middleware('permission:supplier_order.edit', only: ['edit', 'update', 'markReceived']),
            new Middleware('permission:supplier_order.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('supplier-orders.index', [
            'suppliers' => Supplier::ordered()->get()->mapWithKeys(fn (Supplier $s) => [$s->id => $s->label()]),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = SupplierOrder::query()
            ->select('supplier_orders.*')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('order_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('order_date', '<=', $request->date('to')))
            ->when($request->filled('status'), fn ($q) => match ($request->string('status')->toString()) {
                'received' => $q->received(),
                'overdue' => $q->overdue(),
                default => $q->pending(),
            });

        return DataTables::eloquent($query)
            ->addColumn('select', fn (SupplierOrder $order) => view('supplier-orders.partials.select-cell', ['order' => $order])->render())
            ->editColumn('form_no', fn (SupplierOrder $order) => '<strong>'.e($order->form_no).'</strong>')
            ->editColumn('order_date', fn (SupplierOrder $order) => $order->order_date->format('d-m-Y'))
            ->editColumn('followup_date', fn (SupplierOrder $order) => $order->followup_date->format('d-m-Y'))
            ->editColumn('customer_delivery_date', fn (SupplierOrder $order) => $order->customer_delivery_date->format('d-m-Y'))
            // Snapshotted on the row, so no join and no query per line.
            ->addColumn('supplier', fn (SupplierOrder $order) => e($order->supplier_name))
            ->addColumn('type', fn (SupplierOrder $order) => e($order->order_type_name ?: '—'))
            ->editColumn('description', fn (SupplierOrder $order) => e(Str::limit($order->description, 45) ?: '—'))
            ->editColumn('order_form_ref', fn (SupplierOrder $order) => e($order->order_form_ref ?: '—'))
            ->addColumn('status', fn (SupplierOrder $order) => view('supplier-orders.partials.status-cell', ['order' => $order])->render())
            ->addColumn('action', fn (SupplierOrder $order) => view('supplier-orders.partials.actions', ['order' => $order])->render())
            ->setRowClass(fn (SupplierOrder $order) => $order->rowClass())
            ->filterColumn('supplier', fn ($q, $keyword) => $q->where('supplier_name', 'like', "%{$keyword}%"))
            ->filterColumn('type', fn ($q, $keyword) => $q->where('order_type_name', 'like', "%{$keyword}%"))
            ->orderColumn('supplier', 'supplier_name $1')
            ->orderColumn('type', 'order_type_name $1')
            ->orderColumn('status', 'received_at $1')
            ->rawColumns(['select', 'form_no', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('supplier-orders.create', $this->formData() + [
            'order' => new SupplierOrder([
                'order_date' => today(),
                'followup_date' => today()->addWeek(),
                'customer_delivery_date' => today()->addWeeks(2),
            ]),
            'nextFormNo' => AppSetting::current()->supplier_order_next_form_no,
        ]);
    }

    public function store(SupplierOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $request) {
            $order = new SupplierOrder($data + $this->snapshots($data));
            // Reserved inside the transaction so the settings row lock holds.
            $order->form_no = SupplierOrder::nextFormNo();
            $order->scan_token = SupplierOrder::newScanToken();
            $order->created_by = $request->user()->id;
            $order->save();

            return $order;
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($order, $request->file('photo'));
        }

        if ($request->boolean('print_after_save')) {
            return redirect()->route('supplier-orders.index')
                ->with('printAfterSave', $order->id)
                ->with('success', "Order {$order->form_no} saved — printing.");
        }

        return redirect()->route('supplier-orders.index')
            ->with('success', "Order {$order->form_no} has been created.");
    }

    public function edit(SupplierOrder $supplierOrder): View
    {
        return view('supplier-orders.edit', $this->formData() + [
            'order' => $supplierOrder,
            'nextFormNo' => $supplierOrder->form_no,
        ]);
    }

    public function update(SupplierOrderRequest $request, SupplierOrder $supplierOrder): RedirectResponse
    {
        $data = $request->validated();

        $supplierOrder->update($data + $this->snapshots($data));

        if ($request->hasFile('photo')) {
            $this->photos->put($supplierOrder, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $this->photos->remove($supplierOrder);
        }

        return redirect()->route('supplier-orders.index')
            ->with('success', "Order {$supplierOrder->form_no} has been updated.");
    }

    /**
     * The goods came back. The QR is for removing an order outright; this is what
     * closes one that simply finished.
     */
    public function markReceived(SupplierOrder $supplierOrder): RedirectResponse
    {
        if ($supplierOrder->isReceived()) {
            return back()->with('error', "Order {$supplierOrder->form_no} is already marked received.");
        }

        $supplierOrder->markReceived();

        return back()->with('success', "Order {$supplierOrder->form_no} marked received.");
    }

    public function destroy(SupplierOrder $supplierOrder): RedirectResponse
    {
        $formNo = $supplierOrder->form_no;
        $supplierOrder->delete();

        return redirect()->route('supplier-orders.index')
            ->with('success', "Order {$formNo} has been deleted.");
    }

    /**
     * The supplier and type names as they stand now.
     *
     * Kept on the order so a rename in either master cannot rewrite a slip that has
     * already printed — the same discipline the repair and order forms follow.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snapshots(array $data): array
    {
        return [
            'supplier_name' => Supplier::findOrFail($data['supplier_id'])->name,
            'order_type_name' => OrderType::findOrFail($data['order_type_id'])->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'suppliers' => Supplier::active()->ordered()->get(['id', 'name', 'short_name']),
            'orderTypes' => OrderType::active()->ordered()->pluck('name', 'id'),
        ];
    }
}
