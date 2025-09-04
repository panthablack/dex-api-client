@extends('layouts.app')

@section('title', 'Bulk Upload Results - DSS Data Exchange')

@section('content')
@php
    // Detect the data type based on the first result
    $dataType = 'clients'; // default
    if (!empty($results)) {
        $firstResult = $results[0];
        if (isset($firstResult['case_data'])) {
            $dataType = 'cases';
        } elseif (isset($firstResult['session_data'])) {
            $dataType = 'sessions';
        } elseif (isset($firstResult['client_data'])) {
            $dataType = 'clients';
        }
    }
    
    $typeLabels = [
        'clients' => 'Client Data',
        'cases' => 'Case Data',
        'sessions' => 'Session Data'
    ];
    $typeLabel = $typeLabels[$dataType] ?? 'Data';
    
    $totalRecords = count($results);
    $successfulRecords = collect($results)->where('status', 'success')->count();
    $failedRecords = collect($results)->where('status', 'error')->count();
@endphp

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Bulk Upload Results</h1>
        <p class="text-muted">Results from your bulk {{ strtolower($typeLabel) }} upload</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <x-bulk-results-stats :results="$results" :type="$dataType" />
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
                <x-bulk-results-table :results="$results" :type="$dataType" />
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
const dataType = @json($dataType);

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
    const exportData = results.map((result, index) => {
        // Safely extract SubmissionID from either array or object
        let submissionId = 'N/A';
        if (result.result) {
            if (typeof result.result === 'object' && result.result.SubmissionID) {
                submissionId = result.result.SubmissionID;
            } else if (Array.isArray(result.result) && result.result.SubmissionID) {
                submissionId = result.result.SubmissionID;
            }
        }

        let exportRow = {
            row: index + 1,
            status: result.status,
            submission_id: submissionId,
            error: result.error || 'N/A'
        };

        // Add type-specific fields
        if (dataType === 'clients') {
            exportRow.client_id = result.client_data?.client_id || 'N/A';
            exportRow.first_name = result.client_data?.first_name || 'N/A';
            exportRow.last_name = result.client_data?.last_name || 'N/A';
        } else if (dataType === 'cases') {
            exportRow.case_id = result.case_data?.case_id || 'N/A';
            exportRow.client_id = result.case_data?.client_id || 'N/A';
        } else if (dataType === 'sessions') {
            exportRow.session_id = result.session_data?.session_id || 'N/A';
            exportRow.case_id = result.session_data?.case_id || 'N/A';
        }

        return exportRow;
    });

    const filename = `bulk_upload_${dataType}_results`;
    if (format === 'csv') {
        const csvContent = convertToCSV(exportData);
        downloadFile(csvContent, `${filename}.csv`, 'text/csv');
    } else if (format === 'json') {
        const jsonContent = JSON.stringify(exportData, null, 2);
        downloadFile(jsonContent, `${filename}.json`, 'application/json');
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