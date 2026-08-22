@extends('layouts.app')

@section('title', 'Edit Internal Stock')

@section('content')
    <x-page-title title="Edit Internal Stock" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('internal-stocks.update', $stock) }}">
                        @method('PUT')
                        @include('internal-stocks._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
