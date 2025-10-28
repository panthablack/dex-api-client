@extends('layouts.app')

@section('title', 'Session Enrichment Dashboard')

@section('content')
  <div x-data="enrichmentApp()" x-init="init()" x-cloak>
    <!-- Prerequisites Not Met: No Cases Available -->
    @if (!$hasAvailableCases)
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start">
          <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
          </div>
          <div class="flex-grow-1">
            <h5 class="alert-heading">Case Data Required</h5>
            <p class="mb-2">You must complete a <strong>Case migration</strong> before you can enrich sessions.</p>
            <a href="{{ route('data-migration.create') }}" class="btn btn-sm btn-warning">
              <i class="fas fa-plus me-1"></i>
              Create Case Migration
            </a>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    @elseif (!$hasShallowSessions)
      <!-- STATE 2: Cases Available but No Shallow Sessions Generated -->
      <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
          <i class="fas fa-database me-2"></i>
          <strong>Generate Shallow Sessions from Case Data</strong>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">
            Shallow sessions will be extracted from your case data to prepare for enrichment.
          </p>

          @if($availableSource)
            <div class="alert alert-info mb-3">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Data Source:</strong>
              @if($availableSource === 'migrated_cases')
                Cases from <strong>Case Migration</strong>
              @elseif($availableSource === 'migrated_enriched_cases')
                Cases from <strong>Case Enrichment</strong>
              @endif
            </div>
          @endif

          <div class="row mb-3">
            <div class="col-md-6">
              <div class="card h-100 bg-light">
                <div class="card-body text-center">
                  <i class="fas fa-bars fa-2x text-primary mb-2"></i>
                  <h6 class="card-title">Ready to Generate</h6>
                  <p class="text-muted small mb-0">
                    Sessions will be extracted one-by-one for reliability
                  </p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card h-100 bg-light">
                <div class="card-body text-center">
                  <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                  <h6 class="card-title">No Duplicates</h6>
                  <p class="text-muted small mb-0">
                    Already-generated sessions will be skipped
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Progress Bar -->
          <div x-show="isGenerating" x-cloak class="mb-3">
            <p class="small text-muted mb-2">
              <span x-text="generationProgress + '%'"></span> Complete
            </p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated"
                   role="progressbar"
                   :aria-valuenow="generationProgress"
                   aria-valuemin="0"
                   aria-valuemax="100"
                   :style="`width: ${generationProgress}%`">
              </div>
            </div>
          </div>

          <!-- Generate Button -->
          <div class="d-flex gap-2">
            <button @click="generateShallowSessions()"
                    :disabled="isGenerating"
                    x-show="!generationComplete"
                    class="btn btn-primary">
              <i class="fas fa-play me-1"></i>
              <span x-text="isGenerating ? 'Generating...' : 'Generate Shallow Sessions'"></span>
            </button>

            <button @click="reloadPage()"
                    x-show="generationComplete"
                    class="btn btn-success">
              <i class="fas fa-check me-1"></i>
              Continue to Enrichment
            </button>
          </div>

          <!-- Success Message -->
          <div x-show="generationResult" x-cloak class="alert alert-success mt-3">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong>
            <span x-text="`Generated ${generationResult?.newly_created || 0} shallow sessions`"></span>
          </div>

          <!-- Error Message -->
          <div x-show="generationError" x-cloak class="alert alert-danger mt-3">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error:</strong>
            <span x-text="generationError"></span>
          </div>
        </div>
      </div>
    @else
      <!-- STATE 3: Ready for Enrichment -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 text-primary">
          <i class="fas fa-database me-2"></i>
          Session Enrichment Dashboard
        </h1>
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

      <!-- Warning if SHALLOW_SESSION migration not completed -->
      @if (!$canEnrich)
        <div class="alert alert-warning" role="alert">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <strong>Prerequisite Required:</strong> You must generate <strong>shallow sessions</strong> before you
          can enrich them.
        </div>
      @endif

      <!-- Statistics Cards -->
      <div class="row mb-4">
        <div class="col-md-3 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-primary bg-opacity-10 p-3 rounded">
                  <i class="fas fa-bars text-primary fa-lg"></i>
                </div>
              </div>
              <div class="ms-3">
                <h6 class="card-title text-muted mb-1">Total Shallow Sessions</h6>
                <h4 class="mb-0" x-text="progress.total_shallow_sessions">{{ $progress['total_shallow_sessions'] }}</h4>
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
                <h6 class="card-title text-muted mb-1">Enriched Sessions</h6>
                <h4 class="mb-0" x-text="progress.enriched_sessions">{{ $progress['enriched_sessions'] }}</h4>
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
                <h6 class="card-title text-muted mb-1">Unenriched Sessions</h6>
                <h4 class="mb-0" x-text="progress.unenriched_sessions">{{ $progress['unenriched_sessions'] }}</h4>
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
            About Session Enrichment
          </h5>
          <p class="card-text">
            Session enrichment fetches complete session data from the DSS API one session at a time, providing maximum fault
            tolerance. The process runs in the background to prevent browser timeout.
          </p>
          <ul>
            <li><strong>Requires:</strong> Generated shallow sessions to provide the list of session IDs</li>
            <li><strong>Processes:</strong> Each session individually using the GetSession API (fault-tolerant)</li>
            <li><strong>Background:</strong> Runs as a queue job to handle large datasets without browser timeout</li>
            <li><strong>Stores:</strong> Full session data including case IDs, dates, and service information</li>
            <li><strong>Resumes:</strong> Automatically skips sessions that are already enriched (safe to re-run)</li>
            <li><strong>Continues:</strong> On error, logs the failure and continues with remaining sessions</li>
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
                <h6 class="text-muted">Total Sessions</h6>
                <h4 x-text="lastEnrichmentResult?.total_shallow_sessions || 0"></h4>
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
                    <th>Session ID</th>
                    <th>Error</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="error in (lastEnrichmentResult?.errors || [])" :key="error.session_id">
                    <tr>
                      <td><code x-text="error.session_id"></code></td>
                      <td x-text="error.error"></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Export Options -->
      <div x-show="progress.enriched_sessions > 0" class="card mt-4" x-cloak>
        <div class="card-header">
          <h5 class="card-title mb-0">Export Enriched Data</h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">
            You have <strong x-text="progress.enriched_sessions"></strong> enriched sessions ready for export.
          </p>
          <div class="d-flex gap-2">
            <a href="{{ route('enrichment.sessions.api.export', ['format' => 'csv']) }}"
              class="btn btn-outline-primary">
              <i class="fas fa-file-csv me-1"></i>
              Export CSV
            </a>
            <a href="{{ route('enrichment.sessions.api.export', ['format' => 'json']) }}"
              class="btn btn-outline-secondary">
              <i class="fas fa-file-code me-1"></i>
              Export JSON
            </a>
            <a href="{{ route('enrichment.sessions.api.export', ['format' => 'xlsx']) }}"
              class="btn btn-outline-info">
              <i class="fas fa-file-excel me-1"></i>
              Export Excel
            </a>
          </div>
        </div>
      </div>

      <!-- Restart Confirmation Modal -->
      <x-confirmation-modal id="restartEnrichmentModal" title="Restart Enrichment" :message="'<p class=\'mb-2\'>This will <strong>permanently delete all enriched session data</strong> and restart the enrichment process from scratch.</p><p class=\'text-danger mb-0\'><i class=\'fas fa-exclamation-triangle me-1\'></i><strong>This action cannot be undone.</strong></p>'"
        confirmText="Yes, Restart Enrichment" confirmClass="btn-danger" cancelText="Cancel"
        icon="fa-exclamation-triangle" iconClass="text-danger" />
    @endif
  </div>

  <script>
    function enrichmentApp() {
      return {
        // Enrichment state
        canEnrich: @json($canEnrich ?? false),
        progress: @json($progress ?? []),
        isEnriching: false,
        isPaused: false,
        lastEnrichmentResult: null,
        pollInterval: null,

        // Generation state (for pre-enrichment shallow session generation)
        isGenerating: false,
        generationProgress: 0,
        generationResult: null,
        generationError: null,
        generationComplete: false,

        // Computed property to check if enrichment is 100% complete
        get isCompleted() {
          return this.progress.progress_percentage >= 100;
        },

        async init() {
          // Check for active enrichment by polling progress periodically
          await this.refreshProgress();
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
            const response = await fetch('{{ route('enrichment.sessions.api.start') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              // Start polling for progress updates
              this.progress = data.data.progress || this.progress;
              window.showToast('Enrichment started. Processing in background...', 'info', 3000);
              this.startPollingProgress();
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
            const response = await fetch('{{ route('enrichment.sessions.api.pause') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              window.showToast('Pause requested. Enrichment will stop after completing the current session...', 'info',
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
            const response = await fetch('{{ route('enrichment.sessions.api.resume') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              this.isPaused = false;
              this.isEnriching = true;
              this.progress = data.data.progress || this.progress;
              window.showToast('Enrichment resumed. Processing in background...', 'success', 3000);
              this.startPollingProgress();
            } else {
              window.showToast('Failed to resume: ' + (data.error || 'Unknown error'), 'error');
            }
          } catch (error) {
            console.error('Resume error:', error);
            window.showToast('Failed to resume: ' + error.message, 'error');
          }
        },

        async startPollingProgress() {
          if (this.pollInterval) {
            clearInterval(this.pollInterval);
          }

          // Poll every 2 seconds for progress updates
          this.pollInterval = setInterval(async () => {
            await this.refreshProgress();
          }, 2000);

          // Also refresh immediately
          await this.refreshProgress();
        },

        async refreshProgress() {
          try {
            const response = await fetch('{{ route('enrichment.sessions.api.progress') }}');
            const data = await response.json();

            if (data.success) {
              const previousPercentage = this.progress.progress_percentage;
              this.progress = data.data;

              // Check if enrichment has completed
              if (this.progress.progress_percentage >= 100 && previousPercentage < 100) {
                // Just completed
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                this.isEnriching = false;
                this.isPaused = false;
                this.lastEnrichmentResult = {
                  total_shallow_sessions: this.progress.total_shallow_sessions,
                  enriched_sessions: this.progress.enriched_sessions,
                  unenriched_sessions: this.progress.unenriched_sessions
                };

                window.showToast(
                  `Enrichment completed! ${this.progress.enriched_sessions} sessions enriched.`,
                  'success',
                  5000
                );
              }
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
            const response = await fetch('{{ route('enrichment.sessions.api.restart') }}', {
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

              // Update progress from response
              this.progress = data.data.progress || this.progress;

              window.showToast('Enrichment restarted. All previous data cleared. Processing in background...', 'info', 5000);
              this.startPollingProgress();
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
        },

        // Shallow Session Generation Methods
        async generateShallowSessions() {
          this.isGenerating = true;
          this.generationError = null;
          this.generationResult = null;
          this.generationProgress = 0;

          try {
            const response = await fetch('{{ route("enrichment.sessions.api.generate") }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              }
            });

            const data = await response.json();

            if (data.success) {
              this.generationResult = data.data;
              this.generationProgress = 100;
              this.generationComplete = true;
              window.showToast('Shallow sessions generated successfully!', 'success', 3000);
            } else {
              this.generationError = data.error || 'Generation failed';
              window.showToast('Generation failed: ' + this.generationError, 'error');
            }
          } catch (error) {
            this.generationError = error.message || 'An error occurred';
            console.error('Generation error:', error);
            window.showToast('Generation error: ' + this.generationError, 'error');
          } finally {
            this.isGenerating = false;
          }
        },

        reloadPage() {
          location.reload();
        }
      };
    }
  </script>
@endsection
