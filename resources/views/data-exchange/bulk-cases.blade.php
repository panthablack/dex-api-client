@extends('layouts.app')

@section('title', 'Bulk Upload Cases - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Bulk Upload Cases</h1>
        <p class="text-muted">Upload multiple case records from a CSV file to the DSS Data Exchange system</p>
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
                <h5 class="mb-0">Upload Case CSV File</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('data-exchange.bulk-cases-upload') }}" method="POST" enctype="multipart/form-data">
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
                        <button type="submit" class="btn btn-success">Upload & Process Cases</button>
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
                    <code>case_id,client_id,case_type,case_status,start_date,end_date,case_worker,priority,description,notes</code>
                </div>
                
                <p><strong>Field Descriptions:</strong></p>
                <ul class="small">
                    <li><strong>case_id:</strong> Unique case identifier</li>
                    <li><strong>client_id:</strong> Associated client ID</li>
                    <li><strong>case_type:</strong> Individual Support, Family Support, Crisis Intervention, Assessment, Long-term Care, Other</li>
                    <li><strong>case_status:</strong> Active, Pending, On Hold, Closed, Transferred</li>
                    <li><strong>start_date:</strong> Format: YYYY-MM-DD</li>
                    <li><strong>end_date:</strong> Format: YYYY-MM-DD (optional)</li>
                    <li><strong>case_worker:</strong> Case worker name (optional)</li>
                    <li><strong>priority:</strong> Low, Medium, High, Urgent (optional)</li>
                    <li><strong>description:</strong> Case description (optional)</li>
                    <li><strong>notes:</strong> Case notes (optional)</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small><strong>Note:</strong> Required fields: case_id, client_id, case_type, case_status, start_date. End date must be after or equal to start date if provided.</small>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Sample CSV</h5>
            </div>
            <div class="card-body">
                <p class="small">Download a sample CSV file to see the expected format:</p>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="downloadSampleCasesCSV()">
                    <i class="fas fa-download"></i> Download Sample
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function downloadSampleCasesCSV() {
    const csvContent = `case_id,client_id,case_type,case_status,start_date,end_date,case_worker,priority,description,notes
CASE001,CLI001,Individual Support,Active,2024-01-15,2024-07-15,John Smith,Medium,Individual support case for counselling,Client requires ongoing support
CASE002,CLI002,Family Support,Active,2024-02-01,,Jane Wilson,High,Family crisis intervention case,Urgent family support needed
CASE003,CLI003,Assessment,Closed,2024-01-01,2024-01-31,Bob Davis,Low,Initial client assessment,Assessment completed successfully`;

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample_cases_bulk_upload.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>
@endpush