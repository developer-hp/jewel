<?php

/*
|--------------------------------------------------------------------------
| Sidebar Menu
|--------------------------------------------------------------------------
|
| The sidebar is rendered from this tree by layouts/partials/sidebar.blade.php.
|
| Per entry:
|   label    shown in the menu
|   icon     Remix icon class — must exist in the theme's icons.min.css, which
|            SidebarTest checks by sweeping the rendered page
|   route    route name to link to
|   active   route pattern(s) that light the entry up; defaults to `route`
|   can      permission required to see it; omit for always-visible
|   children a collapsible group instead of a link (groups carry no route)
|
| Plain arrays only — no closures — so `php artisan config:cache` still works.
|
*/

return [

    [
        'title' => 'Main',
        'items' => [
            [
                'label' => 'Dashboard',
                'icon' => 'ri-dashboard-2-fill',
                'route' => 'dashboard',
            ],
            [
                // Opened every morning, so it sits above everything it feeds.
                // Links to the bulk entry screen but highlights for any rate page.
                'label' => 'Daily Rates',
                'icon' => 'ri-exchange-funds-fill',
                'route' => 'rates.today',
                'active' => 'rates.*',
                'can' => 'metal_rate.view',
            ],

            // What the shop holds.
            [
                'label' => 'Stock',
                'icon' => 'ri-price-tag-3-fill',
                'children' => [
                    [
                        'label' => 'Items',
                        // `items.*` deliberately, so it does not also match `item-groups.*`.
                        'route' => 'items.index',
                        'active' => 'items.*',
                        'can' => 'item.view',
                    ],
                    [
                        'label' => 'Item Photos',
                        'route' => 'items.photos.index',
                        'active' => 'items.photos.*',
                        'can' => 'item.view',
                    ],
                    [
                        'label' => 'Item Lots',
                        'route' => 'lots.index',
                        'active' => 'lots.*',
                        'can' => 'item_lot.view',
                    ],
                ],
            ],

            // Customer orders, and the pieces made against them.
            [
                'label' => 'Orders',
                'icon' => 'ri-shopping-bag-fill',
                'children' => [
                    [
                        'label' => 'Order Forms',
                        'route' => 'order-forms.index',
                        'active' => 'order-forms.*',
                        'can' => 'order_form.view',
                    ],
                    [
                        // Making a piece to order — the full item detail, so it gets
                        // a screen and an entry of its own.
                        'label' => 'Order Items',
                        'route' => 'order-items.create',
                        'active' => 'order-items.*',
                        'can' => 'order_form.edit',
                    ],
                ],
            ],

            // Customer goods in for repair, and the pieces coming back.
            [
                'label' => 'Repairs',
                'icon' => 'ri-tools-fill',
                'children' => [
                    [
                        'label' => 'Repair Forms',
                        'route' => 'repair-forms.index',
                        'active' => 'repair-forms.*',
                        'can' => 'repair_form.view',
                    ],
                    [
                        // Booking a repaired piece back into stock — its own screen,
                        // so it gets its own entry rather than hiding in the item form.
                        'label' => 'Repair Items',
                        'route' => 'repair-items.create',
                        'active' => 'repair-items.*',
                        'can' => 'repair_form.edit',
                    ],
                ],
            ],

            // Goods leaving the premises for a third party.
            [
                'label' => 'Dispatch',
                'icon' => 'ri-send-plane-fill',
                'children' => [
                    [
                        'label' => 'Angadiya',
                        'route' => 'angadiyas.index',
                        'active' => 'angadiyas.*',
                        'can' => 'angadiya.view',
                    ],
                    [
                        'label' => 'Hallmark',
                        'route' => 'hallmarks.index',
                        'active' => 'hallmarks.*',
                        'can' => 'hallmark.view',
                    ],
                ],
            ],

            // Two entries earn a group, the same rule Stock and Repairs follow.
            [
                'label' => 'Suppliers',
                'icon' => 'ri-hand-coin-fill',
                'children' => [
                    [
                        'label' => 'Supplier Orders',
                        'route' => 'supplier-orders.index',
                        'active' => 'supplier-orders.*',
                        'can' => 'supplier_order.view',
                    ],
                    [
                        'label' => 'Supplier Hisab',
                        'route' => 'supplier-hisabs.index',
                        'active' => 'supplier-hisabs.*',
                        'can' => 'supplier_hisab.view',
                    ],
                ],
            ],
        ],
    ],

    [
        'title' => 'Manage',
        'items' => [
            [
                'label' => 'Masters',
                'icon' => 'ri-database-2-fill',
                'children' => [
                    [
                        'label' => 'Metal Types',
                        'route' => 'metal-types.index',
                        'active' => 'metal-types.*',
                        'can' => 'metal_type.view',
                    ],
                    [
                        'label' => 'Purities',
                        'route' => 'purities.index',
                        'active' => 'purities.*',
                        'can' => 'purity.view',
                    ],
                    [
                        'label' => 'Item Groups',
                        'route' => 'item-groups.index',
                        'active' => 'item-groups.*',
                        'can' => 'item_group.view',
                    ],
                    [
                        'label' => 'Stock Groups',
                        'route' => 'stock-groups.index',
                        'active' => 'stock-groups.*',
                        'can' => 'stock_group.view',
                    ],
                    [
                        // Stones and diamonds are one table behind two screens, so
                        // they share the single `stone` permission module.
                        'label' => 'Stones',
                        'route' => 'stones.index',
                        'active' => 'stones.*',
                        'can' => 'stone.view',
                    ],
                    [
                        'label' => 'Diamonds',
                        'route' => 'diamonds.index',
                        'active' => 'diamonds.*',
                        'can' => 'stone.view',
                    ],
                    [
                        'label' => 'Making Charges',
                        'route' => 'making-charges.index',
                        'active' => 'making-charges.*',
                        'can' => 'making_charge.view',
                    ],
                    [
                        'label' => 'Customers',
                        'route' => 'customers.index',
                        'active' => 'customers.*',
                        'can' => 'customer.view',
                    ],
                    [
                        'label' => 'Order Types',
                        'route' => 'order-types.index',
                        'active' => 'order-types.*',
                        'can' => 'order_type.view',
                    ],
                    [
                        'label' => 'Sales Persons',
                        'route' => 'sales-persons.index',
                        'active' => 'sales-persons.*',
                        'can' => 'sales_person.view',
                    ],
                    [
                        'label' => 'Suppliers',
                        'route' => 'suppliers.index',
                        'active' => 'suppliers.*',
                        'can' => 'supplier.view',
                    ],
                ],
            ],

            [
                'label' => 'Settings',
                'icon' => 'ri-settings-3-fill',
                'children' => [
                    [
                        'label' => 'Appearance',
                        'route' => 'app-settings.edit',
                        'active' => 'app-settings.*',
                        'can' => 'app_setting.view',
                    ],
                    [
                        'label' => 'Label Settings',
                        'route' => 'label-settings.edit',
                        'active' => 'label-settings.*',
                        'can' => 'label_setting.view',
                    ],
                    [
                        'label' => 'Security',
                        'route' => 'security-settings.edit',
                        'active' => 'security-settings.*',
                        'can' => 'app_setting.view',
                    ],
                ],
            ],

            [
                'label' => 'Administration',
                'icon' => 'ri-shield-user-fill',
                'children' => [
                    [
                        'label' => 'Users',
                        'route' => 'users.index',
                        'active' => 'users.*',
                        'can' => 'user.view',
                    ],
                    [
                        'label' => 'Roles',
                        'route' => 'roles.index',
                        'active' => 'roles.*',
                        'can' => 'role.view',
                    ],
                    [
                        'label' => 'Permissions',
                        'route' => 'permissions.index',
                        'active' => 'permissions.*',
                        'can' => 'permission.view',
                    ],
                ],
            ],
        ],
    ],

];
