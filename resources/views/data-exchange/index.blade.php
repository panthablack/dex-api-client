@extends('layouts.app')

@section('title', 'DSS Data Exchange Dashboard')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">DSS Data Exchange SOAP Client</h1>
            <p class="lead">Connect to the Australian Government's Department of Social Services Data Exchange System</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Connection Status</h5>
                    <button id="testConnection" class="btn btn-outline-primary btn-sm">Test Connection</button>
                </div>
                <div class="card-body">
                    <div id="connectionStatus" class="text-muted">Click "Test Connection" to check SOAP service availability
                    </div>
                    <div id="connectionDetails" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-upload fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Submit Data</h5>
                    <p class="card-text">Submit client information, service data, or upload CSV files in bulk</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('data-exchange.client-form') }}" class="btn btn-outline-primary btn-sm">Submit
                            Client Data</a>
                        <a href="{{ route('data-exchange.case-form') }}" class="btn btn-outline-primary btn-sm">Submit Case
                            Data</a>
                        <a href="{{ route('data-exchange.session-form') }}" class="btn btn-outline-primary btn-sm">Submit
                            Session Data</a>
                        <a href="{{ route('data-exchange.bulk-form') }}" class="btn btn-outline-primary btn-sm">Bulk
                            Upload</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-download fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Retrieve Data</h5>
                    <p class="card-text">Retrieve, filter, and download data in JSON, XML, or CSV formats</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('data-exchange.retrieve-form') }}" class="btn btn-success">Search & Retrieve</a>
                        <div class="btn-group w-100" role="group">
                            <a href="{{ route('data-exchange.clients.index') }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-users"></i> Clients
                            </a>
                            <a href="{{ route('data-exchange.cases.index') }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-folder-open"></i> Cases
                            </a>
                            <a href="{{ route('data-exchange.sessions.index') }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-calendar-alt"></i> Sessions
                            </a>
                        </div>
                        <small class="text-muted mt-2">Browse by resource type or search specific records</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">System Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>SOAP Configuration</h6>
                            <ul class="list-unstyled">
                                <li><strong>WSDL URL:</strong> <code>{{ config('soap.dss.wsdl_url') }}</code></li>
                                <li><strong>SOAP Version:</strong>
                                    <code>{{ config('soap.dss.soap_options.soap_version') == SOAP_1_2 ? '1.2' : '1.1' }}</code>
                                </li>
                                <li><strong>Organization ID:</strong>
                                    <code>{{ config('soap.dss.organisation_id') ?? 'Not configured' }}</code>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Features</h6>
                            <ul class="list-unstyled">
                                <li>✓ Client data submission</li>
                                <li>✓ Case data submission</li>
                                <li>✓ Session data submission</li>
                                <li>✓ Bulk upload via CSV</li>
                                <li>✓ Connection testing</li>
                                <li>✓ Request/Response debugging</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('testConnection').addEventListener('click', function() {
            const button = this;
            const statusDiv = document.getElementById('connectionStatus');
            const detailsDiv = document.getElementById('connectionDetails');

            button.disabled = true;
            button.textContent = 'Testing...';
            statusDiv.innerHTML =
                '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Testing connection...';
            detailsDiv.style.display = 'none';

            fetch('{{ route('data-exchange.test-connection') }}')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        statusDiv.innerHTML = '<span class="badge bg-success">Connected</span> ' + data.message;
                        detailsDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Available Functions (${data.functions_count})</h6>
                            <div class="small text-muted" style="max-height: 200px; overflow-y: auto;">
                                ${data.functions.map(func => `<div>${func}</div>`).join('')}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Available Types (${data.types_count})</h6>
                            <div class="small text-muted" style="max-height: 200px; overflow-y: auto;">
                                ${data.types.map(type => `<div>${type}</div>`).join('')}
                            </div>
                        </div>
                    </div>
                `;
                        detailsDiv.style.display = 'block';
                    } else {
                        statusDiv.innerHTML = '<span class="badge bg-danger">Failed</span> ' + data.message;
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = '<span class="badge bg-danger">Error</span> ' + error.message;
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = 'Test Connection';
                });
        });
    </script>
@endpush
