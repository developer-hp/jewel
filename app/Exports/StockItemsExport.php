<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockItemsExport implements FromQuery, WithHeadings, WithMapping
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return Item::query()
            ->with('itemGroup.stockGroup', 'metalType', 'purity')
            ->when($this->filters['item_group_id'] ?? null, fn ($q) => $q->where('item_group_id', $this->filters['item_group_id']))
            ->when($this->filters['stock_group_id'] ?? null, function ($q) {
                return $q->whereHas('itemGroup', fn ($sub) => $sub->where('stock_group_id', $this->filters['stock_group_id']));
            })
            ->when($this->filters['metal_type_id'] ?? null, fn ($q) => $q->where('metal_type_id', $this->filters['metal_type_id']))
            ->when($this->filters['purity_id'] ?? null, fn ($q) => $q->where('purity_id', $this->filters['purity_id']))
            ->when($this->filters['status'] ?? null, function ($q) {
                if ($this->filters['status'] === 'active') {
                    return $q->whereNull('sold_at');
                } elseif ($this->filters['status'] === 'sold') {
                    return $q->whereNotNull('sold_at');
                }
                return $q;
            })
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Item Group',
            'Stock Group',
            'Metal Type',
            'Purity',
            'Gross Weight (g)',
            'Status',
            'Created Date',
        ];
    }

    public function map($item): array
    {
        return [
            $item->code ?? '-',
            $item->name,
            $item->itemGroup?->name,
            $item->itemGroup?->stockGroup?->name,
            $item->metalType?->name,
            $item->purity?->name,
            (float) $item->gross_weight,
            $item->sold_at ? 'Sold' : 'Active',
            $item->created_at?->format('d M Y'),
        ];
    }
}
