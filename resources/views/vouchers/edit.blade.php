@extends('layouts.app')

@section('title', 'Edit '.$voucher->reference())

@section('content')
    <x-page-title :title="'Voucher — '.$voucher->reference()" />

    <form method="POST" action="{{ route('vouchers.update', $voucher) }}">
        @method('PUT')
        @include('vouchers._form')
    </form>
@endsection
