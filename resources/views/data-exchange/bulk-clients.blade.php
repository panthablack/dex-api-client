@extends('layouts.app')

@section('title', 'Bulk Upload Clients - DSS Data Exchange')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Bulk Upload Clients</h1>
            <p class="text-muted">Upload multiple client records from a CSV file to the DSS Data Exchange system</p>
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
                    <h5 class="mb-0">Upload Client CSV File</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('data-exchange.bulk-clients-upload') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('csv_file') is-invalid @enderror"
                                id="csv_file" name="csv_file" accept=".csv,.txt" required>
                            @error('csv_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Maximum file size: 2MB. Only CSV files are accepted.
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('data-exchange.bulk-form') }}"
                                class="btn btn-outline-secondary me-md-2">Back</a>
                            <button type="submit" class="btn btn-primary">Upload & Process Clients</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">CSV Format Requirements</h5>
                </div>
                <div class="card-body">
                    <p><strong>Required CSV Header Row:</strong></p>
                    <div class="bg-light p-2 rounded mb-3" style="font-size: 0.75em; word-break: break-all;">
                        <code>client_id,first_name,last_name,date_of_birth,is_birth_date_estimate,gender,suburb,state,postal_code,country_of_birth,primary_language,indigenous_status,interpreter_required,disability_flag,is_using_pseudonym,consent_to_provide_details,consent_to_be_contacted,client_type</code>
                    </div>

                    <p><strong>Key Field Requirements:</strong></p>
                    <ul class="small">
                        <li><strong>client_id:</strong> Unique client identifier</li>
                        <li><strong>date_of_birth:</strong> Format: YYYY-MM-DD</li>
                        <li><strong>is_birth_date_estimate:</strong> true/false</li>
                        <li><strong>gender:</strong> M, F, X, or 9</li>
                        <li><strong>suburb, state, postal_code:</strong> Required address fields</li>
                        <li><strong>country_of_birth:</strong> e.g., Australia, UK</li>
                        <li><strong>primary_language:</strong> e.g., English, Mandarin</li>
                        <li><strong>indigenous_status:</strong> 1, 2, 3, 4, or 9</li>
                        <li><strong>Boolean fields:</strong> true/false or 1/0</li>
                        <li><strong>consent fields:</strong> Both must be true for valid submission</li>
                    </ul>

                    <div class="alert alert-info mt-3">
                        <small><strong>Note:</strong> All column headers must match exactly. Required fields: client_id,
                            first_name, last_name, date_of_birth, gender, suburb, state, postal_code,
                            consent_to_provide_details, consent_to_be_contacted</small>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Generate Test Data</h5>
                </div>
                <div class="card-body">
                    <p class="small">Generate realistic fake client data for testing:</p>

                    <div class="mb-3">
                        <label for="fake_count" class="form-label">Number of records</label>
                        <input type="number" class="form-control form-control-sm" id="fake_count" value="10"
                            min="1" max="1000">
                    </div>

                    <div class="d-grid">
                        <button type="button" class="btn btn-success btn-sm" onclick="generateFakeClientsCSV()">
                            <i class="fas fa-magic"></i> Generate Fake CSV
                        </button>
                    </div>

                    <div id="fake-generation-status" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function generateFakeClientsCSV() {
            const count = parseInt(document.getElementById('fake_count').value);
            const statusDiv = document.getElementById('fake-generation-status');

            if (count < 1 || count > 1000) {
                statusDiv.innerHTML =
                    '<div class="alert alert-warning alert-sm">Please enter a number between 1 and 1000</div>';
                return;
            }

            statusDiv.innerHTML =
                '<div class="alert alert-info alert-sm"><i class="fas fa-spinner fa-spin"></i> Generating fake data...</div>';

            fetch('{{ route('data-exchange.generate-fake-data') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        type: 'clients',
                        count: count,
                        format: 'csv'
                    })
                })
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    }
                    throw new Error('Network response was not ok');
                })
                .then(blob => {
                    // Download the generated CSV
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download =
                        `fake_clients_${count}_records_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);

                    statusDiv.innerHTML =
                        `<div class="alert alert-success alert-sm"><i class="fas fa-check"></i> Generated ${count} fake client records!</div>`;

                    // Clear status after 5 seconds
                    setTimeout(() => {
                        statusDiv.innerHTML = '';
                    }, 5000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusDiv.innerHTML =
                        '<div class="alert alert-danger alert-sm"><i class="fas fa-exclamation-triangle"></i> Failed to generate fake data</div>';
                });
        }
    </script>
@endpush
