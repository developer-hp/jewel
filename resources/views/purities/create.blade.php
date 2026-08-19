@extends('layouts.app')

@section('title', 'Add Purity')

@section('content')
    <x-page-title title="Add Purity" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('purities.store') }}">
                        @include('purities._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
