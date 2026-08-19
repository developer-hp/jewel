@extends('layouts.app')

@section('title', 'Add Making Charge')

@section('content')
    <x-page-title title="Add Making Charge" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('making-charges.store') }}">
                        @include('making-charges._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
