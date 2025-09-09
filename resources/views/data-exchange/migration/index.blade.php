@extends('layouts.app')

@section('title', 'Data Migration Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 text-primary">Data Migration Dashboard</h1>
    <a href="{{ route('data-migration.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>
        New Migration
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Statistics Cards -->
<div class="row mb-4" id="stats-cards">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="fas fa-database text-primary fa-lg"></i>
                    </div>
                </div>
                <div class="ms-3">
                    <h6 class="card-title text-muted mb-1">Total Migrations</h6>
                    <h4 class="mb-0" id="total-migrations">{{ $migrations->total() }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="fas fa-clock text-warning fa-lg"></i>
                    </div>
                </div>
                <div class="ms-3">
                    <h6 class="card-title text-muted mb-1">Active Migrations</h6>
                    <h4 class="mb-0" id="active-migrations">
                        {{ $migrations->whereIn('status', ['pending', 'in_progress'])->count() }}
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
                    <h6 class="card-title text-muted mb-1">Completed</h6>
                    <h4 class="mb-0" id="completed-migrations">
                        {{ $migrations->where('status', 'completed')->count() }}
                    </h4>
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
                    <h6 class="card-title text-muted mb-1">Failed</h6>
                    <h4 class="mb-0" id="failed-migrations">
                        {{ $migrations->where('status', 'failed')->count() }}
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Migrations Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Recent Migrations</h5>
    </div>

    @if($migrations->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Resources</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($migrations as $migration)
                    <tr data-migration-id="{{ $migration->id }}">
                        <td>
                            <div>
                                <div class="fw-medium">
                                    <a href="{{ route('data-migration.show', $migration) }}" class="text-decoration-none">
                                        {{ $migration->name }}
                                    </a>
                                </div>
                                <small class="text-muted">
                                    {{ $migration->processed_items }}/{{ $migration->total_items }} items
                                </small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                @foreach($migration->resource_types as $type)
                                    <span class="badge
                                        {{ $type === 'clients' ? 'bg-primary' : '' }}
                                        {{ $type === 'cases' ? 'bg-success' : '' }}
                                        {{ $type === 'sessions' ? 'bg-info' : '' }}">
                                        {{ ucfirst($type) }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <span class="badge
                                {{ $migration->status === 'completed' ? 'bg-success' : '' }}
                                {{ $migration->status === 'in_progress' ? 'bg-warning' : '' }}
                                {{ $migration->status === 'failed' ? 'bg-danger' : '' }}
                                {{ $migration->status === 'pending' ? 'bg-secondary' : '' }}
                                {{ $migration->status === 'cancelled' ? 'bg-danger' : '' }}">
                                {{ ucfirst($migration->status) }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-primary" role="progressbar" 
                                         style="width: {{ $migration->progress_percentage }}%" 
                                         aria-valuenow="{{ $migration->progress_percentage }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted">{{ $migration->progress_percentage }}%</small>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted">{{ $migration->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('data-migration.show', $migration) }}" 
                                   class="btn btn-outline-primary btn-sm">View</a>
                                
                                @if(in_array($migration->status, ['pending', 'in_progress']))
                                    <button onclick="cancelMigration({{ $migration->id }})" 
                                            class="btn btn-outline-danger btn-sm">Cancel</button>
                                @endif
                                
                                @if($migration->status === 'failed' || $migration->batches->where('status', 'failed')->count() > 0)
                                    <button onclick="retryMigration({{ $migration->id }})" 
                                            class="btn btn-outline-warning btn-sm">Retry</button>
                                @endif
                                
                                @if(!in_array($migration->status, ['in_progress']))
                                    <form method="POST" action="{{ route('data-migration.destroy', $migration) }}" 
                                          class="d-inline" onsubmit="return confirm('Are you sure you want to delete this migration and all its data?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer">
        {{ $migrations->links() }}
    </div>
    @else
        <div class="card-body text-center py-5">
            <i class="fas fa-database fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No migrations</h5>
            <p class="text-muted mb-4">Get started by creating a new data migration.</p>
            <a href="{{ route('data-migration.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                New Migration
            </a>
        </div>
    @endif
</div>

<script>
// Auto-refresh active migrations every 30 seconds
let refreshInterval;

function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        const activeMigrations = document.querySelectorAll('[data-migration-id]');
        if (activeMigrations.length === 0) return;

        // Update statistics
        fetch('{{ route("data-migration.api.stats") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('total-migrations').textContent = data.data.total_migrations;
                    document.getElementById('active-migrations').textContent = data.data.active_migrations;
                    document.getElementById('completed-migrations').textContent = data.data.completed_migrations;
                    document.getElementById('failed-migrations').textContent = data.data.failed_migrations;
                }
            })
            .catch(error => console.error('Error updating stats:', error));

        // Update individual migration statuses
        activeMigrations.forEach(row => {
            const migrationId = row.dataset.migrationId;
            updateMigrationRow(migrationId, row);
        });
    }, 30000);
}

function updateMigrationRow(migrationId, row) {
    fetch(`{{ url('data-migration/api') }}/${migrationId}/status`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const migration = data.data;
                
                // Update progress bar
                const progressBar = row.querySelector('.progress-bar');
                const progressText = row.querySelector('.text-muted');
                if (progressBar && progressText) {
                    progressBar.style.width = migration.progress_percentage + '%';
                    progressBar.setAttribute('aria-valuenow', migration.progress_percentage);
                    progressText.textContent = migration.progress_percentage + '%';
                }
                
                // Update status badge
                const statusBadge = row.querySelector('.badge');
                if (statusBadge) {
                    // Remove old Bootstrap badge classes
                    statusBadge.className = statusBadge.className.replace(/bg-(success|warning|danger|secondary|primary|info)/g, '');
                    const statusClass = getStatusClass(migration.status);
                    statusBadge.className += ' ' + statusClass;
                    statusBadge.textContent = migration.status.charAt(0).toUpperCase() + migration.status.slice(1);
                }
                
                // Update items count
                const itemsText = row.querySelector('small.text-muted');
                if (itemsText) {
                    itemsText.textContent = `${migration.processed_items}/${migration.total_items} items`;
                }
            }
        })
        .catch(error => console.error('Error updating migration status:', error));
}

function getStatusClass(status) {
    const classes = {
        'completed': 'bg-success',
        'in_progress': 'bg-warning',
        'failed': 'bg-danger',
        'pending': 'bg-secondary',
        'cancelled': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function cancelMigration(migrationId) {
    if (!confirm('Are you sure you want to cancel this migration?')) return;
    
    fetch(`{{ url('data-migration/api') }}/${migrationId}/cancel`, {
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

function retryMigration(migrationId) {
    if (!confirm('Are you sure you want to retry failed batches for this migration?')) return;
    
    fetch(`{{ url('data-migration/api') }}/${migrationId}/retry`, {
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

// Start auto-refresh when page loads
document.addEventListener('DOMContentLoaded', startAutoRefresh);

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(refreshInterval);
    } else {
        startAutoRefresh();
    }
});
</script>
@endsection