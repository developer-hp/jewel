@extends('layouts.app')

@section('title', 'Add Rate')

@section('content')
    <x-page-title title="Add Rate" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('rates.store') }}">
                        @include('rates._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
