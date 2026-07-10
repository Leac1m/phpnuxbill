import { test, expect } from '@playwright/test';

test.describe('Router Auto-Provisioning Flow', () => {
  
  test('should generate token, display modal, and start polling', async ({ page }) => {
    // 1. Admin Login (assumes default admin credentials in test environment)
    await page.goto('http://localhost/?_route=login');
    // PHPNuxBill login form has username and password fields
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'admin'); 
    await page.click('button[type="submit"]');

    // 2. Navigate to Routers list
    await page.goto('http://localhost/?_route=routers/list');
    
    // 3. Click the Auto-Provision button
    // It should have the text "Auto-Provision"
    await page.click('text=Auto-Provision');

    // 4. Verify Modal is visible
    const modal = page.locator('#provisionModal');
    await expect(modal).toBeVisible();
    
    // 5. Verify the textarea is populated with the /tool fetch command
    const textarea = page.locator('#provisionCmd');
    // Playwright automatically waits for the AJAX request to populate the field
    await expect(textarea).toContainText('/tool fetch url=');
    await expect(textarea).toContainText('setup.rsc');
    
    // 6. Verify the polling spinner is active
    const spinner = page.locator('#prov-spinner');
    await expect(spinner).toBeVisible();
  });
});
