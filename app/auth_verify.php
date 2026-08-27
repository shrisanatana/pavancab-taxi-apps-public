<?php
/**
 * PAVANCAB GOA TAXI - Authentication & WhatsApp OTP Module
 * Path: app/auth.php
 *
 * Handles: User login, Driver login, Admin login, OTP, Password auth
 * Driver endpoints use action=driver_X prefix (merged from api_driver.php)
 * v3 - driver endpoints moved before POST block, OPcache verified
 */
// v3 marker - remove after verification

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$b      = getBody();

// === USER LOGOUT ===
if ($action === 'logout' || isset($_GET['logout']) || isset($_POST['logout'])) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['isLoggedIn'])) {
        if (isJsonRequest()) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        header('Location: ./index.php');
        exit;
    }
    $fcmToken  = trim($b['fcm_token'] ?? $_GET['fcm_token'] ?? $_POST['fcm_token'] ?? '');
    $userId    = intval($_SESSION['user']['id'] ?? 0);
    $userPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? '');

    try {
        $conn = db();
        if ($conn) {
            if ($fcmToken) {
                $fSafe = $conn->real_escape_string($fcmToken);
                $conn->query("DELETE FROM app_fcm_tokens WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_users SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_drivers SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_team_members SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
            }
            if ($userPhone) {
                $clean10 = substr($userPhone, -10);
                $conn->query("DELETE FROM app_fcm_tokens WHERE RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
                $conn->query("UPDATE app_users SET is_online = 0, fcm_token = NULL WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
            }
        }
    } catch (Exception $e) {}

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    if (isset($_COOKIE[session_name()])) @setcookie(session_name(), '', time() - 86400, '/');
    if (isset($_COOKIE['PHPSESSID'])) @setcookie('PHPSESSID', '', time() - 86400, '/');

    if (isJsonRequest()) jsonResponse(['success' => true, 'message' => 'Logged out successfully and FCM push token cleared']);
    header('Location: ./index.php');
    exit;
}

// === GET SESSION ===
if ($method === 'GET' && ($action === 'me' || isset($_GET['me']))) {
    if (isset($_SESSION['user'])) {
        jsonResponse(['success' => true, 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        jsonResponse(['success' => false, 'isLoggedIn' => false, 'user' => null]);
    }
}

// ============================================================
// DRIVER API ENDPOINTS (moved before POST block to avoid
// the isset($b['phone']) catch-all in send_otp handler)
// ============================================================

if (strpos($action, 'driver_') === 0) {
    $da = substr($action, 7);

    // Auto-migrate driver table columns
    try {
        $conn = db();
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL");
    } catch (Exception $e) {}

    // ===== DRIVER: VERIFY OTP =====
    if ($da === 'verify-otp' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        if (!$phone || !$otp) jsonResponse(['success' => false, 'error' => 'Phone and OTP required'], 400);

        $clean10 = substr($phone, -10);
        $cleanOtp = trim($otp);
        $conn = db();

        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $phone, $clean10, $cleanOtp);
        $stmt->execute();
        $r = $stmt->get_result();
        $otpRow = $r ? $r->fetch_assoc() : null;

        if (!$otpRow) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP. Please try again.'], 401);

        dbExec("DELETE FROM app_otp_store WHERE id = ?", 'i', [$otpRow['id']]);

        $stmt = $conn->prepare("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('s', $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $driver = $r ? $r->fetch_assoc() : null;

        if ($driver) {
            $_SESSION['driver'] = [
                'id' => intval($driver['id']),
                'name' => $driver['name'],
                'phone' => $phone,
                'car_model' => $driver['car_model'] ?? '',
                'plate_number' => $driver['plate_number'] ?? '',
                'isLoggedIn' => true
            ];
            $approved = intval($driver['is_approved'] ?? 0);
            jsonResponse([
                'success' => true,
                'approved' => $approved == 1,
                'existing_driver' => true,
                'driver' => [
                    'id' => intval($driver['id']),
                    'name' => $driver['name'],
                    'phone' => $phone,
                    'car_model' => $driver['car_model'] ?? '',
                    'plate_number' => $driver['plate_number'] ?? '',
                    'rating' => floatval($driver['rating'] ?? 5.0),
                    'is_approved' => $approved
                ]
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO app_drivers (name, phone, car_model, plate_number, is_approved, status) VALUES (?, ?, '', '', 0, 'pending')");
            $stmt->bind_param('ss', $phone, $phone);
            $stmt->execute();
            $newId = $conn->insert_id;

            $_SESSION['driver'] = [
                'id' => $newId,
                'name' => '',
                'phone' => $phone,
                'car_model' => '',
                'plate_number' => '',
                'isLoggedIn' => true
            ];

            try { sendFCMPushToAdmins("New Driver Registration", "Phone: $phone has registered as a driver. Please approve from Admin app.", ['type' => 'NEW_DRIVER', 'driver_id' => strval($newId)]); } catch (Exception $e) {}
            try { sendMetaWhatsApp("+919000000000", "NEW DRIVER REGISTRATION\n\nPhone: $phone\nStatus: PENDING APPROVAL\n\nPlease approve from Admin app."); } catch (Exception $e) {}

            jsonResponse([
                'success' => true,
                'approved' => false,
                'existing_driver' => false,
                'driver' => [
                    'id' => $newId,
                    'name' => '',
                    'phone' => $phone,
                    'car_model' => '',
                    'plate_number' => '',
                    'rating' => 5.0,
                    'is_approved' => 0
                ]
            ]);
        }
    }

    // ===== DRIVER: CHECK SESSION =====
    if ($da === 'check-session') {
        if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        $approved = !empty($rows) ? intval($rows[0]['is_approved'] ?? 0) : 0;
        jsonResponse(['success' => true, 'approved' => $approved == 1, 'driver' => $_SESSION['driver']]);
    }

    // ===== DRIVER: CHECK APPROVAL =====
    if ($da === 'check-approval') {
        if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT is_approved, name, car_model, plate_number FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
        jsonResponse(['success' => true, 'approved' => intval($rows[0]['is_approved'] ?? 0) == 1, 'driver' => $rows[0]]);
    }

    // ===== DRIVER: CHECK PHONE (has password?) =====
    if ($da === 'check-phone' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        if (!$phone || strlen($phone) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone required'], 400);
        $clean10 = substr($phone, -10);
        $rows = dbRows("SELECT id, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
        jsonResponse(['success' => true, 'exists' => !empty($rows), 'has_password' => !empty($rows[0]['password_hash'] ?? null)]);
    }

    // ===== DRIVER: SET PASSWORD =====
    if ($da === 'set-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $clean10 = substr($phone, -10);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
        jsonResponse(['success' => true, 'message' => 'Password set successfully']);
    }

    // ===== DRIVER: LOGIN WITH PASSWORD =====
    if ($da === 'login-with-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        $clean10 = substr($phone, -10);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
        if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Driver account not found. Register with OTP first.'], 404);
        $driver = $rows[0];
        if (empty($driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
        if (!password_verify($password, $driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
        $approved = intval($driver['is_approved'] ?? 0);
        $_SESSION['driver'] = [
            'id' => intval($driver['id']),
            'name' => $driver['name'],
            'phone' => $phone,
            'car_model' => $driver['car_model'] ?? '',
            'plate_number' => $driver['plate_number'] ?? '',
            'isLoggedIn' => true
        ];
        jsonResponse([
            'success' => true,
            'approved' => $approved == 1,
            'driver' => [
                'id' => intval($driver['id']),
                'name' => $driver['name'],
                'phone' => $phone,
                'car_model' => $driver['car_model'] ?? '',
                'plate_number' => $driver['plate_number'] ?? '',
                'rating' => floatval($driver['rating'] ?? 5.0),
                'is_approved' => $approved
            ]
        ]);
    }

    // ===== DRIVER: RESET PASSWORD =====
    if ($da === 'reset-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $clean10 = substr($phone, -10);
        $conn = db();
        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $phone, $clean10, $otp);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
        dbExec("DELETE FROM app_otp_store WHERE phone = ? OR RIGHT(phone, 10) = ?", 'ss', [$phone, $clean10]);
        jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    }

    // ===== DRIVER: LOGOUT =====
    if ($da === 'logout' && $method === 'POST') {
        if (!empty($_SESSION['driver']['id'])) {
            $driverId = intval($_SESSION['driver']['id']);
            try { dbExec("UPDATE app_drivers SET is_online = 0 WHERE id = ?", 'i', [$driverId]); } catch (Exception $e) {}
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
        jsonResponse(['success' => true]);
    }

    // ===== Require Driver Auth (for authenticated endpoints) =====
    if (in_array($da, ['my-bookings', 'booking-detail', 'respond', 'trip-status', 'earnings', 'profile', 'toggle-online'])) {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver authentication required. Please login.'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
    }

    // ===== DRIVER: MY BOOKINGS =====
    if ($da === 'my-bookings') {
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows(
            "SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? ORDER BY b.pickup_date DESC, b.pickup_time DESC LIMIT 100",
            'i', [$driverId]
        );
        jsonResponse(['bookings' => $rows]);
    }

    // ===== DRIVER: BOOKING DETAIL =====
    if ($da === 'booking-detail') {
        $driverId = intval($_SESSION['driver']['id']);
        $bookingId = intval($b['id'] ?? $_GET['id'] ?? 0);
        if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
        $rows = dbRows("SELECT b.* FROM app_bookings b WHERE b.id = ? AND (b.driver_id = ? OR ? = 0) LIMIT 1", 'iii', [$bookingId, $driverId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        jsonResponse(['booking' => $rows[0]]);
    }

    // ===== DRIVER: RESPOND TO BOOKING =====
    if ($da === 'respond' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverName = $_SESSION['driver']['name'] ?? '';
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $bookingId = intval($b['booking_id'] ?? 0);
        $decision = strtoupper(trim($b['decision'] ?? ''));

        if (!$bookingId || !in_array($decision, ['ACCEPT', 'REJECT'])) {
            jsonResponse(['error' => 'booking_id and decision (ACCEPT/REJECT) required'], 400);
        }

        $rows = dbRows("SELECT id, status, driver_id, customer_name, customer_phone, booking_ref FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

        $booking = $rows[0];
        if ($booking['status'] !== 'ASSIGNED' && $booking['status'] !== 'ACCEPTED') {
            jsonResponse(['error' => 'Booking is in ' . $booking['status'] . ' status, cannot respond'], 400);
        }

        if ($decision === 'ACCEPT') {
            dbExec("UPDATE app_bookings SET status = 'ACCEPTED', driver_decision = 'ACCEPTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
            try { sendFCMPushToAdmins("Driver Accepted (#{$booking['booking_ref']})", "$driverName ($driverPhone) accepted ride #{$booking['booking_ref']} for {$booking['customer_name']}", ['type' => 'BOOKING_CONFIRMED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
        } else {
            dbExec("UPDATE app_bookings SET status = 'PENDING', driver_id = 0, driver_name = '', driver_phone = '', driver_decision = 'REJECTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
            try { sendFCMPushToAdmins("Driver Rejected (#{$booking['booking_ref']})", "$driverName REJECTED ride #{$booking['booking_ref']}. Please re-assign!", ['type' => 'DRIVER_REJECTED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'decision' => $decision]);
    }

    // ===== DRIVER: TRIP STATUS UPDATE =====
    if ($da === 'trip-status' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $bookingId = intval($b['booking_id'] ?? 0);
        $status = strtoupper(trim($b['status'] ?? ''));

        if (!$bookingId || !$status) jsonResponse(['error' => 'booking_id and status required'], 400);

        $allowed = ['IN_TRIP', 'IN_TRANSIT', 'COMPLETED', 'STARTED'];
        if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);

        $rows = dbRows("SELECT id, status, driver_id, booking_ref, customer_name, customer_phone, pickup_location, drop_location, total_fare FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

        $mapStatus = $status;
        if ($status === 'IN_TRIP') $mapStatus = 'IN_TRANSIT';
        if ($status === 'STARTED') $mapStatus = 'IN_TRANSIT';

        dbExec("UPDATE app_bookings SET status = ?, updated_at = NOW() WHERE id = ?", 'si', [$mapStatus, $bookingId]);

        if ($mapStatus === 'IN_TRANSIT') {
            dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
            try { broadcastRideLifecycleFCM('RIDE_STARTED', $bookingId); } catch (Exception $e) {}
        }
        if ($mapStatus === 'COMPLETED') {
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
            try { broadcastRideLifecycleFCM('RIDE_COMPLETED', $bookingId); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'status' => $mapStatus]);
    }

    // ===== DRIVER: EARNINGS =====
    if ($da === 'earnings') {
        $driverId = intval($_SESSION['driver']['id']);
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');

        $todayRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date = ?", 'is', [$driverId, $today]);
        $weekRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= ?", 'is', [$driverId, $weekStart]);
        $monthRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= ?", 'is', [$driverId, $monthStart]);
        $commRow = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'commission_per_ride' LIMIT 1");
        $commission = !empty($commRow) ? intval($commRow[0]['setting_value']) : 300;

        jsonResponse([
            'today_rides' => intval($todayRow[0]['cnt'] ?? 0), 'today_earnings' => floatval($todayRow[0]['total'] ?? 0),
            'week_rides' => intval($weekRow[0]['cnt'] ?? 0), 'week_earnings' => floatval($weekRow[0]['total'] ?? 0),
            'month_rides' => intval($monthRow[0]['cnt'] ?? 0), 'month_earnings' => floatval($monthRow[0]['total'] ?? 0),
            'commission_per_ride' => $commission
        ]);
    }

    // ===== DRIVER: PROFILE =====
    if ($da === 'profile') {
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, status, is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
        jsonResponse(['driver' => $rows[0]]);
    }

    // ===== DRIVER: TOGGLE ONLINE =====
    if ($da === 'toggle-online' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $row = dbRows("SELECT is_online FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        $current = intval($row[0]['is_online'] ?? 0);
        $newVal = $current == 1 ? 0 : 1;
        dbExec("UPDATE app_drivers SET is_online = ? WHERE id = ?", 'ii', [$newVal, $driverId]);
        jsonResponse(['success' => true, 'is_online' => $newVal]);
    }

    // ===== DRIVER: SAVE FCM TOKEN =====
    if ($da === 'save-fcm-token' && $method === 'POST') {
        $fcmToken = trim($b['fcm_token'] ?? '');
        $phone = cleanPhoneDigits($b['phone'] ?? '');

        if ($fcmToken) {
            if ($phone) {
                $clean10 = substr($phone, -10);
                dbExec("UPDATE app_drivers SET fcm_token = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$fcmToken, $clean10]);
            }
            $existing = dbRows("SELECT id FROM app_fcm_tokens WHERE fcm_token = ? LIMIT 1", 's', [$fcmToken]);
            if (empty($existing)) {
                dbExec("INSERT INTO app_fcm_tokens (fcm_token, user_mobile) VALUES (?, ?)", 'ss', [$fcmToken, $phone ?: null]);
            }
        }
        jsonResponse(['success' => true]);
    }

    // ===== ADMIN: APPROVE DRIVER =====
    if ($da === 'approve-driver' && $method === 'POST') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);

        $driverId = intval($b['driver_id'] ?? 0);
        $approved = intval($b['approved'] ?? 1);
        if (!$driverId) jsonResponse(['error' => 'driver_id required'], 400);

        dbExec("UPDATE app_drivers SET is_approved = ? WHERE id = ?", 'ii', [$approved, $driverId]);

        $rows = dbRows("SELECT phone, name FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (!empty($rows)) {
            $phone = $rows[0]['phone'];
            if ($approved) {
                try {
                    sendFCMPushToDriver($driverId, "Account Approved!", "Your driver account has been approved. You can now start accepting rides.", ['type' => 'APPROVED']);
                    sendMetaWhatsApp($phone, "ACCOUNT APPROVED!\n\nYour PAVANCAB Driver account has been approved!\n\nOpen the Driver App to start accepting rides. Drive safe!");
                } catch (Exception $e) {}
            } else {
                try {
                    sendFCMPushToDriver($driverId, "Account Rejected", "Your driver account has been rejected. Contact support for details.", ['type' => 'REJECTED']);
                } catch (Exception $e) {}
            }
        }

        jsonResponse(['success' => true, 'approved' => $approved == 1]);
    }

    // ===== ADMIN: LIST PENDING DRIVERS =====
    if ($da === 'pending-drivers') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, created_at FROM app_drivers WHERE is_approved = 0 ORDER BY id DESC");
        jsonResponse(['drivers' => $rows]);
    }

    // ===== ADMIN: LIST ALL DRIVERS =====
    if ($da === 'all-drivers') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, rating, status FROM app_drivers ORDER BY id DESC");
        jsonResponse(['drivers' => $rows]);
    }

    // If no driver action matched, return error
    jsonResponse(['error' => 'Unknown driver action: ' . $da], 400);
}

if ($method === 'POST') {
    // === VERIFY OTP (USER) ===
    if ($action === 'verify_otp' || !empty($b['otp'])) {
        $phone = trim($b['phone'] ?? $_POST['phone'] ?? $_GET['phone'] ?? '');
        $otp   = trim($b['otp'] ?? $_POST['otp'] ?? $_GET['otp'] ?? '');
        $name  = trim($b['name'] ?? $_POST['name'] ?? $_GET['name'] ?? '');
        $email = trim($b['email'] ?? $_POST['email'] ?? $_GET['email'] ?? '');

        if (!$phone || !$otp) {
            jsonResponse(['success' => false, 'error' => 'Phone number and 6-digit OTP code are required.'], 400);
        }

        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $cleanOtp    = trim($otp);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $conn = db();
        $otpValid = false;

        $stmt = $conn->prepare("SELECT * FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();

        if ($r && $row = $r->fetch_assoc()) {
            if (trim($row['otp']) === $cleanOtp) {
                $otpValid = true;
                $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
                $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
                $stmtDel->execute();
            }
        }

        if (!$otpValid && isset($_SESSION['pending_otp'])) {
            if (($_SESSION['pending_otp']['phone'] === $cleanDigits || $_SESSION['pending_otp']['clean10'] === $clean10) && $_SESSION['pending_otp']['otp'] === $cleanOtp) {
                if ($_SESSION['pending_otp']['expires'] > time()) $otpValid = true;
            }
        }

        if (!$otpValid) {
            if (!isset($_SESSION['otp_attempts'])) $_SESSION['otp_attempts'] = [];
            $attempts = $_SESSION['otp_attempts'][$cleanDigits] ?? ['count' => 0, 'first' => time()];
            if (time() - $attempts['first'] > 600) $attempts = ['count' => 0, 'first' => time()];
            $attempts['count']++;
            $_SESSION['otp_attempts'][$cleanDigits] = $attempts;
            if ($attempts['count'] > 5) jsonResponse(['success' => false, 'error' => 'Too many failed attempts. Please wait 10 minutes and try again.'], 429);
            jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP code. Please request a new OTP.'], 401);
        }
        if (isset($_SESSION['otp_attempts'][$cleanDigits])) unset($_SESSION['otp_attempts'][$cleanDigits]);
        unset($_SESSION['pending_otp']);
        session_regenerate_id(true);

        $role = determineUserRole($cleanDigits, $email);
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');

        $finalName = $name;
        if ($isAdmin) {
            $finalName = 'Niranjan Yamgar (Admin)';
        } elseif ($isTeam) {
            $stmtTm = $conn->prepare("SELECT member_name FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), 10) = ? OR REPLACE(REPLACE(member_phone, '+', ''), ' ', '') = ?) AND is_active = 1 LIMIT 1");
            if ($stmtTm) {
                $stmtTm->bind_param('ss', $clean10, $cleanDigits);
                $stmtTm->execute();
                $rTm = $stmtTm->get_result();
                if ($rTm && $rowTm = $rTm->fetch_assoc()) $finalName = $rowTm['member_name'];
            }
        }
        if (!$finalName) $finalName = 'Goa Traveler';

        $formattedMobile = '+' . $cleanDigits;
        $reqFcm = trim($b['fcm_token'] ?? $_POST['fcm_token'] ?? $_GET['fcm_token'] ?? '');

        $stmtUser = $conn->prepare("SELECT id, name, email, role FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmtUser->bind_param('ss', $formattedMobile, $clean10);
        $stmtUser->execute();
        $rUser = $stmtUser->get_result();
        $isNewUser = true;
        $isBanned = false;

        if ($rUser && $rowU = $rUser->fetch_assoc()) {
            $isNewUser = false;
            $userId = $rowU['id'];
            $stmtBan = $conn->prepare("SELECT IFNULL(is_banned, 0) as banned FROM app_users WHERE id = ? LIMIT 1");
            if ($stmtBan) {
                $stmtBan->bind_param('i', $userId);
                $stmtBan->execute();
                $rBan = $stmtBan->get_result();
                if ($rBan && $rowBan = $rBan->fetch_assoc()) $isBanned = intval($rowBan['banned']) === 1;
            }
            if ($isBanned) jsonResponse(['success' => false, 'error' => 'Your account has been banned by the administrator. Please contact support.'], 403);
            if (!$name && $rowU['name']) $finalName = $rowU['name'];
            $stmtUpd = $conn->prepare("UPDATE app_users SET name = ?, mobile = ?, role = ?, last_active_at = NOW(), is_online = 1 WHERE id = ?");
            $stmtUpd->bind_param('sssi', $finalName, $formattedMobile, $role, $userId);
            $stmtUpd->execute();
        } else {
            $stmtIns = $conn->prepare("INSERT INTO app_users (name, mobile, email, role, last_active_at, is_online) VALUES (?, ?, ?, ?, NOW(), 1)");
            $stmtIns->bind_param('ssss', $finalName, $formattedMobile, $email, $role);
            $stmtIns->execute();
            $userId = $conn->insert_id;
        }

        if (!empty($reqFcm)) {
            $fSafe = $conn->real_escape_string($reqFcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $reqFcm, $email, $cleanDigits); $stmtFcm->execute(); }
            $stmtFcmUpd = $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE id = ?");
            $stmtFcmUpd->bind_param('si', $reqFcm, $userId);
            $stmtFcmUpd->execute();
        }

        $userSession = [
            'id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $email,
            'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true
        ];
        $_SESSION['user'] = $userSession;

        if ($role === 'user') {
            $loginType = $isNewUser ? 'joined & logged in' : 'logged in';
            $notifBody = "$finalName ($formattedMobile) $loginType to PAVANCAB.";
            if ($isNewUser) $notifBody .= " New user!";
            try {
                sendFCMPushToAdmins("Passenger " . ucfirst($loginType) . " (#$finalName)", $notifBody, ['url' => 'https://pavancab.com/app/dashboard/users.php', 'event_type' => 'NEW_BOOKING']);
            } catch (Exception $e) {}
        }

        jsonResponse([
            'success' => true, 'message' => 'Login verified successfully!', 'user' => $userSession,
            'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'has_password' => !empty($rowU['password_hash'] ?? null),
            'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php'
        ]);
    } elseif ($action === 'check_phone') {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone number required'], 400);

        $conn = db();
        $stmt = $conn->prepare("SELECT id, name, password_hash FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('ss', '+' . $cleanDigits, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;

        jsonResponse(['success' => true, 'exists' => !empty($row), 'has_password' => !empty($row['password_hash'] ?? null), 'name' => $row['name'] ?? '']);
    } elseif ($action === 'set_password') {
        $phone = trim($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $formattedMobile = '+' . $cleanDigits;

        $conn = db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?");
        $stmt->bind_param('sss', $hash, $formattedMobile, $clean10);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => 'Password set successfully']);
    } elseif ($action === 'login_with_password') {
        $phone = trim($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        $fcm = trim($b['fcm_token'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $formattedMobile = '+' . $cleanDigits;

        $conn = db();
        $stmt = $conn->prepare("SELECT id, name, email, role, password_hash, is_banned FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('ss', $formattedMobile, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;

        if (!$row) jsonResponse(['success' => false, 'error' => 'Account not found. Please register first with OTP.'], 404);
        if (empty($row['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
        if (!password_verify($password, $row['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
        if (intval($row['is_banned'] ?? 0) === 1) jsonResponse(['success' => false, 'error' => 'Account banned'], 403);

        $userId = intval($row['id']);
        $role = $row['role'] ?? 'user';
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');
        $finalName = $row['name'] ?: 'Goa Traveler';

        session_regenerate_id(true);
        $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE id = $userId");

        if (!empty($fcm)) {
            $fSafe = $conn->real_escape_string($fcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_fcm_tokens SET is_online = 0 WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcm, $row['email'] ?? '', $formattedMobile); $stmtFcm->execute(); }
            $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE id = ?")->bind_param('si', $fcm, $userId);
            $conn->query("UPDATE app_users SET fcm_token = '$fSafe', is_online = 1, last_active_at = NOW() WHERE id = $userId");
        }

        $userSession = ['id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $row['email'] ?? '', 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
        $_SESSION['user'] = $userSession;

        jsonResponse(['success' => true, 'message' => 'Login successful!', 'user' => $userSession, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php']);
    } elseif ($action === 'reset_password') {
        $phone = trim($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        $conn = db();
        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ssss', $cleanDigits, $cleanDigits, $clean10, $otp);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);

        $formattedMobile = '+' . $cleanDigits;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?")->bind_param('sss', $hash, $formattedMobile, $clean10);
        $conn->query("DELETE FROM app_otp_store WHERE phone = '$cleanDigits' OR RIGHT(phone, 10) = '$clean10'");

        jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    } elseif ($action === 'send_otp' || (isset($b['phone']) && strpos($action, 'driver_') !== 0)) {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);

        $appType = trim($b['app_type'] ?? 'passenger');
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $conn = db();

        $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
        $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmtDel->execute();

        $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->bind_param('ss', $cleanDigits, $otp);
        $stmt->execute();

        $_SESSION['pending_otp'] = [
            'phone'   => $cleanDigits,
            'clean10' => $clean10,
            'otp'     => $otp,
            'expires' => time() + 600
        ];

        $svc  = 'PAVANCAB';
        $appN = 'PAVANCAB';
        $supP = '+919000000000';
        if ($appType === 'driver') {
            $appN = 'PAVANCAB Driver';
            $supP = '+919518541625';
        } elseif ($appType === 'dispatch') {
            $appN = 'PAVANCAB Dispatch';
        }
        $acctLabel = $appType === 'driver' ? 'Driver' : ($appType === 'dispatch' ? 'Admin' : 'Passenger');
        $result = sendOTPWhatsAppTemplate($cleanDigits, $otp, $svc, $acctLabel, $appN, 'PAVANCAB', $supP);

        $waSent = true;
        $formattedDisplay = '+' . $cleanDigits;
        $msg = "WhatsApp OTP sent to $formattedDisplay!";

        if (is_array($result) && isset($result['success']) && !$result['success']) {
            $waSent = false;
            $msg = "WhatsApp delivery failed. " . ($result['error'] ?? 'Check API token.');
        }

        jsonResponse([
            'success' => $waSent,
            'message' => $msg,
            'phone' => $formattedDisplay,
            'wa_sent' => $waSent
        ]);

    } elseif ($action === 'save_fcm_token' || $action === 'fcm_token') {
        if (empty($_SESSION['user'])) {
            jsonResponse(['success' => false, 'message' => 'Login required'], 401);
        }
        $email    = trim($b['email'] ?? $b['user_email'] ?? $_SESSION['user']['email'] ?? '');
        $mobile   = trim($b['mobile'] ?? $b['user_mobile'] ?? $_SESSION['user']['mobile'] ?? '');
        $fcmToken = trim($b['fcm_token'] ?? $b['token'] ?? '');
        if (!$fcmToken) jsonResponse(['success' => false, 'message' => 'No token provided'], 200);

        $conn = db();
        $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online, last_active_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, last_active_at = NOW(), updated_at = NOW()");
        if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcmToken, $email, $mobile); $stmtFcm->execute(); }
        if (!empty($mobile)) {
            $clean10 = substr(preg_replace('/\D/', '', $mobile), -10);
            $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '" . $conn->real_escape_string($clean10) . "'");
        }
        if (!empty($email)) {
            $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE LOWER(email) = '" . $conn->real_escape_string(strtolower($email)) . "'");
        }

        if (isset($_SESSION['user'])) $_SESSION['user']['fcm_token'] = $fcmToken;

        jsonResponse(['success' => true, 'message' => 'FCM token saved']);
    }

    if ($action === 'update_profile') {
        if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        $newName  = trim($b['name'] ?? '');
        $newEmail = trim($b['email'] ?? '');
        $userId   = intval($_SESSION['user']['id'] ?? 0);
        $conn = db();

        if ($newName) {
            $stmt = $conn->prepare("UPDATE app_users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $newName, $userId);
            $stmt->execute();
            $_SESSION['user']['name'] = $newName;
        }
        if ($newEmail) {
            $stmt2 = $conn->prepare("UPDATE app_users SET email = ? WHERE id = ?");
            $stmt2->bind_param('si', $newEmail, $userId);
            $stmt2->execute();
            $_SESSION['user']['email'] = $newEmail;
        }
        jsonResponse(['success' => true, 'message' => 'Profile updated', 'user' => $_SESSION['user']]);
    }
}

// === NON-JSON REDIRECT ===
if (!isJsonRequest()) {
    $redir = trim($_GET['redirect'] ?? '');
    $target = './index.php?login=1';
    if ($redir) $target .= '&redirect=' . urlencode($redir);
    header('Location: ' . $target);
    exit;
}

jsonResponse(['error' => 'Invalid request method or action'], 400);
<?php
/**
 * PAVANCAB GOA TAXI - Authentication & WhatsApp OTP Module
 * Path: app/auth.php
 *
 * Handles: User login, Driver login, Admin login, OTP, Password auth
 * Driver endpoints use action=driver_X prefix (merged from api_driver.php)
 * v4 - merged cron_tick endpoint (every-minute cron)
 */
// v4 - cron_tick at top, OPcache bust

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$b      = getBody();

// === CRON TICK (every minute) ===
if ($action === 'cron_tick' && ($_GET['key'] ?? '') === 'pavancab_cron_2026') {
    date_default_timezone_set('Asia/Kolkata');
    $conn = db();
    $nowTs = time();
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $log = [];
    $startMs = microtime(true);

    // 1. OTP cleanup
    try {
        $del = $conn->query("DELETE FROM app_otp_store WHERE expires_at < NOW()");
        if ($del && $del->affected_rows > 0) $log[] = "OTP: -{$del->affected_rows}";
    } catch (Exception $e) {}

    // 2. Ride-soon reminder (60 min)
    try {
        $rides = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.driver_id, b.driver_phone, b.total_fare, b.reminder_sent, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as dphone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE UPPER(b.status) IN ('CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent = 0) AND b.pickup_date IN (?, ?)", 'ss', [$today, $tomorrow]);
        foreach ($rides as $ride) {
            $diffMin = (strtotime("{$ride['pickup_date']} {$ride['pickup_time']}") - $nowTs) / 60;
            $ageMin = ($nowTs - strtotime($ride['created_at'] ?? 'now')) / 60;
            if ($ageMin < 30) continue;
            if ($diffMin > 0 && $diffMin <= 60) {
                $dP = $ride['dphone'] ?? '';
                $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                if ($dP) @sendMetaWhatsApp($dP, "Ã¢ÂÂ° *RIDE REMINDER*\n\nRef: #{$ride['booking_ref']}\nPassenger: {$ride['customer_name']}\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: Ã¢â€šÂ¹{$ride['total_fare']}\n\nRide in " . intval($diffMin) . " min! Head to pickup now.");
                @sendFCMPushToDriver($ride['driver_id'] ?: $dP, "Ride in " . intval($diffMin) . " min!", "Pickup {$ride['customer_name']}", ['type' => 'RIDE_REMINDER', 'booking_id' => strval($ride['id'])]);
                dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                $log[] = "Reminder #{$ride['booking_ref']}";
            }
        }
    } catch (Exception $e) {}

    // 3. Unassigned urgent (90 min)
    try {
        $ua = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.cab_type, b.total_fare, b.reminder_sent FROM app_bookings b WHERE UPPER(b.status) IN ('PENDING','CONFIRMED') AND (b.driver_id IS NULL OR b.driver_id = 0) AND b.pickup_date IN (?, ?)", 'ss', [$today, $tomorrow]);
        foreach ($ua as $ride) {
            $diffMin = (strtotime("{$ride['pickup_date']} {$ride['pickup_time']}") - $nowTs) / 60;
            if ($diffMin > 0 && $diffMin <= 90 && ($ride['reminder_sent'] ?? 0) < 3) {
                $urg = $diffMin <= 30 ? "Ã°Å¸Å¡Â¨ URGENT" : "Ã¢Å¡Â Ã¯Â¸Â NEEDS DRIVER";
                $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                @sendMetaWhatsApp('+919000000000', "$urg Ã¢â‚¬â€ Ride #{$ride['booking_ref']}\n\nPassenger: {$ride['customer_name']} ({$ride['customer_phone']})\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: Ã¢â€šÂ¹{$ride['total_fare']}\n\nPickup in " . intval($diffMin) . " min! Assign driver NOW.");
                @sendFCMPushToAdmins("$urg Ride #{$ride['booking_ref']}", "Pickup in " . intval($diffMin) . " min!", ['type' => 'UNASSIGNED_URGENT']);
                dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                $log[] = "Unassigned #{$ride['booking_ref']}";
            }
        }
    } catch (Exception $e) {}

    // 4. Night ride alert (10PM)
    try {
        $cHour = intval(date('G'));
        if ($cHour >= 22 || $cHour < 1) {
            $nr = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.total_fare, b.reminder_sent FROM app_bookings b WHERE UPPER(b.status) IN ('PENDING','CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent < 5) AND b.pickup_date IN (?, ?)", 'ss', [$today, $tomorrow]);
            foreach ($nr as $ride) {
                $pH = intval(date('G', strtotime("{$ride['pickup_date']} {$ride['pickup_time']}")));
                if ($pH >= 22 || $pH < 6) {
                    $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                    @sendMetaWhatsApp('+919000000000', "Ã°Å¸Å’â„¢ *NIGHT RIDE*\nRef: #{$ride['booking_ref']}\n{$ride['customer_name']} ({$ride['customer_phone']})\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: Ã¢â€šÂ¹{$ride['total_fare']}\n1.5x multiplier.");
                    dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                    $log[] = "Night #{$ride['booking_ref']}";
                }
            }
        }
    } catch (Exception $e) {}

    // 5. Subscription expiry reminders
    try {
        $subs = dbRows("SELECT ds.*, d.phone as dphone, d.name as dname FROM driver_subscriptions ds JOIN app_drivers d ON ds.driver_id = d.id WHERE ds.status = 'active' AND ds.end_date >= ? AND ds.end_date <= DATE_ADD(?, INTERVAL 7 DAY)", 'ss', [$today, $today]);
        foreach ($subs as $sub) {
            $daysLeft = max(0, (strtotime($sub['end_date']) - strtotime($today)) / 86400);
            $dP = $sub['dphone'];
            $dN = $sub['dname'] ?: 'Driver';
            $lr = $sub['last_reminder_sent'] ?? '';
            if ($daysLeft <= 0 && $lr !== $today) {
                @sendMetaWhatsApp($dP, "Ã°Å¸â€â€ž *SUBSCRIPTION EXPIRED*\nHi $dN, your PAVANCAB subscription expired today.\nPay Ã¢â€šÂ¹200/ride commission or renew Ã¢â€šÂ¹1000/month!\nOpen Driver App to subscribe.");
                dbExec("UPDATE driver_subscriptions SET status = 'expired', last_reminder_sent = ? WHERE id = ?", 'si', [$today, $sub['id']]);
                dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [$sub['driver_id']]);
                $log[] = "Sub expired #{$sub['driver_id']}";
            } elseif ($daysLeft <= 1 && $lr !== '1d') {
                @sendMetaWhatsApp($dP, "Ã¢ÂÂ° *SUBSCRIPTION EXPIRES TOMORROW*\nHi $dN, your subscription expires {$sub['end_date']}.\nRenew Ã¢â€šÂ¹1000/month in Driver App!");
                dbExec("UPDATE driver_subscriptions SET last_reminder_sent = '1d' WHERE id = ?", 'i', [$sub['id']]);
                $log[] = "Sub 1d #{$sub['driver_id']}";
            } elseif ($daysLeft <= 3 && $lr !== '3d') {
                @sendMetaWhatsApp($dP, "Ã°Å¸â€œÂ¢ *SUBSCRIPTION EXPIRES IN 3 DAYS*\nHi $dN, expires {$sub['end_date']}. Renew Ã¢â€šÂ¹1000/month!");
                dbExec("UPDATE driver_subscriptions SET last_reminder_sent = '3d' WHERE id = ?", 'i', [$sub['id']]);
                $log[] = "Sub 3d #{$sub['driver_id']}";
            }
        }
    } catch (Exception $e) {}

    // 6. Pending commission reminders (every 6h)
    try {
        if (intval(date('G')) % 6 === 0) {
            $up = dbRows("SELECT dp.driver_id, d.phone as dphone, d.name as dname, COUNT(*) as cnt, SUM(dp.amount) as total FROM driver_payments dp JOIN app_drivers d ON dp.driver_id = d.id WHERE dp.status = 'pending' GROUP BY dp.driver_id HAVING cnt > 0");
            foreach ($up as $row) {
                @sendMetaWhatsApp($row['dphone'], "Ã°Å¸â€™Â° *PENDING COMMISSION*\nHi {$row['dname']}, {$row['cnt']} unpaid ride(s) = Ã¢â€šÂ¹{$row['total']}.\nPay in Driver App or subscribe Ã¢â€šÂ¹1000/month!");
                $log[] = "Comm reminder #{$row['driver_id']}";
            }
        }
    } catch (Exception $e) {}

    // 7. Offline cleanup (>5min no activity)
    try {
        $off = $conn->query("UPDATE app_drivers SET is_online = 0 WHERE is_online = 1 AND last_active_at IS NOT NULL AND last_active_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        if ($off && $off->affected_rows > 0) $log[] = "Offline: -{$off->affected_rows}";
    } catch (Exception $e) {}

    // 8. Stale FCM cleanup (90 days)
    try {
        $fc = $conn->query("DELETE FROM app_fcm_tokens WHERE last_active_at IS NOT NULL AND last_active_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        if ($fc && $fc->affected_rows > 0) $log[] = "FCM: -{$fc->affected_rows}";
    } catch (Exception $e) {}

    // 9. Daily summary (9AM)
    try {
        if (date('H:i') === '09:00') {
            $yd = date('Y-m-d', strtotime('-1 day'));
            $bk = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as rev FROM app_bookings WHERE DATE(created_at) = ?", 's', [$yd]);
            $comp = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status = 'COMPLETED' AND pickup_date = ?", 's', [$yd]);
            $canc = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status LIKE '%CANCELLED%' AND DATE(created_at) = ?", 's', [$yd]);
            $pend = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status IN ('PENDING','CONFIRMED') AND pickup_date = ?", 's', [$today]);
            $on = dbRows("SELECT COUNT(*) as cnt FROM app_drivers WHERE is_online = 1");
            $new = dbRows("SELECT COUNT(*) as cnt FROM app_users WHERE DATE(created_at) = ?", 's', [$yd]);
            @sendMetaWhatsApp('+919000000000', "Ã°Å¸â€œÅ  *DAILY SUMMARY Ã¢â‚¬â€ $yd*\n\nÃ°Å¸Å¡â€¢ Bookings: {$bk[0]['cnt']}\nÃ¢Å“â€¦ Completed: {$comp[0]['cnt']}\nÃ¢ÂÅ’ Cancelled: {$canc[0]['cnt']}\nÃ°Å¸â€™Â° Revenue: Ã¢â€šÂ¹{$bk[0]['rev']}\nÃ°Å¸â€œâ€¹ Today Pending: {$pend[0]['cnt']}\nÃ°Å¸Å¡â€” Online Drivers: {$on[0]['cnt']}\nÃ°Å¸â€˜Â¤ New Users: {$new[0]['cnt']}");
            $log[] = "Daily summary";
        }
    } catch (Exception $e) {}

    // 10. Expired subscription cleanup
    try {
        $exp = $conn->query("UPDATE driver_subscriptions SET status = 'expired' WHERE status = 'active' AND end_date < CURDATE()");
        if ($exp && $exp->affected_rows > 0) {
            $er = $conn->query("SELECT driver_id FROM driver_subscriptions WHERE status = 'expired' AND end_date < CURDATE()");
            if ($er) while ($r = $er->fetch_assoc()) dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [intval($r['driver_id'])]);
            $log[] = "Expired: -{$exp->affected_rows}";
        }
    } catch (Exception $e) {}

    $elapsed = round((microtime(true) - $startMs) * 1000);
    jsonResponse(['status' => 'ok', 'time' => date('Y-m-d H:i:s'), 'ms' => $elapsed, 'tasks' => $log]);
}

// === USER LOGOUT ===
if ($action === 'logout' || isset($_GET['logout']) || isset($_POST['logout'])) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['isLoggedIn'])) {
        if (isJsonRequest()) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        header('Location: ./index.php');
        exit;
    }
    $fcmToken  = trim($b['fcm_token'] ?? $_GET['fcm_token'] ?? $_POST['fcm_token'] ?? '');
    $userId    = intval($_SESSION['user']['id'] ?? 0);
    $userPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? '');

    try {
        $conn = db();
        if ($conn) {
            if ($fcmToken) {
                $fSafe = $conn->real_escape_string($fcmToken);
                $conn->query("DELETE FROM app_fcm_tokens WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_users SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_drivers SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_team_members SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
            }
            if ($userPhone) {
                $clean10 = substr($userPhone, -10);
                $conn->query("DELETE FROM app_fcm_tokens WHERE RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
                $conn->query("UPDATE app_users SET is_online = 0, fcm_token = NULL WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
            }
        }
    } catch (Exception $e) {}

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    if (isset($_COOKIE[session_name()])) @setcookie(session_name(), '', time() - 86400, '/');
    if (isset($_COOKIE['PHPSESSID'])) @setcookie('PHPSESSID', '', time() - 86400, '/');

    if (isJsonRequest()) jsonResponse(['success' => true, 'message' => 'Logged out successfully and FCM push token cleared']);
    header('Location: ./index.php');
    exit;
}

// === GET SESSION ===
if ($method === 'GET' && ($action === 'me' || isset($_GET['me']))) {
    if (isset($_SESSION['user'])) {
        jsonResponse(['success' => true, 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        jsonResponse(['success' => false, 'isLoggedIn' => false, 'user' => null]);
    }
}

// ============================================================
// DRIVER API ENDPOINTS (moved before POST block to avoid
// the isset($b['phone']) catch-all in send_otp handler)
// ============================================================

if (strpos($action, 'driver_') === 0) {
    $da = substr($action, 7);

    // Auto-migrate driver table columns
    try {
        $conn = db();
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL");
    } catch (Exception $e) {}

    // ===== DRIVER: VERIFY OTP =====
    if ($da === 'verify-otp' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        if (!$phone || !$otp) jsonResponse(['success' => false, 'error' => 'Phone and OTP required'], 400);

        $clean10 = substr($phone, -10);
        $cleanOtp = trim($otp);
        $conn = db();

        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $phone, $clean10, $cleanOtp);
        $stmt->execute();
        $r = $stmt->get_result();
        $otpRow = $r ? $r->fetch_assoc() : null;

        if (!$otpRow) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP. Please try again.'], 401);

        dbExec("DELETE FROM app_otp_store WHERE id = ?", 'i', [$otpRow['id']]);

        $stmt = $conn->prepare("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('s', $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $driver = $r ? $r->fetch_assoc() : null;

        if ($driver) {
            $_SESSION['driver'] = [
                'id' => intval($driver['id']),
                'name' => $driver['name'],
                'phone' => $phone,
                'car_model' => $driver['car_model'] ?? '',
                'plate_number' => $driver['plate_number'] ?? '',
                'isLoggedIn' => true
            ];
            $approved = intval($driver['is_approved'] ?? 0);
            jsonResponse([
                'success' => true,
                'approved' => $approved == 1,
                'existing_driver' => true,
                'driver' => [
                    'id' => intval($driver['id']),
                    'name' => $driver['name'],
                    'phone' => $phone,
                    'car_model' => $driver['car_model'] ?? '',
                    'plate_number' => $driver['plate_number'] ?? '',
                    'rating' => floatval($driver['rating'] ?? 5.0),
                    'is_approved' => $approved
                ]
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO app_drivers (name, phone, car_model, plate_number, is_approved, status) VALUES (?, ?, '', '', 0, 'pending')");
            $stmt->bind_param('ss', $phone, $phone);
            $stmt->execute();
            $newId = $conn->insert_id;

            $_SESSION['driver'] = [
                'id' => $newId,
                'name' => '',
                'phone' => $phone,
                'car_model' => '',
                'plate_number' => '',
                'isLoggedIn' => true
            ];

            try { sendFCMPushToAdmins("New Driver Registration", "Phone: $phone has registered as a driver. Please approve from Admin app.", ['type' => 'NEW_DRIVER', 'driver_id' => strval($newId)]); } catch (Exception $e) {}
            try { sendMetaWhatsApp("+919000000000", "NEW DRIVER REGISTRATION\n\nPhone: $phone\nStatus: PENDING APPROVAL\n\nPlease approve from Admin app."); } catch (Exception $e) {}

            jsonResponse([
                'success' => true,
                'approved' => false,
                'existing_driver' => false,
                'driver' => [
                    'id' => $newId,
                    'name' => '',
                    'phone' => $phone,
                    'car_model' => '',
                    'plate_number' => '',
                    'rating' => 5.0,
                    'is_approved' => 0
                ]
            ]);
        }
    }

    // ===== DRIVER: CHECK SESSION =====
    if ($da === 'check-session') {
        if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        $approved = !empty($rows) ? intval($rows[0]['is_approved'] ?? 0) : 0;
        jsonResponse(['success' => true, 'approved' => $approved == 1, 'driver' => $_SESSION['driver']]);
    }

    // ===== DRIVER: CHECK APPROVAL =====
    if ($da === 'check-approval') {
        if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT is_approved, name, car_model, plate_number FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
        jsonResponse(['success' => true, 'approved' => intval($rows[0]['is_approved'] ?? 0) == 1, 'driver' => $rows[0]]);
    }

    // ===== DRIVER: CHECK PHONE (has password?) =====
    if ($da === 'check-phone' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        if (!$phone || strlen($phone) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone required'], 400);
        $clean10 = substr($phone, -10);
        $rows = dbRows("SELECT id, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
        jsonResponse(['success' => true, 'exists' => !empty($rows), 'has_password' => !empty($rows[0]['password_hash'] ?? null)]);
    }

    // ===== DRIVER: SET PASSWORD =====
    if ($da === 'set-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $clean10 = substr($phone, -10);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
        jsonResponse(['success' => true, 'message' => 'Password set successfully']);
    }

    // ===== DRIVER: LOGIN WITH PASSWORD =====
    if ($da === 'login-with-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        $clean10 = substr($phone, -10);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
        if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Driver account not found. Register with OTP first.'], 404);
        $driver = $rows[0];
        if (empty($driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
        if (!password_verify($password, $driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
        $approved = intval($driver['is_approved'] ?? 0);
        $_SESSION['driver'] = [
            'id' => intval($driver['id']),
            'name' => $driver['name'],
            'phone' => $phone,
            'car_model' => $driver['car_model'] ?? '',
            'plate_number' => $driver['plate_number'] ?? '',
            'isLoggedIn' => true
        ];
        jsonResponse([
            'success' => true,
            'approved' => $approved == 1,
            'driver' => [
                'id' => intval($driver['id']),
                'name' => $driver['name'],
                'phone' => $phone,
                'car_model' => $driver['car_model'] ?? '',
                'plate_number' => $driver['plate_number'] ?? '',
                'rating' => floatval($driver['rating'] ?? 5.0),
                'is_approved' => $approved
            ]
        ]);
    }

    // ===== DRIVER: RESET PASSWORD =====
    if ($da === 'reset-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $clean10 = substr($phone, -10);
        $conn = db();
        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $phone, $clean10, $otp);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
        dbExec("DELETE FROM app_otp_store WHERE phone = ? OR RIGHT(phone, 10) = ?", 'ss', [$phone, $clean10]);
        jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    }

    // ===== DRIVER: LOGOUT =====
    if ($da === 'logout' && $method === 'POST') {
        if (!empty($_SESSION['driver']['id'])) {
            $driverId = intval($_SESSION['driver']['id']);
            try { dbExec("UPDATE app_drivers SET is_online = 0 WHERE id = ?", 'i', [$driverId]); } catch (Exception $e) {}
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
        jsonResponse(['success' => true]);
    }

    // ===== Require Driver Auth (for authenticated endpoints) =====
    if (in_array($da, ['my-bookings', 'booking-detail', 'respond', 'trip-status', 'earnings', 'profile', 'toggle-online'])) {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver authentication required. Please login.'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
    }

    // ===== DRIVER: MY BOOKINGS =====
    if ($da === 'my-bookings') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $cleanPhone10 = '';
        if ($driverPhone) {
            $cleanPhone10 = substr(preg_replace('/\D/', '', $driverPhone), -10);
        }
        if ($cleanPhone10) {
            $rows = dbRows(
                "SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? OR b.driver_id IN (SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?) ORDER BY b.pickup_date DESC, b.pickup_time DESC LIMIT 100",
                'is', [$driverId, $cleanPhone10]
            );
        } else {
            $rows = dbRows(
                "SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? ORDER BY b.pickup_date DESC, b.pickup_time DESC LIMIT 100",
                'i', [$driverId]
            );
        }
        jsonResponse(['bookings' => $rows]);
    }

    // ===== DRIVER: BOOKING DETAIL =====
    if ($da === 'booking-detail') {
        $driverId = intval($_SESSION['driver']['id']);
        $bookingId = intval($b['id'] ?? $_GET['id'] ?? 0);
        if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
        $rows = dbRows("SELECT b.* FROM app_bookings b WHERE b.id = ? AND (b.driver_id = ? OR ? = 0) LIMIT 1", 'iii', [$bookingId, $driverId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        jsonResponse(['booking' => $rows[0]]);
    }

    // ===== DRIVER: RESPOND TO BOOKING =====
    if ($da === 'respond' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverName = $_SESSION['driver']['name'] ?? '';
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $bookingId = intval($b['booking_id'] ?? 0);
        $decision = strtoupper(trim($b['decision'] ?? ''));

        if (!$bookingId || !in_array($decision, ['ACCEPT', 'REJECT'])) {
            jsonResponse(['error' => 'booking_id and decision (ACCEPT/REJECT) required'], 400);
        }

        $rows = dbRows("SELECT id, status, driver_id, customer_name, customer_phone, booking_ref FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

        $booking = $rows[0];
        if ($booking['status'] !== 'ASSIGNED' && $booking['status'] !== 'ACCEPTED') {
            jsonResponse(['error' => 'Booking is in ' . $booking['status'] . ' status, cannot respond'], 400);
        }

        if ($decision === 'ACCEPT') {
            $now = date('Y-m-d');
            $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
            if (empty($sub)) {
                $pending = dbRows("SELECT COUNT(*) as cnt FROM driver_payments WHERE driver_id = ? AND status = 'pending' LIMIT 1", 'i', [$driverId]);
                $pendingCount = intval($pending[0]['cnt'] ?? 0);
                if ($pendingCount > 0) {
                    jsonResponse(['success' => false, 'error' => 'payment_required', 'message' => "You have $pendingCount unpaid ride commission(s). Pay Ã¢â€šÂ¹{$pendingCount}00 or subscribe for Ã¢â€šÂ¹1000/month to accept rides.", 'pending_count' => $pendingCount], 402);
                }
            }
            dbExec("UPDATE app_bookings SET status = 'ACCEPTED', driver_decision = 'ACCEPTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
            try { sendFCMPushToAdmins("Driver Accepted (#{$booking['booking_ref']})", "$driverName ($driverPhone) accepted ride #{$booking['booking_ref']} for {$booking['customer_name']}", ['type' => 'BOOKING_CONFIRMED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
        } else {
            dbExec("UPDATE app_bookings SET status = 'PENDING', driver_id = 0, driver_name = '', driver_phone = '', driver_decision = 'REJECTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
            try { sendFCMPushToAdmins("Driver Rejected (#{$booking['booking_ref']})", "$driverName REJECTED ride #{$booking['booking_ref']}. Please re-assign!", ['type' => 'DRIVER_REJECTED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'decision' => $decision]);
    }

    // ===== DRIVER: TRIP STATUS UPDATE =====
    if ($da === 'trip-status' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $bookingId = intval($b['booking_id'] ?? 0);
        $status = strtoupper(trim($b['status'] ?? ''));

        if (!$bookingId || !$status) jsonResponse(['error' => 'booking_id and status required'], 400);

        $allowed = ['IN_TRIP', 'IN_TRANSIT', 'COMPLETED', 'STARTED'];
        if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);

        $rows = dbRows("SELECT id, status, driver_id, booking_ref, customer_name, customer_phone, pickup_location, drop_location, total_fare FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

        $mapStatus = $status;
        if ($status === 'IN_TRIP') $mapStatus = 'IN_TRANSIT';
        if ($status === 'STARTED') $mapStatus = 'IN_TRANSIT';

        dbExec("UPDATE app_bookings SET status = ?, updated_at = NOW() WHERE id = ?", 'si', [$mapStatus, $bookingId]);

        if ($mapStatus === 'IN_TRANSIT') {
            $canStart = true;
            if ($booking['status'] === 'ASSIGNED' || $booking['status'] === 'ACCEPTED') {
                $now = date('Y-m-d');
                $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
                if (empty($sub)) {
                    $pending = dbRows("SELECT COUNT(*) as cnt FROM driver_payments WHERE driver_id = ? AND status = 'pending' LIMIT 1", 'i', [$driverId]);
                    $pendingCount = intval($pending[0]['cnt'] ?? 0);
                    if ($pendingCount > 0) {
                        jsonResponse(['success' => false, 'error' => 'payment_required', 'message' => "You have $pendingCount unpaid commission(s). Pay Ã¢â€šÂ¹" . ($pendingCount * 200) . " or subscribe to accept rides.", 'pending_count' => $pendingCount], 402);
                    }
                }
            }
            dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
            try { broadcastRideLifecycleFCM('RIDE_STARTED', $bookingId); } catch (Exception $e) {}
        }
        if ($mapStatus === 'COMPLETED') {
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
            $now = date('Y-m-d');
            $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
            if (empty($sub)) {
                $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
                $commissionAmount = floatval($settings[0]['setting_value'] ?? 200);
                dbExec("INSERT INTO driver_payments (driver_id, type, booking_id, amount, status) VALUES (?, 'commission', ?, ?, 'pending')", 'iid', [$driverId, $bookingId, $commissionAmount]);
                dbExec("UPDATE app_bookings SET commission_status = 'pending' WHERE id = ?", 'i', [$bookingId]);
                try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "RIDE COMPLETED!\n\nRef: #{$rows[0]['booking_ref']}\nCommission: Ã¢â€šÂ¹$commissionAmount\n\nPay Ã¢â€šÂ¹$commissionAmount to accept your next ride, or subscribe for Ã¢â€šÂ¹1000/month for unlimited rides!\n\nPay now in the Driver App."); } catch (Exception $e) {}
            } else {
                dbExec("UPDATE app_bookings SET commission_status = 'waived' WHERE id = ?", 'i', [$bookingId]);
            }
            try { broadcastRideLifecycleFCM('RIDE_COMPLETED', $bookingId); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'status' => $mapStatus]);
    }

    // ===== DRIVER: EARNINGS =====
    if ($da === 'earnings') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $cleanPhone10 = substr(preg_replace('/\D/', '', $driverPhone), -10);
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');

        if ($cleanPhone10) {
            $driverFilter = "driver_id = ? OR driver_id IN (SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?)";
            $todayRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE ($driverFilter) AND status = 'COMPLETED' AND pickup_date = ?", 'iss', [$driverId, $cleanPhone10, $today]);
            $weekRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE ($driverFilter) AND status = 'COMPLETED' AND pickup_date >= ?", 'iss', [$driverId, $cleanPhone10, $weekStart]);
            $monthRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE ($driverFilter) AND status = 'COMPLETED' AND pickup_date >= ?", 'iss', [$driverId, $cleanPhone10, $monthStart]);
        } else {
            $todayRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date = ?", 'is', [$driverId, $today]);
            $weekRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= ?", 'is', [$driverId, $weekStart]);
            $monthRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= ?", 'is', [$driverId, $monthStart]);
        }
        $commRow = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'commission_per_ride' LIMIT 1");
        $commission = !empty($commRow) ? intval($commRow[0]['setting_value']) : 300;

        jsonResponse([
            'today_rides' => intval($todayRow[0]['cnt'] ?? 0), 'today_earnings' => floatval($todayRow[0]['total'] ?? 0),
            'week_rides' => intval($weekRow[0]['cnt'] ?? 0), 'week_earnings' => floatval($weekRow[0]['total'] ?? 0),
            'month_rides' => intval($monthRow[0]['cnt'] ?? 0), 'month_earnings' => floatval($monthRow[0]['total'] ?? 0),
            'commission_per_ride' => $commission
        ]);
    }

    // ===== DRIVER: PROFILE =====
    if ($da === 'profile') {
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, status, is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
        jsonResponse(['driver' => $rows[0]]);
    }

    // ===== DRIVER: TOGGLE ONLINE =====
    if ($da === 'toggle-online' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $row = dbRows("SELECT is_online FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        $current = intval($row[0]['is_online'] ?? 0);
        $newVal = $current == 1 ? 0 : 1;
        dbExec("UPDATE app_drivers SET is_online = ? WHERE id = ?", 'ii', [$newVal, $driverId]);
        jsonResponse(['success' => true, 'is_online' => $newVal]);
    }

    // ===== DRIVER: SAVE FCM TOKEN =====
    if ($da === 'save-fcm-token' && $method === 'POST') {
        $fcmToken = trim($b['fcm_token'] ?? '');
        $phone = cleanPhoneDigits($b['phone'] ?? '');

        if ($fcmToken) {
            if ($phone) {
                $clean10 = substr($phone, -10);
                dbExec("UPDATE app_drivers SET fcm_token = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$fcmToken, $clean10]);
            }
            $existing = dbRows("SELECT id FROM app_fcm_tokens WHERE fcm_token = ? LIMIT 1", 's', [$fcmToken]);
            if (empty($existing)) {
                dbExec("INSERT INTO app_fcm_tokens (fcm_token, user_mobile) VALUES (?, ?)", 'ss', [$fcmToken, $phone ?: null]);
            }
        }
        jsonResponse(['success' => true]);
    }

    // ===== ADMIN: APPROVE DRIVER =====
    if ($da === 'approve-driver' && $method === 'POST') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);

        $driverId = intval($b['driver_id'] ?? 0);
        $approved = intval($b['approved'] ?? 1);
        if (!$driverId) jsonResponse(['error' => 'driver_id required'], 400);

        dbExec("UPDATE app_drivers SET is_approved = ? WHERE id = ?", 'ii', [$approved, $driverId]);

        $rows = dbRows("SELECT phone, name FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (!empty($rows)) {
            $phone = $rows[0]['phone'];
            if ($approved) {
                try {
                    sendFCMPushToDriver($driverId, "Account Approved!", "Your driver account has been approved. You can now start accepting rides.", ['type' => 'APPROVED']);
                    sendMetaWhatsApp($phone, "ACCOUNT APPROVED!\n\nYour PAVANCAB Driver account has been approved!\n\nOpen the Driver App to start accepting rides. Drive safe!");
                } catch (Exception $e) {}
            } else {
                try {
                    sendFCMPushToDriver($driverId, "Account Rejected", "Your driver account has been rejected. Contact support for details.", ['type' => 'REJECTED']);
                } catch (Exception $e) {}
            }
        }

        jsonResponse(['success' => true, 'approved' => $approved == 1]);
    }

    // ===== ADMIN: LIST PENDING DRIVERS =====
    if ($da === 'pending-drivers') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, created_at FROM app_drivers WHERE is_approved = 0 ORDER BY id DESC");
        jsonResponse(['drivers' => $rows]);
    }

    // ===== ADMIN: LIST ALL DRIVERS =====
    if ($da === 'all-drivers') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, rating, status FROM app_drivers ORDER BY id DESC");
        jsonResponse(['drivers' => $rows]);
    }

    // ===== DRIVER: SUBSCRIPTION STATUS =====
    if ($da === 'subscription-status') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $now = date('Y-m-d');
        $active = dbRows("SELECT id, start_date, end_date, amount FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? ORDER BY id DESC LIMIT 1", 'is', [$driverId, $now]);
        $hasActive = !empty($active);
        $endDate = $hasActive ? $active[0]['end_date'] : null;
        $daysLeft = $hasActive ? max(0, (strtotime($endDate) - strtotime($now)) / 86400) : 0;

        $pendingPayments = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM driver_payments WHERE driver_id = ? AND status = 'pending'", 'i', [$driverId]);
        $pendingCount = intval($pendingPayments[0]['cnt'] ?? 0);
        $pendingTotal = floatval($pendingPayments[0]['total'] ?? 0);

        $settings = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount', 'driver_commission_per_ride')");
        $config = [];
        foreach ($settings as $s) $config[$s['setting_key']] = $s['setting_value'];
        $subAmount = floatval($config['driver_subscription_amount'] ?? 1000);
        $rideCommission = floatval($config['driver_commission_per_ride'] ?? 200);

        jsonResponse([
            'is_subscribed' => $hasActive,
            'end_date' => $endDate,
            'days_left' => intval($daysLeft),
            'subscription_amount' => $subAmount,
            'commission_per_ride' => $rideCommission,
            'pending_payments_count' => $pendingCount,
            'pending_payments_total' => $pendingTotal,
            'can_accept' => $hasActive || $pendingCount == 0
        ]);
    }

    // ===== DRIVER: CREATE RAZORPAY ORDER =====
    if ($da === 'create-order' && $method === 'POST') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $paymentType = strtolower(trim($b['type'] ?? 'subscription'));
        $bookingId = intval($b['booking_id'] ?? 0);

        if ($paymentType === 'subscription') {
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_subscription_amount' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 1000);
            $receipt = "sub_driver{$driverId}_" . time();
        } else {
            if (!$bookingId) jsonResponse(['error' => 'booking_id required for ride commission'], 400);
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 200);
            $receipt = "comm_bk{$bookingId}_driver{$driverId}_" . time();
        }

        $keySecret = razorpayKeys();
        $keyId = $keySecret[0];
        $secret = $keySecret[1] ?? '';

        $amountPaise = intval($amount * 100);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => "$keyId:$secret",
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => ['driver_id' => strval($driverId), 'type' => $paymentType, 'booking_id' => strval($bookingId)]
            ])
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $order = json_decode($response, true);

        if ($httpCode === 200 && !empty($order['id'])) {
            jsonResponse([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $amountPaise,
                'amount_display' => $amount,
                'currency' => 'INR',
                'key_id' => $keyId,
                'type' => $paymentType
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to create order', 'details' => $order['error']['description'] ?? 'Unknown error'], 500);
        }
    }

    // ===== DRIVER: VERIFY PAYMENT =====
    if ($da === 'verify-payment' && $method === 'POST') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $razorpayOrderId = trim($b['razorpay_order_id'] ?? '');
        $razorpayPaymentId = trim($b['razorpay_payment_id'] ?? '');
        $razorpaySignature = trim($b['razorpay_signature'] ?? '');
        $paymentType = strtolower(trim($b['type'] ?? 'subscription'));
        $bookingId = intval($b['booking_id'] ?? 0);

        if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature) {
            jsonResponse(['error' => 'Missing payment details'], 400);
        }

        $secret = razorpayKeys()[1];
        $expectedSig = hash_hmac('sha256', "$razorpayOrderId|$razorpayPaymentId", $secret);
        $isValid = hash_equals($expectedSig, $razorpaySignature);

        if (!$isValid) {
            jsonResponse(['success' => false, 'error' => 'Payment verification failed - signature mismatch'], 400);
        }

        if ($paymentType === 'subscription') {
            $amount = 0;
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_subscription_amount' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 1000);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+1 month'));
            dbExec("INSERT INTO driver_subscriptions (driver_id, start_date, end_date, amount, razorpay_order_id, razorpay_payment_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
                'isssss', [$driverId, $startDate, $endDate, $amount, $razorpayOrderId, $razorpayPaymentId]);
            dbExec("UPDATE app_drivers SET has_active_subscription = 1 WHERE id = ?", 'i', [$driverId]);
            try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "SUBSCRIPTION ACTIVATED!\n\nYour PAVANCAB driver subscription is now active until $endDate.\n\nNo commission on rides. Accept as many rides as you want!\n\nThank you for being a PAVANCAB partner."); } catch (Exception $e) {}
        } else {
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 200);
            dbExec("INSERT INTO driver_payments (driver_id, type, booking_id, amount, razorpay_order_id, razorpay_payment_id, status, paid_at) VALUES (?, 'commission', ?, ?, ?, ?, 'paid', NOW())",
                'iidsss', [$driverId, $bookingId, $amount, $razorpayOrderId, $razorpayPaymentId]);
            if ($bookingId) {
                dbExec("UPDATE app_bookings SET commission_status = 'paid' WHERE id = ?", 'i', [$bookingId]);
            }
            try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "COMMISSION PAID!\n\nÃ¢â€šÂ¹$amount commission paid for ride #$bookingId.\n\nYou can now accept the next ride!"); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'message' => 'Payment verified successfully']);
    }

    // ===== DRIVER: PAYMENT HISTORY =====
    if ($da === 'payment-history') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $payments = dbRows("SELECT * FROM driver_payments WHERE driver_id = ? ORDER BY created_at DESC LIMIT 50", 'i', [$driverId]);
        $subscriptions = dbRows("SELECT * FROM driver_subscriptions WHERE driver_id = ? ORDER BY created_at DESC LIMIT 10", 'i', [$driverId]);
        jsonResponse(['payments' => $payments, 'subscriptions' => $subscriptions]);
    }

    // If no driver action matched, return error
    jsonResponse(['error' => 'Unknown driver action: ' . $da], 400);
}

if ($method === 'POST') {
    // === VERIFY OTP (USER) ===
    if ($action === 'verify_otp' || !empty($b['otp'])) {
        $phone = trim($b['phone'] ?? $_POST['phone'] ?? $_GET['phone'] ?? '');
        $otp   = trim($b['otp'] ?? $_POST['otp'] ?? $_GET['otp'] ?? '');
        $name  = trim($b['name'] ?? $_POST['name'] ?? $_GET['name'] ?? '');
        $email = trim($b['email'] ?? $_POST['email'] ?? $_GET['email'] ?? '');

        if (!$phone || !$otp) {
            jsonResponse(['success' => false, 'error' => 'Phone number and 6-digit OTP code are required.'], 400);
        }

        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $cleanOtp    = trim($otp);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $conn = db();
        $otpValid = false;

        $stmt = $conn->prepare("SELECT * FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();

        if ($r && $row = $r->fetch_assoc()) {
            if (trim($row['otp']) === $cleanOtp) {
                $otpValid = true;
                $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
                $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
                $stmtDel->execute();
            }
        }

        if (!$otpValid && isset($_SESSION['pending_otp'])) {
            if (($_SESSION['pending_otp']['phone'] === $cleanDigits || $_SESSION['pending_otp']['clean10'] === $clean10) && $_SESSION['pending_otp']['otp'] === $cleanOtp) {
                if ($_SESSION['pending_otp']['expires'] > time()) $otpValid = true;
            }
        }

        if (!$otpValid) {
            if (!isset($_SESSION['otp_attempts'])) $_SESSION['otp_attempts'] = [];
            $attempts = $_SESSION['otp_attempts'][$cleanDigits] ?? ['count' => 0, 'first' => time()];
            if (time() - $attempts['first'] > 600) $attempts = ['count' => 0, 'first' => time()];
            $attempts['count']++;
            $_SESSION['otp_attempts'][$cleanDigits] = $attempts;
            if ($attempts['count'] > 5) jsonResponse(['success' => false, 'error' => 'Too many failed attempts. Please wait 10 minutes and try again.'], 429);
            jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP code. Please request a new OTP.'], 401);
        }
        if (isset($_SESSION['otp_attempts'][$cleanDigits])) unset($_SESSION['otp_attempts'][$cleanDigits]);
        unset($_SESSION['pending_otp']);
        session_regenerate_id(true);

        $role = determineUserRole($cleanDigits, $email);
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');

        $finalName = $name;
        if ($isAdmin) {
            $finalName = 'Niranjan Yamgar (Admin)';
        } elseif ($isTeam) {
            $stmtTm = $conn->prepare("SELECT member_name FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), 10) = ? OR REPLACE(REPLACE(member_phone, '+', ''), ' ', '') = ?) AND is_active = 1 LIMIT 1");
            if ($stmtTm) {
                $stmtTm->bind_param('ss', $clean10, $cleanDigits);
                $stmtTm->execute();
                $rTm = $stmtTm->get_result();
                if ($rTm && $rowTm = $rTm->fetch_assoc()) $finalName = $rowTm['member_name'];
            }
        }
        if (!$finalName) $finalName = 'Goa Traveler';

        $formattedMobile = '+' . $cleanDigits;
        $reqFcm = trim($b['fcm_token'] ?? $_POST['fcm_token'] ?? $_GET['fcm_token'] ?? '');

        $stmtUser = $conn->prepare("SELECT id, name, email, role FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmtUser->bind_param('ss', $formattedMobile, $clean10);
        $stmtUser->execute();
        $rUser = $stmtUser->get_result();
        $isNewUser = true;
        $isBanned = false;

        if ($rUser && $rowU = $rUser->fetch_assoc()) {
            $isNewUser = false;
            $userId = $rowU['id'];
            $stmtBan = $conn->prepare("SELECT IFNULL(is_banned, 0) as banned FROM app_users WHERE id = ? LIMIT 1");
            if ($stmtBan) {
                $stmtBan->bind_param('i', $userId);
                $stmtBan->execute();
                $rBan = $stmtBan->get_result();
                if ($rBan && $rowBan = $rBan->fetch_assoc()) $isBanned = intval($rowBan['banned']) === 1;
            }
            if ($isBanned) jsonResponse(['success' => false, 'error' => 'Your account has been banned by the administrator. Please contact support.'], 403);
            if (!$name && $rowU['name']) $finalName = $rowU['name'];
            $stmtUpd = $conn->prepare("UPDATE app_users SET name = ?, mobile = ?, role = ?, last_active_at = NOW(), is_online = 1 WHERE id = ?");
            $stmtUpd->bind_param('sssi', $finalName, $formattedMobile, $role, $userId);
            $stmtUpd->execute();
        } else {
            $stmtIns = $conn->prepare("INSERT INTO app_users (name, mobile, email, role, last_active_at, is_online) VALUES (?, ?, ?, ?, NOW(), 1)");
            $stmtIns->bind_param('ssss', $finalName, $formattedMobile, $email, $role);
            $stmtIns->execute();
            $userId = $conn->insert_id;
        }

        if (!empty($reqFcm)) {
            $fSafe = $conn->real_escape_string($reqFcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $reqFcm, $email, $cleanDigits); $stmtFcm->execute(); }
            $stmtFcmUpd = $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE id = ?");
            $stmtFcmUpd->bind_param('si', $reqFcm, $userId);
            $stmtFcmUpd->execute();
        }

        $userSession = [
            'id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $email,
            'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true
        ];
        $_SESSION['user'] = $userSession;

        if ($role === 'user') {
            $loginType = $isNewUser ? 'joined & logged in' : 'logged in';
            $notifBody = "$finalName ($formattedMobile) $loginType to PAVANCAB.";
            if ($isNewUser) $notifBody .= " New user!";
            try {
                sendFCMPushToAdmins("Passenger " . ucfirst($loginType) . " (#$finalName)", $notifBody, ['url' => 'https://pavancab.com/app/dashboard/users.php', 'event_type' => 'NEW_BOOKING']);
            } catch (Exception $e) {}
        }

        jsonResponse([
            'success' => true, 'message' => 'Login verified successfully!', 'user' => $userSession,
            'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'has_password' => !empty($rowU['password_hash'] ?? null),
            'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php'
        ]);
    } elseif ($action === 'check_phone') {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone number required'], 400);

        $conn = db();
        $stmt = $conn->prepare("SELECT id, name, password_hash FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('ss', '+' . $cleanDigits, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;

        jsonResponse(['success' => true, 'exists' => !empty($row), 'has_password' => !empty($row['password_hash'] ?? null), 'name' => $row['name'] ?? '']);
    } elseif ($action === 'set_password') {
        $phone = trim($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $formattedMobile = '+' . $cleanDigits;

        $conn = db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?");
        $stmt->bind_param('sss', $hash, $formattedMobile, $clean10);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => 'Password set successfully']);
    } elseif ($action === 'login_with_password') {
        $phone = trim($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        $fcm = trim($b['fcm_token'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $formattedMobile = '+' . $cleanDigits;

        $conn = db();
        $stmt = $conn->prepare("SELECT id, name, email, role, password_hash, is_banned FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('ss', $formattedMobile, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;

        if (!$row) jsonResponse(['success' => false, 'error' => 'Account not found. Please register first with OTP.'], 404);
        if (empty($row['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
        if (!password_verify($password, $row['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
        if (intval($row['is_banned'] ?? 0) === 1) jsonResponse(['success' => false, 'error' => 'Account banned'], 403);

        $userId = intval($row['id']);
        $role = $row['role'] ?? 'user';
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');
        $finalName = $row['name'] ?: 'Goa Traveler';

        session_regenerate_id(true);
        $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE id = $userId");

        if (!empty($fcm)) {
            $fSafe = $conn->real_escape_string($fcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_fcm_tokens SET is_online = 0 WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcm, $row['email'] ?? '', $formattedMobile); $stmtFcm->execute(); }
            $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE id = ?")->bind_param('si', $fcm, $userId);
            $conn->query("UPDATE app_users SET fcm_token = '$fSafe', is_online = 1, last_active_at = NOW() WHERE id = $userId");
        }

        $userSession = ['id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $row['email'] ?? '', 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
        $_SESSION['user'] = $userSession;

        jsonResponse(['success' => true, 'message' => 'Login successful!', 'user' => $userSession, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php']);
    } elseif ($action === 'reset_password') {
        $phone = trim($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        $conn = db();
        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ssss', $cleanDigits, $cleanDigits, $clean10, $otp);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);

        $formattedMobile = '+' . $cleanDigits;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?")->bind_param('sss', $hash, $formattedMobile, $clean10);
        $conn->query("DELETE FROM app_otp_store WHERE phone = '$cleanDigits' OR RIGHT(phone, 10) = '$clean10'");

        jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    } elseif ($action === 'send_otp' || (isset($b['phone']) && strpos($action, 'driver_') !== 0)) {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);

        $appType = trim($b['app_type'] ?? 'passenger');
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $conn = db();

        $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
        $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmtDel->execute();

        $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->bind_param('ss', $cleanDigits, $otp);
        $stmt->execute();

        $_SESSION['pending_otp'] = [
            'phone'   => $cleanDigits,
            'clean10' => $clean10,
            'otp'     => $otp,
            'expires' => time() + 600
        ];

        $svc  = 'PAVANCAB';
        $appN = 'PAVANCAB';
        $supP = '+919000000000';
        if ($appType === 'driver') {
            $appN = 'PAVANCAB Driver';
            $supP = '+919518541625';
        } elseif ($appType === 'dispatch') {
            $appN = 'PAVANCAB Dispatch';
        }
        $acctLabel = $appType === 'driver' ? 'Driver' : ($appType === 'dispatch' ? 'Admin' : 'Passenger');
        $result = sendOTPWhatsAppTemplate($cleanDigits, $otp, $svc, $acctLabel, $appN, 'PAVANCAB', $supP);

        $waSent = true;
        $formattedDisplay = '+' . $cleanDigits;
        $msg = "WhatsApp OTP sent to $formattedDisplay!";

        if (is_array($result) && isset($result['success']) && !$result['success']) {
            $waSent = false;
            $msg = "WhatsApp delivery failed. " . ($result['error'] ?? 'Check API token.');
        }

        jsonResponse([
            'success' => $waSent,
            'message' => $msg,
            'phone' => $formattedDisplay,
            'wa_sent' => $waSent
        ]);

    } elseif ($action === 'save_fcm_token' || $action === 'fcm_token') {
        if (empty($_SESSION['user'])) {
            jsonResponse(['success' => false, 'message' => 'Login required'], 401);
        }
        $email    = trim($b['email'] ?? $b['user_email'] ?? $_SESSION['user']['email'] ?? '');
        $mobile   = trim($b['mobile'] ?? $b['user_mobile'] ?? $_SESSION['user']['mobile'] ?? '');
        $fcmToken = trim($b['fcm_token'] ?? $b['token'] ?? '');
        if (!$fcmToken) jsonResponse(['success' => false, 'message' => 'No token provided'], 200);

        $conn = db();
        $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online, last_active_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, last_active_at = NOW(), updated_at = NOW()");
        if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcmToken, $email, $mobile); $stmtFcm->execute(); }
        if (!empty($mobile)) {
            $clean10 = substr(preg_replace('/\D/', '', $mobile), -10);
            $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '" . $conn->real_escape_string($clean10) . "'");
        }
        if (!empty($email)) {
            $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE LOWER(email) = '" . $conn->real_escape_string(strtolower($email)) . "'");
        }

        if (isset($_SESSION['user'])) $_SESSION['user']['fcm_token'] = $fcmToken;

        jsonResponse(['success' => true, 'message' => 'FCM token saved']);
    }

    if ($action === 'update_profile') {
        if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        $newName  = trim($b['name'] ?? '');
        $newEmail = trim($b['email'] ?? '');
        $userId   = intval($_SESSION['user']['id'] ?? 0);
        $conn = db();

        if ($newName) {
            $stmt = $conn->prepare("UPDATE app_users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $newName, $userId);
            $stmt->execute();
            $_SESSION['user']['name'] = $newName;
        }
        if ($newEmail) {
            $stmt2 = $conn->prepare("UPDATE app_users SET email = ? WHERE id = ?");
            $stmt2->bind_param('si', $newEmail, $userId);
            $stmt2->execute();
            $_SESSION['user']['email'] = $newEmail;
        }
        jsonResponse(['success' => true, 'message' => 'Profile updated', 'user' => $_SESSION['user']]);
    }
}

// === NON-JSON REDIRECT ===
if (!isJsonRequest()) {
    $redir = trim($_GET['redirect'] ?? '');
    $target = './index.php?login=1';
    if ($redir) $target .= '&redirect=' . urlencode($redir);
    header('Location: ' . $target);
    exit;
}

jsonResponse(['error' => 'Invalid request method or action'], 400);
<?php
/**
 * PAVANCAB GOA TAXI - Authentication & WhatsApp OTP Module
 * Path: app/auth.php
 *
 * Handles: User login, Driver login, Admin login, OTP, Password auth
 * Driver endpoints use action=driver_X prefix (merged from api_driver.php)
 * v4 - merged cron_tick endpoint (every-minute cron)
 */
// v4 - cron_tick at top, OPcache bust

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$b      = getBody();

// === CRON TICK (every minute) ===
if ($action === 'cron_tick' && ($_GET['key'] ?? '') === 'pavancab_cron_2026') {
    date_default_timezone_set('Asia/Kolkata');
    $conn = db();
    $nowTs = time();
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $log = [];
    $startMs = microtime(true);

    // 1. OTP cleanup
    try {
        $del = $conn->query("DELETE FROM app_otp_store WHERE expires_at < NOW()");
        if ($del && $del->affected_rows > 0) $log[] = "OTP: -{$del->affected_rows}";
    } catch (Exception $e) {}

    // 2. Ride-soon reminder (60 min)
    try {
        $rides = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.driver_id, b.driver_phone, b.total_fare, b.reminder_sent, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as dphone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE UPPER(b.status) IN ('CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent = 0) AND b.pickup_date IN (?, ?)", 'ss', [$today, $tomorrow]);
        foreach ($rides as $ride) {
            $diffMin = (strtotime("{$ride['pickup_date']} {$ride['pickup_time']}") - $nowTs) / 60;
            $ageMin = ($nowTs - strtotime($ride['created_at'] ?? 'now')) / 60;
            if ($ageMin < 30) continue;
            if ($diffMin > 0 && $diffMin <= 60) {
                $dP = $ride['dphone'] ?? '';
                $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                if ($dP) @sendMetaWhatsApp($dP, "Ã¢ÂÂ° *RIDE REMINDER*\n\nRef: #{$ride['booking_ref']}\nPassenger: {$ride['customer_name']}\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: Ã¢â€šÂ¹{$ride['total_fare']}\n\nRide in " . intval($diffMin) . " min! Head to pickup now.");
                @sendFCMPushToDriver($ride['driver_id'] ?: $dP, "Ride in " . intval($diffMin) . " min!", "Pickup {$ride['customer_name']}", ['type' => 'RIDE_REMINDER', 'booking_id' => strval($ride['id'])]);
                dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                $log[] = "Reminder #{$ride['booking_ref']}";
            }
        }
    } catch (Exception $e) {}

    // 3. Unassigned urgent (90 min)
    try {
        $ua = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.cab_type, b.total_fare, b.reminder_sent FROM app_bookings b WHERE UPPER(b.status) IN ('PENDING','CONFIRMED') AND (b.driver_id IS NULL OR b.driver_id = 0) AND b.pickup_date IN (?, ?)", 'ss', [$today, $tomorrow]);
        foreach ($ua as $ride) {
            $diffMin = (strtotime("{$ride['pickup_date']} {$ride['pickup_time']}") - $nowTs) / 60;
            if ($diffMin > 0 && $diffMin <= 90 && ($ride['reminder_sent'] ?? 0) < 3) {
                $urg = $diffMin <= 30 ? "Ã°Å¸Å¡Â¨ URGENT" : "Ã¢Å¡Â Ã¯Â¸Â NEEDS DRIVER";
                $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                @sendMetaWhatsApp('+919000000000', "$urg Ã¢â‚¬â€ Ride #{$ride['booking_ref']}\n\nPassenger: {$ride['customer_name']} ({$ride['customer_phone']})\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: Ã¢â€šÂ¹{$ride['total_fare']}\n\nPickup in " . intval($diffMin) . " min! Assign driver NOW.");
                @sendFCMPushToAdmins("$urg Ride #{$ride['booking_ref']}", "Pickup in " . intval($diffMin) . " min!", ['type' => 'UNASSIGNED_URGENT']);
                dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                $log[] = "Unassigned #{$ride['booking_ref']}";
            }
        }
    } catch (Exception $e) {}

    // 4. Night ride alert (10PM)
    try {
        $cHour = intval(date('G'));
        if ($cHour >= 22 || $cHour < 1) {
            $nr = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.total_fare, b.reminder_sent FROM app_bookings b WHERE UPPER(b.status) IN ('PENDING','CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent < 5) AND b.pickup_date IN (?, ?)", 'ss', [$today, $tomorrow]);
            foreach ($nr as $ride) {
                $pH = intval(date('G', strtotime("{$ride['pickup_date']} {$ride['pickup_time']}")));
                if ($pH >= 22 || $pH < 6) {
                    $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                    @sendMetaWhatsApp('+919000000000', "Ã°Å¸Å’â„¢ *NIGHT RIDE*\nRef: #{$ride['booking_ref']}\n{$ride['customer_name']} ({$ride['customer_phone']})\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: Ã¢â€šÂ¹{$ride['total_fare']}\n1.5x multiplier.");
                    dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                    $log[] = "Night #{$ride['booking_ref']}";
                }
            }
        }
    } catch (Exception $e) {}

    // 5. Subscription expiry reminders
    try {
        $subs = dbRows("SELECT ds.*, d.phone as dphone, d.name as dname FROM driver_subscriptions ds JOIN app_drivers d ON ds.driver_id = d.id WHERE ds.status = 'active' AND ds.end_date >= ? AND ds.end_date <= DATE_ADD(?, INTERVAL 7 DAY)", 'ss', [$today, $today]);
        foreach ($subs as $sub) {
            $daysLeft = max(0, (strtotime($sub['end_date']) - strtotime($today)) / 86400);
            $dP = $sub['dphone'];
            $dN = $sub['dname'] ?: 'Driver';
            $lr = $sub['last_reminder_sent'] ?? '';
            if ($daysLeft <= 0 && $lr !== $today) {
                @sendMetaWhatsApp($dP, "Ã°Å¸â€â€ž *SUBSCRIPTION EXPIRED*\nHi $dN, your PAVANCAB subscription expired today.\nPay Ã¢â€šÂ¹200/ride commission or renew Ã¢â€šÂ¹1000/month!\nOpen Driver App to subscribe.");
                dbExec("UPDATE driver_subscriptions SET status = 'expired', last_reminder_sent = ? WHERE id = ?", 'si', [$today, $sub['id']]);
                dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [$sub['driver_id']]);
                $log[] = "Sub expired #{$sub['driver_id']}";
            } elseif ($daysLeft <= 1 && $lr !== '1d') {
                @sendMetaWhatsApp($dP, "Ã¢ÂÂ° *SUBSCRIPTION EXPIRES TOMORROW*\nHi $dN, your subscription expires {$sub['end_date']}.\nRenew Ã¢â€šÂ¹1000/month in Driver App!");
                dbExec("UPDATE driver_subscriptions SET last_reminder_sent = '1d' WHERE id = ?", 'i', [$sub['id']]);
                $log[] = "Sub 1d #{$sub['driver_id']}";
            } elseif ($daysLeft <= 3 && $lr !== '3d') {
                @sendMetaWhatsApp($dP, "Ã°Å¸â€œÂ¢ *SUBSCRIPTION EXPIRES IN 3 DAYS*\nHi $dN, expires {$sub['end_date']}. Renew Ã¢â€šÂ¹1000/month!");
                dbExec("UPDATE driver_subscriptions SET last_reminder_sent = '3d' WHERE id = ?", 'i', [$sub['id']]);
                $log[] = "Sub 3d #{$sub['driver_id']}";
            }
        }
    } catch (Exception $e) {}

    // 6. Pending commission reminders (every 6h)
    try {
        if (intval(date('G')) % 6 === 0) {
            $up = dbRows("SELECT dp.driver_id, d.phone as dphone, d.name as dname, COUNT(*) as cnt, SUM(dp.amount) as total FROM driver_payments dp JOIN app_drivers d ON dp.driver_id = d.id WHERE dp.status = 'pending' GROUP BY dp.driver_id HAVING cnt > 0");
            foreach ($up as $row) {
                @sendMetaWhatsApp($row['dphone'], "Ã°Å¸â€™Â° *PENDING COMMISSION*\nHi {$row['dname']}, {$row['cnt']} unpaid ride(s) = Ã¢â€šÂ¹{$row['total']}.\nPay in Driver App or subscribe Ã¢â€šÂ¹1000/month!");
                $log[] = "Comm reminder #{$row['driver_id']}";
            }
        }
    } catch (Exception $e) {}

    // 7. Offline cleanup (>5min no activity)
    try {
        $off = $conn->query("UPDATE app_drivers SET is_online = 0 WHERE is_online = 1 AND last_active_at IS NOT NULL AND last_active_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        if ($off && $off->affected_rows > 0) $log[] = "Offline: -{$off->affected_rows}";
    } catch (Exception $e) {}

    // 8. Stale FCM cleanup (90 days)
    try {
        $fc = $conn->query("DELETE FROM app_fcm_tokens WHERE last_active_at IS NOT NULL AND last_active_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        if ($fc && $fc->affected_rows > 0) $log[] = "FCM: -{$fc->affected_rows}";
    } catch (Exception $e) {}

    // 9. Daily summary (9AM)
    try {
        if (date('H:i') === '09:00') {
            $yd = date('Y-m-d', strtotime('-1 day'));
            $bk = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as rev FROM app_bookings WHERE DATE(created_at) = ?", 's', [$yd]);
            $comp = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status = 'COMPLETED' AND pickup_date = ?", 's', [$yd]);
            $canc = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status LIKE '%CANCELLED%' AND DATE(created_at) = ?", 's', [$yd]);
            $pend = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status IN ('PENDING','CONFIRMED') AND pickup_date = ?", 's', [$today]);
            $on = dbRows("SELECT COUNT(*) as cnt FROM app_drivers WHERE is_online = 1");
            $new = dbRows("SELECT COUNT(*) as cnt FROM app_users WHERE DATE(created_at) = ?", 's', [$yd]);
            @sendMetaWhatsApp('+919000000000', "Ã°Å¸â€œÅ  *DAILY SUMMARY Ã¢â‚¬â€ $yd*\n\nÃ°Å¸Å¡â€¢ Bookings: {$bk[0]['cnt']}\nÃ¢Å“â€¦ Completed: {$comp[0]['cnt']}\nÃ¢ÂÅ’ Cancelled: {$canc[0]['cnt']}\nÃ°Å¸â€™Â° Revenue: Ã¢â€šÂ¹{$bk[0]['rev']}\nÃ°Å¸â€œâ€¹ Today Pending: {$pend[0]['cnt']}\nÃ°Å¸Å¡â€” Online Drivers: {$on[0]['cnt']}\nÃ°Å¸â€˜Â¤ New Users: {$new[0]['cnt']}");
            $log[] = "Daily summary";
        }
    } catch (Exception $e) {}

    // 10. Expired subscription cleanup
    try {
        $exp = $conn->query("UPDATE driver_subscriptions SET status = 'expired' WHERE status = 'active' AND end_date < CURDATE()");
        if ($exp && $exp->affected_rows > 0) {
            $er = $conn->query("SELECT driver_id FROM driver_subscriptions WHERE status = 'expired' AND end_date < CURDATE()");
            if ($er) while ($r = $er->fetch_assoc()) dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [intval($r['driver_id'])]);
            $log[] = "Expired: -{$exp->affected_rows}";
        }
    } catch (Exception $e) {}

    $elapsed = round((microtime(true) - $startMs) * 1000);
    jsonResponse(['status' => 'ok', 'time' => date('Y-m-d H:i:s'), 'ms' => $elapsed, 'tasks' => $log]);
}

// === USER LOGOUT ===
if ($action === 'logout' || isset($_GET['logout']) || isset($_POST['logout'])) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['isLoggedIn'])) {
        if (isJsonRequest()) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        header('Location: ./index.php');
        exit;
    }
    $fcmToken  = trim($b['fcm_token'] ?? $_GET['fcm_token'] ?? $_POST['fcm_token'] ?? '');
    $userId    = intval($_SESSION['user']['id'] ?? 0);
    $userPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? '');

    try {
        $conn = db();
        if ($conn) {
            if ($fcmToken) {
                $fSafe = $conn->real_escape_string($fcmToken);
                $conn->query("DELETE FROM app_fcm_tokens WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_users SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_drivers SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_team_members SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
            }
            if ($userPhone) {
                $clean10 = substr($userPhone, -10);
                $conn->query("DELETE FROM app_fcm_tokens WHERE RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
                $conn->query("UPDATE app_users SET is_online = 0, fcm_token = NULL WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
            }
        }
    } catch (Exception $e) {}

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    if (isset($_COOKIE[session_name()])) @setcookie(session_name(), '', time() - 86400, '/');
    if (isset($_COOKIE['PHPSESSID'])) @setcookie('PHPSESSID', '', time() - 86400, '/');

    if (isJsonRequest()) jsonResponse(['success' => true, 'message' => 'Logged out successfully and FCM push token cleared']);
    header('Location: ./index.php');
    exit;
}

// === GET SESSION ===
if ($method === 'GET' && ($action === 'me' || isset($_GET['me']))) {
    if (isset($_SESSION['user'])) {
        jsonResponse(['success' => true, 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        jsonResponse(['success' => false, 'isLoggedIn' => false, 'user' => null]);
    }
}

// ============================================================
// DRIVER API ENDPOINTS (moved before POST block to avoid
// the isset($b['phone']) catch-all in send_otp handler)
// ============================================================

if (strpos($action, 'driver_') === 0) {
    $da = substr($action, 7);

    // Auto-migrate driver table columns
    try {
        $conn = db();
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL");
        @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL");
    } catch (Exception $e) {}

    // ===== DRIVER: VERIFY OTP =====
    if ($da === 'verify-otp' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        if (!$phone || !$otp) jsonResponse(['success' => false, 'error' => 'Phone and OTP required'], 400);

        $clean10 = substr($phone, -10);
        $cleanOtp = trim($otp);
        $conn = db();

        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $phone, $clean10, $cleanOtp);
        $stmt->execute();
        $r = $stmt->get_result();
        $otpRow = $r ? $r->fetch_assoc() : null;

        if (!$otpRow) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP. Please try again.'], 401);

        dbExec("DELETE FROM app_otp_store WHERE id = ?", 'i', [$otpRow['id']]);

        $stmt = $conn->prepare("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('s', $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $driver = $r ? $r->fetch_assoc() : null;

        if ($driver) {
            $_SESSION['driver'] = [
                'id' => intval($driver['id']),
                'name' => $driver['name'],
                'phone' => $phone,
                'car_model' => $driver['car_model'] ?? '',
                'plate_number' => $driver['plate_number'] ?? '',
                'isLoggedIn' => true
            ];
            $approved = intval($driver['is_approved'] ?? 0);
            jsonResponse([
                'success' => true,
                'approved' => $approved == 1,
                'existing_driver' => true,
                'driver' => [
                    'id' => intval($driver['id']),
                    'name' => $driver['name'],
                    'phone' => $phone,
                    'car_model' => $driver['car_model'] ?? '',
                    'plate_number' => $driver['plate_number'] ?? '',
                    'rating' => floatval($driver['rating'] ?? 5.0),
                    'is_approved' => $approved
                ]
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO app_drivers (name, phone, car_model, plate_number, is_approved, status) VALUES (?, ?, '', '', 0, 'pending')");
            $stmt->bind_param('ss', $phone, $phone);
            $stmt->execute();
            $newId = $conn->insert_id;

            $_SESSION['driver'] = [
                'id' => $newId,
                'name' => '',
                'phone' => $phone,
                'car_model' => '',
                'plate_number' => '',
                'isLoggedIn' => true
            ];

            try { sendFCMPushToAdmins("New Driver Registration", "Phone: $phone has registered as a driver. Please approve from Admin app.", ['type' => 'NEW_DRIVER', 'driver_id' => strval($newId)]); } catch (Exception $e) {}
            try { sendMetaWhatsApp("+919000000000", "NEW DRIVER REGISTRATION\n\nPhone: $phone\nStatus: PENDING APPROVAL\n\nPlease approve from Admin app."); } catch (Exception $e) {}

            jsonResponse([
                'success' => true,
                'approved' => false,
                'existing_driver' => false,
                'driver' => [
                    'id' => $newId,
                    'name' => '',
                    'phone' => $phone,
                    'car_model' => '',
                    'plate_number' => '',
                    'rating' => 5.0,
                    'is_approved' => 0
                ]
            ]);
        }
    }

    // ===== DRIVER: CHECK SESSION =====
    if ($da === 'check-session') {
        if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        $approved = !empty($rows) ? intval($rows[0]['is_approved'] ?? 0) : 0;
        jsonResponse(['success' => true, 'approved' => $approved == 1, 'driver' => $_SESSION['driver']]);
    }

    // ===== DRIVER: CHECK APPROVAL =====
    if ($da === 'check-approval') {
        if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT is_approved, name, car_model, plate_number FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
        jsonResponse(['success' => true, 'approved' => intval($rows[0]['is_approved'] ?? 0) == 1, 'driver' => $rows[0]]);
    }

    // ===== DRIVER: CHECK PHONE (has password?) =====
    if ($da === 'check-phone' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        if (!$phone || strlen($phone) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone required'], 400);
        $clean10 = substr($phone, -10);
        $rows = dbRows("SELECT id, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
        jsonResponse(['success' => true, 'exists' => !empty($rows), 'has_password' => !empty($rows[0]['password_hash'] ?? null)]);
    }

    // ===== DRIVER: SET PASSWORD =====
    if ($da === 'set-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $clean10 = substr($phone, -10);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
        jsonResponse(['success' => true, 'message' => 'Password set successfully']);
    }

    // ===== DRIVER: LOGIN WITH PASSWORD =====
    if ($da === 'login-with-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        $clean10 = substr($phone, -10);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
        if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Driver account not found. Register with OTP first.'], 404);
        $driver = $rows[0];
        if (empty($driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
        if (!password_verify($password, $driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
        $approved = intval($driver['is_approved'] ?? 0);
        $_SESSION['driver'] = [
            'id' => intval($driver['id']),
            'name' => $driver['name'],
            'phone' => $phone,
            'car_model' => $driver['car_model'] ?? '',
            'plate_number' => $driver['plate_number'] ?? '',
            'isLoggedIn' => true
        ];
        jsonResponse([
            'success' => true,
            'approved' => $approved == 1,
            'driver' => [
                'id' => intval($driver['id']),
                'name' => $driver['name'],
                'phone' => $phone,
                'car_model' => $driver['car_model'] ?? '',
                'plate_number' => $driver['plate_number'] ?? '',
                'rating' => floatval($driver['rating'] ?? 5.0),
                'is_approved' => $approved
            ]
        ]);
    }

    // ===== DRIVER: RESET PASSWORD =====
    if ($da === 'reset-password' && $method === 'POST') {
        $phone = cleanPhoneDigits($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $clean10 = substr($phone, -10);
        $conn = db();
        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $phone, $clean10, $otp);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
        dbExec("DELETE FROM app_otp_store WHERE phone = ? OR RIGHT(phone, 10) = ?", 'ss', [$phone, $clean10]);
        jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    }

    // ===== DRIVER: LOGOUT =====
    if ($da === 'logout' && $method === 'POST') {
        if (!empty($_SESSION['driver']['id'])) {
            $driverId = intval($_SESSION['driver']['id']);
            try { dbExec("UPDATE app_drivers SET is_online = 0 WHERE id = ?", 'i', [$driverId]); } catch (Exception $e) {}
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
        jsonResponse(['success' => true]);
    }

    // ===== Require Driver Auth (for authenticated endpoints) =====
    if (in_array($da, ['my-bookings', 'booking-detail', 'respond', 'trip-status', 'earnings', 'profile', 'toggle-online'])) {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver authentication required. Please login.'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
    }

    // ===== DRIVER: MY BOOKINGS =====
    if ($da === 'my-bookings') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $cleanPhone10 = '';
        if ($driverPhone) {
            $cleanPhone10 = substr(preg_replace('/\D/', '', $driverPhone), -10);
        }
        if ($cleanPhone10) {
            $rows = dbRows(
                "SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? OR b.driver_id IN (SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?) ORDER BY b.pickup_date DESC, b.pickup_time DESC LIMIT 100",
                'is', [$driverId, $cleanPhone10]
            );
        } else {
            $rows = dbRows(
                "SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? ORDER BY b.pickup_date DESC, b.pickup_time DESC LIMIT 100",
                'i', [$driverId]
            );
        }
        jsonResponse(['bookings' => $rows]);
    }

    // ===== DRIVER: BOOKING DETAIL =====
    if ($da === 'booking-detail') {
        $driverId = intval($_SESSION['driver']['id']);
        $bookingId = intval($b['id'] ?? $_GET['id'] ?? 0);
        if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
        $rows = dbRows("SELECT b.* FROM app_bookings b WHERE b.id = ? AND (b.driver_id = ? OR ? = 0) LIMIT 1", 'iii', [$bookingId, $driverId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        jsonResponse(['booking' => $rows[0]]);
    }

    // ===== DRIVER: RESPOND TO BOOKING =====
    if ($da === 'respond' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverName = $_SESSION['driver']['name'] ?? '';
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $bookingId = intval($b['booking_id'] ?? 0);
        $decision = strtoupper(trim($b['decision'] ?? ''));

        if (!$bookingId || !in_array($decision, ['ACCEPT', 'REJECT'])) {
            jsonResponse(['error' => 'booking_id and decision (ACCEPT/REJECT) required'], 400);
        }

        $rows = dbRows("SELECT id, status, driver_id, customer_name, customer_phone, booking_ref FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

        $booking = $rows[0];
        if ($booking['status'] !== 'ASSIGNED' && $booking['status'] !== 'ACCEPTED') {
            jsonResponse(['error' => 'Booking is in ' . $booking['status'] . ' status, cannot respond'], 400);
        }

        if ($decision === 'ACCEPT') {
            $now = date('Y-m-d');
            $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
            if (empty($sub)) {
                $pending = dbRows("SELECT COUNT(*) as cnt FROM driver_payments WHERE driver_id = ? AND status = 'pending' LIMIT 1", 'i', [$driverId]);
                $pendingCount = intval($pending[0]['cnt'] ?? 0);
                if ($pendingCount > 0) {
                    jsonResponse(['success' => false, 'error' => 'payment_required', 'message' => "You have $pendingCount unpaid ride commission(s). Pay Ã¢â€šÂ¹{$pendingCount}00 or subscribe for Ã¢â€šÂ¹1000/month to accept rides.", 'pending_count' => $pendingCount], 402);
                }
            }
            dbExec("UPDATE app_bookings SET status = 'ACCEPTED', driver_decision = 'ACCEPTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
            try { sendFCMPushToAdmins("Driver Accepted (#{$booking['booking_ref']})", "$driverName ($driverPhone) accepted ride #{$booking['booking_ref']} for {$booking['customer_name']}", ['type' => 'BOOKING_CONFIRMED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
        } else {
            dbExec("UPDATE app_bookings SET status = 'PENDING', driver_id = 0, driver_name = '', driver_phone = '', driver_decision = 'REJECTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
            try { sendFCMPushToAdmins("Driver Rejected (#{$booking['booking_ref']})", "$driverName REJECTED ride #{$booking['booking_ref']}. Please re-assign!", ['type' => 'DRIVER_REJECTED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'decision' => $decision]);
    }

    // ===== DRIVER: TRIP STATUS UPDATE =====
    if ($da === 'trip-status' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $bookingId = intval($b['booking_id'] ?? 0);
        $status = strtoupper(trim($b['status'] ?? ''));

        if (!$bookingId || !$status) jsonResponse(['error' => 'booking_id and status required'], 400);

        $allowed = ['IN_TRIP', 'IN_TRANSIT', 'COMPLETED', 'STARTED'];
        if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);

        $rows = dbRows("SELECT id, status, driver_id, booking_ref, customer_name, customer_phone, pickup_location, drop_location, total_fare FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

        $mapStatus = $status;
        if ($status === 'IN_TRIP') $mapStatus = 'IN_TRANSIT';
        if ($status === 'STARTED') $mapStatus = 'IN_TRANSIT';

        dbExec("UPDATE app_bookings SET status = ?, updated_at = NOW() WHERE id = ?", 'si', [$mapStatus, $bookingId]);

        if ($mapStatus === 'IN_TRANSIT') {
            $canStart = true;
            if ($booking['status'] === 'ASSIGNED' || $booking['status'] === 'ACCEPTED') {
                $now = date('Y-m-d');
                $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
                if (empty($sub)) {
                    $pending = dbRows("SELECT COUNT(*) as cnt FROM driver_payments WHERE driver_id = ? AND status = 'pending' LIMIT 1", 'i', [$driverId]);
                    $pendingCount = intval($pending[0]['cnt'] ?? 0);
                    if ($pendingCount > 0) {
                        jsonResponse(['success' => false, 'error' => 'payment_required', 'message' => "You have $pendingCount unpaid commission(s). Pay Ã¢â€šÂ¹" . ($pendingCount * 200) . " or subscribe to accept rides.", 'pending_count' => $pendingCount], 402);
                    }
                }
            }
            dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
            try { broadcastRideLifecycleFCM('RIDE_STARTED', $bookingId); } catch (Exception $e) {}
        }
        if ($mapStatus === 'COMPLETED') {
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
            $now = date('Y-m-d');
            $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
            if (empty($sub)) {
                $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
                $commissionAmount = floatval($settings[0]['setting_value'] ?? 200);
                dbExec("INSERT INTO driver_payments (driver_id, type, booking_id, amount, status) VALUES (?, 'commission', ?, ?, 'pending')", 'iid', [$driverId, $bookingId, $commissionAmount]);
                dbExec("UPDATE app_bookings SET commission_status = 'pending' WHERE id = ?", 'i', [$bookingId]);
                try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "RIDE COMPLETED!\n\nRef: #{$rows[0]['booking_ref']}\nCommission: Ã¢â€šÂ¹$commissionAmount\n\nPay Ã¢â€šÂ¹$commissionAmount to accept your next ride, or subscribe for Ã¢â€šÂ¹1000/month for unlimited rides!\n\nPay now in the Driver App."); } catch (Exception $e) {}
            } else {
                dbExec("UPDATE app_bookings SET commission_status = 'waived' WHERE id = ?", 'i', [$bookingId]);
            }
            try { broadcastRideLifecycleFCM('RIDE_COMPLETED', $bookingId); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'status' => $mapStatus]);
    }

    // ===== DRIVER: EARNINGS =====
    if ($da === 'earnings') {
        $driverId = intval($_SESSION['driver']['id']);
        $driverPhone = $_SESSION['driver']['phone'] ?? '';
        $cleanPhone10 = substr(preg_replace('/\D/', '', $driverPhone), -10);
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');

        if ($cleanPhone10) {
            $driverFilter = "driver_id = ? OR driver_id IN (SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?)";
            $todayRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE ($driverFilter) AND status = 'COMPLETED' AND pickup_date = ?", 'iss', [$driverId, $cleanPhone10, $today]);
            $weekRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE ($driverFilter) AND status = 'COMPLETED' AND pickup_date >= ?", 'iss', [$driverId, $cleanPhone10, $weekStart]);
            $monthRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE ($driverFilter) AND status = 'COMPLETED' AND pickup_date >= ?", 'iss', [$driverId, $cleanPhone10, $monthStart]);
        } else {
            $todayRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date = ?", 'is', [$driverId, $today]);
            $weekRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= ?", 'is', [$driverId, $weekStart]);
            $monthRow = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= ?", 'is', [$driverId, $monthStart]);
        }
        $commRow = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'commission_per_ride' LIMIT 1");
        $commission = !empty($commRow) ? intval($commRow[0]['setting_value']) : 300;

        jsonResponse([
            'today_rides' => intval($todayRow[0]['cnt'] ?? 0), 'today_earnings' => floatval($todayRow[0]['total'] ?? 0),
            'week_rides' => intval($weekRow[0]['cnt'] ?? 0), 'week_earnings' => floatval($weekRow[0]['total'] ?? 0),
            'month_rides' => intval($monthRow[0]['cnt'] ?? 0), 'month_earnings' => floatval($monthRow[0]['total'] ?? 0),
            'commission_per_ride' => $commission
        ]);
    }

    // ===== DRIVER: PROFILE =====
    if ($da === 'profile') {
        $driverId = intval($_SESSION['driver']['id']);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, status, is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
        jsonResponse(['driver' => $rows[0]]);
    }

    // ===== DRIVER: TOGGLE ONLINE =====
    if ($da === 'toggle-online' && $method === 'POST') {
        $driverId = intval($_SESSION['driver']['id']);
        $row = dbRows("SELECT is_online FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        $current = intval($row[0]['is_online'] ?? 0);
        $newVal = $current == 1 ? 0 : 1;
        dbExec("UPDATE app_drivers SET is_online = ? WHERE id = ?", 'ii', [$newVal, $driverId]);
        jsonResponse(['success' => true, 'is_online' => $newVal]);
    }

    // ===== DRIVER: SAVE FCM TOKEN =====
    if ($da === 'save-fcm-token' && $method === 'POST') {
        $fcmToken = trim($b['fcm_token'] ?? '');
        $phone = cleanPhoneDigits($b['phone'] ?? '');

        if ($fcmToken) {
            if ($phone) {
                $clean10 = substr($phone, -10);
                dbExec("UPDATE app_drivers SET fcm_token = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$fcmToken, $clean10]);
            }
            $existing = dbRows("SELECT id FROM app_fcm_tokens WHERE fcm_token = ? LIMIT 1", 's', [$fcmToken]);
            if (empty($existing)) {
                dbExec("INSERT INTO app_fcm_tokens (fcm_token, user_mobile) VALUES (?, ?)", 'ss', [$fcmToken, $phone ?: null]);
            }
        }
        jsonResponse(['success' => true]);
    }

    // ===== ADMIN: APPROVE DRIVER =====
    if ($da === 'approve-driver' && $method === 'POST') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);

        $driverId = intval($b['driver_id'] ?? 0);
        $approved = intval($b['approved'] ?? 1);
        if (!$driverId) jsonResponse(['error' => 'driver_id required'], 400);

        dbExec("UPDATE app_drivers SET is_approved = ? WHERE id = ?", 'ii', [$approved, $driverId]);

        $rows = dbRows("SELECT phone, name FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (!empty($rows)) {
            $phone = $rows[0]['phone'];
            if ($approved) {
                try {
                    sendFCMPushToDriver($driverId, "Account Approved!", "Your driver account has been approved. You can now start accepting rides.", ['type' => 'APPROVED']);
                    sendMetaWhatsApp($phone, "ACCOUNT APPROVED!\n\nYour PAVANCAB Driver account has been approved!\n\nOpen the Driver App to start accepting rides. Drive safe!");
                } catch (Exception $e) {}
            } else {
                try {
                    sendFCMPushToDriver($driverId, "Account Rejected", "Your driver account has been rejected. Contact support for details.", ['type' => 'REJECTED']);
                } catch (Exception $e) {}
            }
        }

        jsonResponse(['success' => true, 'approved' => $approved == 1]);
    }

    // ===== ADMIN: LIST PENDING DRIVERS =====
    if ($da === 'pending-drivers') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, created_at FROM app_drivers WHERE is_approved = 0 ORDER BY id DESC");
        jsonResponse(['drivers' => $rows]);
    }

    // ===== ADMIN: LIST ALL DRIVERS =====
    if ($da === 'all-drivers') {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
        $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, rating, status FROM app_drivers ORDER BY id DESC");
        jsonResponse(['drivers' => $rows]);
    }

    // ===== DRIVER: SUBSCRIPTION STATUS =====
    if ($da === 'subscription-status') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $now = date('Y-m-d');
        $active = dbRows("SELECT id, start_date, end_date, amount FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? ORDER BY id DESC LIMIT 1", 'is', [$driverId, $now]);
        $hasActive = !empty($active);
        $endDate = $hasActive ? $active[0]['end_date'] : null;
        $daysLeft = $hasActive ? max(0, (strtotime($endDate) - strtotime($now)) / 86400) : 0;

        $pendingPayments = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM driver_payments WHERE driver_id = ? AND status = 'pending'", 'i', [$driverId]);
        $pendingCount = intval($pendingPayments[0]['cnt'] ?? 0);
        $pendingTotal = floatval($pendingPayments[0]['total'] ?? 0);

        $settings = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount', 'driver_commission_per_ride')");
        $config = [];
        foreach ($settings as $s) $config[$s['setting_key']] = $s['setting_value'];
        $subAmount = floatval($config['driver_subscription_amount'] ?? 1000);
        $rideCommission = floatval($config['driver_commission_per_ride'] ?? 200);

        jsonResponse([
            'is_subscribed' => $hasActive,
            'end_date' => $endDate,
            'days_left' => intval($daysLeft),
            'subscription_amount' => $subAmount,
            'commission_per_ride' => $rideCommission,
            'pending_payments_count' => $pendingCount,
            'pending_payments_total' => $pendingTotal,
            'can_accept' => $hasActive || $pendingCount == 0
        ]);
    }

    // ===== DRIVER: CREATE RAZORPAY ORDER =====
    if ($da === 'create-order' && $method === 'POST') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $paymentType = strtolower(trim($b['type'] ?? 'subscription'));
        $bookingId = intval($b['booking_id'] ?? 0);

        if ($paymentType === 'subscription') {
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_subscription_amount' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 1000);
            $receipt = "sub_driver{$driverId}_" . time();
        } else {
            if (!$bookingId) jsonResponse(['error' => 'booking_id required for ride commission'], 400);
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 200);
            $receipt = "comm_bk{$bookingId}_driver{$driverId}_" . time();
        }

        $keySecret = razorpayKeys();
        $keyId = $keySecret[0];
        $secret = $keySecret[1] ?? '';

        $amountPaise = intval($amount * 100);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => "$keyId:$secret",
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => ['driver_id' => strval($driverId), 'type' => $paymentType, 'booking_id' => strval($bookingId)]
            ])
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $order = json_decode($response, true);

        if ($httpCode === 200 && !empty($order['id'])) {
            jsonResponse([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $amountPaise,
                'amount_display' => $amount,
                'currency' => 'INR',
                'key_id' => $keyId,
                'type' => $paymentType
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to create order', 'details' => $order['error']['description'] ?? 'Unknown error'], 500);
        }
    }

    // ===== DRIVER: VERIFY PAYMENT =====
    if ($da === 'verify-payment' && $method === 'POST') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $razorpayOrderId = trim($b['razorpay_order_id'] ?? '');
        $razorpayPaymentId = trim($b['razorpay_payment_id'] ?? '');
        $razorpaySignature = trim($b['razorpay_signature'] ?? '');
        $paymentType = strtolower(trim($b['type'] ?? 'subscription'));
        $bookingId = intval($b['booking_id'] ?? 0);

        if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature) {
            jsonResponse(['error' => 'Missing payment details'], 400);
        }

        $secret = razorpayKeys()[1];
        $expectedSig = hash_hmac('sha256', "$razorpayOrderId|$razorpayPaymentId", $secret);
        $isValid = hash_equals($expectedSig, $razorpaySignature);

        if (!$isValid) {
            jsonResponse(['success' => false, 'error' => 'Payment verification failed - signature mismatch'], 400);
        }

        if ($paymentType === 'subscription') {
            $amount = 0;
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_subscription_amount' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 1000);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+1 month'));
            dbExec("INSERT INTO driver_subscriptions (driver_id, start_date, end_date, amount, razorpay_order_id, razorpay_payment_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
                'isssss', [$driverId, $startDate, $endDate, $amount, $razorpayOrderId, $razorpayPaymentId]);
            dbExec("UPDATE app_drivers SET has_active_subscription = 1 WHERE id = ?", 'i', [$driverId]);
            try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "SUBSCRIPTION ACTIVATED!\n\nYour PAVANCAB driver subscription is now active until $endDate.\n\nNo commission on rides. Accept as many rides as you want!\n\nThank you for being a PAVANCAB partner."); } catch (Exception $e) {}
        } else {
            $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
            $amount = floatval($settings[0]['setting_value'] ?? 200);
            dbExec("INSERT INTO driver_payments (driver_id, type, booking_id, amount, razorpay_order_id, razorpay_payment_id, status, paid_at) VALUES (?, 'commission', ?, ?, ?, ?, 'paid', NOW())",
                'iidsss', [$driverId, $bookingId, $amount, $razorpayOrderId, $razorpayPaymentId]);
            if ($bookingId) {
                dbExec("UPDATE app_bookings SET commission_status = 'paid' WHERE id = ?", 'i', [$bookingId]);
            }
            try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "COMMISSION PAID!\n\nÃ¢â€šÂ¹$amount commission paid for ride #$bookingId.\n\nYou can now accept the next ride!"); } catch (Exception $e) {}
        }

        jsonResponse(['success' => true, 'message' => 'Payment verified successfully']);
    }

    // ===== DRIVER: PAYMENT HISTORY =====
    if ($da === 'payment-history') {
        if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
            jsonResponse(['error' => 'Driver auth required'], 401);
        }
        $driverId = intval($_SESSION['driver']['id']);
        $payments = dbRows("SELECT * FROM driver_payments WHERE driver_id = ? ORDER BY created_at DESC LIMIT 50", 'i', [$driverId]);
        $subscriptions = dbRows("SELECT * FROM driver_subscriptions WHERE driver_id = ? ORDER BY created_at DESC LIMIT 10", 'i', [$driverId]);
        jsonResponse(['payments' => $payments, 'subscriptions' => $subscriptions]);
    }

    // If no driver action matched, return error
    jsonResponse(['error' => 'Unknown driver action: ' . $da], 400);
}

if ($method === 'POST') {
    // === VERIFY OTP (USER) ===
    if ($action === 'verify_otp' || !empty($b['otp'])) {
        $phone = trim($b['phone'] ?? $_POST['phone'] ?? $_GET['phone'] ?? '');
        $otp   = trim($b['otp'] ?? $_POST['otp'] ?? $_GET['otp'] ?? '');
        $name  = trim($b['name'] ?? $_POST['name'] ?? $_GET['name'] ?? '');
        $email = trim($b['email'] ?? $_POST['email'] ?? $_GET['email'] ?? '');

        if (!$phone || !$otp) {
            jsonResponse(['success' => false, 'error' => 'Phone number and 6-digit OTP code are required.'], 400);
        }

        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $cleanOtp    = trim($otp);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $conn = db();
        $otpValid = false;

        $stmt = $conn->prepare("SELECT * FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();

        if ($r && $row = $r->fetch_assoc()) {
            if (trim($row['otp']) === $cleanOtp) {
                $otpValid = true;
                $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
                $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
                $stmtDel->execute();
            }
        }

        if (!$otpValid && isset($_SESSION['pending_otp'])) {
            if (($_SESSION['pending_otp']['phone'] === $cleanDigits || $_SESSION['pending_otp']['clean10'] === $clean10) && $_SESSION['pending_otp']['otp'] === $cleanOtp) {
                if ($_SESSION['pending_otp']['expires'] > time()) $otpValid = true;
            }
        }

        if (!$otpValid) {
            if (!isset($_SESSION['otp_attempts'])) $_SESSION['otp_attempts'] = [];
            $attempts = $_SESSION['otp_attempts'][$cleanDigits] ?? ['count' => 0, 'first' => time()];
            if (time() - $attempts['first'] > 600) $attempts = ['count' => 0, 'first' => time()];
            $attempts['count']++;
            $_SESSION['otp_attempts'][$cleanDigits] = $attempts;
            if ($attempts['count'] > 5) jsonResponse(['success' => false, 'error' => 'Too many failed attempts. Please wait 10 minutes and try again.'], 429);
            jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP code. Please request a new OTP.'], 401);
        }
        if (isset($_SESSION['otp_attempts'][$cleanDigits])) unset($_SESSION['otp_attempts'][$cleanDigits]);
        unset($_SESSION['pending_otp']);
        session_regenerate_id(true);

        $role = determineUserRole($cleanDigits, $email);
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');

        $finalName = $name;
        if ($isAdmin) {
            $finalName = 'Niranjan Yamgar (Admin)';
        } elseif ($isTeam) {
            $stmtTm = $conn->prepare("SELECT member_name FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), 10) = ? OR REPLACE(REPLACE(member_phone, '+', ''), ' ', '') = ?) AND is_active = 1 LIMIT 1");
            if ($stmtTm) {
                $stmtTm->bind_param('ss', $clean10, $cleanDigits);
                $stmtTm->execute();
                $rTm = $stmtTm->get_result();
                if ($rTm && $rowTm = $rTm->fetch_assoc()) $finalName = $rowTm['member_name'];
            }
        }
        if (!$finalName) $finalName = 'Goa Traveler';

        $formattedMobile = '+' . $cleanDigits;
        $reqFcm = trim($b['fcm_token'] ?? $_POST['fcm_token'] ?? $_GET['fcm_token'] ?? '');

        $stmtUser = $conn->prepare("SELECT id, name, email, role FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmtUser->bind_param('ss', $formattedMobile, $clean10);
        $stmtUser->execute();
        $rUser = $stmtUser->get_result();
        $isNewUser = true;
        $isBanned = false;

        if ($rUser && $rowU = $rUser->fetch_assoc()) {
            $isNewUser = false;
            $userId = $rowU['id'];
            $stmtBan = $conn->prepare("SELECT IFNULL(is_banned, 0) as banned FROM app_users WHERE id = ? LIMIT 1");
            if ($stmtBan) {
                $stmtBan->bind_param('i', $userId);
                $stmtBan->execute();
                $rBan = $stmtBan->get_result();
                if ($rBan && $rowBan = $rBan->fetch_assoc()) $isBanned = intval($rowBan['banned']) === 1;
            }
            if ($isBanned) jsonResponse(['success' => false, 'error' => 'Your account has been banned by the administrator. Please contact support.'], 403);
            if (!$name && $rowU['name']) $finalName = $rowU['name'];
            $stmtUpd = $conn->prepare("UPDATE app_users SET name = ?, mobile = ?, role = ?, last_active_at = NOW(), is_online = 1 WHERE id = ?");
            $stmtUpd->bind_param('sssi', $finalName, $formattedMobile, $role, $userId);
            $stmtUpd->execute();
        } else {
            $stmtIns = $conn->prepare("INSERT INTO app_users (name, mobile, email, role, last_active_at, is_online) VALUES (?, ?, ?, ?, NOW(), 1)");
            $stmtIns->bind_param('ssss', $finalName, $formattedMobile, $email, $role);
            $stmtIns->execute();
            $userId = $conn->insert_id;
        }

        if (!empty($reqFcm)) {
            $fSafe = $conn->real_escape_string($reqFcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $reqFcm, $email, $cleanDigits); $stmtFcm->execute(); }
            $stmtFcmUpd = $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE id = ?");
            $stmtFcmUpd->bind_param('si', $reqFcm, $userId);
            $stmtFcmUpd->execute();
        }

        $userSession = [
            'id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $email,
            'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true
        ];
        $_SESSION['user'] = $userSession;

        if ($role === 'user') {
            $loginType = $isNewUser ? 'joined & logged in' : 'logged in';
            $notifBody = "$finalName ($formattedMobile) $loginType to PAVANCAB.";
            if ($isNewUser) $notifBody .= " New user!";
            try {
                sendFCMPushToAdmins("Passenger " . ucfirst($loginType) . " (#$finalName)", $notifBody, ['url' => 'https://pavancab.com/app/dashboard/users.php', 'event_type' => 'NEW_BOOKING']);
            } catch (Exception $e) {}
        }

        jsonResponse([
            'success' => true, 'message' => 'Login verified successfully!', 'user' => $userSession,
            'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'has_password' => !empty($rowU['password_hash'] ?? null),
            'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php'
        ]);
    } elseif ($action === 'check_phone') {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone number required'], 400);

        $conn = db();
        $stmt = $conn->prepare("SELECT id, name, password_hash FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('ss', '+' . $cleanDigits, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;

        jsonResponse(['success' => true, 'exists' => !empty($row), 'has_password' => !empty($row['password_hash'] ?? null), 'name' => $row['name'] ?? '']);
    } elseif ($action === 'set_password') {
        $phone = trim($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $formattedMobile = '+' . $cleanDigits;

        $conn = db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?");
        $stmt->bind_param('sss', $hash, $formattedMobile, $clean10);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => 'Password set successfully']);
    } elseif ($action === 'login_with_password') {
        $phone = trim($b['phone'] ?? '');
        $password = trim($b['password'] ?? '');
        $fcm = trim($b['fcm_token'] ?? '');
        if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $formattedMobile = '+' . $cleanDigits;

        $conn = db();
        $stmt = $conn->prepare("SELECT id, name, email, role, password_hash, is_banned FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmt->bind_param('ss', $formattedMobile, $clean10);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;

        if (!$row) jsonResponse(['success' => false, 'error' => 'Account not found. Please register first with OTP.'], 404);
        if (empty($row['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
        if (!password_verify($password, $row['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
        if (intval($row['is_banned'] ?? 0) === 1) jsonResponse(['success' => false, 'error' => 'Account banned'], 403);

        $userId = intval($row['id']);
        $role = $row['role'] ?? 'user';
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');
        $finalName = $row['name'] ?: 'Goa Traveler';

        session_regenerate_id(true);
        $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE id = $userId");

        if (!empty($fcm)) {
            $fSafe = $conn->real_escape_string($fcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_fcm_tokens SET is_online = 0 WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcm, $row['email'] ?? '', $formattedMobile); $stmtFcm->execute(); }
            $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE id = ?")->bind_param('si', $fcm, $userId);
            $conn->query("UPDATE app_users SET fcm_token = '$fSafe', is_online = 1, last_active_at = NOW() WHERE id = $userId");
        }

        $userSession = ['id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $row['email'] ?? '', 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
        $_SESSION['user'] = $userSession;

        jsonResponse(['success' => true, 'message' => 'Login successful!', 'user' => $userSession, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php']);
    } elseif ($action === 'reset_password') {
        $phone = trim($b['phone'] ?? '');
        $otp = trim($b['otp'] ?? '');
        $password = trim($b['password'] ?? '');
        if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
        if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        $conn = db();
        $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('ssss', $cleanDigits, $cleanDigits, $clean10, $otp);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);

        $formattedMobile = '+' . $cleanDigits;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?")->bind_param('sss', $hash, $formattedMobile, $clean10);
        $conn->query("DELETE FROM app_otp_store WHERE phone = '$cleanDigits' OR RIGHT(phone, 10) = '$clean10'");

        jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    } elseif ($action === 'send_otp' || (isset($b['phone']) && strpos($action, 'driver_') !== 0)) {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);

        $appType = trim($b['app_type'] ?? 'passenger');
        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $conn = db();

        $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
        $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmtDel->execute();

        $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->bind_param('ss', $cleanDigits, $otp);
        $stmt->execute();

        $_SESSION['pending_otp'] = [
            'phone'   => $cleanDigits,
            'clean10' => $clean10,
            'otp'     => $otp,
            'expires' => time() + 600
        ];

        $svc  = 'PAVANCAB';
        $appN = 'PAVANCAB';
        $supP = '+919000000000';
        if ($appType === 'driver') {
            $appN = 'PAVANCAB Driver';
            $supP = '+919518541625';
        } elseif ($appType === 'dispatch') {
            $appN = 'PAVANCAB Dispatch';
        }
        $acctLabel = $appType === 'driver' ? 'Driver' : ($appType === 'dispatch' ? 'Admin' : 'Passenger');
        $result = sendOTPWhatsAppTemplate($cleanDigits, $otp, $svc, $acctLabel, $appN, 'PAVANCAB', $supP);

        $waSent = true;
        $formattedDisplay = '+' . $cleanDigits;
        $msg = "WhatsApp OTP sent to $formattedDisplay!";

        if (is_array($result) && isset($result['success']) && !$result['success']) {
            $waSent = false;
            $msg = "WhatsApp delivery failed. " . ($result['error'] ?? 'Check API token.');
        }

        jsonResponse([
            'success' => $waSent,
            'message' => $msg,
            'phone' => $formattedDisplay,
            'wa_sent' => $waSent
        ]);

    } elseif ($action === 'save_fcm_token' || $action === 'fcm_token') {
        if (empty($_SESSION['user'])) {
            jsonResponse(['success' => false, 'message' => 'Login required'], 401);
        }
        $email    = trim($b['email'] ?? $b['user_email'] ?? $_SESSION['user']['email'] ?? '');
        $mobile   = trim($b['mobile'] ?? $b['user_mobile'] ?? $_SESSION['user']['mobile'] ?? '');
        $fcmToken = trim($b['fcm_token'] ?? $b['token'] ?? '');
        if (!$fcmToken) jsonResponse(['success' => false, 'message' => 'No token provided'], 200);

        $conn = db();
        $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online, last_active_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, last_active_at = NOW(), updated_at = NOW()");
        if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcmToken, $email, $mobile); $stmtFcm->execute(); }
        if (!empty($mobile)) {
            $clean10 = substr(preg_replace('/\D/', '', $mobile), -10);
            $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '" . $conn->real_escape_string($clean10) . "'");
        }
        if (!empty($email)) {
            $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE LOWER(email) = '" . $conn->real_escape_string(strtolower($email)) . "'");
        }

        if (isset($_SESSION['user'])) $_SESSION['user']['fcm_token'] = $fcmToken;

        jsonResponse(['success' => true, 'message' => 'FCM token saved']);
    }

    if ($action === 'update_profile') {
        if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        $newName  = trim($b['name'] ?? '');
        $newEmail = trim($b['email'] ?? '');
        $userId   = intval($_SESSION['user']['id'] ?? 0);
        $conn = db();

        if ($newName) {
            $stmt = $conn->prepare("UPDATE app_users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $newName, $userId);
            $stmt->execute();
            $_SESSION['user']['name'] = $newName;
        }
        if ($newEmail) {
            $stmt2 = $conn->prepare("UPDATE app_users SET email = ? WHERE id = ?");
            $stmt2->bind_param('si', $newEmail, $userId);
            $stmt2->execute();
            $_SESSION['user']['email'] = $newEmail;
        }
        jsonResponse(['success' => true, 'message' => 'Profile updated', 'user' => $_SESSION['user']]);
    }
}

// === NON-JSON REDIRECT ===
if (!isJsonRequest()) {
    $redir = trim($_GET['redirect'] ?? '');
    $target = './index.php?login=1';
    if ($redir) $target .= '&redirect=' . urlencode($redir);
    header('Location: ' . $target);
    exit;
}

jsonResponse(['error' => 'Invalid request method or action'], 400);
