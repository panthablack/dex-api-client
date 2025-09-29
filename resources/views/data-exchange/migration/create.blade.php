@extends('layouts.app')

@section('title', 'Create Data Migration')

@section('content')

    @php
        $hasMigratedCases = \App\Models\MigratedCase::exists();
    @endphp
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('data-migration.index') }}" class="text-decoration-none">
                    Data Migration
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                Create Migration
            </li>
        </ol>
    </nav>

    <div class="mb-4">
        <h1 class="h2 text-primary">Create New Data Migration</h1>
        <p class="text-muted">Set up a new migration to fetch and store data from the DSS SOAP API.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Please correct the following errors:</strong>
            <ul class="mt-2 mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('data-migration.store') }}">
                @csrf

                <!-- Migration Name -->
                <div class="mb-4">
                    <label for="name" class="form-label">Migration Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control"
                        placeholder="e.g., Clients, Cases Migration - {{ now()->format('Y-m-d H:i:s') }}" required>
                    <div class="form-text">Give your migration a descriptive name to help identify it later.</div>
                </div>

                <!-- Resource Type -->
                <div class="mb-4">
                    <label class="form-label">Resource to Migrate</label>
                    <div class="d-flex flex-column gap-3">
                        <div class="form-check">
                            <input type="radio" name="resource_type" value="CLIENTS" id="CLIENTS"
                                class="form-check-input" {{ old('resource_type', 'CLIENTS') == 'CLIENTS' ? 'checked' : '' }}
                                required>
                            <label for="CLIENTS" class="form-check-label d-flex align-items-center">
                                <span>Clients</span>
                                <span class="badge bg-primary ms-2">
                                    Client (All)
                                </span>
                            </label>
                        </div>

                        <div class="form-check">
                            <input type="radio" name="resource_type" value="CASES" id="CASES"
                                class="form-check-input" {{ old('resource_type') == 'CASES' ? 'checked' : '' }} required>
                            <label for="CASES" class="form-check-label d-flex align-items-center">
                                <span>Cases</span>
                                <span class="badge bg-success ms-2">
                                    Cases (Open)
                                </span>
                            </label>
                        </div>

                        <div class="form-check">
                            <input type="radio" name="resource_type" value="CLOSED_CASES" id="CLOSED_CASES"
                                class="form-check-input" {{ old('resource_type') == 'CLOSED_CASES' ? 'checked' : '' }}
                                required>
                            <label for="CLOSED_CASES" class="form-check-label d-flex align-items-center">
                                <span>Closed Cases</span>
                                <span class="badge bg-success ms-2">
                                    Closed Cases
                                </span>
                            </label>
                        </div>

                        @if ($hasMigratedCases)
                            <div class="form-check">
                                <input type="radio" name="resource_type" value="SESSIONS" id="SESSIONS"
                                    class="form-check-input" {{ old('resource_type') == 'SESSIONS' ? 'checked' : '' }}
                                    required>
                                <label for="SESSIONS" class="form-check-label d-flex align-items-center">
                                    <span>Sessions</span>
                                    <span class="badge bg-info ms-2">
                                        Sessions (From Cases)
                                    </span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="resource_type" value="CASE_CLIENTS" id="CASE_CLIENTS"
                                    class="form-check-input" {{ old('resource_type') == 'CASE_CLIENTS' ? 'checked' : '' }}
                                    required>
                                <label for="CASE_CLIENTS" class="form-check-label d-flex align-items-center">
                                    <span>Case Clients</span>
                                    <span class="badge bg-info ms-2">
                                        Clients associated with migrated Cases
                                    </span>
                                </label>
                            </div>
                        @else
                            <div class="form-check">
                                <input type="radio" name="resource_type" value="sessions" id="sessions"
                                    class="form-check-input" disabled
                                    title="Sessions require migrated cases to be available">
                                <label for="sessions" class="form-check-label d-flex align-items-center text-muted">
                                    <span>Sessions</span>
                                    <span class="badge bg-secondary ms-2">
                                        Session records from DSS (requires migrated cases)
                                    </span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="resource_type" value="case_clients" id="case_clients"
                                    class="form-check-input" disabled
                                    title="Sessions require migrated cases to be available">
                                <label for="case_clients" class="form-check-label d-flex align-items-center text-muted">
                                    <span>Case Clients</span>
                                    <span class="badge bg-secondary ms-2">
                                        Clients associated with migrated Cases (requires migrated cases)
                                    </span>
                                </label>
                            </div>
                        @endif
                    </div>
                    <div class="form-text">
                        Resources can only be migrated one at a time.
                        @if (!$hasMigratedCases)
                            Sessions are only available when cases have been migrated first.
                        @endif
                    </div>
                </div>

                <!-- Date Range Filters -->
                <div class="mb-4">
                    <label class="form-label">Date Range (Optional)</label>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" name="date_from" id="date_from" value="{{ old('date_from') }}"
                                class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" name="date_to" id="date_to" value="{{ old('date_to') }}"
                                class="form-control">
                        </div>
                    </div>
                    <div class="form-text">
                        Specify a date range to limit which records are migrated. Leave empty to migrate all available data.
                    </div>
                </div>

                <!-- Batch Size -->
                <div class="mb-4">
                    <label for="batch_size" class="form-label">Batch Size</label>
                    <select name="batch_size" id="batch_size" class="form-select">
                        <option value="25" {{ old('batch_size') == '25' ? 'selected' : '' }}>25 items per batch
                        </option>
                        <option value="50" {{ old('batch_size') == '50' ? 'selected' : '' }}>50 items per batch
                        </option>
                        <option value="100" {{ old('batch_size', '100') == '100' ? 'selected' : '' }}>100 items per
                            batch (recommended)</option>
                    </select>
                    <div class="form-text">
                        Number of items to process in each batch. Smaller batches are more reliable but slower.
                        The maximum allowed by the DSS API is 100 items.
                    </div>
                </div>

                <!-- Migration Preview -->
                <div class="bg-light rounded p-3 mb-4">
                    <h6 class="fw-medium mb-2">Migration Preview</h6>
                    <div id="migration-preview" class="text-muted">
                        <p>Select resource types to see migration preview...</p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="{{ route('data-migration.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Create Migration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Information Panel -->
    <div class="alert alert-info mt-4">
        <div class="d-flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-info"></i>
            </div>
            <div class="ms-3">
                <h6 class="alert-heading">How Data Migration Works</h6>
                <ul class="mb-0">
                    <li><strong>Batch Processing:</strong> Data is fetched and stored in configurable batches to ensure
                        reliability</li>
                    <li><strong>Asynchronous:</strong> Migrations run in the background using Laravel's queue system</li>
                    <li><strong>Progress Tracking:</strong> Real-time progress updates show completion status for each batch
                    </li>
                    <li><strong>Error Recovery:</strong> Failed batches can be retried without affecting successful ones
                    </li>
                    <li><strong>Data Verification:</strong> Compare migrated data against the original API data for accuracy
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resourceRadios = document.querySelectorAll('input[name="resource_type"]');
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');
            const batchSizeSelect = document.getElementById('batch_size');
            const previewDiv = document.getElementById('migration-preview');

            function getSelectedResource() {
                const selectedRadio = document.querySelector('input[name="resource_type"]:checked');
                return selectedRadio ? selectedRadio.value : '';
            }

            function updatePreview() {
                const selectedResource = getSelectedResource();
                const dateFrom = dateFromInput.value;
                const dateTo = dateToInput.value;
                const batchSize = batchSizeSelect.value;

                if (!selectedResource) {
                    previewDiv.innerHTML = '<p>Select a resource type to see migration preview...</p>';
                    return;
                }

                let preview = '<div>';
                const resourceName = selectedResource.charAt(0).toUpperCase() + selectedResource.slice(1);
                preview += `<p><strong>Resource:</strong> ${resourceName}</p>`;

                if (dateFrom || dateTo) {
                    let dateRange = 'Date range: ';
                    if (dateFrom && dateTo) {
                        dateRange += `${dateFrom} to ${dateTo}`;
                    } else if (dateFrom) {
                        dateRange += `from ${dateFrom}`;
                    } else if (dateTo) {
                        dateRange += `up to ${dateTo}`;
                    }
                    preview += `<p><strong>${dateRange}</strong></p>`;
                } else {
                    preview += '<p><strong>Date range:</strong> All available data</p>';
                }

                preview += `<p><strong>Batch size:</strong> ${batchSize} items per batch</p>`;
                preview +=
                    `<p><strong>Processing:</strong> The ${resourceName.toLowerCase()} data will be processed in batches of ${batchSize} items</p>`;

                preview += '</div>';
                previewDiv.innerHTML = preview;
            }

            // Update preview when inputs change
            resourceRadios.forEach(radio => {
                radio.addEventListener('change', updatePreview);
                radio.addEventListener('change', updateDefaultName);
            });
            dateFromInput.addEventListener('change', updatePreview);
            dateToInput.addEventListener('change', updatePreview);
            batchSizeSelect.addEventListener('change', updatePreview);

            // Set default name if empty
            const nameInput = document.getElementById('name');

            function generateDefaultName() {
                const selectedResource = getSelectedResource();
                const now = new Date();
                const dateTime = now.toISOString().replace('T', ' ').split('.')[0];

                let name = 'Data Migration';
                if (selectedResource) {
                    const resourceName = selectedResource.charAt(0).toUpperCase() + selectedResource.slice(1);
                    name = `${resourceName} Migration`;
                }
                name += ` - ${dateTime}`;

                return name;
            }

            function updateDefaultName() {
                if (!nameInput.value || nameInput.value.includes('Data Migration - ') || nameInput.value.includes(
                        ' Migration - ')) {
                    nameInput.value = generateDefaultName();
                }
            }

            if (!nameInput.value) {
                nameInput.value = generateDefaultName();
            }

            // Initial preview update
            updatePreview();
        });
    </script>
@endsection
