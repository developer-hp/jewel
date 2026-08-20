@extends('layouts.app')

@section('title', 'Add Stock Group')

@section('content')
    <x-page-title title="Add Stock Group" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('stock-groups.store') }}">
                        @include('stock-groups._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
