@props(['source' => null, 'canGenerate' => false])

<div x-data="sessionGenerationApp()" x-cloak>
    <!-- Info Card -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <i class="fas fa-database me-2"></i>
            <strong>Generate Shallow Sessions from Case Data</strong>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Shallow sessions will be extracted from your case data to prepare for enrichment.
            </p>

            @if($source)
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Data Source:</strong>
                    @if($source === 'migrated_cases')
                        Cases from <strong>Case Migration</strong>
                    @elseif($source === 'migrated_enriched_cases')
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
            <div x-show="isGenerating" x-cloak>
                <div class="mb-3">
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
            </div>

            <!-- Generate Button -->
            <div class="d-flex gap-2">
                <button @click="generateShallowSessions()"
                        :disabled="!canGenerate || isGenerating"
                        x-show="!isGenerating || !generationComplete"
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
                <span x-text="`Generated ${generationResult.newly_created} shallow sessions from ${generationResult.source}`"></span>
            </div>

            <!-- Error Message -->
            <div x-show="generationError" x-cloak class="alert alert-danger mt-3">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error:</strong>
                <span x-text="generationError"></span>
            </div>
        </div>
    </div>
</div>

<script>
    window.sessionGenerationApp = () => ({
        canGenerate: {{ $canGenerate ? 'true' : 'false' }},
        isGenerating: false,
        generationProgress: 0,
        generationResult: null,
        generationError: null,
        generationComplete: false,

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
                } else {
                    this.generationError = data.error || 'Generation failed';
                }
            } catch (error) {
                this.generationError = error.message || 'An error occurred';
                console.error('Generation error:', error);
            } finally {
                this.isGenerating = false;
            }
        },

        reloadPage() {
            location.reload();
        }
    });
</script>
