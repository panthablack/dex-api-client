@extends('layouts.app')

@section('title', 'Available SOAP Methods - DSS Data Exchange')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Available SOAP Methods</h1>
            <p class="text-muted">These are the SOAP methods available in the DSS Data Exchange service</p>
        </div>
    </div>

    @if (isset($error))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (isset($functions))
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">SOAP Methods ({{ count($functions) }})</h5>
                        <a href="{{ route('data-exchange.retrieve-form') }}" class="btn btn-outline-primary btn-sm">
                            ← Back to Retrieve Form
                        </a>
                    </div>
                    <div class="card-body">
                        @if (count($functions) > 0)
                            <div class="mt-2">
                                <h6>Method Analysis:</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Data Retrieval Methods</h6>
                                                <ul class="list-unstyled mb-0">
                                                    @foreach ($functions as $function)
                                                        @if (str_contains($function, 'Search') || str_contains($function, 'Get'))
                                                            <li><small><code>{{ $function }}</code></small></li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Data Submission Methods</h6>
                                                <ul class="list-unstyled mb-0">
                                                    @foreach ($functions as $function)
                                                        @if (str_contains($function, 'Submit') || str_contains($function, 'Create') || str_contains($function, 'Update'))
                                                            <li><small><code>{{ $function }}</code></small></li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">Usage Notes:</h6>
                                    <ul class="mb-0">
                                        <li>These methods are detected directly from the WSDL service definition</li>
                                        <li>The application will try multiple method names for each resource type</li>
                                        <li>If a method doesn't work, check the exact parameter requirements in the SOAP
                                            documentation</li>
                                        <li>Use the "Get Resource Schema" button on the retrieve form for parameter details
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mt-3">
                                <h6>All Methods:</h6>
                                <div class="row">
                                    @foreach ($functions as $index => $function)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100 border-secondary">
                                                <div class="card-body">
                                                    <h6 class="card-title text-primary">{{ $index + 1 }}.</h6>
                                                    <p class="card-text">
                                                        <code class="text-dark">{{ $function }}</code>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <h6 class="alert-heading">No Methods Found</h6>
                                <p class="mb-0">No SOAP methods were detected. This could indicate a connection issue or
                                    WSDL parsing problem.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if (isset($types) && count($types) > 0)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">SOAP Types ({{ count($types) }})</h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="typesAccordion">
                                @foreach ($types as $index => $type)
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading{{ $index }}">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}"
                                                aria-expanded="false" aria-controls="collapse{{ $index }}">
                                                Type {{ $index + 1 }}
                                            </button>
                                        </h2>
                                        <div id="collapse{{ $index }}" class="accordion-collapse collapse"
                                            aria-labelledby="heading{{ $index }}" data-bs-parent="#typesAccordion">
                                            <div class="accordion-body">
                                                <pre><code>{{ $type }}</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
