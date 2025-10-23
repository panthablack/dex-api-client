@extends('layouts.app')

@section('title', 'Case Enrichment Dashboard')

@section('content')
  <div x-data="enrichmentApp()" x-init="init()" x-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h2 text-primary">Case Enrichment Dashboard</h1>
      <div class="btn-group">
        <!-- Start/Resume button -->
        <button @click="startEnrichment()" :disabled="!canEnrich || (isEnriching && !isPaused)" class="btn btn-primary"
          x-show="(!isEnriching || isPaused) && !isCompleted">
          <i class="fas fa-play me-1"></i>
          <span x-text="isPaused ? 'Resume Enrichment' : 'Start Enrichment'"></span>
        </button>

        <!-- Pause button -->
        <button @click="pauseEnrichment()" :disabled="!isEnriching || isPaused" class="btn btn-warning"
          x-show="isEnriching && !isPaused">
          <i class="fas fa-pause me-1"></i>
          <span>Pause Enrichment</span>
        </button>

        <!-- Restart button (shown when 100% complete) -->
        <button @click="showRestartModal()" :disabled="isEnriching" class="btn btn-warning"
          x-show="isCompleted && !isEnriching">
          <i class="fas fa-redo me-1"></i>
          <span>Restart Enrichment</span>
        </button>

        <!-- Restart button (shown when 100% complete) -->
        <button @click="generateShallowSessions()" :disabled="isEnriching" class="btn btn-info"
          x-show="isCompleted && !isEnriching">
          <i class="fas fa-redo me-1"></i>
          <span>Generate Shallow Sessions</span>
        </button>
      </div>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <!-- Warning if SHALLOW_CASE migration not completed -->
    @if (!$canEnrich)
      <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Prerequisite Required:</strong> You must complete a <strong>SHALLOW_CASE</strong> migration before you
        can enrich cases.
        <a href="{{ route('data-migration.create') }}" class="alert-link">Create a SHALLOW_CASE migration now</a>.
      </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0">
              <div class="bg-primary bg-opacity-10 p-3 rounded">
                <i class="fas fa-list text-primary fa-lg"></i>
              </div>
            </div>
            <div class="ms-3">
              <h6 class="card-title text-muted mb-1">Total Shallow Cases</h6>
              <h4 class="mb-0" x-text="progress.total_shallow_cases">{{ $progress['total_shallow_cases'] }}</h4>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3 mb-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0">
              <div class="bg-success bg-opacity-10 p-3 rounded">
                <i class="fas fa-check-circle text-success fa-lg"></i>
              </div>
            </div>
            <div class="ms-3">
              <h6 class="card-title text-muted mb-1">Enriched Cases</h6>
              <h4 class="mb-0" x-text="progress.enriched_cases">{{ $progress['enriched_cases'] }}</h4>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3 mb-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0">
              <div class="bg-warning bg-opacity-10 p-3 rounded">
                <i class="fas fa-hourglass-half text-warning fa-lg"></i>
              </div>
            </div>
            <div class="ms-3">
              <h6 class="card-title text-muted mb-1">Unenriched Cases</h6>
              <h4 class="mb-0" x-text="progress.unenriched_cases">{{ $progress['unenriched_cases'] }}</h4>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3 mb-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0">
              <div class="bg-info bg-opacity-10 p-3 rounded">
                <i class="fas fa-percentage text-info fa-lg"></i>
              </div>
            </div>
            <div class="ms-3">
              <h6 class="card-title text-muted mb-1">Progress</h6>
              <h4 class="mb-0" x-text="progress.progress_percentage + '%'">{{ $progress['progress_percentage'] }}%</h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">Enrichment Progress</h5>
          <span x-show="isPaused" class="badge bg-warning text-dark">
            <i class="fas fa-pause me-1"></i>
            Paused
          </span>
          <span x-show="isEnriching && !isPaused" class="badge bg-success">
            <i class="fas fa-spinner fa-spin me-1"></i>
            Processing
          </span>
        </div>
        <div class="progress" style="height: 30px;">
          <div class="progress-bar progress-bar-striped"
            :class="{ 'progress-bar-animated': isEnriching && !isPaused, 'bg-warning': isPaused, 'bg-success': !isPaused }"
            role="progressbar" :style="'width: ' + progress.progress_percentage + '%'"
            :aria-valuenow="progress.progress_percentage" aria-valuemin="0" aria-valuemax="100">
            <span x-text="progress.progress_percentage + '%'"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- About Enrichment Card -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">
          <i class="fas fa-info-circle text-primary me-2"></i>
          About Case Enrichment
        </h5>
        <p class="card-text">
          Case enrichment fetches complete case data from the DSS API one case at a time, providing maximum fault
          tolerance. The process runs in the background to prevent browser timeout.
        </p>
        <ul>
          <li><strong>Requires:</strong> A completed SHALLOW_CASE migration to provide the list of case IDs</li>
          <li><strong>Processes:</strong> Each case individually using the GetCase API (fault-tolerant)</li>
          <li><strong>Background:</strong> Runs as a queue job to handle large datasets without browser timeout</li>
          <li><strong>Stores:</strong> Full case data including client IDs, outlet details, and session information</li>
          <li><strong>Resumes:</strong> Automatically skips cases that are already enriched (safe to re-run)</li>
          <li><strong>Continues:</strong> On error, logs the failure and continues with remaining cases</li>
        </ul>
        <p class="mb-0 text-muted">
          <i class="fas fa-lightbulb me-1"></i>
          <strong>Tip:</strong> You can navigate away from this page during enrichment. The job will continue running in
          the background. Return here to check progress.
        </p>
      </div>
    </div>

    <!-- Last Enrichment Results (shown after enrichment completes) -->
    <div x-show="lastEnrichmentResult" class="card" x-cloak>
      <div class="card-body">
        <h5 class="card-title">
          <i class="fas fa-chart-bar text-success me-2"></i>
          Last Enrichment Results
        </h5>
        <div class="row">
          <div class="col-md-3">
            <div class="text-center p-3">
              <i class="fas fa-list-check text-primary fa-2x mb-2"></i>
              <h6 class="text-muted">Total Cases</h6>
              <h4 x-text="lastEnrichmentResult?.total_shallow_cases || 0"></h4>
            </div>
          </div>
          <div class="col-md-3">
            <div class="text-center p-3">
              <i class="fas fa-plus-circle text-success fa-2x mb-2"></i>
              <h6 class="text-muted">Newly Enriched</h6>
              <h4 x-text="lastEnrichmentResult?.newly_enriched || 0"></h4>
            </div>
          </div>
          <div class="col-md-3">
            <div class="text-center p-3">
              <i class="fas fa-check-double text-info fa-2x mb-2"></i>
              <h6 class="text-muted">Already Enriched</h6>
              <h4 x-text="lastEnrichmentResult?.already_enriched || 0"></h4>
            </div>
          </div>
          <div class="col-md-3">
            <div class="text-center p-3">
              <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
              <h6 class="text-muted">Failed</h6>
              <h4 x-text="lastEnrichmentResult?.failed || 0"></h4>
            </div>
          </div>
        </div>

        <!-- Error details if any -->
        <div x-show="lastEnrichmentResult?.errors?.length > 0" class="mt-3">
          <h6 class="text-danger">
            <i class="fas fa-exclamation-circle me-1"></i>
            Enrichment Errors
          </h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Case ID</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="error in (lastEnrichmentResult?.errors || [])" :key="error.case_id">
                  <tr>
                    <td><code x-text="error.case_id"></code></td>
                    <td x-text="error.error"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Restart Confirmation Modal -->
    <x-confirmation-modal id="restartEnrichmentModal" title="Restart Enrichment" :message="'<p class=\'mb-2\'>This will <strong>permanently delete all enriched case data</strong> and restart the enrichment process from scratch.</p><p class=\'text-danger mb-0\'><i class=\'fas fa-exclamation-triangle me-1\'></i><strong>This action cannot be undone.</strong></p>'"
      confirmText="Yes, Restart Enrichment" confirmClass="btn-danger" cancelText="Cancel"
      icon="fa-exclamation-triangle" iconClass="text-danger" />

  </div>

  <script>
    function enrichmentApp() {
      return {
        canEnrich: @json($canEnrich),
        progress: @json($progress),
        isEnriching: false,
        isPaused: false,
        lastEnrichmentResult: null,
        pollInterval: null,
        currentJobId: null,

        // Computed property to check if enrichment is 100% complete
        get isCompleted() {
          return this.progress.progress_percentage >= 100;
        },

        async init() {
          // Check for any active enrichment jobs on page load
          await this.checkForActiveJob();
        },

        async checkForActiveJob() {
          try {
            const response = await fetch('{{ route('enrichment.api.active-job') }}');
            const data = await response.json();

            if (data.success && data.data) {
              const job = data.data;

              // If there's an active job (queued, processing, or paused), resume monitoring
              if (job.status === 'queued' || job.status === 'processing') {
                this.currentJobId = job.job_id;
                this.isEnriching = true;
                this.isPaused = false;
                window.showToast('Resuming monitoring of enrichment job...', 'info', 3000);
                this.startPollingJobStatus();
              } else if (job.status === 'paused') {
                this.currentJobId = job.job_id;
                this.isEnriching = true;
                this.isPaused = true;
                this.lastEnrichmentResult = job.data;
                window.showToast('Enrichment is paused. Click Resume to continue.', 'warning', 5000);
              } else if (job.status === 'completed') {
                // Show completed results
                this.lastEnrichmentResult = job.data;
                window.showToast('Previous enrichment completed', 'success', 3000);
              } else if (job.status === 'failed') {
                // Show failure message
                window.showToast('Previous enrichment failed: ' + (job.data?.error || 'Unknown error'), 'error', 5000);
              }
            }
          } catch (error) {
            console.error('Failed to check for active job:', error);
          }
        },

        async startEnrichment() {
          if (!this.canEnrich || (this.isEnriching && !this.isPaused)) {
            return;
          }

          // If resuming from paused state
          if (this.isPaused) {
            await this.resumeEnrichment();
            return;
          }

          this.isEnriching = true;
          this.isPaused = false;
          this.lastEnrichmentResult = null;

          try {
            const response = await fetch('{{ route('enrichment.api.start') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              // Always background mode: start polling for job status
              this.currentJobId = data.data.job_id;
              window.showToast('Enrichment job started. Monitoring progress...', 'info', 3000);
              this.startPollingJobStatus();
            } else {
              window.showToast('Enrichment failed: ' + (data.error || 'Unknown error'), 'error');
              this.isEnriching = false;
              this.isPaused = false;
            }
          } catch (error) {
            console.error('Enrichment error:', error);
            window.showToast('Enrichment failed: ' + error.message, 'error');
            this.isEnriching = false;
            this.isPaused = false;
          }
        },

        async pauseEnrichment() {
          if (!this.isEnriching || this.isPaused) {
            return;
          }

          try {
            const response = await fetch('{{ route('enrichment.api.pause') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              window.showToast('Pause requested. Enrichment will stop after completing the current case...', 'info',
                3000);
              // Don't set isPaused yet - wait for job status to confirm
            } else {
              window.showToast('Failed to pause: ' + (data.error || 'Unknown error'), 'error');
            }
          } catch (error) {
            console.error('Pause error:', error);
            window.showToast('Failed to pause: ' + error.message, 'error');
          }
        },

        async resumeEnrichment() {
          if (!this.isPaused) {
            return;
          }

          try {
            const response = await fetch('{{ route('enrichment.api.resume') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              this.currentJobId = data.data.job_id;
              this.isPaused = false;
              window.showToast('Enrichment resumed. Monitoring progress...', 'success', 3000);
              this.startPollingJobStatus();
            } else {
              window.showToast('Failed to resume: ' + (data.error || 'Unknown error'), 'error');
            }
          } catch (error) {
            console.error('Resume error:', error);
            window.showToast('Failed to resume: ' + error.message, 'error');
          }
        },

        startPollingJobStatus() {
          if (this.pollInterval) {
            clearInterval(this.pollInterval);
          }

          // Poll every 2 seconds
          this.pollInterval = setInterval(async () => {
            await this.checkJobStatus();
          }, 2000);

          // Also check immediately
          this.checkJobStatus();
        },

        async checkJobStatus() {
          if (!this.currentJobId) return;

          try {
            const response = await fetch(`/enrichment/api/job-status/${this.currentJobId}`);
            const data = await response.json();

            if (data.success) {
              const jobStatus = data.data.status;

              // Update progress
              await this.refreshProgress();

              if (jobStatus === 'completed') {
                // Job finished successfully
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                this.isEnriching = false;
                this.isPaused = false;
                this.lastEnrichmentResult = data.data.data;

                window.showToast(
                  `Background enrichment completed! ${data.data.data.newly_enriched} cases enriched, ${data.data.data.already_enriched} already enriched, ${data.data.data.failed} failed.`,
                  'success',
                  8000
                );
              } else if (jobStatus === 'paused') {
                // Job was paused
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                this.isPaused = true;
                this.lastEnrichmentResult = data.data.data;

                window.showToast(
                  `Enrichment paused. Progress: ${data.data.data.newly_enriched} newly enriched, ${data.data.data.already_enriched} already enriched. Click Resume to continue.`,
                  'warning',
                  8000
                );
              } else if (jobStatus === 'failed') {
                // Job failed
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                this.isEnriching = false;
                this.isPaused = false;

                window.showToast('Background enrichment failed: ' + (data.data.data?.error || 'Unknown error'),
                'error');
              }
              // else: job is still processing (queued or processing)
            }
          } catch (error) {
            console.error('Failed to check job status:', error);
          }
        },

        async refreshProgress() {
          try {
            const response = await fetch('{{ route('enrichment.api.progress') }}');
            const data = await response.json();

            if (data.success) {
              this.progress = data.data;
            }
          } catch (error) {
            console.error('Failed to refresh progress:', error);
          }
        },

        showRestartModal() {
          const modal = new bootstrap.Modal(document.getElementById('restartEnrichmentModal'));
          const confirmBtn = document.getElementById('restartEnrichmentModalConfirmBtn');

          // Set up the confirm button click handler
          confirmBtn.onclick = () => {
            modal.hide();
            this.restartEnrichment();
          };

          modal.show();
        },

        async restartEnrichment() {
          if (this.isEnriching) {
            return;
          }

          this.isEnriching = true;
          this.isPaused = false;

          try {
            const response = await fetch('{{ route('enrichment.api.restart') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              // Clear the last enrichment result
              this.lastEnrichmentResult = null;

              // Refresh progress to show reset state
              await this.refreshProgress();

              // Start monitoring the new job
              this.currentJobId = data.data.job_id;
              window.showToast('Enrichment restarted. All previous data cleared. Monitoring progress...', 'info', 5000);
              this.startPollingJobStatus();
            } else {
              window.showToast('Restart failed: ' + (data.error || 'Unknown error'), 'error');
              this.isEnriching = false;
              this.isPaused = false;
            }
          } catch (error) {
            console.error('Restart error:', error);
            window.showToast('Restart failed: ' + error.message, 'error');
            this.isEnriching = false;
            this.isPaused = false;
          }
        }
      };
    }
  </script>
@endsection
