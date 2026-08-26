@extends('layouts.app')

@section('title', 'Edit Cash')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Cash Entry — {{ $entry->reference() }}" />

    <form method="POST" action="{{ route('cash-entries.update', $entry) }}">
        @method('PUT')
        @include('cash-entries._form')
    </form>
@endsection
