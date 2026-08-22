@extends('layouts.app')

@section('title', $item->code.' — Photo')

@section('content')
    <x-page-title :title="$item->code">
        <x-slot:actions>
            <a href="{{ route('items.photos.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Back to photos
            </a>
            @can('item.view')
                <a href="{{ route('items.show', $item) }}" class="btn btn-primary">
                    <i class="ri-price-tag-3-line"></i> Item
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <p class="text-muted fs-13 mb-3">
                        {{ $item->name }}@if ($item->itemGroup) — {{ $item->itemGroup->name }}@endif
                    </p>

                    {{-- Natural size: no width or height, so the browser shows the file
                         as uploaded and scrolls if it overflows. --}}
                    <div style="overflow: auto;">
                        <img src="{{ route('items.photo.raw', $item) }}" alt="{{ $item->code }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
