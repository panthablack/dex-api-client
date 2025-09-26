@extends('layouts.app')

@section('title', 'Submit Client Data - DSS Data Exchange')

@section('content')
    <div x-data="clientFormApp()" x-cloak>
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Submit Client Data</h1>
                <p class="text-muted">Submit individual client information to the DSS Data Exchange system</p>
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
                        <h5 class="mb-0">Client Information Form</h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="loadSampleData()">Load
                            Sample
                            Data</button>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('data-exchange.submit-client') }}" method="POST">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="client_id" class="form-label">Client ID <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('client_id') is-invalid @enderror"
                                        id="client_id" name="client_id" value="{{ old('client_id') }}" required>
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                        id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                                    @error('first_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                        id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                                    @error('last_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth <span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                        id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                                    @error('date_of_birth')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="is_birth_date_estimate"
                                            name="is_birth_date_estimate" value="1"
                                            {{ old('is_birth_date_estimate') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_birth_date_estimate">
                                            Birth date is an estimate
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select @error('gender') is-invalid @enderror" id="gender"
                                        name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="M" {{ old('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                        <option value="F" {{ old('gender') == 'F' ? 'selected' : '' }}>Female</option>
                                        <option value="X" {{ old('gender') == 'X' ? 'selected' : '' }}>Non-binary
                                        </option>
                                        <option value="9" {{ old('gender') == '9' ? 'selected' : '' }}>Not stated
                                        </option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="indigenous_status" class="form-label">Indigenous Status</label>
                                    <select class="form-select @error('indigenous_status') is-invalid @enderror"
                                        id="indigenous_status" name="indigenous_status">
                                        <option value="">Select Status</option>
                                        @foreach ($atsiOptions as $option)
                                            <option value="{{ $option->Code }}"
                                                {{ old('indigenous_status') == $option->Code ? 'selected' : '' }}>
                                                {{ $option->Description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('indigenous_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="country_of_birth" class="form-label">Country of Birth</label>
                                    <select class="form-select @error('country_of_birth') is-invalid @enderror"
                                        id="country_of_birth" name="country_of_birth">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->Code }}"
                                                {{ old('country_of_birth') == $country->Code ? 'selected' : '' }}>
                                                {{ $country->Description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('country_of_birth')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="suburb" class="form-label">Suburb <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('suburb') is-invalid @enderror"
                                        id="suburb" name="suburb" value="{{ old('suburb') }}" required>
                                    @error('suburb')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="state" class="form-label">State <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select @error('state') is-invalid @enderror" id="state"
                                        name="state" required>
                                        <option value="">Select State</option>
                                        <option value="NSW" {{ old('state') == 'NSW' ? 'selected' : '' }}>NSW</option>
                                        <option value="VIC" {{ old('state') == 'VIC' ? 'selected' : '' }}>VIC</option>
                                        <option value="QLD" {{ old('state') == 'QLD' ? 'selected' : '' }}>QLD</option>
                                        <option value="WA" {{ old('state') == 'WA' ? 'selected' : '' }}>WA</option>
                                        <option value="SA" {{ old('state') == 'SA' ? 'selected' : '' }}>SA</option>
                                        <option value="TAS" {{ old('state') == 'TAS' ? 'selected' : '' }}>TAS</option>
                                        <option value="ACT" {{ old('state') == 'ACT' ? 'selected' : '' }}>ACT</option>
                                        <option value="NT" {{ old('state') == 'NT' ? 'selected' : '' }}>NT</option>
                                    </select>
                                    @error('state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="postal_code" class="form-label">Postal Code <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('postal_code') is-invalid @enderror"
                                        id="postal_code" name="postal_code" value="{{ old('postal_code') }}" required>
                                    @error('postal_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="primary_language" class="form-label">Primary Language</label>
                                    <select class="form-select @error('primary_language') is-invalid @enderror"
                                        id="primary_language" name="primary_language">
                                        <option value="">Select Language</option>
                                        @foreach ($languages as $language)
                                            <option value="{{ $language->Code }}"
                                                {{ old('primary_language') == $language->Code ? 'selected' : '' }}>
                                                {{ $language->Description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('primary_language')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="interpreter_required"
                                            name="interpreter_required" value="1"
                                            {{ old('interpreter_required') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="interpreter_required">
                                            Interpreter Required
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="disability_flag"
                                            name="disability_flag" value="1"
                                            {{ old('disability_flag') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="disability_flag">
                                            Has Disability
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="consent_to_provide_details"
                                            name="consent_to_provide_details" value="1"
                                            {{ old('consent_to_provide_details', '1') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="consent_to_provide_details">
                                            Consent to Provide Details <span class="text-danger">*</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="consent_to_be_contacted"
                                            name="consent_to_be_contacted" value="1"
                                            {{ old('consent_to_be_contacted', '1') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="consent_to_be_contacted">
                                            Consent to be Contacted <span class="text-danger">*</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_using_pseudonym"
                                            name="is_using_pseudonym" value="1"
                                            {{ old('is_using_pseudonym') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_using_pseudonym">
                                            Using Pseudonym
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-outline-secondary me-md-2"
                                    @click="clearForm()">Clear
                                    Form</button>
                                <button type="submit" class="btn btn-primary">Submit Client Data</button>
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
                            <p><strong>Gender Values:</strong></p>
                            <ul>
                                <li>M - Male</li>
                                <li>F - Female</li>
                                <li>X - Non-binary</li>
                                <li>9 - Not stated</li>
                            </ul>
                            <p><strong>Indigenous Status:</strong></p>
                            <ul>
                                <li>Y - Yes</li>
                                <li>N - No</li>
                                <li>U - Unknown</li>
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
        function clientFormApp() {
            return {
                sampleData: @json($sampleData),

                loadSampleData() {
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
                },

                clearForm() {
                    document.querySelector('form').reset();
                }
            };
        }
    </script>
@endpush
