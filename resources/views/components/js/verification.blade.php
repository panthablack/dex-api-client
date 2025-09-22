<script>
    function verificationApp() {
        return {
            verification: {
                status: 'loading', // 'loading', 'idle', 'starting', 'in_progress', 'completed', 'failed', 'partial'
                progress: 0,
                total: 0,
                processed: 0,
                verified: 0,
                currentActivity: '',
                resourceProgress: {},
                results: {
                    clients: {},
                    cases: {},
                    sessions: {}
                }
            },
            pollInterval: null,
            errorModal: {
                title: '',
                resourceType: '',
                errors: []
            },
            initialised: false,
            migration: {},
            verificationStatus: {},

            async getStatus() {
                const response = await fetch(`{{ route('data-migration.api.verification-status', $migration) }}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.verificationStatus = data
                if (!data) this.showToast('Error: ' + data.error, 'error');
            },

            async getMigration() {
                const response = await fetch(`{{ route('data-migration.api.get-migration', $migration) }}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.migration = data
                if (!data) this.showToast('Error: ' + data.error, 'error');
            },

            async init() {
                // if app already initialised, return
                if (this.initialised) return

                // Do initial fetch of data and set state
                this.initialised = true
                const res = await this.getStatus()
                this.verification.status = 'idle'
            },

            async startVerification() {
                try {
                    console.log('starting verification...');
                } catch (error) {
                    console.error('Error:', error);
                    alert('Failed to start verification');
                }
            },

            updateVerification(data) {
                // Map new API response format to frontend expectations
                this.verification.status = data.status;
                this.verification.total = data.total_records || 0;
                this.verification.processed = data.processed_records || 0;
                this.verification.verified = data.verified_records || 0;
                this.verification.currentActivity = data.message || '';
                this.verification.resourceProgress = data.resource_progress || {};

                // Convert resource_progress to results format for button logic
                this.verification.results = {};
                if (data.resource_progress) {
                    for (const [resourceType, progress] of Object.entries(data.resource_progress)) {
                        this.verification.results[resourceType] = {
                            total: progress.total || 0,
                            verified: progress.verified || 0,
                            failed: progress.failed || 0,
                            pending: progress.pending || 0
                        };
                    }
                }

                if (this.verification.total > 0) {
                    this.verification.progress = Math.min(Math.round((this.verification.processed / this.verification
                        .total) * 100), 100);
                }
            },

            getStatusText() {
                switch (this.verification.status) {
                    case 'loading':
                        return 'Loading...';
                    case 'starting':
                        return 'Starting...';
                    case 'in_progress':
                        return 'In Progress';
                    case 'completed':
                        return 'Completed';
                    case 'completed_with_failures':
                        return 'Completed with Failures';
                    case 'partial':
                        return 'Partially Verified';
                    case 'failed':
                        return 'Failed';
                    case 'stopping':
                        return 'Stopping...';
                    case 'stopped':
                        return 'Stopped';
                    case 'idle':
                        return 'Ready to Start';
                    case 'no_data':
                        return 'No Data Available';
                    default:
                        return 'Initializing...';
                }
            },

            getStatusBadgeClass() {
                switch (this.verification.status) {
                    case 'loading':
                        return 'bg-info';
                    case 'starting':
                        return 'bg-info';
                    case 'in_progress':
                        return 'bg-warning';
                    case 'completed':
                        return 'bg-success';
                    case 'completed_with_failures':
                        return 'bg-warning';
                    case 'partial':
                        return 'bg-warning';
                    case 'failed':
                        return 'bg-danger';
                    case 'stopping':
                        return 'bg-warning';
                    case 'stopped':
                        return 'bg-secondary';
                    case 'idle':
                        return 'bg-secondary';
                    case 'no_data':
                        return 'bg-light text-dark';
                    default:
                        return 'bg-info';
                }
            },

            getProgressBarClass() {
                switch (this.verification.status) {
                    case 'completed':
                        return 'bg-success';
                    case 'completed_with_failures':
                        return 'bg-warning';
                    case 'partial':
                        return 'bg-warning';
                    case 'failed':
                        return 'bg-danger';
                    case 'in_progress':
                        return 'progress-bar-striped progress-bar-animated bg-warning';
                    case 'no_data':
                        return 'bg-light';
                    default:
                        return 'progress-bar-striped progress-bar-animated';
                }
            },

            getProgressText() {
                return `${this.verification.processed?.toLocaleString() || 0} of ${this.verification.total?.toLocaleString() || 0} records processed`;
            },

            getSuccessRate() {
                return this.verification.total > 0 ? Math.round((this.verification.verified / this.verification.total) *
                    100) : 0;
            },

            getSuccessText() {
                return `${this.verification.verified?.toLocaleString() || 0} of ${this.verification.total?.toLocaleString() || 0} records verified`;
            },

            getProcessingBarClass() {
                // Processing progress: Blue for in progress, Green when complete
                if (this.verification.status === 'in_progress' || this.verification.status === 'starting') {
                    return 'progress-bar-striped progress-bar-animated bg-info';
                }
                return this.verification.progress >= 100 ? 'bg-success' : 'bg-info';
            },

            getSuccessBarClass() {
                // Success rate: Green for high success, Yellow for medium, Orange for low
                const rate = this.getSuccessRate();
                if (rate >= 100) return 'bg-success';
                if (rate >= 30) return 'bg-warning'; // Orange/warning for moderate success
                return 'bg-danger'; // Only red for very low success rates
            },

            getResultSuccessRate(result) {
                return result.total > 0 ? Math.round((result.verified / result.total) * 100) : 0;
            },

            getResultCardClass(result) {
                const rate = this.getResultSuccessRate(result);
                if (rate >= 95) return 'border-success';
                if (rate >= 80) return 'border-warning';
                return 'border-danger';
            },

            getResultStatusClass(result) {
                const rate = this.getResultSuccessRate(result);
                if (rate >= 95) return 'text-success';
                if (rate >= 80) return 'text-warning';
                return 'text-danger';
            },

            getResultIcon(result) {
                const rate = this.getResultSuccessRate(result);
                if (rate >= 95) return '✓';
                if (rate >= 80) return '⚠';
                return '✗';
            },

            showErrorDetails(resourceType, errors) {
                this.errorModal.resourceType = resourceType;
                this.errorModal.errors = errors.slice(0, 10); // Show first 10 errors
                this.errorModal.title =
                    `${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)} Verification Errors`;

                const modal = new bootstrap.Modal(document.getElementById('error-details-modal'));
                modal.show();
            },

            getResourceProgressRate(resourceType, progress) {
                const resourceProgress = progress.resource_progress || {};
                const resource = resourceProgress[resourceType] || {
                    total: 0,
                    processed: 0
                };
                return resource.total > 0 ? Math.round((resource.processed / resource.total) * 100) : 0;
            },

            getResourceProgressText(resourceType, progress) {
                const resourceProgress = progress.resource_progress || {};
                const resource = resourceProgress[resourceType] || {
                    total: 0,
                    processed: 0
                };
                return `${resource.processed?.toLocaleString() || 0} of ${resource.total?.toLocaleString() || 0} processed`;
            },

            getResourceProgressBarClass(resourceType, progress) {
                const rate = this.getResourceProgressRate(resourceType, progress);
                if (progress.status === 'in_progress' || progress.status === 'starting') {
                    return 'progress-bar-striped progress-bar-animated bg-info';
                }
                return rate >= 100 ? 'bg-success' : 'bg-info';
            },

            getResourceSuccessRate(resourceType, progress) {
                const results = this.verification.results || {};
                const result = results[resourceType] || {
                    total: 0,
                    verified: 0
                };
                return result.total > 0 ? Math.round((result.verified / result.total) * 100) : 0;
            },

            getResourceSuccessText(resourceType, progress) {
                const results = this.verification.results || {};
                const result = results[resourceType] || {
                    total: 0,
                    verified: 0
                };
                return `${result.verified?.toLocaleString() || 0} of ${result.total?.toLocaleString() || 0} verified`;
            },

            getResourceSuccessBarClass(resourceType, progress) {
                const rate = this.getResourceSuccessRate(resourceType, progress);
                if (rate >= 100) return 'bg-success';
                if (rate >= 30) return 'bg-warning';
                return 'bg-danger';
            },

            async continueVerification() {
                try {
                    // Immediately set status to starting to show stop button
                    console.log('Continue Verification...');

                } catch (error) {
                    console.error('Continue verification failed:', error);
                    alert('Continue verification failed: ' + error.message);
                }
            },

            async stopVerification() {
                try {
                    console.log('Stopping verification...');

                } catch (error) {
                    console.error('Stop verification failed:', error);
                    alert('Stop verification failed: ' + error.message);
                }
            },

            hasUnverifiedRecords() {
                if (!this.verification.results) return false;

                for (const [resourceType, result] of Object.entries(this.verification.results)) {
                    const failed = result.failed || 0;
                    const total = result.total || 0;
                    const verified = result.verified || 0;
                    const pending = total - verified - failed;

                    if (failed > 0 || pending > 0) {
                        return true;
                    }
                }
                return false;
            },

            hasNeverBeenVerified() {
                // Check if verification has never been started
                // This is true when:
                // 1. No verification results exist, OR
                // 2. All records are still in pending state (never attempted)

                if (!this.verification.results || Object.keys(this.verification.results).length === 0) {
                    return true;
                }

                // Check if any records have been processed (verified or failed)
                for (const [resourceType, result] of Object.entries(this.verification.results)) {
                    const verified = result.verified || 0;
                    const failed = result.failed || 0;

                    // If any records have been verified or failed, verification has been attempted
                    if (verified > 0 || failed > 0) {
                        return false;
                    }
                }

                // All records are still pending - verification never started
                return true;
            }
        };
    }
</script>
