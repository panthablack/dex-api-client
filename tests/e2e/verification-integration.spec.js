import { test, expect } from '@playwright/test';
import { 
  mockQuickVerifySuccess, 
  mockQuickVerifyError, 
  waitForModalReady, 
  assertVerificationResults,
  createVerificationTestData,
  mockFullVerifyStart,
  simulateFullVerificationProgress
} from './helpers/verification-helpers.js';

test.describe('Verification Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/data-migration/1');
  });

  test('Quick Verify should show verification results instead of "No data to verify"', async ({ page }) => {
    // Mock successful response with actual data
    await mockQuickVerifySuccess(page, createVerificationTestData.multipleResources);
    
    // Click Quick Verify
    await page.click('button:has-text("Quick Verify")');
    
    // Wait for modal to be ready
    await waitForModalReady(page);
    
    // Verify results are shown correctly
    await expect(page.locator('h6:has-text("Sample Size: 10")')).toBeVisible();
    
    // Check that we don't see the "No data" message
    await expect(page.locator(':has-text("No data to verify")')).not.toBeVisible();
    await expect(page.locator(':has-text("—")')).not.toBeVisible();
    
    // Verify actual results are displayed
    await assertVerificationResults(page, createVerificationTestData.multipleResources.results);
  });

  test('Quick Verify should show meaningful error instead of generic "Failed to verify data"', async ({ page }) => {
    // Mock specific error response
    await mockQuickVerifyError(page, 'DSS API connection timeout - please check network connectivity');
    
    await page.click('button:has-text("Quick Verify")');
    
    await waitForModalReady(page);
    
    // Verify specific error message is shown
    await expect(page.locator('h5:has-text("Verification Failed")')).toBeVisible();
    await expect(page.locator(':has-text("DSS API connection timeout - please check network connectivity")')).toBeVisible();
    
    // Verify generic message is not shown
    await expect(page.locator(':has-text("Failed to verify data")')).not.toBeVisible();
  });

  test('Quick Verify modal should close properly without backdrop issues', async ({ page }) => {
    await mockQuickVerifySuccess(page, createVerificationTestData.good);
    
    // Open modal
    await page.click('button:has-text("Quick Verify")');
    await waitForModalReady(page);
    
    // Verify modal is open
    await expect(page.locator('#quick-verify-modal')).toBeVisible();
    
    // Close using X button
    await page.click('#quick-verify-modal .btn-close');
    
    // Verify modal is completely closed
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible();
    await expect(page.locator('.modal-backdrop')).not.toBeVisible();
    
    // Verify page is interactive - test by opening modal again
    await page.click('button:has-text("Quick Verify")');
    await expect(page.locator('#quick-verify-modal')).toBeVisible();
    
    // Close using footer button
    await page.click('#quick-verify-modal button:has-text("Close")');
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible();
    
    // Verify no backdrop remains and page is still interactive
    await expect(page.locator('button:has-text("Refresh")')).toBeEnabled();
    await page.click('button:has-text("Refresh")');
  });

  test('Full Verification should show real-time progress', async ({ page }) => {
    // Navigate to full verification page
    await page.goto('/data-migration/1/verification');
    
    // Mock the full verification flow
    await mockFullVerifyStart(page);
    await simulateFullVerificationProgress(page);
    
    // Start verification
    await page.click('button:has-text("Start Full Verification")');
    
    // Verify status card appears
    await expect(page.locator('#verification-status-card')).toBeVisible();
    
    // Verify loading state
    await expect(page.locator('#verification-status-badge:has-text("Starting...")')).toBeVisible();
    
    // Wait for progress updates
    await expect(page.locator(':has-text("Processing clients...")')).toBeVisible({ timeout: 10000 });
    
    // Verify progress statistics update
    await expect(page.locator('#verified-records')).toContainText('22');
    await expect(page.locator('#total-records')).toContainText('100');
    
    // Wait for completion
    await expect(page.locator('#verification-status-badge:has-text("Completed")')).toBeVisible({ timeout: 15000 });
    
    // Verify results are shown
    await expect(page.locator('#verification-results')).toBeVisible();
    await expect(page.locator('.card:has(.card-title:has-text("Clients"))')).toBeVisible();
    await expect(page.locator('.card:has(.card-title:has-text("Cases"))')).toBeVisible();
  });

  test('Verification should work with real API endpoints', async ({ page }) => {
    // This test runs against actual API endpoints without mocking
    console.log('Testing against real API endpoints...');
    
    // Click Quick Verify and wait for real response
    await page.click('button:has-text("Quick Verify")');
    
    // Wait for modal to appear
    await expect(page.locator('#quick-verify-modal')).toBeVisible();
    
    // Wait for loading to complete (max 30 seconds)
    try {
      await page.waitForSelector('.spinner-border', { state: 'hidden', timeout: 30000 });
    } catch (e) {
      console.log('No spinner found or timeout waiting for spinner to hide');
    }
    
    // Check what we got - either results or error
    const hasResults = await page.locator('.card .card-title').count() > 0;
    const hasError = await page.locator('h5:has-text("Verification Failed")').isVisible();
    const hasSampleSize = await page.locator('h6:has-text("Sample Size:")').isVisible();
    const hasNoData = await page.locator(':has-text("No verification results available")').isVisible();
    
    console.log(`Results: ${hasResults}, Error: ${hasError}, Sample Size: ${hasSampleSize}, No Data: ${hasNoData}`);
    
    // We should have one of these outcomes
    expect(hasResults || hasError || hasNoData).toBeTruthy();
    
    // If we have results, verify they're properly formatted
    if (hasResults) {
      await expect(page.locator('h6:has-text("Sample Size:")')).toBeVisible();
      
      // At least one resource type should be shown
      const resourceCards = await page.locator('.card .card-title').count();
      expect(resourceCards).toBeGreaterThan(0);
    }
    
    // Take screenshot for debugging
    await page.screenshot({ path: 'verification-real-api-result.png' });
  });

  test('Error handling - network failures', async ({ page }) => {
    // Mock network failure
    await page.route('/data-migration/api/1/quick-verify', async (route) => {
      await route.abort('failed');
    });
    
    await page.click('button:has-text("Quick Verify")');
    
    await waitForModalReady(page);
    
    // Verify error is handled gracefully
    await expect(page.locator('h5:has-text("Verification Failed")')).toBeVisible();
    await expect(page.locator(':has-text("Failed to verify data")')).toBeVisible();
  });

  test('Performance - Quick Verify should respond within reasonable time', async ({ page }) => {
    await mockQuickVerifySuccess(page, createVerificationTestData.perfect);
    
    const startTime = Date.now();
    
    await page.click('button:has-text("Quick Verify")');
    
    // Wait for modal to show results
    await waitForModalReady(page);
    await expect(page.locator('h6:has-text("Sample Size:")')).toBeVisible();
    
    const endTime = Date.now();
    const duration = endTime - startTime;
    
    // Should respond within 5 seconds (generous for integration test)
    expect(duration).toBeLessThan(5000);
    
    console.log(`Quick Verify completed in ${duration}ms`);
  });

  test('Accessibility - verification modals should be accessible', async ({ page }) => {
    await mockQuickVerifySuccess(page, createVerificationTestData.perfect);
    
    await page.click('button:has-text("Quick Verify")');
    await waitForModalReady(page);
    
    // Check modal has proper ARIA attributes
    await expect(page.locator('#quick-verify-modal')).toHaveAttribute('aria-labelledby', 'quickVerifyModalLabel');
    await expect(page.locator('#quick-verify-modal')).not.toHaveAttribute('aria-hidden'); // Bootstrap removes this when modal is shown
    
    // Check modal title exists and is properly linked
    await expect(page.locator('#quickVerifyModalLabel')).toBeVisible();
    await expect(page.locator('#quickVerifyModalLabel')).toHaveText('Quick Verification Results');
    
    // Check close button has proper label
    await expect(page.locator('#quick-verify-modal .btn-close')).toHaveAttribute('aria-label', 'Close');
    
    // Check keyboard navigation works
    await page.keyboard.press('Escape');
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible();
  });
});