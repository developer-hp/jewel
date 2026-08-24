@extends('layouts.app')

@section('title', 'Edit Label Template')

@section('content')
    <x-page-title title="Label Template — {{ $setting->name }}">
        <x-slot:actions>
            @can('label_setting.edit')
                @unless ($setting->is_default)
                    {{-- Promotion has its own route, so is_default is never a form
                         field and two defaults cannot be submitted. --}}
                    <form action="{{ route('label-settings.default', $setting) }}" method="POST" class="d-inline"
                        data-confirm="Make &quot;{{ $setting->name }}&quot; the default template?">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="ri-star-fill"></i> Make Default
                        </button>
                    </form>
                @endunless
            @endcan

            <a href="{{ route('label-settings.index') }}" class="btn btn-light">
                <i class="ri-list-check"></i> All Templates
            </a>
        </x-slot:actions>
    </x-page-title>

    @unless ($previewItem)
        <div class="alert alert-info">
            <i class="ri-information-line me-1"></i>
            Add an item to preview the tag.
        </div>
    @endunless

    <form method="POST" action="{{ route('label-settings.update', $setting) }}">
        @include('label-settings._form')
    </form>
@endsection
