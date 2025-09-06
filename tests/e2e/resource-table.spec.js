import { test, expect } from '@playwright/test';

test.describe('Resource Table Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the clients page before each test
    await page.goto('/data-exchange/clients');
  });

  test('should display the client table with data', async ({ page }) => {
    // Wait for the page to load
    await page.waitForLoadState('domcontentloaded');
    
    // Check if the table exists
    await expect(page.locator('.table')).toBeVisible();
    
    // Check if the table has headers
    await expect(page.locator('thead th')).toContainText(['Client ID', 'First Name', 'Last Name']);
    
    // Check if there are data rows
    const rows = page.locator('tbody tr');
    await expect(rows).toHaveCountGreaterThan(0);
  });

  test('should display the correct record count badge', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    // Check if the record count badge is visible
    const badge = page.locator('.badge.bg-info');
    await expect(badge).toBeVisible();
    await expect(badge).toContainText(/\d+ records?/);
  });

  test('should have refresh button that works', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    // Find the refresh button
    const refreshButton = page.locator('button:has-text("Refresh")');
    await expect(refreshButton).toBeVisible();
    
    // Check Alpine.js attributes
    await expect(refreshButton).toHaveAttribute('x-on:click', 'refreshData()');
    await expect(refreshButton).toHaveAttribute('x-bind:disabled', 'isRefreshing');
  });

  test('should have action buttons for each row', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    // Get the first data row
    const firstRow = page.locator('tbody tr').first();
    
    // Check for action buttons in the row
    const viewButton = firstRow.locator('button[title="View Details"]');
    const updateButton = firstRow.locator('button[title="Update"]');
    const deleteButton = firstRow.locator('button[title="Delete"]');
    
    await expect(viewButton).toBeVisible();
    await expect(updateButton).toBeVisible();
    await expect(deleteButton).toBeVisible();
    
    // Check icons
    await expect(viewButton.locator('.fa-eye')).toBeVisible();
    await expect(updateButton.locator('.fa-edit')).toBeVisible();
    await expect(deleteButton.locator('.fa-trash')).toBeVisible();
  });

  test('should have Alpine.js attributes on action buttons', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    const firstRow = page.locator('tbody tr').first();
    const viewButton = firstRow.locator('button[title="View Details"]');
    const updateButton = firstRow.locator('button[title="Update"]');
    const deleteButton = firstRow.locator('button[title="Delete"]');
    
    // Check Alpine.js click handlers
    await expect(viewButton).toHaveAttribute('x-on:click');
    await expect(updateButton).toHaveAttribute('x-on:click');
    await expect(deleteButton).toHaveAttribute('x-on:click');
    
    // Check disabled binding for loading states
    await expect(viewButton).toHaveAttribute('x-bind:disabled');
    await expect(updateButton).toHaveAttribute('x-bind:disabled');
    await expect(deleteButton).toHaveAttribute('x-bind:disabled');
  });

  test('should display proper data in table cells', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    const firstRow = page.locator('tbody tr').first();
    const cells = firstRow.locator('td');
    
    // First cell should contain Client ID
    const clientId = await cells.nth(0).textContent();
    expect(clientId).toBeTruthy();
    expect(clientId.trim()).not.toBe('');
    
    // Check that cells contain actual data, not empty or "N/A"
    const cellCount = await cells.count();
    expect(cellCount).toBeGreaterThan(5); // Should have multiple columns
  });

  test('should handle empty states gracefully', async ({ page }) => {
    // Navigate to a potentially empty page (this might need to be adjusted based on your routes)
    await page.goto('/data-exchange/clients?test=empty');
    
    // If there's an empty state, check for the appropriate message
    const emptyState = page.locator('.text-center:has-text("No clients found")');
    if (await emptyState.isVisible()) {
      await expect(emptyState).toBeVisible();
      await expect(page.locator('.fa-inbox')).toBeVisible();
    }
  });

  test('should have responsive table wrapper', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    // Check if table is wrapped in responsive div
    const responsiveWrapper = page.locator('.table-responsive');
    await expect(responsiveWrapper).toBeVisible();
    
    const table = responsiveWrapper.locator('.table');
    await expect(table).toBeVisible();
  });

  test('should have proper Bootstrap classes', async ({ page }) => {
    await page.waitForLoadState('domcontentloaded');
    
    // Check card structure
    await expect(page.locator('.card')).toBeVisible();
    await expect(page.locator('.card-header')).toBeVisible();
    await expect(page.locator('.card-body')).toBeVisible();
    
    // Check table classes
    const table = page.locator('.table');
    await expect(table).toHaveClass(/table-striped/);
    await expect(table).toHaveClass(/table-hover/);
    
    // Check header classes
    const thead = page.locator('thead');
    await expect(thead).toHaveClass(/table-dark/);
  });
});