@props([
    'title',
    'resourceType',
    'data' => [],
    'columns' => [],
    'showActions' => true,
    'emptyMessage' => 'No data available',
    'loading' => false,
])

<div class="card" x-data="resourceTableComponent">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $title }}</h5>
        <div class="d-flex align-items-center">
            @if (count($data) > 0)
                <span class="badge bg-info me-2">{{ count($data) }} {{ Str::plural('record', count($data)) }}</span>
            @endif
            <button class="btn btn-outline-primary btn-sm" x-on:click="refreshData()" x-bind:disabled="isRefreshing">
                <i class="fas fa-sync-alt" x-bind:class="{ 'fa-spin': isRefreshing }"></i>
                <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh'"></span>
            </button>
        </div>
    </div>
    <div class="card-body">
        @if ($loading)
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading {{ strtolower($title) }}...</p>
            </div>
        @elseif(empty($data))
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">{{ $emptyMessage }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                            @if ($showActions)
                                <th width="150">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data as $index => $item)
                            <tr>
                                @foreach ($columns as $column)
                                    <td>
                                        @php
                                            $value = data_get($item, $column['key']);
                                            if (isset($column['format'])) {
                                                if ($column['format'] === 'date' && $value) {
                                                    try {
                                                        $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                                                    } catch (\Exception $e) {
                                                        $value = $value; // Keep original if parsing fails
                                                    }
                                                } elseif ($column['format'] === 'boolean') {
                                                    $value = $value ? 'Yes' : 'No';
                                                } elseif ($column['format'] === 'badge' && isset($column['badges'])) {
                                                    $badgeClass = $column['badges'][$value] ?? 'bg-secondary';
                                                    echo "<span class='badge $badgeClass'>$value</span>";
                                                    continue;
                                                } elseif (isset($column['callback'])) {
                                                    $value = call_user_func($column['callback'], $value, $item);
                                                }
                                            }
                                            if (strlen($value) > 50) {
                                                echo '<span title="' .
                                                    htmlspecialchars($value) .
                                                    '">' .
                                                    htmlspecialchars(substr($value, 0, 50)) .
                                                    '...</span>';
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                        @endphp
                                    </td>
                                @endforeach
                                @if ($showActions)
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                x-on:click="viewResource('{{ $resourceType }}', {{ $index }})"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @if ($resourceType === 'case')
                                                <button type="button" class="btn btn-outline-info btn-sm"
                                                    x-on:click="viewCaseSessions('{{ $resourceType }}', {{ $index }})"
                                                    title="View Sessions">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-warning btn-sm"
                                                x-on:click="showUpdateForm('{{ $resourceType }}', {{ $index }})"
                                                title="Update">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                x-on:click="confirmDelete('{{ $resourceType }}', {{ $index }})"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Modals for actions -->
@if ($showActions)
    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update <span id="updateResourceType"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="updateModalBody">
                    <div id="updateErrorAlert" class="alert alert-danger d-none" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Update Failed:</strong>
                        <div id="updateErrorMessage"></div>
                    </div>
                    <div id="updateModalContent">
                        <!-- Update form will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View <span id="viewResourceType"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <div id="viewModalContent">
                        <!-- Resource details will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="deleteErrorAlert" class="alert alert-danger d-none" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Delete Failed:</strong>
                        <div id="deleteErrorMessage"></div>
                    </div>
                    <div id="deleteConfirmationText">
                        <p>Are you sure you want to delete this <span id="deleteResourceType"></span>?</p>
                        <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-1"></i>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function resourceTable(preloadedData) {
                return {
                    isRefreshing: false,
                    data: preloadedData || [],

                    refreshData() {
                        this.isRefreshing = true;
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    },

                    viewResource(resourceType, itemIndex) {
                        const item = this.data[itemIndex];
                        if (!item) {
                            this.showNotification('Resource data not found. Please refresh the page and try again.', 'error');
                            return;
                        }

                        const resourceId = this.getResourceId(resourceType, item);

                        document.getElementById('viewResourceType').textContent = resourceType;
                        document.getElementById('viewModalContent').innerHTML =
                            '<div class="text-center py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading resource details...</p></div>';

                        const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
                        viewModal.show();

                        this.fetchResourceData(resourceType, resourceId, item).then(freshData => {
                            console.log(`Fresh ${resourceType} data:`, freshData);
                            this.generateViewContent(resourceType, freshData, itemIndex);
                        }).catch(error => {
                            document.getElementById('viewModalContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error loading resource:</strong> ${error}
                            </div>
                        `;
                        });
                    },

                    viewCaseSessions(resourceType, itemIndex) {
                        const item = this.data[itemIndex];
                        if (!item) {
                            this.showNotification('Resource data not found. Please refresh the page and try again.', 'error');
                            return;
                        }

                        const caseId = this.getResourceId('case', item);
                        if (!caseId) {
                            this.showNotification('Case ID not found. Unable to view sessions.', 'error');
                            return;
                        }

                        // Navigate to the nested sessions page
                        window.location.href = `/data-exchange/cases/${caseId}/sessions`;
                    },

                    showUpdateForm(resourceType, itemIndex) {
                        const item = this.data[itemIndex];
                        if (!item) {
                            this.showNotification('Resource data not found. Please refresh the page and try again.', 'error');
                            return;
                        }

                        const resourceId = this.getResourceId(resourceType, item);

                        document.getElementById('updateResourceType').textContent = resourceType;

                        // Reset error state
                        document.getElementById('updateErrorAlert').classList.add('d-none');
                        document.getElementById('updateModalContent').innerHTML =
                            '<div class="text-center py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading update form...</p></div>';

                        const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
                        updateModal.show();

                        this.fetchResourceData(resourceType, resourceId, item).then(freshData => {
                            console.log(`Fresh ${resourceType} data for update:`, freshData);
                            this.generateUpdateForm(resourceType, itemIndex, freshData);
                        }).catch(error => {
                            document.getElementById('updateModalContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error loading resource:</strong> ${error}
                            </div>
                        `;
                        });
                    },

                    confirmDelete(resourceType, itemIndex) {
                        const item = this.data[itemIndex];
                        if (!item) {
                            this.showNotification('Resource data not found. Please refresh the page and try again.', 'error');
                            return;
                        }

                        const resourceId = this.getResourceId(resourceType, item);

                        // Reset modal state
                        document.getElementById('deleteResourceType').textContent = resourceType;
                        document.getElementById('deleteErrorAlert').classList.add('d-none');
                        document.getElementById('deleteConfirmationText').classList.remove('d-none');

                        const confirmBtn = document.getElementById('confirmDeleteBtn');
                        confirmBtn.onclick = () => this.handleDelete(resourceType, resourceId, itemIndex);

                        new bootstrap.Modal(document.getElementById('deleteModal')).show();
                    },

                    handleDelete(resourceType, resourceId, itemIndex) {
                        const confirmBtn = document.getElementById('confirmDeleteBtn');
                        const originalHtml = confirmBtn.innerHTML;

                        // Show loading state
                        confirmBtn.disabled = true;
                        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';

                        // Build API URL
                        let deleteUrl;
                        if (resourceType === 'session') {
                            const caseId = this.data[itemIndex]?.CaseId;
                            if (!caseId) {
                                this.showNotification('Case ID is required for session deletion', 'error');
                                confirmBtn.disabled = false;
                                confirmBtn.innerHTML = originalHtml;
                                return;
                            }
                            deleteUrl = `/data-exchange/api/cases/${caseId}/sessions/${resourceId}`;
                        } else {
                            deleteUrl = `/data-exchange/api/${resourceType}s/${resourceId}`;
                        }

                        // Make API call
                        fetch(deleteUrl, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                        'content'),
                                    'Accept': 'application/json'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.showNotification(data.message, 'success');
                                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                                    // Refresh page after successful deletion
                                    setTimeout(() => window.location.reload(), 1500);
                                } else {
                                    let errorMessage = data.message || 'Delete failed';

                                    if (data.soap_response) {
                                        // SOAP error - message already contains the formatted error
                                        console.log('SOAP Error Response:', data.soap_response);
                                    }

                                    // Display error in the modal
                                    document.getElementById('deleteErrorMessage').textContent = errorMessage;
                                    document.getElementById('deleteErrorAlert').classList.remove('d-none');
                                    document.getElementById('deleteConfirmationText').classList.add('d-none');

                                    // Change delete button to "Try Again"
                                    confirmBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Try Again';
                                    confirmBtn.classList.remove('btn-danger');
                                    confirmBtn.classList.add('btn-warning');
                                }
                            })
                            .catch(error => {
                                console.error('Delete error:', error);
                                this.showNotification('Network error occurred while deleting. Please try again.', 'error');
                            })
                            .finally(() => {
                                confirmBtn.disabled = false;
                                confirmBtn.innerHTML = originalHtml;
                            });
                    },


                    showNotification(message, type = 'info', duration = 5000) {
                        const existingNotification = document.getElementById('notification-toast');
                        if (existingNotification) {
                            existingNotification.remove();
                        }

                        const notification = document.createElement('div');
                        notification.id = 'notification-toast';
                        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                        notification.style.cssText =
                            'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
                        notification.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;

                        document.body.appendChild(notification);

                        setTimeout(() => {
                            if (notification && notification.parentNode) {
                                notification.remove();
                            }
                        }, duration);
                    },

                    generateViewContent(resourceType, resourceData, itemIndex) {
                        const modalContent = document.getElementById('viewModalContent');

                        let content = '<div class="row">';

                        // Generate view content based on resource type with correct API field names
                        if (resourceType === 'client') {
                            content += this.generateClientViewContent(resourceData);
                        } else if (resourceType === 'case') {
                            content += this.generateCaseViewContent(resourceData);
                        } else if (resourceType === 'session') {
                            content += this.generateSessionViewContent(resourceData);
                        } else {
                            content +=
                                '<div class="col-12 text-center"><p class="text-muted">Resource details not available</p></div>';
                        }

                        content += '</div>';
                        content += `
                        <div class="mt-3 d-flex justify-content-end">
                            <button type="button" class="btn btn-warning me-2" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); resourceTableComponent.showUpdateForm('${resourceType}', ${itemIndex})">
                                <i class="fas fa-edit"></i> Update
                            </button>
                            <button type="button" class="btn btn-danger" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); resourceTableComponent.confirmDelete('${resourceType}', ${itemIndex})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    `;

                        modalContent.innerHTML = content;
                    },

                    generateClientViewContent(data) {
                        return `
                        <div class="col-md-6 mb-3">
                            <strong>Client ID:</strong><br>
                            <span class="text-muted">${data.ClientId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Name:</strong><br>
                            <span class="text-muted">${(data.GivenName || '') + ' ' + (data.FamilyName || '')}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Date of Birth:</strong><br>
                            <span class="text-muted">${data.BirthDate ? new Date(data.BirthDate).toLocaleDateString() : 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Gender:</strong><br>
                            <span class="text-muted">${data.GenderCode || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>State:</strong><br>
                            <span class="text-muted">${data.ResidentialAddress?.State || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Postcode:</strong><br>
                            <span class="text-muted">${data.ResidentialAddress?.Postcode || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Suburb:</strong><br>
                            <span class="text-muted">${data.ResidentialAddress?.Suburb || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Country of Birth:</strong><br>
                            <span class="text-muted">${data.CountryOfBirthCode || 'N/A'}</span>
                        </div>
                    `;
                    },

                    generateCaseViewContent(data) {
                        // Extract data from the correct API response structure
                        const caseId = data.CaseDetail?.CaseId || data.CaseId || 'N/A';
                        const clientId = data.Clients?.CaseClient?.ClientId || data.ClientId || 'N/A';
                        const outletActivityId = data.CaseDetail?.OutletActivityId || data.OutletActivityId || 'N/A';
                        const referralSourceCode = data.Clients?.CaseClient?.ReferralSourceCode || data.ReferralSourceCode ||
                            'N/A';
                        const attendanceProfileCode = data.CaseDetail?.ClientAttendanceProfileCode || data
                            .ClientAttendanceProfileCode || 'N/A';
                        const createdDateTime = data.CreatedDateTime || 'N/A';
                        const outletName = data.OutletName || 'N/A';
                        const programActivityName = data.ProgramActivityName || 'N/A';
                        const totalClients = data.CaseDetail?.TotalNumberOfUnidentifiedClients || data
                            .TotalNumberOfUnidentifiedClients || 'N/A';
                        const exitReasonCode = data.Clients?.CaseClient?.ExitReasonCode || data.ExitReasonCode || 'N/A';
                        const sessionIds = data.Sessions?.SessionId || [];
                        const sessionCount = Array.isArray(sessionIds) ? sessionIds.length : (sessionIds ? 1 : 0);

                        return `
                        <div class="col-md-6 mb-3">
                            <strong>Case ID:</strong><br>
                            <span class="text-muted">${caseId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Client ID:</strong><br>
                            <span class="text-muted">${clientId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Outlet Name:</strong><br>
                            <span class="text-muted">${outletName}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Program Activity:</strong><br>
                            <span class="text-muted">${programActivityName}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Outlet Activity ID:</strong><br>
                            <span class="text-muted">${outletActivityId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Total Clients:</strong><br>
                            <span class="text-muted">${totalClients}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Referral Source:</strong><br>
                            <span class="text-muted">${referralSourceCode}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Attendance Profile:</strong><br>
                            <span class="text-muted">${attendanceProfileCode}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Exit Reason:</strong><br>
                            <span class="text-muted">${exitReasonCode}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Sessions Count:</strong><br>
                            <span class="text-muted">${sessionCount}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Created Date:</strong><br>
                            <span class="text-muted">${createdDateTime ? new Date(createdDateTime).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    `;
                    },

                    generateSessionViewContent(data) {
                        // Extract data from the correct API response structure
                        const sessionId = data.SessionDetails?.SessionId || data.SessionId || 'N/A';
                        const caseId = data.CaseId || 'N/A';
                        const serviceTypeId = data.SessionDetails?.ServiceTypeId || data.ServiceTypeId || 'N/A';
                        const sessionDate = data.SessionDetails?.SessionDate || data.SessionDate || 'N/A';
                        const time = data.SessionDetails?.Time || data.Time || 'N/A';
                        const topicCode = data.SessionDetails?.TopicCode || data.TopicCode || 'N/A';
                        const totalClients = data.SessionDetails?.TotalNumberOfUnidentifiedClients || data
                            .TotalNumberOfUnidentifiedClients || 'N/A';
                        const interpreterPresent = data.SessionDetails?.InterpreterPresent || data.InterpreterPresent || false;
                        const serviceSettingCode = data.SessionDetails?.ServiceSettingCode || data.ServiceSettingCode || 'N/A';
                        const createdDateTime = data.CreatedDateTime || 'N/A';
                        const clientId = data.Clients?.SessionClient?.ClientId || data.ClientId || 'N/A';
                        const participationCode = data.Clients?.SessionClient?.ParticipationCode || data.ParticipationCode ||
                            'N/A';
                        const quantity = data.SessionDetails?.Quantity || data.Quantity || 'N/A';
                        const totalCost = data.SessionDetails?.TotalCost || data.TotalCost || 'N/A';

                        return `
                        <div class="col-md-6 mb-3">
                            <strong>Session ID:</strong><br>
                            <span class="text-muted">${sessionId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Case ID:</strong><br>
                            <span class="text-muted">${caseId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Client ID:</strong><br>
                            <span class="text-muted">${clientId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Participation Code:</strong><br>
                            <span class="text-muted">${participationCode}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Session Date:</strong><br>
                            <span class="text-muted">${sessionDate ? new Date(sessionDate).toLocaleDateString() : 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Service Type ID:</strong><br>
                            <span class="text-muted">${serviceTypeId}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Service Setting:</strong><br>
                            <span class="text-muted">${serviceSettingCode}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Total Clients:</strong><br>
                            <span class="text-muted">${totalClients}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Topic Code:</strong><br>
                            <span class="text-muted">${topicCode}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Duration/Time:</strong><br>
                            <span class="text-muted">${time}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Quantity:</strong><br>
                            <span class="text-muted">${quantity}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Total Cost:</strong><br>
                            <span class="text-muted">${totalCost}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Interpreter Present:</strong><br>
                            <span class="text-muted">${interpreterPresent ? 'Yes' : 'No'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Created Date:</strong><br>
                            <span class="text-muted">${createdDateTime ? new Date(createdDateTime).toLocaleDateString() : 'N/A'}</span>
                        </div>
                    `;
                    },

                    generateUpdateForm(resourceType, itemIndex, resourceData) {
                        const modalContent = document.getElementById('updateModalContent');

                        // Get the resource ID for API calls
                        const resourceId = this.getResourceId(resourceType, resourceData);

                        let formHtml =
                            `<form id="updateForm" onsubmit="window.resourceTableComponent.handleUpdate(event, '${resourceType}', '${resourceId}', ${itemIndex})">`;

                        if (resourceType === 'client') {
                            formHtml += this.generateClientUpdateFields(resourceData);
                        } else if (resourceType === 'case') {
                            formHtml += this.generateCaseUpdateFields(resourceData);
                        } else if (resourceType === 'session') {
                            formHtml += this.generateSessionUpdateFields(resourceData);
                        } else {
                            formHtml +=
                                '<div class="text-center"><p class="text-muted">Update form not available for this resource type</p></div>';
                        }

                        formHtml += `
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning" id="updateSubmitBtn">
                                <i class="fas fa-save me-1"></i>Update ${resourceType}
                            </button>
                        </div>
                    </form>`;

                        modalContent.innerHTML = formHtml;
                    },

                    getResourceId(resourceType, resourceData) {
                        // Extract the appropriate ID based on resource type
                        if (resourceType === 'client') {
                            return resourceData.ClientId;
                        } else if (resourceType === 'case') {
                            return resourceData.CaseDetail?.CaseId || resourceData.CaseId;
                        } else if (resourceType === 'session') {
                            return resourceData.SessionDetails?.SessionId || resourceData.SessionDetail?.SessionId ||
                                resourceData.SessionId;
                        }
                        return null;
                    },

                    fetchResourceData(resourceType, resourceId, originalItem) {
                        return new Promise((resolve, reject) => {
                            let url;

                            // Use nested API routes for sessions
                            if (resourceType === 'session') {
                                const caseId = originalItem.CaseId;
                                if (!caseId) {
                                    reject('Case ID is required for session operations');
                                    return;
                                }
                                url = `/data-exchange/api/cases/${caseId}/sessions/${resourceId}`;
                            } else {
                                url = `/data-exchange/api/${resourceType}s/${resourceId}`;
                            }

                            fetch(url, {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json'
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Extract the actual resource data from the SOAP response structure
                                        let resourceData;

                                        if (resourceType === 'client') {
                                            resourceData = data.data.Client || data.data;
                                        } else if (resourceType === 'case') {
                                            // Pass the full Case structure to preserve all nested data
                                            resourceData = data.data.Case || data.data;
                                        } else if (resourceType === 'session') {
                                            // Pass the full Session structure to preserve all nested data
                                            resourceData = data.data.Session || data.data;
                                        } else {
                                            // Fallback to the raw data structure
                                            resourceData = data.data;
                                        }

                                        resolve(resourceData);
                                    } else {
                                        reject(data.message || 'Failed to load resource data');
                                    }
                                })
                                .catch(error => {
                                    console.error('Fetch resource error:', error);
                                    reject('Network error occurred while loading resource');
                                });
                        });
                    },

                    handleUpdate(event, resourceType, resourceId, itemIndex) {
                        event.preventDefault();

                        const formData = new FormData(event.target);
                        const submitBtn = document.getElementById('updateSubmitBtn');
                        const originalHtml = submitBtn.innerHTML;

                        // Show loading state
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';

                        // Convert FormData to JSON
                        const data = {};
                        for (let [key, value] of formData.entries()) {
                            data[key] = value;
                        }

                        // Build API URL
                        let updateUrl;
                        if (resourceType === 'session') {
                            const caseId = this.data[itemIndex]?.CaseId;
                            if (!caseId) {
                                this.showNotification('Case ID is required for session updates', 'error');
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalHtml;
                                return;
                            }
                            updateUrl = `/data-exchange/api/cases/${caseId}/sessions/${resourceId}`;
                            // No need to add case_id to data anymore since it's in the URL
                        } else {
                            updateUrl = `/data-exchange/api/${resourceType}s/${resourceId}`;
                        }

                        // Make API call
                        fetch(updateUrl, {
                                method: 'PUT',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                        'content'),
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(data)
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.showNotification(data.message, 'success');
                                    bootstrap.Modal.getInstance(document.getElementById('updateModal')).hide();
                                    // Refresh page after successful update
                                    setTimeout(() => window.location.reload(), 1500);
                                } else {
                                    let errorMessage = data.message || 'Update failed';

                                    if (data.errors) {
                                        // Display validation errors
                                        const errorMessages = Object.values(data.errors).flat().join(', ');
                                        errorMessage = 'Validation errors: ' + errorMessages;
                                    } else if (data.soap_response) {
                                        // SOAP error - message already contains the formatted error
                                        console.log('SOAP Error Response:', data.soap_response);
                                    }

                                    // Display error in the modal
                                    document.getElementById('updateErrorMessage').textContent = errorMessage;
                                    document.getElementById('updateErrorAlert').classList.remove('d-none');

                                    // Scroll to top of modal to show error
                                    document.getElementById('updateModalBody').scrollTop = 0;
                                }
                            })
                            .catch(error => {
                                console.error('Update error:', error);
                                this.showNotification('Network error occurred while updating. Please try again.', 'error');
                            })
                            .finally(() => {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalHtml;
                            });
                    },

                    generateClientUpdateFields(data) {
                        return `
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_given_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="update_given_name" name="given_name" value="${data.GivenName || ''}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="update_family_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="update_family_name" name="family_name" value="${data.FamilyName || ''}" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_birth_date" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="update_birth_date" name="birth_date"
                                       value="${data.BirthDate ? data.BirthDate.split('T')[0] : ''}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="update_gender_code" class="form-label">Gender</label>
                                <select class="form-select" id="update_gender_code" name="gender_code" required>
                                    <option value="">Select Gender</option>
                                    <option value="MALE" ${data.GenderCode === 'MALE' ? 'selected' : ''}>Male</option>
                                    <option value="FEMALE" ${data.GenderCode === 'FEMALE' ? 'selected' : ''}>Female</option>
                                    <option value="OTHER" ${data.GenderCode === 'OTHER' ? 'selected' : ''}>Other</option>
                                    <option value="NOTSTATED" ${data.GenderCode === 'NOTSTATED' ? 'selected' : ''}>Not stated</option>
                                </select>
                            </div>
                        </div>
                    `;
                    },

                    generateCaseUpdateFields(data) {
                        const referralSourceCode = data.ReferralSourceCode || data.Clients?.CaseClient?.ReferralSourceCode ||
                            '';
                        const attendanceProfileCode = data.ClientAttendanceProfileCode || data.CaseDetail
                            ?.ClientAttendanceProfileCode || '';

                        return `
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_referral_source_code" class="form-label">Referral Source</label>
                                <select class="form-select" id="update_referral_source_code" name="referral_source_code" required>
                                    <option value="">Select Referral Source</option>
                                    <option value="COMMUNITY" ${referralSourceCode === 'COMMUNITY' ? 'selected' : ''}>Community services agency</option>
                                    <option value="SELF" ${referralSourceCode === 'SELF' ? 'selected' : ''}>Self</option>
                                    <option value="FAMILY" ${referralSourceCode === 'FAMILY' ? 'selected' : ''}>Family</option>
                                    <option value="GP" ${referralSourceCode === 'GP' ? 'selected' : ''}>General Medical Practitioner</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="update_client_attendance_profile_code" class="form-label">Attendance Profile</label>
                                <select class="form-select" id="update_client_attendance_profile_code" name="client_attendance_profile_code">
                                    <option value="">Select Profile</option>
                                    <option value="FAMILY" ${attendanceProfileCode === 'FAMILY' ? 'selected' : ''}>Family</option>
                                    <option value="COMMEVENT" ${attendanceProfileCode === 'COMMEVENT' ? 'selected' : ''}>Community event</option>
                                    <option value="PSGROUP" ${attendanceProfileCode === 'PSGROUP' ? 'selected' : ''}>Peer support group</option>
                                    <option value="COUPLE" ${attendanceProfileCode === 'COUPLE' ? 'selected' : ''}>Couple</option>
                                    <option value="COHABITANTS" ${attendanceProfileCode === 'COHABITANTS' ? 'selected' : ''}>Cohabitants</option>
                                </select>
                            </div>
                        </div>
                    `;
                    },

                    generateSessionUpdateFields(data) {
                        // Handle both direct field access and nested SOAP response structures
                        const sessionDate = data.SessionDate || data.SessionDetails?.SessionDate || data.SessionDetail
                            ?.SessionDate || '';
                        const topicCode = data.TopicCode || data.SessionDetails?.TopicCode || data.SessionDetail?.TopicCode ||
                            '';
                        const time = data.Time || data.SessionDetails?.Time || data.SessionDetail?.Time || '';

                        return `
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_session_date" class="form-label">Session Date</label>
                                <input type="date" class="form-control" id="update_session_date" name="session_date"
                                       value="${sessionDate ? sessionDate.split('T')[0] : ''}" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_topic_code" class="form-label">Topic</label>
                                <input type="text" class="form-control" id="update_topic_code" name="topic_code" value="${topicCode}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="update_time" class="form-label">Duration/Time</label>
                                <input type="text" class="form-control" id="update_time" name="time" value="${time}"
                                       placeholder="e.g., 60 minutes">
                            </div>
                        </div>
                    `;
                    },

                };
            }

            // Initialize Alpine.js component
            document.addEventListener('alpine:init', () => {
                Alpine.data('resourceTableComponent', () => {
                    const component = resourceTable(@json($data));
                    // Make component globally accessible for onclick handlers
                    window.resourceTableComponent = component;
                    return component;
                });
            });
        </script>
    @endpush
@endif
