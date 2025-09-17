@extends('layouts.app')

@section('title', 'Full Verification - ' . $migration->name)

@section('content')
    <div x-data="verificationApp()" x-init="init()" x-cloak>
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('data-migration.index') }}" class="text-decoration-none">
                        Data Migration
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('data-migration.show', $migration) }}" class="text-decoration-none">
                        {{ $migration->name }}
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    Full Verification
                </li>
            </ol>
        </nav>

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
                @if ($migration->status === 'completed' || $migration->batches->where('status', 'completed')->count() > 0)
                    <!-- Loading state - hide buttons -->
                    <template x-if="verification.status === 'loading'">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="text-muted">Loading...</span>
                        </div>
                    </template>

                    <!-- Show stop button during active verification -->
                    <template x-if="verification.status === 'starting' || verification.status === 'in_progress' || verification.status === 'stopping'">
                        <button @click="stopVerification()" class="btn btn-outline-danger"
                                :disabled="verification.status === 'stopping'">
                            <i :class="verification.status === 'stopping' ? 'fas fa-spinner fa-spin me-1' : 'fas fa-stop me-1'"></i>
                            <span x-text="verification.status === 'stopping' ? 'Stopping...' : 'Stop Verification'"></span>
                        </button>
                    </template>

                    <!-- Show action buttons when not loading or running -->
                    <template x-if="!['loading', 'starting', 'in_progress', 'stopping'].includes(verification.status)">
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
                            <button x-show="!hasNeverBeenVerified()" @click="continueVerification()" class="btn btn-outline-primary"
                                :disabled="!hasUnverifiedRecords()"
                                :title="hasUnverifiedRecords() ? 'Continue verification of failed and pending records only' : 'No failed or pending records to continue with'">
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

        @if (!($migration->status === 'completed' || $migration->batches->where('status', 'completed')->count() > 0))
            <!-- Info Card for Unavailable Verification -->
            <div class="card border-warning mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle text-warning fa-2x me-3"></i>
                        <div>
                            <h5 class="card-title mb-1">Verification Not Available</h5>
                            <p class="card-text mb-0">
                                Full verification requires completed migration batches. Please complete your migration
                                first,
                                then return here to verify data integrity.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Info Card for Available Verification -->
            <div class="card border-info mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-shield-alt text-info fa-2x me-3 mt-1"></i>
                        <div>
                            <h5 class="card-title mb-2">About Full Verification</h5>
                            <p class="card-text mb-2">
                                Full verification performs comprehensive data integrity checks on all migrated records:
                            </p>
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-check text-success me-2"></i> Validates data structure and format</li>
                                <li><i class="fas fa-check text-success me-2"></i> Checks for missing or corrupted fields
                                </li>
                                <li><i class="fas fa-check text-success me-2"></i> Verifies data relationships and
                                    constraints
                                </li>
                                <li><i class="fas fa-check text-success me-2"></i> Generates detailed error reports</li>
                            </ul>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    This process may take several minutes depending on the amount of data.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Loading Card -->
        <div x-show="verification.status === 'loading'" class="card mb-4" x-transition>
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="text-muted">Loading verification status...</h5>
                <p class="text-muted mb-0">Please wait while we check the current verification state.</p>
            </div>
        </div>

        <!-- Verification Status Card -->
        <div x-show="verification.status !== 'loading' && (verification.status !== 'idle' || verification.total > 0)" class="card mb-4" x-transition>
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt me-2"></i>
                    Verification Progress
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <div class="badge" :class="getStatusBadgeClass()" x-text="getStatusText()"></div>
                    <div x-show="verification.status === 'starting' || verification.status === 'in_progress' || verification.status === 'stopping'">
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
                                    <small class="text-muted" x-text="`${progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0}%`"></small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar"
                                        :class="(progress.total > 0 && progress.processed === progress.total) ? 'bg-success' : 'bg-info'"
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
                                    <div class="progress-bar"
                                        :class="getResourceSuccessBarClass(resourceType, progress)"
                                        role="progressbar"
                                        :style="`width: ${getResourceSuccessRate(resourceType, progress)}%`">
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

        <!-- Resource Type Results -->
        <div x-show="verification.results && Object.keys(verification.results).length > 0" class="row">
            <template x-for="[resourceType, result] in Object.entries(verification.results || {})" :key="resourceType">
                <div class="col-md-4 mb-4">
                    <div class="card h-100" :class="getResultCardClass(result)">
                        <div class="card-body text-center">
                            <h5 class="card-title text-capitalize" x-text="resourceType"></h5>
                            <div class="mb-3" style="font-size: 3rem;" :class="getResultStatusClass(result)"
                                x-text="getResultIcon(result)">
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="h6 mb-0" x-text="result.verified?.toLocaleString() || '0'"></div>
                                    <small class="text-muted">Verified</small>
                                </div>
                                <div class="col-6">
                                    <div class="h6 mb-0" x-text="result.total?.toLocaleString() || '0'"></div>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span :class="getResultStatusClass(result)" class="fw-bold"
                                    x-text="getResultSuccessRate(result) + '% Success'">
                                </span>
                            </div>
                            <button x-show="result.errors && result.errors.length > 0"
                                @click="showErrorDetails(resourceType, result.errors)"
                                class="btn btn-outline-danger btn-sm mt-2"
                                x-text="`View ${result.errors?.length || 0} Errors`">
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Verification Details -->
        <div x-show="verification.results && Object.keys(verification.results).length > 0" class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Verification Details</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" role="tablist">
                            <template x-for="([resourceType], index) in Object.entries(verification.results || {})"
                                :key="resourceType">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" :class="{ 'active': index === 0 }"
                                        :id="`${resourceType}-tab`" data-bs-toggle="tab"
                                        :data-bs-target="`#${resourceType}-content`" type="button" role="tab"
                                        x-text="resourceType.charAt(0).toUpperCase() + resourceType.slice(1)">
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <div class="tab-content mt-3">
                            <template x-for="([resourceType, result], index) in Object.entries(verification.results || {})"
                                :key="resourceType">
                                <div class="tab-pane fade" :class="{ 'show active': index === 0 }"
                                    :id="`${resourceType}-content`" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Verification Summary</h6>
                                            <ul class="list-unstyled">
                                                <li><strong>Total Records:</strong> <span
                                                        x-text="result.total?.toLocaleString() || '0'"></span></li>
                                                <li><strong>Verified:</strong> <span
                                                        x-text="result.verified?.toLocaleString() || '0'"></span></li>
                                                <li><strong>Failed:</strong> <span
                                                        x-text="((result.total || 0) - (result.verified || 0)).toLocaleString()"></span>
                                                </li>
                                                <li><strong>Success Rate:</strong> <span
                                                        x-text="getResultSuccessRate(result) + '%'"></span></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Common Issues</h6>
                                            <template x-if="result.errors && result.errors.length > 0">
                                                <ul class="list-group list-group-flush">
                                                    <template x-for="(error, errorIndex) in result.errors.slice(0, 5)"
                                                        :key="errorIndex">
                                                        <li class="list-group-item px-0 py-1">
                                                            <small x-text="error"></small>
                                                        </li>
                                                    </template>
                                                    <li x-show="result.errors.length > 5"
                                                        class="list-group-item px-0 py-1">
                                                        <small class="text-muted"
                                                            x-text="`...and ${result.errors.length - 5} more`"></small>
                                                    </li>
                                                </ul>
                                            </template>
                                            <template x-if="!result.errors || result.errors.length === 0">
                                                <p class="text-muted">No issues found</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Details Modal -->
        <div class="modal fade" id="error-details-modal" tabindex="-1" aria-labelledby="errorDetailsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="errorDetailsModalLabel" x-text="errorModal.title">Verification Error
                            Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <span
                                    x-text="`Found ${errorModal.errors?.length || 0} verification error${(errorModal.errors?.length || 0) > 1 ? 's' : ''} for ${errorModal.resourceType}`"></span>
                            </div>
                        </div>
                        <div class="list-group">
                            <template x-for="(error, index) in errorModal.errors" :key="index">
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-danger" x-text="`Error ${index + 1}`"></h6>
                                        <small class="text-muted" x-text="`#${index + 1}`"></small>
                                    </div>
                                    <p class="mb-1" x-text="error"></p>
                                    <small class="text-muted">This record failed verification and may need manual
                                        review.</small>
                                </div>
                            </template>
                        </div>
                        <div x-show="errorModal.errors && errorModal.errors.length > 10"
                            class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Showing first 10 errors. Additional errors may exist in the full verification log.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- End Alpine.js wrapper -->

    <script>
        function verificationApp() {
            return {
                verification: {
                    status: 'loading', // 'loading', 'idle', 'starting', 'in_progress', 'completed', 'failed', 'partial'
                    progress: 0,
                    total: 0,
                    processed: 0,
                    verified: 0,
                    currentActivity: '',
                    resourceProgress: {},
                    results: {}
                },
                pollInterval: null,
                errorModal: {
                    title: '',
                    resourceType: '',
                    errors: []
                },

                async init() {
                    // Start polling immediately to load status
                    this.startPolling();
                },

                async startVerification() {
                    try {
                        // Immediately set status to starting to show stop button
                        this.verification.status = 'starting';
                        this.verification.currentActivity = 'Starting full verification...';

                        const response = await fetch(`{{ route('data-migration.api.full-verify', $migration) }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (!data.success) {
                            alert('Error starting verification: ' + data.error);
                            // Reset status on error
                            this.checkStatus();
                        } else {
                            // Change to in_progress to show stop button and activity
                            this.verification.status = 'in_progress';
                            this.verification.currentActivity = 'Verification in progress...';
                        }
                        // Polling will automatically pick up the new status
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Failed to start verification');
                        // Reset status on error
                        this.checkStatus();
                    }
                },

                startPolling() {
                    this.pollInterval = setInterval(() => this.checkStatus(), 1500);
                    // Load status immediately
                    this.checkStatus();
                },

                async checkStatus() {
                    try {
                        const response = await fetch(`{{ route('data-migration.api.verification-status', $migration) }}`);
                        const data = await response.json();

                        if (data.success) {
                            this.updateVerification(data.data);

                            // Stop polling if verification is completed
                            if (['completed', 'completed_with_failures', 'failed', 'stopped', 'no_data'].includes(data.data.status)) {
                                clearInterval(this.pollInterval);
                            }
                        } else {
                            this.verification.currentActivity = 'Temporary connection issue, retrying...';
                        }
                    } catch (error) {
                        console.error('Error checking verification status:', error);
                        this.verification.currentActivity = 'Connection issue, retrying...';
                    }
                },

                updateVerification(data) {
                    // Map new API response format to frontend expectations
                    this.verification.status = data.status;
                    this.verification.total = data.total_records || 0;
                    this.verification.processed = data.processed_records || 0;
                    this.verification.verified = data.verified_records || 0;
                    this.verification.currentActivity = data.message || '';
                    this.verification.resourceProgress = data.resource_progress || {};

                    // Convert resource_progress to results format for button logic
                    this.verification.results = {};
                    if (data.resource_progress) {
                        for (const [resourceType, progress] of Object.entries(data.resource_progress)) {
                            this.verification.results[resourceType] = {
                                total: progress.total || 0,
                                verified: progress.verified || 0,
                                failed: progress.failed || 0,
                                pending: progress.pending || 0
                            };
                        }
                    }

                    if (this.verification.total > 0) {
                        this.verification.progress = Math.min(Math.round((this.verification.processed / this.verification
                            .total) * 100), 100);
                    }
                },

                getStatusText() {
                    switch (this.verification.status) {
                        case 'loading':
                            return 'Loading...';
                        case 'starting':
                            return 'Starting...';
                        case 'in_progress':
                            return 'In Progress';
                        case 'completed':
                            return 'Completed';
                        case 'completed_with_failures':
                            return 'Completed with Failures';
                        case 'partial':
                            return 'Partially Verified';
                        case 'failed':
                            return 'Failed';
                        case 'stopping':
                            return 'Stopping...';
                        case 'stopped':
                            return 'Stopped';
                        case 'idle':
                            return 'Ready to Start';
                        case 'no_data':
                            return 'No Data Available';
                        default:
                            return 'Initializing...';
                    }
                },

                getStatusBadgeClass() {
                    switch (this.verification.status) {
                        case 'loading':
                            return 'bg-info';
                        case 'starting':
                            return 'bg-info';
                        case 'in_progress':
                            return 'bg-warning';
                        case 'completed':
                            return 'bg-success';
                        case 'completed_with_failures':
                            return 'bg-warning';
                        case 'partial':
                            return 'bg-warning';
                        case 'failed':
                            return 'bg-danger';
                        case 'stopping':
                            return 'bg-warning';
                        case 'stopped':
                            return 'bg-secondary';
                        case 'idle':
                            return 'bg-secondary';
                        case 'no_data':
                            return 'bg-light text-dark';
                        default:
                            return 'bg-info';
                    }
                },

                getProgressBarClass() {
                    switch (this.verification.status) {
                        case 'completed':
                            return 'bg-success';
                        case 'completed_with_failures':
                            return 'bg-warning';
                        case 'partial':
                            return 'bg-warning';
                        case 'failed':
                            return 'bg-danger';
                        case 'in_progress':
                            return 'progress-bar-striped progress-bar-animated bg-warning';
                        case 'no_data':
                            return 'bg-light';
                        default:
                            return 'progress-bar-striped progress-bar-animated';
                    }
                },

                getProgressText() {
                    return `${this.verification.processed?.toLocaleString() || 0} of ${this.verification.total?.toLocaleString() || 0} records processed`;
                },

                getSuccessRate() {
                    return this.verification.total > 0 ? Math.round((this.verification.verified / this.verification.total) *
                        100) : 0;
                },

                getSuccessText() {
                    return `${this.verification.verified?.toLocaleString() || 0} of ${this.verification.total?.toLocaleString() || 0} records verified`;
                },

                getProcessingBarClass() {
                    // Processing progress: Blue for in progress, Green when complete
                    if (this.verification.status === 'in_progress' || this.verification.status === 'starting') {
                        return 'progress-bar-striped progress-bar-animated bg-info';
                    }
                    return this.verification.progress >= 100 ? 'bg-success' : 'bg-info';
                },

                getSuccessBarClass() {
                    // Success rate: Green for high success, Yellow for medium, Orange for low
                    const rate = this.getSuccessRate();
                    if (rate >= 100) return 'bg-success';
                    if (rate >= 30) return 'bg-warning'; // Orange/warning for moderate success
                    return 'bg-danger'; // Only red for very low success rates
                },

                getResultSuccessRate(result) {
                    return result.total > 0 ? Math.round((result.verified / result.total) * 100) : 0;
                },

                getResultCardClass(result) {
                    const rate = this.getResultSuccessRate(result);
                    if (rate >= 95) return 'border-success';
                    if (rate >= 80) return 'border-warning';
                    return 'border-danger';
                },

                getResultStatusClass(result) {
                    const rate = this.getResultSuccessRate(result);
                    if (rate >= 95) return 'text-success';
                    if (rate >= 80) return 'text-warning';
                    return 'text-danger';
                },

                getResultIcon(result) {
                    const rate = this.getResultSuccessRate(result);
                    if (rate >= 95) return '✓';
                    if (rate >= 80) return '⚠';
                    return '✗';
                },

                showErrorDetails(resourceType, errors) {
                    this.errorModal.resourceType = resourceType;
                    this.errorModal.errors = errors.slice(0, 10); // Show first 10 errors
                    this.errorModal.title =
                        `${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)} Verification Errors`;

                    const modal = new bootstrap.Modal(document.getElementById('error-details-modal'));
                    modal.show();
                },

                getResourceProgressRate(resourceType, progress) {
                    const resourceProgress = progress.resource_progress || {};
                    const resource = resourceProgress[resourceType] || { total: 0, processed: 0 };
                    return resource.total > 0 ? Math.round((resource.processed / resource.total) * 100) : 0;
                },

                getResourceProgressText(resourceType, progress) {
                    const resourceProgress = progress.resource_progress || {};
                    const resource = resourceProgress[resourceType] || { total: 0, processed: 0 };
                    return `${resource.processed?.toLocaleString() || 0} of ${resource.total?.toLocaleString() || 0} processed`;
                },

                getResourceProgressBarClass(resourceType, progress) {
                    const rate = this.getResourceProgressRate(resourceType, progress);
                    if (progress.status === 'in_progress' || progress.status === 'starting') {
                        return 'progress-bar-striped progress-bar-animated bg-info';
                    }
                    return rate >= 100 ? 'bg-success' : 'bg-info';
                },

                getResourceSuccessRate(resourceType, progress) {
                    const results = this.verification.results || {};
                    const result = results[resourceType] || { total: 0, verified: 0 };
                    return result.total > 0 ? Math.round((result.verified / result.total) * 100) : 0;
                },

                getResourceSuccessText(resourceType, progress) {
                    const results = this.verification.results || {};
                    const result = results[resourceType] || { total: 0, verified: 0 };
                    return `${result.verified?.toLocaleString() || 0} of ${result.total?.toLocaleString() || 0} verified`;
                },

                getResourceSuccessBarClass(resourceType, progress) {
                    const rate = this.getResourceSuccessRate(resourceType, progress);
                    if (rate >= 100) return 'bg-success';
                    if (rate >= 30) return 'bg-warning';
                    return 'bg-danger';
                },

                async continueVerification() {
                    try {
                        // Immediately set status to starting to show stop button
                        this.verification.status = 'starting';
                        this.verification.currentActivity = 'Starting continue verification...';

                        const response = await fetch(`{{ route('data-migration.api.continue-verification', $migration) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const result = await response.json();

                        if (!result.success) {
                            alert('Error: ' + result.error);
                            // Reset status on error
                            this.checkStatus();
                        } else {
                            // Change to in_progress to show stop button and activity
                            this.verification.status = 'in_progress';
                            this.verification.currentActivity = 'Continue verification in progress...';
                        }
                        // Polling will automatically pick up the new status
                    } catch (error) {
                        console.error('Continue verification failed:', error);
                        alert('Continue verification failed: ' + error.message);
                        // Reset status on error
                        this.checkStatus();
                    }
                },

                async stopVerification() {
                    try {
                        const response = await fetch(`{{ route('data-migration.api.stop-verification', $migration) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const result = await response.json();

                        if (result.success) {
                            this.verification.status = 'stopping';
                            this.verification.currentActivity = 'Stopping verification...';
                            // Continue polling to see when it actually stops
                        } else {
                            alert('Error stopping verification: ' + result.error);
                        }
                    } catch (error) {
                        console.error('Stop verification failed:', error);
                        alert('Stop verification failed: ' + error.message);
                    }
                },

                hasUnverifiedRecords() {
                    if (!this.verification.results) return false;

                    for (const [resourceType, result] of Object.entries(this.verification.results)) {
                        const failed = result.failed || 0;
                        const total = result.total || 0;
                        const verified = result.verified || 0;
                        const pending = total - verified - failed;

                        if (failed > 0 || pending > 0) {
                            return true;
                        }
                    }
                    return false;
                },

                hasNeverBeenVerified() {
                    // Check if verification has never been started
                    // This is true when:
                    // 1. No verification results exist, OR
                    // 2. All records are still in pending state (never attempted)

                    if (!this.verification.results || Object.keys(this.verification.results).length === 0) {
                        return true;
                    }

                    // Check if any records have been processed (verified or failed)
                    for (const [resourceType, result] of Object.entries(this.verification.results)) {
                        const verified = result.verified || 0;
                        const failed = result.failed || 0;

                        // If any records have been verified or failed, verification has been attempted
                        if (verified > 0 || failed > 0) {
                            return false;
                        }
                    }

                    // All records are still pending - verification never started
                    return true;
                }
            };
        }
    </script>
@endsection
