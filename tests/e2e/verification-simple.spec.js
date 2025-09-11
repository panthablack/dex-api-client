import { test, expect } from '@playwright/test';

test.describe('Verification Simple Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/data-migration/1');
  });

  test('Quick Verify should show meaningful content or proper no-data message', async ({ page }) => {
    // Click Quick Verify
    await page.click('button:has-text("Quick Verify")');
    
    // Wait for modal to appear
    await expect(page.locator('#quick-verify-modal')).toBeVisible();
    
    // Wait for loading to complete
    try {
      await page.waitForSelector('.spinner-border', { state: 'hidden', timeout: 10000 });
    } catch (e) {
      console.log('No spinner found or timeout - checking content anyway');
    }
    
    // Wait for actual content to appear instead of arbitrary timeout
    await expect(page.locator('#verify-results-content')).not.toBeEmpty();
    
    // Check that we get some kind of meaningful content
    const modalContent = await page.locator('#verify-results-content').textContent();
    
    console.log('Modal content:', modalContent);
    
    // We should see meaningful content: either results, proper no-data message, or error
    const hasResults = modalContent.includes('Sample Size') && modalContent.includes('verified');
    const hasProperNoData = modalContent.includes('Sample Size') && modalContent.includes('No data to verify');
    const hasProperError = modalContent.includes('Verification Failed') && modalContent.includes('Error:');
    
    expect(hasResults || hasProperNoData || hasProperError).toBeTruthy();
    
    // Ensure we have sample size information regardless of data presence
    expect(modalContent).toContain('Sample Size');
    
    // Take screenshot for debugging
    await page.screenshot({ path: 'quick-verify-result.png' });
  });

  test('Quick Verify modal should close without backdrop issues', async ({ page }) => {
    // Open modal
    await page.click('button:has-text("Quick Verify")');
    await expect(page.locator('#quick-verify-modal')).toBeVisible();
    
    // Wait for modal content to be fully loaded
    await expect(page.locator('#verify-results-content')).not.toBeEmpty();
    
    // Close modal with X button
    await page.click('#quick-verify-modal .btn-close');
    
    // Verify modal is closed
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible();
    
    // Verify no backdrop remains
    await expect(page.locator('.modal-backdrop')).not.toBeVisible();
    
    // Verify page is still interactive
    await page.click('button:has-text("Refresh")');
    
    // Open modal again to verify it still works
    await page.click('button:has-text("Quick Verify")');
    await expect(page.locator('#quick-verify-modal')).toBeVisible();
    
    // Close with footer close button
    await page.click('#quick-verify-modal button:has-text("Close")');
    await expect(page.locator('#quick-verify-modal')).not.toBeVisible();
  });
});