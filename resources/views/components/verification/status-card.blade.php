<div x-show="verification.status !== 'loading' && (verification.status !== 'idle' || verification.total > 0)"
    class="card mb-4" x-transition>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-shield-alt me-2"></i>
            Verification Progress
        </h5>
        <div class="d-flex align-items-center gap-2">
            <div class="badge" :class="getStatusBadgeClass()" x-text="getStatusText()"></div>
            <div
                x-show="verification.status === 'starting' || verification.status === 'in_progress' || verification.status === 'stopping'">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Processing Progress Bar -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Processing Progress</span>
                <span class="text-muted" x-text="getProgressText()"></span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar progress-bar-striped" role="progressbar"
                    :style="`width: ${verification.progress}%`" :class="getProcessingBarClass()"
                    x-text="`${verification.progress}%`">
                </div>
            </div>
        </div>

        <!-- Success Progress Bar -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Success Rate</span>
                <span class="text-muted" x-text="getSuccessText()"></span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar" role="progressbar" :style="`width: ${getSuccessRate()}%`"
                    :class="getSuccessBarClass()" x-text="`${getSuccessRate()}%`">
                </div>
            </div>
        </div>

        <!-- Resource Type Progress -->
        <div x-show="verification.resourceProgress && Object.keys(verification.resourceProgress).length > 0"
            class="mb-3">
            <h6 class="text-muted mb-3">Resource Type Progress</h6>
            <template x-for="[resourceType, progress] in Object.entries(verification.resourceProgress || {})"
                :key="resourceType">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-capitalize fw-medium" x-text="resourceType"></span>
                        <span class="text-muted small"
                            x-text="`${progress.processed || 0}/${progress.total || 0} processed`"></span>
                    </div>

                    <!-- Processing Progress -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Processing</small>
                            <small class="text-muted"
                                x-text="`${progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0}%`"></small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar"
                                :class="(progress.total > 0 && progress.processed === progress.total) ? 'bg-success' :
                                'bg-info'"
                                role="progressbar"
                                :style="`width: ${progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0}%`">
                            </div>
                        </div>
                    </div>

                    <!-- Success Progress -->
                    <div class="mb-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Success Rate</small>
                            <small class="text-muted" x-text="getResourceSuccessText(resourceType, progress)"></small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" :class="getResourceSuccessBarClass(resourceType, progress)"
                                role="progressbar" :style="`width: ${getResourceSuccessRate(resourceType, progress)}%`">
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Statistics -->
        <div class="row text-center mt-3">
            <div class="col-md-3">
                <div class="h5 mb-0" x-text="verification.total?.toLocaleString() || '0'"></div>
                <small class="text-muted">Total Records</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0 text-success" x-text="verification.verified?.toLocaleString() || '0'"></div>
                <small class="text-muted">Verified</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0 text-danger"
                    x-text="((verification.processed || 0) - (verification.verified || 0)).toLocaleString()"></div>
                <small class="text-muted">Failed</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0" x-text="getSuccessRate() + '%'"></div>
                <small class="text-muted">Success Rate</small>
            </div>
        </div>

        <!-- Current Activity -->
        <div x-show="verification.currentActivity" class="mt-3">
            <small class="text-muted">
                <i :class="verification.status === 'in_progress' || verification.status === 'starting' ?
                    'fas fa-cog fa-spin' : 'fas fa-info-circle'"
                    class="me-1"></i>
                <span x-text="verification.currentActivity"></span>
            </small>
        </div>

        <!-- Verification Progress - For test compatibility -->
        <div x-show="verification.status === 'in_progress' || verification.status === 'starting'" class="mt-3"
            id="verification-progress">
            <div class="d-flex align-items-center">
                <i class="fas fa-spinner fa-spin text-primary me-2"></i>
                <span x-text="verification.currentActivity || 'Processing clients...'"
                    id="verification-progress-text"></span>
            </div>
        </div>
    </div>
</div>
