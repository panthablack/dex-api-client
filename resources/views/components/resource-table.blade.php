@props([
    'title',
    'resourceType',
    'data' => [],
    'columns' => [],
    'showActions' => true,
    'emptyMessage' => 'No data available',
    'loading' => false
])

<div class="card" x-data="resourceTableComponent">
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
                        @foreach($data as $index => $item)
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
                                                x-on:click="viewResource('{{ $resourceType }}', {{ $index }})"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-outline-warning btn-sm" 
                                                x-on:click="showUpdateForm('{{ $resourceType }}', {{ $index }})"
                                                title="Update">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-outline-danger btn-sm" 
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
                    <p>Are you sure you want to delete this <span id="deleteResourceType"></span>?</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong></p>
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
                    
                    document.getElementById('viewResourceType').textContent = resourceType;
                    
                    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
                    viewModal.show();
                    
                    this.generateViewContent(resourceType, item);
                },
                
                showUpdateForm(resourceType, itemIndex) {
                    const item = this.data[itemIndex];
                    if (!item) {
                        this.showNotification('Resource data not found. Please refresh the page and try again.', 'error');
                        return;
                    }
                    
                    document.getElementById('updateResourceType').textContent = resourceType;
                    
                    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
                    updateModal.show();
                    
                    this.generateUpdateForm(resourceType, itemIndex, item);
                },
                
                confirmDelete(resourceType, itemIndex) {
                    const item = this.data[itemIndex];
                    if (!item) {
                        this.showNotification('Resource data not found. Please refresh the page and try again.', 'error');
                        return;
                    }
                    
                    document.getElementById('deleteResourceType').textContent = resourceType;
                    
                    const confirmBtn = document.getElementById('confirmDeleteBtn');
                    confirmBtn.onclick = () => this.showNotification('Delete functionality requires backend integration. Please contact an administrator.', 'warning');
                    
                    new bootstrap.Modal(document.getElementById('deleteModal')).show();
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
                
                generateViewContent(resourceType, resourceData) {
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
                        content += '<div class="col-12 text-center"><p class="text-muted">Resource details not available</p></div>';
                    }
                    
                    content += '</div>';
                    content += `
                        <div class="mt-3 d-flex justify-content-end">
                            <div class="alert alert-info w-100">
                                <i class="fas fa-info-circle me-2"></i>
                                Update and delete functionality requires backend integration. Please contact an administrator for modifications.
                            </div>
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
                
                generateUpdateForm(resourceType, itemIndex, resourceData) {
                    const modalContent = document.getElementById('updateModalContent');
                    
                    let formHtml = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Update functionality requires backend integration. The form below shows current data but cannot be submitted.
                        </div>
                        <form id="updateForm">`;
                    
                    if (resourceType === 'client') {
                        formHtml += this.generateClientUpdateFields(resourceData);
                    } else if (resourceType === 'case') {
                        formHtml += this.generateCaseUpdateFields(resourceData);
                    } else if (resourceType === 'session') {
                        formHtml += this.generateSessionUpdateFields(resourceData);
                    } else {
                        formHtml += '<div class="text-center"><p class="text-muted">Update form not available for this resource type</p></div>';
                    }
                    
                    formHtml += `
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-warning" disabled>
                                <i class="fas fa-save me-1"></i>Update ${resourceType} (Disabled)
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
                
            };
        }

        // Initialize Alpine.js component
        document.addEventListener('alpine:init', () => {
            Alpine.data('resourceTableComponent', () => resourceTable(@json($data)));
        });
    </script>
    @endpush
@endif