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
        <form action="{{ $deleteUrl }}" method="POST" onsubmit="return confirm(@js($confirm));">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Delete">
                <i class="ri-delete-bin-2-fill"></i>
            </button>
        </form>
    @endif
</div>
