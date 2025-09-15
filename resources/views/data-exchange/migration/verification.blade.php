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
                <button @click="startVerification()" class="btn btn-primary"
                    :disabled="verification.status === 'starting' || verification.status === 'in_progress'"
                    x-text="getStartButtonText()">
                    <i class="fas fa-play me-1"></i> Start Full Verification
                </button>
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
                            Full verification requires completed migration batches. Please complete your migration first,
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
                            <li><i class="fas fa-check text-success me-2"></i> Checks for missing or corrupted fields</li>
                            <li><i class="fas fa-check text-success me-2"></i> Verifies data relationships and constraints
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

    <!-- Verification Status Card -->
    <div x-show="verification.status !== 'idle'" class="card mb-4" x-transition>
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-shield-alt me-2"></i>
                Verification Progress
            </h5>
            <div class="d-flex align-items-center gap-2">
                <div class="badge" :class="getStatusBadgeClass()" x-text="getStatusText()"></div>
                <div x-show="verification.status === 'starting' || verification.status === 'in_progress'">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Main Progress Bar -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Overall Progress</span>
                    <span class="text-muted" x-text="getProgressText()"></span>
                </div>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped"
                        role="progressbar"
                        :style="`width: ${verification.progress}%`"
                        :class="getProgressBarClass()"
                        x-text="`${verification.progress}%`">
                    </div>
                </div>
            </div>

            <!-- Resource Type Progress -->
            <div x-show="verification.resourceProgress && Object.keys(verification.resourceProgress).length > 0" class="mb-3">
                <h6 class="text-muted mb-3">Resource Type Progress</h6>
                <template x-for="[resourceType, progress] in Object.entries(verification.resourceProgress || {})" :key="resourceType">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-capitalize fw-medium" x-text="resourceType"></span>
                            <span class="text-muted small" x-text="`${progress.processed || 0}/${progress.total || 0}`"></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar"
                                :class="(progress.total > 0 && progress.processed === progress.total) ? 'bg-success' : 'bg-info'"
                                role="progressbar"
                                :style="`width: ${progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0}%`">
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
                    <div class="h5 mb-0 text-danger" x-text="((verification.processed || 0) - (verification.verified || 0)).toLocaleString()"></div>
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
                    <i class="fas fa-cog fa-spin me-1"></i>
                    <span x-text="verification.currentActivity"></span>
                </small>
            </div>

            <!-- Verification Progress - For test compatibility -->
            <div x-show="verification.status === 'in_progress' || verification.status === 'starting'" class="mt-3" id="verification-progress">
                <div class="d-flex align-items-center">
                    <i class="fas fa-spinner fa-spin text-primary me-2"></i>
                    <span x-text="verification.currentActivity || 'Processing clients...'" id="verification-progress-text"></span>
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
                        <div class="mb-3" style="font-size: 3rem;"
                            :class="getResultStatusClass(result)"
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
                        <template x-for="([resourceType], index) in Object.entries(verification.results || {})" :key="resourceType">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link"
                                    :class="{ 'active': index === 0 }"
                                    :id="`${resourceType}-tab`"
                                    data-bs-toggle="tab"
                                    :data-bs-target="`#${resourceType}-content`"
                                    type="button"
                                    role="tab"
                                    x-text="resourceType.charAt(0).toUpperCase() + resourceType.slice(1)">
                                </button>
                            </li>
                        </template>
                    </ul>
                    <div class="tab-content mt-3">
                        <template x-for="([resourceType, result], index) in Object.entries(verification.results || {})" :key="resourceType">
                            <div class="tab-pane fade"
                                :class="{ 'show active': index === 0 }"
                                :id="`${resourceType}-content`"
                                role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Verification Summary</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Total Records:</strong> <span x-text="result.total?.toLocaleString() || '0'"></span></li>
                                            <li><strong>Verified:</strong> <span x-text="result.verified?.toLocaleString() || '0'"></span></li>
                                            <li><strong>Failed:</strong> <span x-text="((result.total || 0) - (result.verified || 0)).toLocaleString()"></span></li>
                                            <li><strong>Success Rate:</strong> <span x-text="getResultSuccessRate(result) + '%'"></span></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Common Issues</h6>
                                        <template x-if="result.errors && result.errors.length > 0">
                                            <ul class="list-group list-group-flush">
                                                <template x-for="(error, errorIndex) in result.errors.slice(0, 5)" :key="errorIndex">
                                                    <li class="list-group-item px-0 py-1">
                                                        <small x-text="error"></small>
                                                    </li>
                                                </template>
                                                <li x-show="result.errors.length > 5" class="list-group-item px-0 py-1">
                                                    <small class="text-muted" x-text="`...and ${result.errors.length - 5} more`"></small>
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
                    <h5 class="modal-title" id="errorDetailsModalLabel" x-text="errorModal.title">Verification Error Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span x-text="`Found ${errorModal.errors?.length || 0} verification error${(errorModal.errors?.length || 0) > 1 ? 's' : ''} for ${errorModal.resourceType}`"></span>
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
                                <small class="text-muted">This record failed verification and may need manual review.</small>
                            </div>
                        </template>
                    </div>
                    <div x-show="errorModal.errors && errorModal.errors.length > 10" class="alert alert-info mt-3 mb-0">
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
                    status: 'idle', // 'idle', 'starting', 'in_progress', 'completed', 'failed'
                    progress: 0,
                    total: 0,
                    processed: 0,
                    verified: 0,
                    currentActivity: '',
                    resourceProgress: {},
                    results: {},
                    verificationId: null
                },
                pollInterval: null,
                errorModal: {
                    title: '',
                    resourceType: '',
                    errors: []
                },

                init() {
                    // Auto-start if needed or setup initial state
                },

                async startVerification() {
                    this.verification.status = 'starting';
                    this.verification.progress = 0;
                    this.verification.currentActivity = 'Initializing verification process...';

                    try {
                        const response = await fetch(`{{ route('data-migration.api.full-verify', $migration) }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.verification.verificationId = data.data.verification_id;
                            this.startPolling();
                        } else {
                            this.verification.status = 'idle';
                            alert('Error starting verification: ' + data.error);
                        }
                    } catch (error) {
                        this.verification.status = 'idle';
                        console.error('Error:', error);
                        alert('Failed to start verification');
                    }
                },

                startPolling() {
                    this.pollInterval = setInterval(() => this.checkStatus(), 1500);
                },

                async checkStatus() {
                    try {
                        const url = new URL(`{{ route('data-migration.api.verification-status', $migration) }}`);
                        if (this.verification.verificationId) {
                            url.searchParams.append('verification_id', this.verification.verificationId);
                        }

                        const response = await fetch(url);
                        const data = await response.json();

                        if (data.success) {
                            this.updateVerification(data.data);

                            if (data.data.status === 'completed' || data.data.status === 'failed') {
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
                    this.verification.status = data.status;
                    this.verification.total = data.total || 0;
                    this.verification.processed = data.processed || 0;
                    this.verification.verified = data.verified || 0;
                    this.verification.currentActivity = data.current_activity || '';
                    this.verification.resourceProgress = data.resource_progress || {};
                    this.verification.results = data.results || {};

                    if (this.verification.total > 0) {
                        this.verification.progress = Math.min(Math.round((this.verification.processed / this.verification.total) * 100), 100);
                    }
                },

                getStartButtonText() {
                    switch (this.verification.status) {
                        case 'starting': return 'Starting...';
                        case 'in_progress': return 'In Progress...';
                        case 'completed': return 'Verification Complete';
                        case 'failed': return 'Verification Failed';
                        default: return 'Start Full Verification';
                    }
                },

                getStatusText() {
                    switch (this.verification.status) {
                        case 'starting': return 'Starting...';
                        case 'in_progress': return 'In Progress';
                        case 'completed': return 'Completed';
                        case 'failed': return 'Failed';
                        default: return 'Initializing...';
                    }
                },

                getStatusBadgeClass() {
                    switch (this.verification.status) {
                        case 'starting': return 'bg-info';
                        case 'in_progress': return 'bg-warning';
                        case 'completed': return 'bg-success';
                        case 'failed': return 'bg-danger';
                        default: return 'bg-info';
                    }
                },

                getProgressBarClass() {
                    switch (this.verification.status) {
                        case 'completed': return 'bg-success';
                        case 'failed': return 'bg-danger';
                        case 'in_progress': return 'progress-bar-striped progress-bar-animated bg-warning';
                        default: return 'progress-bar-striped progress-bar-animated';
                    }
                },

                getProgressText() {
                    return `${this.verification.processed?.toLocaleString() || 0} of ${this.verification.total?.toLocaleString() || 0} records`;
                },

                getSuccessRate() {
                    return this.verification.processed > 0 ? Math.round((this.verification.verified / this.verification.processed) * 100) : 0;
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
                    this.errorModal.title = `${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)} Verification Errors`;

                    const modal = new bootstrap.Modal(document.getElementById('error-details-modal'));
                    modal.show();
                }
            };
        }
    </script>
@endsection
