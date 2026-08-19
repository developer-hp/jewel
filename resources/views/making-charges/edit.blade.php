@extends('layouts.app')

@section('title', 'Edit Making Charge')

@section('content')
    <x-page-title title="Edit Making Charge — {{ $charge->code }}" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('making-charges.update', $charge) }}">
                        @method('PUT')
                        @include('making-charges._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
