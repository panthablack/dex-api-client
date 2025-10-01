<script>
  /**
   * Data Migration Status Constants
   * These constants match the PHP DataMigrationStatus enum values
   */
  const DataMigrationStatus = {
    CANCELLED: 'CANCELLED',
    COMPLETED: 'COMPLETED',
    FAILED: 'FAILED',
    IN_PROGRESS: 'IN_PROGRESS',
    PENDING: 'PENDING',
  }

  /**
   * Data Migration Batch Status Constants
   * These constants match the PHP DataMigrationBatchStatus enum values
   */
  const DataMigrationBatchStatus = {
    IN_PROGRESS: 'IN_PROGRESS',
    COMPLETED: 'COMPLETED',
    FAILED: 'FAILED',
    PENDING: 'PENDING',
  }

  /**
   * Status to CSS class mappings for consistent styling
   */
  const StatusColorMappings = {
    [DataMigrationStatus.PENDING]: 'bg-secondary',
    [DataMigrationStatus.IN_PROGRESS]: 'bg-warning',
    [DataMigrationStatus.COMPLETED]: 'bg-success',
    [DataMigrationStatus.FAILED]: 'bg-danger',
    [DataMigrationStatus.CANCELLED]: 'bg-secondary',

    [DataMigrationBatchStatus.PENDING]: 'bg-secondary',
    [DataMigrationBatchStatus.IN_PROGRESS]: 'bg-warning',
    [DataMigrationBatchStatus.COMPLETED]: 'bg-success',
    [DataMigrationBatchStatus.FAILED]: 'bg-danger',
  }

  /**
   * Helper function to get CSS class for a status
   * @param {string} status - The status value
   * @returns {string} The corresponding CSS class
   */
  function getStatusClass(status) {
    return StatusColorMappings[status] || 'bg-light'
  }

  /**
   * Helper function to check if a status is "active" (pending or in progress)
   * @param {string} status - The status value
   * @returns {boolean} True if status indicates active processing
   */
  function isActiveStatus(status) {
    return [DataMigrationStatus.PENDING, DataMigrationStatus.IN_PROGRESS].includes(status)
  }

  /**
   * Helper function to check if a status is "completed" (finished successfully)
   * @param {string} status - The status value
   * @returns {boolean} True if status indicates successful completion
   */
  function isCompletedStatus(status) {
    return status === DataMigrationStatus.COMPLETED || status === DataMigrationBatchStatus.COMPLETED
  }

  /**
   * Helper function to check if a status is "failed"
   * @param {string} status - The status value
   * @returns {boolean} True if status indicates failure
   */
  function isFailedStatus(status) {
    return status === DataMigrationStatus.FAILED || status === DataMigrationBatchStatus.FAILED
  }
</script>
