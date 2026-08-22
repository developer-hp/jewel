@extends('layouts.app')

@section('title', 'Edit Order Type')

@section('content')
    <x-page-title title="Edit Order Type" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('order-types.update', $type) }}">
                        @method('PUT')
                        @include('order-types._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
