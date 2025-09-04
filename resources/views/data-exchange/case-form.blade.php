@extends('layouts.app')

@section('title', 'Submit Case Data - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Submit Case Data</h1>
        <p class="text-muted">Submit case information to the DSS Data Exchange system</p>
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
                <h5 class="mb-0">Case Information Form</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadSampleData()">Load Sample Data</button>
            </div>
            <div class="card-body">
                <form action="{{ route('data-exchange.submit-case') }}" method="POST">
                    @csrf
                    
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
                            <label for="case_type" class="form-label">Case Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('case_type') is-invalid @enderror" 
                                    id="case_type" name="case_type" required>
                                <option value="">Select Case Type</option>
                                <option value="Individual Support" {{ old('case_type') == 'Individual Support' ? 'selected' : '' }}>Individual Support</option>
                                <option value="Family Support" {{ old('case_type') == 'Family Support' ? 'selected' : '' }}>Family Support</option>
                                <option value="Crisis Intervention" {{ old('case_type') == 'Crisis Intervention' ? 'selected' : '' }}>Crisis Intervention</option>
                                <option value="Assessment" {{ old('case_type') == 'Assessment' ? 'selected' : '' }}>Assessment</option>
                                <option value="Long-term Care" {{ old('case_type') == 'Long-term Care' ? 'selected' : '' }}>Long-term Care</option>
                                <option value="Other" {{ old('case_type') == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('case_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="case_status" class="form-label">Case Status <span class="text-danger">*</span></label>
                            <select class="form-select @error('case_status') is-invalid @enderror" 
                                    id="case_status" name="case_status" required>
                                <option value="">Select Status</option>
                                <option value="Active" {{ old('case_status') == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Pending" {{ old('case_status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                <option value="On Hold" {{ old('case_status') == 'On Hold' ? 'selected' : '' }}>On Hold</option>
                                <option value="Closed" {{ old('case_status') == 'Closed' ? 'selected' : '' }}>Closed</option>
                                <option value="Transferred" {{ old('case_status') == 'Transferred' ? 'selected' : '' }}>Transferred</option>
                            </select>
                            @error('case_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                   id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                   id="end_date" name="end_date" value="{{ old('end_date') }}">
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="case_worker" class="form-label">Case Worker</label>
                            <input type="text" class="form-control @error('case_worker') is-invalid @enderror" 
                                   id="case_worker" name="case_worker" value="{{ old('case_worker') }}">
                            @error('case_worker')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority Level</label>
                            <select class="form-select @error('priority') is-invalid @enderror" 
                                    id="priority" name="priority">
                                <option value="">Select Priority</option>
                                <option value="Low" {{ old('priority') == 'Low' ? 'selected' : '' }}>Low</option>
                                <option value="Medium" {{ old('priority') == 'Medium' ? 'selected' : '' }}>Medium</option>
                                <option value="High" {{ old('priority') == 'High' ? 'selected' : '' }}>High</option>
                                <option value="Urgent" {{ old('priority') == 'Urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="description" class="form-label">Case Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="notes" class="form-label">Case Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="clearForm()">Clear Form</button>
                        <button type="submit" class="btn btn-primary">Submit Case Data</button>
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
                    <p><strong>Case Types:</strong></p>
                    <ul>
                        <li>Individual Support - Support for individual clients</li>
                        <li>Family Support - Support for families and children</li>
                        <li>Crisis Intervention - Emergency support cases</li>
                        <li>Assessment - Initial assessment cases</li>
                        <li>Long-term Care - Ongoing care cases</li>
                        <li>Other - Other case types</li>
                    </ul>
                    <p><strong>Case Status:</strong></p>
                    <ul>
                        <li>Active - Case is currently active</li>
                        <li>Pending - Case is awaiting action</li>
                        <li>On Hold - Case is temporarily paused</li>
                        <li>Closed - Case has been completed</li>
                        <li>Transferred - Case has been transferred</li>
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
            case_id: 'CASE001',
            client_id: 'CLI001',
            case_type: 'Individual Support',
            case_status: 'Active',
            start_date: new Date().toISOString().split('T')[0],
            end_date: new Date(Date.now() + 6*30*24*60*60*1000).toISOString().split('T')[0], // 6 months from now
            case_worker: 'John Smith',
            priority: 'Medium',
            description: 'Individual support case for client counselling services',
            notes: 'Initial case setup for ongoing support services'
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