@extends('layouts.app')

@section('title', 'WhatsApp Template')

@php
    // Blade ends an echo at the first "}}" it sees, including one inside a string,
    // so the braces are assembled a character at a time and never sit adjacent in
    // this file's source.
    $token = fn (int $n) => '{'.'{'.$n.'}'.'}';
@endphp

@section('content')
    <x-page-title title="WhatsApp — {{ $event->label() }}">
        <x-slot:actions>
            <a href="{{ route('whatsapp-templates.index') }}" class="btn btn-light">
                <i class="ri-list-check"></i> All Messages
            </a>
        </x-slot:actions>
    </x-page-title>

    @include('whatsapp-templates.partials._warnings')

    <form method="POST" action="{{ route('whatsapp-templates.update', $event->value) }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Template</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13">{{ $event->description() }}</p>

                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Template name <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $template->name) }}" maxlength="100"
                                placeholder="customerorder" required>
                            <small class="text-muted">
                                Exactly as it is named in Meta — lower case, digits and underscores.
                            </small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="language" class="form-label">
                                Language <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="language" name="language"
                                class="form-control @error('language') is-invalid @enderror"
                                value="{{ old('language', $template->language) }}" maxlength="10"
                                placeholder="en" required>
                            <small class="text-muted">
                                Meta treats <code>en</code> and <code>en_US</code> as different
                                templates; a mismatch is rejected outright.
                            </small>
                            @error('language')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch">
                            {{-- The hidden 0 is what actually turns it off: an unticked
                                 box is absent from the request, not false. --}}
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active"
                                name="is_active" value="1" @checked(old('is_active', $template->is_active))>
                            <label class="form-check-label" for="is_active">
                                Send this message
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Placeholders</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13">
                            What this app fills in, in order. The template registered with Meta
                            must take the same variables in the same positions, or the message
                            is rejected.
                        </p>

                        @if ($event->headerParameters() !== [])
                            <div class="fw-semibold fs-13 mb-1">Header</div>
                            <table class="table table-sm table-bordered mb-3">
                                @foreach ($event->headerParameters() as $i => $parameter)
                                    <tr>
                                        <td style="width: 4rem;"><code>{{ $token($i + 1) }}</code></td>
                                        <td>{{ $parameter }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        <div class="fw-semibold fs-13 mb-1">Body</div>
                        <table class="table table-sm table-bordered mb-0">
                            @foreach ($event->bodyParameters() as $i => $parameter)
                                <tr>
                                    <td style="width: 4rem;"><code>{{ $token($i + 1) }}</code></td>
                                    <td>{{ $parameter }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @can('app_setting.edit')
            <div class="mb-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Template
                </button>
                <a href="{{ route('whatsapp-templates.index') }}" class="btn btn-light">Cancel</a>
            </div>
        @endcan
    </form>
@endsection
