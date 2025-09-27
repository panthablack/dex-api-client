<div x-show="allResources && allResources.length > 0" class="row">
    <template x-for="resourceType in allResources" :key="resourceType">
        <div class="col-md-4 mb-4">
            <div class="card h-100" :class="getResourceCardClass(resourceType)">
                <div class="card-body text-center">
                    <h5 class="card-title text-capitalize" x-text="resourceType.toLowerCase()"></h5>
                    <div class="mb-3" style="font-size: 3rem;" :class="getResourceStatusClass(resourceType)"
                        x-text="getResourceIcon(resourceType)">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="h6 mb-0" x-text="(verificationCounts[resourceType]?.VERIFIED || 0).toLocaleString()"></div>
                            <small class="text-muted">Verified</small>
                        </div>
                        <div class="col-6">
                            <div class="h6 mb-0" x-text="(verificationCounts[resourceType]?.total || 0).toLocaleString()"></div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span :class="getResourceStatusClass(resourceType)" class="fw-bold"
                            x-text="getSuccessRateByResource(resourceType.toLowerCase()) + '% Success'">
                        </span>
                    </div>
                    <button x-show="(verificationCounts[resourceType]?.FAILED || 0) > 0"
                        @click="showErrorDetails(resourceType.toLowerCase(), [`${verificationCounts[resourceType]?.FAILED || 0} verification failures found`])"
                        class="btn btn-outline-danger btn-sm mt-2" x-text="`View ${verificationCounts[resourceType]?.FAILED || 0} Errors`">
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
