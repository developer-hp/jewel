@extends('layouts.app')

@section('title', 'Edit Item Group')

@section('content')
    <x-page-title title="Edit Item Group — {{ $group->name }}" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('item-groups.update', $group) }}">
                        @method('PUT')
                        @include('item-groups._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
