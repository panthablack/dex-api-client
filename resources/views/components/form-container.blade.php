@props([
    'title',
    'description',
    'formAction',
    'method' => 'POST',
    'sampleData' => null,
    'helpContent' => null
])

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">{{ $title }}</h1>
        @if($description)
            <p class="text-muted">{{ $description }}</p>
        @endif
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
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $slot }}</h5>
                @if($sampleData)
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadSampleData()">Load Sample Data</button>
                @endif
            </div>
            <div class="card-body">
                <form action="{{ $formAction }}" method="{{ $method }}" id="resourceForm">
                    @csrf
                    @if(strtoupper($method) !== 'GET')
                        @method($method)
                    @endif
                    {{ $form }}
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="clearForm()">Clear Form</button>
                        {{ $buttons ?? '' }}
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($helpContent)
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Form Help</h5>
                </div>
                <div class="card-body">
                    {{ $helpContent }}
                </div>
            </div>
        </div>
    @endif
</div>

@if (session('request') || session('response') || session('result'))
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">SOAP Request/Response Debug</h5>
                </div>
                <div class="card-body">
                    @if (session('request'))
                        <h6>Last Request:</h6>
                        <div class="xml-container">
                            <pre><code class="language-xml">{{ session('request') }}</code></pre>
                        </div>
                    @endif

                    @if (session('response'))
                        <h6 class="mt-3">Last Response:</h6>
                        <div class="xml-container">
                            <pre><code class="language-xml">{{ session('response') }}</code></pre>
                        </div>
                    @endif

                    @if (session('result'))
                        <h6 class="mt-3">Parsed Result:</h6>
                        <div class="xml-container">
                            <pre>{{ json_encode(session('result'), JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

@if($sampleData)
    @push('scripts')
        <script>
            const sampleData = @json($sampleData);

            function loadSampleData() {
                if (sampleData && Object.keys(sampleData).length > 0) {
                    Object.keys(sampleData).forEach(key => {
                        const element = document.getElementById(key);
                        if (element) {
                            if (element.type === 'checkbox') {
                                element.checked = sampleData[key];
                            } else {
                                element.value = sampleData[key];
                            }
                        }
                    });
                    
                    // Handle special data structures if needed
                    if (typeof window.handleSpecialSampleData === 'function') {
                        window.handleSpecialSampleData(sampleData);
                    }
                }
            }

            function clearForm() {
                document.getElementById('resourceForm').reset();
                // Trigger any special clear functionality
                if (typeof window.handleFormClear === 'function') {
                    window.handleFormClear();
                }
            }
        </script>
    @endpush
@endif