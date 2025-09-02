@extends('layouts.app')

@section('title', 'Resource Schema - DSS Data Exchange')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Resource Schema</h1>
        @if($resource_type)
            <p class="text-muted">Schema information for: <strong>{{ ucfirst($resource_type) }}</strong></p>
        @else
            <p class="text-muted">Resource schema and parameter information</p>
        @endif
    </div>
</div>

@if(isset($error))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error:</strong> {{ $error }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(isset($schema))
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Schema Information</h5>
                <a href="{{ route('data-exchange.retrieve-form') }}" class="btn btn-outline-primary btn-sm">
                    ← Back to Retrieve Form
                </a>
            </div>
            <div class="card-body">
                @if(isset($schema['method']))
                    <!-- Default schema format -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">Method Information</h6>
                                    <p><strong>SOAP Method:</strong> <code>{{ $schema['method'] }}</code></p>
                                    <p><strong>Description:</strong> {{ $schema['description'] }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                <h6 class="card-title text-success">Usage</h6>
                                <p><small>This schema information is provided by the application since the DSS service doesn't expose schema details directly.</small></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(isset($schema['required_parameters']))
                    <div class="mt-4">
                        <h6 class="text-danger">Required Parameters</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schema['required_parameters'] as $param => $description)
                                    <tr>
                                        <td><code class="text-danger">{{ $param }}</code></td>
                                        <td>{{ $description }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    @if(isset($schema['optional_parameters']))
                    <div class="mt-4">
                        <h6 class="text-info">Optional Parameters</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schema['optional_parameters'] as $param => $description)
                                    <tr>
                                        <td><code class="text-info">{{ $param }}</code></td>
                                        <td>{{ $description }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                @else
                    <!-- Raw schema data from SOAP -->
                    <div class="alert alert-success">
                        <h6 class="alert-heading">SOAP Schema Retrieved</h6>
                        <p class="mb-0">The following schema was retrieved directly from the DSS SOAP service:</p>
                    </div>
                    <div class="mt-3">
                        <pre class="bg-light p-3 border rounded"><code>{{ json_encode($schema, JSON_PRETTY_PRINT) }}</code></pre>
                    </div>
                @endif

                <div class="mt-4">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Additional Notes</h6>
                        <ul class="mb-0">
                            <li>Date parameters should be in ISO 8601 format (YYYY-MM-DDTHH:MM:SS)</li>
                            <li>All search operations support pagination through PageIndex and PageSize</li>
                            <li>Results can be sorted using SortColumn and IsAscending parameters</li>
                            <li>For specific ID lookups, use the "by ID" options in the resource type dropdown</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex justify-content-center">
            <a href="{{ route('data-exchange.retrieve-form') }}" class="btn btn-primary">
                ← Back to Data Retrieval
            </a>
        </div>
    </div>
</div>
@endsection