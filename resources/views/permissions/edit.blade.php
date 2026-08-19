@extends('layouts.app')

@section('title', 'Edit Permission')

@section('content')
    <x-page-title title="Edit Permission — {{ $permission->name }}" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('permissions.update', $permission) }}">
                        @method('PUT')
                        @include('permissions._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
