@extends('layouts.app')

@section('title', 'Add Metal Type')

@section('content')
    <x-page-title title="Add Metal Type" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('metal-types.store') }}">
                        @include('metal-types._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
