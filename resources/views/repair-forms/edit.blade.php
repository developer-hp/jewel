@extends('layouts.app')

@section('title', 'Edit '.$form->reference())

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title :title="'Repair Form — '.$form->reference()" />

    <form method="POST" action="{{ route('repair-forms.update', $form) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('repair-forms._form')
    </form>
@endsection
