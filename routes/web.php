<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataExchangeController;
use App\Http\Controllers\DataMigrationController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\EnrichmentController;

Route::get('/', [DataExchangeController::class, 'index'])->name('home');

// Data Exchange routes
Route::prefix('data-exchange')->name('data-exchange.')->group(function () {
    Route::get('/test-connection', [DataExchangeController::class, 'testConnection'])->name('test-connection');

    // Data Submission Routes
    Route::get('/client-form', [DataExchangeController::class, 'showClientForm'])->name('client-form');
    Route::post('/submit-client', [DataExchangeController::class, 'submitClientData'])->name('submit-client');
    Route::get('/case-form', [DataExchangeController::class, 'showCaseForm'])->name('case-form');
    Route::post('/submit-case', [DataExchangeController::class, 'submitCaseData'])->name('submit-case');
    Route::get('/session-form', [DataExchangeController::class, 'showSessionForm'])->name('session-form');
    Route::post('/submit-session', [DataExchangeController::class, 'submitSessionData'])->name('submit-session');
    Route::get('/bulk-form', [DataExchangeController::class, 'showBulkForm'])->name('bulk-form');
    Route::get('/bulk-clients', [DataExchangeController::class, 'showBulkClientsForm'])->name('bulk-clients');
    Route::post('/bulk-clients-upload', [DataExchangeController::class, 'bulkUploadClients'])->name('bulk-clients-upload');
    Route::get('/bulk-cases', [DataExchangeController::class, 'showBulkCasesForm'])->name('bulk-cases');
    Route::post('/bulk-cases-upload', [DataExchangeController::class, 'bulkUploadCases'])->name('bulk-cases-upload');
    Route::get('/bulk-sessions', [DataExchangeController::class, 'showBulkSessionsForm'])->name('bulk-sessions');
    Route::post('/bulk-sessions-upload', [DataExchangeController::class, 'bulkUploadSessions'])->name('bulk-sessions-upload');

    // Data Retrieval Routes
    Route::get('/retrieve-form', [DataExchangeController::class, 'showRetrieveForm'])->name('retrieve-form');
    Route::post('/retrieve-data', [DataExchangeController::class, 'retrieveData'])->name('retrieve-data');
    Route::get('/resource-schema', [DataExchangeController::class, 'showResourceSchema'])->name('resource-schema');
    Route::post('/generate-report', [DataExchangeController::class, 'generateReport'])->name('generate-report');

    // Resource Index Routes
    Route::get('/clients', [DataExchangeController::class, 'clientsIndex'])->name('clients.index');
    Route::get('/cases', [DataExchangeController::class, 'casesIndex'])->name('cases.index');

    // Nested sessions under cases
    Route::get('/cases/{caseId}/sessions', [DataExchangeController::class, 'caseSessions'])->name('cases.sessions.index');

    // API endpoints for resource operations
    Route::prefix('api')->group(function () {
        // Client API endpoints
        Route::get('/clients/{id}', [DataExchangeController::class, 'apiGetClient'])->name('api.clients.show');
        Route::put('/clients/{id}', [DataExchangeController::class, 'apiUpdateClient'])->name('api.clients.update');
        Route::delete('/clients/{id}', [DataExchangeController::class, 'apiDeleteClient'])->name('api.clients.delete');

        // Case API endpoints
        Route::get('/cases/{id}', [DataExchangeController::class, 'apiGetCase'])->name('api.cases.show');
        Route::put('/cases/{id}', [DataExchangeController::class, 'apiUpdateCase'])->name('api.cases.update');
        Route::delete('/cases/{id}', [DataExchangeController::class, 'apiDeleteCase'])->name('api.cases.delete');

        // Nested session API endpoints under cases
        Route::get('/cases/{caseId}/sessions/{sessionId}', [DataExchangeController::class, 'apiGetCaseSession'])->name('api.cases.sessions.show');
        Route::put('/cases/{caseId}/sessions/{sessionId}', [DataExchangeController::class, 'apiUpdateCaseSession'])->name('api.cases.sessions.update');
        Route::delete('/cases/{caseId}/sessions/{sessionId}', [DataExchangeController::class, 'apiDeleteCaseSession'])->name('api.cases.sessions.delete');

        // Legacy session endpoints (deprecated, kept for backwards compatibility)
        Route::get('/sessions/{id}', [DataExchangeController::class, 'apiGetSession'])->name('api.sessions.show');
        Route::put('/sessions/{id}', [DataExchangeController::class, 'apiUpdateSession'])->name('api.sessions.update');
        Route::delete('/sessions/{id}', [DataExchangeController::class, 'apiDeleteSession'])->name('api.sessions.delete');

        // Export endpoints for live data
        Route::get('/export-clients', [DataExchangeController::class, 'exportClients'])->name('api.export-clients');
        Route::get('/export-cases', [DataExchangeController::class, 'exportCases'])->name('api.export-cases');
        Route::get('/cases/{caseId}/export-sessions', [DataExchangeController::class, 'exportCaseSessions'])->name('api.cases.export-sessions');

        // Legacy session export (deprecated)
        Route::get('/export-sessions', [DataExchangeController::class, 'exportSessions'])->name('api.export-sessions');
    });

    // Status and Utility Routes
    Route::post('/submission-status', [DataExchangeController::class, 'getSubmissionStatus'])->name('submission-status');
    Route::get('/available-functions', [DataExchangeController::class, 'showAvailableFunctions'])->name('available-functions');

    // Fake Data Generation Routes
    Route::post('/generate-fake-data', [DataExchangeController::class, 'generateFakeData'])->name('generate-fake-data');
    Route::get('/download-fake-csv/{type}/{timestamp}', [DataExchangeController::class, 'downloadFakeCSV'])->name('download-fake-csv');

    // Reference Data Routes
    Route::get('/reference-data', [DataExchangeController::class, 'showReferenceData'])->name('reference-data');
    Route::post('/get-reference-data', [DataExchangeController::class, 'getReferenceData'])->name('get-reference-data');
    Route::get('/test-reference-data', [DataExchangeController::class, 'testReferenceData'])->name('test-reference-data');

    // Debug route for session testing
    Route::post('/debug-session', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'case_id_value' => $request->case_id,
            'case_id_empty' => empty($request->case_id),
            'case_id_null' => is_null($request->case_id),
            'case_id_string' => (string)$request->case_id,
            'case_id_length' => strlen((string)$request->case_id),
            'all_data' => $request->all()
        ]);
    })->name('debug-session');
});

// Data Migration routes
Route::prefix('data-migration')->name('data-migration.')->group(function () {
    // Main migration management routes
    Route::get('/', [DataMigrationController::class, 'index'])->name('index');
    Route::get('/create', [DataMigrationController::class, 'create'])->name('create');
    Route::post('/', [DataMigrationController::class, 'store'])->name('store');
    Route::get('/{migration}', [DataMigrationController::class, 'show'])->name('show');
    Route::delete('/{migration}', [DataMigrationController::class, 'destroy'])->name('destroy');

    // Verification routes
    Route::get('/{migration}/verification', [DataMigrationController::class, 'showVerification'])->name('verification');

    // API endpoints for AJAX operations
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/dashboard', [DataMigrationController::class, 'dashboard'])->name('dashboard');
        Route::get('/stats', [DataMigrationController::class, 'stats'])->name('stats');
        Route::get('/{migration}', [DataMigrationController::class, 'getMigration'])->name('get-migration');
        Route::get('/{migration}/status', [DataMigrationController::class, 'status'])->name('status');
        Route::post('/{migration}/cancel', [DataMigrationController::class, 'cancel'])->name('cancel');
        Route::post('/{migration}/retry', [DataMigrationController::class, 'retry'])->name('retry');
        Route::get('/{migration}/export', [DataMigrationController::class, 'export'])->name('export');
        Route::post('/{migration}/quick-verify', [DataMigrationController::class, 'quickVerify'])->name('quick-verify');
        Route::get('/{migration}/verification-status', [VerificationController::class, 'getStatus'])->name('verification-status');
    });
});

// Enrichment routes
Route::prefix('enrichment')->name('enrichment.')->group(function () {
    // Main enrichment dashboard
    Route::get('/', [EnrichmentController::class, 'index'])->name('index');

    // API endpoints for AJAX operations
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/start', [EnrichmentController::class, 'start'])->name('start');
        Route::post('/pause', [EnrichmentController::class, 'pause'])->name('pause');
        Route::post('/resume', [EnrichmentController::class, 'resume'])->name('resume');
        Route::get('/progress', [EnrichmentController::class, 'progress'])->name('progress');
        Route::get('/unenriched', [EnrichmentController::class, 'unenriched'])->name('unenriched');
        Route::get('/active-job', [EnrichmentController::class, 'activeJob'])->name('active-job');
        Route::get('/job-status/{jobId}', [EnrichmentController::class, 'jobStatus'])->name('job-status');
    });
});
