<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemGroupRequest;
use App\Models\ItemGroup;
use App\Models\MetalType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ItemGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:item_group.view', only: ['index']),
            new Middleware('permission:item_group.create', only: ['create', 'store']),
            new Middleware('permission:item_group.edit', only: ['edit', 'update']),
            new Middleware('permission:item_group.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('item-groups.index');
    }

    private function data(): JsonResponse
    {
        $query = ItemGroup::query()->select('item_groups.*')->with('metalType')->withCount('items');

        return DataTables::eloquent($query)
            ->editColumn('prefix', fn (ItemGroup $group) => '<code>'.e($group->prefix).'</code>')
            ->addColumn('next_code', fn (ItemGroup $group) => '<span class="text-muted">'.e($group->previewNextCode()).'</span>')
            ->addColumn('metal_type', fn (ItemGroup $group) => e($group->metalType?->name ?? 'Any'))
            ->addColumn('status', fn (ItemGroup $group) => view('components.status-badge', ['active' => $group->is_active])->render())
            ->addColumn('action', fn (ItemGroup $group) => view('item-groups.partials.actions', ['group' => $group])->render())
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['prefix', 'next_code', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('item-groups.create', [
            'group' => new ItemGroup(['is_active' => true, 'code_padding' => 4, 'sort_order' => 0]),
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function store(ItemGroupRequest $request): RedirectResponse
    {
        $group = ItemGroup::create($request->validated());

        return redirect()->route('item-groups.index')
            ->with('success', "Item group \"{$group->name}\" created — first code will be {$group->previewNextCode()}.");
    }

    public function edit(ItemGroup $itemGroup): View
    {
        return view('item-groups.edit', [
            'group' => $itemGroup,
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function update(ItemGroupRequest $request, ItemGroup $itemGroup): RedirectResponse
    {
        $data = $request->validated();

        // Changing the prefix once codes are issued would leave the group's existing
        // items orphaned from its naming scheme.
        if ($itemGroup->items()->exists() && $data['prefix'] !== $itemGroup->prefix) {
            return back()->withInput()
                ->with('error', 'The prefix cannot be changed once items exist in this group.');
        }

        $itemGroup->update($data);

        return redirect()->route('item-groups.index')
            ->with('success', "Item group \"{$itemGroup->name}\" has been updated.");
    }

    public function destroy(ItemGroup $itemGroup): RedirectResponse
    {
        if ($itemGroup->items()->exists()) {
            return back()->with('error', "\"{$itemGroup->name}\" has items and cannot be deleted.");
        }

        $name = $itemGroup->name;
        $itemGroup->delete();

        return redirect()->route('item-groups.index')
            ->with('success', "Item group \"{$name}\" has been deleted.");
    }
}
