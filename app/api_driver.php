<?php
/**
 * PAVANCAB Driver API
 * OTP-based login, admin approval gate, bookings, trip status, earnings
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$body = getBody();
$action = trim($body['action'] ?? $_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Auto-migrate: add columns needed for driver app
try {
    $conn = db();
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0");
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0");
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL");
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL");
} catch (Exception $e) {}

// ===== SEND OTP =====
if ($action === 'send-otp' && $method === 'POST') {
    $phone = cleanPhoneDigits($body['phone'] ?? '');
    if (!$phone || strlen($phone) < 7) {
        jsonResponse(['success' => false, 'error' => 'Valid phone number required'], 400);
    }

    $clean10 = substr($phone, -10);
    $otp = strval(random_int(100000, 999999));

    $conn = db();
    // Store OTP
    dbExec("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))", 'ss', [$phone, $otp]);

    // Send via WhatsApp
    $sent = false;
    try {
        $result = sendOTPWhatsAppTemplate($phone, $otp, 'PAVANCAB', 'Driver', 'PAVANCAB Driver', 'PAVANCAB', '+919518541625');
        $sent = !empty($result['success']);
        @file_put_contents(__DIR__ . '/otp_debug.log', date('Y-m-d H:i:s') . " | API_DRIVER send-otp | phone=$phone | otp=$otp | sent=$sent | result=" . json_encode($result) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {}

    // Debug: include OTP in response (remove in production)
    jsonResponse(['success' => true, 'message' => 'OTP sent via WhatsApp', 'debug_otp' => $otp, 'whatsapp_sent' => $sent]);
}

// ===== VERIFY OTP =====
if ($action === 'verify-otp' && $method === 'POST') {
    $phone = cleanPhoneDigits($body['phone'] ?? '');
    $otp = trim($body['otp'] ?? '');

    if (!$phone || !$otp) {
        jsonResponse(['success' => false, 'error' => 'Phone and OTP required'], 400);
    }

    $clean10 = substr($phone, -10);
    $cleanOtp = trim($otp);
    $conn = db();

    // Verify OTP from app_otp_store
    $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('sss', $phone, $clean10, $cleanOtp);
    $stmt->execute();
    $r = $stmt->get_result();
    $otpRow = $r ? $r->fetch_assoc() : null;

    if (!$otpRow) {
        jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP. Please try again.'], 401);
    }

    // Delete used OTP
    dbExec("DELETE FROM app_otp_store WHERE id = ?", 'i', [$otpRow['id']]);

    // Check if driver exists in app_drivers
    $stmt = $conn->prepare("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1");
    $stmt->bind_param('s', $clean10);
    $stmt->execute();
    $r = $stmt->get_result();
    $driver = $r ? $r->fetch_assoc() : null;

    if ($driver) {
        // Existing driver â€” check approval
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
        // New driver â€” auto-register as PENDING (not approved)
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

        // Notify admin about new driver registration
        try {
            sendFCMPushToAdmins(
                "ðŸš• New Driver Registration",
                "Phone: $phone has registered as a driver. Please approve from Admin app.",
                ['type' => 'NEW_DRIVER', 'driver_id' => strval($newId)]
            );
        } catch (Exception $e) {}

        // WhatsApp to admin
        try {
            sendMetaWhatsApp("+919000000000", "ðŸš• *NEW DRIVER REGISTRATION*\n\nPhone: $phone\nStatus: PENDING APPROVAL\n\nPlease approve from Admin app.");
        } catch (Exception $e) {}

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

// ===== CHECK SESSION =====
if ($action === 'check-session') {
    if (empty($_SESSION['driver']['isLoggedIn'])) {
        jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
    }
    // Re-check approval status
    $driverId = intval($_SESSION['driver']['id']);
    $conn = db();
    $rows = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    $approved = !empty($rows) ? intval($rows[0]['is_approved'] ?? 0) : 0;

    jsonResponse([
        'success' => true,
        'approved' => $approved == 1,
        'driver' => $_SESSION['driver']
    ]);
}

// ===== CHECK APPROVAL =====
if ($action === 'check-approval') {
    if (empty($_SESSION['driver']['isLoggedIn'])) {
        jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
    }
    $driverId = intval($_SESSION['driver']['id']);
    $conn = db();
    $rows = dbRows("SELECT is_approved, name, car_model, plate_number FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);

    jsonResponse([
        'success' => true,
        'approved' => intval($rows[0]['is_approved'] ?? 0) == 1,
        'driver' => $rows[0]
    ]);
}

// ===== CHECK PHONE (has password?) =====
if ($action === 'check-phone' && $method === 'POST') {
    $phone = cleanPhoneDigits($body['phone'] ?? '');
    if (!$phone || strlen($phone) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone required'], 400);
    $clean10 = substr($phone, -10);
    $rows = dbRows("SELECT id, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
    jsonResponse([
        'success' => true,
        'exists' => !empty($rows),
        'has_password' => !empty($rows[0]['password_hash'] ?? null)
    ]);
}

// ===== SET PASSWORD =====
if ($action === 'set-password' && $method === 'POST') {
    $phone = cleanPhoneDigits($body['phone'] ?? '');
    $password = trim($body['password'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
    $clean10 = substr($phone, -10);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
    jsonResponse(['success' => true, 'message' => 'Password set successfully']);
}

// ===== LOGIN WITH PASSWORD =====
if ($action === 'login-with-password' && $method === 'POST') {
    $phone = cleanPhoneDigits($body['phone'] ?? '');
    $password = trim($body['password'] ?? '');
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

// ===== RESET PASSWORD =====
if ($action === 'reset-password' && $method === 'POST') {
    $phone = cleanPhoneDigits($body['phone'] ?? '');
    $otp = trim($body['otp'] ?? '');
    $password = trim($body['password'] ?? '');
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

// ===== REQUIRE DRIVER AUTH =====
function requireDriverAuth() {
    if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
        jsonResponse(['error' => 'Driver authentication required. Please login.'], 401);
    }
    // Also check approval
    $driverId = intval($_SESSION['driver']['id']);
    $conn = db();
    $rows = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (empty($rows) || intval($rows[0]['is_approved'] ?? 0) != 1) {
        jsonResponse(['error' => 'Your account has not been approved yet.', 'approved' => false], 403);
    }
}

// ===== MY BOOKINGS =====
if ($action === 'my-bookings') {
    requireDriverAuth();
    $driverId = intval($_SESSION['driver']['id']);
    $rows = dbRows(
        "SELECT b.*, 
         COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name,
         COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone
         FROM app_bookings b 
         LEFT JOIN app_drivers d ON b.driver_id = d.id 
         WHERE b.driver_id = ? 
         ORDER BY b.pickup_date DESC, b.pickup_time DESC 
         LIMIT 100",
        'i', [$driverId]
    );
    jsonResponse(['bookings' => $rows]);
}

// ===== BOOKING DETAIL =====
if ($action === 'booking-detail') {
    requireDriverAuth();
    $driverId = intval($_SESSION['driver']['id']);
    $bookingId = intval($body['id'] ?? $_GET['id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
    $rows = dbRows("SELECT b.* FROM app_bookings b WHERE b.id = ? AND (b.driver_id = ? OR ? = 0) LIMIT 1", 'iii', [$bookingId, $driverId, $driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    jsonResponse(['booking' => $rows[0]]);
}

// ===== RESPOND TO BOOKING =====
if ($action === 'respond' && $method === 'POST') {
    requireDriverAuth();
    $driverId = intval($_SESSION['driver']['id']);
    $driverName = $_SESSION['driver']['name'] ?? '';
    $driverPhone = $_SESSION['driver']['phone'] ?? '';
    $bookingId = intval($body['booking_id'] ?? 0);
    $decision = strtoupper(trim($body['decision'] ?? ''));

    if (!$bookingId || !in_array($decision, ['ACCEPT', 'REJECT'])) {
        jsonResponse(['error' => 'booking_id and decision (ACCEPT/REJECT) required'], 400);
    }

    $conn = db();
    $rows = dbRows("SELECT id, status, driver_id, customer_name, customer_phone, booking_ref FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);

    $booking = $rows[0];
    if ($booking['status'] !== 'ASSIGNED' && $booking['status'] !== 'ACCEPTED') {
        jsonResponse(['error' => 'Booking is in ' . $booking['status'] . ' status, cannot respond'], 400);
    }

    if ($decision === 'ACCEPT') {
        dbExec("UPDATE app_bookings SET status = 'ACCEPTED', driver_decision = 'ACCEPTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
        try {
            sendFCMPushToAdmins("âœ… Driver Accepted (#{$booking['booking_ref']})", "$driverName ($driverPhone) accepted ride #{$booking['booking_ref']} for {$booking['customer_name']}", ['type' => 'BOOKING_CONFIRMED', 'booking_id' => strval($bookingId)]);
        } catch (Exception $e) {}
    } else {
        dbExec("UPDATE app_bookings SET status = 'PENDING', driver_id = 0, driver_name = '', driver_phone = '', driver_decision = 'REJECTED', updated_at = NOW() WHERE id = ?", 'i', [$bookingId]);
        dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
        try {
            sendFCMPushToAdmins("ðŸš¨ Driver Rejected (#{$booking['booking_ref']})", "$driverName REJECTED ride #{$booking['booking_ref']}. Please re-assign!", ['type' => 'DRIVER_REJECTED', 'booking_id' => strval($bookingId)]);
        } catch (Exception $e) {}
    }

    jsonResponse(['success' => true, 'decision' => $decision]);
}

// ===== TRIP STATUS UPDATE =====
if ($action === 'trip-status' && $method === 'POST') {
    requireDriverAuth();
    $driverId = intval($_SESSION['driver']['id']);
    $bookingId = intval($body['booking_id'] ?? 0);
    $status = strtoupper(trim($body['status'] ?? ''));

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

// ===== EARNINGS =====
if ($action === 'earnings') {
    requireDriverAuth();
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

// ===== PROFILE =====
if ($action === 'profile') {
    requireDriverAuth();
    $driverId = intval($_SESSION['driver']['id']);
    $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, status, is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
    jsonResponse(['driver' => $rows[0]]);
}

// ===== TOGGLE ONLINE =====
if ($action === 'toggle-online' && $method === 'POST') {
    requireDriverAuth();
    $driverId = intval($_SESSION['driver']['id']);
    $row = dbRows("SELECT is_online FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    $current = intval($row[0]['is_online'] ?? 0);
    $newVal = $current == 1 ? 0 : 1;
    dbExec("UPDATE app_drivers SET is_online = ? WHERE id = ?", 'ii', [$newVal, $driverId]);
    jsonResponse(['success' => true, 'is_online' => $newVal]);
}

// ===== SAVE FCM TOKEN =====
if ($action === 'save-fcm-token' && $method === 'POST') {
    $fcmToken = trim($body['fcm_token'] ?? '');
    $phone = cleanPhoneDigits($body['phone'] ?? '');

    if ($fcmToken) {
        $conn = db();
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

// ===== LOGOUT =====
if ($action === 'logout' && $method === 'POST') {
    if (!empty($_SESSION['driver']['id'])) {
        $driverId = intval($_SESSION['driver']['id']);
        try {
            dbExec("UPDATE app_drivers SET is_online = 0 WHERE id = ?", 'i', [$driverId]);
        } catch (Exception $e) {}
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    jsonResponse(['success' => true]);
}

// ===== ADMIN: APPROVE DRIVER =====
if ($action === 'approve-driver' && $method === 'POST') {
    // Admin auth check
    if (empty($_SESSION['user'])) {
        jsonResponse(['error' => 'Admin auth required'], 401);
    }

    $driverId = intval($body['driver_id'] ?? 0);
    $approved = intval($body['approved'] ?? 1);

    if (!$driverId) jsonResponse(['error' => 'driver_id required'], 400);

    dbExec("UPDATE app_drivers SET is_approved = ? WHERE id = ?", 'ii', [$approved, $driverId]);

    // Notify driver
    $rows = dbRows("SELECT phone, name FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (!empty($rows)) {
        $phone = $rows[0]['phone'];
        if ($approved) {
            try {
                sendFCMPushToDriver($driverId, "âœ… Account Approved!", "Your driver account has been approved. You can now start accepting rides.", ['type' => 'APPROVED']);
                sendMetaWhatsApp($phone, "ðŸŽ‰ *ACCOUNT APPROVED!*\n\nYour PAVANCAB Driver account has been approved!\n\nOpen the Driver App to start accepting rides. Drive safe! ðŸš•");
            } catch (Exception $e) {}
        } else {
            try {
                sendFCMPushToDriver($driverId, "âŒ Account Rejected", "Your driver account has been rejected. Contact support for details.", ['type' => 'REJECTED']);
            } catch (Exception $e) {}
        }
    }

    jsonResponse(['success' => true, 'approved' => $approved == 1]);
}

// ===== ADMIN: LIST PENDING DRIVERS =====
if ($action === 'pending-drivers') {
    if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
    $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, created_at FROM app_drivers WHERE is_approved = 0 ORDER BY id DESC");
    jsonResponse(['drivers' => $rows]);
}

// ===== ADMIN: LIST ALL DRIVERS =====
if ($action === 'all-drivers') {
    if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
    $rows = dbRows("SELECT id, name, phone, car_model, plate_number, is_approved, is_online, rating, status FROM app_drivers ORDER BY id DESC");
    jsonResponse(['drivers' => $rows]);
}

jsonResponse(['error' => 'Unknown action: ' . $action], 400);
