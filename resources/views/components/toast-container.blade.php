@props([
    'position' => null
])

@php
    $position = $position ?? config('features.toast.position', 'top-right');
    
    // Define position classes
    $positionClasses = [
        'top-right' => 'top-0 end-0 p-3',
        'top-left' => 'top-0 start-0 p-3', 
        'bottom-right' => 'bottom-0 end-0 p-3',
        'bottom-left' => 'bottom-0 start-0 p-3'
    ];
    
    $positionClass = $positionClasses[$position] ?? $positionClasses['top-right'];
@endphp

<div class="toast-container position-fixed {{ $positionClass }}" style="z-index: 1060;">
    {{ $slot }}
</div>