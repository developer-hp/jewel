@can('user.edit')
    <form action="{{ route('users.toggle-status', $user) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-link text-muted p-0 me-2"
            title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
            <i class="{{ $user->is_active ? 'ri-toggle-fill text-success' : 'ri-toggle-line' }} fs-18"></i>
        </button>
    </form>
    <a href="{{ route('users.edit', $user) }}" class="text-reset me-2" title="Edit">
        <i class="ri-pencil-fill fs-18"></i>
    </a>
@endcan

@can('user.delete')
    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline"
        onsubmit="return confirm('Delete user {{ $user->username }}?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
            <i class="ri-delete-bin-2-fill fs-18"></i>
        </button>
    </form>
@endcan
