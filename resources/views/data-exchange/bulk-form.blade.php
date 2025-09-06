@extends('layouts.app')

@section('title', 'Bulk Upload - DSS Data Exchange')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Bulk Upload</h1>
            <p class="text-muted">Choose the type of data you want to bulk upload to the DSS Data Exchange system</p>
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
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Bulk Upload Clients</h5>
                    <p class="card-text">Upload multiple client records from a CSV file with all required client information
                        including address, demographics, and consent data</p>
                    <a href="{{ route('data-exchange.bulk-clients') }}" class="btn btn-primary">Upload Clients</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-folder fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Bulk Upload Cases</h5>
                    <p class="card-text">Upload multiple case records from a CSV file with case management information,
                        client assignments, and case details</p>
                    <a href="{{ route('data-exchange.bulk-cases') }}" class="btn btn-success">Upload Cases</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-calendar fa-3x text-info mb-3"></i>
                    <h5 class="card-title">Bulk Upload Sessions</h5>
                    <p class="card-text">Upload multiple session records from a CSV file with session and service delivery
                        data including duration, outcomes, and notes</p>
                    <a href="{{ route('data-exchange.bulk-sessions') }}" class="btn btn-info">Upload Sessions</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Bulk Upload Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>General Requirements</h6>
                            <ul>
                                <li>Maximum file size: 2MB</li>
                                <li>Only CSV files are accepted</li>
                                <li>First row must contain column headers</li>
                                <li>Data must match the expected format exactly</li>
                                <li>Empty fields are allowed for optional columns</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Processing Notes</h6>
                            <ul>
                                <li>Each row will be validated before submission</li>
                                <li>Invalid rows will be skipped with error reporting</li>
                                <li>Processing may take time for large files</li>
                                <li>You'll receive detailed results after processing</li>
                                <li>Download sample templates from individual upload pages</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
