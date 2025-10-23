@props([
    'id' => 'confirmationModal',
    'title' => 'Confirm Action',
    'message' => 'Are you sure you want to proceed?',
    'confirmText' => 'Confirm',
    'confirmClass' => 'btn-danger',
    'cancelText' => 'Cancel',
    'icon' => 'fa-exclamation-triangle',
    'iconClass' => 'text-warning'
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $id }}Label">
          <i class="fas {{ $icon }} {{ $iconClass }} me-2"></i>
          {{ $title }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        {!! $message !!}
        {{ $slot }}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $cancelText }}</button>
        <button type="button" class="btn {{ $confirmClass }}" id="{{ $id }}ConfirmBtn">{{ $confirmText }}</button>
      </div>
    </div>
  </div>
</div>
