@extends('layouts.app')

@section('title', 'Retrieve Data - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Retrieve Data</h1>
        <p class="text-muted">Retrieve and download data from the DSS Data Exchange system</p>
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

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Data Retrieval Form</h5>
            </div>
            <div class="card-body">
                <form id="retrieveForm" action="{{ route('data-exchange.retrieve-data') }}" method="POST">
                    @csrf
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="resource_type" class="form-label">Resource Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('resource_type') is-invalid @enderror" 
                                    id="resource_type" name="resource_type" required onchange="updateFilters()">
                                <option value="">Select Resource Type</option>
                                @foreach($resources as $key => $label)
                                    <option value="{{ $key }}" {{ old('resource_type') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                                <option value="client_by_id" {{ old('resource_type') == 'client_by_id' ? 'selected' : '' }}>
                                    Get Client by ID
                                </option>
                                <option value="case_by_id" {{ old('resource_type') == 'case_by_id' ? 'selected' : '' }}>
                                    Get Case by ID
                                </option>
                                <option value="session_by_id" {{ old('resource_type') == 'session_by_id' ? 'selected' : '' }}>
                                    Get Session by ID
                                </option>
                                <option value="sessions_for_case" {{ old('resource_type') == 'sessions_for_case' ? 'selected' : '' }}>
                                    Get Sessions for Case
                                </option>
                            </select>
                            @error('resource_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="format" class="form-label">Output Format <span class="text-danger">*</span></label>
                            <select class="form-select @error('format') is-invalid @enderror" 
                                    id="format" name="format" required>
                                <option value="">Select Format</option>
                                <option value="json" {{ old('format') == 'json' ? 'selected' : '' }}>JSON</option>
                                <option value="xml" {{ old('format') == 'xml' ? 'selected' : '' }}>XML</option>
                                <option value="csv" {{ old('format') == 'csv' ? 'selected' : '' }}>CSV</option>
                            </select>
                            @error('format')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Filters Section -->
                    <div id="filtersSection">
                        <h6 class="mb-3">Filters (Optional)</h6>
                        
                        <div class="row mb-3" id="clientFilters">
                            <div class="col-md-4">
                                <label for="client_id" class="form-label">Client ID</label>
                                <input type="text" class="form-control" 
                                       id="client_id" name="client_id" value="{{ old('client_id') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" 
                                       id="first_name" name="first_name" value="{{ old('first_name') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" 
                                       id="last_name" name="last_name" value="{{ old('last_name') }}">
                            </div>
                        </div>

                        <div class="row mb-3" id="caseFilters" style="display: none;">
                            <div class="col-md-4">
                                <label for="case_id" class="form-label">Case ID</label>
                                <input type="text" class="form-control" 
                                       id="case_id" name="case_id" value="{{ old('case_id') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="case_status" class="form-label">Case Status</label>
                                <input type="text" class="form-control" 
                                       id="case_status" name="case_status" value="{{ old('case_status') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="case_type" class="form-label">Case Type</label>
                                <input type="text" class="form-control" 
                                       id="case_type" name="case_type" value="{{ old('case_type') }}">
                            </div>
                        </div>

                        <div class="row mb-3" id="sessionFilters" style="display: none;">
                            <div class="col-md-4">
                                <label for="session_id" class="form-label">Session ID</label>
                                <input type="text" class="form-control" 
                                       id="session_id" name="session_id" value="{{ old('session_id') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="session_type" class="form-label">Session Type</label>
                                <input type="text" class="form-control" 
                                       id="session_type" name="session_type" value="{{ old('session_type') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="session_status" class="form-label">Session Status</label>
                                <input type="text" class="form-control" 
                                       id="session_status" name="session_status" value="{{ old('session_status') }}">
                            </div>
                        </div>

                        <div class="row mb-3" id="serviceFilters" style="display: none;">
                            <div class="col-md-4">
                                <label for="service_type" class="form-label">Service Type</label>
                                <input type="text" class="form-control" 
                                       id="service_type" name="service_type" value="{{ old('service_type') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="service_start_date" class="form-label">Start Date From</label>
                                <input type="date" class="form-control" 
                                       id="service_start_date" name="service_start_date" value="{{ old('service_start_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="service_end_date" class="form-label">End Date To</label>
                                <input type="date" class="form-control" 
                                       id="service_end_date" name="service_end_date" value="{{ old('service_end_date') }}">
                            </div>
                        </div>

                        <div class="row mb-3" id="dateFilters">
                            <div class="col-md-6">
                                <label for="date_from" class="form-label">Date From <small class="text-muted">(Optional)</small></label>
                                <input type="date" class="form-control" 
                                       id="date_from" name="date_from" value="{{ old('date_from') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="date_to" class="form-label">Date To <small class="text-muted">(Optional)</small></label>
                                <input type="date" class="form-control" 
                                       id="date_to" name="date_to" value="{{ old('date_to') }}">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="clearForm()">Clear Form</button>
                        <button type="submit" name="action" value="preview" class="btn btn-info me-md-2">Preview Data</button>
                        <button type="submit" name="action" value="download" class="btn btn-success">Download Data</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reports Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Generate Reports</h5>
            </div>
            <div class="card-body">
                <form id="reportForm" action="{{ route('data-exchange.generate-report') }}" method="POST">
                    @csrf
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <option value="">Select Report Type</option>
                                @foreach($reports as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="report_format" class="form-label">Format</label>
                            <select class="form-select" id="report_format" name="format" required>
                                <option value="">Select Format</option>
                                <option value="json">JSON</option>
                                <option value="xml">XML</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="report_date_from" class="form-label">Date From <small class="text-muted">(Optional)</small></label>
                            <input type="date" class="form-control" id="report_date_from" name="date_from">
                        </div>
                        <div class="col-md-6">
                            <label for="report_date_to" class="form-label">Date To <small class="text-muted">(Optional)</small></label>
                            <input type="date" class="form-control" id="report_date_to" name="date_to">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_details" name="include_details" value="1">
                                <label class="form-check-label" for="include_details">
                                    Include Detailed Information
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="group_by" class="form-label">Group By</label>
                            <select class="form-select" id="group_by" name="group_by">
                                <option value="">No Grouping</option>
                                <option value="date">Date</option>
                                <option value="service_type">Service Type</option>
                                <option value="location">Location</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="action" value="preview" class="btn btn-info me-md-2">Preview Report</button>
                        <button type="submit" name="action" value="download" class="btn btn-success">Download Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Available Resources</h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <p><strong>Resource Types:</strong></p>
                    <ul>
                        @foreach($resources as $key => $label)
                            <li><strong>{{ ucfirst($key) }}:</strong> {{ $label }}</li>
                        @endforeach
                    </ul>
                    
                    <p><strong>Output Formats:</strong></p>
                    <ul>
                        <li><strong>JSON:</strong> JavaScript Object Notation</li>
                        <li><strong>XML:</strong> Extensible Markup Language</li>
                        <li><strong>CSV:</strong> Comma Separated Values</li>
                    </ul>
                    
                    <p><strong>Available Reports:</strong></p>
                    <ul>
                        @foreach($reports as $key => $label)
                            <li>{{ $label }}</li>
                        @endforeach
                    </ul>
                </small>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="getResourceSchema()">
                        Get Resource Schema
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="getAvailableFunctions()">
                        View Available Methods
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('data') || session('request') || session('response'))
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Retrieved Data & Debug Information</h5>
                @if(session('data'))
                    <div>
                        <span class="badge bg-info">Format: {{ strtoupper(session('format', 'json')) }}</span>
                        <button class="btn btn-outline-primary btn-sm ms-2" onclick="downloadSessionData()">
                            Download This Data
                        </button>
                    </div>
                @endif
            </div>
            <div class="card-body">
                @if(session('data'))
                <h6>Retrieved Data:</h6>
                <div class="xml-container">
                    <pre><code id="dataContent">{{ is_string(session('data')) ? session('data') : json_encode(session('data'), JSON_PRETTY_PRINT) }}</code></pre>
                </div>
                @endif
                
                @if(session('request'))
                <h6 class="mt-3">Last Request:</h6>
                <div class="xml-container">
                    <pre><code class="language-xml">{{ session('request') }}</code></pre>
                </div>
                @endif
                
                @if(session('response'))
                <h6 class="mt-3">Last Response:</h6>
                <div class="xml-container">
                    <pre><code class="language-xml">{{ session('response') }}</code></pre>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function updateFilters() {
    const resourceType = document.getElementById('resource_type').value;
    const clientFilters = document.getElementById('clientFilters');
    const caseFilters = document.getElementById('caseFilters');
    const sessionFilters = document.getElementById('sessionFilters');
    const serviceFilters = document.getElementById('serviceFilters');
    
    // Hide all filter sections
    clientFilters.style.display = 'none';
    caseFilters.style.display = 'none';
    sessionFilters.style.display = 'none';
    serviceFilters.style.display = 'none';
    
    // Reset required fields
    document.getElementById('client_id').required = false;
    document.getElementById('case_id').required = false;
    document.getElementById('session_id').required = false;
    
    // Show relevant filters based on resource type
    if (resourceType === 'clients' || resourceType === 'client_by_id') {
        clientFilters.style.display = 'block';
        if (resourceType === 'client_by_id') {
            document.getElementById('client_id').required = true;
        }
    } else if (resourceType === 'cases' || resourceType === 'case_by_id' || resourceType === 'sessions_for_case') {
        caseFilters.style.display = 'block';
        if (resourceType === 'case_by_id' || resourceType === 'sessions_for_case') {
            document.getElementById('case_id').required = true;
        }
    } else if (resourceType === 'sessions' || resourceType === 'session_by_id') {
        sessionFilters.style.display = 'block';
        if (resourceType === 'session_by_id') {
            document.getElementById('session_id').required = true;
        }
    }
}

function clearForm() {
    document.getElementById('retrieveForm').reset();
    updateFilters();
}

function getResourceSchema() {
    const resourceType = document.getElementById('resource_type').value;
    if (!resourceType) {
        alert('Please select a resource type first');
        return;
    }
    
    fetch(`{{ route('data-exchange.resource-schema') }}?resource_type=${resourceType}`)
        .then(response => response.json())
        .then(data => {
            alert('Schema: ' + JSON.stringify(data, null, 2));
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

function getAvailableFunctions() {
    window.open('{{ route('data-exchange.available-functions') }}', '_blank');
}

function downloadSessionData() {
    const data = @json(session('data'));
    const format = '{{ session('format', 'json') }}';
    const resourceType = '{{ old('resource_type', 'data') }}';
    
    if (!data) {
        alert('No data available to download');
        return;
    }
    
    // Create temporary form for download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('data-exchange.retrieve-data') }}';
    form.style.display = 'none';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    form.appendChild(csrfInput);
    
    // Add parameters
    const actionInput = document.createElement('input');
    actionInput.name = 'action';
    actionInput.value = 'download';
    form.appendChild(actionInput);
    
    const resourceInput = document.createElement('input');
    resourceInput.name = 'resource_type';
    resourceInput.value = resourceType;
    form.appendChild(resourceInput);
    
    const formatInput = document.createElement('input');
    formatInput.name = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Initialize filters on page load
document.addEventListener('DOMContentLoaded', function() {
    updateFilters();
});
</script>
@endpush