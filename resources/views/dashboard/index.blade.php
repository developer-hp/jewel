@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-title title="Dashboard" />

    @if ($puritiesWithoutRate > 0)
        @can('metal_rate.edit')
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    <i class="ri-alarm-warning-fill me-1"></i>
                    <strong>{{ $puritiesWithoutRate }}</strong> active
                    {{ Str::plural('purity', $puritiesWithoutRate) }} {{ $puritiesWithoutRate === 1 ? 'has' : 'have' }}
                    no rate for today. Items will price against the last recorded rate until you enter them.
                </div>
                <a href="{{ route('rates.today') }}" class="btn btn-sm btn-warning">Enter Rates</a>
            </div>
        @endcan
    @endif

    <div class="row row-cols-1 row-cols-lg-4 row-cols-md-2">
        @foreach ([
            ['label' => 'Items', 'value' => number_format($itemCount), 'icon' => 'ri-price-tag-3-fill', 'bg' => 'bg-primary'],
            ['label' => 'Net Weight in Stock', 'value' => number_format($netWeight, 3) . ' g', 'icon' => 'ri-scales-3-fill', 'bg' => 'bg-success'],
            ['label' => 'Rated Today', 'value' => $ratedToday . ' / ' . ($ratedToday + $puritiesWithoutRate), 'icon' => 'ri-money-rupee-circle-fill', 'bg' => 'bg-info'],
            ['label' => 'Stone & Diamond Masters', 'value' => number_format($stoneCount), 'icon' => 'ri-vip-diamond-fill', 'bg' => 'bg-warning'],
        ] as $card)
            <div class="col">
                <div class="card widget-icon-box">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <h5 class="text-muted text-uppercase fs-13 mt-0">{{ $card['label'] }}</h5>
                                <h3 class="my-2">{{ $card['value'] }}</h3>
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
                        Signed in as <strong>{{ auth()->user()->username }}</strong>
                        with the role <strong>{{ auth()->user()->roleLabel() }}</strong>.
                    </p>
                    <p class="text-muted mb-0">
                        Masters and the item register are live. Stock movement and quotations come next.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
