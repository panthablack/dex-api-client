@props([
    'title',
    'resourceType',
    'data' => [],
    'columns' => [],
    'showActions' => true,
    'emptyMessage' => 'No data available',
    'loading' => false
])

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $title }}</h5>
        <div class="d-flex align-items-center">
            @if(count($data) > 0)
                <span class="badge bg-info me-2">{{ count($data) }} {{ Str::plural('record', count($data)) }}</span>
            @endif
            <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i> Refresh
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
                                                onclick="viewResource('{{ $resourceType }}', '{{ data_get($item, $columns[0]['key']) }}')"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-outline-warning btn-sm" 
                                                onclick="showUpdateForm('{{ $resourceType }}', '{{ data_get($item, $columns[0]['key']) }}')"
                                                title="Update">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                class="btn btn-outline-danger btn-sm" 
                                                onclick="confirmDelete('{{ $resourceType }}', '{{ data_get($item, $columns[0]['key']) }}')"
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
                    <!-- Update form will be loaded here -->
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
                    <!-- Resource details will be loaded here -->
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
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function refreshData() {
            window.location.reload();
        }

        function viewResource(resourceType, resourceId) {
            document.getElementById('viewResourceType').textContent = resourceType;
            
            fetch(`/data-exchange/get-${resourceType}/${resourceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        generateViewContent(resourceType, resourceId, data.resource);
                        new bootstrap.Modal(document.getElementById('viewModal')).show();
                    } else {
                        showNotification(data.message || 'Failed to load resource data', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error loading resource data: ' + error.message, 'error');
                });
        }

        function generateViewContent(resourceType, resourceId, resourceData) {
            const modalBody = document.getElementById('viewModalBody');
            
            let content = '<div class="row">';
            
            // Generate view content based on resource type
            if (resourceType === 'client') {
                content += generateClientViewContent(resourceData);
            } else if (resourceType === 'case') {
                content += generateCaseViewContent(resourceData);
            } else if (resourceType === 'session') {
                content += generateSessionViewContent(resourceData);
            }
            
            content += '</div>';
            content += `
                <div class="mt-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-warning me-2" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); showUpdateForm('${resourceType}', '${resourceId}')">
                        <i class="fas fa-edit"></i> Update
                    </button>
                    <button type="button" class="btn btn-danger" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); confirmDelete('${resourceType}', '${resourceId}')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            `;
            
            modalBody.innerHTML = content;
        }

        function generateClientViewContent(data) {
            return `
                <div class="col-md-6">
                    <strong>Client ID:</strong> ${data.client_id || 'N/A'}<br>
                    <strong>Name:</strong> ${data.first_name || ''} ${data.last_name || ''}<br>
                    <strong>Date of Birth:</strong> ${data.date_of_birth || 'N/A'}<br>
                    <strong>Gender:</strong> ${data.gender || 'N/A'}<br>
                    <strong>Client Type:</strong> ${data.client_type || 'N/A'}
                </div>
                <div class="col-md-6">
                    <strong>Location:</strong> ${data.suburb || ''}, ${data.state || ''} ${data.postal_code || ''}<br>
                    <strong>Country of Birth:</strong> ${data.country_of_birth || 'N/A'}<br>
                    <strong>Primary Language:</strong> ${data.primary_language || 'N/A'}<br>
                    <strong>Indigenous Status:</strong> ${data.indigenous_status || 'N/A'}<br>
                    <strong>Disability:</strong> ${data.disability_flag ? 'Yes' : 'No'}
                </div>
            `;
        }

        function generateCaseViewContent(data) {
            return `
                <div class="col-md-6">
                    <strong>Case ID:</strong> ${data.case_id || 'N/A'}<br>
                    <strong>Client ID:</strong> ${data.client_id || 'N/A'}<br>
                    <strong>Outlet Activity ID:</strong> ${data.outlet_activity_id || 'N/A'}<br>
                    <strong>Referral Source:</strong> ${data.referral_source_code || 'N/A'}
                </div>
                <div class="col-md-6">
                    <strong>End Date:</strong> ${data.end_date || 'N/A'}<br>
                    <strong>Exit Reason:</strong> ${data.exit_reason_code || 'N/A'}<br>
                    <strong>Attendance Profile:</strong> ${data.client_attendance_profile_code || 'N/A'}<br>
                    <strong>Total Unidentified Clients:</strong> ${data.total_unidentified_clients || '0'}
                </div>
            `;
        }

        function generateSessionViewContent(data) {
            return `
                <div class="col-md-6">
                    <strong>Session ID:</strong> ${data.session_id || 'N/A'}<br>
                    <strong>Case ID:</strong> ${data.case_id || 'N/A'}<br>
                    <strong>Service Type ID:</strong> ${data.service_type_id || 'N/A'}<br>
                    <strong>Session Date:</strong> ${data.session_date || 'N/A'}
                </div>
                <div class="col-md-6">
                    <strong>Duration:</strong> ${data.duration_minutes || 'N/A'} minutes<br>
                    <strong>Status:</strong> ${data.session_status || 'N/A'}<br>
                    <strong>Location:</strong> ${data.location || 'N/A'}<br>
                    <strong>Outcome:</strong> ${data.outcome || 'N/A'}
                </div>
                <div class="col-12 mt-2">
                    <strong>Notes:</strong><br>
                    <div class="border rounded p-2 bg-light">${data.notes || 'No notes available'}</div>
                </div>
            `;
        }

        // Reuse existing functions from resource-actions component
        function showUpdateForm(resourceType, resourceId) {
            document.getElementById('updateResourceType').textContent = resourceType;
            
            fetch(`/data-exchange/get-${resourceType}/${resourceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        generateUpdateForm(resourceType, resourceId, data.resource);
                        new bootstrap.Modal(document.getElementById('updateModal')).show();
                    } else {
                        showNotification(data.message || 'Failed to load resource data', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error loading resource data: ' + error.message, 'error');
                });
        }

        function generateUpdateForm(resourceType, resourceId, resourceData) {
            const modalBody = document.getElementById('updateModalBody');
            
            let formHtml = `<form id="updateForm" onsubmit="handleUpdate(event, '${resourceType}', '${resourceId}')">`;
            
            if (resourceType === 'client') {
                formHtml += generateClientUpdateFields(resourceData);
            } else if (resourceType === 'case') {
                formHtml += generateCaseUpdateFields(resourceData);
            } else if (resourceType === 'session') {
                formHtml += generateSessionUpdateFields(resourceData);
            }
            
            formHtml += `
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update ${resourceType}</button>
                </div>
            </form>`;
            
            modalBody.innerHTML = formHtml;
        }

        function generateClientUpdateFields(data) {
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="update_first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="update_first_name" name="first_name" value="${data.first_name || ''}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="update_last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="update_last_name" name="last_name" value="${data.last_name || ''}" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="update_date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="update_date_of_birth" name="date_of_birth" value="${data.date_of_birth || ''}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="update_gender" class="form-label">Gender</label>
                        <select class="form-select" id="update_gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="M" ${data.gender === 'M' ? 'selected' : ''}>Male</option>
                            <option value="F" ${data.gender === 'F' ? 'selected' : ''}>Female</option>
                            <option value="X" ${data.gender === 'X' ? 'selected' : ''}>Non-binary</option>
                            <option value="9" ${data.gender === '9' ? 'selected' : ''}>Not stated</option>
                        </select>
                    </div>
                </div>
            `;
        }

        function generateCaseUpdateFields(data) {
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="update_referral_source_code" class="form-label">Referral Source</label>
                        <select class="form-select" id="update_referral_source_code" name="referral_source_code" required>
                            <option value="">Select Referral Source</option>
                            <option value="COMMUNITY" ${data.referral_source_code === 'COMMUNITY' ? 'selected' : ''}>Community services agency</option>
                            <option value="SELF" ${data.referral_source_code === 'SELF' ? 'selected' : ''}>Self</option>
                            <option value="FAMILY" ${data.referral_source_code === 'FAMILY' ? 'selected' : ''}>Family</option>
                            <option value="GP" ${data.referral_source_code === 'GP' ? 'selected' : ''}>General Medical Practitioner</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="update_end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="update_end_date" name="end_date" value="${data.end_date || ''}">
                    </div>
                </div>
            `;
        }

        function generateSessionUpdateFields(data) {
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="update_session_status" class="form-label">Session Status</label>
                        <select class="form-select" id="update_session_status" name="session_status">
                            <option value="">Select Status</option>
                            <option value="Scheduled" ${data.session_status === 'Scheduled' ? 'selected' : ''}>Scheduled</option>
                            <option value="Completed" ${data.session_status === 'Completed' ? 'selected' : ''}>Completed</option>
                            <option value="Cancelled" ${data.session_status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                            <option value="No Show" ${data.session_status === 'No Show' ? 'selected' : ''}>No Show</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="update_duration_minutes" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="update_duration_minutes" name="duration_minutes" value="${data.duration_minutes || ''}" min="1">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="update_notes" class="form-label">Session Notes</label>
                        <textarea class="form-control" id="update_notes" name="notes" rows="3">${data.notes || ''}</textarea>
                    </div>
                </div>
            `;
        }

        function handleUpdate(event, resourceType, resourceId) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
            
            fetch(`/data-exchange/update-${resourceType}/${resourceId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${resourceType} updated successfully`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('updateModal')).hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Update failed', 'error');
                }
            })
            .catch(error => {
                showNotification('Error updating resource: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        function confirmDelete(resourceType, resourceId) {
            document.getElementById('deleteResourceType').textContent = resourceType;
            
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.onclick = () => handleDelete(resourceType, resourceId);
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function handleDelete(resourceType, resourceId) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.textContent;
            
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Deleting...';
            
            fetch(`/data-exchange/delete-${resourceType}/${resourceId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${resourceType} deleted successfully`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Delete failed', 'error');
                }
            })
            .catch(error => {
                showNotification('Error deleting resource: ' + error.message, 'error');
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
            });
        }

        function showNotification(message, type = 'info', duration = 5000) {
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
        }
    </script>
    @endpush
@endif