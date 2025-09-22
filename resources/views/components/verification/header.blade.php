<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2 text-primary">Full Verification</h1>
        <h4 class="text-muted">{{ $migration->name }}</h4>
        <small class="text-muted">Comprehensive data integrity verification</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('data-migration.show', $migration) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Migration
        </a>
        @if ($migration->status === 'COMPLETED' || $migration->batches->where('status', 'COMPLETED')->count() > 0)
            <!-- Loading state - hide buttons -->
            <template x-if="pageStatus === 'loading'">
                <div class="d-flex align-items-center">
                    <x-spinners.small />
                    <span class="text-muted">Loading...</span>
                </div>
            </template>

            <!-- Show stop button during active verification -->
            <template x-if="pageStatus === 'starting' || pageStatus === 'in_progress' || pageStatus === 'stopping'">
                <button @click="stopVerification()" class="btn btn-outline-danger"
                    :disabled="pageStatus === 'stopping'">
                    <i :class="pageStatus === 'stopping' ? 'fas fa-spinner fa-spin me-1' : 'fas fa-stop me-1'"></i>
                    <span x-text="pageStatus === 'stopping' ? 'Stopping...' : 'Stop Verification'"></span>
                </button>
            </template>

            <!-- Show action buttons when not loading or running -->
            <template x-if="!['loading', 'starting', 'in_progress', 'stopping'].includes(pageStatus)">
                <div class="btn-group">
                    <!-- First time verification button -->
                    <button x-show="hasNeverBeenVerified()" @click="startVerification()" class="btn btn-primary"
                        title="Start data verification for the first time">
                        <i class="fas fa-play me-1"></i> Start Verification
                    </button>

                    <!-- Verification has been run before - always show these buttons -->
                    <template x-if="!hasNeverBeenVerified()">
                        <button @click="startVerification()" class="btn btn-primary"
                            title="Reset all verification states and start fresh verification">
                            <i class="fas fa-redo me-1"></i> Run Verification Again
                        </button>
                    </template>

                    <!-- Continue Verification - show when verification has been attempted -->
                    <button x-show="!hasNeverBeenVerified()" @click="continueVerification()"
                        class="btn btn-outline-primary" :disabled="!hasUnverifiedRecords()"
                        :title="hasUnverifiedRecords() ? 'Continue verification of failed and pending records only' :
                            'No failed or pending records to continue with'">
                        <i class="fas fa-play me-1"></i> Continue Verification
                    </button>
                </div>
            </template>
        @else
            <button class="btn btn-secondary" disabled title="Migration must be completed first">
                <i class="fas fa-lock me-1"></i> Verification Unavailable
            </button>
        @endif
    </div>
</div>
