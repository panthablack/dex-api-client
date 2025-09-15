@extends('layouts.app')

@section('title', 'Clients - DSS Data Exchange')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mb-2">Clients</h1>
                    <p class="text-muted">View and manage client records from the DSS Data Exchange system</p>
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

                    <a href="{{ route('data-exchange.client-form') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Client
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
                <form method="GET" action="{{ route('data-exchange.clients.index') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                value="{{ request('first_name') }}" placeholder="Search first name">
                        </div>
                        <div class="col-md-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                value="{{ request('last_name') }}" placeholder="Search last name">
                        </div>
                        <div class="col-md-2">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">All Genders</option>
                                <option value="M" {{ request('gender') === 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ request('gender') === 'F' ? 'selected' : '' }}>Female</option>
                                <option value="X" {{ request('gender') === 'X' ? 'selected' : '' }}>Non-binary</option>
                                <option value="9" {{ request('gender') === '9' ? 'selected' : '' }}>Not stated</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="state" class="form-label">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">All States</option>
                                <option value="NSW" {{ request('state') === 'NSW' ? 'selected' : '' }}>NSW</option>
                                <option value="VIC" {{ request('state') === 'VIC' ? 'selected' : '' }}>VIC</option>
                                <option value="QLD" {{ request('state') === 'QLD' ? 'selected' : '' }}>QLD</option>
                                <option value="WA" {{ request('state') === 'WA' ? 'selected' : '' }}>WA</option>
                                <option value="SA" {{ request('state') === 'SA' ? 'selected' : '' }}>SA</option>
                                <option value="TAS" {{ request('state') === 'TAS' ? 'selected' : '' }}>TAS</option>
                                <option value="ACT" {{ request('ACT') === 'ACT' ? 'selected' : '' }}>ACT</option>
                                <option value="NT" {{ request('state') === 'NT' ? 'selected' : '' }}>NT</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="{{ route('data-exchange.clients.index') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clients Table -->
    <x-resource-table title="Client Records" resource-type="client" :data="$clients ?? []" :columns="[
        ['key' => 'ClientId', 'label' => 'Client ID'],
        ['key' => 'GivenName', 'label' => 'First Name'],
        ['key' => 'FamilyName', 'label' => 'Last Name'],
        ['key' => 'BirthDate', 'label' => 'Date of Birth', 'format' => 'date'],
        ['key' => 'GenderCode', 'label' => 'Gender'],
        ['key' => 'ResidentialAddress.State', 'label' => 'State'],
        ['key' => 'ResidentialAddress.Postcode', 'label' => 'Postcode'],
        ['key' => 'ResidentialAddress.Suburb', 'label' => 'Suburb'],
    ]" :loading="$loading ?? false"
        empty-message="No clients found. Try adjusting your filters or add a new client." />

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
            const firstNameInput = document.getElementById('first_name');
            if (firstNameInput && firstNameInput.value) filters.append('first_name', firstNameInput.value);

            const lastNameInput = document.getElementById('last_name');
            if (lastNameInput && lastNameInput.value) filters.append('last_name', lastNameInput.value);

            const genderSelect = document.getElementById('gender');
            if (genderSelect && genderSelect.value) filters.append('gender', genderSelect.value);

            const stateSelect = document.getElementById('state');
            if (stateSelect && stateSelect.value) filters.append('state', stateSelect.value);

            // Add format parameter
            filters.append('format', format);

            // Create download URL
            const exportUrl = `{{ route('data-exchange.api.export-clients') }}?${filters.toString()}`;

            // Trigger download
            window.location.href = exportUrl;
        }
    </script>
@endpush
