# ðŸ” WhatsApp OTP Authentication & Role System

## 1. Authentication Flow

PAVANCAB features a 1-click passwordless **WhatsApp OTP Authentication System** implemented in `app/auth.php`:

1. **Step 1: Enter Phone Number**:
   - User inputs 10-digit Indian WhatsApp mobile number.
   - Server generates a cryptographically secure 6-digit OTP code and stores it in `app_otp_store` with a 10-minute expiry (`DATE_ADD(NOW(), INTERVAL 10 MINUTE)`).
   - Server dispatches the OTP via Meta WhatsApp Cloud API (`sendMetaWhatsApp()`).

2. **Step 2: Enter 6-Digit OTP**:
   - User submits OTP code.
   - Server verifies code against `app_otp_store`.
   - On match, OTP is deleted and user record is upserted in `app_users`.
   - Server sets `$_SESSION['user']` and `$_SESSION['active_booking_phone']`.
   - Frontend sets `window.currentUser` and `window.isUserLoggedIn = true`.

---

## 2. Role Determination Hierarchy (`determineUserRole`)

Role is computed dynamically in `app/db.php`:

1. **Super Admin (`admin`)**:
   - Phone matches `919000000000` or email matches `admin@pavancab-demo.local`.
   - Redirects to `dashboard.php`.
   - Has full access to dispatching, fleet management, WhatsApp token updates, and team management.

2. **Team Member (`team`)**:
   - Phone or email exists in `app_team_members` with `is_active = 1`.
   - Redirects to `dashboard.php`.
   - Has access to dispatching and status updates.

3. **Passenger (`user`)**:
   - Any other verified WhatsApp user.
   - Redirects to `index.php` or `rides.php`.
