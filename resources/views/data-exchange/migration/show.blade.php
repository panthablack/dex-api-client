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
          <span class="badge" :class="getStatusClass(migration.status)"
            x-text="migration.status.charAt(0).toUpperCase() + migration.status.slice(1)">
            {{ ucfirst($migration->status->value) }}
          </span>
          <small class="text-muted">Created {{ $migration->created_at->diffForHumans() }}</small>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button x-show="[DataMigrationStatus.PENDING, DataMigrationStatus.IN_PROGRESS].includes(migration.status)"
          @click="cancelMigration()" class="btn btn-danger btn-sm">
          Cancel Migration
        </button>

        <button
          x-show="migration.status === DataMigrationStatus.FAILED || migration.batches.some(b => b.status === DataMigrationBatchStatus.FAILED)"
          @click="retryMigration()" class="btn btn-warning btn-sm">
          Retry Failed Batches
        </button>

        <button
          x-show="migration.status === DataMigrationStatus.IN_PROGRESS && migration.batches.every(b => b.status === DataMigrationBatchStatus.PENDING)"
          @click="restartStuckMigration()" class="btn btn-warning btn-sm">
          <i class="fas fa-redo me-1"></i>
          Restart Stuck Migration
        </button>

        <button x-show="isVerificationAvailable()" @click="quickVerifyData()" class="btn btn-success btn-sm">
          Quick Verify
        </button>

        <a x-show="isVerificationAvailable()" href="{{ route('data-migration.verification', $migration) }}"
          class="btn btn-info btn-sm">
          Full Verification
        </a>

        <button @click="refreshStatus()" class="btn btn-primary btn-sm">
          <i class="fas fa-sync-alt me-1"></i>
          Refresh
        </button>
      </div>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <!-- Progress Overview -->
    <div class="row mb-4">
      <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
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
                <h4 class="mb-0" x-text="migration.progress_percentage + '%'">
                  {{ $migration->progress_percentage }}%</h4>
              </div>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar bg-primary" role="progressbar" :style="`width: ${migration.progress_percentage}%`"
                aria-valuenow="{{ $migration->progress_percentage }}" aria-valuemin="0" aria-valuemax="100">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
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
                <span x-text="migration.processed_items">{{ $migration->processed_items }}</span>/<span
                  x-text="migration.total_items">{{ $migration->total_items }}</span>
              </h4>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
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

      <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0">
              <div class="bg-primary bg-opacity-10 p-3 rounded">
                <i class="fas fa-shield-alt text-primary fa-lg"></i>
              </div>
            </div>
            <div class="ms-3">
              <h6 class="card-title text-muted mb-1">Verification</h6>
              <h4 class="mb-0">
                <span x-show="verificationStatus.total > 0"
                  x-text="Math.round((verificationStatus.verified / verificationStatus.total) * 100) + '%'">
                  {{ $migration->verification_percentage ?? 0 }}%
                </span>
                <span x-show="verificationStatus.total === 0" class="text-muted">—</span>
              </h4>
            </div>
          </div>
        </div>
      </div>

      <div x-show="migration.failed_items > 0" class="col-xl-2 col-lg-4 col-md-6 mb-3">
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
            @foreach ([$migration->resource_type] as $type)
              @php
                $resourceType = \App\Enums\ResourceType::resolve($type);
                $resourceTypeClass = match ($resourceType->value) {
                    \App\Enums\ResourceType::CLIENT => 'bg-pastel-lavender',
                    \App\Enums\ResourceType::CASE => 'bg-pastel-mint',
                    \App\Enums\ResourceType::CASE_CLIENT => 'bg-pastel-lavender',
                    \App\Enums\ResourceType::CLOSED_CASE => 'bg-pastel-peach',
                    \App\Enums\ResourceType::SESSION => 'bg-pastel-rose',
                    default => '',
                };
              @endphp
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="badge {{ $resourceTypeClass }}">
                    {{ \App\Helpers\StringHelpers::titleCase($resourceType->value) }}
                  </span>
                  @php
                    $typeBatches = $migration->batches->where('resource_type', $type);
                    $typeCompleted = $typeBatches->where('status', \App\Enums\DataMigrationBatchStatus::COMPLETED);
                    $typeProgress =
                        $typeBatches->count() > 0 ? round(($typeCompleted->count() / $typeBatches->count()) * 100) : 0;
                  @endphp
                  <div class="flex-grow-1 px-3">
                    <div class="progress" style="height: 6px;">
                      <div
                        class="progress-bar
                                                {{ $type === 'clients' ? 'bg-primary' : '' }}
                                                {{ $type === 'cases' ? 'bg-success' : '' }}
                                                {{ $type === 'sessions' ? 'bg-info' : '' }}"
                        role="progressbar" style="width: {{ $typeProgress }}%" aria-valuenow="{{ $typeProgress }}"
                        aria-valuemin="0" aria-valuemax="100">
                      </div>
                    </div>
                  </div>
                  <small class="text-muted">{{ $typeProgress }}% complete</small>
                </div>
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

              @if ($migration->filters && count($migration->filters) > 0)
                @if (isset($migration->filters['date_from']))
                  <dt class="col-sm-5 text-muted">Date From:</dt>
                  <dd class="col-sm-7">{{ $migration->filters['date_from'] }}</dd>
                @endif
                @if (isset($migration->filters['date_to']))
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

              @if ($migration->completed_at)
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
          @foreach ([$migration->resource_type] as $type)
            <button @click="setResourceFilter('{{ $type }}')" class="btn"
              :class="resourceFilter === '{{ $type }}' ? 'btn-secondary' : 'btn-outline-secondary'">
              {{ ucfirst($type->value) }}
            </button>
          @endforeach
          <button @click="setResourceFilter('all')" class="btn"
            :class="resourceFilter === 'all' ? 'btn-secondary' : 'btn-outline-secondary'">
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
              <template x-for="batch in filteredBatches" :key="batch.id">
                <tr>
                  <td>
                    <div class="fw-medium" x-text="`#${batch.batch_number}`"></div>
                    <small class="text-muted" x-text="`Page ${batch.page_index || batch.batch_number}`"></small>
                  </td>
                  <td>
                    <span class="badge text-capitalize"
                      :class="{
                          'bg-pastel-lavender': batch
                              .resource_type ===
                                                    '{{ \App\Enums\ResourceType::CLIENT->value }}',
                          'bg-pastel-mint': batch
                              .resource_type ===
                                                    '{{ \App\Enums\ResourceType::CASE->value }}',
                          'bg-pastel-rose': batch
                              .resource_type ===
                                                    '{{ \App\Enums\ResourceType::SESSION->value }}',
                          'bg-pastel-peach': batch
                              .resource_type ===
                                                    '{{ \App\Enums\ResourceType::CLOSED_CASE->value }}',
                      }"
                      x-text="batch.resource_type">
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="getStatusClass(batch.status)"
                      x-text="batch.status.charAt(0).toUpperCase() + batch.status.slice(1)">
                    </span>
                  </td>
                  <td>
                    <div>
                      <div x-text="`${batch.items_stored || 0}/${batch.batch_size || 0} stored`">
                      </div>
                      <small x-show="batch.batch_size > 0" class="text-muted"
                        x-text="`${Math.round(((batch.items_stored || 0) / batch.batch_size) * 100)}% success`">
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
      @if (
          $migration->status === \App\Enums\DataMigrationStatus::COMPLETED ||
              $migration->batches->where('status', \App\Enums\DataMigrationBatchStatus::COMPLETED)->count() > 0)
        <div class="card mt-4">
          <div class="card-header">
            <h5 class="card-title mb-0">Export Migrated Data</h5>
          </div>
          <div class="card-body">
            <div class="row">
              @foreach ([$migration->resource_type] as $type)
                @php
                  $resourceType = \App\Enums\ResourceType::resolve($type);
                  $completedBatches = $migration->batches
                      ->where('resource_type', $resourceType->value)
                      ->where('status', \App\Enums\DataMigrationBatchStatus::COMPLETED);
                @endphp
                @if ($completedBatches->count() > 0)
                  <div class="col-md-4 mb-3">
                    <div class="border rounded p-3">
                      <h6 class="fw-medium mb-2">{{ ucfirst($type->value) }}</h6>
                      <p class="text-muted mb-3">
                        {{ $completedBatches->sum('items_stored') }} items migrated
                      </p>
                      <div class="d-grid gap-2">
                        <a href="{{ route('data-migration.api.export', ['migration' => $migration, 'resource_type' => $resourceType->value, 'format' => 'csv']) }}"
                          class="btn btn-outline-primary btn-sm">
                          Export CSV
                        </a>
                        <a href="{{ route('data-migration.api.export', ['migration' => $migration, 'resource_type' => $resourceType->value, 'format' => 'json']) }}"
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
      <div class="modal fade" id="quick-verify-modal" tabindex="-1" aria-labelledby="quickVerifyModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="quickVerifyModalLabel">Quick Verification Results</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <!-- Loading State -->
              <div x-show="verifyModal.state === 'loading'" class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <h5>Verifying Data...</h5>
                <p class="text-muted">Please wait while we verify your migrated data.</p>
              </div>

              <!-- Error State -->
              <div x-show="verifyModal.state === 'error'" class="text-center py-5">
                <div class="text-danger mb-3">
                  <i class="fas fa-exclamation-triangle" style="font-size: 3rem;"></i>
                </div>
                <h5 class="text-danger">Verification Failed</h5>
                <p class="text-muted" x-text="verifyModal.errorMessage"></p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>

              <!-- Results State -->
              <div x-show="verifyModal.state === 'results'">
                <div class="mb-3">
                  <h6 class="text-muted">Sample Size: <span x-text="verifyModal.results?.sample_size || 10"></span>
                    records per resource
                    type</h6>
                </div>

                <!-- No Results -->
                <div x-show="verifyModal.results && Object.keys(verifyModal.results.results || {}).length === 0"
                  class="text-center py-5">
                  <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                  <h5 class="text-muted">No verification results available</h5>
                  <p class="text-muted">No data was found to verify for this migration.</p>
                </div>

                <!-- Results Grid -->
                <div x-show="verifyModal.results && Object.keys(verifyModal.results.results || {}).length > 0"
                  class="row">
                  <template x-for="[resourceType, result] in Object.entries(verifyModal.results?.results || {})"
                    :key="resourceType">
                    <div class="col-md-4 mb-3">
                      <div class="card h-100">
                        <div class="card-body text-center">
                          <h5 class="card-title text-capitalize" x-text="resourceType"></h5>
                          <div class="mb-2" style="font-size: 2rem;"
                            :class="{
                                'text-success': result.status === DataMigrationBatchStatus
                                    .COMPLETED && (result
                                        .success_rate || 0) >= 95,
                                'text-warning': result.status === DataMigrationBatchStatus
                                    .COMPLETED && (result
                                        .success_rate || 0) >= 80 && (result.success_rate ||
                                        0) < 95,
                                'text-danger': result.status === DataMigrationBatchStatus
                                    .COMPLETED && (result
                                        .success_rate || 0) < 80,
                                'text-muted': result.status === 'no_data',
                                'text-danger': result.status !== DataMigrationBatchStatus
                                    .COMPLETED && result
                                    .status !== 'no_data'
                            }"
                            x-text="result.status === DataMigrationBatchStatus.COMPLETED ?
                                                            ((result.success_rate || 0) >= 95 ? '✓' :
                                                             (result.success_rate || 0) >= 80 ? '⚠' : '✗') :
                                                            (result.status === 'no_data' ? '—' : '✗')">
                          </div>
                          <p class="card-text"
                            :class="{
                                'text-success': result.status === DataMigrationBatchStatus
                                    .COMPLETED && (result
                                        .success_rate || 0) >= 95,
                                'text-warning': result.status === DataMigrationBatchStatus
                                    .COMPLETED && (result
                                        .success_rate || 0) >= 80 && (result.success_rate ||
                                        0) < 95,
                                'text-danger': result.status === DataMigrationBatchStatus
                                    .COMPLETED && (result
                                        .success_rate || 0) < 80,
                                'text-muted': result.status === 'no_data',
                                'text-danger': result.status !== DataMigrationBatchStatus
                                    .COMPLETED && result
                                    .status !== 'no_data'
                            }"
                            x-text="result.status === DataMigrationBatchStatus.COMPLETED ?
                                                            `${result.verified || 0}/${result.total_checked || 0} verified (${result.success_rate || 0}%)` :
                                                            (result.status === 'no_data' ? 'No data to verify' :
                                                             `Error: ${result.error || 'Unknown error'}`)">
                          </p>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <a href="{{ route('data-migration.verification', $migration) }}" class="btn btn-primary">Run
                Full Verification</a>
            </div>
          </div>
        </div>
      </div>

      <x-js.data-migration-functions />
      <script>
        function migrationApp() {
          return {
            migration: {
              id: {{ $migration->id ?? 'null' }},
              name: @json($migration->name),
              status: @json($migration->status->value),
              progress_percentage: {{ $migration->progress_percentage ?? 0 }},
              success_rate: {{ $migration->success_rate ?? 0 }},
              total_items: {{ $migration->total_items ?? 0 }},
              processed_items: {{ $migration->processed_items ?? 0 }},
              successful_items: {{ $migration->successful_items ?? 0 }},
              failed_items: {{ $migration->failed_items ?? 0 }},
              batches: @json(
                  $migration->batches->map(function ($batch) {
                      $batchArray = $batch->toArray();
                      $batchArray['status'] = $batch->status->value;
                      return $batchArray;
                  }))
            },
            refreshInterval: null,
            resourceFilter: 'all',
            verifyModal: {
              state: 'loading', // 'loading', 'error', 'results'
              errorMessage: '',
              results: null
            },
            verificationStatus: {
              total: 0,
              verified: 0,
              failed: 0
            },

            get filteredBatches() {
              if (this.resourceFilter === 'all') {
                return this.migration.batches;
              }
              return this.migration.batches.filter(batch => batch.resource_type === this.resourceFilter);
            },

            init() {
              // Always start refreshing
              this.startAutoRefresh();

              document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                  if (this.refreshInterval) clearInterval(this.refreshInterval);
                } else if (this.migration.status === DataMigrationStatus.IN_PROGRESS) {
                  this.startAutoRefresh();
                }
              });
            },

            setResourceFilter(type) {
              this.resourceFilter = type;
            },

            startAutoRefresh() {
              // clear out old interval if there
              if (this.refreshInterval) clearInterval(this.refreshInterval);
              // set new interval
              this.refreshInterval = setInterval(() => this.refreshStatus(), 10000);
            },

            async refreshStatus() {
              try {
                const response = await fetch(`{{ route('data-migration.api.status', $migration) }}`);
                const data = await response.json();

                if (data.success) {
                  const oldStatus = this.migration.status;
                  this.migration = data.data;

                  if ((oldStatus === DataMigrationStatus.IN_PROGRESS || oldStatus === DataMigrationStatus
                      .PENDING) &&
                    (data.data.status === DataMigrationStatus.COMPLETED || data.data.status ===
                      DataMigrationStatus.FAILED)) {
                    if (this.refreshInterval) {
                      clearInterval(this.refreshInterval);
                      this.refreshInterval = null;
                    }
                    window.location.reload();
                    return;
                  }

                  if (data.data.status === DataMigrationStatus.COMPLETED || data.data.status ===
                    DataMigrationStatus.FAILED || data.data
                    .status === DataMigrationStatus.CANCELLED) {
                    if (this.refreshInterval) {
                      // run one more time, then kill
                      setTimeout(() => {
                        clearInterval(this.refreshInterval);
                        this.refreshInterval = null;
                      }, 10000);
                    }
                  }
                }
              } catch (error) {
                console.error('Error fetching migration status:', error);
              }
            },

            isVerificationAvailable() {
              return (this.migration.status === DataMigrationStatus.COMPLETED &&
                this.migration.batches &&
                this.migration.batches.some(b => b.status === DataMigrationBatchStatus.COMPLETED));
            },

            getStatusClass(status) {
              return getStatusClass(status);
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
                  this.showToast('Error: ' + data.error, 'error');
                }
              } catch (error) {
                console.error('Error:', error);
                this.showToast('Failed to cancel migration', 'error');
              }
            },

            async retryMigration() {
              if (!confirm(
                  'Are you sure you want to retry failed batches? This includes any batches with partial storage failures.'
                )) return;
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
                  this.showToast('Error: ' + data.error, 'error');
                }
              } catch (error) {
                console.error('Error:', error);
                this.showToast('Failed to retry migration', 'error');
              }
            },

            async restartStuckMigration() {
              if (!confirm(
                  'Are you sure you want to restart this stuck migration? This will attempt to process the pending batches.'
                )) return;
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
                  this.showToast('Migration restarted: ' + data.message, 'success');
                  setTimeout(() => location.reload(), 1500); // Delay reload to show toast
                } else {
                  this.showToast('Error: ' + data.error, 'error');
                }
              } catch (error) {
                console.error('Error:', error);
                this.showToast('Failed to restart migration', 'error');
              }
            },

            async quickVerifyData() {
              this.verifyModal.state = 'loading';
              this.showModal();

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
                  this.verifyModal.results = data.data;
                  this.verifyModal.state = 'results';
                } else {
                  this.verifyModal.errorMessage = 'Error: ' + data.error;
                  this.verifyModal.state = 'error';
                }
              } catch (error) {
                console.error('Error:', error);
                this.verifyModal.errorMessage = 'Network error: Failed to verify data';
                this.verifyModal.state = 'error';
              }
            },

            showModal() {
              const modalElement = document.getElementById('quick-verify-modal');
              const existingModal = bootstrap.Modal.getInstance(modalElement);
              if (existingModal) existingModal.dispose();

              const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
              });
              modal.show();
            },

            // Helper function to show toast notifications - now uses global toast system
            showToast(message, type = 'info') {
              return window.showToast(message, type);
            }
          };
        }

        // Make function available globally for Alpine.js
        window.migrationApp = migrationApp;
      </script>

    </div>
  @endsection
