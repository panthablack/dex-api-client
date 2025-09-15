# DSS Data Exchange SOAP Client

A Laravel-based SOAP client for connecting to the Australian Government's Department of Social Services (DSS) Data Exchange System, based on the Web Services Technical Specifications Version 5 (March 2024).

## Features

- **Web Interface**: User-friendly web interface for submitting client and service data
- **Command Line Interface**: Comprehensive CLI for automation and bulk operations
- **SOAP Integration**: Native PHP SOAP client with full error handling and logging
- **Bulk Upload**: CSV file processing for bulk data submissions
- **Docker Support**: Complete Docker environment with SOAP extension enabled
- **Debugging**: Full request/response logging and debugging capabilities

## Installation

### Option 1: Docker (Recommended)

1. Configure environment variables:

   ```bash
   cp .env.example .env
   # Edit .env file with your DSS credentials
   ```

2. Build and run with Docker:

   ```bash
   docker-compose up --build -d
   ```

3. Access the application at `http://localhost:8000`

### Option 2: Local Installation

1. Ensure PHP 8.3+ with SOAP extension is installed
2. Install dependencies:

   ```bash
   composer install
   ```

3. Configure environment:

   ```bash
   cp .env.example .env
   php artisan key:generate
   # Edit .env with your DSS credentials
   ```

4. Run the development server:
   ```bash
   php artisan serve
   ```

## Configuration

Update the following environment variables in your `.env` file:

```env
# DSS Data Exchange SOAP Configuration
DSS_WSDL_URL=https://dex.dss.gov.au/webservice?wsdl
DSS_USERNAME=your_username_here
DSS_PASSWORD=your_password_here
DSS_ORGANISATION_ID=your_org_id_here
DSS_LOGGING_ENABLED=true
DSS_LOG_CHANNEL=daily
```

## Web Interface

Navigate to the web interface to access:

- **Dashboard** (`/`): Connection testing and system overview
- **Submit Data**: Client forms, case forms, session forms, and bulk upload capabilities
  - **Client Form** (`/data-exchange/client-form`): Submit individual client data
  - **Case Form** (`/data-exchange/case-form`): Submit case management data
  - **Session Form** (`/data-exchange/session-form`): Submit session/service delivery data
  - **Bulk Upload** (`/data-exchange/bulk-form`): Upload CSV files for bulk processing
- **Retrieve Data** (`/data-exchange/retrieve-form`): Retrieve and download data in multiple formats
  - Filter by client ID, name, service type, date ranges
  - Export as JSON, XML, or CSV
  - Generate reports with custom parameters
  - Download directly or preview in browser

## Command Line Interface

### Connection Testing

```bash
php artisan dex:client test
php artisan dex:client test --detailed
```

### Data Submission

```bash
# Interactive client submission
php artisan dex:client submit-client --interactive

# Submit with parameters
php artisan dex:client submit-client \
  --client-id=TEST001 \
  --first-name=John \
  --last-name=Doe \
  --date-of-birth=1990-01-15 \
  --gender=M

# Submit service data
php artisan dex:client submit-service --interactive

# Bulk upload from CSV
php artisan dex:client bulk-submit --file=storage/app/sample_clients.csv

# Check submission status
php artisan dex:client status --submission-id=12345
```

### Data Retrieval & Export

```bash
# Get all clients as JSON
php artisan dex:client get-clients --format=json

# Get specific client as XML
php artisan dex:client get-client --client-id=TEST001 --format=xml

# Get services with filters, save as CSV
php artisan dex:client get-services \
  --service-type=Counselling \
  --date-from=2024-01-01 \
  --format=csv \
  --output=services.csv

# Export data with advanced filtering
php artisan dex:client export-data \
  --resource=clients \
  --format=json \
  --first-name=John \
  --output=john_clients.json

# Generate reports
php artisan dex:client generate-report \
  --report=client_summary \
  --format=csv \
  --date-from=2024-01-01 \
  --date-to=2024-12-31 \
  --output=summary_report.csv
```

### Available Resources & Reports

```bash
# Show available SOAP functions
php artisan dex:client functions

# Get help with all commands
php artisan dex:client --help
```

### Format Options

- **JSON**: `--format=json` - JavaScript Object Notation
- **XML**: `--format=xml` - Extensible Markup Language
- **CSV**: `--format=csv` - Comma Separated Values

### Filter Options

- `--client-id=ID` - Specific client identifier
- `--first-name=Name` - Client first name
- `--last-name=Name` - Client last name
- `--gender=M|F|X|9` - Gender filter
- `--postal-code=Code` - Postal code filter
- `--service-type=Type` - Service type filter
- `--date-from=YYYY-MM-DD` - Start date filter
- `--date-to=YYYY-MM-DD` - End date filter
- `--output=filename` - Save to file (auto-detects extension)
- `--detailed` - Show full SOAP request/response

## CSV Format for Bulk Upload

```csv
client_id,first_name,last_name,date_of_birth,gender,indigenous_status,country_of_birth,postal_code,primary_language,interpreter_required,disability_flag,client_type
TEST001,John,Doe,1990-01-15,M,N,Australia,2000,English,false,false,Individual
```

## Usage Examples

1. **Start the application**:

   ```bash
   docker-compose up -d
   ```

2. **Test SOAP connection**:

   ```bash
   php artisan dex:client test --verbose
   ```

3. **Submit client data via CLI**:

   ```bash
   php artisan dex:client submit-client --interactive
   ```

4. **Access web interface**:
   Open `http://localhost:8000` in your browser

## Project Structure

```
├── app/
│   ├── Console/Commands/DataExchangeCommand.php
│   ├── Http/Controllers/DataExchangeController.php
│   └── Services/
│       ├── DataExchangeService.php
│       └── SoapClientService.php
├── config/soap.php
├── resources/views/data-exchange/
├── storage/app/sample_clients.csv
├── docker-compose.yml
└── Dockerfile
```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
