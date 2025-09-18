@props(['results', 'type' => ResourceType::CLIENT])

@php
    $totalRecords = count($results);
    $successfulRecords = collect($results)->where('status', 'success')->count();
    $failedRecords = collect($results)->where('status', 'error')->count();

    $typeLabels = [
        ResourceType::CLIENT => 'Client',
        ResourceType::CASE => 'Case',
        ResourceType::SESSION => 'Session',
    ];
    $typeLabel = $typeLabels[$type] ?? 'Record';
@endphp

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3>{{ $totalRecords }}</h3>
                <p class="mb-0">Total {{ Str::plural($typeLabel) }}</p>
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
