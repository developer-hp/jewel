@extends('layouts.app')

@section('title', 'Edit '.$estimate->reference())

@section('content')
    <x-page-title :title="'OG Estimate — '.$estimate->reference()" />

    <form method="POST" action="{{ route('og-estimates.update', $estimate) }}">
        @method('PUT')
        @include('og-estimates._form')
    </form>
@endsection
