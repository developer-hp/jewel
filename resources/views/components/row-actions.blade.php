{{--
    Standard edit/delete row actions for a master listing.

    $editUrl / $deleteUrl  — route URLs (null hides that action)
    $editPermission / $deletePermission — Spatie permission names
    $confirm — the confirmation message shown before deleting
--}}
@props([
    'editUrl' => null,
    'deleteUrl' => null,
    'editPermission' => null,
    'deletePermission' => null,
    'confirm' => 'Delete this record?',
])

@if ($editUrl && (! $editPermission || auth()->user()->can($editPermission)))
    <a href="{{ $editUrl }}" class="text-reset me-2" title="Edit">
        <i class="ri-pencil-fill fs-18"></i>
    </a>
@endif

@if ($deleteUrl && (! $deletePermission || auth()->user()->can($deletePermission)))
    <form action="{{ $deleteUrl }}" method="POST" class="d-inline"
        onsubmit="return confirm(@js($confirm));">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
            <i class="ri-delete-bin-2-fill fs-18"></i>
        </button>
    </form>
@endif
