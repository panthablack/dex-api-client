<div x-show="allResources && allResources.length > 0" class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Verification Details</h5>
      </div>
      <div class="card-body">
        <ul class="nav nav-tabs" role="tablist">
          <template x-for="(resourceType, index) in allResources" :key="resourceType">
            <li class="nav-item" role="presentation">
              <button class="nav-link" :class="{ 'active': index === 0 }" :id="`${resourceType}-tab`"
                data-bs-toggle="tab" :data-bs-target="`#${resourceType}-content`" type="button" role="tab"
                x-text="resourceType.charAt(0).toUpperCase() + resourceType.slice(1).toLowerCase()">
              </button>
            </li>
          </template>
        </ul>
        <div class="tab-content mt-3">
          <template x-for="(resourceType, index) in allResources" :key="resourceType">
            <div class="tab-pane fade" :class="{ 'show active': index === 0 }" :id="`${resourceType}-content`"
              role="tabpanel">
              <div class="row">
                <div class="col-md-6">
                  <h6>Verification Summary</h6>
                  <ul class="list-unstyled">
                    <li>
                      <strong>Total Records:</strong>
                      <span x-text="verificationCounts[resourceType]?.total || 0"></span>
                    </li>
                    <li>
                      <strong>Pending:</strong>
                      <span
                        x-text="verificationCounts[resourceType]?.{{ \App\Enums\VerificationStatus::PENDING }} || 0"></span>
                    </li>
                    <li>
                      <strong>Verified:</strong>
                      <span
                        x-text="verificationCounts[resourceType]?.{{ \App\Enums\VerificationStatus::VERIFIED }} || 0"></span>
                    </li>
                    <li>
                      <strong>Failed:</strong>
                      <span
                        x-text="verificationCounts[resourceType]?.{{ \App\Enums\VerificationStatus::FAILED }} || 0"></span>
                    </li>
                    <li>
                      <strong>Success Rate:</strong>
                      <span x-text="getSuccessRateByResource(resourceType.toLowerCase()) + '%'"></span>
                    </li>
                  </ul>
                </div>
                <div class="col-md-6">
                  <h6>Common Issues</h6>
                  <template
                    x-if="(verificationCounts[resourceType]?.{{ \App\Enums\VerificationStatus::FAILED }} || 0) > 0">
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item px-0 py-1">
                        <small
                          x-text="`${verificationCounts[resourceType]?.{{ \App\Enums\VerificationStatus::FAILED }} || 0} verification failures found`"></small>
                      </li>
                    </ul>
                  </template>
                  <template
                    x-if="(verificationCounts[resourceType]?.{{ \App\Enums\VerificationStatus::FAILED }} || 0) === 0">
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
