@extends('layouts.app')

@section('title', 'Add Internal Stock')

@section('content')
    <x-page-title title="Add Internal Stock" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('internal-stocks.store') }}">
                        @include('internal-stocks._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
