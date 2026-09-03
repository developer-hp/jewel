{{--
    Ctrl+M — jump to any page without going through the sidebar.

    The contents come from App\Support\CommandPalette, which reads the same
    config/menu.php the sidebar does and has already dropped anything this user
    cannot reach. Nothing here needs its own permission check.

    Rendered into every page rather than fetched on open: the whole menu is a few
    kilobytes, and a palette that has to wait for the network is not a shortcut.
--}}
@php($paletteGroups = App\Support\CommandPalette::groups())

<div class="modal fade command-palette" id="commandPalette" tabindex="-1" aria-labelledby="commandPaletteLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header align-items-center gap-2">
                <label class="modal-title fs-16 mb-0" id="commandPaletteLabel" for="commandPaletteFilter">
                    Go to
                </label>

                <div class="palette-search flex-grow-1">
                    <i class="ri-search-line"></i>
                    <input type="text" class="form-control" id="commandPaletteFilter" autocomplete="off"
                        placeholder="Type to filter…" aria-label="Filter menu">
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" id="commandPaletteBody">
                @include('layouts.partials._palette-groups', ['groups' => $paletteGroups, 'filter' => true])

                <p class="text-muted text-center py-4 mb-0 d-none" id="commandPaletteEmpty">
                    Nothing matches that.
                </p>
            </div>

            <div class="modal-footer justify-content-start gap-3 fs-12 text-muted">
                <span><kbd>↑</kbd> <kbd>↓</kbd> move</span>
                <span><kbd>Enter</kbd> open</span>
                <span><kbd>Esc</kbd> close</span>
                <span class="ms-auto"><kbd>Ctrl</kbd> + <kbd>M</kbd></span>
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        (function () {
            'use strict';

            var modalEl = document.getElementById('commandPalette');
            var filter = document.getElementById('commandPaletteFilter');
            var empty = document.getElementById('commandPaletteEmpty');

            if (!modalEl || typeof bootstrap === 'undefined') {
                return;
            }

            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            var items = Array.prototype.slice.call(modalEl.querySelectorAll('[data-palette-item]'));
            var groups = Array.prototype.slice.call(modalEl.querySelectorAll('[data-palette-group]'));
            var cursor = -1;

            function visible() {
                return items.filter(function (item) {
                    return !item.classList.contains('d-none');
                });
            }

            function highlight(index) {
                var shown = visible();

                items.forEach(function (item) {
                    item.classList.remove('is-active');
                });

                if (!shown.length) {
                    cursor = -1;

                    return;
                }

                // Wrap at both ends, so holding an arrow key never dead-ends.
                cursor = (index + shown.length) % shown.length;
                shown[cursor].classList.add('is-active');
                shown[cursor].scrollIntoView({ block: 'nearest' });
            }

            function apply(term) {
                var needle = term.trim().toLowerCase();

                items.forEach(function (item) {
                    var hit = needle === '' || item.dataset.paletteText.indexOf(needle) !== -1;
                    item.classList.toggle('d-none', !hit);
                });

                // A heading with nothing left under it is noise.
                groups.forEach(function (group) {
                    var any = group.querySelector('[data-palette-item]:not(.d-none)');
                    group.classList.toggle('d-none', !any);
                });

                empty.classList.toggle('d-none', visible().length > 0);

                // Filtering points at the first hit, so Enter is enough once the
                // search has narrowed to what you meant.
                highlight(0);
            }

            // --- opening ---------------------------------------------------------
            document.addEventListener('keydown', function (e) {
                // Ctrl+M, or Cmd+M on a Mac keyboard.
                if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'm' || e.key === 'M')) {
                    e.preventDefault();
                    modal.toggle();
                }
            });

            document.querySelectorAll('[data-command-palette-open]').forEach(function (trigger) {
                trigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    modal.show();
                });
            });

            modalEl.addEventListener('shown.bs.modal', function () {
                filter.value = '';
                apply('');
                filter.focus();
            });

            // --- driving it -------------------------------------------------------
            filter.addEventListener('input', function () {
                apply(filter.value);
            });

            modalEl.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    highlight(cursor + 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    highlight(cursor - 1);
                } else if (e.key === 'Enter') {
                    var shown = visible();

                    if (cursor > -1 && shown[cursor]) {
                        e.preventDefault();
                        window.location.href = shown[cursor].href;
                    }
                }
            });

            items.forEach(function (item) {
                item.addEventListener('mouseenter', function () {
                    highlight(visible().indexOf(item));
                });
            });
        })();
    </script>
@endpush
