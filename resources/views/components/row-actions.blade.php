{{--
    Standard edit/delete row actions for a master listing, as soft icon buttons
    (theme/ui-buttons.html btn-soft-* variants + the .btn-icon size in app-custom.css).

    $editUrl / $deleteUrl  — route URLs (null hides that action)
    $editPermission / $deletePermission — Spatie permission names
    $confirm — the confirmation message shown before deleting
    $before  — slot for extra buttons rendered ahead of edit (view, print, …)
--}}
@props([
    'editUrl' => null,
    'deleteUrl' => null,
    'editPermission' => null,
    'deletePermission' => null,
    'confirm' => 'Delete this record?',
])

<div class="row-actions">
    {{ $before ?? '' }}

    @if ($editUrl && (! $editPermission || auth()->user()->can($editPermission)))
        <a href="{{ $editUrl }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endif

    @if ($deleteUrl && (! $deletePermission || auth()->user()->can($deletePermission)))
        {{-- No form: the delegated handler in layouts/partials/ui-feedback confirms
             through SweetAlert2 and sends the DELETE itself, so the row can go
             without the page going with it. --}}
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ $deleteUrl }}" data-delete-confirm="{{ $confirm }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endif
</div>
