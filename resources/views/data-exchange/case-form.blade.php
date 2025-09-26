@extends('layouts.app')

@section('title', 'Submit Case Data - DSS Data Exchange')

@section('content')
    <div x-data="caseFormApp()" x-cloak>
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Submit Case Data</h1>
                <p class="text-muted">Submit case information to the DSS Data Exchange system</p>
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
                        <h5 class="mb-0">Case Information Form</h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="loadSampleData()">Load
                            Sample
                            Data</button>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('data-exchange.submit-case') }}" method="POST">
                            @csrf

                            <!-- Core Case Fields -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="case_id" class="form-label">Case ID <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('case_id') is-invalid @enderror"
                                        id="case_id" name="case_id" value="{{ old('case_id') }}" required>
                                    @error('case_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="outlet_activity_id" class="form-label">Outlet Activity <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select @error('outlet_activity_id') is-invalid @enderror"
                                        id="outlet_activity_id" name="outlet_activity_id" required>
                                        <option value="">Select Outlet Activity</option>
                                        @if (isset($outletActivities) && !empty($outletActivities))
                                            @foreach ($outletActivities as $activity)
                                                <option value="{{ $activity->OutletActivityId }}"
                                                    {{ old('outlet_activity_id') == $activity->OutletActivityId ? 'selected' : '' }}>
                                                    {{ $activity->ActivityName }} - {{ $activity->OutletName }}
                                                </option>
                                            @endforeach
                                        @else
                                            <option value="61936" selected>
                                                Community Mental
                                                Health - A Better Life - testing outlet 1</option>
                                        @endif
                                    </select>
                                    @error('outlet_activity_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Client Information -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="client_id" class="form-label">Client ID</label>
                                    <input type="text" class="form-control @error('client_id') is-invalid @enderror"
                                        id="client_id" name="client_id" value="{{ old('client_id') }}"
                                        placeholder="Leave empty for unidentified clients">
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Optional: Specify a client ID or leave empty to use unidentified
                                        clients</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="total_unidentified_clients" class="form-label">Total Unidentified
                                        Clients</label>
                                    <input type="number"
                                        class="form-control @error('total_unidentified_clients') is-invalid @enderror"
                                        id="total_unidentified_clients" name="total_unidentified_clients"
                                        value="{{ old('total_unidentified_clients', 1) }}" min="0" max="100"
                                        placeholder="1">
                                    @error('total_unidentified_clients')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Number of unidentified clients (used when Client ID is empty)
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="referral_source_code" class="form-label">Referral Source <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select @error('referral_source_code') is-invalid @enderror"
                                        id="referral_source_code" name="referral_source_code" required>
                                        <option value="">Select Referral Source</option>
                                        @if (isset($referralSources) && !empty($referralSources))
                                            @foreach ($referralSources as $source)
                                                <option value="{{ $source->Code }}"
                                                    {{ old('referral_source_code') == $source->Code ? 'selected' : '' }}>
                                                    {{ $source->Description }}
                                                </option>
                                            @endforeach
                                        @else
                                            <option value="COMMUNITY"
                                                {{ old('referral_source_code') == 'COMMUNITY' ? 'selected' : '' }}>
                                                Community
                                                services agency</option>
                                            <option value="SELF"
                                                {{ old('referral_source_code') == 'SELF' ? 'selected' : '' }}>Self</option>
                                            <option value="FAMILY"
                                                {{ old('referral_source_code') == 'FAMILY' ? 'selected' : '' }}>Family
                                            </option>
                                            <option value="GP"
                                                {{ old('referral_source_code') == 'GP' ? 'selected' : '' }}>
                                                General Medical Practitioner</option>
                                            <option value="HealthAgency"
                                                {{ old('referral_source_code') == 'HealthAgency' ? 'selected' : '' }}>
                                                Health
                                                Agency</option>
                                        @endif
                                    </select>
                                    @error('referral_source_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Reasons for Assistance -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Reasons for Assistance <span
                                            class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="assistance_physical"
                                                    name="reasons_for_assistance[]" value="PHYSICAL">
                                                <label class="form-check-label" for="assistance_physical">Physical</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="assistance_emotional"
                                                    name="reasons_for_assistance[]" value="EMOTIONAL">
                                                <label class="form-check-label"
                                                    for="assistance_emotional">Emotional</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="assistance_financial"
                                                    name="reasons_for_assistance[]" value="FINANCIAL">
                                                <label class="form-check-label"
                                                    for="assistance_financial">Financial</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="assistance_housing"
                                                    name="reasons_for_assistance[]" value="HOUSING">
                                                <label class="form-check-label" for="assistance_housing">Housing</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="assistance_legal"
                                                    name="reasons_for_assistance[]" value="LEGAL">
                                                <label class="form-check-label" for="assistance_legal">Legal</label>
                                            </div>
                                        </div>
                                    </div>
                                    @error('reasons_for_assistance')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Optional Fields -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="client_attendance_profile_code" class="form-label">Client Attendance
                                        Profile</label>
                                    <select
                                        class="form-select @error('client_attendance_profile_code') is-invalid @enderror"
                                        id="client_attendance_profile_code" name="client_attendance_profile_code">
                                        <option value="">Select Profile</option>
                                        @if (isset($attendanceProfiles) && !empty($attendanceProfiles))
                                            @foreach ($attendanceProfiles as $profile)
                                                <option value="{{ $profile->Code }}"
                                                    {{ old('client_attendance_profile_code') == $profile->Code ? 'selected' : '' }}>
                                                    {{ $profile->Description }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('client_attendance_profile_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                                        id="end_date" name="end_date" value="{{ old('end_date') }}"
                                        min="{{ date('Y-m-d', strtotime('-60 days')) }}"
                                        max="{{ date('Y-m-d', strtotime('-1 day')) }}">
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">End date must be within the last 60 days and before today</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="exit_reason_code" class="form-label">Exit Reason</label>
                                    <select class="form-select @error('exit_reason_code') is-invalid @enderror"
                                        id="exit_reason_code" name="exit_reason_code">
                                        <option value="">Select Exit Reason</option>
                                        @if (isset($exitReasons) && !empty($exitReasons))
                                            @foreach ($exitReasons as $reason)
                                                <option value="{{ $reason->Code }}"
                                                    {{ old('exit_reason_code') == $reason->Code ? 'selected' : '' }}>
                                                    {{ $reason->Description }}
                                                </option>
                                            @endforeach
                                        @else
                                            <option value="MOVED"
                                                {{ old('exit_reason_code') == 'MOVED' ? 'selected' : '' }}>
                                                Moved</option>
                                            <option value="COMPLETED"
                                                {{ old('exit_reason_code') == 'COMPLETED' ? 'selected' : '' }}>Completed
                                            </option>
                                            <option value="VOLUNTARY"
                                                {{ old('exit_reason_code') == 'VOLUNTARY' ? 'selected' : '' }}>Voluntary
                                            </option>
                                            <option value="OTHER"
                                                {{ old('exit_reason_code') == 'OTHER' ? 'selected' : '' }}>
                                                Other</option>
                                        @endif
                                    </select>
                                    @error('exit_reason_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ag_business_type_code" class="form-label">AG Business Type Code</label>
                                    <input type="text"
                                        class="form-control @error('ag_business_type_code') is-invalid @enderror"
                                        id="ag_business_type_code" name="ag_business_type_code"
                                        value="{{ old('ag_business_type_code') }}" maxlength="10">
                                    @error('ag_business_type_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">e.g., 0111 for specific business types</div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-outline-secondary me-md-2"
                                    @click="clearForm()">Clear
                                    Form</button>
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
                            <p><strong>DSS Case Structure:</strong></p>
                            <ul>
                                <li><strong>Case ID:</strong> Unique identifier for the case</li>
                                <li><strong>Outlet Activity:</strong> Required DSS outlet activity</li>
                                <li><strong>Client ID:</strong> ID of the primary client</li>
                                <li><strong>Referral Source:</strong> How the client was referred</li>
                                <li><strong>Reasons for Assistance:</strong> At least one reason required</li>
                                <li><strong>End Date:</strong> Must be in the past (before today)</li>
                            </ul>
                            <p><strong>Form Features:</strong></p>
                            <ul>
                                <li>Outlet activities populated from DSS API</li>
                                <li>Referral sources use official DSS reference data</li>
                                <li>Form validates all required DSS fields</li>
                                <li>Sample data can be loaded for testing</li>
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
        function caseFormApp() {
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

                        // Handle reasons for assistance array - it might be nested in clients array
                        let reasonsForAssistance = this.sampleData.reasons_for_assistance;
                        if (!reasonsForAssistance && this.sampleData.clients && this.sampleData.clients[0]) {
                            reasonsForAssistance = this.sampleData.clients[0].reasons_for_assistance;
                        }

                        if (reasonsForAssistance && Array.isArray(reasonsForAssistance)) {
                            // Clear all checkboxes first
                            document.querySelectorAll('input[name="reasons_for_assistance[]"]').forEach(cb => cb.checked =
                                false);

                            // Check the appropriate boxes
                            reasonsForAssistance.forEach(reason => {
                                // Handle both string format (like "LEGAL") and object format (like {assistance_needed_code: "LEGAL"})
                                const reasonCode = typeof reason === 'string' ? reason : reason
                                    .assistance_needed_code;
                                const checkbox = document.querySelector(
                                    `input[name="reasons_for_assistance[]"][value="${reasonCode}"]`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });
                        }
                    } else {
                        // Helper function to generate resource ID with 10 random digits
                        function generateResourceId(resourceName) {
                            let randomDigits = '';
                            for (let i = 0; i < 10; i++) {
                                randomDigits += Math.floor(Math.random() * 10);
                            }
                            return resourceName.toUpperCase() + '_' + randomDigits;
                        }

                        // Load default sample data if not provided by controller
                        const defaultSample = {
                            case_id: generateResourceId('CASE'),
                            client_id: generateResourceId('CLIENT'),
                            outlet_activity_id: '61936',
                            referral_source_code: 'COMMUNITY',
                            total_unidentified_clients: '1', // Default to 1 unidentified client
                            client_attendance_profile_code: 'FAMILY',
                            end_date: '',
                            exit_reason_code: '',
                            ag_business_type_code: ''
                        };

                        Object.keys(defaultSample).forEach(key => {
                            const element = document.getElementById(key);
                            if (element) {
                                element.value = defaultSample[key];
                            }
                        });

                        // Check first reason for assistance
                        const physicalCheckbox = document.querySelector(
                            'input[name="reasons_for_assistance[]"][value="PHYSICAL"]');
                        if (physicalCheckbox) {
                            physicalCheckbox.checked = true;
                        }
                    }
                },

                clearForm() {
                    document.querySelector('form').reset();
                }
            };
        }
    </script>
@endpush
