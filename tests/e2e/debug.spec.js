import { test, expect } from '@playwright/test';

test.describe('Debug Tests', () => {
  test('should access the base URL and take screenshot', async ({ page }) => {
    console.log('Base URL from config:', process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000');
    
    // Try explicit URL first
    const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
    console.log('Attempting to navigate to:', baseUrl);
    
    try {
      await page.goto(baseUrl);
      console.log('Successfully navigated to:', baseUrl);
    } catch (error) {
      console.log('Failed to navigate to base URL:', error.message);
      // Try direct port specification
      await page.goto('http://app:80/');
    }
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    
    // Take a screenshot for debugging
    await page.screenshot({ path: 'debug-home.png', fullPage: true });
    
    // Log the page content
    const title = await page.title();
    const url = page.url();
    console.log('Page title:', title);
    console.log('Current URL:', url);
    
    // Check for any error messages
    const body = await page.textContent('body');
    console.log('Page body preview:', body?.substring(0, 500));
    
    // Verify we can see some expected content
    await expect(page.locator('body')).not.toBeEmpty();
  });
});