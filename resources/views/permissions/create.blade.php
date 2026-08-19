@extends('layouts.app')

@section('title', 'Add Permission')

@section('content')
    <x-page-title title="Add Permission" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('permissions.store') }}">
                        @include('permissions._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
