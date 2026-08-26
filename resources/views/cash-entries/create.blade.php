@extends('layouts.app')

@section('title', 'Create Cash')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Create Cash" />

    <form method="POST" action="{{ route('cash-entries.store') }}">
        @include('cash-entries._form')
    </form>
@endsection
