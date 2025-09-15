@extends('layouts.app')

@section('title', 'Sessions - DSS Data Exchange')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mb-2">Sessions</h1>
                    <p class="text-muted">View and manage session records from the DSS Data Exchange system</p>
                </div>
                <div class="d-flex gap-2">
                    <!-- Export Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                            <li>
                                <a class="dropdown-item" href="#" onclick="exportData('csv')">
                                    <i class="fas fa-file-csv"></i> Export as CSV
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="exportData('json')">
                                    <i class="fas fa-file-code"></i> Export as JSON
                                </a>
                            </li>
                        </ul>
                    </div>

                    <a href="{{ route('data-exchange.session-form') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Session
                    </a>
                </div>
            </div>
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

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter"></i> Filters
                <button class="btn btn-sm btn-outline-secondary ms-2" type="button" data-bs-toggle="collapse"
                    data-bs-target="#filtersCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div class="collapse show" id="filtersCollapse">
            <div class="card-body">
                <form method="GET" action="{{ route('data-exchange.sessions.index') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="case_id" class="form-label">Case ID</label>
                            <input type="text" class="form-control" id="case_id" name="case_id"
                                value="{{ request('case_id') }}" placeholder="Search case ID">
                        </div>
                        <div class="col-md-3">
                            <label for="session_status" class="form-label">Session Status</label>
                            <select class="form-select" id="session_status" name="session_status">
                                <option value="">All Statuses</option>
                                <option value="Scheduled" {{ request('session_status') === 'Scheduled' ? 'selected' : '' }}>
                                    Scheduled</option>
                                <option value="Completed" {{ request('session_status') === 'Completed' ? 'selected' : '' }}>
                                    Completed</option>
                                <option value="Cancelled" {{ request('session_status') === 'Cancelled' ? 'selected' : '' }}>
                                    Cancelled</option>
                                <option value="No Show" {{ request('session_status') === 'No Show' ? 'selected' : '' }}>No
                                    Show</option>
                                <option value="In Progress"
                                    {{ request('session_status') === 'In Progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="Rescheduled"
                                    {{ request('session_status') === 'Rescheduled' ? 'selected' : '' }}>Rescheduled
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="service_type_id" class="form-label">Service Type</label>
                            <select class="form-select" id="service_type_id" name="service_type_id">
                                <option value="">All Types</option>
                                @if (isset($serviceTypes))
                                    @foreach ($serviceTypes as $serviceType)
                                        <option value="{{ $serviceType->ServiceTypeId }}"
                                            {{ request('service_type_id') == $serviceType->ServiceTypeId ? 'selected' : '' }}>
                                            {{ substr($serviceType->ServiceTypeName, 0, 20) }}{{ strlen($serviceType->ServiceTypeName) > 20 ? '...' : '' }}
                                        </option>
                                    @endforeach
                                @else
                                    <option value="5" {{ request('service_type_id') == '5' ? 'selected' : '' }}>
                                        Counselling</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_range" class="form-label">Date Range</label>
                            <select class="form-select" id="date_range" name="date_range">
                                <option value="">All Dates</option>
                                <option value="7" {{ request('date_range') === '7' ? 'selected' : '' }}>Last 7 days
                                </option>
                                <option value="30" {{ request('date_range') === '30' ? 'selected' : '' }}>Last 30 days
                                </option>
                                <option value="90" {{ request('date_range') === '90' ? 'selected' : '' }}>Last 90 days
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="{{ route('data-exchange.sessions.index') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sessions Table -->
    <x-resource-table title="Session Records" resource-type="session" :data="$sessions ?? []" :columns="[
        ['key' => 'SessionDetails.SessionId', 'label' => 'Session ID'],
        ['key' => 'CaseId', 'label' => 'Case ID'],
        ['key' => 'SessionDetails.ServiceTypeId', 'label' => 'Service Type ID'],
        ['key' => 'SessionDetails.SessionDate', 'label' => 'Session Date', 'format' => 'date'],
        ['key' => 'SessionDetails.Time', 'label' => 'Duration/Time'],
        ['key' => 'SessionDetails.TopicCode', 'label' => 'Topic'],
        ['key' => 'CreatedDateTime', 'label' => 'Created Date', 'format' => 'date'],
        ['key' => 'SessionDetails.TotalNumberOfUnidentifiedClients', 'label' => 'Unidentified Clients'],
    ]" :loading="$loading ?? false"
        empty-message="No sessions found. Try adjusting your filters or add a new session." />

    <!-- Pagination -->
    <x-pagination :pagination="$pagination ?? null" />

    @if (isset($debugInfo['view_debug']) && $debugInfo['view_debug'] && config('features.debugging.show_debug_information'))
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Debug Information</h5>
            </div>
            <div class="card-body">
                <pre>{{ json_encode($debugInfo ?? [], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    @endif

    @if (isset($errorToast))
        <x-toast-container>
            <x-error-toast :title="$errorToast['title']" :message="$errorToast['message']" :details="$errorToast['details'] ?? null" />
        </x-toast-container>
    @endif

@endsection

@push('scripts')
    <script>
        // Auto-submit form when filters change (optional)
        document.querySelectorAll('#filtersCollapse select').forEach(select => {
            select.addEventListener('change', function() {
                // Uncomment to auto-submit on filter change
                // this.form.submit();
            });
        });

        // Export function
        function exportData(format) {
            // Get current filters from the form
            const filters = new URLSearchParams();

            // Add current filter values
            const caseIdInput = document.getElementById('case_id');
            if (caseIdInput && caseIdInput.value) filters.append('case_id', caseIdInput.value);

            const sessionStatusSelect = document.getElementById('session_status');
            if (sessionStatusSelect && sessionStatusSelect.value) filters.append('session_status', sessionStatusSelect.value);

            const serviceTypeSelect = document.getElementById('service_type_id');
            if (serviceTypeSelect && serviceTypeSelect.value) filters.append('service_type_id', serviceTypeSelect.value);

            const dateRangeSelect = document.getElementById('date_range');
            if (dateRangeSelect && dateRangeSelect.value) filters.append('date_range', dateRangeSelect.value);

            // Add format parameter
            filters.append('format', format);

            // Create download URL
            const exportUrl = `{{ route('data-exchange.api.export-sessions') }}?${filters.toString()}`;

            // Trigger download
            window.location.href = exportUrl;
        }
    </script>
@endpush
