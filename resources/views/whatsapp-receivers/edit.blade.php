@extends('layouts.app')

@section('title', 'Edit Receiver')

@section('content')
    <x-page-title title="Edit Receiver — {{ $receiver->name }}" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('whatsapp-receivers.update', $receiver) }}">
                        @method('PUT')
                        @include('whatsapp-receivers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
