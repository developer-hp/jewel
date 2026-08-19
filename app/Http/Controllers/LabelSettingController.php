<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabelSettingRequest;
use App\Models\Item;
use App\Models\LabelSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * There is exactly one settings row, so this is edit/update only.
 */
class LabelSettingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:label_setting.view', only: ['edit']),
            new Middleware('permission:label_setting.edit', only: ['update']),
        ];
    }

    public function edit(): View
    {
        return view('label-settings.edit', [
            'settings' => LabelSetting::current(),
            // Judging a 110 x 18 mm layout means seeing it; offer the newest item.
            'previewItem' => Item::latest('id')->first(),
        ]);
    }

    public function update(LabelSettingRequest $request): RedirectResponse
    {
        LabelSetting::current()->update($request->validated());

        return redirect()->route('label-settings.edit')
            ->with('success', 'Label settings have been saved.');
    }
}
