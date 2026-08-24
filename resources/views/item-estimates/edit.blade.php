@extends('layouts.app')

@section('title', 'Edit '.$estimate->reference())

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title :title="'Item Estimate — '.$estimate->reference()" />

    <form method="POST" action="{{ route('item-estimates.update', $estimate) }}">
        @method('PUT')
        @include('item-estimates._form')
    </form>
@endsection
