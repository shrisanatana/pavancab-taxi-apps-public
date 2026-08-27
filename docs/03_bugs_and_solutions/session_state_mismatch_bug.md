# 🐛 Bug Registry: Session State Mismatch Across Views

## 1. Issue Description
A user who was logged in showed as authenticated in the top navbar, but when clicking to proceed to Step 2, Step 4, or booking, the app repeatedly popped up the WhatsApp login modal.

## 2. Root Cause
- PHP session (`$_SESSION['user']`) was available to PHP during header rendering, but the frontend JavaScript variable `window.isUserLoggedIn` was undefined on `app/index.php`.
- Handlers (`goToStep`, `handleConfirmBooking`) evaluated `!isUserLoggedIn` as `true` and triggered `openAuthModal()`.

## 3. Solution Applied
1. Exposed global JavaScript variables directly in `app/header.php`:
   ```html
   <script>
     window.currentUser = <?php echo json_encode($currentUser); ?>;
     window.isUserLoggedIn = <?php echo (!empty($currentUser) ? 'true' : 'false'); ?>;
     window.currentUserPhone = "<?php echo htmlspecialchars($currentUser['mobile'] ?? ''); ?>";
     window.currentUserName = "<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>";
   </script>
   ```
2. Updated all step and booking handlers in `app/index.php` to check `window.isUserLoggedIn` and `window.currentUser`.
3. Added session fallback in `app/bookings.php` to auto-establish session for validated phone inputs.

## 4. Prevention Rule
- Always initialize `window.currentUser` and `window.isUserLoggedIn` globally in `header.php` so all views inherit identical authentication state.
