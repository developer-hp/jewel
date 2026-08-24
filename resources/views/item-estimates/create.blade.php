@extends('layouts.app')

@section('title', 'Add Item Estimate')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Item Estimate" />

    <form method="POST" action="{{ route('item-estimates.store') }}">
        @include('item-estimates._form')
    </form>
@endsection
