<div class="row-actions">
    @can('og_estimate.print')
        <button type="button" class="btn btn-sm btn-secondary btn-icon print-one" data-id="{{ $estimate->id }}"
            title="Print this estimate">
            <i class="ri-printer-fill"></i>
        </button>
    @endcan

    @can('og_estimate.create')
        {{-- Copies onto a fresh ref; the original keeps its number. --}}
        <form action="{{ route('og-estimates.copy', $estimate) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-warning btn-icon" title="Copy to a new estimate">
                <i class="ri-file-copy-fill"></i>
            </button>
        </form>
    @endcan

    @can('og_estimate.edit')
        <a href="{{ route('og-estimates.edit', $estimate) }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endcan

    @can('og_estimate.delete')
        <form action="{{ route('og-estimates.destroy', $estimate) }}" method="POST"
            onsubmit="return confirm(@js('Delete estimate '.$estimate->reference().'?'));">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Delete">
                <i class="ri-delete-bin-2-fill"></i>
            </button>
        </form>
    @endcan
</div>
