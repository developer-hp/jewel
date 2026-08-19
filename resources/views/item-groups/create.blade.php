@extends('layouts.app')

@section('title', 'Add Item Group')

@section('content')
    <x-page-title title="Add Item Group" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('item-groups.store') }}">
                        @include('item-groups._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
