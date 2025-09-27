<div class="modal fade" id="error-details-modal" tabindex="-1" aria-labelledby="errorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorDetailsModalLabel" x-text="errorModal.title">Verification Error
                    Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span
                            x-text="`Found ${errorModal.errors?.length || 0} verification error${(errorModal.errors?.length || 0) > 1 ? 's' : ''} for ${errorModal.resourceType}`"></span>
                    </div>
                </div>
                <div class="list-group">
                    <template x-for="(error, index) in errorModal.errors" :key="index">
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 text-danger" x-text="`Error ${index + 1}`"></h6>
                                <small class="text-muted" x-text="`#${index + 1}`"></small>
                            </div>
                            <p class="mb-1" x-text="error"></p>
                            <small class="text-muted">This record failed verification and may need manual
                                review.</small>
                        </div>
                    </template>
                </div>
                <div x-show="errorModal.errors && errorModal.errors.length > 10" class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Showing first 10 errors. Additional errors may exist in the full verification log.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
