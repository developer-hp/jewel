<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoneMasterRequest;
use App\Models\StoneMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Serves both the Stones and the Diamonds screens. They are one table with a
 * `kind` discriminator, so the kind is derived from the route name and every
 * query is scoped to it.
 */
class StoneMasterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stone.view', only: ['index']),
            new Middleware('permission:stone.create', only: ['create', 'store']),
            new Middleware('permission:stone.edit', only: ['edit', 'update']),
            new Middleware('permission:stone.delete', only: ['destroy']),
        ];
    }

    private function kind(Request $request): string
    {
        return $request->routeIs('diamonds.*')
            ? StoneMaster::KIND_DIAMOND
            : StoneMaster::KIND_STONE;
    }

    /**
     * Labels and route prefix for whichever screen is being served.
     *
     * @return array<string, string>
     */
    private function context(Request $request): array
    {
        $kind = $this->kind($request);

        return [
            'kind' => $kind,
            'routePrefix' => $kind === StoneMaster::KIND_DIAMOND ? 'diamonds' : 'stones',
            'singular' => $kind === StoneMaster::KIND_DIAMOND ? 'Diamond' : 'Stone',
            'plural' => $kind === StoneMaster::KIND_DIAMOND ? 'Diamonds' : 'Stones',
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('stone-masters.index', $this->context($request) + [
            'rateUnits' => StoneMaster::RATE_UNITS,
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $context = $this->context($request);

        $query = StoneMaster::query()
            ->select('stone_masters.*')
            ->kind($context['kind'])
            ->withCount('itemStones')
            ->when($request->filled('rate_unit'), fn ($q) => $q->where('rate_unit', $request->string('rate_unit')->toString()));

        return DataTables::eloquent($query)
            ->addColumn('attributes', fn (StoneMaster $stone) => view('stone-masters.partials.attributes-cell', compact('stone'))->render())
            ->addColumn('rate', fn (StoneMaster $stone) => view('stone-masters.partials.rate-cell', compact('stone'))->render())
            ->addColumn('status', fn (StoneMaster $stone) => view('components.status-badge', ['active' => $stone->is_active])->render())
            ->addColumn('action', fn (StoneMaster $stone) => view('stone-masters.partials.actions', compact('stone') + $context)->render())
            ->orderColumn('rate', 'default_rate $1')
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['attributes', 'rate', 'status', 'action'])
            ->toJson();
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('stone-masters.create', $context + [
            'stone' => new StoneMaster(['kind' => $context['kind'], 'is_active' => true, 'rate_unit' => 'carat', 'default_rate' => 0]),
        ]);
    }

    public function store(StoneMasterRequest $request): RedirectResponse
    {
        $context = $this->context($request);

        // Kind comes from the route, never the request body.
        $stone = StoneMaster::create($request->validated() + ['kind' => $context['kind']]);

        return redirect()->route("{$context['routePrefix']}.index")
            ->with('success', "{$context['singular']} \"{$stone->name}\" has been created.");
    }

    public function edit(Request $request, StoneMaster $stone): View
    {
        $context = $this->context($request);

        // Guards against reaching a diamond through /stones/{id}/edit.
        abort_unless($stone->kind === $context['kind'], 404);

        return view('stone-masters.edit', $context + compact('stone'));
    }

    public function update(StoneMasterRequest $request, StoneMaster $stone): RedirectResponse
    {
        $context = $this->context($request);
        abort_unless($stone->kind === $context['kind'], 404);

        $stone->update($request->validated());

        return redirect()->route("{$context['routePrefix']}.index")
            ->with('success', "{$context['singular']} \"{$stone->name}\" has been updated.");
    }

    public function destroy(Request $request, StoneMaster $stone): RedirectResponse
    {
        $context = $this->context($request);
        abort_unless($stone->kind === $context['kind'], 404);

        if ($stone->itemStones()->exists()) {
            return back()->with('error', "\"{$stone->name}\" is used on existing items and cannot be deleted.");
        }

        $name = $stone->name;
        $stone->delete();

        return redirect()->route("{$context['routePrefix']}.index")
            ->with('success', "{$context['singular']} \"{$name}\" has been deleted.");
    }
}
