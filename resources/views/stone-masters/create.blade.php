@extends('layouts.app')

@section('title', 'Add ' . $singular)

@section('content')
    <x-page-title :title="'Add ' . $singular" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route($routePrefix . '.store') }}">
                        @include('stone-masters._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
