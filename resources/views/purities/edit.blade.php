@extends('layouts.app')

@section('title', 'Edit Purity')

@section('content')
    <x-page-title title="Edit Purity — {{ $purity->label() }}" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('purities.update', $purity) }}">
                        @method('PUT')
                        @include('purities._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
