import { test, expect } from '@playwright/test';

test.describe('Data Migration Verification', () => {
  // Setup: Visit the migration detail page before each test
  test.beforeEach(async ({ page }) => {
    // Navigate to the migration detail page - assuming migration ID 1 exists
    await page.goto('/data-migration/1');
    
    // Wait for the page to load and show the migration status
    await expect(page.locator('h1.h2.text-primary')).toBeVisible();
  });

  test.describe('Quick Verification Modal', () => {
    test('should open Quick Verify modal with loading state', async ({ page }) => {
      // Mock the Quick Verify API to simulate loading
      await page.route('/data-migration/api/1/quick-verify', async (route) => {
        // Minimal delay to ensure loading state is visible, then respond immediately
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              sample_size: 10,
              results: {
                clients: {
                  total_checked: 10,
                  verified: 8,
                  failed: 2,
                  success_rate: 80,
                  status: 'completed'
                }
              }
            }
          })
        });
      });

      // Click the Quick Verify button
      await page.click('button:has-text("Quick Verify")');
      
      // Verify modal opens with loading state
      await expect(page.locator('#quick-verify-modal')).toBeVisible();
      await expect(page.locator('.spinner-border')).toBeVisible();
      await expect(page.locator('h5:has-text("Verifying Data...")')).toBeVisible();
      
      // Wait for loading to complete and verify results are shown
      await expect(page.locator('.spinner-border')).not.toBeVisible({ timeout: 5000 });
      await expect(page.locator('h6:has-text("Sample Size: 10")')).toBeVisible();
    });

    test('should display verification results correctly', async ({ page }) => {
      // Mock successful verification response
      await page.route('/data-migration/api/1/quick-verify', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              sample_size: 10,
              results: {
                clients: {
                  total_checked: 10,
                  verified: 8,
                  failed: 2,
                  success_rate: 80,
                  status: 'completed'
                },
                cases: {
                  total_checked: 5,
                  verified: 5,
                  failed: 0,
                  success_rate: 100,
                  status: 'completed'
                }
              }
            }
          })
        });
      });

      await page.click('button:has-text("Quick Verify")');
      
      // Wait for modal to show results
      await expect(page.locator('#quick-verify-modal')).toBeVisible();
      await expect(page.locator('h6:has-text("Sample Size: 10")')).toBeVisible();
      
      // Verify clients card shows correct information
      const clientsCard = page.locator('#quick-verify-modal .card:has(.card-title:has-text("Clients"))');
      await expect(clientsCard).toBeVisible();
      await expect(clientsCard.locator(':has-text("8/10 verified (80%)")')).toBeVisible();
      await expect(clientsCard.locator('p.card-text.text-warning:has-text("8/10 verified (80%)")')).toBeVisible(); // 80% should show warning
      
      // Verify cases card shows correct information
      const casesCard = page.locator('#quick-verify-modal .card:has(.card-title:has-text("Cases"))');
      await expect(casesCard).toBeVisible();
      await expect(casesCard.locator(':has-text("5/5 verified (100%)")')).toBeVisible();
      await expect(casesCard.locator('p.card-text.text-success:has-text("5/5 verified (100%)")')).toBeVisible(); // 100% should show success
    });

    test('should handle verification errors gracefully', async ({ page }) => {
      // Mock API error response
      await page.route('/data-migration/api/1/quick-verify', async (route) => {
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            error: 'DSS API connection failed'
          })
        });
      });

      await page.click('button:has-text("Quick Verify")');
      
      // Verify error is displayed in modal
      await expect(page.locator('#quick-verify-modal')).toBeVisible();
      await expect(page.locator('h5:has-text("Verification Failed")')).toBeVisible();
      await expect(page.locator(':has-text("Error: DSS API connection failed")')).toBeVisible();
    });

    test('should handle no data to verify', async ({ page }) => {
      // Mock response with no results
      await page.route('/data-migration/api/1/quick-verify', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              sample_size: 10,
              results: {}
            }
          })
        });
      });

      await page.click('button:has-text("Quick Verify")');
      
      // Verify "no results" message is shown
      await expect(page.locator('#quick-verify-modal')).toBeVisible();
      await expect(page.locator('h5:has-text("No verification results available")')).toBeVisible();
      await expect(page.locator(':has-text("No data was found to verify")')).toBeVisible();
    });

    test('should properly close modal without backdrop issues', async ({ page }) => {
      // Mock quick response
      await page.route('/data-migration/api/1/quick-verify', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              sample_size: 10,
              results: {
                clients: {
                  total_checked: 10,
                  verified: 10,
                  failed: 0,
                  success_rate: 100,
                  status: 'completed'
                }
              }
            }
          })
        });
      });

      await page.click('button:has-text("Quick Verify")');
      await expect(page.locator('#quick-verify-modal')).toBeVisible();
      
      // Close the modal using the close button
      await page.click('#quick-verify-modal .btn-close');
      
      // Verify modal is hidden and no backdrop remains
      await expect(page.locator('#quick-verify-modal')).not.toBeVisible();
      await expect(page.locator('.modal-backdrop')).not.toBeVisible();
      
      // Verify page is still interactive (no backdrop blocking clicks)
      await expect(page.locator('button:has-text("Quick Verify")')).toBeEnabled();
      await page.click('button:has-text("Refresh")'); // Should be clickable
    });

    test('should navigate to Full Verification from modal', async ({ page }) => {
      // Mock quick response
      await page.route('/data-migration/api/1/quick-verify', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              sample_size: 10,
              results: {
                clients: {
                  total_checked: 10,
                  verified: 8,
                  failed: 2,
                  success_rate: 80,
                  status: 'completed'
                }
              }
            }
          })
        });
      });

      await page.click('button:has-text("Quick Verify")');
      await expect(page.locator('#quick-verify-modal')).toBeVisible();
      
      // Click "Run Full Verification" button
      await page.click('a:has-text("Run Full Verification")');
      
      // Verify navigation to full verification page
      await expect(page).toHaveURL('/data-migration/1/verification');
    });
  });

  test.describe('Full Verification Page', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/data-migration/1/verification');
    });

    test('should display full verification page correctly', async ({ page }) => {
      // Verify page elements are present
      await expect(page.locator('h1:has-text("Full Verification")')).toBeVisible();
      await expect(page.locator('button:has-text("Start Full Verification")')).toBeVisible();
      await expect(page.locator('.card:has-text("About Full Verification")')).toBeVisible();
    });

    test('should start full verification process', async ({ page }) => {
      // Mock the full verify API
      await page.route('/data-migration/api/1/full-verify', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              verification_id: '1_1234567890',
              status: 'starting',
              message: 'Full verification started. Check status for progress.'
            }
          })
        });
      });

      // Mock the status check API
      await page.route('/data-migration/api/1/verification-status*', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              status: 'in_progress',
              total: 100,
              processed: 25,
              verified: 20,
              current_activity: 'Processing clients...'
            }
          })
        });
      });

      await page.click('button:has-text("Start Full Verification")');
      
      // Verify verification status card appears
      await expect(page.locator('#verification-status-card')).toBeVisible();
      await expect(page.locator('#verification-status-badge:has-text("Starting...")')).toBeVisible();
      await expect(page.locator('.spinner-border')).toBeVisible();
      
      // Wait for progress to show
      await expect(page.locator(':has-text("Processing clients...")')).toBeVisible();
      await expect(page.locator('#progress-text:has-text("25 of 100")')).toBeVisible();
    });

    test('should show completed verification results', async ({ page }) => {
      // Mock starting the verification
      await page.route('/data-migration/api/1/full-verify', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              verification_id: '1_1234567890',
              status: 'starting'
            }
          })
        });
      });

      // Mock completed verification status
      await page.route('/data-migration/api/1/verification-status*', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              status: 'completed',
              total: 100,
              processed: 100,
              verified: 95,
              results: {
                clients: {
                  total: 100,
                  verified: 95,
                  errors: ['Client 123: Name mismatch', 'Client 456: Missing data']
                }
              }
            }
          })
        });
      });

      await page.click('button:has-text("Start Full Verification")');
      
      // Wait for completion
      await expect(page.locator('#verification-status-badge:has-text("Completed")')).toBeVisible({ timeout: 10000 });
      
      // Verify results are displayed
      await expect(page.locator('#verification-results')).toBeVisible();
      await expect(page.locator('.card:has(.card-title:has-text("Clients"))')).toBeVisible();
      await expect(page.locator(':has-text("95% Success")')).toBeVisible();
    });

    test('should handle verification errors', async ({ page }) => {
      // Mock API error
      await page.route('/data-migration/api/1/full-verify', async (route) => {
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            error: 'Migration is not in completed state'
          })
        });
      });

      await page.click('button:has-text("Start Full Verification")');
      
      // Verify error handling - button should be re-enabled
      await expect(page.locator('button:has-text("Start Full Verification")')).toBeEnabled();
      
      // Check for error message (this would depend on your error handling implementation)
      // You might show an alert or toast notification
    });
  });

  test.describe('Verification Button Visibility', () => {
    test('should show verification buttons only for completed migrations', async ({ page }) => {
      // Navigate to a completed migration
      await page.goto('/data-migration/1');
      
      // Verify buttons are visible for completed migrations
      await expect(page.locator('button:has-text("Quick Verify")')).toBeVisible();
      await expect(page.locator('a:has-text("Full Verification")')).toBeVisible();
    });
    
    test('should hide verification buttons for pending migrations', async ({ page }) => {
      // You might need to create a pending migration or mock the response
      // This test assumes you have a way to test with different migration statuses
      
      // Mock the migration status API to return a pending migration
      await page.route('/data-migration/api/1/status', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              status: 'pending',
              progress_percentage: 0,
              // ... other fields
            }
          })
        });
      });
      
      await page.goto('/data-migration/1');
      
      // Verify verification buttons are not visible for pending migrations
      await expect(page.locator('button:has-text("Quick Verify")')).not.toBeVisible();
      await expect(page.locator('a:has-text("Full Verification")')).not.toBeVisible();
    });
  });

  test.describe('API Integration Tests', () => {
    test('should handle real API responses gracefully', async ({ page }) => {
      // This test doesn't mock the API - it tests against the real endpoints
      // Make sure your test environment has proper data
      
      await page.click('button:has-text("Quick Verify")');
      
      // Wait for either success or error response
      await page.waitForSelector('#quick-verify-modal', { state: 'visible' });
      
      // The modal should either show results or an error - both are valid outcomes
      const hasResults = await page.locator('h6:has-text("Sample Size:")').isVisible();
      const hasError = await page.locator('h5:has-text("Verification Failed")').isVisible();
      
      expect(hasResults || hasError).toBeTruthy();
    });
  });
});