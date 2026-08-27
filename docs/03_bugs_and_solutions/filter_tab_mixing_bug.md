# 🐛 Bug Registry: Dispatch Filter Tab Mixing & Reset

## 1. Issue Description
On `app/dashboard.php`, when a dispatcher selected a category tab (e.g. `🔴 Needs Driver`, `Assigned`, `On Trip`, `Completed`), the list mixed or reset all cards back after a few seconds during background polling.

## 2. Root Cause
- Status variations such as `ACCEPTED`, `DRIVER_ASSIGNED`, `FINISHED`, and `CANCELLED_BY_ADMIN` were not recognized by strict status equality checks and were defaulting to `data-status="ALL"`.
- When background live polling ran every 3 seconds, `filterBookings(currentFilter)` matched those cards against `ALL`, causing non-matching cards to leak into specific category views.

## 3. Solution Applied
1. Standardized status classification across PHP and JS renderers:
   - `PENDING`: `PENDING`
   - `CONFIRMED`: `CONFIRMED`, `ASSIGNED`, `ACCEPTED`, `DRIVER_ASSIGNED`
   - `IN_TRANSIT`: `IN_TRANSIT`, `ON_TRIP`, `ARRIVED`
   - `COMPLETED`: `COMPLETED`, `FINISHED`
   - `CANCELLED`: any status containing `CANCEL` or `REJECTED`
2. Defaulted fallback category to `PENDING` instead of `ALL`.
3. Locked `currentFilter` persistence inside `filterBookings(statusKey)` so poll cycles strictly enforce the selected filter tab.

## 4. Prevention Rule
- Always classify statuses using case-insensitive substring/array checks before assigning `data-status` attributes.
