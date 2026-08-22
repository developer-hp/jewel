@extends('layouts.app')

@section('title', 'Internal Stock — Edit')

@section('content')
    <x-page-title title="Internal Stock" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Edit Entry</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('internal-stock-entries.update', $entry) }}">
                        @method('PUT')
                        @include('internal-stock-entries._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
