@extends('layouts.app')

@section('title', 'Session Enrichment Dashboard')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 text-primary">
                <i class="fas fa-database me-2"></i>
                Session Enrichment Dashboard
            </h1>
        </div>

        <!-- Session Alerts -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- STATE 1: No Cases Available -->
        @if (!$hasAvailableCases)
            <x-enrichment.prerequisite-warning
                prerequisiteType="case"
                message="You must complete a Case migration before you can enrich sessions."
                actionLink="{{ route('data-migration.create') }}"
            />
        @elseif (!$hasShallowSessions)
            <!-- STATE 2: Cases Available but No Shallow Sessions -->
            <x-enrichment.shallow-generation-interface
                :source="$availableSource"
                :canGenerate="true"
            />
        @else
            <!-- STATE 3: Ready for Enrichment -->
            <x-enrichment.enrichment-dashboard
                resourceType="session"
                :canEnrich="$canEnrich"
                :progress="$progress"
            />
        @endif
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Enriched Sessions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Select the format for exporting your enriched session data:</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="exportData('csv')">
                            <i class="fas fa-file-csv me-2"></i>
                            Export as CSV
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportData('json')">
                            <i class="fas fa-file-code me-2"></i>
                            Export as JSON
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportData('xlsx')">
                            <i class="fas fa-file-excel me-2"></i>
                            Export as Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportData(format) {
            const endpoint = '{{ route("enrichment.sessions.api.export") }}?format=' + format;
            fetch(endpoint, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `enriched_sessions_{{ now()->format('Y-m-d_H-i-s') }}.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            })
            .catch(error => {
                console.error('Export error:', error);
                alert('Export failed');
            });

            bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
        }
    </script>
@endsection
