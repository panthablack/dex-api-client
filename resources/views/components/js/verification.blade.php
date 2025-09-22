<script>
    // This manages the common issue of enum rendering as a constant
    const {{ \App\Enums\ResourceType::CLIENT }} = '{{ \App\Enums\ResourceType::CLIENT }}'
    const {{ \App\Enums\ResourceType::CASE }} = '{{ \App\Enums\ResourceType::CASE }}'
    const {{ \App\Enums\ResourceType::SESSION }} = '{{ \App\Enums\ResourceType::SESSION }}'
    const {{ \App\Enums\VerificationStatus::PENDING }} =
        '{{ \App\Enums\VerificationStatus::PENDING }}'
    const {{ \App\Enums\VerificationStatus::VERIFIED }} =
        '{{ \App\Enums\VerificationStatus::VERIFIED }}'
    const {{ \App\Enums\VerificationStatus::FAILED }} =
        '{{ \App\Enums\VerificationStatus::FAILED }}'

    function verificationApp() {
        return {
            pageStatus: 'loading', // 'loading', 'idle', 'starting', 'in_progress', 'completed', 'failed', 'partial'
            errorModal: {
                title: '',
                resourceType: '',
                errors: []
            },
            initialised: false,
            migration: {},
            verificationStatus: {
                {{ \App\Enums\ResourceType::CLIENT }}: {
                    total: 0,
                    {{ \App\Enums\VerificationStatus::PENDING }}: [],
                    {{ \App\Enums\VerificationStatus::VERIFIED }}: [],
                    {{ \App\Enums\VerificationStatus::FAILED }}: [],
                },
                {{ \App\Enums\ResourceType::CASE }}: {
                    total: 0,
                    {{ \App\Enums\VerificationStatus::PENDING }}: [],
                    {{ \App\Enums\VerificationStatus::VERIFIED }}: [],
                    {{ \App\Enums\VerificationStatus::FAILED }}: [],
                },
                {{ \App\Enums\ResourceType::SESSION }}: {
                    total: 0,
                    {{ \App\Enums\VerificationStatus::PENDING }}: [],
                    {{ \App\Enums\VerificationStatus::VERIFIED }}: [],
                    {{ \App\Enums\VerificationStatus::FAILED }}: [],
                },
            },

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
                else return data
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
                else return data
            },

            async init() {
                // if app already initialised, return
                if (this.initialised) return

                // Do initial fetch of data and set state
                this.initialised = true
                const res = await Promise.all([this.getStatus(), this.getMigration()])
                console.debug('Initialised: ', res)
                this.pageStatus = 'idle'
            },

            async startVerification() {
                try {
                    console.log('starting verification...');
                } catch (error) {
                    console.error('Error:', error);
                    alert('Failed to start verification');
                }
            },

            getSuccessRateByResource(type) {
                const counts =
                    this.getVerificationCountsByResource(this.resolveResourceType(type))
                const v = counts.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0
                const t = counts?.total || 0
                if (!v || !t) return 0
                else return Math.round((v / t) * 10000) / 100
            },

            resolveResourceType(type) {
                if (!type) throw 'resource type not supported'

                // check for straight match
                if (type === {{ \App\Enums\ResourceType::CLIENT }})
                    return {{ \App\Enums\ResourceType::CLIENT }}
                if (type === {{ \App\Enums\ResourceType::CASE }})
                    return {{ \App\Enums\ResourceType::CASE }}
                if (type === {{ \App\Enums\ResourceType::SESSION }})
                    return {{ \App\Enums\ResourceType::SESSION }}

                // check for value string match
                const strType = String(type).toLowerCase()
                const rt = {
                    'client': {{ \App\Enums\ResourceType::CLIENT }},
                    'client': {{ \App\Enums\ResourceType::CLIENT }},
                    'clients': {{ \App\Enums\ResourceType::CLIENT }},
                    'case': {{ \App\Enums\ResourceType::CASE }},
                    'cases': {{ \App\Enums\ResourceType::CASE }},
                    'session': {{ \App\Enums\ResourceType::SESSION }},
                    'sessions': {{ \App\Enums\ResourceType::SESSION }},
                }
                return rt[strType]
            },

            get allVerificationStatuses() {
                return [
                    {{ \App\Enums\VerificationStatus::PENDING }},
                    {{ \App\Enums\VerificationStatus::VERIFIED }},
                    {{ \App\Enums\VerificationStatus::FAILED }},
                ]
            },

            getVerificationCountsByResource(resource) {
                const counts = {
                    total: this.verificationStatus[resource]?.total || 0
                }

                this.allVerificationStatuses.forEach(s => {
                    counts[s] = this.verificationStatus[resource]?.[s]?.length || 0
                });

                return counts
            },

            get allResources() {
                return [
                    {{ \App\Enums\ResourceType::CLIENT }},
                    {{ \App\Enums\ResourceType::CASE }},
                    {{ \App\Enums\ResourceType::SESSION }},
                ]
            },

            get verificationCounts() {
                return this.allResources.reduce((a, v) => {
                    a[v] = this.getVerificationCountsByResource(v)
                    return a
                }, {})
            },

            get totalCounts() {
                return [...this.allResources].reduce((a, v) => {
                    this.allVerificationStatuses.forEach(s => {
                        const vc = this.verificationCounts
                        a[s] = Number(a[s] || 0) + Number(vc[v][s] || 0)
                    })
                    return a
                }, {})
            },

            getStatusText() {
                switch (this.pageStatus) {
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
                switch (this.pageStatus) {
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
                switch (this.pageStatus) {
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

            get totalProcessed() {
                const tv = this.totalCounts?.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0
                const tf = this.totalCounts?.{{ \App\Enums\VerificationStatus::FAILED }} || 0
                return tv + tf
            },

            getProgressText() {
                return `${this.totalProcessed || 0} of ${this.migration?.total_items || 0} records processed`;
            },

            getProcessingProgress() {
                const total = this.migration?.total_items || 0;
                const processed = this.totalProcessed || 0;
                return total > 0 ? Math.round((processed / total) * 100) : 0;
            },

            getSuccessRate() {
                const total = this.migration?.total_items || 0;
                const verified = this.totalCounts?.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0;
                return total > 0 ? Math.round((verified / total) * 100) : 0;
            },

            getSuccessText() {
                const verified = this.totalCounts?.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0;
                const total = this.migration?.total_items || 0;
                return `${verified?.toLocaleString() || 0} of ${total?.toLocaleString() || 0} records verified`;
            },

            getProcessingBarClass() {
                // Processing progress: Blue for in progress, Green when complete
                if (this.pageStatus === 'in_progress' || this.pageStatus === 'starting') {
                    return 'progress-bar-striped progress-bar-animated bg-info';
                }
                const progress = this.getProcessingProgress();
                return progress >= 100 ? 'bg-success' : 'bg-info';
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

            getResourceCardClass(resourceType) {
                const rate = this.getSuccessRateByResource(resourceType.toLowerCase());
                if (rate >= 95) return 'border-success';
                if (rate >= 80) return 'border-warning';
                return 'border-danger';
            },

            getResourceStatusClass(resourceType) {
                const rate = this.getSuccessRateByResource(resourceType.toLowerCase());
                if (rate >= 95) return 'text-success';
                if (rate >= 80) return 'text-warning';
                return 'text-danger';
            },

            getResourceIcon(resourceType) {
                const rate = this.getSuccessRateByResource(resourceType.toLowerCase());
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
                const resolvedType = this.resolveResourceType(resourceType);
                const counts = this.verificationCounts[resolvedType] || { total: 0 };
                const verified = counts.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0;
                const total = counts.total || 0;
                return total > 0 ? Math.round((verified / total) * 100) : 0;
            },

            getResourceSuccessText(resourceType, progress) {
                const resolvedType = this.resolveResourceType(resourceType);
                const counts = this.verificationCounts[resolvedType] || { total: 0 };
                const verified = counts.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0;
                const total = counts.total || 0;
                return `${verified?.toLocaleString() || 0} of ${total?.toLocaleString() || 0} verified`;
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
                for (const resourceType of this.allResources) {
                    const counts = this.verificationCounts[resourceType] || {};
                    const failed = counts.{{ \App\Enums\VerificationStatus::FAILED }} || 0;
                    const pending = counts.{{ \App\Enums\VerificationStatus::PENDING }} || 0;

                    if (failed > 0 || pending > 0) {
                        return true;
                    }
                }
                return false;
            },

            hasNeverBeenVerified() {
                // Check if verification has never been started
                // This is true when all records are still in pending state (never attempted)

                for (const resourceType of this.allResources) {
                    const counts = this.verificationCounts[resourceType] || {};
                    const verified = counts.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0;
                    const failed = counts.{{ \App\Enums\VerificationStatus::FAILED }} || 0;

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
