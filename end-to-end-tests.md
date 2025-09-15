# End-to-End Tests Documentation

This document lists all e2e tests in the system with their locations and individual run commands.

**Total Tests: 50 tests across 6 files**

## How to Run Tests

### All Tests

```bash
npm run test:docker
# OR
docker compose --profile testing run --rm playwright npx playwright test
```

### Individual Test Files

```bash
docker compose --profile testing run --rm playwright npx playwright test tests/e2e/[filename]
```

### Individual Tests

```bash
docker compose --profile testing run --rm playwright npx playwright test -g "test name"
```

---

## Test Inventory

### 1. Loading States Tests (8 tests)

**File:** `tests/e2e/loading-states.spec.js`
**Overall Status:** ✅ ALL PASSING (8/8)

| Test Name                                                      | Status  | Command                                                                                                                                        | Notes/Errors                             |
| -------------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| refresh button should have proper Alpine.js loading attributes | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "refresh button should have proper Alpine.js loading attributes"` | -                                        |
| CSS loading states should be properly defined                  | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "CSS loading states should be properly defined"`                  | -                                        |
| Alpine.js should be loaded and available                       | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Alpine.js should be loaded and available"`                       | -                                        |
| resource table Alpine.js function should be available          | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "resource table Alpine.js function should be available"`          | -                                        |
| Alpine.js data methods should be properly defined              | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Alpine.js data methods should be properly defined"`              | -                                        |
| notification system should have proper implementation          | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "notification system should have proper implementation"`          | -                                        |
| CSRF token should be available for AJAX requests               | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "CSRF token should be available for AJAX requests"`               | Fixed: Updated invalid Playwright method |
| Bootstrap modal integration should be properly implemented     | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Bootstrap modal integration should be properly implemented"`     | -                                        |

### 2. Modal Interaction Tests (10 tests)

**File:** `tests/e2e/modals.spec.js`
**Overall Status:** ✅ ALL PASSING (10/10)

| Test Name                                                | Status  | Command                                                                                                                                  | Notes/Errors |
| -------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| should have all modal elements present in DOM            | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should have all modal elements present in DOM"`            | -            |
| view modal should have correct structure                 | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "view modal should have correct structure"`                 | -            |
| update modal should have correct structure               | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "update modal should have correct structure"`               | -            |
| delete modal should have correct structure               | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "delete modal should have correct structure"`               | -            |
| clicking view button should trigger Alpine.js function   | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "clicking view button should trigger Alpine.js function"`   | -            |
| clicking update button should trigger Alpine.js function | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "clicking update button should trigger Alpine.js function"` | -            |
| clicking delete button should trigger Alpine.js function | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "clicking delete button should trigger Alpine.js function"` | -            |
| modals should have Bootstrap modal attributes            | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "modals should have Bootstrap modal attributes"`            | -            |
| modal close buttons should have correct attributes       | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "modal close buttons should have correct attributes"`       | -            |
| should verify Alpine.js data attribute on main container | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should verify Alpine.js data attribute on main container"` | -            |

### 3. Resource Table Tests (9 tests)

**File:** `tests/e2e/resource-table.spec.js`
**Overall Status:** ⚠️ MOSTLY PASSING (8/9) - 1 test failing

| Test Name                                              | Status  | Command                                                                                                                                | Notes/Errors                                            |
| ------------------------------------------------------ | ------- | -------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| should display the client table with data              | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should display the client table with data"`              | -                                                       |
| should display the correct record count badge          | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should display the correct record count badge"`          | -                                                       |
| should have refresh button that works                  | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should have refresh button that works"`                  | -                                                       |
| should have action buttons for each row                | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should have action buttons for each row"`                | -                                                       |
| should have Alpine.js click handlers on action buttons | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should have Alpine.js click handlers on action buttons"` | Fixed: Removed non-existent disabled state expectations |
| should display proper data in table cells              | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should display proper data in table cells"`              | **Error:** Empty cell content - clientId.trim() is ""   |
| should handle empty states gracefully                  | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should handle empty states gracefully"`                  | -                                                       |
| should have responsive table wrapper                   | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should have responsive table wrapper"`                   | -                                                       |
| should have proper Bootstrap classes                   | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should have proper Bootstrap classes"`                   | Fixed: Made selectors more specific for strict mode     |

### 4. Verification Integration Tests (8 tests)

**File:** `tests/e2e/verification-integration.spec.js`
**Overall Status:** ⚠️ MOSTLY PASSING (6/8) - 2 tests failing

| Test Name                                                                            | Status  | Command                                                                                                                                          | Notes/Errors                                                                                    |
| ------------------------------------------------------------------------------------ | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------- |
| Quick Verify should show verification results instead of "No data to verify"         | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Quick Verify should show verification results instead of"`         | -                                                                                               |
| Quick Verify should show meaningful error instead of generic "Failed to verify data" | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Quick Verify should show meaningful error instead of generic"`     | -                                                                                               |
| Quick Verify modal should close properly without backdrop issues                     | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Quick Verify modal should close properly without backdrop issues"` | -                                                                                               |
| Full Verification should show real-time progress                                     | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "Full Verification should show real-time progress"`                 | **Error:** Element not found - `#verification-progress :has-text("Processing clients...")`      |
| Verification should work with real API endpoints                                     | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Verification should work with real API endpoints"`                 | -                                                                                               |
| Error handling - network failures                                                    | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "Error handling - network failures"`                                | **Error:** Strict mode violation - multiple elements match `:has-text("Failed to verify data")` |
| Performance - Quick Verify should respond within reasonable time                     | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Performance - Quick Verify should respond within reasonable time"` | -                                                                                               |
| Accessibility - verification modals should be accessible                             | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Accessibility - verification modals should be accessible"`         | -                                                                                               |

### 5. Verification Simple Tests (2 tests)

**File:** `tests/e2e/verification-simple.spec.js`
**Overall Status:** ✅ ALL PASSING (2/2)

| Test Name                                                             | Status  | Command                                                                                                                                               | Notes/Errors                                                 |
| --------------------------------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| Quick Verify should show meaningful content or proper no-data message | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Quick Verify should show meaningful content or proper no-data message"` | Fixed: Updated timeout strategy to use content-based waiting |
| Quick Verify modal should close without backdrop issues               | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "Quick Verify modal should close without backdrop issues"`               | Fixed: Updated timeout strategy to use content-based waiting |

### 6. Main Verification Tests (13 tests)

**File:** `tests/e2e/verification.spec.js`
**Overall Status:** ❌ MOSTLY FAILING (4/13) - 9 tests failing

| Test Name                                                      | Status  | Command                                                                                                                                        | Notes/Errors                                                             |
| -------------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| should open Quick Verify modal with loading state              | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should open Quick Verify modal with loading state"`              | **Error:** Element not found - `.spinner-border`                         |
| should display verification results correctly                  | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should display verification results correctly"`                  | **Error:** Element not found - `.spinner-border`                         |
| should handle verification errors gracefully                   | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should handle verification errors gracefully"`                   | **Error:** Element not found - `.spinner-border`                         |
| should handle no data to verify                                | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should handle no data to verify"`                                | **Error:** Element not found - `.spinner-border`                         |
| should properly close modal without backdrop issues            | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should properly close modal without backdrop issues"`            | -                                                                        |
| should navigate to Full Verification from modal                | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should navigate to Full Verification from modal"`                | **Error:** Element not found - `.spinner-border`                         |
| should display full verification page correctly                | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should display full verification page correctly"`                | -                                                                        |
| should start full verification process                         | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should start full verification process"`                         | **Error:** Element not found - `#verification-status-card`               |
| should show completed verification results                     | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should show completed verification results"`                     | **Error:** Element not found - `#verification-status-card`               |
| should handle verification errors                              | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should handle verification errors"`                              | -                                                                        |
| should show verification buttons only for completed migrations | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should show verification buttons only for completed migrations"` | **Error:** Button visible when should be hidden for completed migrations |
| should hide verification buttons for pending migrations        | ❌ FAIL | `docker compose --profile testing run --rm playwright npx playwright test -g "should hide verification buttons for pending migrations"`        | **Error:** Button visible when should be hidden for pending migrations   |
| should handle real API responses gracefully                    | ✅ PASS | `docker compose --profile testing run --rm playwright npx playwright test -g "should handle real API responses gracefully"`                    | -                                                                        |

---

## Test Status Summary

**Current Status (50 tests total):**

- ✅ **38 tests PASSING** (76%)
- ❌ **12 tests FAILING** (24%)

**By File:**

1. **loading-states.spec.js**: ✅ 8/8 passing (100%) - All tests working correctly
2. **modals.spec.js**: ✅ 10/10 passing (100%) - Previous timeout issues resolved
3. **resource-table.spec.js**: ⚠️ 8/9 passing (89%) - 1 test failing with empty cell data
4. **verification-integration.spec.js**: ⚠️ 6/8 passing (75%) - 2 tests failing with DOM element issues
5. **verification-simple.spec.js**: ✅ 2/2 passing (100%) - All tests working correctly
6. **verification.spec.js**: ❌ 4/13 passing (31%) - 9 tests failing with strict mode violations

**Main Issues Remaining:**

1. **verification.spec.js** - Strict mode violations with multiple element matches
2. **verification-integration.spec.js** - Missing DOM elements and progress indicators
3. **resource-table.spec.js** - Empty table cell content issue
4. **Button visibility logic** - Verification buttons showing when they should be hidden

## Debugging Individual Tests

To run a single test with debug output:

```bash
docker compose --profile testing run --rm playwright npx playwright test -g "test name" --debug
```

To run with UI mode:

```bash
docker compose --profile testing run --rm playwright npx playwright test tests/e2e/filename.spec.js --ui-port=9323
```

To view test reports:

```bash
docker compose --profile testing run --rm playwright npx playwright show-report
```
