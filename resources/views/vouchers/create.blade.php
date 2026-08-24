@extends('layouts.app')

@section('title', 'Add Voucher')

@section('content')
    <x-page-title title="Voucher" />

    <form method="POST" action="{{ route('vouchers.store') }}">
        @include('vouchers._form')
    </form>
@endsection
