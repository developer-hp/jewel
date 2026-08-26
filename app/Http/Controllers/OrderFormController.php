<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderFormRequest;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\OrderFormLine;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\StoneMaster;
use App\Services\ItemCalculator;
use App\Services\ItemPhotoStore;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OrderFormController extends Controller implements HasMiddleware
{
    /**
     * Lines whose rate could not be pinned because that purity has no rate today.
     *
     * @var array<int, string>
     */
    private array $rateFailures = [];

    public function __construct(
        private readonly ItemPhotoStore $photos,
        private readonly ItemCalculator $calculator,
        private readonly WhatsAppNotifier $whatsapp,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:order_form.view', only: ['index']),
            new Middleware('permission:order_form.create', only: ['create', 'store']),
            new Middleware('permission:order_form.edit', only: ['edit', 'update', 'fixRate']),
            new Middleware('permission:order_form.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('order-forms.index');
    }

    private function data(Request $request): JsonResponse
    {
        $query = OrderForm::query()
            ->select('order_forms.*')
            ->withReadyCounts()
            // Other Orders reads off the customer's other forms; eager-loaded so the
            // column never costs a query per row.
            ->with(['customer.orderForms:id,customer_id,ref_no'])
            ->when($request->filled('status'), fn ($q) => $request->string('status')->toString() === 'ready'
                ? $q->ready()
                : $q->pending());

        $prefix = OrderForm::refPrefix();

        return DataTables::eloquent($query)
            ->addColumn('select', fn (OrderForm $form) => view('order-forms.partials.select-cell', ['form' => $form])->render())
            ->addColumn('ref', fn (OrderForm $form) => '<strong>'.e($form->reference()).'</strong>')
            ->addColumn('customer', fn (OrderForm $form) => e($form->customer_name))
            ->addColumn('contact', fn (OrderForm $form) => e($form->contact_no))
            ->editColumn('form_date', fn (OrderForm $form) => $form->form_date->format('d-m-Y'))
            ->editColumn('delivery_date', fn (OrderForm $form) => $form->delivery_date->format('d-m-Y'))
            ->addColumn('other_orders', fn (OrderForm $form) => e($this->otherOrders($form, $prefix)))
            ->addColumn('progress', fn (OrderForm $form) => $form->ready_lines_count.' / '.$form->lines_count)
            ->addColumn('status', fn (OrderForm $form) => view('components.status-badge', [
                'active' => $this->isRowReady($form),
                'labels' => ['Ready', 'Pending'],
            ])->render())
            ->addColumn('action', fn (OrderForm $form) => view('order-forms.partials.actions', ['form' => $form])->render())
            ->setRowClass(fn (OrderForm $form) => $this->isRowReady($form) ? 'row-ready' : 'row-pending')
            ->filterColumn('ref', fn ($q, $keyword) => $q->where('ref_no', 'like', '%'.ltrim($keyword, $prefix.' ').'%'))
            ->filterColumn('customer', fn ($q, $keyword) => $q->where('customer_name', 'like', "%{$keyword}%"))
            ->filterColumn('contact', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('contact_no', 'like', "%{$keyword}%")
                    ->orWhere('contact_no_alt', 'like', "%{$keyword}%"));
            })
            ->orderColumn('ref', 'ref_no $1')
            ->orderColumn('customer', 'customer_name $1')
            ->rawColumns(['select', 'ref', 'status', 'action'])
            ->toJson();
    }

    /**
     * This customer's other orders, by the number they gave. Empty when they are new
     * or when the form predates the register.
     */
    private function otherOrders(OrderForm $form, string $prefix): string
    {
        $others = $form->customer?->orderForms
            ->reject(fn (OrderForm $other) => $other->id === $form->id)
            ->sortByDesc('ref_no')
            ->take(5)
            ->map(fn (OrderForm $other) => $prefix.' '.$other->ref_no);

        return $others?->isNotEmpty() ? $others->implode(', ') : '—';
    }

    private function isRowReady(OrderForm $form): bool
    {
        return $form->lines_count > 0 && $form->ready_lines_count === $form->lines_count;
    }

    public function create(): View
    {
        return view('order-forms.create', $this->formData() + [
            'form' => new OrderForm(['form_date' => today(), 'delivery_date' => today()->addWeeks(2)]),
            'lines' => collect(),
            'nextRef' => OrderForm::refPrefix().' '.AppSetting::current()->order_next_ref_no,
        ]);
    }

    public function store(OrderFormRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $form = DB::transaction(function () use ($data, $request) {
            $form = new OrderForm($data);
            // Reserved inside the transaction so the settings row lock holds.
            $form->ref_no = OrderForm::nextRefNo();
            $form->created_by = $request->user()->id;
            $form->sales_person_name = SalesPerson::find($data['sales_person_id'])?->name;
            $form->save();

            $this->syncLines($form, $data['lines']);
            $this->linkCustomer($form);

            return $form;
        });

        // After the commit, like the photo below: the customer is only told about an
        // order that is definitely on disk. Never throws — see WhatsAppNotifier.
        $this->whatsapp->orderCreated($form);

        if ($request->hasFile('photo')) {
            $this->photos->put($form, $request->file('photo'));
        }

        if ($request->boolean('print_after_save')) {
            return $this->withRateWarning(redirect()->route('order-forms.index')
                ->with('printAfterSave', $form->id)
                ->with('success', "Order {$form->reference()} saved — printing."));
        }

        return $this->withRateWarning(redirect()->route('order-forms.index')
            ->with('success', "Order {$form->reference()} has been created."));
    }

    public function edit(OrderForm $orderForm): View
    {
        return view('order-forms.edit', $this->formData() + [
            'form' => $orderForm,
            'lines' => $orderForm->lines()->with(['item:id,code,order_form_line_id', 'sourceItem:id,code,name', 'stones.stoneMaster'])->get(),
            'nextRef' => $orderForm->reference(),
        ]);
    }

    public function update(OrderFormRequest $request, OrderForm $orderForm): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($orderForm, $data) {
            $orderForm->update($data);
            $orderForm->forceFill(['sales_person_name' => SalesPerson::find($data['sales_person_id'])?->name])->save();

            $this->syncLines($orderForm, $data['lines']);
            $this->linkCustomer($orderForm);
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($orderForm, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $this->photos->remove($orderForm);
        }

        return $this->withRateWarning(redirect()->route('order-forms.index')
            ->with('success', "Order {$orderForm->reference()} has been updated."));
    }

    /**
     * A rate that could not be pinned is worth saying out loud — the clerk ticked
     * the box and would otherwise assume it took.
     */
    private function withRateWarning(RedirectResponse $response): RedirectResponse
    {
        if ($this->rateFailures === []) {
            return $response;
        }

        return $response->with('error', 'Rate not fixed for: '.implode(', ', $this->rateFailures)
            .'. Enter the rate for that purity under Daily Rates, then tick it again.');
    }

    public function destroy(OrderForm $orderForm): RedirectResponse
    {
        if ($orderForm->lines()->whereHas('item')->exists()) {
            return back()->with('error', "{$orderForm->reference()} has pieces held against it and cannot be deleted.");
        }

        $reference = $orderForm->reference();

        if ($orderForm->hasPhoto()) {
            $this->photos->remove($orderForm);
        }

        $orderForm->lines->each(function (OrderFormLine $line) {
            $line->stones()->delete();
            $line->delete();
        });

        $orderForm->delete();

        return redirect()->route('order-forms.index')
            ->with('success', "Order {$reference} has been deleted.");
    }

    /**
     * Pin, or release, the day's rate against one line.
     */
    public function fixRate(Request $request, OrderFormLine $line): RedirectResponse
    {
        $back = redirect()->route('order-forms.edit', $line->order_form_id);

        if ($request->boolean('release')) {
            $line->releaseRate();

            return $back->with('success', 'Rate released — the line prices at the rate of the day again.');
        }

        if (! $line->fixRate()) {
            return $back->with('error', 'No rate has been entered for that purity today. Enter it under Daily Rates first.');
        }

        return $back->with('success', 'Rate fixed at '.number_format((float) $line->fresh()->fixed_rate_per_gram, 2).' per gram.');
    }

    /**
     * Update the lines in place rather than replacing them wholesale.
     *
     * A line with a piece held against it is pointed at by that item, so deleting and
     * recreating it would quietly break the reservation. Only lines nothing points at
     * are ever removed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncLines(OrderForm $form, array $rows): void
    {
        $kept = [];

        foreach ($rows as $row) {
            $line = $row['id'] ? $form->lines()->whereKey($row['id'])->first() : null;

            if ($line?->isReady()) {
                // Nothing on a held line may change; only its position may shift —
                // and its rate, which is a commercial agreement rather than a fact
                // about the piece.
                $line->forceFill(['sort_order' => $row['sort_order']])->save();
                $this->applyRateFix($line, $row);
                $kept[] = $line->id;

                continue;
            }

            $attributes = collect($row)->only([
                'source_item_id', 'made_to_order', 'description', 'size_pcs',
                'metal_type_id', 'purity_id', 'net_weight',
                'lc_amount', 'lc_type', 'oc_amount', 'sort_order',
            ])->all();

            if ($line) {
                $line->update($attributes);
            } else {
                $line = $form->lines()->create($attributes);
            }

            $this->syncStones($line, $row['stones']);
            $this->reserve($line, $row);
            $this->applyRateFix($line, $row);

            $kept[] = $line->id;
        }

        $form->lines()->whereNotIn('id', $kept)->whereDoesntHave('item')->each(function (OrderFormLine $line) {
            $line->stones()->delete();
            $line->delete();
        });
    }

    /**
     * Pin or release the day's rate, as ticked on the form.
     *
     * Deliberately not gated on the piece being held: the rate is what the customer
     * agreed on the day the order was taken, and a piece made six weeks later must
     * still price at it. Requiring a held piece would also make the tick useless on
     * the add screen, which is the only place it will realistically be used.
     *
     * A purity with no rate entered today is collected and reported rather than
     * failing the save — the order matters more than the rate.
     *
     * @param  array<string, mixed>  $row
     */
    private function applyRateFix(OrderFormLine $line, array $row): void
    {
        if (! $row['fix_rate']) {
            if ($line->isRateFixed()) {
                $line->releaseRate();
            }

            return;
        }

        if ($line->isRateFixed()) {
            return;
        }

        if (! $line->load('purity')->fixRate()) {
            $this->rateFailures[] = $line->description;
        }
    }

    /**
     * Hold the chosen stock piece against this line, or let it go.
     *
     * Only stock lines reserve here: a made-to-order piece does not exist yet and is
     * created on the Order Items screen, which sets the link itself.
     *
     * @param  array<string, mixed>  $row
     */
    private function reserve(OrderFormLine $line, array $row): void
    {
        $wanted = $row['reserve'] && ! $row['made_to_order'] && $row['source_item_id'];

        if (! $wanted) {
            return;
        }

        $item = Item::find($row['source_item_id']);

        if (! $item) {
            return;
        }

        // The unique index is the real guard; this turns a database error into a
        // message naming the order that already holds the piece.
        if ($item->order_form_line_id !== null && $item->order_form_line_id !== $line->id) {
            $holder = OrderFormLine::with('orderForm')->find($item->order_form_line_id)?->orderForm;

            throw ValidationException::withMessages([
                'lines' => "{$item->code} is already held against ".($holder?->reference() ?? 'another order').'.',
            ]);
        }

        $item->forceFill(['order_form_line_id' => $line->id])->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncStones(OrderFormLine $line, array $rows): void
    {
        $line->stones()->delete();

        if ($rows === []) {
            return;
        }

        $masters = StoneMaster::whereIn('id', array_column($rows, 'stone_master_id'))->get()->keyBy('id');

        // The one calculator owns the carat/gram/amount arithmetic, for an order line
        // exactly as for an item.
        $line->stones()->createMany($this->calculator->buildStoneRows($rows, $masters));
    }

    /**
     * Tie the order to the customer register, adding them on first contact.
     */
    private function linkCustomer(OrderForm $form): void
    {
        $customer = Customer::rememberByPhone($form->contact_no, $form->customer_name, $form->address);

        if ($customer && $form->customer_id !== $customer->id) {
            $form->forceFill(['customer_id' => $customer->id])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'salesPersons' => SalesPerson::active()->ordered()->get(['id', 'name', 'city']),
            'metalTypes' => MetalType::active()->ordered()->get(),
            'puritiesByMetal' => Purity::active()->ordered()->get()
                ->groupBy('metal_type_id')
                ->map(fn ($group) => $group->map(fn (Purity $p) => ['id' => $p->id, 'name' => $p->name])->values()),
            'stoneMasters' => StoneMaster::active()->kind(StoneMaster::KIND_STONE)->orderBy('name')->get(),
            'diamondMasters' => StoneMaster::active()->kind(StoneMaster::KIND_DIAMOND)->orderBy('name')->get(),
        ];
    }
}
