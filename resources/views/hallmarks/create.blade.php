@extends('layouts.app')

@section('title', 'Add Hallmark')

@section('content')
    <x-page-title title="Hallmark" />

    <form method="POST" action="{{ route('hallmarks.store') }}" enctype="multipart/form-data">
        @include('hallmarks._form')
    </form>
@endsection
