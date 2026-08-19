@extends('layouts.app')

@section('title', 'Edit ' . $singular)

@section('content')
    <x-page-title :title="'Edit ' . $singular . ' — ' . $stone->name" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route($routePrefix . '.update', $stone) }}">
                        @method('PUT')
                        @include('stone-masters._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
