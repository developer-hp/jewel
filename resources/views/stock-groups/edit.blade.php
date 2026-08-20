@extends('layouts.app')

@section('title', 'Edit Stock Group')

@section('content')
    <x-page-title title="Edit Stock Group — {{ $stockGroup->name }}" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('stock-groups.update', $stockGroup) }}">
                        @method('PUT')
                        @include('stock-groups._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
