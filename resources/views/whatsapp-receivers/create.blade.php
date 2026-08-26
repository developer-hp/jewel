@extends('layouts.app')

@section('title', 'Add Receiver')

@section('content')
    <x-page-title title="Add Receiver" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('whatsapp-receivers.store') }}">
                        @include('whatsapp-receivers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
