@extends('layouts.app')

@section('title', 'Add Label Template')

@section('content')
    <x-page-title title="Add Label Template" />

    <form method="POST" action="{{ route('label-settings.store') }}">
        @include('label-settings._form')
    </form>
@endsection
