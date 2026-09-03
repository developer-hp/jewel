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

    @unless ($overview)
        {{-- No dashboard.view: the figures are not for this viewer, but the page they
             land on after signing in still has to be useful. Same jump-to menu as
             Ctrl+M, from the same partial, already filtered to what they can reach. --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="mb-0">Where to next?</h5>
                    <span class="text-muted fs-13">
                        Press <kbd>Ctrl</kbd> + <kbd>M</kbd> anywhere to open this as a search.
                    </span>
                </div>

                @include('layouts.partials._palette-groups', ['groups' => $groups, 'filter' => false])

                @if ($groups === [])
                    <p class="text-muted text-center py-4 mb-0">
                        There is nothing your account can reach yet. Ask an administrator
                        to give your role some permissions.
                    </p>
                @endif
            </div>
        </div>
    @else
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
    @endunless
@endsection
