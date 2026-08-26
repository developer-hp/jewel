@extends('layouts.app')

@section('title', 'Add Cash Drawer')

@section('content')
    <x-page-title title="Add Cash Drawer" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('cash-drawers.store') }}">
                        @include('cash-drawers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
