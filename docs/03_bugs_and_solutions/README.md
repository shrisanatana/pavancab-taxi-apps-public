# 🐛 Master Bug Index & Resolution Registry

This directory contains complete documentation on every issue resolved in the PAVANCAB platform.

---

## 📋 Comprehensive Bug Resolution Log

| Bug ID | Issue Title | Severity | Impacted Files | Status |
|---|---|---|---|---|
| **BUG-001** | [Legacy PDO Elimination & DB Helpers](database_pdo_migration_bug.md) | **Critical** | `app/drops.php`, `app/bookings.php`, `app/db.php` | ✅ Resolved & Verified |
| **BUG-002** | [Dispatch Filter Category Mixing & Tab Reset](filter_tab_mixing_bug.md) | **High** | `app/dashboard.php`, `app/api_dashboard.php` | ✅ Resolved & Verified |
| **BUG-003** | [Global Session State Mismatch (`window.isUserLoggedIn`)](session_state_mismatch_bug.md) | **High** | `app/header.php`, `app/index.php`, `app/auth.php` | ✅ Resolved & Verified |
| **BUG-004** | [Permanent Client Directive: SOS Feature Removal](sos_feature_removal.md) | **High** | `app/header.php`, `app/footer.php`, `app/rides.php`, `app/dashboard.php` | ✅ Resolved & Verified |
| **BUG-005** | [Dashboard Syntax Error & Real-Time Sync Hash](script_syntax_error.md) | **Medium** | `app/dashboard.php` | ✅ Resolved & Verified |
| **BUG-006** | [Single Ride Direct Spotlight Redirection](session_state_mismatch_bug.md) | **Medium** | `app/index.php`, `app/rides.php` | ✅ Resolved & Verified |
| **BUG-007** | [Duplicate Function Declarations in `api.php`](database_pdo_migration_bug.md) | **Critical** | `app/api.php`, `app/db.php` | ✅ Resolved & Verified |
| **BUG-008** | [Rides Page False Unauthenticated Login Prompt](rides_page_session_bypass_bug.md) | **High** | `app/rides.php`, `app/api_rides.php` | ✅ Resolved & Verified |
| **BUG-009** | [Dispatch Tower Request Routing & Modal Lockup Fixes](dashboard_critical_fixes.md) | **High** | `app/dashboard.php`, `app/api_dashboard.php`, `app/db.php` | ✅ Resolved & Verified |
| **BUG-010** | [Rides Page Background Polling & Spotlight Card Live Sync](rides_polling_spotlight_sync_bug.md) | **High** | `app/rides.php`, `app/api_rides.php` | ✅ Resolved & Verified |
| **BUG-011** | [Modular Dashboard Status Classification & Empty State Sync](dashboard_modular_status_classification_sync_bug.md) | **High** | `app/dashboard/`, `app/api_dashboard.php` | ✅ Resolved & Verified |
| **BUG-012** | [Comprehensive Session Destruction & Mobile Logout Action](session_logout_destruction_bug.md) | **High** | `app/auth.php`, `app/footer.php`, `app/dashboard/_layout_header.php` | ✅ Resolved & Verified |

---

## 🔒 Architectural Rules for Future Changes

1. **Database Engine**: Always use `db()`, `dbRows()`, and `dbExec()` from `app/db.php`. Never instantiate or call PDO.
2. **Operating Model**: There is NO driver app. Super Admin and Team members assign and manage all rides from `app/dashboard.php`.
3. **No SOS Features**: SOS buttons and emergency modals have been permanently decommissioned per client instructions. Use the standard Ride Reports Desk (`app_ride_reports`).
4. **Session Synchronization**: All authentication state must be exposed globally through `window.currentUser` and `window.isUserLoggedIn` in `app/header.php`.
5. **Real-Time Polling**: Background live sync must respect open modals (`isModalActive = true`) and preserve active filter tabs (`currentFilter`).
