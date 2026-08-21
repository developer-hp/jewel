@extends('layouts.app')

@section('title', 'Add Repair Form')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Repair Form" />

    <form method="POST" action="{{ route('repair-forms.store') }}" enctype="multipart/form-data">
        @include('repair-forms._form')
    </form>
@endsection
