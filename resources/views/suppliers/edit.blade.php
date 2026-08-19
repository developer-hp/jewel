@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
    <x-page-title title="Edit Supplier — {{ $supplier->name }}" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                        @method('PUT')
                        @include('suppliers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
