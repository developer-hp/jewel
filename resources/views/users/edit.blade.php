@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <x-page-title title="Edit User — {{ $user->username }}" />

    @if ($user->is(auth()->user()))
        <div class="alert alert-info">
            <i class="ri-information-fill me-1"></i>
            You are editing your own account. Your roles and active status cannot be changed here.
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @method('PUT')
                        @include('users._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
