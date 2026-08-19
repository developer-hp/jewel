@extends('layouts.app')

@section('title', 'Edit Item')

@section('content')
    <x-page-title title="Edit Item — {{ $item->code }}" />

    <form method="POST" action="{{ route('items.update', $item) }}">
        @method('PUT')
        @include('items._form')
    </form>
@endsection
