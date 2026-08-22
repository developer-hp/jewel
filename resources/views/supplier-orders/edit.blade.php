@extends('layouts.app')

@section('title', 'Edit Order '.$order->form_no)

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title :title="'Supplier Order — '.$order->form_no" />

    <form method="POST" action="{{ route('supplier-orders.update', $order) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('supplier-orders._form')
    </form>
@endsection
