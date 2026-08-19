@extends('layouts.app')

@section('title', 'Edit Rate')

@section('content')
    <x-page-title title="Edit Rate — {{ $rate->purity->label() }}" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('rates.update', $rate) }}">
                        @method('PUT')
                        @include('rates._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
