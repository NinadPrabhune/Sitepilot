@props([
    'src' => null,
    'alt' => 'Preview image',
    'images' => null,
    'initialIndex' => 0,
    'class' => 'btn btn-sm btn-primary',
    'icon' => '🔍',
    'text' => 'Preview'
])

@php
    $imageArray = $images ?? ($src ? [$src] : []);
@endphp

@if(!empty($imageArray))
    <button 
        type="button"
        class="{{ $class }} image-preview-trigger"
        onclick="openImagePreview({
            images: @js($imageArray),
            initialIndex: {{ $initialIndex }},
            enableZoom: true,
            enableFullscreen: true,
            zIndex: 10000
        })"
        aria-label="{{ $alt }}"
    >
        {{ $icon }} {{ $text }}
    </button>
@endif
