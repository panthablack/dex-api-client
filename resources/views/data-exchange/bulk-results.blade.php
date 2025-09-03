@extends('layouts.app')

@section('title', 'Bulk Upload Results - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Bulk Upload Results</h1>
        <p class="text-muted">Results from your bulk client data upload</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        @php
            $totalRecords = count($results);
            $successfulRecords = collect($results)->where('status', 'success')->count();
            $failedRecords = collect($results)->where('status', 'error')->count();
        @endphp
        
        <div class="row">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3>{{ $totalRecords }}</h3>
                        <p class="mb-0">Total Records</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3>{{ $successfulRecords }}</h3>
                        <p class="mb-0">Successful</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3>{{ $failedRecords }}</h3>
                        <p class="mb-0">Failed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3>{{ $totalRecords > 0 ? number_format(($successfulRecords / $totalRecords) * 100, 1) : 0 }}%</h3>
                        <p class="mb-0">Success Rate</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($failedRecords > 0)
<div class="alert alert-warning">
    <h6 class="alert-heading">Some Records Failed</h6>
    {{ $failedRecords }} out of {{ $totalRecords }} records failed to process. Please review the errors below and correct your data before re-uploading.
</div>
@endif

@if($successfulRecords === $totalRecords)
<div class="alert alert-success">
    <h6 class="alert-heading">All Records Processed Successfully!</h6>
    All {{ $totalRecords }} records were successfully submitted to the DSS Data Exchange system.
</div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Detailed Results</h5>
                <div>
                    <button class="btn btn-outline-primary btn-sm" onclick="exportResults('csv')">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-info btn-sm ms-2" onclick="exportResults('json')">
                        <i class="fas fa-download"></i> Export JSON
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Row #</th>
                                <th>Status</th>
                                <th>Client ID</th>
                                <th>Name</th>
                                <th>Result/Error</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $index => $result)
                            <tr class="{{ $result['status'] === 'success' ? 'table-success' : 'table-danger' }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if($result['status'] === 'success')
                                        <span class="badge bg-success">Success</span>
                                    @else
                                        <span class="badge bg-danger">Error</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($result['client_data']['client_id']))
                                        {{ $result['client_data']['client_id'] }}
                                    @else
                                        <em class="text-muted">N/A</em>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($result['client_data']['first_name']) && isset($result['client_data']['last_name']))
                                        {{ $result['client_data']['first_name'] }} {{ $result['client_data']['last_name'] }}
                                    @else
                                        <em class="text-muted">N/A</em>
                                    @endif
                                </td>
                                <td>
                                    @if($result['status'] === 'success')
                                        @if(isset($result['result']['SubmissionID']))
                                            <small class="text-success">Submission ID: {{ $result['result']['SubmissionID'] }}</small>
                                        @else
                                            <small class="text-success">Successfully processed</small>
                                        @endif
                                    @else
                                        <small class="text-danger">{{ $result['error'] ?? 'Unknown error' }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($result['status'] === 'success' && isset($result['result']['SubmissionID']))
                                        <button class="btn btn-outline-info btn-sm" 
                                                onclick="checkSubmissionStatus('{{ $result['result']['SubmissionID'] }}')"
                                                title="Check Status">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    @endif
                                    <button class="btn btn-outline-secondary btn-sm" 
                                            onclick="showDetails({{ $index }})"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12 text-center">
        <a href="{{ route('data-exchange.bulk-form') }}" class="btn btn-primary me-2">
            <i class="fas fa-upload"></i> Upload Another File
        </a>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submission Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="statusContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Checking status...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const results = @json($results);

function showDetails(index) {
    const result = results[index];
    const detailsContent = document.getElementById('detailsContent');
    detailsContent.textContent = JSON.stringify(result, null, 2);
    
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
}

function checkSubmissionStatus(submissionId) {
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    const statusContent = document.getElementById('statusContent');
    
    statusModal.show();
    
    fetch('{{ route('data-exchange.submission-status') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ submission_id: submissionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            statusContent.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${data.error}
                </div>
            `;
        } else {
            statusContent.innerHTML = `
                <div class="alert alert-info">
                    <h6>Submission ID: ${submissionId}</h6>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        }
    })
    .catch(error => {
        statusContent.innerHTML = `
            <div class="alert alert-danger">
                <strong>Error:</strong> Failed to retrieve status information.
            </div>
        `;
    });
}

function exportResults(format) {
    const exportData = results.map((result, index) => ({
        row: index + 1,
        status: result.status,
        client_id: result.client_data?.client_id || 'N/A',
        first_name: result.client_data?.first_name || 'N/A',
        last_name: result.client_data?.last_name || 'N/A',
        submission_id: result.result?.SubmissionID || 'N/A',
        error: result.error || 'N/A'
    }));

    if (format === 'csv') {
        const csvContent = convertToCSV(exportData);
        downloadFile(csvContent, 'bulk_upload_results.csv', 'text/csv');
    } else if (format === 'json') {
        const jsonContent = JSON.stringify(exportData, null, 2);
        downloadFile(jsonContent, 'bulk_upload_results.json', 'application/json');
    }
}

function convertToCSV(data) {
    if (!data.length) return '';
    
    const headers = Object.keys(data[0]);
    const csvHeaders = headers.join(',');
    
    const csvRows = data.map(row => 
        headers.map(header => {
            const value = row[header] || '';
            return `"${String(value).replace(/"/g, '""')}"`;
        }).join(',')
    );
    
    return csvHeaders + '\n' + csvRows.join('\n');
}

function downloadFile(content, filename, contentType) {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>
@endpush