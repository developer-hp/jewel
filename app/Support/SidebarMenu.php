<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Str;

/**
 * Turns config/menu.php into what the sidebar actually renders.
 *
 * Permission filtering and active/open state are worked out here rather than in
 * Blade, so the view stays a loop and the rules are testable on their own.
 */
class SidebarMenu
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $childrenMap = null;

    /** @var bool|null */
    private static ?bool $childrenMapFiltered = null;

    /**
     * Visible sections, each with its visible items. A section whose every entry
     * is hidden by permissions drops out entirely, so no orphan headings are left.
     *
     * @return array<int, array{key: string, title: string, items: array<int, array<string, mixed>>}>
     */
    public static function sections(bool $filterPermissions = true): array
    {
        $order = AppSetting::current()->menuOrder();

        // Build a map of all children from all groups, keyed by their child key.
        // This allows children to be found when they've been moved to a different group.
        self::buildChildrenMap(config('menu', []), $filterPermissions);

        $sections = collect(config('menu', []))
            ->map(function (array $section) use ($filterPermissions, $order) {
                $sectionKey = $section['title'] ?? '';
                $items = self::items($section['items'] ?? [], $filterPermissions);

                if ($items === []) {
                    return null;
                }

                // Apply saved order to items within this section
                $items = self::applyOrder($items, $order, 'section:'.$sectionKey);

                return [
                    'key' => $sectionKey,
                    'title' => $sectionKey,
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $result = self::applyOrder($sections, $order, 'sections');
        self::$childrenMap = null;
        self::$childrenMapFiltered = null;

        return $result;
    }

    /**
     * Build a map of all children from all groups, indexed by child key.
     * Used to look up children when they've been moved to a different group.
     *
     * @return void
     */
    private static function buildChildrenMap(array $sections, bool $filterPermissions): void
    {
        if (self::$childrenMap !== null && self::$childrenMapFiltered === $filterPermissions) {
            return;
        }

        self::$childrenMap = [];
        self::$childrenMapFiltered = $filterPermissions;

        foreach ($sections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (isset($item['children'])) {
                    foreach ($item['children'] as $child) {
                        $childNode = self::leaf($child, $filterPermissions);
                        if ($childNode !== null) {
                            self::$childrenMap[$childNode['key']] = $childNode;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function items(array $items, bool $filterPermissions = true): array
    {
        return collect($items)
            ->map(fn (array $item) => isset($item['children'])
                ? self::group($item, $filterPermissions)
                : self::leaf($item, $filterPermissions))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function leaf(array $item, bool $filterPermissions = true): ?array
    {
        if ($filterPermissions && ! self::allowed($item)) {
            return null;
        }

        return [
            'type' => 'leaf',
            'key' => $item['route'],
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
    private static function group(array $item, bool $filterPermissions = true): ?array
    {
        $slug = Str::slug($item['label']);
        $order = AppSetting::current()->menuOrder();
        $groupOrder = $order['group:'.$slug] ?? [];

        // Build the full set of children for this group:
        // 1. Start with configured children, filtered by permissions
        $children = self::items($item['children'], $filterPermissions);
        $childrenByKey = [];
        foreach ($children as $child) {
            $childrenByKey[$child['key'] ?? null] = $child;
        }

        // 2. If a saved order exists for this group, use it to determine which children appear.
        // This allows children to be moved between groups: look them up from the global map.
        if (!empty($groupOrder)) {
            $filteredChildren = [];
            foreach ($groupOrder as $childKey) {
                // Try to find the child in the current group first, then in the global map
                $child = $childrenByKey[$childKey] ?? self::$childrenMap[$childKey] ?? null;
                if ($child !== null) {
                    $filteredChildren[] = $child;
                }
            }
            $children = $filteredChildren;
        }

        if ($children === []) {
            return null;
        }

        $children = self::applyOrder($children, $order, 'group:'.$slug);

        return [
            'type' => 'group',
            'key' => $slug,
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'children' => $children,
            // Open the group holding the current page, decided server-side so the
            // right one is already expanded in the delivered HTML.
            'open' => collect($children)->contains(fn (array $child) => $child['active']),
            'id' => 'menu-'.$slug,
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

    /**
     * Sort items by the saved order for a scope.
     *
     * Items not in the saved order keep their original relative position and sort
     * after everything that is listed. This makes the order backward-compatible:
     * a menu entry added later shows up correctly with zero migration.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<int, string>>  $order
     * @return array<int, array<string, mixed>>
     */
    private static function applyOrder(array $items, array $order, string $scope): array
    {
        $scopeOrder = $order[$scope] ?? [];

        if ($scopeOrder === []) {
            return $items;
        }

        $listed = [];
        $unlisted = [];

        foreach ($items as $item) {
            $key = $item['key'] ?? null;
            if ($key !== null && in_array($key, $scopeOrder, true)) {
                $listed[$key] = $item;
            } else {
                $unlisted[] = $item;
            }
        }

        $sorted = [];
        foreach ($scopeOrder as $key) {
            if (isset($listed[$key])) {
                $sorted[] = $listed[$key];
            }
        }

        return array_merge($sorted, $unlisted);
    }
}
