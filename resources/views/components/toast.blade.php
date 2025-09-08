@props([
    'message' => '',
    'type' => 'info',
    'timeout' => null,
    'position' => null,
    'dismissible' => true,
    'id' => null
])

@php
    $timeout = $timeout ?? config('features.toast.default_timeout', 5000);
    $position = $position ?? config('features.toast.position', 'top-right');
    $toastId = $id ?? 'toast-' . uniqid();
    
    // Define type-specific classes
    $typeClasses = [
        'success' => 'bg-success text-white',
        'error' => 'bg-danger text-white',
        'warning' => 'bg-warning text-dark',
        'info' => 'bg-info text-white',
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white'
    ];
    
    $typeClass = $typeClasses[$type] ?? $typeClasses['info'];
    
    // Define position classes
    $positionClasses = [
        'top-right' => 'top-0 end-0',
        'top-left' => 'top-0 start-0', 
        'bottom-right' => 'bottom-0 end-0',
        'bottom-left' => 'bottom-0 start-0'
    ];
    
    $positionClass = $positionClasses[$position] ?? $positionClasses['top-right'];
@endphp

<div id="{{ $toastId }}" 
     class="toast align-items-center border-0 {{ $typeClass }}" 
     role="alert" 
     aria-live="assertive" 
     aria-atomic="true"
     data-timeout="{{ $timeout }}"
     style="min-width: 300px;">
    <div class="d-flex">
        <div class="toast-body">
            {{ $message }}
        </div>
        @if($dismissible)
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastElement = document.getElementById('{{ $toastId }}');
    if (toastElement) {
        const toast = new bootstrap.Toast(toastElement, {
            delay: {{ $timeout }}
        });
        toast.show();
    }
});
</script>