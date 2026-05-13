@props([
    'title' => null,
    'subtitle' => null,
    'headerActions' => null,
    'footer' => null
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || $headerActions)
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    @if($title)
                        <h5 class="card-title mb-0">{{ __($title) }}</h5>
                    @endif
                    @if($subtitle)
                        <small class="text-muted">{{ $subtitle }}</small>
                    @endif
                </div>
                @if($headerActions)
                    <div class="card-header-actions">
                        {{ $headerActions }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card-body">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endif
</div>
