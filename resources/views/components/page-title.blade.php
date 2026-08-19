@props(['title'])

<div class="row">
    <div class="col-12">
        <div class="page-title-box justify-content-between d-flex align-items-lg-center flex-lg-row flex-column">
            <h4 class="page-title">{{ $title }}</h4>
            @isset($actions)
                <div class="d-flex gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
