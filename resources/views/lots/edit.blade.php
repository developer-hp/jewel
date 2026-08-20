@extends('layouts.app')

@section('title', 'Edit Lot')

@section('content')
    <x-page-title title="Edit Lot — {{ $lot->code }}" />

    <form method="POST" action="{{ route('lots.update', $lot) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('lots._form')
    </form>
@endsection
