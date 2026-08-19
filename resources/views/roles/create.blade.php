@extends('layouts.app')

@section('title', 'Add Role')

@section('content')
    <x-page-title title="Add Role" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('roles.store') }}">
                        @include('roles._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
