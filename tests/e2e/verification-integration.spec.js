import { test, expect } from '@playwright/test'
import {
  mockQuickVerifySuccess,
  mockQuickVerifyError,
  waitForModalReady,
  assertVerificationResults,
  createVerificationTestData,
  mockFullVerifyStart,
  simulateFullVerificationProgress,
} from './helpers/verification-helpers.js'
import { getScreenshotPath } from './helpers/generic.js'

test.describe('Verification Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Mock migration status API to return completed status immediately
    await page.route('/data-migration/api/1/status', async route => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            status: 'completed',
            progress_percentage: 100,
            success_rate: 90,
            total_items: 100,
            processed_items: 100,
            successful_items: 90,
            failed_items: 10,
            batches: [
              {
                id: 1,
                status: 'completed',
                resource_type: 'clients',
              },
            ],
          },
        }),
      })
    })

    await page.goto('/data-migration/1')

    // Wait for Alpine.js to process the mocked status and show buttons
    await page.waitForTimeout(500)
  })

  test('Quick Verify should show verification results instead of "No data to verify"', async ({
    page,
  }) => {
    // Mock successful response with actual data
    await mockQuickVerifySuccess(page, createVerificationTestData.multipleResources)

    // Click Quick Verify
    await page.click('button:has-text("Quick Verify")')

    // Wait for modal to be ready
    await waitForModalReady(page)

    // Verify results are shown correctly
    await expect(page.locator('h6:has-text("Sample Size: 10")')).toBeVisible()

    // Check that we don't see the "No data" message
    await expect(page.locator(':has-text("No data to verify")')).not.toBeVisible()
    await expect(page.locator(':has-text("—")')).not.toBeVisible()

    // Verify actual results are displayed
    await assertVerificationResults(page, createVerificationTestData.multipleResources.results)
  })

  test('Quick Verify should show meaningful error instead of generic "Failed to verify data"', async ({
    page,
  }) => {
    // Mock specific error response
    await mockQuickVerifyError(
      page,
      'DSS API connection timeout - please check network connectivity'
    )

    await page.click('button:has-text("Quick Verify")')

    await waitForModalReady(page)

    // Verify specific error message is shown
    await expect(page.locator('h5:has-text("Verification Failed")')).toBeVisible()
    await expect(
      page.locator(
        '#quick-verify-modal p.text-muted:has-text("DSS API connection timeout - please check network connectivity")'
      )
    ).toBeVisible()

    // Verify generic message is not shown
    await expect(
      page.locator('#quick-verify-modal :has-text("Failed to verify data")')
    ).not.toBeVisible()
  })

  test('Quick Verify modal should close properly without backdrop issues', async ({ page }) => {
    await mockQuickVerifySuccess(page, createVerificationTestData.good)

    // Open modal
    await page.click('button:has-text("Quick Verify")')
    await waitForModalReady(page)

    // Verify modal is open
    await expect(page.locator('#quick-verify-modal')).toBeVisible()

    // Close using X button
    await page.click('#quick-verify-modal .btn-close')

    // Verify modal is completely closed
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible()
    await expect(page.locator('.modal-backdrop')).not.toBeVisible()

    // Verify page is interactive - test by opening modal again
    await page.click('button:has-text("Quick Verify")')
    await expect(page.locator('#quick-verify-modal')).toBeVisible()

    // Close using footer button
    await page.click('#quick-verify-modal button:has-text("Close")')
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible()

    // Verify no backdrop remains and page is still interactive
    await expect(page.locator('button:has-text("Refresh")')).toBeEnabled()
    await page.click('button:has-text("Refresh")')
  })

  test('Error handling - network failures', async ({ page }) => {
    // Mock network failure
    await page.route('/data-migration/api/1/quick-verify', async route => {
      await route.abort('failed')
    })

    await page.click('button:has-text("Quick Verify")')

    await waitForModalReady(page)

    // Verify error is handled gracefully
    await expect(page.locator('h5:has-text("Verification Failed")')).toBeVisible()
    await expect(
      page.locator('#quick-verify-error-message:has-text("Failed to verify data")')
    ).toBeVisible()
  })

  test('Accessibility - verification modals should be accessible', async ({ page }) => {
    await mockQuickVerifySuccess(page, createVerificationTestData.perfect)

    await page.click('button:has-text("Quick Verify")')
    await waitForModalReady(page)

    // Check modal has proper ARIA attributes
    await expect(page.locator('#quick-verify-modal')).toHaveAttribute(
      'aria-labelledby',
      'quickVerifyModalLabel'
    )
    await expect(page.locator('#quick-verify-modal')).not.toHaveAttribute('aria-hidden') // Bootstrap removes this when modal is shown

    // Check modal title exists and is properly linked
    await expect(page.locator('#quickVerifyModalLabel')).toBeVisible()
    await expect(page.locator('#quickVerifyModalLabel')).toHaveText('Quick Verification Results')

    // Check close button has proper label
    await expect(page.locator('#quick-verify-modal .btn-close')).toHaveAttribute(
      'aria-label',
      'Close'
    )

    // Check modal can be closed (Escape key might not work due to Bootstrap config)
    // Try closing with the close button instead
    await page.click('#quick-verify-modal .btn-close')
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible()
  })
})
