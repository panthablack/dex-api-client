<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataExchangeController;

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
    Route::post('/bulk-upload', [DataExchangeController::class, 'bulkUpload'])->name('bulk-upload');

    // Data Retrieval Routes
    Route::get('/retrieve-form', [DataExchangeController::class, 'showRetrieveForm'])->name('retrieve-form');
    Route::post('/retrieve-data', [DataExchangeController::class, 'retrieveData'])->name('retrieve-data');
    Route::get('/resource-schema', [DataExchangeController::class, 'showResourceSchema'])->name('resource-schema');
    Route::post('/generate-report', [DataExchangeController::class, 'generateReport'])->name('generate-report');

    // Status and Utility Routes
    Route::post('/submission-status', [DataExchangeController::class, 'getSubmissionStatus'])->name('submission-status');
    Route::get('/available-functions', [DataExchangeController::class, 'showAvailableFunctions'])->name('available-functions');

    // Fake Data Generation Routes
    Route::post('/generate-fake-data', [DataExchangeController::class, 'generateFakeData'])->name('generate-fake-data');
    Route::post('/generate-fake-dataset', [DataExchangeController::class, 'generateFakeDataset'])->name('generate-fake-dataset');
    Route::get('/download-fake-csv/{type}/{timestamp}', [DataExchangeController::class, 'downloadFakeCSV'])->name('download-fake-csv');

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
