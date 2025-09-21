@extends('layouts.app')

@section('title', 'Data Migration Dashboard')

@section('content')
    <div x-data="migrationIndexApp()" x-init="init()" x-cloak>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 text-primary">Data Migration Dashboard</h1>
            <a href="{{ route('data-migration.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                New Migration
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
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
                            <h4 class="mb-0" x-text="stats.total_migrations">{{ $migrations->total() }}</h4>
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
                            <h4 class="mb-0" x-text="stats.active_migrations">
                                {{ $migrations->whereIn('status', ['PENDING', 'IN_PROGRESS'])->count() }}
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
                            <h4 class="mb-0" x-text="stats.completed_migrations">
                                {{ $migrations->where('status', 'COMPLETED')->count() }}
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
                            <h4 class="mb-0" x-text="stats.failed_migrations">
                                {{ $migrations->where('status', 'FAILED')->count() }}
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

            @if ($migrations->count() > 0)
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
                            @foreach ($migrations as $migration)
                                <tr data-migration-id="{{ $migration->id }}">
                                    <td>
                                        <div>
                                            <div class="fw-medium">
                                                <a href="{{ route('data-migration.show', $migration) }}"
                                                    class="text-decoration-none">
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
                                            @foreach ($migration->resource_types as $type)
                                                <span
                                                    class="badge
                                        {{ $type === \App\Enums\ResourceType::CLIENT ? 'bg-primary' : '' }}
                                        {{ $type === \App\Enums\ResourceType::CASE ? 'bg-success' : '' }}
                                        {{ $type === \App\Enums\ResourceType::SESSION ? 'bg-info' : '' }}">
                                                    {{ ucfirst($type) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="badge
                                {{ $migration->status === 'COMPLETED' ? 'bg-success' : '' }}
                                {{ $migration->status === 'IN_PROGRESS' ? 'bg-warning' : '' }}
                                {{ $migration->status === 'FAILED' ? 'bg-danger' : '' }}
                                {{ $migration->status === 'PENDING' ? 'bg-secondary' : '' }}
                                {{ $migration->status === 'CANCELLED' ? 'bg-danger' : '' }}">
                                            {{ ucfirst($migration->status->value) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 100px; height: 8px;">
                                                <div class="progress-bar bg-primary" role="progressbar"
                                                    style="width: {{ $migration->progress_percentage }}%"
                                                    aria-valuenow="{{ $migration->progress_percentage }}" aria-valuemin="0"
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

                                            @if (in_array($migration->status, ['PENDING', 'IN_PROGRESS']))
                                                <button @click="cancelMigration({{ $migration->id }})"
                                                    class="btn btn-outline-danger btn-sm">Cancel</button>
                                            @endif

                                            @if ($migration->status === 'FAILED' || $migration->batches->where('status', 'FAILED')->count() > 0)
                                                <button @click="retryMigration({{ $migration->id }})"
                                                    class="btn btn-outline-warning btn-sm">Retry</button>
                                            @endif

                                            @if (!in_array($migration->status, ['IN_PROGRESS']))
                                                <form method="POST"
                                                    action="{{ route('data-migration.destroy', $migration) }}"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this migration and all its data?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="btn btn-outline-danger btn-sm">Delete</button>
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

    </div> <!-- End Alpine.js wrapper -->

    <script>
        // Define status constants and helper functions inline
        const DataMigrationStatus = {
            CANCELLED: 'CANCELLED',
            COMPLETED: 'COMPLETED',
            FAILED: 'FAILED',
            IN_PROGRESS: 'IN_PROGRESS',
            PENDING: 'PENDING',
            UNKNOWN: 'UNKNOWN'
        };

        const StatusColorMappings = {
            [DataMigrationStatus.PENDING]: 'bg-secondary',
            [DataMigrationStatus.IN_PROGRESS]: 'bg-warning',
            [DataMigrationStatus.COMPLETED]: 'bg-success',
            [DataMigrationStatus.FAILED]: 'bg-danger',
            [DataMigrationStatus.CANCELLED]: 'bg-secondary',
            [DataMigrationStatus.UNKNOWN]: 'bg-light'
        };

        function getStatusClass(status) {
            return StatusColorMappings[status] || 'bg-light';
        }

        window.migrationIndexApp = function migrationIndexApp() {
            return {
                stats: {
                    total_migrations: {{ $migrations->total() }},
                    active_migrations: {{ $migrations->whereIn('status', ['PENDING', 'IN_PROGRESS'])->count() }},
                    completed_migrations: {{ $migrations->where('status', 'COMPLETED')->count() }},
                    failed_migrations: {{ $migrations->where('status', 'FAILED')->count() }}
                },
                refreshInterval: null,

                init() {
                    this.startAutoRefresh();
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) {
                            if (this.refreshInterval) clearInterval(this.refreshInterval);
                        } else {
                            this.startAutoRefresh();
                        }
                    });
                },

                startAutoRefresh() {
                    this.refreshInterval = setInterval(() => {
                        this.updateStats();
                        this.updateMigrations();
                    }, 10000);
                },

                async updateStats() {
                    try {
                        const response = await fetch('{{ route('data-migration.api.stats') }}');
                        const data = await response.json();
                        if (data.success) {
                            this.stats = data.data;
                        }
                    } catch (error) {
                        console.error('Error updating stats:', error);
                    }
                },

                async updateMigrations() {
                    const migrationRows = document.querySelectorAll('[data-migration-id]');
                    migrationRows.forEach(row => {
                        const migrationId = row.dataset.migrationId;
                        this.updateMigrationRow(migrationId, row);
                    });
                },

                async updateMigrationRow(migrationId, row) {
                    try {
                        const response = await fetch(`{{ url('data-migration/api') }}/${migrationId}/status`);
                        const data = await response.json();
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
                                statusBadge.className = statusBadge.className.replace(
                                    /bg-(success|warning|danger|secondary|primary|info)/g, '');
                                const statusClass = this.getStatusClass(migration.status);
                                statusBadge.className += ' ' + statusClass;
                                statusBadge.textContent = migration.status.charAt(0).toUpperCase() + migration
                                    .status
                                    .slice(1);
                            }

                            // Update items count
                            const itemsText = row.querySelector('small.text-muted');
                            if (itemsText) {
                                itemsText.textContent =
                                    `${migration.processed_items}/${migration.total_items} items`;
                            }
                        }
                    } catch (error) {
                        console.error('Error updating migration status:', error);
                    }
                },

                getStatusClass(status) {
                    return getStatusClass(status);
                },

                async cancelMigration(migrationId) {
                    if (!confirm('Are you sure you want to cancel this migration?')) return;

                    try {
                        const response = await fetch(`{{ url('data-migration/api') }}/${migrationId}/cancel`, {
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
                            alert('Error: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Failed to cancel migration');
                    }
                },

                async retryMigration(migrationId) {
                    if (!confirm('Are you sure you want to retry failed batches for this migration?')) return;

                    try {
                        const response = await fetch(`{{ url('data-migration/api') }}/${migrationId}/retry`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Failed to retry migration');
                    }
                }
            };
        }
    </script>
@endsection
