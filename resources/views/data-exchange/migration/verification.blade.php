@extends('layouts.app')

@section('title', 'Full Verification - ' . $migration->name)

@section('content')
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
        @if($migration->status === 'completed' || $migration->batches->where('status', 'completed')->count() > 0)
            <button onclick="startFullVerification()" class="btn btn-primary" id="start-verification-btn">
                <i class="fas fa-play me-1"></i> Start Full Verification
            </button>
        @else
            <button class="btn btn-secondary" disabled title="Migration must be completed first">
                <i class="fas fa-lock me-1"></i> Verification Unavailable
            </button>
        @endif
    </div>
</div>

@if(!($migration->status === 'completed' || $migration->batches->where('status', 'completed')->count() > 0))
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
                    <li><i class="fas fa-check text-success me-2"></i> Verifies data relationships and constraints</li>
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
<div class="card mb-4" id="verification-status-card" style="display: none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-shield-alt me-2"></i>
            Verification Progress
        </h5>
        <div class="d-flex align-items-center gap-2">
            <div id="verification-status-badge" class="badge bg-info">Initializing...</div>
            <div id="verification-spinner" style="display: none;">
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
                <span class="text-muted" id="progress-text">0 of 0 records</span>
            </div>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar progress-bar-striped" 
                     role="progressbar" 
                     style="width: 0%" 
                     id="verification-progress-bar">
                    0%
                </div>
            </div>
        </div>
        
        <!-- Resource Type Progress -->
        <div id="resource-progress-container" style="display: none;">
            <h6 class="text-muted mb-3">Resource Type Progress</h6>
            <div id="resource-progress-bars">
                <!-- Individual resource progress bars will be added here -->
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row text-center mt-3">
            <div class="col-md-3">
                <div class="h5 mb-0" id="total-records">0</div>
                <small class="text-muted">Total Records</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0 text-success" id="verified-records">0</div>
                <small class="text-muted">Verified</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0 text-danger" id="failed-records">0</div>
                <small class="text-muted">Failed</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0" id="verification-rate">0%</div>
                <small class="text-muted">Success Rate</small>
            </div>
        </div>
        
        <!-- Current Activity -->
        <div class="mt-3" id="current-activity" style="display: none;">
            <small class="text-muted">
                <i class="fas fa-cog fa-spin me-1"></i>
                <span id="current-activity-text">Processing...</span>
            </small>
        </div>
    </div>
</div>

<!-- Resource Type Results -->
<div class="row" id="verification-results" style="display: none;">
    <!-- Results will be populated here -->
</div>

<!-- Verification Details -->
<div class="row" id="verification-details" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Verification Details</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="details-tabs" role="tablist">
                    <!-- Tabs will be populated dynamically -->
                </ul>
                <div class="tab-content mt-3" id="details-tab-content">
                    <!-- Tab content will be populated dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Details Modal -->
<div class="modal fade" id="error-details-modal" tabindex="-1" aria-labelledby="errorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorDetailsModalLabel">Verification Error Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="error-details-content">
                    <!-- Error details will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let verificationInterval;
let currentVerificationId = null;
const migrationId = {{ $migration->id }};

function startFullVerification() {
    const button = document.getElementById('start-verification-btn');
    const statusCard = document.getElementById('verification-status-card');
    const statusBadge = document.getElementById('verification-status-badge');
    const spinner = document.getElementById('verification-spinner');
    
    button.textContent = 'Starting...';
    button.disabled = true;
    
    // Show status card with loading state
    statusCard.style.display = 'block';
    statusBadge.textContent = 'Initializing...';
    statusBadge.className = 'badge bg-info';
    spinner.style.display = 'block';
    
    // Reset progress elements
    document.getElementById('verification-progress-bar').style.width = '0%';
    document.getElementById('verification-progress-bar').textContent = '0%';
    document.getElementById('progress-text').textContent = 'Preparing verification...';
    
    // Start verification
    fetch(`{{ route('data-migration.api.full-verify', $migration) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store verification ID and start polling
            currentVerificationId = data.data.verification_id;
            verificationInterval = setInterval(checkVerificationStatus, 1500);
            statusBadge.textContent = 'Starting...';
            statusBadge.className = 'badge bg-warning';
            
            // Show immediate feedback
            document.getElementById('progress-text').textContent = 'Verification started...';
            document.getElementById('current-activity').style.display = 'block';
            document.getElementById('current-activity-text').textContent = 'Initializing verification process...';
        } else {
            button.textContent = 'Start Full Verification';
            button.disabled = false;
            spinner.style.display = 'none';
            showAlert('Error starting verification: ' + data.error, 'error');
        }
    })
    .catch(error => {
        button.textContent = 'Start Full Verification';
        button.disabled = false;
        spinner.style.display = 'none';
        console.error('Error:', error);
        showAlert('Failed to start verification', 'error');
    });
}


function updateVerificationProgress(data) {
    const progressBar = document.getElementById('verification-progress-bar');
    const spinner = document.getElementById('verification-spinner');
    const currentActivity = document.getElementById('current-activity');
    const resourceProgressContainer = document.getElementById('resource-progress-container');
    
    // Calculate progress more safely
    let progress = 0;
    if (data.total > 0) {
        progress = Math.min(Math.round((data.processed / data.total) * 100), 100);
    }
    
    // Update main progress bar
    progressBar.style.width = progress + '%';
    progressBar.textContent = progress + '%';
    
    // Update progress text
    document.getElementById('progress-text').textContent = 
        `${data.processed?.toLocaleString() || 0} of ${data.total?.toLocaleString() || 0} records`;
    
    // Update statistics
    document.getElementById('total-records').textContent = (data.total || 0).toLocaleString();
    document.getElementById('verified-records').textContent = (data.verified || 0).toLocaleString();
    document.getElementById('failed-records').textContent = ((data.processed || 0) - (data.verified || 0)).toLocaleString();
    
    const successRate = (data.processed > 0) ? Math.round((data.verified / data.processed) * 100) : 0;
    document.getElementById('verification-rate').textContent = successRate + '%';
    
    // Update resource-specific progress if available
    if (data.resource_progress) {
        updateResourceProgress(data.resource_progress);
        resourceProgressContainer.style.display = 'block';
    }
    
    // Update current activity
    if (data.current_activity) {
        document.getElementById('current-activity-text').textContent = data.current_activity;
        currentActivity.style.display = 'block';
    }
    
    // Update status and UI based on verification state
    const statusBadge = document.getElementById('verification-status-badge');
    const startBtn = document.getElementById('start-verification-btn');
    
    if (data.status === 'completed') {
        statusBadge.textContent = 'Completed';
        statusBadge.className = 'badge bg-success';
        startBtn.textContent = 'Verification Complete';
        progressBar.className = 'progress-bar bg-success';
        spinner.style.display = 'none';
        currentActivity.style.display = 'none';
    } else if (data.status === 'failed') {
        statusBadge.textContent = 'Failed';
        statusBadge.className = 'badge bg-danger';
        startBtn.textContent = 'Verification Failed';
        progressBar.className = 'progress-bar bg-danger';
        spinner.style.display = 'none';
        currentActivity.style.display = 'none';
    } else if (data.status === 'in_progress') {
        statusBadge.textContent = 'In Progress';
        statusBadge.className = 'badge bg-warning';
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
        spinner.style.display = 'block';
    } else if (data.status === 'starting') {
        statusBadge.textContent = 'Starting...';
        statusBadge.className = 'badge bg-info';
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        spinner.style.display = 'block';
    }
}

function showVerificationResults(data) {
    const resultsContainer = document.getElementById('verification-results');
    const detailsContainer = document.getElementById('verification-details');
    
    // Build results cards
    let resultsHtml = '';
    for (const [resourceType, result] of Object.entries(data.results)) {
        const successRate = result.total > 0 ? Math.round((result.verified / result.total) * 100) : 0;
        let cardClass = 'border-success';
        let statusClass = 'text-success';
        let statusIcon = '✓';
        
        if (successRate < 95) {
            cardClass = 'border-warning';
            statusClass = 'text-warning';
            statusIcon = '⚠';
        }
        if (successRate < 80) {
            cardClass = 'border-danger';
            statusClass = 'text-danger';
            statusIcon = '✗';
        }
        
        resultsHtml += `
            <div class="col-md-4 mb-4">
                <div class="card h-100 ${cardClass}">
                    <div class="card-body text-center">
                        <h5 class="card-title text-capitalize">${resourceType}</h5>
                        <div class="${statusClass} mb-3" style="font-size: 3rem;">${statusIcon}</div>
                        <div class="row">
                            <div class="col-6">
                                <div class="h6 mb-0">${result.verified.toLocaleString()}</div>
                                <small class="text-muted">Verified</small>
                            </div>
                            <div class="col-6">
                                <div class="h6 mb-0">${result.total.toLocaleString()}</div>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="${statusClass} fw-bold">${successRate}% Success</span>
                        </div>
                        ${result.errors && result.errors.length > 0 ? 
                            `<button class="btn btn-outline-danger btn-sm mt-2" 
                             onclick="showErrorDetails('${resourceType}', ${JSON.stringify(result.errors).replace(/"/g, '&quot;')})">
                                View ${result.errors.length} Errors
                            </button>` : ''
                        }
                    </div>
                </div>
            </div>
        `;
    }
    
    resultsContainer.innerHTML = resultsHtml;
    resultsContainer.style.display = 'flex';
    detailsContainer.style.display = 'block';
    
    // Build details tabs
    buildDetailsTabs(data.results);
}

function buildDetailsTabs(results) {
    const tabsList = document.getElementById('details-tabs');
    const tabsContent = document.getElementById('details-tab-content');
    
    let tabsHtml = '';
    let contentHtml = '';
    let isFirst = true;
    
    for (const [resourceType, result] of Object.entries(results)) {
        const tabId = resourceType + '-tab';
        const contentId = resourceType + '-content';
        
        tabsHtml += `
            <li class="nav-item" role="presentation">
                <button class="nav-link ${isFirst ? 'active' : ''}" 
                        id="${tabId}" 
                        data-bs-toggle="tab" 
                        data-bs-target="#${contentId}" 
                        type="button" 
                        role="tab">
                    ${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)}
                </button>
            </li>
        `;
        
        contentHtml += `
            <div class="tab-pane fade ${isFirst ? 'show active' : ''}" 
                 id="${contentId}" 
                 role="tabpanel" 
                 aria-labelledby="${tabId}">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Verification Summary</h6>
                        <ul class="list-unstyled">
                            <li><strong>Total Records:</strong> ${result.total.toLocaleString()}</li>
                            <li><strong>Verified:</strong> ${result.verified.toLocaleString()}</li>
                            <li><strong>Failed:</strong> ${(result.total - result.verified).toLocaleString()}</li>
                            <li><strong>Success Rate:</strong> ${result.total > 0 ? Math.round((result.verified / result.total) * 100) : 0}%</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Common Issues</h6>
                        ${result.errors && result.errors.length > 0 ? 
                            `<ul class="list-group list-group-flush">
                                ${result.errors.slice(0, 5).map(error => 
                                    `<li class="list-group-item px-0 py-1"><small>${error}</small></li>`
                                ).join('')}
                                ${result.errors.length > 5 ? 
                                    `<li class="list-group-item px-0 py-1"><small class="text-muted">...and ${result.errors.length - 5} more</small></li>` 
                                    : ''
                                }
                            </ul>` 
                            : '<p class="text-muted">No issues found</p>'
                        }
                    </div>
                </div>
            </div>
        `;
        
        isFirst = false;
    }
    
    tabsList.innerHTML = tabsHtml;
    tabsContent.innerHTML = contentHtml;
}

function updateResourceProgress(resourceProgress) {
    const container = document.getElementById('resource-progress-bars');
    let progressHtml = '';
    
    for (const [resourceType, progress] of Object.entries(resourceProgress)) {
        const percentage = progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0;
        const statusColor = percentage === 100 ? 'success' : 'info';
        
        progressHtml += `
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-capitalize fw-medium">${resourceType}</span>
                    <span class="text-muted small">${progress.processed || 0}/${progress.total || 0}</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-${statusColor}" 
                         role="progressbar" 
                         style="width: ${percentage}%"
                         aria-valuenow="${percentage}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = progressHtml;
}

function checkVerificationStatus() {
    const url = new URL(`{{ route('data-migration.api.verification-status', $migration) }}`);
    if (currentVerificationId) {
        url.searchParams.append('verification_id', currentVerificationId);
    }
    
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateVerificationProgress(data.data);
            
            // Check if verification is complete
            if (data.data.status === 'completed' || data.data.status === 'failed') {
                clearInterval(verificationInterval);
                showVerificationResults(data.data);
            }
        } else {
            console.error('Verification status check failed:', data.error);
            // Don't stop polling on single error, but show feedback
            document.getElementById('current-activity-text').textContent = 'Temporary connection issue, retrying...';
        }
    })
    .catch(error => {
        console.error('Error checking verification status:', error);
        document.getElementById('current-activity-text').textContent = 'Connection issue, retrying...';
        // Don't immediately stop polling on network errors
    });
}

function showErrorDetails(resourceType, errors) {
    const modal = document.getElementById('error-details-modal');
    const content = document.getElementById('error-details-content');
    
    document.getElementById('errorDetailsModalLabel').textContent = 
        `${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)} Verification Errors`;
    
    let errorHtml = `
        <div class="mb-3">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Found ${errors.length} verification error${errors.length > 1 ? 's' : ''} for ${resourceType}
            </div>
        </div>
        <div class="list-group">
            ${errors.map((error, index) => 
                `<div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1 text-danger">Error ${index + 1}</h6>
                        <small class="text-muted">#${index + 1}</small>
                    </div>
                    <p class="mb-1">${error}</p>
                    <small class="text-muted">This record failed verification and may need manual review.</small>
                </div>`
            ).join('')}
        </div>
        ${errors.length > 10 ? 
            `<div class="alert alert-info mt-3 mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Showing first 10 errors. Additional errors may exist in the full verification log.
            </div>` 
            : ''
        }
    `;
    
    content.innerHTML = errorHtml;
    
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
}
</script>
@endsection