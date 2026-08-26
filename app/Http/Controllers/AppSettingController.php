<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppSettingRequest;
use App\Models\AppSetting;
use App\Models\MetalType;
use App\Models\Purity;
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
        return view('app-settings.edit', [
            'settings' => AppSetting::current(),
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
            'purities' => Purity::active()->with('metalType')->ordered()->get()
                ->mapWithKeys(fn (Purity $purity) => [$purity->id => $purity->label()]),
        ]);
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
            'firm_website',
            'firm_office_phone',
            'angadiya_columns',
            'angadiya_slip_height_mm',
            'hallmark_next_lot_no',
            'repair_ref_prefix',
            'repair_next_ref_no',
            'repair_contact_no',
            'repair_metal_type_id',
            'repair_purity_id',
            'repair_terms',
            'order_ref_prefix',
            'order_next_ref_no',
            'order_contact_no',
            'order_terms',
            'og_estimate_ref_prefix',
            'og_estimate_next_ref_no',
            'voucher_ref_prefix',
            'voucher_next_ref_no',
            'item_estimate_ref_prefix',
            'item_estimate_next_ref_no',
            'gst_percent',
            'cash_entry_ref_prefix',
            'cash_entry_next_ref_no',
            'supplier_order_next_form_no',
            'supplier_order_header',
            'sidebar_user_bg_from',
            'sidebar_user_bg_to',
            'sidebar_user_text_color',
            'table_header_bg_light',
            'table_header_bg_dark',
            'settings_cache_enabled',
        ]));

        // Stored as what is hidden, so a section added later shows up by default
        // rather than staying invisible until someone notices. Left alone entirely
        // when the form did not send the field.
        if ($request->has('dashboard_sections')) {
            $shown = $request->safe()->input('dashboard_sections', []);

            $settings->dashboard_hidden_sections = collect(config('dashboard', []))
                ->pluck('key')
                ->reject(fn (string $key) => in_array($key, $shown, true))
                ->values()
                ->all();
        }

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
