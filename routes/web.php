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
    
    Route::get('/service-form', [DataExchangeController::class, 'showServiceForm'])->name('service-form');
    Route::post('/submit-service', [DataExchangeController::class, 'submitServiceData'])->name('submit-service');
    
    Route::get('/bulk-form', [DataExchangeController::class, 'showBulkForm'])->name('bulk-form');
    Route::post('/bulk-upload', [DataExchangeController::class, 'bulkUpload'])->name('bulk-upload');
    
    // Data Retrieval Routes
    Route::get('/retrieve-form', [DataExchangeController::class, 'showRetrieveForm'])->name('retrieve-form');
    Route::post('/retrieve-data', [DataExchangeController::class, 'retrieveData'])->name('retrieve-data');
    Route::post('/generate-report', [DataExchangeController::class, 'generateReport'])->name('generate-report');
    Route::get('/resource-schema', [DataExchangeController::class, 'getResourceSchema'])->name('resource-schema');
    
    // Status and Utility Routes
    Route::post('/submission-status', [DataExchangeController::class, 'getSubmissionStatus'])->name('submission-status');
    Route::get('/available-functions', [DataExchangeController::class, 'showAvailableFunctions'])->name('available-functions');
});
