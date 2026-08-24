<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The Ctrl+M jump-to menu.
 *
 * Built from SidebarMenu::sections(), not from config/menu.php directly, so it
 * inherits the permission filtering already done there — a user can never be shown
 * a shortcut to a page the sidebar would have hidden. Adding a menu entry puts it in
 * the palette with no second edit.
 *
 * The shape is flatter than the sidebar's: a sidebar group becomes a heading of its
 * own, because a palette you have to expand is no faster than the menu it replaces.
 */
class CommandPalette
{
    /**
     * One heading per sidebar group, plus a heading per section for its loose links.
     *
     * @return array<int, array{title: string, accent: string, items: array<int, array{label: string, hint: string, url: string, icon: string, active: bool}>}>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (SidebarMenu::sections() as $section) {
            // Links sitting directly in a section — Dashboard, Daily Rates — have no
            // group of their own, so the section heading stands in for one. They lead
            // their section here just as they do in the sidebar.
            $loose = collect($section['items'])
                ->where('type', 'leaf')
                ->map(fn (array $item) => self::entry($item, $section['title'], $item['icon']))
                ->values()
                ->all();

            if ($loose !== []) {
                $groups[] = ['title' => $section['title'], 'items' => $loose];
            }

            foreach ($section['items'] as $item) {
                if ($item['type'] !== 'group') {
                    continue;
                }

                $groups[] = [
                    'title' => $item['label'],
                    'items' => collect($item['children'])
                        ->map(fn (array $child) => self::entry($child, $item['label'], $item['icon']))
                        ->all(),
                ];
            }
        }

        return self::withAccents($groups);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, hint: string, url: string, icon: string, active: bool}
     */
    private static function entry(array $item, string $hint, ?string $icon): array
    {
        return [
            'label' => $item['label'],
            // The small line under the name, so a bare "Items" or "Voucher" still
            // says where it lives once the list is filtered and the headings scroll away.
            'hint' => $hint,
            'url' => $item['url'],
            // Children carry no icon of their own; they borrow the group's.
            'icon' => $item['icon'] ?? $icon ?? 'ri-circle-fill',
            'active' => $item['active'],
        ];
    }

    /**
     * A colour per heading, cycled through the theme's own palette so the blocks are
     * told apart at a glance rather than read one by one.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private static function withAccents(array $groups): array
    {
        $accents = ['primary', 'info', 'purple', 'warning', 'pink', 'success', 'danger', 'secondary'];

        return collect($groups)
            ->values()
            ->map(fn (array $group, int $i) => $group + [
                'accent' => $accents[$i % count($accents)],
                'id' => 'palette-'.Str::slug($group['title']),
            ])
            ->all();
    }
}
