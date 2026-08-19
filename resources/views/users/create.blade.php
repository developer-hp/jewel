@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <x-page-title title="Add User" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('users.store') }}">
                        @include('users._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
