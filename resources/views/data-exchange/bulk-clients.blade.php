@extends('layouts.app')

@section('title', 'Bulk Upload Clients - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Bulk Upload Clients</h1>
        <p class="text-muted">Upload multiple client records from a CSV file to the DSS Data Exchange system</p>
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
                <h5 class="mb-0">Upload Client CSV File</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('data-exchange.bulk-clients-upload') }}" method="POST" enctype="multipart/form-data">
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
                        <a href="{{ route('data-exchange.bulk-form') }}" class="btn btn-outline-secondary me-md-2">Back</a>
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
                    <small><strong>Note:</strong> All column headers must match exactly. Required fields: client_id, first_name, last_name, date_of_birth, gender, suburb, state, postal_code, consent_to_provide_details, consent_to_be_contacted</small>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Sample CSV</h5>
            </div>
            <div class="card-body">
                <p class="small">Download a sample CSV file to see the expected format:</p>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="downloadSampleClientsCSV()">
                    <i class="fas fa-download"></i> Download Sample
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function downloadSampleClientsCSV() {
    const csvContent = `client_id,first_name,last_name,date_of_birth,is_birth_date_estimate,gender,suburb,state,postal_code,country_of_birth,primary_language,indigenous_status,interpreter_required,disability_flag,is_using_pseudonym,consent_to_provide_details,consent_to_be_contacted,client_type
CLI001,John,Smith,1990-05-15,false,M,Sydney,NSW,2000,Australia,English,4,false,false,false,true,true,Individual
CLI002,Jane,Doe,1985-03-22,false,F,Melbourne,VIC,3000,Australia,English,4,true,false,false,true,true,Individual
CLI003,Bob,Johnson,1975-01-01,true,M,Brisbane,QLD,4000,Australia,English,1,false,true,false,true,true,Individual`;

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample_clients_bulk_upload.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>
@endpush