@extends('layouts.app')

@section('title', 'Add Item')

@section('content')
    <x-page-title title="Add Item" />

    <form method="POST" action="{{ route('items.store') }}">
        @include('items._form')
    </form>
@endsection
