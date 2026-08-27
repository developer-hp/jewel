@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-title title="Dashboard">
        <x-slot:actions>
            <span class="text-muted fs-13">
                {{ auth()->user()->name }} &middot; {{ auth()->user()->roleLabel() }}
                &middot; {{ now()->format('d-m-Y') }}
            </span>
        </x-slot:actions>
    </x-page-title>

    @php($shown = collect($sections)->filter(fn ($section) => isset($data[$section['key']])))

    @forelse ($shown as $section)
        @include('dashboard.sections.'.$section['key'], ['section' => $data[$section['key']]])
    @empty
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ri-dashboard-2-line fs-36 text-muted d-block mb-2"></i>
                <h5>Nothing to show yet</h5>
                <p class="text-muted mb-0">
                    Every dashboard section is either switched off or has no data behind it.
                    @can('app_setting.edit')
                        Turn them back on under <a href="{{ route('app-settings.edit') }}">Appearance</a>.
                    @endcan
                </p>
            </div>
        </div>
    @endforelse
@endsection
