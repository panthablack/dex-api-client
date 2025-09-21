/**
 * Data Migration Status Constants
 * These constants match the PHP DataMigrationStatus enum values
 */
export const DataMigrationStatus = {
    CANCELLED: 'CANCELLED',
    COMPLETED: 'COMPLETED',
    FAILED: 'FAILED',
    IN_PROGRESS: 'IN_PROGRESS',
    PENDING: 'PENDING',
    UNKNOWN: 'UNKNOWN'
};

/**
 * Data Migration Batch Status Constants
 * These constants match the PHP DataMigrationBatchStatus enum values
 */
export const DataMigrationBatchStatus = {
    IN_PROGRESS: 'IN_PROGRESS',
    COMPLETED: 'COMPLETED',
    FAILED: 'FAILED',
    PENDING: 'PENDING',
    UNKNOWN: 'UNKNOWN'
};

/**
 * Status to CSS class mappings for consistent styling
 */
export const StatusColorMappings = {
    [DataMigrationStatus.PENDING]: 'bg-secondary',
    [DataMigrationStatus.IN_PROGRESS]: 'bg-warning',
    [DataMigrationStatus.COMPLETED]: 'bg-success',
    [DataMigrationStatus.FAILED]: 'bg-danger',
    [DataMigrationStatus.CANCELLED]: 'bg-secondary',
    [DataMigrationStatus.UNKNOWN]: 'bg-light',

    [DataMigrationBatchStatus.PENDING]: 'bg-secondary',
    [DataMigrationBatchStatus.IN_PROGRESS]: 'bg-warning',
    [DataMigrationBatchStatus.COMPLETED]: 'bg-success',
    [DataMigrationBatchStatus.FAILED]: 'bg-danger',
    [DataMigrationBatchStatus.UNKNOWN]: 'bg-light'
};

/**
 * Helper function to get CSS class for a status
 * @param {string} status - The status value
 * @returns {string} The corresponding CSS class
 */
export function getStatusClass(status) {
    return StatusColorMappings[status] || 'bg-light';
}

/**
 * Helper function to check if a status is "active" (pending or in progress)
 * @param {string} status - The status value
 * @returns {boolean} True if status indicates active processing
 */
export function isActiveStatus(status) {
    return [DataMigrationStatus.PENDING, DataMigrationStatus.IN_PROGRESS].includes(status);
}

/**
 * Helper function to check if a status is "completed" (finished successfully)
 * @param {string} status - The status value
 * @returns {boolean} True if status indicates successful completion
 */
export function isCompletedStatus(status) {
    return status === DataMigrationStatus.COMPLETED || status === DataMigrationBatchStatus.COMPLETED;
}

/**
 * Helper function to check if a status is "failed"
 * @param {string} status - The status value
 * @returns {boolean} True if status indicates failure
 */
export function isFailedStatus(status) {
    return status === DataMigrationStatus.FAILED || status === DataMigrationBatchStatus.FAILED;
}