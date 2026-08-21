@extends('layouts.app')

@section('title', 'Print Sticker')

@section('content')
    <x-page-title title="Print Sticker" />

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('order-forms.sticker-by-ref') }}" target="_blank">
                        <div class="row mb-3">
                            <label for="ref_no" class="col-sm-3 col-form-label text-sm-end">
                                Ref No <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <input type="text" id="ref_no" name="ref_no" class="form-control"
                                    placeholder="{{ $prefix }} 159" autocomplete="off" autofocus required>
                                <small class="text-muted">
                                    Type it however it is written — <code>{{ $prefix }} 159</code> or just
                                    <code>159</code>.
                                </small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-center">
                            <a href="{{ route('order-forms.index') }}" class="btn btn-warning">Cancel</a>
                            <button type="submit" class="btn btn-dark">
                                <i class="ri-printer-line"></i> Print
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
