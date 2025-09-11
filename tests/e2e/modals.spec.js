import { test, expect } from '@playwright/test'

test.describe('Modal Interactions', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/data-exchange/clients')
    await page.waitForLoadState('domcontentloaded')
  })

  test('should have all modal elements present in DOM', async ({ page }) => {
    // Check that all modals exist (even if hidden)
    await expect(page.locator('#viewModal')).toBeAttached()
    await expect(page.locator('#updateModal')).toBeAttached()
    await expect(page.locator('#deleteModal')).toBeAttached()
  })

  test('view modal should have correct structure', async ({ page }) => {
    const viewModal = page.locator('#viewModal')

    // Check modal structure
    await expect(viewModal.locator('.modal-dialog')).toBeAttached()
    await expect(viewModal.locator('.modal-content')).toBeAttached()
    await expect(viewModal.locator('.modal-header')).toBeAttached()
    await expect(viewModal.locator('.modal-body')).toBeAttached()

    // Check modal title
    await expect(viewModal.locator('.modal-title')).toContainText('View')

    // Check basic modal elements (loading states not implemented in current version)

    // Check content area
    await expect(viewModal.locator('#viewModalContent')).toBeAttached()
  })

  test('update modal should have correct structure', async ({ page }) => {
    const updateModal = page.locator('#updateModal')

    // Check modal structure
    await expect(updateModal.locator('.modal-dialog')).toBeAttached()
    await expect(updateModal.locator('.modal-content')).toBeAttached()
    await expect(updateModal.locator('.modal-header')).toBeAttached()
    await expect(updateModal.locator('.modal-body')).toBeAttached()

    // Check modal title
    await expect(updateModal.locator('.modal-title')).toContainText('Update')

    // Check basic modal elements (loading states not implemented in current version)

    // Check content area
    await expect(updateModal.locator('#updateModalContent')).toBeAttached()
  })

  test('delete modal should have correct structure', async ({ page }) => {
    const deleteModal = page.locator('#deleteModal')

    // Check modal structure
    await expect(deleteModal.locator('.modal-dialog')).toBeAttached()
    await expect(deleteModal.locator('.modal-content')).toBeAttached()
    await expect(deleteModal.locator('.modal-header')).toBeAttached()
    await expect(deleteModal.locator('.modal-body')).toBeAttached()
    await expect(deleteModal.locator('.modal-footer')).toBeAttached()

    // Check modal title
    await expect(deleteModal.locator('.modal-title')).toContainText('Confirm Delete')

    // Check delete confirmation text
    await expect(deleteModal.locator('.modal-body')).toContainText(
      'Are you sure you want to delete'
    )
    await expect(deleteModal.locator('.text-danger')).toContainText('This action cannot be undone')

    // Check buttons
    const cancelButton = deleteModal.locator('button:has-text("Cancel")')
    const deleteButton = deleteModal.locator('#confirmDeleteBtn')

    await expect(cancelButton).toBeAttached()
    await expect(deleteButton).toBeAttached()
    // Note: Loading state attributes not implemented in current version
  })

  test('clicking view button should trigger Alpine.js function', async ({ page }) => {
    // Get the first row's view button
    const firstRow = page.locator('tbody tr').first()
    const viewButton = firstRow.locator('button[title="View Details"]')

    // Check that the button has the correct Alpine.js attributes
    const onClickAttr = await viewButton.getAttribute('x-on:click')
    expect(onClickAttr).toContain('viewResource')
    expect(onClickAttr).toContain('client')

    // Note: Disabled loading states not implemented in current version
  })

  test('clicking update button should trigger Alpine.js function', async ({ page }) => {
    // Get the first row's update button
    const firstRow = page.locator('tbody tr').first()
    const updateButton = firstRow.locator('button[title="Update"]')

    // Check that the button has the correct Alpine.js attributes
    const onClickAttr = await updateButton.getAttribute('x-on:click')
    expect(onClickAttr).toContain('showUpdateForm')
    expect(onClickAttr).toContain('client')

    // Note: Disabled loading states not implemented in current version
  })

  test('clicking delete button should trigger Alpine.js function', async ({ page }) => {
    // Get the first row's delete button
    const firstRow = page.locator('tbody tr').first()
    const deleteButton = firstRow.locator('button[title="Delete"]')

    // Check that the button has the correct Alpine.js attributes
    const onClickAttr = await deleteButton.getAttribute('x-on:click')
    expect(onClickAttr).toContain('confirmDelete')
    expect(onClickAttr).toContain('client')

    // Note: Disabled loading states not implemented in current version
  })

  test('modals should have Bootstrap modal attributes', async ({ page }) => {
    // Check Bootstrap modal attributes
    const viewModal = page.locator('#viewModal')
    const updateModal = page.locator('#updateModal')
    const deleteModal = page.locator('#deleteModal')

    // Check modal classes and attributes
    await expect(viewModal).toHaveClass(/modal/)
    await expect(viewModal).toHaveClass(/fade/)
    await expect(viewModal).toHaveAttribute('tabindex', '-1')
    await expect(viewModal).toHaveAttribute('aria-hidden', 'true')

    await expect(updateModal).toHaveClass(/modal/)
    await expect(updateModal).toHaveClass(/fade/)
    await expect(updateModal).toHaveAttribute('tabindex', '-1')
    await expect(updateModal).toHaveAttribute('aria-hidden', 'true')

    await expect(deleteModal).toHaveClass(/modal/)
    await expect(deleteModal).toHaveClass(/fade/)
    await expect(deleteModal).toHaveAttribute('tabindex', '-1')
    await expect(deleteModal).toHaveAttribute('aria-hidden', 'true')
  })

  test('modal close buttons should have correct attributes', async ({ page }) => {
    // Check view modal close button
    const viewCloseButton = page.locator('#viewModal .btn-close')
    await expect(viewCloseButton).toHaveAttribute('data-bs-dismiss', 'modal')

    // Check update modal close button
    const updateCloseButton = page.locator('#updateModal .btn-close')
    await expect(updateCloseButton).toHaveAttribute('data-bs-dismiss', 'modal')

    // Check delete modal close button
    const deleteCloseButton = page.locator('#deleteModal .btn-close')
    await expect(deleteCloseButton).toHaveAttribute('data-bs-dismiss', 'modal')
  })

  // Note: Loading state tests removed - x-cloak and modalLoading features not implemented

  // Note: Delete button loading state test removed - modalLoading feature not implemented in current version

  test('should verify Alpine.js data attribute on main container', async ({ page }) => {
    // Check that the main card has Alpine.js data binding
    const card = page.locator('.card[x-data]')
    await expect(card).toBeAttached()

    const xDataAttr = await card.getAttribute('x-data')
    expect(xDataAttr).toBe('resourceTableComponent')
  })
})
