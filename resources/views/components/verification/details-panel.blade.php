<div x-show="verification.results && Object.keys(verification.results).length > 0" class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Verification Details</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    <template x-for="([resourceType], index) in Object.entries(verification.results || {})"
                        :key="resourceType">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" :class="{ 'active': index === 0 }" :id="`${resourceType}-tab`"
                                data-bs-toggle="tab" :data-bs-target="`#${resourceType}-content`" type="button"
                                role="tab" x-text="resourceType.charAt(0).toUpperCase() + resourceType.slice(1)">
                            </button>
                        </li>
                    </template>
                </ul>
                <div class="tab-content mt-3">
                    <template x-for="([resourceType, result], index) in Object.entries(verification.results || {})"
                        :key="resourceType">
                        <div class="tab-pane fade" :class="{ 'show active': index === 0 }"
                            :id="`${resourceType}-content`" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Verification Summary</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Total Records:</strong> <span
                                                x-text="result.total?.toLocaleString() || '0'"></span></li>
                                        <li><strong>Verified:</strong> <span
                                                x-text="result.verified?.toLocaleString() || '0'"></span></li>
                                        <li><strong>Failed:</strong> <span
                                                x-text="((result.total || 0) - (result.verified || 0)).toLocaleString()"></span>
                                        </li>
                                        <li><strong>Success Rate:</strong> <span
                                                x-text="getResultSuccessRate(result) + '%'"></span></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Common Issues</h6>
                                    <template x-if="result.errors && result.errors.length > 0">
                                        <ul class="list-group list-group-flush">
                                            <template x-for="(error, errorIndex) in result.errors.slice(0, 5)"
                                                :key="errorIndex">
                                                <li class="list-group-item px-0 py-1">
                                                    <small x-text="error"></small>
                                                </li>
                                            </template>
                                            <li x-show="result.errors.length > 5" class="list-group-item px-0 py-1">
                                                <small class="text-muted"
                                                    x-text="`...and ${result.errors.length - 5} more`"></small>
                                            </li>
                                        </ul>
                                    </template>
                                    <template x-if="!result.errors || result.errors.length === 0">
                                        <p class="text-muted">No issues found</p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
