@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-title title="Dashboard" />

    <div class="row row-cols-1 row-cols-lg-4 row-cols-md-2">
        @foreach ([
            ['label' => 'Total Users', 'value' => $userCount, 'icon' => 'ri-group-fill', 'bg' => 'bg-primary'],
            ['label' => 'Active Users', 'value' => $activeUserCount, 'icon' => 'ri-user-follow-fill', 'bg' => 'bg-success'],
            ['label' => 'Roles', 'value' => $roleCount, 'icon' => 'ri-shield-user-fill', 'bg' => 'bg-info'],
            ['label' => 'Permissions', 'value' => $permissionCount, 'icon' => 'ri-key-2-fill', 'bg' => 'bg-warning'],
        ] as $card)
            <div class="col">
                <div class="card widget-icon-box">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <h5 class="text-muted text-uppercase fs-13 mt-0">{{ $card['label'] }}</h5>
                                <h3 class="my-2">{{ number_format($card['value']) }}</h3>
                            </div>
                            <div class="avatar-md {{ $card['bg'] }} rounded-circle d-flex align-items-center justify-content-center">
                                <i class="{{ $card['icon'] }} fs-24 text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title mb-3">Welcome, {{ auth()->user()->name }}</h4>
                    <p class="text-muted mb-2">
                        You are signed in as <strong>{{ auth()->user()->username }}</strong>
                        with the role <strong>{{ auth()->user()->roleLabel() }}</strong>.
                    </p>
                    <p class="text-muted mb-0">
                        Stock, item and quotation modules will appear here once they are built.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
