<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the shop holds, and how it moved.
 *
 * Both stock screens read through here so they cannot drift on what counts. Nothing
 * is stored: the figures come off `items` every time, which means a back-dated day
 * still reports correctly and a correction flows straight through — neither of which
 * a nightly snapshot would manage.
 *
 * Everything is aggregated in SQL, grouped by item group. A page of groups is one
 * query, never a count per row.
 */
class StockFigures
{
    /**
     * What is in stock now, a row per item group.
     *
     * Every active group appears, including those holding nothing, so the sheet reads
     * the same from one day to the next.
     *
     * @return Collection<int, object>
     */
    public function byItemGroup(?int $metalTypeId = null): Collection
    {
        $totals = Item::query()
            ->when($metalTypeId, fn ($q) => $q->where('metal_type_id', $metalTypeId))
            ->selectRaw('item_group_id')
            ->selectRaw('count(*) as pcs')
            ->selectRaw('coalesce(sum(gross_weight), 0) as gross')
            ->selectRaw('coalesce(sum(net_weight), 0) as net')
            // Promised to a customer on an order form. Reported, not deducted.
            ->selectRaw('coalesce(sum(case when order_form_line_id is not null then 1 else 0 end), 0) as held')
            ->groupBy('item_group_id')
            ->get()
            ->keyBy('item_group_id');

        return ItemGroup::active()->ordered()->get()->map(function (ItemGroup $group) use ($totals) {
            $row = $totals->get($group->id);

            return (object) [
                'id' => $group->id,
                'code' => $group->prefix,
                'name' => $group->name,
                'stock_group_id' => $group->stock_group_id,
                'pcs' => (int) ($row->pcs ?? 0),
                'held' => (int) ($row->held ?? 0),
                'gross' => round((float) ($row->gross ?? 0), 3),
                'net' => round((float) ($row->net ?? 0), 3),
            ];
        })->values();
    }

    /**
     * The same figures rolled up through item_groups.stock_group_id.
     *
     * Reuses the rows above rather than querying again. A group with no stock group
     * lands in one "(unassigned)" row instead of vanishing.
     *
     * @param  Collection<int, object>  $itemGroupRows
     * @return Collection<int, object>
     */
    public function byStockGroup(Collection $itemGroupRows, Collection $stockGroups): Collection
    {
        return $stockGroups
            ->map(fn ($stockGroup) => $this->rollUp(
                $itemGroupRows->where('stock_group_id', $stockGroup->id),
                $stockGroup->code,
                $stockGroup->name,
            ))
            ->push($this->rollUp(
                $itemGroupRows->whereNull('stock_group_id'),
                '—',
                '(unassigned)',
            ))
            ->values();
    }

    /**
     * How each item group moved on one day.
     *
     * One query over withTrashed(), so a piece created and removed the same day lands
     * in both Add and Less and contributes nothing to Closing — right, and it falls
     * out of the arithmetic rather than needing a special case.
     *
     * Closing is computed from the other three, never queried on its own: a rounding
     * difference between two queries would print a sheet that does not add up.
     *
     * @return Collection<int, object>
     */
    public function daily(Carbon $date, ?int $metalTypeId = null): Collection
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->addDay()->startOfDay();

        $opening = 'created_at < ? and (deleted_at is null or deleted_at >= ?)';
        $added = 'created_at >= ? and created_at < ?';
        $less = 'deleted_at is not null and deleted_at >= ? and deleted_at < ?';

        $totals = Item::withTrashed()
            ->when($metalTypeId, fn ($q) => $q->where('metal_type_id', $metalTypeId))
            ->selectRaw('item_group_id')
            ->selectRaw("coalesce(sum(case when {$opening} then 1 else 0 end), 0) as opening_pcs", [$start, $start])
            ->selectRaw("coalesce(sum(case when {$opening} then net_weight else 0 end), 0) as opening_wt", [$start, $start])
            ->selectRaw("coalesce(sum(case when {$added} then 1 else 0 end), 0) as add_pcs", [$start, $end])
            ->selectRaw("coalesce(sum(case when {$added} then net_weight else 0 end), 0) as add_wt", [$start, $end])
            ->selectRaw("coalesce(sum(case when {$less} then 1 else 0 end), 0) as less_pcs", [$start, $end])
            ->selectRaw("coalesce(sum(case when {$less} then net_weight else 0 end), 0) as less_wt", [$start, $end])
            ->groupBy('item_group_id')
            ->get()
            ->keyBy('item_group_id');

        // Only the groups the report is set to show. The figures above are gathered
        // across everything and then narrowed here, so the SQL stays one query.
        return ItemGroup::active()->onDailyReport()->ordered()->get()->map(function (ItemGroup $group) use ($totals) {
            $row = $totals->get($group->id);

            $openingPcs = (int) ($row->opening_pcs ?? 0);
            $addPcs = (int) ($row->add_pcs ?? 0);
            $lessPcs = (int) ($row->less_pcs ?? 0);

            $openingWt = round((float) ($row->opening_wt ?? 0), 3);
            $addWt = round((float) ($row->add_wt ?? 0), 3);
            $lessWt = round((float) ($row->less_wt ?? 0), 3);

            return (object) [
                'code' => $group->prefix,
                'name' => $group->name,
                'opening_pcs' => $openingPcs,
                'opening_wt' => $openingWt,
                'add_pcs' => $addPcs,
                'add_wt' => $addWt,
                'less_pcs' => $lessPcs,
                'less_wt' => $lessWt,
                'closing_pcs' => $openingPcs + $addPcs - $lessPcs,
                'closing_wt' => round($openingWt + $addWt - $lessWt, 3),
            ];
        })->values();
    }

    /**
     * Sum a set of rows across whichever numeric keys they carry.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<int, string>  $keys
     */
    public function totals(Collection $rows, array $keys): object
    {
        $totals = [];

        foreach ($keys as $key) {
            $sum = $rows->sum($key);
            $totals[$key] = is_int($rows->first()?->{$key}) ? (int) $sum : round((float) $sum, 3);
        }

        return (object) $totals;
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function rollUp(Collection $rows, string $code, string $name): object
    {
        return (object) [
            'code' => $code,
            'name' => $name,
            'pcs' => (int) $rows->sum('pcs'),
            'held' => (int) $rows->sum('held'),
            'gross' => round((float) $rows->sum('gross'), 3),
            'net' => round((float) $rows->sum('net'), 3),
        ];
    }
}
