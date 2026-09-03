<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\SidebarMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class MenuOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['edit']),
            new Middleware('permission:app_setting.edit', only: ['update']),
        ];
    }

    public function edit(): View
    {
        // Show every node — the admin editing the menu might not have permission to
        // see it in their own sidebar. An admin missing permission for a module still
        // needs to be able to reorder it.
        $sections = SidebarMenu::sections(filterPermissions: false);

        return view('menu-order.edit', compact('sections'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'string', function ($attribute, $value, $fail) {
                $decoded = json_decode($value, true);

                if (! is_array($decoded)) {
                    $fail('Menu order must be a valid JSON object.');

                    return;
                }

                // Every value must be an array of strings (the keys).
                foreach ($decoded as $scope => $keys) {
                    if (! is_string($scope) || ! is_array($keys)) {
                        $fail('Menu order must be an object where each scope maps to an array of keys.');

                        return;
                    }

                    foreach ($keys as $key) {
                        if (! is_string($key)) {
                            $fail('All menu keys must be strings.');

                            return;
                        }
                    }
                }
            }],
        ]);

        $order = json_decode($validated['order'], true);

        if (empty($order)) {
            // Empty order means "reset to default config order".
            AppSetting::current()->update(['menu_order' => null]);
        } else {
            AppSetting::current()->update(['menu_order' => $order]);
        }

        // Clear cache so next request gets the updated menu order
        AppSetting::flushCache();

        return back()->with('success', 'Menu order saved.');
    }
}
