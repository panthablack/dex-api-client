<div x-show="pageStatus !== 'loading'" class="card mb-4" x-transition>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-shield-alt me-2"></i>
            Verification Progress
        </h5>
        <div class="d-flex align-items-center gap-2">
            <div class="badge" :class="getStatusBadgeClass()" x-text="getStatusText()"></div>
            <div x-show="pageStatus === 'starting' || pageStatus === 'in_progress' || pageStatus === 'stopping'">
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
                    :style="`width: ${getProcessingProgress()}%`" :class="getProcessingBarClass()"
                    x-text="`${getProcessingProgress()}%`">
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
        <div x-show="allResources && allResources.length > 0" class="mb-3">
            <h6 class="text-muted mb-3">Resource Type Progress</h6>
            <template x-for="resourceType in allResources" :key="resourceType">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-capitalize fw-medium" x-text="resourceType.toLowerCase()"></span>
                        <span class="text-muted small"
                            x-text="`${(verificationCounts[resourceType]?.VERIFIED || 0) + (verificationCounts[resourceType]?.FAILED || 0)}/${verificationCounts[resourceType]?.total || 0} processed`"></span>
                    </div>

                    <!-- Processing Progress -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Processing</small>
                            <small class="text-muted"
                                x-text="`${verificationCounts[resourceType]?.total > 0 ? Math.round(((verificationCounts[resourceType]?.VERIFIED || 0) + (verificationCounts[resourceType]?.FAILED || 0)) / verificationCounts[resourceType]?.total * 100) : 0}%`"></small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar"
                                :class="(verificationCounts[resourceType]?.total > 0 && ((verificationCounts[resourceType]?.VERIFIED || 0) + (verificationCounts[resourceType]?.FAILED || 0)) === verificationCounts[resourceType]?.total) ? 'bg-success' : 'bg-info'"
                                role="progressbar"
                                :style="`width: ${verificationCounts[resourceType]?.total > 0 ? Math.round(((verificationCounts[resourceType]?.VERIFIED || 0) + (verificationCounts[resourceType]?.FAILED || 0)) / verificationCounts[resourceType]?.total * 100) : 0}%`">
                            </div>
                        </div>
                    </div>

                    <!-- Success Progress -->
                    <div class="mb-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Success Rate</small>
                            <small class="text-muted" x-text="getResourceSuccessText(resourceType.toLowerCase(), null)"></small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" :class="getResourceSuccessBarClass(resourceType.toLowerCase(), null)"
                                role="progressbar" :style="`width: ${getResourceSuccessRate(resourceType.toLowerCase(), null)}%`">
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Statistics -->
        <div class="row text-center mt-3">
            <div class="col-md-3">
                <div class="h5 mb-0" x-text="migration.total_items?.toLocaleString() || '0'"></div>
                <small class="text-muted">Total Records</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0 text-success" x-text="(totalCounts.VERIFIED || 0).toLocaleString()"></div>
                <small class="text-muted">Verified</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0 text-danger"
                    x-text="(totalCounts.FAILED || 0).toLocaleString()"></div>
                <small class="text-muted">Failed</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0" x-text="getSuccessRate() + '%'"></div>
                <small class="text-muted">Success Rate</small>
            </div>
        </div>
    </div>
</div>
