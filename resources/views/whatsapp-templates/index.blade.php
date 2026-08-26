@extends('layouts.app')

@section('title', 'WhatsApp Templates')

@section('content')
    <x-page-title title="WhatsApp Templates" />

    @include('whatsapp-templates.partials._warnings')

    <div class="row">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Each message the shop can send has to match a template approved by Meta.
                        Register the template there, then record its name and language here and
                        switch it on. Nothing is sent for a message that is off.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-centered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Message</th>
                                    <th>Template</th>
                                    <th>Language</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Driven by the WhatsAppEvent enum, not the table: a message
                                     nobody has set up still has to be findable. --}}
                                @foreach ($events as $event)
                                    @php($template = $templates->get($event->value))

                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $event->label() }}</span>
                                            <div class="text-muted fs-12">{{ $event->description() }}</div>
                                        </td>
                                        <td>
                                            @if ($template && filled($template->name))
                                                <code>{{ $template->name }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $template?->language ?: '—' }}</td>
                                        <td>
                                            @if (! $template || blank($template->name))
                                                <span class="badge bg-secondary">Not set up</span>
                                            @elseif ($template->is_active)
                                                <span class="badge bg-success">On</span>
                                            @else
                                                <span class="badge bg-danger">Off</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @can('app_setting.edit')
                                                <a href="{{ route('whatsapp-templates.edit', $event->value) }}"
                                                    class="btn btn-sm btn-primary btn-icon" title="Edit">
                                                    <i class="ri-pencil-fill"></i>
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
