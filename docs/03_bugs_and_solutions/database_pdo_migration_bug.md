# 🐛 Bug Registry: Database PDO Removal & Helper Functions

## 1. Issue Description
When attempting to query drop destinations (`app/drops.php`) or submit bookings (`app/bookings.php`), the application crashed with silent 500 fatal errors or network errors.

## 2. Root Cause
- Legacy code contained references to `$pdo` (`$pdo->prepare()`, `$pdo->beginTransaction()`), which was undefined under the unified `mysqli` (`db()`) database architecture.
- `app/bookings.php` invoked `notifyAdminAndTeamNewBooking()`, which was not declared in `app/db.php`.

## 3. Solution Applied
1. Refactored `app/drops.php` and `app/bookings.php` to use the unified `db()`, `dbRows()`, and `dbExec()` helpers with prepared statements and parameter binding.
2. Implemented `notifyAdminAndTeamNewBooking()` in `app/db.php` to send WhatsApp and FCM alerts to Super Admin and dispatchers.

## 4. Prevention Rule
- Never use `$pdo` anywhere in the codebase.
- Always use `dbRows($sql, $types, $params)` for `SELECT` queries and `dbExec($sql, $types, $params)` for `INSERT`/`UPDATE`/`DELETE` queries.
