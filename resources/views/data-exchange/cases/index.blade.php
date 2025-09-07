@extends('layouts.app')

@section('title', 'Cases - DSS Data Exchange')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mb-2">Cases</h1>
                    <p class="text-muted">View and manage case records from the DSS Data Exchange system</p>
                </div>
                <div>
                    <a href="{{ route('data-exchange.case-form') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Case
                    </a>
                </div>
            </div>
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

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter"></i> Filters
                <button class="btn btn-sm btn-outline-secondary ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div class="collapse show" id="filtersCollapse">
            <div class="card-body">
                <form method="GET" action="{{ route('data-exchange.cases.index') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="client_id" class="form-label">Client ID</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" 
                                   value="{{ request('client_id') }}" placeholder="Search client ID">
                        </div>
                        <div class="col-md-3">
                            <label for="referral_source_code" class="form-label">Referral Source</label>
                            <select class="form-select" id="referral_source_code" name="referral_source_code">
                                <option value="">All Sources</option>
                                <option value="COMMUNITY" {{ request('referral_source_code') === 'COMMUNITY' ? 'selected' : '' }}>Community services agency</option>
                                <option value="SELF" {{ request('referral_source_code') === 'SELF' ? 'selected' : '' }}>Self</option>
                                <option value="FAMILY" {{ request('referral_source_code') === 'FAMILY' ? 'selected' : '' }}>Family</option>
                                <option value="GP" {{ request('referral_source_code') === 'GP' ? 'selected' : '' }}>General Medical Practitioner</option>
                                <option value="HealthAgency" {{ request('referral_source_code') === 'HealthAgency' ? 'selected' : '' }}>Health Agency</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="outlet_activity_id" class="form-label">Outlet Activity</label>
                            <select class="form-select" id="outlet_activity_id" name="outlet_activity_id">
                                <option value="">All Activities</option>
                                @if(isset($outletActivities))
                                    @foreach($outletActivities as $activity)
                                        <option value="{{ $activity->OutletActivityId }}" 
                                                {{ request('outlet_activity_id') == $activity->OutletActivityId ? 'selected' : '' }}>
                                            {{ substr($activity->ActivityName, 0, 30) }}{{ strlen($activity->ActivityName) > 30 ? '...' : '' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_range" class="form-label">Date Range</label>
                            <select class="form-select" id="date_range" name="date_range">
                                <option value="">All Dates</option>
                                <option value="7" {{ request('date_range') === '7' ? 'selected' : '' }}>Last 7 days</option>
                                <option value="30" {{ request('date_range') === '30' ? 'selected' : '' }}>Last 30 days</option>
                                <option value="90" {{ request('date_range') === '90' ? 'selected' : '' }}>Last 90 days</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="{{ route('data-exchange.cases.index') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cases Table -->
    <x-resource-table 
        title="Case Records" 
        resource-type="case"
        :data="$cases ?? []"
        :columns="[
            ['key' => 'CaseDetail.CaseId', 'label' => 'Case ID'],
            ['key' => 'Clients.CaseClient.ClientId', 'label' => 'Client ID'],
            ['key' => 'CaseDetail.OutletActivityId', 'label' => 'Outlet Activity ID'],
            ['key' => 'Clients.CaseClient.ReferralSourceCode', 'label' => 'Referral Source'],
            ['key' => 'CaseDetail.ClientAttendanceProfileCode', 'label' => 'Attendance Profile'],
            ['key' => 'CreatedDateTime', 'label' => 'Created Date', 'format' => 'date'],
            ['key' => 'Clients.CaseClient.ExitReasonCode', 'label' => 'Exit Reason'],
            ['key' => 'CaseDetail.TotalNumberOfUnidentifiedClients', 'label' => 'Unidentified Clients']
        ]"
        :loading="$loading ?? false"
        empty-message="No cases found. Try adjusting your filters or add a new case."
    />

    <!-- Pagination -->
    <x-pagination :pagination="$pagination ?? null" />

    @if(isset($debugInfo['view_debug']) && $debugInfo['view_debug'] && config('features.debugging.show_debug_information'))
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Debug Information</h5>
            </div>
            <div class="card-body">
                <pre>{{ json_encode($debugInfo ?? [], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
<script>
    // Auto-submit form when filters change (optional)
    document.querySelectorAll('#filtersCollapse select').forEach(select => {
        select.addEventListener('change', function() {
            // Uncomment to auto-submit on filter change
            // this.form.submit();
        });
    });
</script>
@endpush