<?php

namespace App\Services;

use App\Models\Angadiya;
use App\Models\InternalStock;
use App\Models\Item;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\RepairForm;
use App\Models\StockGroup;
use App\Models\SupplierHisab;
use App\Models\SupplierOrder;

/**
 * Everything the dashboard shows.
 *
 * One method per section, and the controller calls only the ones that will render —
 * a section switched off in Appearance must not cost a query.
 *
 * Each method returns null when it has nothing worth a box on the page. That is what
 * keeps the composite sections honest: a viewer who can see none of the modules
 * behind "Needs Attention" gets no empty panel, rather than a heading over nothing.
 */
class DashboardData
{
    public function __construct(private readonly StockFigures $figures) {}

    /**
     * Build the sections named, keyed by section key.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    public function for(array $sections): array
    {
        $built = [];

        foreach ($sections as $section) {
            $key = $section['key'];
            $method = str($key)->camel()->toString();

            if (! method_exists($this, $method)) {
                continue;
            }

            $data = $this->{$method}();

            // Nothing to say, so no box.
            if ($data !== null) {
                $built[$key] = $data;
            }
        }

        return $built;
    }

    /**
     * The rate per gram for each active purity, and how many still have none today.
     */
    private function rates(): ?array
    {
        $purities = Purity::active()
            ->with('metalType')
            ->whereRelation('metalType', 'is_active', true)
            ->ordered()
            ->get();

        if ($purities->isEmpty()) {
            return null;
        }

        $rows = $purities->map(fn (Purity $purity) => (object) [
            'label' => $purity->label(),
            'rate' => $purity->ratePerGramOn(),
            'rated' => $purity->rates()->whereDate('effective_date', today())->exists(),
        ]);

        return [
            'rows' => $rows,
            'missing' => $rows->where('rated', false)->count(),
        ];
    }

    /**
     * The chase list. Each line is dropped for a viewer who cannot open it, and the
     * whole section disappears when none survive.
     */
    private function attention(): ?array
    {
        $user = auth()->user();
        $lines = collect();

        if ($user?->can('repair_form.view')) {
            $lines->push($this->line(
                'Repairs overdue',
                RepairForm::pending()->whereDate('delivery_date', '<', today())->count(),
                route('repair-forms.index', ['status' => 'pending']),
            ))->push($this->line(
                'Repairs due today',
                RepairForm::pending()->whereDate('delivery_date', today())->count(),
                route('repair-forms.index', ['status' => 'pending']),
            ));
        }

        if ($user?->can('order_form.view')) {
            $lines->push($this->line(
                'Orders overdue',
                OrderForm::pending()->whereDate('delivery_date', '<', today())->count(),
                route('order-forms.index', ['status' => 'pending']),
            ))->push($this->line(
                'Orders due today',
                OrderForm::pending()->whereDate('delivery_date', today())->count(),
                route('order-forms.index', ['status' => 'pending']),
            ));
        }

        if ($user?->can('supplier_order.view')) {
            $lines->push($this->line(
                'Karigar followups due',
                SupplierOrder::overdue()->count(),
                route('supplier-orders.index', ['status' => 'overdue']),
            ));
        }

        if ($user?->can('angadiya.view')) {
            $lines->push($this->line(
                'Angadiya not printed',
                Angadiya::unprinted()->count(),
                route('angadiyas.index', ['printed' => 'no']),
            ));
        }

        return $lines->isEmpty() ? null : ['lines' => $lines];
    }

    /**
     * The things started most often, minus anything this user may not do.
     */
    private function quickActions(): ?array
    {
        $user = auth()->user();

        $actions = collect([
            ['label' => 'New Repair', 'icon' => 'ri-tools-line', 'can' => 'repair_form.create', 'route' => 'repair-forms.create'],
            ['label' => 'New Order', 'icon' => 'ri-shopping-bag-line', 'can' => 'order_form.create', 'route' => 'order-forms.create'],
            ['label' => 'Add Item', 'icon' => 'ri-price-tag-3-line', 'can' => 'item.create', 'route' => 'items.create'],
            ['label' => 'Karigar Order', 'icon' => 'ri-hammer-line', 'can' => 'supplier_order.create', 'route' => 'supplier-orders.create'],
            ['label' => 'Enter Rates', 'icon' => 'ri-exchange-funds-line', 'can' => 'metal_rate.create', 'route' => 'rates.today'],
            ['label' => 'Scan Slip', 'icon' => 'ri-qr-scan-2-line', 'can' => 'supplier_order.delete', 'route' => 'supplier-orders.scan'],
        ])->filter(fn (array $action) => $user?->can($action['can']))
            ->map(fn (array $action) => (object) array_merge($action, ['url' => route($action['route'])]))
            ->values();

        return $actions->isEmpty() ? null : ['actions' => $actions];
    }

    /**
     * Read through StockFigures, the same service the stock screens use, so the
     * dashboard cannot quietly disagree with them.
     */
    private function stock(): ?array
    {
        $itemGroups = $this->figures->byItemGroup();

        if ($itemGroups->sum('pcs') === 0) {
            return null;
        }

        $stockGroups = $this->figures->byStockGroup($itemGroups, StockGroup::active()->ordered()->get());

        return [
            // Only the groups actually holding something; a dashboard is a glance,
            // not the full sheet.
            'rows' => $stockGroups->filter(fn ($row) => $row->pcs > 0)->values(),
            'totals' => $this->figures->totals($itemGroups, ['pcs', 'held', 'net']),
        ];
    }

    private function internalStock(): ?array
    {
        $stocks = InternalStock::active()->withBalance()->ordered()->get();

        return $stocks->isEmpty() ? null : ['stocks' => $stocks];
    }

    /**
     * How much of each is still outstanding.
     */
    private function progress(): ?array
    {
        $user = auth()->user();
        $bars = collect();

        if ($user?->can('repair_form.view')) {
            $bars->push($this->bar('Repairs', RepairForm::ready()->count(), RepairForm::count(), route('repair-forms.index')));
        }

        if ($user?->can('order_form.view')) {
            $bars->push($this->bar('Orders', OrderForm::ready()->count(), OrderForm::count(), route('order-forms.index')));
        }

        if ($user?->can('supplier_order.view')) {
            $bars->push($this->bar('Karigar Orders', SupplierOrder::received()->count(), SupplierOrder::count(), route('supplier-orders.index')));
        }

        return $bars->isEmpty() ? null : ['bars' => $bars];
    }

    private function hisab(): ?array
    {
        $entries = SupplierHisab::onDate(today())->with('payments')->get();

        if ($entries->isEmpty()) {
            return null;
        }

        return [
            'count' => $entries->count(),
            'unsettled' => $entries->reject->isSettled()->count(),
            'fine_baki' => round((float) $entries->sum('fine_baki'), 3),
            'cash_baki' => round((float) $entries->sum('cash_baki'), 2),
            'fine_kapi' => round($entries->sum(fn (SupplierHisab $h) => $h->fineKapi()), 3),
            'cash_apvi' => round($entries->sum(fn (SupplierHisab $h) => $h->cashApvi()), 2),
        ];
    }

    /**
     * The last few things recorded, newest first.
     *
     * Four short indexed queries merged in PHP, each skipped for a viewer who cannot
     * see that module — cheaper and simpler than a union across four shapes.
     */
    private function recent(): ?array
    {
        $user = auth()->user();
        $events = collect();

        if ($user?->can('item.view')) {
            $events = $events->concat(
                Item::latest()->take(5)->get()->map(fn (Item $item) => $this->event(
                    "{$item->code} added to stock", $item->created_at, route('items.show', $item), 'ri-price-tag-3-line',
                ))
            );
        }

        if ($user?->can('repair_form.view')) {
            $events = $events->concat(
                RepairForm::latest()->take(5)->get()->map(fn (RepairForm $form) => $this->event(
                    "Repair {$form->reference()} taken in", $form->created_at, route('repair-forms.index'), 'ri-tools-line',
                ))
            );
        }

        if ($user?->can('order_form.view')) {
            $events = $events->concat(
                OrderForm::latest()->take(5)->get()->map(fn (OrderForm $form) => $this->event(
                    "Order {$form->reference()} raised", $form->created_at, route('order-forms.index'), 'ri-shopping-bag-line',
                ))
            );
        }

        if ($user?->can('supplier_order.view')) {
            $events = $events->concat(
                SupplierOrder::latest()->take(5)->get()->map(fn (SupplierOrder $order) => $this->event(
                    "Karigar order {$order->form_no} to {$order->supplier_name}", $order->created_at,
                    route('supplier-orders.index'), 'ri-hammer-line',
                ))
            );
        }

        $events = $events->filter(fn ($event) => $event->at !== null)
            ->sortByDesc('at')
            ->take(8)
            ->values();

        return $events->isEmpty() ? null : ['events' => $events];
    }

    private function line(string $label, int $count, string $url): object
    {
        return (object) ['label' => $label, 'count' => $count, 'url' => $url];
    }

    private function bar(string $label, int $done, int $total, string $url): object
    {
        return (object) [
            'label' => $label,
            'done' => $done,
            'total' => $total,
            'pending' => $total - $done,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'url' => $url,
        ];
    }

    private function event(string $label, $at, string $url, string $icon): object
    {
        return (object) ['label' => $label, 'at' => $at, 'url' => $url, 'icon' => $icon];
    }
}
