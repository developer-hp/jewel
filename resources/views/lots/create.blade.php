@extends('layouts.app')

@section('title', 'New Lot')

@section('content')
    <x-page-title title="New Item Lot" />

    <form method="POST" action="{{ route('lots.store') }}" enctype="multipart/form-data">
        @include('lots._form')
    </form>
@endsection
