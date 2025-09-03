@extends('layouts.app')

@section('title', 'Bulk Upload - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Bulk Upload</h1>
        <p class="text-muted">Upload multiple client records from a CSV file</p>
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
                <h5 class="mb-0">Upload CSV File</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('data-exchange.bulk-upload') }}" method="POST" enctype="multipart/form-data">
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
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Upload & Process</button>
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
                <div class="bg-light p-2 rounded mb-3" style="font-size: 0.8em;">
                    <code>client_id,first_name,last_name,date_of_birth,gender,indigenous_status,country_of_birth,postal_code,primary_language,interpreter_required,disability_flag,client_type</code>
                </div>
                
                <p><strong>Field Descriptions:</strong></p>
                <ul class="small">
                    <li><strong>client_id:</strong> Unique client identifier</li>
                    <li><strong>first_name:</strong> Client's first name</li>
                    <li><strong>last_name:</strong> Client's last name</li>
                    <li><strong>date_of_birth:</strong> Format: YYYY-MM-DD</li>
                    <li><strong>gender:</strong> M, F, X, or 9</li>
                    <li><strong>indigenous_status:</strong> Indigenous status code</li>
                    <li><strong>country_of_birth:</strong> Country of birth code</li>
                    <li><strong>postal_code:</strong> Postal/ZIP code</li>
                    <li><strong>primary_language:</strong> Primary language code</li>
                    <li><strong>interpreter_required:</strong> true or false</li>
                    <li><strong>disability_flag:</strong> true or false</li>
                    <li><strong>client_type:</strong> Client type code</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small><strong>Note:</strong> The first row must contain the header names exactly as shown above. Empty fields are allowed.</small>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Sample CSV</h5>
            </div>
            <div class="card-body">
                <p class="small">Download a sample CSV file to see the expected format:</p>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="downloadSampleCSV()">
                    <i class="fas fa-download"></i> Download Sample
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function downloadSampleCSV() {
    const csvContent = `client_id,first_name,last_name,date_of_birth,gender,indigenous_status,country_of_birth,postal_code,primary_language,interpreter_required,disability_flag,client_type
CLI001,John,Smith,1990-05-15,M,1,AU,3000,EN,false,false,IND
CLI002,Jane,Doe,1985-03-22,F,2,AU,3001,EN,true,false,FAM
CLI003,Bob,Johnson,1975-12-01,M,1,NZ,3002,EN,false,true,IND`;

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample_bulk_upload.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>
@endpush