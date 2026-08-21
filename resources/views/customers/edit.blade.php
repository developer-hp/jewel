@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
    <x-page-title title="Edit Customer" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('customers.update', $customer) }}">
                        @method('PUT')
                        @include('customers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
