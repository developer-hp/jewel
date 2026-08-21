@extends('layouts.app')

@section('title', 'Edit Sales Person')

@section('content')
    <x-page-title title="Edit Sales Person" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('sales-persons.update', $person) }}">
                        @method('PUT')
                        @include('sales-persons._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
