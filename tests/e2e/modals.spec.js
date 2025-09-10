import { test, expect } from '@playwright/test';

test.describe('Modal Interactions', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/data-exchange/clients');
    await page.waitForLoadState('domcontentloaded');
  });

  test('should have all modal elements present in DOM', async ({ page }) => {
    // Check that all modals exist (even if hidden)
    await expect(page.locator('#viewModal')).toBeAttached();
    await expect(page.locator('#updateModal')).toBeAttached();
    await expect(page.locator('#deleteModal')).toBeAttached();
  });

  test('view modal should have correct structure', async ({ page }) => {
    const viewModal = page.locator('#viewModal');
    
    // Check modal structure
    await expect(viewModal.locator('.modal-dialog')).toBeAttached();
    await expect(viewModal.locator('.modal-content')).toBeAttached();
    await expect(viewModal.locator('.modal-header')).toBeAttached();
    await expect(viewModal.locator('.modal-body')).toBeAttached();
    
    // Check modal title
    await expect(viewModal.locator('.modal-title')).toContainText('View');
    
    // Check loading spinner elements
    const loadingSpinner = viewModal.locator('[x-show="modalLoading === \'view\'"]');
    await expect(loadingSpinner).toBeAttached();
    await expect(loadingSpinner.locator('.spinner-border')).toBeAttached();
    
    // Check content area
    await expect(viewModal.locator('#viewModalContent')).toBeAttached();
  });

  test('update modal should have correct structure', async ({ page }) => {
    const updateModal = page.locator('#updateModal');
    
    // Check modal structure
    await expect(updateModal.locator('.modal-dialog')).toBeAttached();
    await expect(updateModal.locator('.modal-content')).toBeAttached();
    await expect(updateModal.locator('.modal-header')).toBeAttached();
    await expect(updateModal.locator('.modal-body')).toBeAttached();
    
    // Check modal title
    await expect(updateModal.locator('.modal-title')).toContainText('Update');
    
    // Check loading spinner elements
    const loadingSpinner = updateModal.locator('[x-show="modalLoading === \'update\'"]');
    await expect(loadingSpinner).toBeAttached();
    await expect(loadingSpinner.locator('.spinner-border.text-warning')).toBeAttached();
    
    // Check content area
    await expect(updateModal.locator('#updateModalContent')).toBeAttached();
  });

  test('delete modal should have correct structure', async ({ page }) => {
    const deleteModal = page.locator('#deleteModal');
    
    // Check modal structure
    await expect(deleteModal.locator('.modal-dialog')).toBeAttached();
    await expect(deleteModal.locator('.modal-content')).toBeAttached();
    await expect(deleteModal.locator('.modal-header')).toBeAttached();
    await expect(deleteModal.locator('.modal-body')).toBeAttached();
    await expect(deleteModal.locator('.modal-footer')).toBeAttached();
    
    // Check modal title
    await expect(deleteModal.locator('.modal-title')).toContainText('Confirm Delete');
    
    // Check delete confirmation text
    await expect(deleteModal.locator('.modal-body')).toContainText('Are you sure you want to delete');
    await expect(deleteModal.locator('.text-danger')).toContainText('This action cannot be undone');
    
    // Check buttons
    const cancelButton = deleteModal.locator('button:has-text("Cancel")');
    const deleteButton = deleteModal.locator('#confirmDeleteBtn');
    
    await expect(cancelButton).toBeAttached();
    await expect(deleteButton).toBeAttached();
    await expect(deleteButton).toHaveAttribute('x-bind:disabled', 'modalLoading === \'delete\'');
  });

  test('clicking view button should trigger Alpine.js function', async ({ page }) => {
    // Get the first row's view button
    const firstRow = page.locator('tbody tr').first();
    const viewButton = firstRow.locator('button[title="View Details"]');
    
    // Check that the button has the correct Alpine.js attributes
    const onClickAttr = await viewButton.getAttribute('x-on:click');
    expect(onClickAttr).toContain('viewResource');
    expect(onClickAttr).toContain('client');
    
    // Check disabled binding
    const disabledAttr = await viewButton.getAttribute('x-bind:disabled');
    expect(disabledAttr).toContain('actionLoading');
  });

  test('clicking update button should trigger Alpine.js function', async ({ page }) => {
    // Get the first row's update button
    const firstRow = page.locator('tbody tr').first();
    const updateButton = firstRow.locator('button[title="Update"]');
    
    // Check that the button has the correct Alpine.js attributes
    const onClickAttr = await updateButton.getAttribute('x-on:click');
    expect(onClickAttr).toContain('showUpdateForm');
    expect(onClickAttr).toContain('client');
    
    // Check disabled binding
    const disabledAttr = await updateButton.getAttribute('x-bind:disabled');
    expect(disabledAttr).toContain('actionLoading');
  });

  test('clicking delete button should trigger Alpine.js function', async ({ page }) => {
    // Get the first row's delete button
    const firstRow = page.locator('tbody tr').first();
    const deleteButton = firstRow.locator('button[title="Delete"]');
    
    // Check that the button has the correct Alpine.js attributes
    const onClickAttr = await deleteButton.getAttribute('x-on:click');
    expect(onClickAttr).toContain('confirmDelete');
    expect(onClickAttr).toContain('client');
    
    // Check disabled binding
    const disabledAttr = await deleteButton.getAttribute('x-bind:disabled');
    expect(disabledAttr).toContain('actionLoading');
  });

  test('modals should have Bootstrap modal attributes', async ({ page }) => {
    // Check Bootstrap modal attributes
    const viewModal = page.locator('#viewModal');
    const updateModal = page.locator('#updateModal');
    const deleteModal = page.locator('#deleteModal');
    
    // Check modal classes and attributes
    await expect(viewModal).toHaveClass(/modal/);
    await expect(viewModal).toHaveClass(/fade/);
    await expect(viewModal).toHaveAttribute('tabindex', '-1');
    await expect(viewModal).toHaveAttribute('aria-hidden', 'true');
    
    await expect(updateModal).toHaveClass(/modal/);
    await expect(updateModal).toHaveClass(/fade/);
    await expect(updateModal).toHaveAttribute('tabindex', '-1');
    await expect(updateModal).toHaveAttribute('aria-hidden', 'true');
    
    await expect(deleteModal).toHaveClass(/modal/);
    await expect(deleteModal).toHaveClass(/fade/);
    await expect(deleteModal).toHaveAttribute('tabindex', '-1');
    await expect(deleteModal).toHaveAttribute('aria-hidden', 'true');
  });

  test('modal close buttons should have correct attributes', async ({ page }) => {
    // Check view modal close button
    const viewCloseButton = page.locator('#viewModal .btn-close');
    await expect(viewCloseButton).toHaveAttribute('data-bs-dismiss', 'modal');
    
    // Check update modal close button
    const updateCloseButton = page.locator('#updateModal .btn-close');
    await expect(updateCloseButton).toHaveAttribute('data-bs-dismiss', 'modal');
    
    // Check delete modal close button
    const deleteCloseButton = page.locator('#deleteModal .btn-close');
    await expect(deleteCloseButton).toHaveAttribute('data-bs-dismiss', 'modal');
  });

  test('Alpine.js x-cloak should be present on loading elements', async ({ page }) => {
    // Check that loading spinners have x-cloak attribute
    const viewLoadingSpinner = page.locator('#viewModal [x-show="modalLoading === \'view\'"]');
    await expect(viewLoadingSpinner).toHaveAttribute('x-cloak');
    
    const updateLoadingSpinner = page.locator('#updateModal [x-show="modalLoading === \'update\'"]');
    await expect(updateLoadingSpinner).toHaveAttribute('x-cloak');
  });

  test('delete button should have loading state attributes', async ({ page }) => {
    const deleteButton = page.locator('#confirmDeleteBtn');
    
    // Check Alpine.js attributes for loading state
    const disabledAttr = await deleteButton.getAttribute('x-bind:disabled');
    expect(disabledAttr).toBe('modalLoading === \'delete\'');
    
    // Check for icon with conditional class binding
    const icon = deleteButton.locator('i.fas');
    await expect(icon).toBeAttached();
    
    const iconClassAttr = await icon.getAttribute('x-bind:class');
    expect(iconClassAttr).toContain('fa-spinner fa-spin');
    expect(iconClassAttr).toContain('fa-trash');
    
    // Check for text with conditional content
    const textSpan = deleteButton.locator('span');
    await expect(textSpan).toBeAttached();
    
    const textAttr = await textSpan.getAttribute('x-text');
    expect(textAttr).toContain('Deleting...');
    expect(textAttr).toContain('Delete');
  });

  test('should verify Alpine.js data attribute on main container', async ({ page }) => {
    // Check that the main card has Alpine.js data binding
    const card = page.locator('.card[x-data]');
    await expect(card).toBeAttached();
    
    const xDataAttr = await card.getAttribute('x-data');
    expect(xDataAttr).toBe('resourceTable()');
  });
});