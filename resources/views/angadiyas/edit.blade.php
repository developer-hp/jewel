@extends('layouts.app')

@section('title', 'Edit Angadiya Slip')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Edit Angadiya Slip" />

    <form method="POST" action="{{ route('angadiyas.update', $angadiya) }}">
        @method('PUT')
        @include('angadiyas._form')
    </form>
@endsection

@include('angadiyas.partials.form-scripts')
