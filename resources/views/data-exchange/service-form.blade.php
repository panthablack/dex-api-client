@extends('layouts.app')

@section('title', 'Submit Service Data - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Submit Service Data</h1>
        <p class="text-muted">Submit service delivery information to the DSS Data Exchange system</p>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Service Information Form</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadSampleData()">Load Sample Data</button>
            </div>
            <div class="card-body">
                <form action="{{ route('data-exchange.submit-service') }}" method="POST">
                    @csrf
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="service_id" class="form-label">Service ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('service_id') is-invalid @enderror" 
                                   id="service_id" name="service_id" value="{{ old('service_id') }}" required>
                            @error('service_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="client_id" class="form-label">Client ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('client_id') is-invalid @enderror" 
                                   id="client_id" name="client_id" value="{{ old('client_id') }}" required>
                            @error('client_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="case_id" class="form-label">Case ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('case_id') is-invalid @enderror" 
                                   id="case_id" name="case_id" value="{{ old('case_id') }}" required>
                            @error('case_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="service_type" class="form-label">Service Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('service_type') is-invalid @enderror" 
                                    id="service_type" name="service_type" required>
                                <option value="">Select Service Type</option>
                                <option value="Counselling" {{ old('service_type') == 'Counselling' ? 'selected' : '' }}>Counselling</option>
                                <option value="Assessment" {{ old('service_type') == 'Assessment' ? 'selected' : '' }}>Assessment</option>
                                <option value="Support Group" {{ old('service_type') == 'Support Group' ? 'selected' : '' }}>Support Group</option>
                                <option value="Case Management" {{ old('service_type') == 'Case Management' ? 'selected' : '' }}>Case Management</option>
                                <option value="Crisis Intervention" {{ old('service_type') == 'Crisis Intervention' ? 'selected' : '' }}>Crisis Intervention</option>
                                <option value="Therapy" {{ old('service_type') == 'Therapy' ? 'selected' : '' }}>Therapy</option>
                                <option value="Other" {{ old('service_type') == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('service_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="service_date" class="form-label">Service Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('service_date') is-invalid @enderror" 
                                   id="service_date" name="service_date" value="{{ old('service_date') }}" required>
                            @error('service_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="duration_minutes" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror" 
                                   id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes') }}" min="1" required>
                            @error('duration_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                   id="location" name="location" value="{{ old('location') }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="service_status" class="form-label">Service Status</label>
                            <select class="form-select @error('service_status') is-invalid @enderror" 
                                    id="service_status" name="service_status">
                                <option value="">Select Status</option>
                                <option value="Scheduled" {{ old('service_status') == 'Scheduled' ? 'selected' : '' }}>Scheduled</option>
                                <option value="Completed" {{ old('service_status') == 'Completed' ? 'selected' : '' }}>Completed</option>
                                <option value="Cancelled" {{ old('service_status') == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                                <option value="No Show" {{ old('service_status') == 'No Show' ? 'selected' : '' }}>No Show</option>
                                <option value="In Progress" {{ old('service_status') == 'In Progress' ? 'selected' : '' }}>In Progress</option>
                            </select>
                            @error('service_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="service_notes" class="form-label">Service Notes</label>
                            <textarea class="form-control @error('service_notes') is-invalid @enderror" 
                                      id="service_notes" name="service_notes" rows="3">{{ old('service_notes') }}</textarea>
                            @error('service_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="clearForm()">Clear Form</button>
                        <button type="submit" class="btn btn-primary">Submit Service Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Form Help</h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <p><strong>Required fields</strong> are marked with <span class="text-danger">*</span></p>
                    <p><strong>Service Types:</strong></p>
                    <ul>
                        <li>Counselling - Individual or group counselling sessions</li>
                        <li>Assessment - Client assessment and evaluation</li>
                        <li>Support Group - Group support activities</li>
                        <li>Case Management - Case management services</li>
                        <li>Crisis Intervention - Emergency support services</li>
                        <li>Therapy - Therapeutic interventions</li>
                        <li>Other - Other service types</li>
                    </ul>
                    <p><strong>Service Status:</strong></p>
                    <ul>
                        <li>Scheduled - Service is planned</li>
                        <li>Completed - Service has been delivered</li>
                        <li>Cancelled - Service was cancelled</li>
                        <li>No Show - Client did not attend</li>
                        <li>In Progress - Service is currently active</li>
                    </ul>
                </small>
            </div>
        </div>
    </div>
</div>

@if(session('request') || session('response'))
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">SOAP Request/Response Debug</h5>
            </div>
            <div class="card-body">
                @if(session('request'))
                <h6>Last Request:</h6>
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
                
                @if(session('result'))
                <h6 class="mt-3">Parsed Result:</h6>
                <div class="xml-container">
                    <pre>{{ json_encode(session('result'), JSON_PRETTY_PRINT) }}</pre>
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
const sampleData = @json($sampleData ?? []);

function loadSampleData() {
    if (sampleData && Object.keys(sampleData).length > 0) {
        Object.keys(sampleData).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = sampleData[key];
                } else {
                    element.value = sampleData[key];
                }
            }
        });
    } else {
        // Load default sample data if not provided by controller
        const defaultSample = {
            service_id: 'SRV001',
            client_id: 'CLI001',
            case_id: 'CSE001',
            service_type: 'Counselling',
            service_date: new Date().toISOString().split('T')[0],
            duration_minutes: '60',
            location: 'Office Room 1',
            service_status: 'Scheduled',
            service_notes: 'Initial counselling session scheduled.'
        };
        
        Object.keys(defaultSample).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                element.value = defaultSample[key];
            }
        });
    }
}

function clearForm() {
    document.querySelector('form').reset();
}
</script>
@endpush