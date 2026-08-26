@extends('layouts.app')

@section('title', 'Edit Cash Drawer')

@section('content')
    <x-page-title title="Edit Cash Drawer — {{ $drawer->name }}" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('cash-drawers.update', $drawer) }}">
                        @method('PUT')
                        @include('cash-drawers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
