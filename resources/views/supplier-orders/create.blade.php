@extends('layouts.app')

@section('title', 'Add Supplier Order')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Supplier Order" />

    <form method="POST" action="{{ route('supplier-orders.store') }}" enctype="multipart/form-data">
        @include('supplier-orders._form')
    </form>
@endsection
