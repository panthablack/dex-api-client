@extends('layouts.app')

@section('title', 'Migration Details - ' . $migration->name)

@section('content')
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ route('data-migration.index') }}" class="text-decoration-none">
                Data Migration
            </a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
            {{ $migration->name }}
        </li>
    </ol>
</nav>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2 text-primary">{{ $migration->name }}</h1>
        <div class="d-flex align-items-center gap-3 mt-2">
            <span class="badge
                {{ $migration->status === 'completed' ? 'bg-success' : '' }}
                {{ $migration->status === 'in_progress' ? 'bg-warning' : '' }}
                {{ $migration->status === 'failed' ? 'bg-danger' : '' }}
                {{ $migration->status === 'pending' ? 'bg-secondary' : '' }}
                {{ $migration->status === 'cancelled' ? 'bg-danger' : '' }}" id="migration-status">
                {{ ucfirst($migration->status) }}
            </span>
            <small class="text-muted">Created {{ $migration->created_at->diffForHumans() }}</small>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        @if(in_array($migration->status, ['pending', 'in_progress']))
            <button onclick="cancelMigration()" class="btn btn-danger btn-sm">
                Cancel Migration
            </button>
        @endif
        
        @if($migration->status === 'failed' || $migration->batches->where('status', 'failed')->count() > 0)
            <button onclick="retryMigration()" class="btn btn-warning btn-sm">
                Retry Failed Batches
            </button>
        @endif
        
        @if(in_array($migration->status, ['completed', 'failed']) && $migration->batches->where('status', 'completed')->count() > 0)
            <button onclick="quickVerifyData()" class="btn btn-success btn-sm">
                Quick Verify
            </button>
            <a href="{{ route('data-migration.verification', $migration) }}" class="btn btn-info btn-sm">
                Full Verification
            </a>
        @endif
        
        <button onclick="refreshStatus()" class="btn btn-primary btn-sm">
            <i class="fas fa-sync-alt me-1"></i>
            Refresh
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Progress Overview -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-chart-bar text-primary fa-lg"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-title text-muted mb-1">Total Progress</h6>
                        <h4 class="mb-0" id="progress-percentage">{{ $migration->progress_percentage }}%</h4>
                    </div>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" 
                         style="width: {{ $migration->progress_percentage }}%" 
                         id="progress-bar"
                         aria-valuenow="{{ $migration->progress_percentage }}" 
                         aria-valuemin="0" 
                         aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="fas fa-list-ol text-info fa-lg"></i>
                    </div>
                </div>
                <div class="ms-3">
                    <h6 class="card-title text-muted mb-1">Items Processed</h6>
                    <h4 class="mb-0">
                        <span id="processed-items">{{ $migration->processed_items }}</span>/<span id="total-items">{{ $migration->total_items }}</span>
                    </h4>
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
                    <h6 class="card-title text-muted mb-1">Success Rate</h6>
                    <h4 class="mb-0" id="success-rate">{{ $migration->success_rate }}%</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                        <i class="fas fa-exclamation-triangle text-danger fa-lg"></i>
                    </div>
                </div>
                <div class="ms-3">
                    <h6 class="card-title text-muted mb-1">Failed Items</h6>
                    <h4 class="mb-0" id="failed-items">{{ $migration->failed_items }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resource Types and Filters -->
<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Resource Types</h5>
            </div>
            <div class="card-body">
                @foreach($migration->resource_types as $type)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge
                            {{ $type === 'clients' ? 'bg-primary' : '' }}
                            {{ $type === 'cases' ? 'bg-success' : '' }}
                            {{ $type === 'sessions' ? 'bg-info' : '' }}">
                            {{ ucfirst($type) }}
                        </span>
                        @php
                            $typeBatches = $migration->batches->where('resource_type', $type);
                            $typeCompleted = $typeBatches->where('status', 'completed');
                            $typeProgress = $typeBatches->count() > 0 ? round(($typeCompleted->count() / $typeBatches->count()) * 100) : 0;
                        @endphp
                        <small class="text-muted">{{ $typeProgress }}% complete</small>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Migration Settings</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">Batch Size:</dt>
                    <dd class="col-sm-7">{{ $migration->batch_size }} items</dd>
                    
                    @if($migration->filters && count($migration->filters) > 0)
                        @if(isset($migration->filters['date_from']))
                            <dt class="col-sm-5 text-muted">Date From:</dt>
                            <dd class="col-sm-7">{{ $migration->filters['date_from'] }}</dd>
                        @endif
                        @if(isset($migration->filters['date_to']))
                            <dt class="col-sm-5 text-muted">Date To:</dt>
                            <dd class="col-sm-7">{{ $migration->filters['date_to'] }}</dd>
                        @endif
                    @else
                        <dt class="col-sm-5 text-muted">Date Range:</dt>
                        <dd class="col-sm-7">All available data</dd>
                    @endif
                    
                    <dt class="col-sm-5 text-muted">Started:</dt>
                    <dd class="col-sm-7">
                        {{ $migration->started_at ? $migration->started_at->format('M j, Y H:i') : 'Not started' }}
                    </dd>
                    
                    @if($migration->completed_at)
                        <dt class="col-sm-5 text-muted">Completed:</dt>
                        <dd class="col-sm-7">{{ $migration->completed_at->format('M j, Y H:i') }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Batch Details -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Batch Details</h5>
        <div class="btn-group btn-group-sm" role="group">
            @foreach($migration->resource_types as $type)
                <button onclick="filterBatches('{{ $type }}')" 
                        class="btn btn-outline-secondary batch-filter"
                        data-resource="{{ $type }}">
                    {{ ucfirst($type) }}
                </button>
            @endforeach
            <button onclick="filterBatches('all')" 
                    class="btn btn-outline-secondary batch-filter active"
                    data-resource="all">
                All
            </button>
        </div>
    </div>

    @if($migration->batches->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Batch</th>
                        <th>Resource</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Started</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody id="batches-table">
                @foreach($migration->batches as $batch)
                    <tr data-resource-type="{{ $batch->resource_type }}" data-batch-id="{{ $batch->id }}">
                        <td>
                            <div class="fw-medium">#{{ $batch->batch_number }}</div>
                            <small class="text-muted">Page {{ $batch->page_index }}</small>
                        </td>
                        <td>
                            <span class="badge
                                {{ $batch->resource_type === 'clients' ? 'bg-primary' : '' }}
                                {{ $batch->resource_type === 'cases' ? 'bg-success' : '' }}
                                {{ $batch->resource_type === 'sessions' ? 'bg-info' : '' }}">
                                {{ ucfirst($batch->resource_type) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge batch-status
                                {{ $batch->status === 'completed' ? 'bg-success' : '' }}
                                {{ $batch->status === 'processing' ? 'bg-warning' : '' }}
                                {{ $batch->status === 'failed' ? 'bg-danger' : '' }}
                                {{ $batch->status === 'pending' ? 'bg-secondary' : '' }}">
                                {{ ucfirst($batch->status) }}
                            </span>
                            @if($batch->error_message)
                                <div class="mt-1">
                                    <button onclick="showError('{{ addslashes($batch->error_message) }}')" 
                                            class="btn btn-link btn-sm text-danger p-0">
                                        View Error
                                    </button>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="batch-progress">
                                {{ $batch->items_stored }}/{{ $batch->items_received }} stored
                            </div>
                            @if($batch->items_received > 0)
                                <small class="text-muted">
                                    {{ round(($batch->items_stored / $batch->items_received) * 100) }}% success
                                </small>
                            @endif
                        </td>
                        <td>
                            <small class="text-muted">{{ $batch->started_at ? $batch->started_at->format('H:i:s') : '-' }}</small>
                        </td>
                        <td>
                            <small class="text-muted">
                                @if($batch->started_at && $batch->completed_at)
                                    {{ $batch->started_at->diffInSeconds($batch->completed_at) }}s
                                @elseif($batch->started_at)
                                    {{ $batch->started_at->diffForHumans(null, true) }} ago
                                @else
                                    -
                                @endif
                            </small>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div class="card-body text-center py-5">
            <i class="fas fa-database fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No batches yet</h5>
            <p class="text-muted">Batches will appear here once the migration is initiated.</p>
        </div>
    @endif
</div>

<!-- Export Options -->
@if($migration->status === 'completed' || $migration->batches->where('status', 'completed')->count() > 0)
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Export Migrated Data</h5>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($migration->resource_types as $type)
                    @php
                        $completedBatches = $migration->batches->where('resource_type', $type)->where('status', 'completed');
                    @endphp
                    @if($completedBatches->count() > 0)
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="fw-medium mb-2">{{ ucfirst($type) }}</h6>
                                <p class="text-muted mb-3">
                                    {{ $completedBatches->sum('items_stored') }} items migrated
                                </p>
                                <div class="d-grid gap-2">
                                    <a href="{{ route('data-migration.api.export', ['migration' => $migration, 'resource_type' => $type, 'format' => 'csv']) }}" 
                                       class="btn btn-outline-primary btn-sm">
                                        Export CSV
                                    </a>
                                    <a href="{{ route('data-migration.api.export', ['migration' => $migration, 'resource_type' => $type, 'format' => 'json']) }}" 
                                       class="btn btn-outline-secondary btn-sm">
                                        Export JSON
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif

<!-- Error Modal -->
<div class="modal fade" id="error-modal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Batch Error Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="error-message" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let refreshInterval;
const migrationId = {{ $migration->id }};

function startAutoRefresh() {
    refreshInterval = setInterval(refreshStatus, 10000); // Every 10 seconds
}

function refreshStatus() {
    fetch(`{{ route('data-migration.api.status', $migration) }}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateMigrationDisplay(data.data);
            }
        })
        .catch(error => console.error('Error refreshing status:', error));
}

function updateMigrationDisplay(migration) {
    // Update status badge
    const statusElement = document.getElementById('migration-status');
    if (statusElement) {
        statusElement.className = statusElement.className.replace(/bg-(success|warning|danger|secondary|primary|info)/g, '');
        statusElement.className += ' ' + getStatusClass(migration.status);
        statusElement.textContent = migration.status.charAt(0).toUpperCase() + migration.status.slice(1);
    }
    
    // Update progress
    document.getElementById('progress-percentage').textContent = migration.progress_percentage + '%';
    document.getElementById('progress-bar').style.width = migration.progress_percentage + '%';
    document.getElementById('processed-items').textContent = migration.processed_items;
    document.getElementById('total-items').textContent = migration.total_items;
    document.getElementById('success-rate').textContent = migration.success_rate + '%';
    document.getElementById('failed-items').textContent = migration.failed_items;
    
    // Update batch statuses
    migration.batches.forEach(batch => {
        const row = document.querySelector(`[data-batch-id="${batch.id}"]`);
        if (row) {
            const statusSpan = row.querySelector('.batch-status');
            const progressDiv = row.querySelector('.batch-progress');
            
            if (statusSpan) {
                statusSpan.className = statusSpan.className.replace(/bg-(success|warning|danger|secondary|primary|info)/g, '');
                statusSpan.className += ' ' + getStatusClass(batch.status);
                statusSpan.textContent = batch.status.charAt(0).toUpperCase() + batch.status.slice(1);
            }
            
            if (progressDiv) {
                progressDiv.textContent = `${batch.items_stored}/${batch.items_received} stored`;
            }
        }
    });
}

function getStatusClass(status) {
    const classes = {
        'completed': 'bg-success',
        'processing': 'bg-warning',
        'in_progress': 'bg-warning',
        'failed': 'bg-danger',
        'pending': 'bg-secondary',
        'cancelled': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function filterBatches(resourceType) {
    const rows = document.querySelectorAll('#batches-table tr');
    const filters = document.querySelectorAll('.batch-filter');
    
    // Update filter buttons
    filters.forEach(filter => {
        filter.classList.remove('active');
        if (filter.classList.contains('btn-outline-secondary')) {
            filter.classList.remove('btn-secondary');
            filter.classList.add('btn-outline-secondary');
        }
    });
    
    event.target.classList.add('active');
    if (event.target.classList.contains('btn-outline-secondary')) {
        event.target.classList.remove('btn-outline-secondary');
        event.target.classList.add('btn-secondary');
    }
    
    // Show/hide rows
    rows.forEach(row => {
        if (resourceType === 'all' || row.dataset.resourceType === resourceType) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function showError(message) {
    document.getElementById('error-message').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('error-modal'));
    modal.show();
}

function cancelMigration() {
    if (!confirm('Are you sure you want to cancel this migration?')) return;
    
    fetch(`{{ route('data-migration.api.cancel', $migration) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to cancel migration');
    });
}

function retryMigration() {
    if (!confirm('Are you sure you want to retry failed batches for this migration?')) return;
    
    fetch(`{{ route('data-migration.api.retry', $migration) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to retry migration');
    });
}

function quickVerifyData() {
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Verifying...';
    button.disabled = true;
    
    fetch(`{{ route('data-migration.api.quick-verify', $migration) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        button.textContent = originalText;
        button.disabled = false;
        
        if (data.success) {
            showVerificationResults(data.data);
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        button.textContent = originalText;
        button.disabled = false;
        console.error('Error:', error);
        alert('Failed to verify data');
    });
}

function showVerificationResults(results) {
    let message = `Quick Verification Results (${results.sample_size} samples per resource):\n\n`;
    
    for (const [resourceType, result] of Object.entries(results.results)) {
        if (result.status === 'completed') {
            const rate = result.success_rate || 0;
            message += `${resourceType.toUpperCase()}: ${result.verified}/${result.total_checked} verified (${rate}%)\n`;
        } else if (result.status === 'no_data') {
            message += `${resourceType.toUpperCase()}: No data to verify\n`;
        } else {
            message += `${resourceType.toUpperCase()}: Error - ${result.error}\n`;
        }
    }
    
    alert(message);
}

// Auto-refresh for active migrations
document.addEventListener('DOMContentLoaded', function() {
    const status = '{{ $migration->status }}';
    if (status === 'in_progress' || status === 'pending') {
        startAutoRefresh();
    }
});

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(refreshInterval);
    } else if ('{{ $migration->status }}' === 'in_progress') {
        startAutoRefresh();
    }
});
</script>
@endsection