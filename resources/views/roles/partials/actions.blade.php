@can('role.view')
    <a href="{{ route('roles.show', $role) }}" class="text-reset me-2" title="View">
        <i class="ri-eye-fill fs-18"></i>
    </a>
@endcan

@if ($role->name !== 'Super Admin')
    @can('role.edit')
        <a href="{{ route('roles.edit', $role) }}" class="text-reset me-2" title="Edit">
            <i class="ri-pencil-fill fs-18"></i>
        </a>
    @endcan

    @can('role.delete')
        <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline"
            onsubmit="return confirm('Delete role {{ $role->name }}?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
                <i class="ri-delete-bin-2-fill fs-18"></i>
            </button>
        </form>
    @endcan
@endif
