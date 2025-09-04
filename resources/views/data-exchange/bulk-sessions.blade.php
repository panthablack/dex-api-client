@extends('layouts.app')

@section('title', 'Bulk Upload Sessions - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Bulk Upload Sessions</h1>
        <p class="text-muted">Upload multiple session records from a CSV file to the DSS Data Exchange system</p>
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
                <h5 class="mb-0">Upload Session CSV File</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('data-exchange.bulk-sessions-upload') }}" method="POST" enctype="multipart/form-data">
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
                        <button type="submit" class="btn btn-info">Upload & Process Sessions</button>
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
                    <code>session_id,case_id,session_type,session_date,duration_minutes,location,session_status,attendees,outcome,notes</code>
                </div>
                
                <p><strong>Field Descriptions:</strong></p>
                <ul class="small">
                    <li><strong>session_id:</strong> Unique session identifier</li>
                    <li><strong>case_id:</strong> Associated case ID</li>
                    <li><strong>session_type:</strong> Individual Counselling, Group Counselling, Assessment, Support Group, Case Review, Therapy, Crisis Intervention, Other</li>
                    <li><strong>session_date:</strong> Format: YYYY-MM-DD</li>
                    <li><strong>duration_minutes:</strong> Session duration in minutes</li>
                    <li><strong>location:</strong> Session location (optional)</li>
                    <li><strong>session_status:</strong> Scheduled, Completed, Cancelled, No Show, In Progress, Rescheduled (optional)</li>
                    <li><strong>attendees:</strong> Who attended (optional)</li>
                    <li><strong>outcome:</strong> Positive, Neutral, Challenging, Ongoing, Referred, Completed (optional)</li>
                    <li><strong>notes:</strong> Session notes (optional)</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small><strong>Note:</strong> Required fields: session_id, case_id, session_type, session_date, duration_minutes. Duration must be greater than 0.</small>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Generate Test Data</h5>
            </div>
            <div class="card-body">
                <p class="small">Generate realistic fake session data for testing:</p>
                
                <div class="mb-3">
                    <label for="fake_count" class="form-label">Number of records</label>
                    <input type="number" class="form-control form-control-sm" id="fake_count" value="10" min="1" max="1000">
                </div>
                
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success btn-sm" onclick="generateFakeSessionsCSV()">
                        <i class="fas fa-magic"></i> Generate Fake CSV
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="downloadSampleSessionsCSV()">
                        <i class="fas fa-download"></i> Download Sample
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
function downloadSampleSessionsCSV() {
    const csvContent = `session_id,case_id,session_type,session_date,duration_minutes,location,session_status,attendees,outcome,notes
SES001,CASE001,Individual Counselling,2024-01-15,60,Office Room 1,Completed,Client and Counsellor,Positive,Good progress made in session
SES002,CASE001,Individual Counselling,2024-01-22,60,Office Room 1,Scheduled,Client and Counsellor,Ongoing,Follow-up session planned
SES003,CASE002,Group Counselling,2024-02-01,90,Conference Room,Completed,Multiple clients and facilitator,Positive,Group dynamic working well`;

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample_sessions_bulk_upload.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function generateFakeSessionsCSV() {
    const count = parseInt(document.getElementById('fake_count').value);
    const statusDiv = document.getElementById('fake-generation-status');
    
    if (count < 1 || count > 1000) {
        statusDiv.innerHTML = '<div class="alert alert-warning alert-sm">Please enter a number between 1 and 1000</div>';
        return;
    }
    
    statusDiv.innerHTML = '<div class="alert alert-info alert-sm"><i class="fas fa-spinner fa-spin"></i> Generating fake data...</div>';
    
    fetch('{{ route("data-exchange.generate-fake-data") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            type: 'sessions',
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
        a.download = `fake_sessions_${count}_records_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        statusDiv.innerHTML = `<div class="alert alert-success alert-sm"><i class="fas fa-check"></i> Generated ${count} fake session records!</div>`;
        
        // Clear status after 5 seconds
        setTimeout(() => {
            statusDiv.innerHTML = '';
        }, 5000);
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger alert-sm"><i class="fas fa-exclamation-triangle"></i> Failed to generate fake data</div>';
    });
}
</script>
@endpush