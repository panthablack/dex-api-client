@props([
    'resourceType' => 'case',
    'canEnrich' => false,
    'progress' => [],
    'isEnriching' => false,
    'isPaused' => false
])

<div x-data="enrichmentApp()" x-init="init()" x-cloak>
    <!-- Controls -->
    <div class="btn-group mb-4" role="group">
        <!-- Start/Resume Button -->
        <button @click="startEnrichment()"
                :disabled="!canEnrich || (isEnriching && !isPaused)"
                x-show="(!isEnriching || isPaused) && !isCompleted"
                class="btn btn-primary">
            <i class="fas fa-play me-1"></i>
            <span x-text="isPaused ? 'Resume Enrichment' : 'Start Enrichment'"></span>
        </button>

        <!-- Pause Button -->
        <button @click="pauseEnrichment()"
                :disabled="!isEnriching || isPaused"
                x-show="isEnriching && !isPaused"
                class="btn btn-warning">
            <i class="fas fa-pause me-1"></i>
            Pause Enrichment
        </button>

        <!-- Restart Button -->
        <button @click="showRestartModal()"
                :disabled="isEnriching"
                x-show="isCompleted && !isEnriching"
                class="btn btn-warning">
            <i class="fas fa-redo me-1"></i>
            Restart Enrichment
        </button>

        <!-- Export Button -->
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#exportModal">
            <i class="fas fa-download me-1"></i>
            Export Data
        </button>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <x-enrichment.stats-card
                :label="$resourceType === 'case' ? 'Total Cases' : 'Total Sessions'"
                :icon="$resourceType === 'case' ? 'fa-list' : 'fa-bars'"
                color="primary"
                :xBind="$resourceType === 'case' ? 'progress.total_shallow_cases' : 'progress.total_shallow_sessions'"
            />
        </div>

        <div class="col-md-3 mb-3">
            <x-enrichment.stats-card
                :label="$resourceType === 'case' ? 'Enriched Cases' : 'Enriched Sessions'"
                icon="fa-check-circle"
                color="success"
                :xBind="$resourceType === 'case' ? 'progress.enriched_cases' : 'progress.enriched_sessions'"
            />
        </div>

        <div class="col-md-3 mb-3">
            <x-enrichment.stats-card
                :label="$resourceType === 'case' ? 'Remaining Cases' : 'Remaining Sessions'"
                icon="fa-hourglass-half"
                color="warning"
                :xBind="$resourceType === 'case' ? 'progress.unenriched_cases' : 'progress.unenriched_sessions'"
            />
        </div>

        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info-opacity-10 p-3 rounded">
                            <i class="fas fa-percentage text-info fa-lg"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-title text-muted mb-1">Progress</h6>
                        <h4 class="mb-0" x-text="progress.progress_percentage + '%'">
                            {{ $progress['progress_percentage'] }}%
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="progress" style="height: 25px;">
                <div class="progress-bar progress-bar-striped"
                     :class="{ 'progress-bar-animated': isEnriching && !isPaused }"
                     role="progressbar"
                     :aria-valuenow="progress.progress_percentage"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     :style="`width: ${progress.progress_percentage}%`">
                    <span x-text="progress.progress_percentage + '%'"></span>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0" x-show="isEnriching && !isPaused">
                <i class="fas fa-spinner fa-spin me-1"></i>
                Processing...
            </p>
            <p class="text-success small mt-2 mb-0" x-show="isCompleted && !isEnriching">
                <i class="fas fa-check-circle me-1"></i>
                Enrichment Complete!
            </p>
            <p class="text-warning small mt-2 mb-0" x-show="isPaused && isEnriching">
                <i class="fas fa-pause-circle me-1"></i>
                Paused - Click Resume to continue
            </p>
        </div>
    </div>

    <!-- Results Section -->
    <div x-show="lastEnrichmentResult" x-cloak>
        <div class="card mb-4 bg-light">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-check-circle text-success me-2"></i>
                    Enrichment Results
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <p class="text-muted small">Total Processed</p>
                        <p class="h5" x-text="lastEnrichmentResult.total_shallow_cases || lastEnrichmentResult.total_shallow_sessions"></p>
                    </div>
                    <div class="col-md-3">
                        <p class="text-muted small">Newly Enriched</p>
                        <p class="h5 text-success" x-text="lastEnrichmentResult.newly_enriched"></p>
                    </div>
                    <div class="col-md-3">
                        <p class="text-muted small">Already Enriched</p>
                        <p class="h5 text-info" x-text="lastEnrichmentResult.already_enriched"></p>
                    </div>
                    <div class="col-md-3">
                        <p class="text-muted small">Failed</p>
                        <p class="h5 text-danger" x-text="lastEnrichmentResult.failed"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Display -->
    <div x-show="lastEnrichmentResult && lastEnrichmentResult.errors && lastEnrichmentResult.errors.length > 0" x-cloak>
        <div class="alert alert-danger">
            <h6>
                <i class="fas fa-exclamation-circle me-2"></i>
                Enrichment Errors
            </h6>
            <ul class="mb-0 small">
                <template x-for="error in lastEnrichmentResult.errors" :key="error">
                    <li x-text="`${error.case_id || error.session_id}: ${error.error}`"></li>
                </template>
            </ul>
        </div>
    </div>

    <!-- No Data Alert -->
    @if(!$canEnrich)
        <x-enrichment.prerequisite-warning
            :prerequisiteType="$resourceType === 'case' ? 'shallow_session' : 'case'"
        />
    @endif
</div>

<script>
    window.enrichmentApp = () => ({
        canEnrich: {{ $canEnrich ? 'true' : 'false' }},
        resourceType: '{{ $resourceType }}',
        progress: {{ json_encode($progress) }},
        isEnriching: false,
        isPaused: false,
        lastEnrichmentResult: null,
        pollInterval: null,
        currentJobId: null,

        get isCompleted() {
            return this.progress.progress_percentage >= 100;
        },

        async init() {
            await this.refreshProgress();
            const activeJobId = await this.getActiveJobId();
            if (activeJobId) {
                this.currentJobId = activeJobId;
                this.isEnriching = true;
                this.startPollingJobStatus();
            }
        },

        async startEnrichment() {
            const endpoint = this.resourceType === 'case'
                ? '{{ route("enrichment.cases.api.start") }}'
                : '{{ route("enrichment.sessions.api.start") }}';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                if (data.success) {
                    this.currentJobId = data.data.job_id;
                    this.isEnriching = true;
                    this.isPaused = false;
                    this.startPollingJobStatus();
                }
            } catch (error) {
                console.error('Error starting enrichment:', error);
                window.showToast('Failed to start enrichment', 'error');
            }
        },

        async pauseEnrichment() {
            const endpoint = this.resourceType === 'case'
                ? '{{ route("enrichment.cases.api.pause") }}'
                : '{{ route("enrichment.sessions.api.pause") }}';

            try {
                await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                this.isPaused = true;
            } catch (error) {
                console.error('Error pausing enrichment:', error);
            }
        },

        async showRestartModal() {
            // Show confirmation modal
            if (confirm('This will delete all enriched data and restart from the beginning. Continue?')) {
                await this.restartEnrichment();
            }
        },

        async restartEnrichment() {
            const endpoint = this.resourceType === 'case'
                ? '{{ route("enrichment.cases.api.restart") }}'
                : '{{ route("enrichment.sessions.api.restart") }}';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                if (data.success) {
                    this.currentJobId = data.data.job_id;
                    this.isEnriching = true;
                    this.isPaused = false;
                    this.lastEnrichmentResult = null;
                    this.startPollingJobStatus();
                }
            } catch (error) {
                console.error('Error restarting enrichment:', error);
            }
        },

        async getActiveJobId() {
            const endpoint = this.resourceType === 'case'
                ? '{{ route("enrichment.cases.api.active-job") }}'
                : '{{ route("enrichment.sessions.api.active-job") }}';

            try {
                const response = await fetch(endpoint);
                const data = await response.json();
                return data.data?.job_id || null;
            } catch (error) {
                console.error('Error getting active job:', error);
                return null;
            }
        },

        startPollingJobStatus() {
            if (this.pollInterval) clearInterval(this.pollInterval);

            this.pollInterval = setInterval(async () => {
                await this.checkJobStatus();
            }, 2000); // Poll every 2 seconds
        },

        async checkJobStatus() {
            if (!this.currentJobId) return;

            const endpoint = this.resourceType === 'case'
                ? `{{ route("enrichment.cases.api.job-status", ":jobId") }}`.replace(':jobId', this.currentJobId)
                : `{{ route("enrichment.sessions.api.job-status", ":jobId") }}`.replace(':jobId', this.currentJobId);

            try {
                const response = await fetch(endpoint);
                const data = await response.json();

                if (data.data) {
                    const status = data.data.status;

                    if (status === 'completed' || status === 'failed') {
                        clearInterval(this.pollInterval);
                        this.isEnriching = false;
                        this.lastEnrichmentResult = data.data.data;
                        await this.refreshProgress();
                    } else if (status === 'paused') {
                        this.isPaused = true;
                        await this.refreshProgress();
                    }
                }
            } catch (error) {
                console.error('Error checking job status:', error);
            }
        },

        async refreshProgress() {
            const endpoint = this.resourceType === 'case'
                ? '{{ route("enrichment.cases.api.progress") }}'
                : '{{ route("enrichment.sessions.api.progress") }}';

            try {
                const response = await fetch(endpoint);
                const data = await response.json();
                if (data.success) {
                    this.progress = data.data;
                }
            } catch (error) {
                console.error('Error refreshing progress:', error);
            }
        }
    });
</script>
