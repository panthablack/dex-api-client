@props([
    'title',
    'resourceType',
    'data' => [],
    'columns' => [],
    'showActions' => true,
    'emptyMessage' => 'No data available',
    'loading' => false
])

<div class="card" x-data="resourceTable()">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $title }}</h5>
        <div class="d-flex align-items-center">
            @if(count($data) > 0)
                <span class="badge bg-info me-2">{{ count($data) }} {{ Str::plural('record', count($data)) }}</span>
            @endif
            <button class="btn btn-outline-primary btn-sm" 
                    x-on:click="refreshData()" 
                    x-bind:disabled="isRefreshing">
                <i class="fas fa-sync-alt" x-bind:class="{ 'fa-spin': isRefreshing }"></i>
                <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh'"></span>
            </button>
        </div>
    </div>
    <div class="card-body">
        @if($loading)
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
                            @foreach($columns as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                            @if($showActions)
                                <th width="150">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data as $item)
                            <tr>
                                @foreach($columns as $column)
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
                                                echo '<span title="' . htmlspecialchars($value) . '">' . 
                                                     htmlspecialchars(substr($value, 0, 50)) . '...</span>';
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                        @endphp
                                    </td>
                                @endforeach
                                @if($showActions)
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" 
                                                class="btn btn-outline-primary btn-sm" 
                                                x-on:click="viewResource('{{ $resourceType }}', '{{ data_get($item, $columns[0]['key']) }}')"
                                                x-bind:disabled="actionLoading === 'view-{{ data_get($item, $columns[0]['key']) }}'"
                                                title="View Details">
                                                <i class="fas" 
                                                   x-bind:class="actionLoading === 'view-{{ data_get($item, $columns[0]['key']) }}' ? 'fa-spinner fa-spin' : 'fa-eye'"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-outline-warning btn-sm" 
                                                x-on:click="showUpdateForm('{{ $resourceType }}', '{{ data_get($item, $columns[0]['key']) }}')"
                                                x-bind:disabled="actionLoading === 'update-{{ data_get($item, $columns[0]['key']) }}'"
                                                title="Update">
                                                <i class="fas" 
                                                   x-bind:class="actionLoading === 'update-{{ data_get($item, $columns[0]['key']) }}' ? 'fa-spinner fa-spin' : 'fa-edit'"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-outline-danger btn-sm" 
                                                x-on:click="confirmDelete('{{ $resourceType }}', '{{ data_get($item, $columns[0]['key']) }}')"
                                                x-bind:disabled="actionLoading === 'delete-{{ data_get($item, $columns[0]['key']) }}'"
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
@if($showActions)
    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update <span id="updateResourceType"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="updateModalBody">
                    <div class="text-center py-4" x-show="modalLoading === 'update'" x-cloak>
                        <div class="spinner-border text-warning" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading update form...</p>
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
                    <div class="text-center py-4" x-show="modalLoading === 'view'" x-cloak>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading resource details...</p>
                    </div>
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
                    <p>Are you sure you want to delete this <span id="deleteResourceType"></span>?</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" 
                            x-bind:disabled="modalLoading === 'delete'">
                        <i class="fas" x-bind:class="modalLoading === 'delete' ? 'fa-spinner fa-spin me-1' : 'fa-trash me-1'"></i>
                        <span x-text="modalLoading === 'delete' ? 'Deleting...' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function resourceTable() {
            return {
                isRefreshing: false,
                actionLoading: null,
                modalLoading: null,
                
                refreshData() {
                    this.isRefreshing = true;
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                },
                
                viewResource(resourceType, resourceId, caseId = null) {
                    this.actionLoading = 'view-' + resourceId;
                    this.modalLoading = 'view';
                    
                    document.getElementById('viewResourceType').textContent = resourceType;
                    document.getElementById('viewModalContent').innerHTML = '';
                    
                    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
                    viewModal.show();
                    
                    // Build URL with case ID for sessions
                    let url = `/data-exchange/get-${resourceType}/${resourceId}`;
                    if (resourceType === 'session' && caseId) {
                        url += `?case_id=${caseId}`;
                    }
                    
                    // Add timeout to prevent indefinite loading
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                    
                    fetch(url, {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => {
                            clearTimeout(timeoutId);
                            
                            if (!response.ok) {
                                throw new Error(`Server error: ${response.status} ${response.statusText}`);
                            }
                            
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Extract the actual resource data from nested structure
                                const resourceKey = resourceType.charAt(0).toUpperCase() + resourceType.slice(1);
                                const resourceData = data.resource[resourceKey] || data.resource;
                                this.generateViewContent(resourceType, resourceId, resourceData);
                            } else {
                                this.showNotification(data.message || 'Failed to load resource data', 'error');
                                viewModal.hide();
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            
                            let errorMessage = 'Unknown error occurred';
                            
                            if (error.name === 'AbortError') {
                                errorMessage = 'Request timed out. Please try again.';
                            } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                                errorMessage = 'Network error. Please check your connection and try again.';
                            } else if (error.message.includes('Server error')) {
                                errorMessage = error.message;
                            } else {
                                errorMessage = 'Error loading resource data: ' + error.message;
                            }
                            
                            this.showNotification(errorMessage, 'error');
                            viewModal.hide();
                        })
                        .finally(() => {
                            this.actionLoading = null;
                            this.modalLoading = null;
                        });
                },
                
                showUpdateForm(resourceType, resourceId) {
                    this.actionLoading = 'update-' + resourceId;
                    this.modalLoading = 'update';
                    
                    document.getElementById('updateResourceType').textContent = resourceType;
                    document.getElementById('updateModalContent').innerHTML = '';
                    
                    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
                    updateModal.show();
                    
                    // Add timeout to prevent indefinite loading
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                    
                    fetch(`/data-exchange/get-${resourceType}/${resourceId}`, {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    })
                        .then(response => {
                            clearTimeout(timeoutId);
                            
                            if (!response.ok) {
                                throw new Error(`Server error: ${response.status} ${response.statusText}`);
                            }
                            
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Extract the actual resource data from nested structure
                                const resourceKey = resourceType.charAt(0).toUpperCase() + resourceType.slice(1);
                                const resourceData = data.resource[resourceKey] || data.resource;
                                this.generateUpdateForm(resourceType, resourceId, resourceData);
                            } else {
                                this.showNotification(data.message || 'Failed to load resource data', 'error');
                                updateModal.hide();
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            
                            let errorMessage = 'Unknown error occurred';
                            
                            if (error.name === 'AbortError') {
                                errorMessage = 'Request timed out. Please try again.';
                            } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                                errorMessage = 'Network error. Please check your connection and try again.';
                            } else if (error.message.includes('Server error')) {
                                errorMessage = error.message;
                            } else {
                                errorMessage = 'Error loading resource data: ' + error.message;
                            }
                            
                            this.showNotification(errorMessage, 'error');
                            updateModal.hide();
                        })
                        .finally(() => {
                            this.actionLoading = null;
                            this.modalLoading = null;
                        });
                },
                
                confirmDelete(resourceType, resourceId) {
                    document.getElementById('deleteResourceType').textContent = resourceType;
                    
                    const confirmBtn = document.getElementById('confirmDeleteBtn');
                    confirmBtn.onclick = () => this.handleDelete(resourceType, resourceId);
                    
                    new bootstrap.Modal(document.getElementById('deleteModal')).show();
                },
                
                handleDelete(resourceType, resourceId) {
                    this.modalLoading = 'delete';
                    
                    // Add timeout to prevent indefinite loading
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                    
                    fetch(`/data-exchange/delete-${resourceType}/${resourceId}`, {
                        method: 'DELETE',
                        signal: controller.signal,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        
                        if (!response.ok) {
                            throw new Error(`Server error: ${response.status} ${response.statusText}`);
                        }
                        
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.showNotification(`${resourceType} deleted successfully`, 'success');
                            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            this.showNotification(data.message || 'Delete failed', 'error');
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        
                        let errorMessage = 'Unknown error occurred';
                        
                        if (error.name === 'AbortError') {
                            errorMessage = 'Delete operation timed out. Please try again.';
                        } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                            errorMessage = 'Network error. Please check your connection and try again.';
                        } else if (error.message.includes('Server error')) {
                            errorMessage = error.message;
                        } else {
                            errorMessage = 'Error deleting resource: ' + error.message;
                        }
                        
                        this.showNotification(errorMessage, 'error');
                    })
                    .finally(() => {
                        this.modalLoading = null;
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
                    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
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
                
                generateViewContent(resourceType, resourceId, resourceData) {
                    const modalContent = document.getElementById('viewModalContent');
                    
                    let content = '<div class="row">';
                    
                    // Generate view content based on resource type with correct API field names
                    if (resourceType === 'client') {
                        content += this.generateClientViewContent(resourceData);
                    } else if (resourceType === 'case') {
                        content += this.generateCaseViewContent(resourceData);
                    } else if (resourceType === 'session') {
                        content += this.generateSessionViewContent(resourceData);
                    }
                    
                    content += '</div>';
                    content += `
                        <div class="mt-3 d-flex justify-content-end">
                            <button type="button" class="btn btn-warning me-2" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); resourceTable().showUpdateForm('${resourceType}', '${resourceId}')">
                                <i class="fas fa-edit"></i> Update
                            </button>
                            <button type="button" class="btn btn-danger" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); resourceTable().confirmDelete('${resourceType}', '${resourceId}')">
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
                    return `
                        <div class="col-md-6 mb-3">
                            <strong>Case ID:</strong><br>
                            <span class="text-muted">${data.CaseDetail?.CaseId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Client ID:</strong><br>
                            <span class="text-muted">${data.Clients?.CaseClient?.ClientId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Outlet Activity ID:</strong><br>
                            <span class="text-muted">${data.CaseDetail?.OutletActivityId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Referral Source:</strong><br>
                            <span class="text-muted">${data.Clients?.CaseClient?.ReferralSourceCode || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Created Date:</strong><br>
                            <span class="text-muted">${data.CreatedDateTime ? new Date(data.CreatedDateTime).toLocaleDateString() : 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Exit Reason:</strong><br>
                            <span class="text-muted">${data.Clients?.CaseClient?.ExitReasonCode || 'N/A'}</span>
                        </div>
                    `;
                },
                
                generateSessionViewContent(data) {
                    return `
                        <div class="col-md-6 mb-3">
                            <strong>Session ID:</strong><br>
                            <span class="text-muted">${data.SessionDetails?.SessionId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Case ID:</strong><br>
                            <span class="text-muted">${data.CaseId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Service Type ID:</strong><br>
                            <span class="text-muted">${data.SessionDetails?.ServiceTypeId || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Session Date:</strong><br>
                            <span class="text-muted">${data.SessionDetails?.SessionDate ? new Date(data.SessionDetails.SessionDate).toLocaleDateString() : 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Duration/Time:</strong><br>
                            <span class="text-muted">${data.SessionDetails?.Time || 'N/A'}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Topic:</strong><br>
                            <span class="text-muted">${data.SessionDetails?.TopicCode || 'N/A'}</span>
                        </div>
                    `;
                },
                
                generateUpdateForm(resourceType, resourceId, resourceData) {
                    const modalContent = document.getElementById('updateModalContent');
                    
                    let formHtml = `<form id="updateForm" onsubmit="resourceTable().handleUpdate(event, '${resourceType}', '${resourceId}')">`;
                    
                    if (resourceType === 'client') {
                        formHtml += this.generateClientUpdateFields(resourceData);
                    } else if (resourceType === 'case') {
                        formHtml += this.generateCaseUpdateFields(resourceData);
                    } else if (resourceType === 'session') {
                        formHtml += this.generateSessionUpdateFields(resourceData);
                    }
                    
                    formHtml += `
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-1"></i>Update ${resourceType}
                            </button>
                        </div>
                    </form>`;
                    
                    modalContent.innerHTML = formHtml;
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
                    return `
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_referral_source_code" class="form-label">Referral Source</label>
                                <select class="form-select" id="update_referral_source_code" name="referral_source_code" required>
                                    <option value="">Select Referral Source</option>
                                    <option value="COMMUNITY" ${data.Clients?.CaseClient?.ReferralSourceCode === 'COMMUNITY' ? 'selected' : ''}>Community services agency</option>
                                    <option value="SELF" ${data.Clients?.CaseClient?.ReferralSourceCode === 'SELF' ? 'selected' : ''}>Self</option>
                                    <option value="FAMILY" ${data.Clients?.CaseClient?.ReferralSourceCode === 'FAMILY' ? 'selected' : ''}>Family</option>
                                    <option value="GP" ${data.Clients?.CaseClient?.ReferralSourceCode === 'GP' ? 'selected' : ''}>General Medical Practitioner</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="update_client_attendance_profile_code" class="form-label">Attendance Profile</label>
                                <select class="form-select" id="update_client_attendance_profile_code" name="client_attendance_profile_code">
                                    <option value="">Select Profile</option>
                                    <option value="REGULAR" ${data.CaseDetail?.ClientAttendanceProfileCode === 'REGULAR' ? 'selected' : ''}>Regular</option>
                                    <option value="IRREGULAR" ${data.CaseDetail?.ClientAttendanceProfileCode === 'IRREGULAR' ? 'selected' : ''}>Irregular</option>
                                    <option value="ONEOFF" ${data.CaseDetail?.ClientAttendanceProfileCode === 'ONEOFF' ? 'selected' : ''}>One-off</option>
                                </select>
                            </div>
                        </div>
                    `;
                },
                
                generateSessionUpdateFields(data) {
                    return `
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_session_date" class="form-label">Session Date</label>
                                <input type="date" class="form-control" id="update_session_date" name="session_date" 
                                       value="${data.SessionDetails?.SessionDate ? data.SessionDetails.SessionDate.split('T')[0] : ''}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="update_topic_code" class="form-label">Topic</label>
                                <input type="text" class="form-control" id="update_topic_code" name="topic_code" value="${data.SessionDetails?.TopicCode || ''}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="update_time" class="form-label">Duration/Time</label>
                                <input type="text" class="form-control" id="update_time" name="time" value="${data.SessionDetails?.Time || ''}" 
                                       placeholder="e.g., 60 minutes">
                            </div>
                        </div>
                    `;
                },
                
                handleUpdate(event, resourceType, resourceId) {
                    event.preventDefault();
                    
                    const formData = new FormData(event.target);
                    const submitBtn = event.target.querySelector('button[type="submit"]');
                    const originalHtml = submitBtn.innerHTML;
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
                    
                    // Add timeout to prevent indefinite loading
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                    
                    fetch(`/data-exchange/update-${resourceType}/${resourceId}`, {
                        method: 'POST',
                        signal: controller.signal,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        
                        if (!response.ok) {
                            throw new Error(`Server error: ${response.status} ${response.statusText}`);
                        }
                        
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.showNotification(`${resourceType} updated successfully`, 'success');
                            bootstrap.Modal.getInstance(document.getElementById('updateModal')).hide();
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            this.showNotification(data.message || 'Update failed', 'error');
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        
                        let errorMessage = 'Unknown error occurred';
                        
                        if (error.name === 'AbortError') {
                            errorMessage = 'Update operation timed out. Please try again.';
                        } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                            errorMessage = 'Network error. Please check your connection and try again.';
                        } else if (error.message.includes('Server error')) {
                            errorMessage = error.message;
                        } else {
                            errorMessage = 'Error updating resource: ' + error.message;
                        }
                        
                        this.showNotification(errorMessage, 'error');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    });
                }
            };
        }

        // Initialize Alpine.js data store for each table instance
        window.resourceTableInstance = null;
        
        document.addEventListener('alpine:init', () => {
            window.resourceTableInstance = resourceTable();
        });
    </script>
    @endpush
@endif