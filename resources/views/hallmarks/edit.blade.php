@extends('layouts.app')

@section('title', 'Edit Hallmark')

@section('content')
    <x-page-title title="Hallmark — Lot {{ $hallmark->lot_no }}" />

    <form method="POST" action="{{ route('hallmarks.update', $hallmark) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('hallmarks._form')
    </form>
@endsection
