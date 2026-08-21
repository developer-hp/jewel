@extends('layouts.app')

@section('title', 'Add Customer')

@section('content')
    <x-page-title title="Add Customer" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('customers.store') }}">
                        @include('customers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
