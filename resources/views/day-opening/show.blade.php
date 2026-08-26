@extends('layouts.app')

@section('title', 'Day Opening')

@section('content')
    <x-page-title title="Day Opening" />

    <div class="alert alert-danger">
        <i class="ri-alert-fill me-1"></i>
        <strong>This cannot be undone.</strong> Opening the day permanently deletes every
        estimate, voucher, angadiya slip, supplier hisab and cash entry. The balances are
        carried forward first, and the reports are sent first, but the documents themselves
        are gone.
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">What this opening covers</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <tr>
                            <th style="width: 40%">Since</th>
                            <td>
                                {{ $since->year > 1971
                                    ? $since->format('d-m-Y H:i')
                                    : 'the beginning — nothing has been opened yet' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Up to</th>
                            <td>{{ $until->format('d-m-Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Items sold</th>
                            <td class="fw-bold">{{ $soldCount }}</td>
                        </tr>
                        <tr>
                            <th>Items added</th>
                            <td class="fw-bold">{{ $addedCount }}</td>
                        </tr>
                    </table>

                    <p class="text-muted fs-13">
                        The window starts where the last opening finished, so nothing is
                        counted twice and nothing is missed — whether this runs on time or
                        two days late.
                    </p>

                    @can('app_setting.edit')
                        <form method="POST" action="{{ route('day-opening.run') }}"
                            data-confirm="Open the day? Every estimate, angadiya slip, hisab and cash entry will be deleted for good.">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                <i class="ri-shut-down-line"></i> Open the Day
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Automatic opening</h5>
                </div>
                <div class="card-body">
                    @if ($settings->auto_opening_enabled)
                        <p class="mb-1"><span class="badge bg-success">On</span> Runs daily at 11:30.</p>
                    @else
                        <p class="mb-1"><span class="badge bg-secondary">Off</span> Only the button above runs it.</p>
                    @endif

                    <small class="text-muted d-block">
                        Switched on under Appearance. The schedule itself only acts when the
                        setting is on, and needs <code>php artisan schedule:work</code> running.
                    </small>

                    @if ($settings->last_opening_at)
                        <hr>
                        <p class="mb-0 fs-13">
                            Last opened {{ $settings->last_opening_at->format('d-m-Y H:i') }}.
                        </p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Reports go to</h5>
                    <a href="{{ route('whatsapp-receivers.index') }}" class="btn btn-sm btn-soft-primary">Manage</a>
                </div>
                <div class="card-body">
                    @unless ($templateReady)
                        <div class="alert alert-warning py-2 fs-13">
                            The <strong>Document sent</strong> WhatsApp message is not set up, so
                            nothing will be sent.
                        </div>
                    @endunless

                    @forelse ($receivers as $receiver)
                        <div>{{ $receiver->name }} <span class="text-muted">{{ $receiver->mobile }}</span></div>
                    @empty
                        <p class="text-muted mb-0 fs-13">Nobody yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
