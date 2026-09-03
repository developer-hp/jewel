@extends('layouts.app')

@section('title', 'Menu Order')

@section('content')
    <x-page-title title="Menu Order">
        <x-slot:description>
            Drag to reorder the sidebar menu. Changes take effect immediately.
        </x-slot:description>
    </x-page-title>

    <form method="POST" action="{{ route('menu-order.update') }}" id="menuOrderForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="order" id="orderInput">

        <div class="card">
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <strong>Drag to reorder:</strong> Sections reorder at the top level. Items can move between sections and be reordered. Children can move between groups and be reordered. Moving a child to a different section moves its entire group.
                </div>

                <div id="sectionsContainer" class="menu-sections" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    @foreach ($sections as $section)
                        <div class="menu-section card" data-section-key="{{ $section['key'] }}" style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                <i class="ri-drag-move-2-line text-muted section-handle" style="cursor: grab;"></i>
                                <h5 class="mb-0">{{ $section['title'] }}</h5>
                            </div>

                            <ul class="section-items list-unstyled" data-section-key="{{ $section['key'] }}" data-parent-type="section" data-parent-key="{{ $section['key'] }}" style="padding-left: 2rem; min-height: 50px;">
                                @foreach ($section['items'] as $item)
                                    <li class="menu-item mb-2" data-item-key="{{ $item['key'] }}" data-item-type="{{ $item['type'] }}">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; background: white; border: 1px solid #e0e0e0; border-radius: 4px;">
                                            <i class="ri-drag-move-2-line text-muted item-handle" style="cursor: grab; flex-shrink: 0;"></i>
                                            @if (isset($item['icon']))
                                                <i class="{{ $item['icon'] }}"></i>
                                            @endif
                                            <span>{{ $item['label'] }}</span>
                                        </div>

                                        @if ($item['type'] === 'group' && !empty($item['children']))
                                            <ul class="group-children list-unstyled" data-group-key="{{ $item['key'] }}" data-parent-type="group" data-parent-key="{{ $item['key'] }}" style="padding-left: 2rem; min-height: 30px; margin-top: 0.5rem;">
                                                @foreach ($item['children'] as $child)
                                                    <li class="menu-child mb-1" data-child-key="{{ $child['key'] }}">
                                                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.5rem; background: #f9f9f9; border: 1px solid #f0f0f0; border-radius: 3px;">
                                                            <i class="ri-drag-move-2-line text-muted child-handle" style="cursor: grab; flex-shrink: 0; font-size: 0.875rem;"></i>
                                                            @if (isset($child['icon']))
                                                                <i class="{{ $child['icon'] }}"></i>
                                                            @endif
                                                            <span class="fs-13">{{ $child['label'] }}</span>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Save Order</button>
                    <button type="button" class="btn btn-secondary" id="resetButton">Reset to Default</button>
                </div>
            </div>
        </div>
    </form>

    @push('js')
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
        <script>
            $(function () {
                // Sections are sortable among themselves only
                $('#sectionsContainer').sortable({
                    handle: '.section-handle',
                    placeholder: 'sortable-placeholder',
                    items: '> .menu-section',
                    update: updateOrder,
                });

                // Items within sections can move between ANY section
                $('.section-items').sortable({
                    handle: '.item-handle',
                    placeholder: 'sortable-placeholder',
                    connectWith: '.section-items',
                    update: updateOrder,
                });

                // Children within groups can move between ANY group (or out to a section)
                $('.group-children').sortable({
                    handle: '.child-handle',
                    placeholder: 'sortable-placeholder',
                    connectWith: '.group-children, .section-items',
                    update: updateOrder,
                });

                // Also make section-items accept children from groups
                $('.section-items').sortable('option', 'connectWith', '.section-items, .group-children');

                function updateOrder() {
                    const order = {
                        sections: [],
                    };

                    // Collect section order
                    $('#sectionsContainer .menu-section').each(function () {
                        const sectionKey = $(this).data('section-key');
                        order.sections.push(sectionKey);
                        order['section:' + sectionKey] = [];

                        // Collect all items (both leaf items and groups) in this section
                        $(this)
                            .find('> .section-items > .menu-item')
                            .each(function () {
                                const itemKey = $(this).data('item-key');
                                order['section:' + sectionKey].push(itemKey);
                            });

                        // Also collect any children dragged directly into section (orphaned from groups)
                        $(this)
                            .find('> .section-items > .menu-child')
                            .each(function () {
                                const childKey = $(this).data('child-key');
                                order['section:' + sectionKey].push(childKey);
                            });
                    });

                    // Collect children for each group (they may be in any section now)
                    $('.group-children').each(function () {
                        const groupKey = $(this).data('group-key');
                        order['group:' + groupKey] = [];

                        $(this)
                            .find('> .menu-child')
                            .each(function () {
                                const childKey = $(this).data('child-key');
                                order['group:' + groupKey].push(childKey);
                            });
                    });

                    $('#orderInput').val(JSON.stringify(order));
                }

                // Initial order population
                updateOrder();

                // Reset button
                $('#resetButton').click(function () {
                    if (confirm('Reset menu to default order?')) {
                        $('#orderInput').val('{}');
                        $('#menuOrderForm').submit();
                    }
                });
            });
        </script>

        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

        <style>
            .sortable-placeholder {
                background-color: #e7f3ff;
                border: 2px dashed #2196f3;
                height: 32px;
                margin-bottom: 0.5rem;
                border-radius: 4px;
            }

            .ri-drag-move-2-line {
                cursor: grab;
            }

            .ri-drag-move-2-line:active {
                cursor: grabbing;
            }

            .ui-sortable-helper {
                opacity: 0.8;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }
        </style>
    @endpush
@endsection
