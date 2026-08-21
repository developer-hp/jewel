@extends('layouts.app')

@section('title', 'Add Order Form')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Order Form" />

    <form method="POST" action="{{ route('order-forms.store') }}" enctype="multipart/form-data">
        @include('order-forms._form')
    </form>
@endsection
