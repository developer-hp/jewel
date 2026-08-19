@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    <x-page-title title="Edit Role — {{ $role->name }}" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('roles.update', $role) }}">
                        @method('PUT')
                        @include('roles._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
