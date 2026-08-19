@extends('layouts.app')

@section('title', 'Edit Metal Type')

@section('content')
    <x-page-title title="Edit Metal Type — {{ $metalType->name }}" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('metal-types.update', $metalType) }}">
                        @method('PUT')
                        @include('metal-types._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
