@extends('layouts.app')

@section('title', 'DSS Reference Data Explorer - DSS Data Exchange')

@section('content')
  <div class="row">
    <div class="col-12">
      <h1 class="mb-4">DSS Reference Data Explorer</h1>
      <p class="text-muted">Explore reference data codes available from the DSS GetReferenceData operation</p>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Reference Data Types</h5>
        </div>
        <div class="card-body">
          <form id="referenceDataForm">
            <div class="mb-3">
              <label for="referenceType" class="form-label">Select Reference Data Type</label>
              <select class="form-select" id="referenceType" name="referenceType">
                <option value="">-- Select a Reference Type --</option>
                <option value="All">All</option>
                <option value="AboriginalOrTorresStraitIslanderOrigin">Aboriginal Or Torres Strait Islander
                  Origin</option>
                <option value="AccommodationType">Accommodation Type</option>
                <option value="AgBusinessType">Ag Business Type</option>
                <option value="Ancestry">Ancestry</option>
                <option value="AssessedBy">Assessed By</option>
                <option value="AssessmentPhase">Assessment Phase</option>
                <option value="AttendanceProfile">Attendance Profile</option>
                <option value="Country">Country</option>
                <option value="Disability">Disability</option>
                <option value="DVACardStatus">DVA Card Status</option>
                <option value="EducationLevel">Education Level</option>
                <option value="EmploymentStatus">Employment Status</option>
                <option value="ExitReason">Exit Reason</option>
                <option value="ExternalReferralDestination">External Referral Destination</option>
                <option value="ExtraItem">Extra Item</option>
                <option value="Gender">Gender</option>
                <option value="HardshipType">Hardship Type</option>
                <option value="HomelessIndicator">Homeless Indicator</option>
                <option value="HouseholdComposition">Household Composition</option>
                <option value="IncomeFrequency">Income Frequency</option>
                <option value="Language">Language</option>
                <option value="MainSourceOfIncome">Main Source Of Income</option>
                <option value="MigrationVisaCategory">Migration Visa Category</option>
                <option value="MoneyBusinessCommunityEducationWorkshop">Money Business Community Education
                  Workshop</option>
                <option value="NDISEligibility">NDIS Eligibility</option>
                <option value="ParentingAgreement">Parenting Agreement</option>
                <option value="ParticipationType">Participation Type</option>
                <option value="PropertyAgreement">Property Agreement</option>
                <option value="ReasonForAssistance">Reason For Assistance</option>
                <option value="ReferralPurpose">Referral Purpose</option>
                <option value="ReferralSource">Referral Source</option>
                <option value="ReferralType">Referral Type</option>
                <option value="ScoreType">Score Type</option>
                <option value="Section60ICertificateType">Section 60I Certificate Type</option>
                <option value="ServiceSetting">Service Setting</option>
                <option value="State">State</option>
                <option value="Topic">Topic</option>
              </select>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary" id="fetchDataBtn">
                <i class="fas fa-search"></i> Fetch Reference Data
              </button>
            </div>
          </form>

          <div id="loading-status" class="mt-3" style="display: none;">
            <div class="alert alert-info">
              <i class="fas fa-spinner fa-spin"></i> Fetching reference data...
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header">
          <h6 class="mb-0">Quick Actions</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="fetchQuickData('Country')">
              <i class="fas fa-flag"></i> Countries
            </button>
            <button type="button" class="btn btn-outline-info btn-sm" onclick="fetchQuickData('Language')">
              <i class="fas fa-language"></i> Languages
            </button>
            <button type="button" class="btn btn-outline-warning btn-sm" onclick="fetchQuickData('Gender')">
              <i class="fas fa-user"></i> Gender Codes
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fetchQuickData('State')">
              <i class="fas fa-map"></i> States
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Reference Data Results</h5>
          <div>
            <button class="btn btn-outline-primary btn-sm" onclick="exportResults('json')" id="exportJsonBtn"
              style="display: none;">
              <i class="fas fa-download"></i> Export JSON
            </button>
            <button class="btn btn-outline-success btn-sm ms-2" onclick="exportResults('csv')" id="exportCsvBtn"
              style="display: none;">
              <i class="fas fa-download"></i> Export CSV
            </button>
          </div>
        </div>
        <div class="card-body">
          <div id="results-container">
            <div class="text-center text-muted py-5">
              <i class="fas fa-database fa-3x mb-3"></i>
              <p>Select a reference data type to view available codes</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    let currentReferenceData = null;

    document.getElementById('referenceDataForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const referenceType = document.getElementById('referenceType').value;
      if (referenceType) {
        fetchReferenceData(referenceType);
      }
    });

    function fetchQuickData(type) {
      document.getElementById('referenceType').value = type;
      fetchReferenceData(type);
    }

    function fetchReferenceData(referenceType) {
      const loadingStatus = document.getElementById('loading-status');
      const resultsContainer = document.getElementById('results-container');
      const fetchBtn = document.getElementById('fetchDataBtn');

      // Show loading state
      loadingStatus.style.display = 'block';
      fetchBtn.disabled = true;
      fetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';

      // Hide export buttons
      document.getElementById('exportJsonBtn').style.display = 'none';
      document.getElementById('exportCsvBtn').style.display = 'none';

      fetch(`{{ route('data-exchange.get-reference-data') }}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          body: JSON.stringify({
            reference_type: referenceType
          })
        })
        .then(response => response.json())
        .then(data => {
          loadingStatus.style.display = 'none';
          fetchBtn.disabled = false;
          fetchBtn.innerHTML = '<i class="fas fa-search"></i> Fetch Reference Data';

          if (data.success) {
            currentReferenceData = data.data;
            displayResults(referenceType, data.data);
            // Show export buttons
            document.getElementById('exportJsonBtn').style.display = 'inline-block';
            document.getElementById('exportCsvBtn').style.display = 'inline-block';
          } else {
            displayError(data.error || 'Failed to fetch reference data');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          loadingStatus.style.display = 'none';
          fetchBtn.disabled = false;
          fetchBtn.innerHTML = '<i class="fas fa-search"></i> Fetch Reference Data';
          displayError('Network error occurred while fetching reference data');
        });
    }

    function displayResults(referenceType, data) {
      const container = document.getElementById('results-container');

      let html = `
        <div class="mb-3">
            <h6 class="text-primary">Reference Type: ${referenceType}</h6>
            <small class="text-muted">Retrieved at ${new Date().toLocaleString()}</small>
        </div>
    `;

      if (data && typeof data === 'object') {
        // Try to parse different response formats
        let items = [];

        if (Array.isArray(data)) {
          items = data;
        } else if (data.ReferenceItems && Array.isArray(data.ReferenceItems)) {
          items = data.ReferenceItems;
        } else if (data.items && Array.isArray(data.items)) {
          items = data.items;
        } else {
          // Show raw data if we can't parse it
          html += `<div class="alert alert-info">
                <h6>Raw Response Data:</h6>
                <pre class="mb-0">${JSON.stringify(data, null, 2)}</pre>
            </div>`;
          container.innerHTML = html;
          return;
        }

        if (items.length > 0) {
          html += `
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Additional Info</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

          items.forEach(item => {
            const code = item.Code || item.code || item.id || 'N/A';
            const description = item.Description || item.description || item.name || item.label ||
              'N/A';
            const additional = item.AdditionalInfo || item.additional || item.notes || '';

            html += `
                    <tr>
                        <td><code>${code}</code></td>
                        <td>${description}</td>
                        <td><small class="text-muted">${additional}</small></td>
                    </tr>
                `;
          });

          html += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <small class="text-muted">${items.length} items found</small>
                </div>
            `;
        } else {
          html += '<div class="alert alert-warning">No reference data items found for this type.</div>';
        }
      } else {
        html += '<div class="alert alert-warning">Unexpected response format received.</div>';
      }

      container.innerHTML = html;
    }

    function displayError(error) {
      const container = document.getElementById('results-container');
      container.innerHTML = `
        <div class="alert alert-danger">
            <h6><i class="fas fa-exclamation-triangle"></i> Error</h6>
            <p class="mb-0">${error}</p>
        </div>
    `;
    }

    function exportResults(format) {
      if (!currentReferenceData) {
        showAlert('No data to export', 'warning');
        return;
      }

      const referenceType = document.getElementById('referenceType').value;
      const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');

      if (format === 'json') {
        const jsonContent = JSON.stringify(currentReferenceData, null, 2);
        downloadFile(jsonContent, `reference_data_${referenceType}_${timestamp}.json`, 'application/json');
      } else if (format === 'csv') {
        const csvContent = convertReferenceDataToCSV(currentReferenceData);
        downloadFile(csvContent, `reference_data_${referenceType}_${timestamp}.csv`, 'text/csv');
      }
    }

    function convertReferenceDataToCSV(data) {
      let items = [];

      if (Array.isArray(data)) {
        items = data;
      } else if (data.ReferenceItems && Array.isArray(data.ReferenceItems)) {
        items = data.ReferenceItems;
      } else if (data.items && Array.isArray(data.items)) {
        items = data.items;
      }

      if (items.length === 0) {
        return 'No data available';
      }

      const headers = ['Code', 'Description', 'Additional Info'];
      const csvRows = [headers.join(',')];

      items.forEach(item => {
        const code = (item.Code || item.code || item.id || '').toString().replace(/"/g, '""');
        const description = (item.Description || item.description || item.name || item.label || '')
          .toString().replace(/"/g, '""');
        const additional = (item.AdditionalInfo || item.additional || item.notes || '').toString().replace(
          /"/g, '""');

        csvRows.push(`"${code}","${description}","${additional}"`);
      });

      return csvRows.join('\n');
    }

    function downloadFile(content, filename, contentType) {
      const blob = new Blob([content], {
        type: contentType
      });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    }
  </script>
@endpush
