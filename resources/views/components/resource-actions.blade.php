@props([
    'resourceType',
    'resourceData' => null,
    'resourceId' => null,
    'showUpdate' => true,
    'showDelete' => true
])

@if($resourceData)
<div class="resource-actions mt-3">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Resource Actions</h6>
            <span class="badge bg-info">{{ ucfirst($resourceType) }} ID: {{ $resourceId }}</span>
        </div>
        <div class="card-body">
            <div class="d-grid gap-2 d-md-flex">
                @if($showUpdate)
                    <button 
                        type="button" 
                        class="btn btn-warning btn-sm" 
                        onclick="showUpdateForm('{{ $resourceType }}', '{{ $resourceId }}')"
                    >
                        <i class="fas fa-edit"></i> Update {{ ucfirst($resourceType) }}
                    </button>
                @endif
                
                @if($showDelete)
                    <button 
                        type="button" 
                        class="btn btn-danger btn-sm" 
                        onclick="confirmDelete('{{ $resourceType }}', '{{ $resourceId }}')"
                    >
                        <i class="fas fa-trash"></i> Delete {{ ucfirst($resourceType) }}
                    </button>
                @endif
                
                <button 
                    type="button" 
                    class="btn btn-outline-info btn-sm" 
                    onclick="downloadResource('{{ $resourceType }}', '{{ $resourceId }}')"
                >
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

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
function showUpdateForm(resourceType, resourceId) {
    document.getElementById('updateResourceType').textContent = resourceType;
    
    // Load the update form
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
    
    // Generate form based on resource type
    let formHtml = `<form id="updateForm" onsubmit="handleUpdate(event, '${resourceType}', '${resourceId}')">`;
    
    // Add form fields based on resource type
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
    
    // Show loading state
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
            // Refresh the data display if needed
            window.location.reload();
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
            // Redirect to retrieve form or refresh
            window.location.href = '/data-exchange/retrieve-form';
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

function downloadResource(resourceType, resourceId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/data-exchange/retrieve-data';
    form.style.display = 'none';

    const csrfInput = document.createElement('input');
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    form.appendChild(csrfInput);

    const actionInput = document.createElement('input');
    actionInput.name = 'action';
    actionInput.value = 'download';
    form.appendChild(actionInput);

    const resourceInput = document.createElement('input');
    resourceInput.name = 'resource_type';
    resourceInput.value = `${resourceType}_by_id`;
    form.appendChild(resourceInput);

    const idInput = document.createElement('input');
    idInput.name = `${resourceType}_id`;
    idInput.value = resourceId;
    form.appendChild(idInput);

    const formatInput = document.createElement('input');
    formatInput.name = 'format';
    formatInput.value = 'json';
    form.appendChild(formatInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
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