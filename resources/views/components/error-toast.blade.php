@props([
    'message' => '',
    'title' => 'Error',
    'timeout' => null,
    'position' => null,
    'dismissible' => true,
    'id' => null,
    'details' => null,
])

@php
    $timeout = $timeout ?? config('features.toast.error_timeout', 8000);
    $position = $position ?? config('features.toast.position', 'top-right');
    $toastId = $id ?? 'error-toast-' . uniqid();

    // Define position classes
    $positionClasses = [
        'top-right' => 'top-0 end-0',
        'top-left' => 'top-0 start-0',
        'bottom-right' => 'bottom-0 end-0',
        'bottom-left' => 'bottom-0 start-0',
    ];

    $positionClass = $positionClasses[$position] ?? $positionClasses['top-right'];
@endphp

<div id="{{ $toastId }}" class="toast align-items-center border-0 bg-danger text-white" role="alert"
    aria-live="assertive" aria-atomic="true" data-timeout="{{ $timeout }}" style="min-width: 350px; max-width: 500px;">
    <div class="toast-header bg-danger text-white border-0">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong class="me-auto">{{ $title }}</strong>
        <small class="text-white-50">just now</small>
        @if ($dismissible)
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"
                aria-label="Close"></button>
        @endif
    </div>
    <div class="toast-body">
        <div class="mb-2">
            {{ $message }}
        </div>
        @if ($details)
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse"
                    data-bs-target="#error-details-{{ $toastId }}" aria-expanded="false">
                    <i class="fas fa-info-circle me-1"></i> Details
                </button>
                <div class="collapse mt-2" id="error-details-{{ $toastId }}">
                    <div class="small text-white-75 bg-dark bg-opacity-25 p-2 rounded">
                        {{ $details }}
                    </div>
                </div>
            </div>
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

            // Add some animation when showing
            toastElement.addEventListener('shown.bs.toast', function() {
                toastElement.style.transform = 'translateX(0)';
            });
        }
    });
</script>
