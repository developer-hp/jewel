<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns config/menu.php into what the sidebar actually renders.
 *
 * Permission filtering and active/open state are worked out here rather than in
 * Blade, so the view stays a loop and the rules are testable on their own.
 */
class SidebarMenu
{
    /**
     * Visible sections, each with its visible items. A section whose every entry
     * is hidden by permissions drops out entirely, so no orphan headings are left.
     *
     * @return array<int, array{title: string, items: array<int, array<string, mixed>>}>
     */
    public static function sections(): array
    {
        return collect(config('menu', []))
            ->map(fn (array $section) => [
                'title' => $section['title'] ?? '',
                'items' => self::items($section['items'] ?? []),
            ])
            ->reject(fn (array $section) => $section['items'] === [])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function items(array $items): array
    {
        return collect($items)
            ->map(fn (array $item) => isset($item['children'])
                ? self::group($item)
                : self::leaf($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function leaf(array $item): ?array
    {
        if (! self::allowed($item)) {
            return null;
        }

        return [
            'type' => 'leaf',
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'url' => route($item['route']),
            'active' => self::matches($item),
        ];
    }

    /**
     * A group is only worth rendering when at least one child survives the
     * permission filter — the group itself carries no permission of its own.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function group(array $item): ?array
    {
        $children = self::items($item['children']);

        if ($children === []) {
            return null;
        }

        return [
            'type' => 'group',
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'children' => $children,
            // Open the group holding the current page, decided server-side so the
            // right one is already expanded in the delivered HTML.
            'open' => collect($children)->contains(fn (array $child) => $child['active']),
            'id' => 'menu-'.Str::slug($item['label']),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function allowed(array $item): bool
    {
        $permission = $item['can'] ?? null;

        if ($permission === null) {
            return true;
        }

        return (bool) auth()->user()?->can($permission);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function matches(array $item): bool
    {
        $patterns = (array) ($item['active'] ?? $item['route']);

        return request()->routeIs(...$patterns);
    }
}
