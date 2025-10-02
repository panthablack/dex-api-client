@extends('layouts.app')

@section('title', 'Retrieve Data - DSS Data Exchange')

@section('content')
  <div class="row">
    <div class="col-12">
      <h1 class="mb-4">Retrieve Data</h1>
      <p class="text-muted">Retrieve and download data from the DSS Data Exchange system</p>
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
                <select class="form-select @error('resource_type') is-invalid @enderror" id="resource_type"
                  name="resource_type" required onchange="updateFilters()">
                  <option value="">Select Resource Type</option>
                  @foreach ($resources as $key => $label)
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
                </select>
                @error('resource_type')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="col-md-6">
                <label for="format" class="form-label">Output Format <span class="text-danger">*</span></label>
                <select class="form-select @error('format') is-invalid @enderror" id="format" name="format" required>
                  <option value="">Select Format</option>
                  <option value="json" {{ old('format', 'json') == 'json' ? 'selected' : '' }}>JSON
                  </option>
                  <option value="xml" {{ old('format') == 'xml' ? 'selected' : '' }}>XML</option>
                  <option value="csv" {{ old('format') == 'csv' ? 'selected' : '' }}>CSV</option>
                </select>
                @error('format')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <!-- Required Filters Section -->
            <div id="requiredFiltersSection" style="display: none;">
              <h6 class="mb-3 text-danger">
                <i class="bi bi-asterisk"></i> Required Filters
              </h6>

              <!-- Required Client ID -->
              <div class="row mb-3" id="requiredClientFilters" style="display: none;">
                <div class="col-md-6">
                  <label for="req_client_id" class="form-label">Client ID <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="req_client_id" name="client_id"
                    value="{{ old('client_id') }}" required>
                </div>
              </div>

              <!-- Required Case ID -->
              <div class="row mb-3" id="requiredCaseFilters" style="display: none;">
                <div class="col-md-6">
                  <label for="req_case_id" class="form-label">Case ID <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="req_case_id" name="case_id" value="{{ old('case_id') }}"
                    required>
                </div>
              </div>

              <!-- Required Session ID and Case ID -->
              <div class="row mb-3" id="requiredSessionFilters" style="display: none;">
                <div class="col-md-6">
                  <label for="req_session_id" class="form-label">Session ID <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="req_session_id" name="session_id"
                    value="{{ old('session_id') }}" required>
                </div>
                <div class="col-md-6">
                  <label for="req_session_case_id" class="form-label">Case ID <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="req_session_case_id" name="case_id"
                    value="{{ old('case_id') }}" required>
                </div>
              </div>
            </div>

            <!-- Optional Filters Section -->
            <div id="optionalFiltersSection" style="display: none;">
              <h6 class="mb-3 text-info">
                <i class="bi bi-funnel"></i> Optional Filters
              </h6>

              <!-- Client Optional Filters -->
              <div class="row mb-3" id="clientOptionalFilters" style="display: none;">
                <div class="col-md-6">
                  <label for="first_name" class="form-label">First Name</label>
                  <input type="text" class="form-control" id="first_name" name="first_name"
                    value="{{ old('first_name') }}">
                </div>
                <div class="col-md-6">
                  <label for="last_name" class="form-label">Last Name</label>
                  <input type="text" class="form-control" id="last_name" name="last_name"
                    value="{{ old('last_name') }}">
                </div>
              </div>

              <!-- Date Range Filters (always available) -->
              <div class="row mb-3" id="dateFilters">
                <div class="col-md-6">
                  <label for="date_from" class="form-label">Date From</label>
                  <input type="date" class="form-control" id="date_from" name="date_from"
                    value="{{ old('date_from') }}">
                </div>
                <div class="col-md-6">
                  <label for="date_to" class="form-label">Date To</label>
                  <input type="date" class="form-control" id="date_to" name="date_to"
                    value="{{ old('date_to') }}">
                </div>
              </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
              <button type="button" class="btn btn-outline-secondary me-md-2" onclick="clearForm()">Clear
                Form</button>
              <button type="submit" name="action" value="preview" class="btn btn-info me-md-2"
                onclick="validateForm(event)">Preview Data</button>
              <button type="submit" name="action" value="download" class="btn btn-success"
                onclick="validateForm(event)">Download Data</button>
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
                <label for="report_format" class="form-label">Format</label>
                <select class="form-select" id="report_format" name="format" required>
                  <option value="">Select Format</option>
                  <option value="json" selected>JSON</option>
                  <option value="xml">XML</option>
                  <option value="csv">CSV</option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="report_date_from" class="form-label">Date From <small
                    class="text-muted">(Optional)</small></label>
                <input type="date" class="form-control" id="report_date_from" name="date_from">
              </div>
              <div class="col-md-6">
                <label for="report_date_to" class="form-label">Date To <small
                    class="text-muted">(Optional)</small></label>
                <input type="date" class="form-control" id="report_date_to" name="date_to">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="include_details" name="include_details"
                    value="1">
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
              <button type="submit" name="action" value="preview" class="btn btn-info me-md-2">Preview
                Report</button>
              <button type="submit" name="action" value="download" class="btn btn-success">Download
                Report</button>
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
              @foreach ($resources as $key => $label)
                <li><strong>{{ ucfirst($key) }}:</strong> {{ $label }}</li>
              @endforeach
            </ul>

            <p><strong>Output Formats:</strong></p>
            <ul>
              <li><strong>JSON:</strong> JavaScript Object Notation</li>
              <li><strong>XML:</strong> Extensible Markup Language</li>
              <li><strong>CSV:</strong> Comma Separated Values</li>
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

  @if (
      (session('data') || session('request') || session('response')) &&
          config('features.debugging.show_debug_information'))
    <div class="row mt-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Retrieved Data & Debug Information</h5>
            @if (session('data'))
              <div>
                <span class="badge bg-info">Format: {{ strtoupper(session('format', 'json')) }}</span>
                <button class="btn btn-outline-primary btn-sm ms-2" onclick="downloadSessionData()">
                  Download This Data
                </button>
              </div>
            @endif
          </div>
          <div class="card-body">
            @if (session('data'))
              <h6>Retrieved Data:</h6>
              <div class="xml-container">
                <pre><code id="dataContent">{{ is_string(session('data')) ? session('data') : json_encode(session('data'), JSON_PRETTY_PRINT) }}</code></pre>
              </div>

              {{-- Show resource actions if we have individual resource data --}}
              @if (session('resource_type') &&
                      in_array(session('resource_type'), ['client_by_id', 'case_by_id', 'session_by_id']) &&
                      session('resource_id'))
                <x-resource-actions :resource-type="str_replace('_by_id', '', session('resource_type'))" :resource-data="session('data')" :resource-id="session('resource_id')" />
              @endif
            @endif

            @if (session('request'))
              <h6 class="mt-3">Last Request:</h6>
              <div class="xml-container">
                <pre><code class="language-xml">{{ session('request') }}</code></pre>
              </div>
            @endif

            @if (session('response'))
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

      // Get section elements
      const requiredSection = document.getElementById('requiredFiltersSection');
      const optionalSection = document.getElementById('optionalFiltersSection');

      // Get required filter elements
      const reqClientFilters = document.getElementById('requiredClientFilters');
      const reqCaseFilters = document.getElementById('requiredCaseFilters');
      const reqSessionFilters = document.getElementById('requiredSessionFilters');

      // Get optional filter elements
      const clientOptionalFilters = document.getElementById('clientOptionalFilters');
      const caseOptionalFilters = document.getElementById('caseOptionalFilters');
      const sessionOptionalFilters = document.getElementById('sessionOptionalFilters');

      // Hide all sections initially
      requiredSection.style.display = 'none';
      optionalSection.style.display = 'none';
      reqClientFilters.style.display = 'none';
      reqCaseFilters.style.display = 'none';
      reqSessionFilters.style.display = 'none';
      clientOptionalFilters.style.display = 'none';
      caseOptionalFilters.style.display = 'none';
      sessionOptionalFilters.style.display = 'none';

      // Reset required attributes and disable all fields initially
      const reqClientId = document.getElementById('req_client_id');
      const reqCaseId = document.getElementById('req_case_id');
      const reqSessionId = document.getElementById('req_session_id');
      const reqSessionCaseId = document.getElementById('req_session_case_id');

      if (reqClientId) {
        reqClientId.required = false;
        reqClientId.disabled = true;
      }
      if (reqCaseId) {
        reqCaseId.required = false;
        reqCaseId.disabled = true;
      }
      if (reqSessionId) {
        reqSessionId.required = false;
        reqSessionId.disabled = true;
      }
      if (reqSessionCaseId) {
        reqSessionCaseId.required = false;
        reqSessionCaseId.disabled = true;
      }

      // Show relevant sections based on resource type
      if (resourceType) {
        let hasRequiredFilters = false;

        // Handle resource types with required fields
        if (resourceType === 'client_by_id') {
          requiredSection.style.display = 'block';
          reqClientFilters.style.display = 'block';
          reqClientId.required = true;
          reqClientId.disabled = false;
          hasRequiredFilters = true;
        } else if (resourceType === 'case_by_id') {
          requiredSection.style.display = 'block';
          reqCaseFilters.style.display = 'block';
          reqCaseId.required = true;
          reqCaseId.disabled = false;
          hasRequiredFilters = true;
        } else if (resourceType === 'session_by_id') {
          requiredSection.style.display = 'block';
          reqSessionFilters.style.display = 'block';
          reqSessionId.required = true;
          reqSessionId.disabled = false;
          reqSessionCaseId.required = true;
          reqSessionCaseId.disabled = false;
          hasRequiredFilters = true;
        } else if (resourceType === 'sessions') {
          // Sessions require Case ID
          requiredSection.style.display = 'block';
          reqCaseFilters.style.display = 'block';
          reqCaseId.required = true;
          reqCaseId.disabled = false;
          hasRequiredFilters = true;
        }

        // Always show optional section for general resource types or after required fields
        optionalSection.style.display = 'block';

        // Show relevant optional filters
        if (resourceType === \App\ Enums\ ResourceType::CLIENT || resourceType === 'client_by_id') {
          clientOptionalFilters.style.display = 'block';
        } else if (resourceType === \App\ Enums\ ResourceType::CASE || resourceType === 'case_by_id' ||
          resourceType ===
          'full_cases' ||
          resourceType === 'full_sessions') {
          caseOptionalFilters.style.display = 'block';
        } else if (resourceType === \App\ Enums\ ResourceType::SESSION || resourceType === 'session_by_id') {
          sessionOptionalFilters.style.display = 'block';
        }
      }
    }

    function clearForm() {
      document.getElementById('retrieveForm').reset();
      updateFilters();
    }

    function getResourceSchema() {
      const resourceType = document.getElementById('resource_type').value;
      const url = `{{ route('data-exchange.resource-schema') }}?resource_type=${resourceType}`;
      window.open(url, '_blank');
    }

    function getAvailableFunctions() {
      window.open('{{ route('data-exchange.available-functions') }}', '_blank');
    }

    function downloadSessionData() {
      const data = @json(session('data'));
      const format = '{{ session('format', 'json') }}';
      const resourceType = '{{ old('resource_type', 'data') }}';

      if (!data) {
        showNotification('No data available to download', 'warning');
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

    // Notification system to replace alerts
    function showNotification(message, type = 'info', duration = 5000) {
      // Remove any existing notifications
      const existingNotification = document.getElementById('notification-toast');
      if (existingNotification) {
        existingNotification.remove();
      }

      // Create notification element
      const notification = document.createElement('div');
      notification.id = 'notification-toast';
      notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
      notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
      notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

      // Add to page
      document.body.appendChild(notification);

      // Auto-dismiss after duration
      setTimeout(() => {
        if (notification && notification.parentNode) {
          notification.remove();
        }
      }, duration);
    }

    // Validate form before submission
    function validateForm(event) {
      const resourceType = document.getElementById('resource_type').value;

      console.log('Form validation - Resource type:', resourceType);

      // Check required fields based on resource type
      if (resourceType === 'client_by_id') {
        const clientId = document.getElementById('req_client_id');
        console.log('Client ID field:', clientId);
        console.log('Client ID value:', clientId ? clientId.value : 'field not found');
        if (!clientId || !clientId.value.trim()) {
          showAlert('Client ID is required for client lookup', 'warning');
          event.preventDefault();
          return false;
        }
      } else if (resourceType === 'case_by_id') {
        const caseId = document.getElementById('req_case_id');
        console.log('Case ID field:', caseId);
        console.log('Case ID value:', caseId ? caseId.value : 'field not found');
        console.log('Case ID field display:', caseId ? getComputedStyle(caseId.parentElement.parentElement)
          .display : 'N/A');
        console.log('Case ID field disabled:', caseId ? caseId.disabled : 'N/A');
        if (!caseId || !caseId.value.trim()) {
          showAlert('Case ID is required for case lookup', 'warning');
          event.preventDefault();
          return false;
        }
      } else if (resourceType === 'session_by_id') {
        const sessionId = document.getElementById('req_session_id');
        const sessionCaseId = document.getElementById('req_session_case_id');
        console.log('Session ID field:', sessionId);
        console.log('Session ID value:', sessionId ? sessionId.value : 'field not found');
        console.log('Session Case ID field:', sessionCaseId);
        console.log('Session Case ID value:', sessionCaseId ? sessionCaseId.value : 'field not found');
        if (!sessionId || !sessionId.value.trim()) {
          showAlert('Session ID is required for session lookup', 'warning');
          event.preventDefault();
          return false;
        }
        if (!sessionCaseId || !sessionCaseId.value.trim()) {
          showAlert('Case ID is required for session lookup', 'warning');
          event.preventDefault();
          return false;
        }
      }

      // Log all form data before submission
      const form = document.getElementById('retrieveForm');
      const formData = new FormData(form);
      console.log('All form data being submitted:');
      for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
      }

      return true;
    }

    // Initialize filters on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateFilters();
    });
  </script>
@endpush
