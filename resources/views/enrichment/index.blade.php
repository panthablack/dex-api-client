@extends('layouts.app')

@section('title', 'Case Enrichment Dashboard')

@section('content')
  <div x-data="enrichmentApp()" x-init="init()" x-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h2 text-primary">Case Enrichment Dashboard</h1>
      <button @click="startEnrichment()" :disabled="!canEnrich || isEnriching" class="btn btn-primary">
        <i class="fas" :class="isEnriching ? 'fa-spinner fa-spin' : 'fa-play'" x-show="!isEnriching"></i>
        <i class="fas fa-spinner fa-spin" x-show="isEnriching"></i>
        <span x-text="isEnriching ? 'Processing...' : 'Start Enrichment'"></span>
      </button>
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
        <h5 class="card-title mb-3">Enrichment Progress</h5>
        <div class="progress" style="height: 30px;">
          <div class="progress-bar bg-success progress-bar-striped" :class="{ 'progress-bar-animated': isEnriching }"
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
          <strong>Tip:</strong> You can navigate away from this page during enrichment. The job will continue running in the background. Return here to check progress.
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
                <template x-for="error in lastEnrichmentResult.errors" :key="error.case_id">
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

  </div>

  <script>
    function enrichmentApp() {
      return {
        canEnrich: @json($canEnrich),
        progress: @json($progress),
        isEnriching: false,
        lastEnrichmentResult: null,
        pollInterval: null,
        currentJobId: null,

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

              // If there's an active job (queued or processing), resume monitoring
              if (job.status === 'queued' || job.status === 'processing') {
                this.currentJobId = job.job_id;
                this.isEnriching = true;
                window.showToast('Resuming monitoring of enrichment job...', 'info', 3000);
                this.startPollingJobStatus();
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
          if (!this.canEnrich || this.isEnriching) {
            return;
          }

          this.isEnriching = true;
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
            }
          } catch (error) {
            console.error('Enrichment error:', error);
            window.showToast('Enrichment failed: ' + error.message, 'error');
            this.isEnriching = false;
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
                this.lastEnrichmentResult = data.data.data;

                window.showToast(
                  `Background enrichment completed! ${data.data.data.newly_enriched} cases enriched, ${data.data.data.already_enriched} already enriched, ${data.data.data.failed} failed.`,
                  'success',
                  8000
                );
              } else if (jobStatus === 'failed') {
                // Job failed
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                this.isEnriching = false;

                window.showToast('Background enrichment failed: ' + (data.data.data?.error || 'Unknown error'), 'error');
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
        }
      };
    }
  </script>
@endsection
