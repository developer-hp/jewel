@if ($isCore)
    <span class="badge bg-dark">core</span>
@else
    @can('permission.edit')
        <a href="{{ route('permissions.edit', $permission) }}" class="text-reset me-2" title="Edit">
            <i class="ri-pencil-fill fs-18"></i>
        </a>
    @endcan

    @can('permission.delete')
        <form action="{{ route('permissions.destroy', $permission) }}" method="POST" class="d-inline"
            onsubmit="return confirm('Delete {{ $permission->name }}? It will be removed from every role.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
                <i class="ri-delete-bin-2-fill fs-18"></i>
            </button>
        </form>
    @endcan
@endif
