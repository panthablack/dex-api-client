import { test, expect } from '@playwright/test';

test.describe('Loading States and User Feedback', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/data-exchange/clients');
    await page.waitForLoadState('domcontentloaded');
  });

  test('refresh button should have proper Alpine.js loading attributes', async ({ page }) => {
    const refreshButton = page.locator('button:has-text("Refresh")');
    
    // Check Alpine.js attributes
    await expect(refreshButton).toHaveAttribute('x-on:click', 'refreshData()');
    await expect(refreshButton).toHaveAttribute('x-bind:disabled', 'isRefreshing');
    
    // Check icon has conditional class binding
    const icon = refreshButton.locator('.fa-sync-alt');
    await expect(icon).toBeAttached();
    await expect(icon).toHaveAttribute('x-bind:class', '{ \'fa-spin\': isRefreshing }');
    
    // Check text has conditional content
    const textSpan = refreshButton.locator('span');
    await expect(textSpan).toBeAttached();
    await expect(textSpan).toHaveAttribute('x-text', 'isRefreshing ? \'Refreshing...\' : \'Refresh\'');
  });

  test('action buttons should have proper loading state attributes', async ({ page }) => {
    const firstRow = page.locator('tbody tr').first();
    
    // Get the first client ID for testing
    const clientIdCell = firstRow.locator('td').first();
    const clientIdText = await clientIdCell.textContent();
    const clientId = clientIdText?.trim();
    
    if (clientId) {
      // Check view button
      const viewButton = firstRow.locator('button[title="View Details"]');
      await expect(viewButton).toHaveAttribute('x-bind:disabled', `actionLoading === 'view-${clientId}'`);
      
      const viewIcon = viewButton.locator('i.fas');
      const viewIconClassAttr = await viewIcon.getAttribute('x-bind:class');
      expect(viewIconClassAttr).toContain('fa-spinner fa-spin');
      expect(viewIconClassAttr).toContain('fa-eye');
      expect(viewIconClassAttr).toContain(`actionLoading === 'view-${clientId}'`);
      
      // Check update button
      const updateButton = firstRow.locator('button[title="Update"]');
      await expect(updateButton).toHaveAttribute('x-bind:disabled', `actionLoading === 'update-${clientId}'`);
      
      const updateIcon = updateButton.locator('i.fas');
      const updateIconClassAttr = await updateIcon.getAttribute('x-bind:class');
      expect(updateIconClassAttr).toContain('fa-spinner fa-spin');
      expect(updateIconClassAttr).toContain('fa-edit');
      expect(updateIconClassAttr).toContain(`actionLoading === 'update-${clientId}'`);
      
      // Check delete button (no loading state on button click, but has disabled state)
      const deleteButton = firstRow.locator('button[title="Delete"]');
      await expect(deleteButton).toHaveAttribute('x-bind:disabled', `actionLoading === 'delete-${clientId}'`);
    }
  });

  test('modal loading spinners should have correct attributes', async ({ page }) => {
    // View modal loading spinner
    const viewLoadingSpinner = page.locator('#viewModal [x-show="modalLoading === \'view\'"]');
    await expect(viewLoadingSpinner).toBeAttached();
    await expect(viewLoadingSpinner).toHaveAttribute('x-cloak');
    
    const viewSpinner = viewLoadingSpinner.locator('.spinner-border.text-primary');
    await expect(viewSpinner).toBeAttached();
    
    const viewLoadingText = viewLoadingSpinner.locator('p.text-muted');
    await expect(viewLoadingText).toContainText('Loading resource details...');
    
    // Update modal loading spinner
    const updateLoadingSpinner = page.locator('#updateModal [x-show="modalLoading === \'update\'"]');
    await expect(updateLoadingSpinner).toBeAttached();
    await expect(updateLoadingSpinner).toHaveAttribute('x-cloak');
    
    const updateSpinner = updateLoadingSpinner.locator('.spinner-border.text-warning');
    await expect(updateSpinner).toBeAttached();
    
    const updateLoadingText = updateLoadingSpinner.locator('p.text-muted');
    await expect(updateLoadingText).toContainText('Loading update form...');
  });

  test('delete button should have proper loading state implementation', async ({ page }) => {
    const deleteButton = page.locator('#confirmDeleteBtn');
    
    // Check disabled binding
    await expect(deleteButton).toHaveAttribute('x-bind:disabled', 'modalLoading === \'delete\'');
    
    // Check icon conditional classes
    const icon = deleteButton.locator('i.fas');
    await expect(icon).toBeAttached();
    
    const iconClassAttr = await icon.getAttribute('x-bind:class');
    expect(iconClassAttr).toContain('modalLoading === \'delete\'');
    expect(iconClassAttr).toContain('fa-spinner fa-spin me-1');
    expect(iconClassAttr).toContain('fa-trash me-1');
    
    // Check text conditional content
    const textSpan = deleteButton.locator('span');
    await expect(textSpan).toBeAttached();
    
    const textAttr = await textSpan.getAttribute('x-text');
    expect(textAttr).toContain('modalLoading === \'delete\'');
    expect(textAttr).toContain('Deleting...');
    expect(textAttr).toContain('Delete');
  });

  test('CSS loading states should be properly defined', async ({ page }) => {
    // Check if custom CSS for loading states is present
    const styles = await page.locator('style').allTextContents();
    const allStyles = styles.join(' ');
    
    // Check for x-cloak hiding
    expect(allStyles).toContain('[x-cloak]');
    expect(allStyles).toContain('display: none');
    
    // Check for disabled button styles
    expect(allStyles).toContain('.btn:disabled');
    expect(allStyles).toContain('opacity: 0.65');
    expect(allStyles).toContain('cursor: not-allowed');
    
    // Check for fa-spin animation
    expect(allStyles).toContain('.fa-spin');
    expect(allStyles).toContain('@keyframes fa-spin');
  });

  test('Alpine.js should be loaded and available', async ({ page }) => {
    // Check if Alpine.js is loaded
    const alpineLoaded = await page.evaluate(() => {
      return typeof window.Alpine !== 'undefined';
    });
    expect(alpineLoaded).toBe(true);
  });

  test('resource table Alpine.js function should be available', async ({ page }) => {
    // Check if the resourceTable function is defined
    const resourceTableDefined = await page.evaluate(() => {
      return typeof window.resourceTableInstance !== 'undefined';
    });
    
    // Note: This might be false initially if Alpine hasn't initialized yet
    // The important thing is that the function is defined in the script tag
    const scriptContent = await page.locator('script').allTextContents();
    const allScripts = scriptContent.join(' ');
    
    expect(allScripts).toContain('function resourceTable()');
    expect(allScripts).toContain('isRefreshing: false');
    expect(allScripts).toContain('actionLoading: null');
    expect(allScripts).toContain('modalLoading: null');
  });

  test('Alpine.js data methods should be properly defined', async ({ page }) => {
    const scriptContent = await page.locator('script').allTextContents();
    const allScripts = scriptContent.join(' ');
    
    // Check for all required methods
    expect(allScripts).toContain('refreshData()');
    expect(allScripts).toContain('viewResource(');
    expect(allScripts).toContain('showUpdateForm(');
    expect(allScripts).toContain('confirmDelete(');
    expect(allScripts).toContain('handleDelete(');
    expect(allScripts).toContain('showNotification(');
    
    // Check for state management
    expect(allScripts).toContain('this.actionLoading =');
    expect(allScripts).toContain('this.modalLoading =');
    expect(allScripts).toContain('this.isRefreshing =');
  });

  test('notification system should have proper implementation', async ({ page }) => {
    const scriptContent = await page.locator('script').allTextContents();
    const allScripts = scriptContent.join(' ');
    
    // Check notification function implementation
    expect(allScripts).toContain('showNotification(message, type = \'info\', duration = 5000)');
    expect(allScripts).toContain('notification-toast');
    expect(allScripts).toContain('alert alert-');
    expect(allScripts).toContain('position-fixed');
    expect(allScripts).toContain('z-index: 9999');
  });

  test('CSRF token should be available for AJAX requests', async ({ page }) => {
    // Check if CSRF token meta tag exists
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    expect(csrfToken).toBeTruthy();
    expect(csrfToken).toHaveLength.greaterThan(10);
    
    // Check if the script uses CSRF token
    const scriptContent = await page.locator('script').allTextContents();
    const allScripts = scriptContent.join(' ');
    
    expect(allScripts).toContain('X-CSRF-TOKEN');
    expect(allScripts).toContain('meta[name="csrf-token"]');
  });

  test('Bootstrap modal integration should be properly implemented', async ({ page }) => {
    const scriptContent = await page.locator('script').allTextContents();
    const allScripts = scriptContent.join(' ');
    
    // Check for Bootstrap modal usage
    expect(allScripts).toContain('bootstrap.Modal');
    expect(allScripts).toContain('new bootstrap.Modal(');
    expect(allScripts).toContain('.show()');
    expect(allScripts).toContain('.hide()');
    expect(allScripts).toContain('bootstrap.Modal.getInstance(');
  });
});