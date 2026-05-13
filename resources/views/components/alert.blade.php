@props([
    'type' => 'info',
    'dismissible' => true,
    'icon' => null
])

@php
    $alertClasses = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info',
    ];

    $icons = [
        'success' => 'ti ti-circle-check',
        'error' => 'ti ti-circle-x',
        'warning' => 'ti ti-alert-triangle',
        'info' => 'ti ti-info-circle',
    ];

    $alertClass = $alertClasses[$type] ?? 'alert-info';
    $iconClass = $icon ?? $icons[$type] ?? 'ti ti-info-circle';
@endphp

<div {{ $attributes->merge(['class' => 'alert ' . $alertClass . ' alert-dismissible fade show', 'role' => 'alert']) }}>
    @if($icon !== false)
        <i class="{{ $iconClass }} me-2"></i>
    @endif
    
    {{ $slot }}
    
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
