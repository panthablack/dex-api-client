@extends('layouts.app')

@section('title', 'Migration Details - ' . $migration->name)

@section('content')
<div x-data="migrationApp()" x-init="init()" x-cloak>
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
            <span class="badge" 
                  :class="getStatusClass(migration.status)"
                  x-text="migration.status.charAt(0).toUpperCase() + migration.status.slice(1)">
                {{ ucfirst($migration->status) }}
            </span>
            <small class="text-muted">Created {{ $migration->created_at->diffForHumans() }}</small>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        <button x-show="['pending', 'in_progress'].includes(migration.status)" 
                @click="cancelMigration()" 
                class="btn btn-danger btn-sm">
            Cancel Migration
        </button>
        
        <button x-show="migration.status === 'failed' || migration.batches.some(b => b.status === 'failed')" 
                @click="retryMigration()" 
                class="btn btn-warning btn-sm">
            Retry Failed Batches
        </button>
        
        <button x-show="isVerificationAvailable()" 
                @click="quickVerifyData()" 
                class="btn btn-success btn-sm">
            Quick Verify
        </button>
        
        <a x-show="isVerificationAvailable()" 
           href="{{ route('data-migration.verification', $migration) }}" 
           class="btn btn-info btn-sm">
            Full Verification
        </a>
        
        <button @click="refreshStatus()" class="btn btn-primary btn-sm">
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
                        <h4 class="mb-0" x-text="migration.progress_percentage + '%'">{{ $migration->progress_percentage }}%</h4>
                    </div>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" 
                         :style="`width: ${migration.progress_percentage}%`"
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
                        <span x-text="migration.processed_items">{{ $migration->processed_items }}</span>/<span x-text="migration.total_items">{{ $migration->total_items }}</span>
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
                    <h4 class="mb-0" x-text="migration.success_rate + '%'">{{ $migration->success_rate }}%</h4>
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
                    <h4 class="mb-0" x-text="migration.failed_items">{{ $migration->failed_items }}</h4>
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

    <div x-show="migration.batches.length > 0">
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
                <tbody>
                <template x-for="batch in migration.batches" :key="batch.id">
                    <tr :data-resource-type="batch.resource_type" :data-batch-id="batch.id">
                        <td>
                            <div class="fw-medium" x-text="`#${batch.batch_number}`"></div>
                            <small class="text-muted" x-text="`Page ${batch.page_index || batch.batch_number}`"></small>
                        </td>
                        <td>
                            <span class="badge text-capitalize"
                                  :class="{
                                      'bg-primary': batch.resource_type === 'clients',
                                      'bg-success': batch.resource_type === 'cases', 
                                      'bg-info': batch.resource_type === 'sessions'
                                  }"
                                  x-text="batch.resource_type">
                            </span>
                        </td>
                        <td>
                            <span class="badge" 
                                  :class="getStatusClass(batch.status)"
                                  x-text="batch.status.charAt(0).toUpperCase() + batch.status.slice(1)">
                            </span>
                            <div x-show="batch.error_message" class="mt-1">
                                <button @click="showError(batch.error_message)" 
                                        class="btn btn-link btn-sm text-danger p-0">
                                    View Error
                                </button>
                            </div>
                        </td>
                        <td>
                            <div>
                                <div x-text="`${batch.items_stored || 0}/${batch.items_received || 0} stored`"></div>
                                <small x-show="batch.items_received > 0" 
                                       class="text-muted" 
                                       x-text="`${Math.round(((batch.items_stored || 0) / batch.items_received) * 100)}% success`">
                                </small>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted" x-text="formatTimestamp(batch.started_at)"></small>
                        </td>
                        <td>
                            <small class="text-muted" x-text="formatDuration(batch.started_at, batch.completed_at)"></small>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    
    <div x-show="migration.batches.length === 0" class="card-body text-center py-5">
        <i class="fas fa-database fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No batches yet</h5>
        <p class="text-muted">Batches will appear here once the migration is initiated.</p>
    </div>
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

<!-- Quick Verify Results Modal -->
<div class="modal fade" id="quick-verify-modal" tabindex="-1" aria-labelledby="quickVerifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickVerifyModalLabel">Quick Verification Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="verify-results-content">
                    <!-- Results will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="{{ route('data-migration.verification', $migration) }}" class="btn btn-primary" data-bs-dismiss="modal">Run Full Verification</a>
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
    
    // Check if we need to create the batch table (was showing "No batches yet")
    const batchTableContainer = document.querySelector('#batches-table');
    const noBatchesMessage = document.querySelector('.card-body.text-center.py-5');
    
    if (migration.batches.length > 0 && noBatchesMessage) {
        // Replace "No batches yet" with batch table
        const batchCard = noBatchesMessage.parentElement.parentElement;
        batchCard.innerHTML = `
            <div class="card-header">
                <h5 class="mb-0">Batch Details</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Started</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody id="batches-table">
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // Update or create batch rows
    migration.batches.forEach(batch => {
        let row = document.querySelector(`[data-batch-id="${batch.id}"]`);
        
        if (!row) {
            // Create new batch row
            const tbody = document.querySelector('#batches-table');
            if (tbody) {
                const newRow = document.createElement('tr');
                newRow.setAttribute('data-resource-type', batch.resource_type);
                newRow.setAttribute('data-batch-id', batch.id);
                
                const progressText = `${batch.items_stored}/${batch.items_received} stored`;
                const successRate = batch.items_received > 0 ? Math.round((batch.items_stored / batch.items_received) * 100) : 0;
                
                let startedText = '-';
                let durationText = '-';
                
                if (batch.started_at) {
                    const startTime = new Date(batch.started_at);
                    startedText = startTime.toLocaleString();
                    
                    if (batch.completed_at) {
                        const endTime = new Date(batch.completed_at);
                        const durationSeconds = Math.round((endTime - startTime) / 1000);
                        durationText = `${durationSeconds}s`;
                    } else {
                        const now = new Date();
                        const elapsed = Math.round((now - startTime) / 1000);
                        durationText = `${elapsed}s ago`;
                    }
                }
                
                newRow.innerHTML = `
                    <td>
                        <div class="fw-medium">#${batch.batch_number}</div>
                        <small class="text-muted">Page ${batch.page_index || batch.batch_number}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">${batch.resource_type}</span>
                    </td>
                    <td>
                        <span class="badge batch-status ${getStatusClass(batch.status)}">
                            ${batch.status.charAt(0).toUpperCase() + batch.status.slice(1)}
                        </span>
                        ${batch.error_message ? `<br><small class="text-danger">${batch.error_message}</small>` : ''}
                    </td>
                    <td>
                        <div class="batch-progress">
                            <div>${progressText}</div>
                            ${batch.items_received > 0 ? `<small class="text-muted">${successRate}% success</small>` : ''}
                        </div>
                    </td>
                    <td>
                        <small class="text-muted">${startedText}</small>
                    </td>
                    <td>
                        <small class="text-muted">${durationText}</small>
                    </td>
                `;
                
                tbody.appendChild(newRow);
                row = newRow;
            }
        }
        
        if (row) {
            const statusSpan = row.querySelector('.batch-status');
            const progressDiv = row.querySelector('.batch-progress');
            
            if (statusSpan) {
                statusSpan.className = statusSpan.className.replace(/bg-(success|warning|danger|secondary|primary|info)/g, '');
                statusSpan.className += ' ' + getStatusClass(batch.status);
                statusSpan.textContent = batch.status.charAt(0).toUpperCase() + batch.status.slice(1);
            }
            
            if (progressDiv) {
                const progressText = `${batch.items_stored}/${batch.items_received} stored`;
                const successRate = batch.items_received > 0 ? Math.round((batch.items_stored / batch.items_received) * 100) : 0;
                progressDiv.innerHTML = `
                    <div>${progressText}</div>
                    ${batch.items_received > 0 ? `<small class="text-muted">${successRate}% success</small>` : ''}
                `;
            }

            // Update timestamps and duration
            const timestampCell = row.querySelector('td:nth-child(4)');
            const durationCell = row.querySelector('td:nth-child(5)');
            
            if (timestampCell) {
                if (batch.started_at) {
                    const startTime = new Date(batch.started_at);
                    timestampCell.innerHTML = `<small class="text-muted">${startTime.toLocaleString()}</small>`;
                } else {
                    timestampCell.innerHTML = `<small class="text-muted">-</small>`;
                }
            }
            
            if (durationCell) {
                if (batch.started_at && batch.completed_at) {
                    const start = new Date(batch.started_at);
                    const end = new Date(batch.completed_at);
                    const durationSeconds = Math.round((end - start) / 1000);
                    durationCell.innerHTML = `<small class="text-muted">${durationSeconds}s</small>`;
                } else if (batch.started_at) {
                    const start = new Date(batch.started_at);
                    const now = new Date();
                    const elapsed = Math.round((now - start) / 1000);
                    durationCell.innerHTML = `<small class="text-muted">${elapsed}s ago</small>`;
                } else {
                    durationCell.innerHTML = `<small class="text-muted">-</small>`;
                }
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
            showAlert('Error: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to cancel migration', 'error');
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
            showAlert(data.message, 'success');
            location.reload();
        } else {
            showAlert('Error: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to retry migration', 'error');
    });
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

function migrationApp() {
    return {
        migration: {
            id: {{ $migration->id }},
            name: @json($migration->name),
            status: @json($migration->status),
            progress_percentage: {{ $migration->progress_percentage }},
            success_rate: {{ $migration->success_rate }},
            total_items: {{ $migration->total_items }},
            processed_items: {{ $migration->processed_items }},
            successful_items: {{ $migration->successful_items }},
            failed_items: {{ $migration->failed_items }},
            batches: @json($migration->batches->toArray())
        },
        refreshInterval: null,

        init() {
            if (this.migration.status === 'in_progress' || this.migration.status === 'pending') {
                this.startAutoRefresh();
            }
            
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (this.refreshInterval) clearInterval(this.refreshInterval);
                } else if (this.migration.status === 'in_progress') {
                    this.startAutoRefresh();
                }
            });
        },

        startAutoRefresh() {
            this.refreshInterval = setInterval(() => this.refreshStatus(), 10000);
        },

        async refreshStatus() {
            try {
                const response = await fetch(`{{ route('data-migration.api.status', $migration) }}`);
                const data = await response.json();
                
                if (data.success) {
                    const oldStatus = this.migration.status;
                    this.migration = data.data;
                    
                    // Check if migration just completed (status changed to completed/failed)
                    if ((oldStatus === 'in_progress' || oldStatus === 'pending') && 
                        (data.data.status === 'completed' || data.data.status === 'failed')) {
                        
                        // Stop auto-refresh
                        if (this.refreshInterval) {
                            clearInterval(this.refreshInterval);
                            this.refreshInterval = null;
                        }
                        
                        // Reload page after short delay to ensure all server-side conditionals update
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                        
                        return;
                    }
                    
                    if (data.data.status === 'completed' || data.data.status === 'failed' || data.data.status === 'cancelled') {
                        if (this.refreshInterval) {
                            clearInterval(this.refreshInterval);
                            this.refreshInterval = null;
                        }
                    }
                }
            } catch (error) {
                console.error('Error fetching migration status:', error);
            }
        },

        isVerificationAvailable() {
            return (['completed', 'failed'].includes(this.migration.status) && 
                    this.migration.batches.some(b => b.status === 'completed'));
        },

        getStatusClass(status) {
            const classes = {
                'completed': 'bg-success',
                'processing': 'bg-warning',
                'in_progress': 'bg-warning',
                'failed': 'bg-danger',
                'pending': 'bg-secondary',
                'cancelled': 'bg-danger'
            };
            return classes[status] || 'bg-secondary';
        },

        formatTimestamp(timestamp) {
            if (!timestamp) return '-';
            return new Date(timestamp).toLocaleString();
        },

        formatDuration(startedAt, completedAt) {
            if (!startedAt) return '-';
            
            const start = new Date(startedAt);
            if (completedAt) {
                const end = new Date(completedAt);
                const durationSeconds = Math.round((end - start) / 1000);
                return `${durationSeconds}s`;
            } else {
                const now = new Date();
                const elapsed = Math.round((now - start) / 1000);
                return `${elapsed}s ago`;
            }
        },

        showError(message) {
            document.getElementById('error-message').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('error-modal'));
            modal.show();
        },

        async cancelMigration() {
            if (!confirm('Are you sure you want to cancel this migration?')) return;
            
            try {
                const response = await fetch(`{{ route('data-migration.api.cancel', $migration) }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert('Error: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to cancel migration', 'error');
            }
        },

        async retryMigration() {
            if (!confirm('Are you sure you want to retry failed batches?')) return;
            
            try {
                const response = await fetch(`{{ route('data-migration.api.retry', $migration) }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert('Error: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to retry migration', 'error');
            }
        },

        async quickVerifyData() {
            // Show modal immediately with loading state
            this.showLoadingModal();
            
            try {
                const response = await fetch(`{{ route('data-migration.api.quick-verify', $migration) }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    this.showVerificationResults(data.data);
                } else {
                    this.showErrorInModal('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                this.showErrorInModal('Failed to verify data');
            }
        },

        showLoadingModal() {
            const loadingContent = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="text-muted">Verifying Data...</h5>
                    <p class="text-muted">Please wait while we verify your migrated data.</p>
                </div>
            `;
            
            const modalElement = document.getElementById('quick-verify-modal');
            document.getElementById('verify-results-content').innerHTML = loadingContent;
            
            // Ensure any existing modal instance is properly cleaned up
            const existingModal = bootstrap.Modal.getInstance(modalElement);
            if (existingModal) {
                existingModal.dispose();
            }
            
            // Create new modal instance
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        },

        showErrorInModal(errorMessage) {
            const errorContent = `
                <div class="text-center py-5">
                    <div class="text-danger mb-3">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-danger">Verification Failed</h5>
                    <p class="text-muted">${errorMessage}</p>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            `;
            
            document.getElementById('verify-results-content').innerHTML = errorContent;
        },

        showVerificationResults(results) {
            try {
                // Validate results structure
                if (!results || !results.results || typeof results.results !== 'object') {
                    throw new Error('Invalid verification results format');
                }

                let content = `
                    <div class="mb-3">
                        <h6 class="text-muted">Sample Size: ${results.sample_size || 10} records per resource type</h6>
                    </div>
                    <div class="row">
                `;
                
                const resultsEntries = Object.entries(results.results);
                if (resultsEntries.length === 0) {
                    content += `
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                                <h5 class="text-muted">No verification results available</h5>
                                <p class="text-muted">No data was found to verify for this migration.</p>
                            </div>
                        </div>
                    `;
                } else {
                    for (const [resourceType, result] of resultsEntries) {
                        let statusClass, statusIcon, statusText;
                        
                        if (result.status === 'completed') {
                            const rate = result.success_rate || 0;
                            if (rate >= 95) {
                                statusClass = 'text-success';
                                statusIcon = '✓';
                            } else if (rate >= 80) {
                                statusClass = 'text-warning';
                                statusIcon = '⚠';
                            } else {
                                statusClass = 'text-danger';
                                statusIcon = '✗';
                            }
                            statusText = `${result.verified || 0}/${result.total_checked || 0} verified (${rate}%)`;
                        } else if (result.status === 'no_data') {
                            statusClass = 'text-muted';
                            statusIcon = '—';
                            statusText = 'No data to verify';
                        } else {
                            statusClass = 'text-danger';
                            statusIcon = '✗';
                            statusText = `Error: ${result.error || 'Unknown error'}`;
                        }
                        
                        content += `
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-capitalize">${resourceType}</h5>
                                        <div class="${statusClass} mb-2" style="font-size: 2rem;">${statusIcon}</div>
                                        <p class="card-text ${statusClass}">${statusText}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                }
                
                content += '</div>';
                
                // Update modal content
                document.getElementById('verify-results-content').innerHTML = content;
                
                // Ensure modal is properly shown (it should already be visible from loading state)
                const modalElement = document.getElementById('quick-verify-modal');
                const existingModal = bootstrap.Modal.getInstance(modalElement);
                if (!existingModal) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            } catch (error) {
                console.error('Error displaying verification results:', error);
                this.showErrorInModal('Failed to display verification results: ' + error.message);
            }
        }
    };
}
</script>

</div>
@endsection