# 🐛 BUG-009: Dispatch Tower Dashboard Stability & Request Routing Fixes

## 📌 Issue Summary
Multiple bugs were detected on the **Dispatch Command Tower** (`https://pavancab.com/app/dashboard.php`):
1. **Blank Screen / JSON Interception**: Browsers sending standard Accept headers (`text/html, ..., application/json`) were mistakenly intercepted by `isJsonRequest()` on initial GET requests, routing to `api_dashboard.php` and returning blank output.
2. **Short Open Tag Syntax Bug**: A PHP short tag `<?` on the driver status badge caused parsing anomalies on PHP 8+ production environments.
3. **Dispatch Modal Button Lockup**: Submitting driver assignment left the submit button disabled; opening the modal for a subsequent booking caused the button to remain stuck on "Dispatching Driver...".
4. **Reports Desk Modal Polling Freeze**: Closing the Ride Reports Desk did not reset `isModalActive = false`, permanently pausing live real-time sync polling (`fetchLiveUpdates`).
6. **Category Count Mismatch & Zero State UI**: Separate hardcoded SQL queries in `api_dashboard.php` and `dashboard.php` used strict `= 'PENDING'` string checks that omitted records with un-trimmed status values, resulting in counters showing lower numbers (e.g. 0 or 1) while multiple pending ride cards were rendered below.

---

## 🔍 Root Cause Analysis
1. `isJsonRequest()` in `app/db.php` returned `true` if `application/json` appeared anywhere in the `HTTP_ACCEPT` string, even when the browser explicitly preferred `text/html`.
2. In `app/dashboard.php`, `<? if ($isAvail) ...` omitted the `php` tag.
3. `openAssignModal()`, `openManualBookingModal()`, and other modal openers did not reset form submit buttons or loading states.
4. `closeReportsDeskModal()` was missing `isModalActive = false;`.
5. Missing `/admin/reports` and `/admin/update-report` route definitions in `api_dashboard.php`.
6. Discrepancy between SQL count queries and card categorization logic in PHP/JS caused counters to not add up to total rides.

---

## 🛠️ Solution & Implementation
1. **Refined `isJsonRequest()` in `app/db.php`**:
   - Explicitly returns `false` if `text/html` is in the Accept header.
   - Only returns `true` for explicit JSON payloads (`Content-Type: application/json`), parameter overrides (`?json=1`), or pure JSON accept headers.
2. **Dashboard Request Delegation**:
   - `app/dashboard.php` now only routes to `api_dashboard.php` if `$_SERVER['REQUEST_METHOD'] === 'POST'` or explicit query parameters (`?action=`, `?json=`) are passed.
3. **Modal State & Button Reset**:
   - Added explicit submit button re-enablement and icon re-rendering in `openAssignModal()`, `handleAssignDriver()`, and `handleCreateManualBooking()`.
   - Added backdrop click and Escape key dismissal listeners for all modal overlays.
   - Updated `closeReportsDeskModal()` to reset `isModalActive = false`.
4. **Added Reports Endpoints to `api_dashboard.php`**:
   - Integrated `/admin/reports` and `/admin/update-report` / `update_report_status` directly into `api_dashboard.php` and mapped the router.
5. **Syntax Correction**:
   - Replaced all short open tags with standard `<?php`.
6. **Unified Categorization & Dynamic Empty State Engine**:
   - Replaced divergent SQL count queries with a unified classification loop in both `app/dashboard.php` and `app/api_dashboard.php`. All category counts (`pending + assigned + inTransit + completed + cancelled`) now 100% mathematically equal `total` (35 rides).
   - Added dynamic zero-state alert banner (`#bookings-empty-state`) when any category has 0 rides.
   - Added smart initial tab selection in `DOMContentLoaded` defaulting to `PENDING` if pending rides exist, or `ALL` if all rides are dispatched.

---

## 🔒 Verification & Prevention Checklist
- [x] Tested GET `/app/dashboard.php` with various browser Accept headers.
- [x] Verified multiple sequential driver dispatch operations in the modal without button freezing.
- [x] Verified Ride Reports Desk opening and closing without stalling background sync.
- [x] Tested Super Admin access by phone and email.
- [x] Verified category counts mathematically sum to total rides across all filter tabs.
- [x] Deployed live to `pavancab.com` using `deploy.ps1`.
