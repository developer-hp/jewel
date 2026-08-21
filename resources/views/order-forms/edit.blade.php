@extends('layouts.app')

@section('title', 'Edit '.$form->reference())

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title :title="'Order Form — '.$form->reference()" />

    @if ($form->lines->contains(fn ($line) => $line->item !== null))
        <div class="alert alert-info fs-13">
            <i class="ri-information-line me-1"></i>
            Lines with a piece held against them are locked — the reservation and any pinned
            rate must not move under an edit. Use the buttons below each to fix or release the rate.
        </div>
    @endif

    <form method="POST" action="{{ route('order-forms.update', $form) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('order-forms._form')
    </form>

    @php($heldLines = $form->lines->filter(fn ($line) => $line->item !== null))

    @if ($heldLines->isNotEmpty())
        <div class="card mb-4" id="rate-card">
            <div class="card-header py-2">
                <h5 class="mb-0">Fix Today's Rate</h5>
            </div>
            <div class="card-body">
                <p class="text-muted fs-13">
                    Pinning stores today's per-gram rate for the line's purity. A quotation
                    prices from the pinned figure rather than the rate of the day.
                </p>

                <table class="table table-sm table-centered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Piece</th>
                            <th>Description</th>
                            <th>Purity</th>
                            <th>Rate</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heldLines as $line)
                            <tr>
                                <td><code>{{ $line->item->code }}</code></td>
                                <td>{{ $line->description }}</td>
                                <td>{{ $line->purity?->name ?? '—' }}</td>
                                <td>
                                    @if ($line->isRateFixed())
                                        <span class="badge bg-success">{{ $line->rateLabel() }}</span>
                                        <small class="text-muted d-block fs-12">
                                            fixed {{ $line->rate_fixed_at?->format('d-m-Y') }}
                                        </small>
                                    @else
                                        <span class="badge bg-secondary">Open</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('order-forms.fix-rate', $line) }}"
                                        class="d-inline">
                                        @csrf
                                        @if ($line->isRateFixed())
                                            <input type="hidden" name="release" value="1">
                                            <button type="submit" class="btn btn-sm btn-warning">Release</button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-dark">Fix today's rate</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
