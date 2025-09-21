<div x-show="verification.results && Object.keys(verification.results).length > 0" class="row">
    <template x-for="[resourceType, result] in Object.entries(verification.results || {})" :key="resourceType">
        <div class="col-md-4 mb-4">
            <div class="card h-100" :class="getResultCardClass(result)">
                <div class="card-body text-center">
                    <h5 class="card-title text-capitalize" x-text="resourceType"></h5>
                    <div class="mb-3" style="font-size: 3rem;" :class="getResultStatusClass(result)"
                        x-text="getResultIcon(result)">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="h6 mb-0" x-text="result.verified?.toLocaleString() || '0'"></div>
                            <small class="text-muted">Verified</small>
                        </div>
                        <div class="col-6">
                            <div class="h6 mb-0" x-text="result.total?.toLocaleString() || '0'"></div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span :class="getResultStatusClass(result)" class="fw-bold"
                            x-text="getResultSuccessRate(result) + '% Success'">
                        </span>
                    </div>
                    <button x-show="result.errors && result.errors.length > 0"
                        @click="showErrorDetails(resourceType, result.errors)"
                        class="btn btn-outline-danger btn-sm mt-2" x-text="`View ${result.errors?.length || 0} Errors`">
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
