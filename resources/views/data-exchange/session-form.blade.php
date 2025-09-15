@extends('layouts.app')

@section('title', 'Submit Session Data - DSS Data Exchange')

@section('content')
    <div x-data="sessionFormApp()" x-cloak>
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Submit Session Data</h1>
            <p class="text-muted">Submit session information to the DSS Data Exchange system</p>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Session Information Form</h5>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="loadSampleData()">Load Sample
                        Data</button>
                </div>
                <div class="card-body">
                    <form action="{{ route('data-exchange.submit-session') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="session_id" class="form-label">Session ID <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('session_id') is-invalid @enderror"
                                    id="session_id" name="session_id" value="{{ old('session_id') }}" required>
                                @error('session_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="case_id" class="form-label">Case ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('case_id') is-invalid @enderror"
                                    id="case_id" name="case_id" value="{{ old('case_id') }}" required>
                                @error('case_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="service_type_id" class="form-label">Service Type ID <span
                                        class="text-danger">*</span></label>
                                <select class="form-select @error('service_type_id') is-invalid @enderror"
                                    id="service_type_id" name="service_type_id" required>
                                    <option value="">Select Service Type</option>
                                    @if (isset($serviceTypes) && !empty($serviceTypes))
                                        @foreach ($serviceTypes as $serviceType)
                                            <option value="{{ $serviceType->ServiceTypeId }}"
                                                {{ old('service_type_id') == $serviceType->ServiceTypeId ? 'selected' : '' }}>
                                                {{ $serviceType->ServiceTypeName }}
                                            </option>
                                        @endforeach
                                    @else
                                        <option value="5">Counselling</option>
                                    @endif
                                </select>
                                @error('service_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="session_status" class="form-label">Session Status</label>
                                <select class="form-select @error('session_status') is-invalid @enderror"
                                    id="session_status" name="session_status">
                                    <option value="">Select Status</option>
                                    <option value="Scheduled" {{ old('session_status') == 'Scheduled' ? 'selected' : '' }}>
                                        Scheduled</option>
                                    <option value="Completed" {{ old('session_status') == 'Completed' ? 'selected' : '' }}>
                                        Completed</option>
                                    <option value="Cancelled" {{ old('session_status') == 'Cancelled' ? 'selected' : '' }}>
                                        Cancelled</option>
                                    <option value="No Show" {{ old('session_status') == 'No Show' ? 'selected' : '' }}>No
                                        Show</option>
                                    <option value="In Progress"
                                        {{ old('session_status') == 'In Progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="Rescheduled"
                                        {{ old('session_status') == 'Rescheduled' ? 'selected' : '' }}>Rescheduled</option>
                                </select>
                                @error('session_status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="session_date" class="form-label">Session Date <span
                                        class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('session_date') is-invalid @enderror"
                                    id="session_date" name="session_date" value="{{ old('session_date') }}" required>
                                @error('session_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="duration_minutes" class="form-label">Duration (minutes) <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror"
                                    id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes') }}"
                                    min="1" required>
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
                                <label for="attendees" class="form-label">Attendees</label>
                                <input type="text" class="form-control @error('attendees') is-invalid @enderror"
                                    id="attendees" name="attendees" value="{{ old('attendees') }}"
                                    placeholder="e.g., Client, Counsellor">
                                @error('attendees')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="outcome" class="form-label">Session Outcome</label>
                                <select class="form-select @error('outcome') is-invalid @enderror" id="outcome"
                                    name="outcome">
                                    <option value="">Select Outcome</option>
                                    <option value="Positive" {{ old('outcome') == 'Positive' ? 'selected' : '' }}>Positive
                                    </option>
                                    <option value="Neutral" {{ old('outcome') == 'Neutral' ? 'selected' : '' }}>Neutral
                                    </option>
                                    <option value="Challenging" {{ old('outcome') == 'Challenging' ? 'selected' : '' }}>
                                        Challenging</option>
                                    <option value="Ongoing" {{ old('outcome') == 'Ongoing' ? 'selected' : '' }}>Ongoing
                                    </option>
                                    <option value="Referred" {{ old('outcome') == 'Referred' ? 'selected' : '' }}>Referred
                                    </option>
                                    <option value="Completed" {{ old('outcome') == 'Completed' ? 'selected' : '' }}>
                                        Completed</option>
                                </select>
                                @error('outcome')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="notes" class="form-label">Session Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-outline-secondary me-md-2" @click="clearForm()">Clear
                                Form</button>
                            <button type="submit" class="btn btn-primary">Submit Session Data</button>
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
                        <p><strong>Session Types:</strong></p>
                        <ul>
                            <li>Individual Counselling - One-on-one counselling</li>
                            <li>Group Counselling - Group counselling sessions</li>
                            <li>Assessment - Client assessment sessions</li>
                            <li>Support Group - Group support activities</li>
                            <li>Case Review - Case review meetings</li>
                            <li>Therapy - Therapeutic sessions</li>
                            <li>Crisis Intervention - Emergency interventions</li>
                            <li>Other - Other session types</li>
                        </ul>
                        <p><strong>Session Status:</strong></p>
                        <ul>
                            <li>Scheduled - Session is planned</li>
                            <li>Completed - Session has been completed</li>
                            <li>Cancelled - Session was cancelled</li>
                            <li>No Show - Client did not attend</li>
                            <li>In Progress - Session is currently active</li>
                            <li>Rescheduled - Session was rescheduled</li>
                        </ul>
                    </small>
                </div>
            </div>
        </div>
    </div>

    @if (session('request') || session('response'))
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">SOAP Request/Response Debug</h5>
                    </div>
                    <div class="card-body">
                        @if (session('request'))
                            <h6>Last Request:</h6>
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

                        @if (session('result'))
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

    </div> <!-- End Alpine.js wrapper -->
@endsection

@push('scripts')
    <script>
        function sessionFormApp() {
            return {
                sampleData: @json($sampleData ?? []),

                loadSampleData() {
                    if (this.sampleData && Object.keys(this.sampleData).length > 0) {
                        Object.keys(this.sampleData).forEach(key => {
                            const element = document.getElementById(key);
                            if (element) {
                                if (element.type === 'checkbox') {
                                    element.checked = this.sampleData[key];
                                } else {
                                    element.value = this.sampleData[key];
                                }
                            }
                        });
                    } else {
                        // Load default sample data if not provided by controller
                        const defaultSample = {
                            session_id: 'SESSION001',
                            case_id: 'CASE001',
                            service_type_id: '5',
                            session_status: 'Scheduled',
                            session_date: new Date().toISOString().split('T')[0],
                            duration_minutes: '60',
                            location: 'Office Room 1',
                            attendees: 'Client, Counsellor',
                            outcome: 'Ongoing',
                            notes: 'Initial counselling session'
                        };

                        Object.keys(defaultSample).forEach(key => {
                            const element = document.getElementById(key);
                            if (element) {
                                element.value = defaultSample[key];
                            }
                        });
                    }
                },

                clearForm() {
                    document.querySelector('form').reset();
                }
            };
        }
    </script>
@endpush
