<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppSettingRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppSettingController extends Controller implements HasMiddleware
{
    /** Form field name => the column it is stored in. */
    private const LOGO_SLOTS = [
        'logo' => 'logo_path',
        'logo_dark' => 'logo_dark_path',
        'logo_small' => 'logo_small_path',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['edit']),
            new Middleware('permission:app_setting.edit', only: ['update']),
        ];
    }

    public function edit(): View
    {
        return view('app-settings.edit', ['settings' => AppSetting::current()]);
    }

    public function update(AppSettingRequest $request): RedirectResponse
    {
        $settings = AppSetting::current();

        $settings->fill($request->safe()->only([
            'app_name',
            'media_disk',
            'firm_name',
            'firm_city',
            'firm_phone',
            'angadiya_columns',
            'angadiya_slip_height_mm',
            'sidebar_user_bg_from',
            'sidebar_user_bg_to',
            'sidebar_user_text_color',
        ]));

        foreach (self::LOGO_SLOTS as $field => $column) {
            $this->applyLogo($request, $settings, $field, $column);
        }

        $settings->save();

        return redirect()->route('app-settings.edit')
            ->with('success', 'Appearance settings have been saved.');
    }

    /**
     * Replace, clear or leave a logo slot alone, deleting the file it replaces so
     * the disk does not fill with orphans.
     */
    private function applyLogo(AppSettingRequest $request, AppSetting $settings, string $field, string $column): void
    {
        $existing = $settings->{$column};

        if ($request->hasFile($field)) {
            $settings->{$column} = $request->file($field)->store('branding', 'public');
            $this->deleteFile($existing);

            return;
        }

        if ($request->boolean("remove_{$field}")) {
            $settings->{$column} = null;
            $this->deleteFile($existing);
        }
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
