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
            ← Back to Migration
        </a>
        <button onclick="startFullVerification()" class="btn btn-primary" id="start-verification-btn">
            Start Full Verification
        </button>
    </div>
</div>

<!-- Verification Status Card -->
<div class="card mb-4" id="verification-status-card" style="display: none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Verification Progress</h5>
        <div id="verification-status-badge" class="badge bg-info">Initializing...</div>
    </div>
    <div class="card-body">
        <div class="progress mb-3" style="height: 25px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: 0%" 
                 id="verification-progress-bar">
                0%
            </div>
        </div>
        <div class="row text-center">
            <div class="col-md-3">
                <div class="h5 mb-0" id="total-records">0</div>
                <small class="text-muted">Total Records</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0" id="verified-records">0</div>
                <small class="text-muted">Verified</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0" id="failed-records">0</div>
                <small class="text-muted">Failed</small>
            </div>
            <div class="col-md-3">
                <div class="h5 mb-0" id="verification-rate">0%</div>
                <small class="text-muted">Success Rate</small>
            </div>
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
const migrationId = {{ $migration->id }};

function startFullVerification() {
    const button = document.getElementById('start-verification-btn');
    button.textContent = 'Starting...';
    button.disabled = true;
    
    // Show status card
    document.getElementById('verification-status-card').style.display = 'block';
    document.getElementById('verification-status-badge').textContent = 'Starting...';
    document.getElementById('verification-status-badge').className = 'badge bg-info';
    
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
            // Start polling for verification status
            verificationInterval = setInterval(checkVerificationStatus, 2000);
            document.getElementById('verification-status-badge').textContent = 'In Progress';
            document.getElementById('verification-status-badge').className = 'badge bg-warning';
        } else {
            button.textContent = 'Start Full Verification';
            button.disabled = false;
            alert('Error starting verification: ' + data.error);
        }
    })
    .catch(error => {
        button.textContent = 'Start Full Verification';
        button.disabled = false;
        console.error('Error:', error);
        alert('Failed to start verification');
    });
}

function checkVerificationStatus() {
    fetch(`{{ route('data-migration.api.verification-status', $migration) }}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateVerificationProgress(data.data);
            
            // Check if verification is complete
            if (data.data.status === 'completed' || data.data.status === 'failed') {
                clearInterval(verificationInterval);
                showVerificationResults(data.data);
            }
        }
    })
    .catch(error => {
        console.error('Error checking verification status:', error);
        clearInterval(verificationInterval);
    });
}

function updateVerificationProgress(data) {
    const progressBar = document.getElementById('verification-progress-bar');
    const progress = Math.round((data.processed / data.total) * 100);
    
    progressBar.style.width = progress + '%';
    progressBar.textContent = progress + '%';
    
    document.getElementById('total-records').textContent = data.total.toLocaleString();
    document.getElementById('verified-records').textContent = data.verified.toLocaleString();
    document.getElementById('failed-records').textContent = (data.processed - data.verified).toLocaleString();
    
    const successRate = data.processed > 0 ? Math.round((data.verified / data.processed) * 100) : 0;
    document.getElementById('verification-rate').textContent = successRate + '%';
    
    // Update status badge
    if (data.status === 'completed') {
        document.getElementById('verification-status-badge').textContent = 'Completed';
        document.getElementById('verification-status-badge').className = 'badge bg-success';
        document.getElementById('start-verification-btn').textContent = 'Verification Complete';
        progressBar.className = 'progress-bar bg-success';
    } else if (data.status === 'failed') {
        document.getElementById('verification-status-badge').textContent = 'Failed';
        document.getElementById('verification-status-badge').className = 'badge bg-danger';
        document.getElementById('start-verification-btn').textContent = 'Verification Failed';
        progressBar.className = 'progress-bar bg-danger';
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

function showErrorDetails(resourceType, errors) {
    const modal = document.getElementById('error-details-modal');
    const content = document.getElementById('error-details-content');
    
    document.getElementById('errorDetailsModalLabel').textContent = 
        `${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)} Verification Errors`;
    
    let errorHtml = `
        <div class="list-group">
            ${errors.map((error, index) => 
                `<div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">Error ${index + 1}</h6>
                    </div>
                    <p class="mb-1">${error}</p>
                </div>`
            ).join('')}
        </div>
    `;
    
    content.innerHTML = errorHtml;
    
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
}
</script>
@endsection