<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecuritySettingRequest;
use App\Models\AppSetting;
use App\Services\DeviceSessionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Session and sign-in policy. Backed by the same singleton as Appearance, kept on
 * its own screen so branding and security are not muddled together.
 */
class SecuritySettingController extends Controller implements HasMiddleware
{
    public function __construct(private readonly DeviceSessionRegistry $sessions) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['edit']),
            new Middleware('permission:app_setting.edit', only: ['update']),
        ];
    }

    public function edit(): View
    {
        return view('security-settings.edit', [
            'settings' => AppSetting::current(),
            // Single-device enforcement needs the database session driver to know
            // which sessions belong to whom.
            'driverSupported' => $this->sessions->isSupported(),
            'sessionDriver' => config('session.driver'),
            'sessionLifetime' => (int) config('session.lifetime'),
        ]);
    }

    public function update(SecuritySettingRequest $request): RedirectResponse
    {
        AppSetting::current()->update($request->validated());

        return redirect()->route('security-settings.edit')
            ->with('success', 'Security settings have been saved.');
    }
}
