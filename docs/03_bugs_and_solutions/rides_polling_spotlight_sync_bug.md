# 🐛 BUG-010: Rides Page Background Polling URL Parameter Loss & Spotlight Card Desync

## 📌 Issue Summary
When accessing ride tracking via a direct URL link (e.g. `https://pavancab.com/app/rides.php?id=36&phone=9518541625`):
1. **Live Updates Not Fetching Automatically**: Real-time status changes (e.g., driver assignment, fare adjustments, on-trip transitions) only became visible after a manual browser page refresh (`F5`).
2. **Missing Query Params in Polling**: `pollCustomerRides()` invoked `./api_rides.php?action=user-bookings` without passing `phone` or `id` URL parameters. If the user was not authenticated via session, the API returned an empty list (`[]`).
3. **Spotlight Card DOM Omission**: `pollCustomerRides()` previously only updated `#active-rides-feed-container`, leaving the featured `#spotlight-card` static.

---

## 🔍 Root Cause Analysis
1. In `app/rides.php`, background polling (`pollCustomerRides`) fetched `./api_rides.php?action=user-bookings` without reading or appending `window.location.search` parameters (`?phone=...&id=...`).
2. `app/api_rides.php` only filtered by `$_SESSION['user']` or `$_GET['phone']` / `$_GET['email']` and did not have explicit fallback handling for `$_GET['id']` / `$_GET['booking_id']`.
3. The `#spotlight-card` element at the top of `rides.php` was rendered purely via server-side PHP with no client-side DOM reconciliation function in JavaScript.

---

## 🛠️ Solution & Implementation
1. **Targeted Polling Requests**:
   - Updated `pollCustomerRides()` in [app/rides.php](file:///c:/Users/Admin/Desktop/Goa%20Taxi%20App/app/rides.php) to read URL search parameters (`id` and `phone`) and append them to API calls:
     ```javascript
     const urlParams = new URLSearchParams(window.location.search);
     const qPhone = urlParams.get('phone') || '';
     const qId = urlParams.get('id') || urlParams.get('booking_id') || '';
     const res = await fetch(`./api_rides.php?action=user-bookings&phone=${encodeURIComponent(qPhone)}&id=${encodeURIComponent(qId)}`);
     ```
2. **Enhanced API Queries**:
   - Updated `handleRidesModule()` in [app/api_rides.php](file:///c:/Users/Admin/Desktop/Goa%20Taxi%20App/app/api_rides.php) to query bookings by specific `id` or phone number seamlessly.
3. **Dynamic Spotlight Card Reconciliation**:
   - Wrapped the spotlight card in `#spotlight-container` and implemented `renderSpotlightCardHTML()`.
   - When background polling detects data hash changes, it automatically re-renders both `#spotlight-card` (showing assigned driver name, phone, plate, call buttons, and WhatsApp link) and the active rides feed.
4. **Faster Polling Cycle**:
   - Set polling interval to 2.5 seconds and triggered an immediate fetch on `DOMContentLoaded`.

---

## 🔒 Verification
- [x] Verified `GET /app/api_rides.php?action=user-bookings&id=36&phone=9518541625` returns complete booking and driver payload.
- [x] Tested live polling on `rides.php` with direct URL parameters without requiring manual refresh.
- [x] Deployed live to production using `deploy.ps1`.
