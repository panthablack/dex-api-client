<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataExchangeService;

class DataExchangeCommand extends Command
{
    protected $signature = 'dex:client
                            {action : The action to perform (test, submit-client, submit-service, get-clients, get-services, get-client, export-data, generate-report, status, functions)}
                            {--file= : CSV file path for bulk operations}
                            {--output= : Output file path for downloads}
                            {--format= : Output format (json, xml, csv)}
                            {--resource= : Resource type for data retrieval}
                            {--report= : Report type for report generation}
                            {--client-id= : Client ID for operations}
                            {--submission-id= : Submission ID for status check}
                            {--first-name= : Client first name}
                            {--last-name= : Client last name}
                            {--date-of-birth= : Client date of birth (YYYY-MM-DD)}
                            {--gender= : Client gender (M/F/X/9)}
                            {--postal-code= : Client postal code}
                            {--service-type= : Service type}
                            {--service-start-date= : Service start date (YYYY-MM-DD)}
                            {--service-end-date= : Service end date (YYYY-MM-DD)}
                            {--date-from= : Filter date from (YYYY-MM-DD)}
                            {--date-to= : Filter date to (YYYY-MM-DD)}
                            {--interactive : Run in interactive mode}
                            {--detailed : Show detailed output}';

    protected $description = 'DSS Data Exchange SOAP client command line interface';

    protected $dataExchangeService;

    public function __construct(DataExchangeService $dataExchangeService)
    {
        parent::__construct();
        $this->dataExchangeService = $dataExchangeService;
    }

    public function handle()
    {
        $action = $this->argument('action');

        $this->info("DSS Data Exchange SOAP Client");
        $this->info("Action: {$action}");
        $this->line('');

        try {
            switch ($action) {
                case 'test':
                    return $this->testConnection();

                case 'submit-client':
                    return $this->submitClientData();

                case 'get-clients':
                    return $this->getClientData();

                case 'get-client':
                    return $this->getClientById();

                case 'export-data':
                    return $this->exportData();

                case 'status':
                    return $this->getSubmissionStatus();

                case 'bulk-submit':
                    return $this->bulkSubmit();

                default:
                    $this->error("Unknown action: {$action}");
                    $this->showHelp();
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());

            if ($this->option('detailed')) {
                $this->error("Request: " . $this->dataExchangeService->getLastRequest());
                $this->error("Response: " . $this->dataExchangeService->getLastResponse());
            }

            return 1;
        }
    }

    protected function testConnection()
    {
        $this->info("Testing SOAP connection...");

        $result = $this->dataExchangeService->testConnection();

        if ($result['status'] === 'success') {
            $this->info("✓ Connection successful!");
            $this->line("Functions available: " . $result['functions_count']);
            $this->line("Types available: " . $result['types_count']);

            if ($this->option('detailed')) {
                $this->line('');
                $this->info("Available Functions:");
                foreach ($result['functions'] as $function) {
                    $this->line("  - {$function}");
                }

                $this->line('');
                $this->info("Available Types:");
                foreach ($result['types'] as $type) {
                    $this->line("  - {$type}");
                }
            }
        } else {
            $this->error("✗ Connection failed: " . $result['message']);
            return 1;
        }

        return 0;
    }

    protected function submitClientData()
    {
        if ($this->option('interactive')) {
            $clientData = $this->collectClientDataInteractively();
        } else {
            $clientData = $this->collectClientDataFromOptions();
        }

        if (empty($clientData['client_id'])) {
            $this->error("Client ID is required");
            return 1;
        }

        $this->info("Submitting client data...");

        $result = $this->dataExchangeService->submitClientData($clientData);

        $this->info("✓ Client data submitted successfully!");

        if ($this->option('detailed')) {
            $this->line("Result: " . json_encode($result, JSON_PRETTY_PRINT));
            $this->line("Request: " . $this->dataExchangeService->getLastRequest());
            $this->line("Response: " . $this->dataExchangeService->getLastResponse());
        }

        return 0;
    }

    protected function getSubmissionStatus()
    {
        $submissionId = $this->option('submission-id');

        if (!$submissionId) {
            $submissionId = $this->ask("Enter submission ID");
        }

        if (!$submissionId) {
            $this->error("Submission ID is required");
            return 1;
        }

        $this->info("Checking submission status...");

        $result = $this->dataExchangeService->getSubmissionStatus($submissionId);

        $this->info("Submission Status:");
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return 0;
    }

    protected function bulkSubmit()
    {
        $file = $this->option('file');

        if (!$file) {
            $file = $this->ask("Enter CSV file path");
        }

        if (!$file || !file_exists($file)) {
            $this->error("CSV file not found: {$file}");
            return 1;
        }

        $this->info("Processing bulk submit from: {$file}");

        $clientDataArray = $this->parseCsvFile($file);

        $this->info("Found " . count($clientDataArray) . " records");

        if (!$this->confirm("Proceed with bulk submission?")) {
            $this->info("Bulk submission cancelled");
            return 0;
        }

        $results = $this->dataExchangeService->bulkSubmitClientData($clientDataArray);

        $successful = 0;
        $failed = 0;

        foreach ($results as $index => $result) {
            if ($result['status'] === 'success') {
                $successful++;
                $this->line("✓ Record " . ($index + 1) . " submitted successfully");
            } else {
                $failed++;
                $this->error("✗ Record " . ($index + 1) . " failed: " . $result['error']);
            }
        }

        $this->info("Bulk submission completed:");
        $this->line("Successful: {$successful}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }

    protected function collectClientDataInteractively()
    {
        $this->info("Enter client information (press Enter to skip optional fields):");

        return [
            'client_id' => $this->ask('Client ID (required)', null, function ($answer) {
                return $answer ?: null;
            }),
            'first_name' => $this->ask('First Name (required)', null, function ($answer) {
                return $answer ?: null;
            }),
            'last_name' => $this->ask('Last Name (required)', null, function ($answer) {
                return $answer ?: null;
            }),
            'date_of_birth' => $this->ask('Date of Birth (YYYY-MM-DD, required)', null, function ($answer) {
                return $answer ?: null;
            }),
            'gender' => $this->choice('Gender (required)', ['M', 'F', 'X', '9'], null),
            'indigenous_status' => $this->choice('Indigenous Status', ['Y', 'N', 'U', ''], 3),
            'country_of_birth' => $this->ask('Country of Birth', 'Australia'),
            'postal_code' => $this->ask('Postal Code'),
            'primary_language' => $this->ask('Primary Language', 'English'),
            'interpreter_required' => $this->confirm('Interpreter Required?', false),
            'disability_flag' => $this->confirm('Has Disability?', false),
            'client_type' => $this->choice('Client Type', ['Individual', 'Family', 'Group', ''], 0)
        ];
    }

    protected function collectClientDataFromOptions()
    {
        return [
            'client_id' => $this->option('client-id'),
            'first_name' => $this->option('first-name'),
            'last_name' => $this->option('last-name'),
            'date_of_birth' => $this->option('date-of-birth'),
            'gender' => $this->option('gender'),
            'postal_code' => $this->option('postal-code'),
        ];
    }

    protected function collectServiceDataInteractively()
    {
        $this->info("Enter service information:");

        return [
            'client_id' => $this->ask('Client ID (required)'),
            'service_type' => $this->ask('Service Type (required)'),
            'service_start_date' => $this->ask('Service Start Date (YYYY-MM-DD, required)'),
            'service_end_date' => $this->ask('Service End Date (YYYY-MM-DD)'),
            'service_outcome' => $this->ask('Service Outcome'),
            'service_location' => $this->ask('Service Location'),
            'service_provider' => $this->ask('Service Provider'),
            'funding_source' => $this->ask('Funding Source'),
            'service_units' => $this->ask('Service Units (number)'),
        ];
    }

    protected function collectServiceDataFromOptions()
    {
        return [
            'client_id' => $this->option('client-id'),
            'service_type' => $this->option('service-type'),
            'service_start_date' => $this->option('service-start-date'),
            'service_end_date' => $this->option('service-end-date'),
        ];
    }

    protected function parseCsvFile($filePath)
    {
        $clientDataArray = [];
        $handle = fopen($filePath, 'r');

        // Skip header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            $clientDataArray[] = [
                'client_id' => $data[0] ?? null,
                'first_name' => $data[1] ?? null,
                'last_name' => $data[2] ?? null,
                'date_of_birth' => $data[3] ?? null,
                'gender' => $data[4] ?? null,
                'indigenous_status' => $data[5] ?? null,
                'country_of_birth' => $data[6] ?? null,
                'postal_code' => $data[7] ?? null,
                'primary_language' => $data[8] ?? null,
                'interpreter_required' => $data[9] === 'true' ? true : false,
                'disability_flag' => $data[10] === 'true' ? true : false,
                'client_type' => $data[11] ?? null,
            ];
        }

        fclose($handle);
        return $clientDataArray;
    }

    protected function getClientData()
    {
        $this->info("Retrieving client data...");

        $filters = $this->buildFiltersFromOptions();
        $result = $this->dataExchangeService->getClientData($filters);

        return $this->handleDataOutput($result, 'client_data');
    }

    protected function getClientById()
    {
        $clientId = $this->option('client-id');

        if (!$clientId) {
            $clientId = $this->ask("Enter Client ID");
        }

        if (!$clientId) {
            $this->error("Client ID is required");
            return 1;
        }

        $this->info("Retrieving client: {$clientId}");

        $result = $this->dataExchangeService->getClientById($clientId);

        return $this->handleDataOutput($result, "client_{$clientId}");
    }

    protected function exportData()
    {
        $resourceType = $this->option('resource');

        if (!$resourceType) {
            $resources = $this->dataExchangeService->getAvailableResources();
            $resourceType = $this->choice('Select resource type', array_keys($resources));
        }

        if (!$resourceType) {
            $this->error("Resource type is required");
            return 1;
        }

        $format = $this->option('format') ?? $this->choice('Select format', ['json', 'xml', 'csv']);
        $filters = $this->buildFiltersFromOptions();

        $this->info("Exporting {$resourceType} data in {$format} format...");

        $result = $this->dataExchangeService->exportData($resourceType, $filters, $format);

        return $this->handleDataOutput($result, "{$resourceType}_export", $format);
    }

    protected function buildFiltersFromOptions()
    {
        $filters = [];

        $filterOptions = [
            'client-id' => 'client_id',
            'first-name' => 'first_name',
            'last-name' => 'last_name',
            'gender' => 'gender',
            'postal-code' => 'postal_code',
            'service-type' => 'service_type',
            'service-start-date' => 'service_start_date',
            'service-end-date' => 'service_end_date',
            'date-from' => 'date_from',
            'date-to' => 'date_to'
        ];

        foreach ($filterOptions as $option => $field) {
            if ($this->option($option)) {
                $filters[$field] = $this->option($option);
            }
        }

        return $filters;
    }

    protected function buildReportParameters()
    {
        return [
            'date_from' => $this->option('date-from'),
            'date_to' => $this->option('date-to'),
            'include_details' => $this->confirm('Include detailed information?', false),
            'filters' => $this->buildFiltersFromOptions()
        ];
    }

    protected function handleDataOutput($data, $filename, $format = null)
    {
        $format = $format ?? $this->option('format') ?? 'json';
        $outputFile = $this->option('output');

        // Convert data to specified format
        $convertedData = $this->dataExchangeService->convertDataFormat($data, $format);

        if ($outputFile) {
            // Save to file
            $fullPath = $outputFile;
            if (pathinfo($outputFile, PATHINFO_EXTENSION) === '') {
                $fullPath .= ".{$format}";
            }

            file_put_contents($fullPath, $convertedData);
            $this->info("✓ Data exported to: {$fullPath}");
        } else {
            // Output to console
            $this->info("✓ Data retrieved successfully!");

            if ($this->option('detailed')) {
                $this->line($convertedData);
            } else {
                // Show summary
                if (is_array($data)) {
                    $count = count($data);
                    $this->line("Records found: {$count}");
                    if ($count > 0) {
                        $this->line("Sample record keys: " . implode(', ', array_keys(reset($data))));
                    }
                } else {
                    $this->line("Response type: " . gettype($data));
                }

                $this->line("Use --detailed flag to see full output");
                $this->line("Use --output=filename to save to file");
            }
        }

        if ($this->option('detailed')) {
            $this->line("Request: " . $this->dataExchangeService->getLastRequest());
            $this->line("Response: " . $this->dataExchangeService->getLastResponse());
        }

        return 0;
    }

    protected function showHelp()
    {
        $this->info("Available actions:");
        $this->line("  test                 - Test SOAP connection");
        $this->line("  submit-client        - Submit client data");
        $this->line("  submit-service       - Submit service data");
        $this->line("  get-clients          - Retrieve client data with filters");
        $this->line("  get-services         - Retrieve service data with filters");
        $this->line("  get-client           - Get specific client by ID");
        $this->line("  export-data          - Export data in specified format");
        $this->line("  generate-report      - Generate and download reports");
        $this->line("  status               - Check submission status");
        $this->line("  functions            - Show available SOAP functions");
        $this->line("  bulk-submit          - Bulk submit from CSV file");
        $this->line('');
        $this->line("Data Retrieval Examples:");
        $this->line("  php artisan dex:client get-clients --format=json --output=clients.json");
        $this->line("  php artisan dex:client get-client --client-id=TEST001 --format=xml");
        $this->line("  php artisan dex:client get-services --service-type=Counselling --format=csv");
        $this->line("  php artisan dex:client export-data --resource=clients --format=csv --output=export.csv");
        $this->line("  php artisan dex:client generate-report --report=client_summary --format=json");
        $this->line('');
        $this->line("Filter Examples:");
        $this->line("  --client-id=TEST001 --first-name=John --last-name=Doe");
        $this->line("  --date-from=2024-01-01 --date-to=2024-12-31");
        $this->line("  --service-type=Counselling --postal-code=2000");
        $this->line('');
        $this->line("Data Submission Examples:");
        $this->line("  php artisan dex:client test");
        $this->line("  php artisan dex:client submit-client --interactive");
        $this->line("  php artisan dex:client submit-client --client-id=123 --first-name=John --last-name=Doe");
        $this->line("  php artisan dex:client bulk-submit --file=clients.csv");
        $this->line("  php artisan dex:client status --submission-id=12345");
    }
}
