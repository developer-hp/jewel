@extends('layouts.app')

@section('title', 'New Angadiya Slip')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="New Angadiya Slip" />

    <form method="POST" action="{{ route('angadiyas.store') }}">
        @include('angadiyas._form')
    </form>
@endsection

@include('angadiyas.partials.form-scripts')
