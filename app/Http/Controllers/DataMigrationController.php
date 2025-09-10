<?php

namespace App\Http\Controllers;

use App\Models\DataMigration;
use App\Services\DataMigrationService;
use App\Services\DataVerificationService;
use App\Services\VerificationService;
use App\Jobs\InitiateDataMigration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DataMigrationController extends Controller
{
    protected DataMigrationService $migrationService;
    protected DataVerificationService $verificationService;
    protected VerificationService $newVerificationService;

    public function __construct(
        DataMigrationService $migrationService, 
        DataVerificationService $verificationService,
        VerificationService $newVerificationService
    ) {
        $this->migrationService = $migrationService;
        $this->verificationService = $verificationService;
        $this->newVerificationService = $newVerificationService;
    }

    /**
     * Display the main data migration dashboard
     */
    public function index()
    {
        $migrations = DataMigration::with('batches')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('data-exchange.migration.index', compact('migrations'));
    }

    /**
     * Show the form for creating a new migration
     */
    public function create()
    {
        return view('data-exchange.migration.create');
    }

    /**
     * Store a newly created migration
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'resource_types' => 'required|array|min:1',
            'resource_types.*' => 'in:clients,cases,sessions',
            'batch_size' => 'integer|min:10|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $filters = [];
            if ($request->date_from) {
                $filters['date_from'] = $request->date_from;
            }
            if ($request->date_to) {
                $filters['date_to'] = $request->date_to;
            }

            $migration = $this->migrationService->createMigration([
                'name' => $request->name,
                'resource_types' => $request->resource_types,
                'filters' => $filters,
                'batch_size' => $request->batch_size ?? 100
            ]);

            // Dispatch job to initiate the migration
            InitiateDataMigration::dispatch($migration);

            return redirect()->route('data-migration.show', $migration)
                ->with('success', 'Data migration created and initiated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to create data migration: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create migration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified migration
     */
    public function show(DataMigration $migration)
    {
        $migration->load(['batches' => function ($query) {
            $query->orderBy('resource_type')->orderBy('batch_number');
        }]);

        return view('data-exchange.migration.show', compact('migration'));
    }

    /**
     * Get migration status via API
     */
    public function status(DataMigration $migration): JsonResponse
    {
        try {
            $status = $this->migrationService->getMigrationStatus($migration);
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a migration
     */
    public function cancel(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, ['pending', 'in_progress'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot cancel migration with status: ' . $migration->status
                ], 400);
            }

            $this->migrationService->cancelMigration($migration);

            return response()->json([
                'success' => true,
                'message' => 'Migration cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed batches
     */
    public function retry(DataMigration $migration): JsonResponse
    {
        try {
            $failedBatches = $migration->batches()->where('status', 'failed')->count();
            
            if ($failedBatches === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No failed batches to retry'
                ], 400);
            }

            $this->migrationService->retryFailedBatches($migration);

            return response()->json([
                'success' => true,
                'message' => "Retrying {$failedBatches} failed batches"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a migration and its data
     */
    public function destroy(DataMigration $migration)
    {
        try {
            if ($migration->status === 'in_progress') {
                return redirect()->back()
                    ->with('error', 'Cannot delete migration that is currently in progress. Cancel it first.');
            }

            // Delete related migrated data based on batch IDs
            $batchIds = $migration->batches()->pluck('batch_id');
            
            if ($batchIds->isNotEmpty()) {
                // Delete migrated records
                \App\Models\MigratedClient::whereIn('migration_batch_id', $batchIds)->delete();
                \App\Models\MigratedCase::whereIn('migration_batch_id', $batchIds)->delete();
                \App\Models\MigratedSession::whereIn('migration_batch_id', $batchIds)->delete();
            }

            // Delete migration and its batches (cascaded)
            $migration->delete();

            return redirect()->route('data-migration.index')
                ->with('success', 'Migration and all associated data deleted successfully');

        } catch (\Exception $e) {
            Log::error('Failed to delete data migration: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to delete migration: ' . $e->getMessage());
        }
    }

    /**
     * Get all migrations with their status for dashboard
     */
    public function dashboard(): JsonResponse
    {
        try {
            $migrations = DataMigration::with('batches')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($migration) {
                    return [
                        'id' => $migration->id,
                        'name' => $migration->name,
                        'status' => $migration->status,
                        'progress_percentage' => $migration->progress_percentage,
                        'success_rate' => $migration->success_rate,
                        'resource_types' => $migration->resource_types,
                        'created_at' => $migration->created_at->toISOString(),
                        'total_batches' => $migration->batches->count(),
                        'completed_batches' => $migration->batches->where('status', 'completed')->count(),
                        'failed_batches' => $migration->batches->where('status', 'failed')->count()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $migrations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get migration statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_migrations' => DataMigration::count(),
                'active_migrations' => DataMigration::active()->count(),
                'completed_migrations' => DataMigration::completed()->count(),
                'failed_migrations' => DataMigration::failed()->count(),
                'total_clients_migrated' => \App\Models\MigratedClient::count(),
                'total_cases_migrated' => \App\Models\MigratedCase::count(),
                'total_sessions_migrated' => \App\Models\MigratedSession::count(),
                'recent_activity' => DataMigration::orderBy('updated_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($migration) {
                        return [
                            'id' => $migration->id,
                            'name' => $migration->name,
                            'status' => $migration->status,
                            'updated_at' => $migration->updated_at->diffForHumans()
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export migrated data
     */
    public function export(DataMigration $migration, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|in:clients,cases,sessions',
            'format' => 'required|in:csv,json,xlsx'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $resourceType = $request->resource_type;
            $format = $request->format;
            
            $batchIds = $migration->batches()
                ->where('resource_type', $resourceType)
                ->where('status', 'completed')
                ->pluck('batch_id');

            if ($batchIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => "No completed {$resourceType} data found for this migration"
                ], 404);
            }

            // Get the data based on resource type
            switch ($resourceType) {
                case 'clients':
                    $data = \App\Models\MigratedClient::whereIn('migration_batch_id', $batchIds)->get();
                    break;
                case 'cases':
                    $data = \App\Models\MigratedCase::whereIn('migration_batch_id', $batchIds)->get();
                    break;
                case 'sessions':
                    $data = \App\Models\MigratedSession::whereIn('migration_batch_id', $batchIds)->get();
                    break;
            }

            // Convert to the requested format
            $filename = "{$migration->name}_{$resourceType}_" . now()->format('Y-m-d_H-i-s');
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($data, $filename);
                case 'json':
                    return $this->exportToJson($data, $filename);
                case 'xlsx':
                    return $this->exportToExcel($data, $filename);
            }

        } catch (\Exception $e) {
            Log::error('Failed to export migration data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    protected function exportToCsv($data, $filename)
    {
        $csv = \League\Csv\Writer::createFromString('');
        
        if ($data->isNotEmpty()) {
            // Add headers
            $headers = array_keys($data->first()->toArray());
            $csv->insertOne($headers);
            
            // Add data rows
            foreach ($data as $row) {
                $csv->insertOne(array_values($row->toArray()));
            }
        }

        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\""
        ]);
    }

    /**
     * Export data to JSON
     */
    protected function exportToJson($data, $filename)
    {
        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\""
        ]);
    }

    /**
     * Export data to Excel (simplified)
     */
    protected function exportToExcel($data, $filename)
    {
        // For now, export as CSV with .xlsx extension
        return $this->exportToCsv($data, $filename . '.xlsx');
    }

    /**
     * Perform quick verification of migrated data
     */
    public function quickVerify(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, ['completed', 'failed']) && $migration->batches->where('status', 'completed')->count() === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only verify migrations with completed batches'
                ], 400);
            }

            $results = $this->newVerificationService->quickVerifyMigration($migration, 10);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Quick verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform full verification of migrated data
     */
    public function fullVerify(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, ['completed', 'failed'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only verify completed or failed migrations'
                ], 400);
            }

            Log::info("Starting full verification for migration {$migration->id}");

            // Generate unique verification ID
            $verificationId = $migration->id . '_' . time();

            // Dispatch async verification job
            \App\Jobs\VerifyMigrationJob::dispatch($migration, $verificationId);

            return response()->json([
                'success' => true,
                'data' => [
                    'verification_id' => $verificationId,
                    'status' => 'starting',
                    'message' => 'Full verification started. Check status for progress.'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Full verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show verification results for a migration
     */
    public function showVerification(DataMigration $migration)
    {
        return view('data-exchange.migration.verification', compact('migration'));
    }

    /**
     * Get verification status for a migration
     */
    public function verificationStatus(DataMigration $migration, Request $request): JsonResponse
    {
        try {
            $verificationId = $request->get('verification_id');
            
            if ($verificationId) {
                // Check cache for ongoing verification status
                $status = Cache::get("verification_status_{$verificationId}");
                
                if ($status) {
                    return response()->json([
                        'success' => true,
                        'data' => $status
                    ]);
                }
            }
            
            // Fallback: return current verification stats from database
            $stats = $this->newVerificationService->getMigrationVerificationStats($migration);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'total' => $stats['total'],
                    'processed' => $stats['total'],
                    'verified' => $stats['verified'],
                    'results' => $stats['results']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Verification status check failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
