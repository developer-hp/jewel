@extends('layouts.app')

@section('title', 'Add OG Estimate')

@section('content')
    <x-page-title title="OG Estimate" />

    <form method="POST" action="{{ route('og-estimates.store') }}">
        @include('og-estimates._form')
    </form>
@endsection
