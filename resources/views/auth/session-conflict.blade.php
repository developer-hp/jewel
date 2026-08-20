@extends('layouts.auth')

@section('title', 'Already Signed In')

@section('content')
    <div class="text-center w-75 m-auto">
        <div class="mb-2">
            <i class="ri-device-line text-warning" style="font-size: 2.25rem;"></i>
        </div>
        <h4 class="text-dark-50 text-center pb-0">Already signed in</h4>
        <p class="text-muted mb-3">
            <strong>{{ $username }}</strong> is signed in on another device. Only one device may be
            signed in at a time.
        </p>
    </div>

    @if ($devices->isNotEmpty())
        <ul class="list-group mb-3">
            @foreach ($devices as $device)
                <li class="list-group-item">
                    <div class="d-flex align-items-center">
                        <i class="ri-computer-line fs-20 me-2 text-muted"></i>
                        <div>
                            <div class="fw-semibold">{{ $device['device'] }}</div>
                            <small class="text-muted">
                                {{ $device['ip'] }} · active {{ $device['last_active']->diffForHumans() }}
                            </small>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('login.conflict.resolve') }}">
        @csrf

        <div class="d-grid gap-2">
            <button type="submit" name="action" value="continue" class="btn btn-primary">
                <i class="ri-logout-circle-line me-1"></i> Sign out the other device and continue
            </button>

            <button type="submit" name="action" value="cancel" class="btn btn-light">
                Cancel — stay signed out here
            </button>
        </div>
    </form>

    <p class="text-muted fs-12 text-center mt-3 mb-0">
        Continuing ends the other session immediately; any unsaved work there is lost.
    </p>
@endsection
