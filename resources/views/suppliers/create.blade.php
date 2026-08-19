@extends('layouts.app')

@section('title', 'Add Supplier')

@section('content')
    <x-page-title title="Add Supplier" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('suppliers.store') }}">
                        @include('suppliers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
