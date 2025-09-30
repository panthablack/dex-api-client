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

## Usage Examples

```bash
docker-compose up -d
```
