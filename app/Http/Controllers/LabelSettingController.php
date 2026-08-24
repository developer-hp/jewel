<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabelSettingRequest;
use App\Models\Item;
use App\Models\LabelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * The named tag templates. A metal type points at one; everything else prints with
 * whichever is flagged default.
 */
class LabelSettingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:label_setting.view', only: ['index']),
            new Middleware('permission:label_setting.create', only: ['create', 'store', 'duplicate']),
            new Middleware('permission:label_setting.edit', only: ['edit', 'update', 'setDefault']),
            new Middleware('permission:label_setting.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        // Reading the default here also creates it on a fresh install, so the
        // listing is never empty and there is always something to print with.
        LabelSetting::default();

        return view('label-settings.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = LabelSetting::query()->select('label_settings.*')->withCount('metalTypes');

        return DataTables::eloquent($query)
            ->editColumn('name', fn (LabelSetting $setting) => e($setting->name)
                .($setting->is_default ? ' <span class="badge bg-success ms-1">Default</span>' : ''))
            ->editColumn('layout', fn (LabelSetting $setting) => '<span class="badge bg-primary-subtle text-primary">'
                .e($setting->layoutLabel()).'</span>')
            ->addColumn('size', fn (LabelSetting $setting) => $this->trim($setting->tag_width_mm)
                .' &times; '.$this->trim($setting->tag_height_mm).' mm')
            ->addColumn('qr', fn (LabelSetting $setting) => view('components.status-badge', [
                'active' => $setting->qr_enabled,
                'labels' => ['On', 'Off'],
            ])->render())
            ->addColumn('action', fn (LabelSetting $setting) => view('label-settings.partials.actions', compact('setting'))->render())
            ->orderColumn('size', 'tag_width_mm $1, tag_height_mm $1')
            ->orderColumn('qr', 'qr_enabled $1')
            ->rawColumns(['name', 'layout', 'size', 'qr', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('label-settings.create', [
            'setting' => new LabelSetting,
            'previewItem' => Item::latest('id')->first(),
        ]);
    }

    public function store(LabelSettingRequest $request): RedirectResponse
    {
        $setting = LabelSetting::create($request->validated());

        // The very first template has to be the default, or nothing can print.
        if (LabelSetting::count() === 1) {
            $setting->makeDefault();
        }

        return redirect()->route('label-settings.edit', $setting)
            ->with('success', "Label template \"{$setting->name}\" has been created.");
    }

    public function edit(LabelSetting $labelSetting): View
    {
        return view('label-settings.edit', [
            'setting' => $labelSetting,
            // Judging a tag layout means seeing it; offer the newest item.
            'previewItem' => Item::latest('id')->first(),
        ]);
    }

    public function update(LabelSettingRequest $request, LabelSetting $labelSetting): RedirectResponse
    {
        $labelSetting->update($request->validated());

        return redirect()->route('label-settings.edit', $labelSetting)
            ->with('success', "Label template \"{$labelSetting->name}\" has been saved.");
    }

    /**
     * A copy to work from, so a second layout does not start from the defaults.
     */
    public function duplicate(LabelSetting $labelSetting): RedirectResponse
    {
        $copy = $labelSetting->replicate();
        $copy->name = $this->availableName($labelSetting->name);
        // replicate() ignores fillable, so without this the copy would carry the
        // default flag across and steal it on save.
        $copy->forceFill(['is_default' => false]);
        $copy->save();

        return redirect()->route('label-settings.edit', $copy)
            ->with('success', "Copied to \"{$copy->name}\".");
    }

    public function setDefault(LabelSetting $labelSetting): RedirectResponse
    {
        $labelSetting->makeDefault();

        return back()->with('success', "\"{$labelSetting->name}\" is now the default template.");
    }

    public function destroy(LabelSetting $labelSetting): RedirectResponse
    {
        if ($labelSetting->is_default) {
            return back()->with('error',
                'The default template cannot be deleted. Make another template the default first.');
        }

        if ($labelSetting->metalTypes()->exists()) {
            return back()->with('error',
                "\"{$labelSetting->name}\" is selected by a metal type and cannot be deleted.");
        }

        $name = $labelSetting->name;
        $labelSetting->delete();

        return redirect()->route('label-settings.index')
            ->with('success', "Label template \"{$name}\" has been deleted.");
    }

    /**
     * "Copy of X", then "Copy of X (2)" — the name column is unique.
     */
    private function availableName(string $name): string
    {
        $base = mb_substr("Copy of {$name}", 0, 60);
        $candidate = $base;

        for ($n = 2; LabelSetting::where('name', $candidate)->exists(); $n++) {
            $suffix = " ({$n})";
            $candidate = mb_substr($base, 0, 60 - mb_strlen($suffix)).$suffix;
        }

        return $candidate;
    }

    /**
     * 110.00 reads as 110, 32.50 as 32.5 — a tag size is not an accounting figure.
     */
    private function trim(float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
