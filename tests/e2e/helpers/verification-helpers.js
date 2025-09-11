/**
 * Helper functions for verification-related tests
 */
import { expect } from '@playwright/test'

/**
 * Mock successful quick verification API response
 */
export function mockQuickVerifySuccess(page, results = null) {
  const defaultResults = {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 10,
        verified: 8,
        failed: 2,
        success_rate: 80,
        status: 'completed',
      },
    },
  }

  return page.route('/data-migration/api/*/quick-verify', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: results || defaultResults,
      }),
    })
  })
}

/**
 * Mock quick verification API error response
 */
export function mockQuickVerifyError(page, errorMessage = 'Verification service unavailable') {
  return page.route('/data-migration/api/*/quick-verify', async route => {
    await route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: JSON.stringify({
        success: false,
        error: errorMessage,
      }),
    })
  })
}

/**
 * Mock full verification start API response
 */
export function mockFullVerifyStart(page, verificationId = '1_1234567890') {
  return page.route('/data-migration/api/*/full-verify', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          verification_id: verificationId,
          status: 'starting',
          message: 'Full verification started. Check status for progress.',
        },
      }),
    })
  })
}

/**
 * Mock verification status API response
 */
export function mockVerificationStatus(page, statusData = null) {
  const defaultStatus = {
    status: 'in_progress',
    total: 100,
    processed: 50,
    verified: 45,
    current_activity: 'Processing clients...',
  }

  return page.route('/data-migration/api/*/verification-status*', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: statusData || defaultStatus,
      }),
    })
  })
}

/**
 * Mock completed verification status with results
 */
export function mockVerificationCompleted(page, results = null) {
  const defaultResults = {
    status: 'completed',
    total: 100,
    processed: 100,
    verified: 95,
    results: {
      clients: {
        total: 100,
        verified: 95,
        errors: ['Client 123: Name mismatch', 'Client 456: Missing data'],
      },
    },
  }

  return mockVerificationStatus(page, results || defaultResults)
}

/**
 * Wait for modal to be visible and loading to complete
 */
export async function waitForModalReady(page, modalId = '#quick-verify-modal') {
  await page.waitForSelector(modalId, { state: 'visible' })

  // Wait for loading spinner to disappear (if present)
  try {
    await page.waitForSelector('.spinner-border', { state: 'hidden', timeout: 5000 })
  } catch (e) {
    // Spinner might not be present, continue
  }

  // Wait for actual content to be loaded instead of relying only on spinner
  const contentSelector = `${modalId} #verify-results-content`
  try {
    await page.waitForFunction(
      selector => {
        const element = document.querySelector(selector)
        return element && element.textContent && element.textContent.trim().length > 0
      },
      contentSelector,
      { timeout: 10000 }
    )
  } catch (e) {
    // Content might not load, but continue - let the test handle this
  }
}

/**
 * Assert verification results are displayed correctly
 */
export async function assertVerificationResults(page, expectedResults) {
  for (const [resourceType, result] of Object.entries(expectedResults)) {
    const card = page.locator(
      `#quick-verify-modal .card:has(.card-title:has-text("${
        resourceType.charAt(0).toUpperCase() + resourceType.slice(1)
      }"))`
    )
    await expect(card).toBeVisible()

    const expectedText = `${result.verified}/${result.total_checked} verified (${result.success_rate}%)`
    await expect(card.locator(`p.card-text:has-text("${expectedText}")`)).toBeVisible()

    // Check status color based on success rate by targeting the percentage text specifically
    if (result.success_rate >= 95) {
      await expect(
        card.locator(`p.card-text.text-success:has-text("${expectedText}")`)
      ).toBeVisible()
    } else if (result.success_rate >= 80) {
      await expect(
        card.locator(`p.card-text.text-warning:has-text("${expectedText}")`)
      ).toBeVisible()
    } else {
      await expect(
        card.locator(`p.card-text.text-danger:has-text("${expectedText}")`)
      ).toBeVisible()
    }
  }
}

/**
 * Create test data for verification
 */
export const createVerificationTestData = {
  /**
   * Perfect verification results (100% success)
   */
  perfect: {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 10,
        verified: 10,
        failed: 0,
        success_rate: 100,
        status: 'completed',
      },
    },
  },

  /**
   * Good verification results (95% success)
   */
  good: {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 10,
        verified: 9,
        failed: 1,
        success_rate: 95,
        status: 'completed',
      },
    },
  },

  /**
   * Warning verification results (80% success)
   */
  warning: {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 10,
        verified: 8,
        failed: 2,
        success_rate: 80,
        status: 'completed',
      },
    },
  },

  /**
   * Poor verification results (50% success)
   */
  poor: {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 10,
        verified: 5,
        failed: 5,
        success_rate: 50,
        status: 'completed',
      },
    },
  },

  /**
   * Multiple resource types
   */
  multipleResources: {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 10,
        verified: 9,
        failed: 1,
        success_rate: 90,
        status: 'completed',
      },
      cases: {
        total_checked: 8,
        verified: 8,
        failed: 0,
        success_rate: 100,
        status: 'completed',
      },
      sessions: {
        total_checked: 5,
        verified: 3,
        failed: 2,
        success_rate: 60,
        status: 'completed',
      },
    },
  },

  /**
   * No data to verify
   */
  noData: {
    sample_size: 10,
    results: {},
  },

  /**
   * Resource with no_data status
   */
  noDataStatus: {
    sample_size: 10,
    results: {
      clients: {
        total_checked: 0,
        verified: 0,
        failed: 0,
        success_rate: 0,
        status: 'no_data',
      },
    },
  },
}

/**
 * Full verification progress simulation
 */
export function simulateFullVerificationProgress(page, stages = null) {
  const defaultStages = [
    {
      status: 'starting',
      total: 100,
      processed: 0,
      verified: 0,
      current_activity: 'Initializing verification...',
    },
    {
      status: 'in_progress',
      total: 100,
      processed: 25,
      verified: 22,
      current_activity: 'Processing clients...',
    },
    {
      status: 'in_progress',
      total: 100,
      processed: 75,
      verified: 70,
      current_activity: 'Processing cases...',
    },
    {
      status: 'completed',
      total: 100,
      processed: 100,
      verified: 95,
      current_activity: 'Verification completed',
      results: {
        clients: {
          total: 60,
          verified: 58,
          errors: ['Client 123: Name mismatch'],
        },
        cases: {
          total: 40,
          verified: 37,
          errors: ['Case 456: Missing data', 'Case 789: Invalid date'],
        },
      },
    },
  ]

  const stages_to_use = stages || defaultStages
  let currentStage = 0

  return page.route('/data-migration/api/*/verification-status*', async route => {
    const stageData = stages_to_use[Math.min(currentStage, stages_to_use.length - 1)]

    // Advance to next stage for subsequent calls
    if (currentStage < stages_to_use.length - 1) {
      currentStage++
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: stageData,
      }),
    })
  })
}
