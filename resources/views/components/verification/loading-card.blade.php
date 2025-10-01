<div x-show="pageStatus === 'loading'" class="card mb-4" x-transition>
  <div class="card-body text-center py-5">
    <x-spinners.primary />
    <h5 class="text-muted">Loading verification status...</h5>
    <p class="text-muted mb-0">Please wait while we check the current verification state.</p>
  </div>
</div>
