<?php
/**
 * PAVANCAB GOA - Public API Backend
 * Location: /app/api.php
 * 
 * Handles: Location lookups, FCM tokens, SOS, Reports, Webhooks, Health check
 * Bookings → bookings.php | Rides → api_rides.php | Auth → auth.php | Admin → api_dashboard.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://pavancab.com');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = '/' . ltrim(preg_replace('#^.*?(?:api)\.php#i', '', $path), '/');
$action = $_REQUEST['action'] ?? $_GET['action'] ?? '';
if ($action) {
    $path = '/' . ltrim($action, '/');
}
if ($path === '' || $path === false) $path = '/';
$method = $_SERVER['REQUEST_METHOD'];

// =========================================================
// LOCATION LOOKUP ENDPOINTS (Public, no auth)
// =========================================================

if ($method === 'GET' && $path === '/pickups') {
    $rows = dbRows('SELECT id, name FROM goaplaces ORDER BY name ASC');
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/drops') {
    $pickup_id = intval($_GET['pickup_id'] ?? 0);
    if (!$pickup_id) jsonResponse(['error' => 'pickup_id required'], 400);
    $rows = dbRows(
        'SELECT DISTINCT id, destination, distance, sedan_fare, suv_fare FROM goafares WHERE goaplace_id = ? ORDER BY destination ASC',
        'i', [$pickup_id]
    );
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/hourly-fares') {
    $rows = dbRows('SELECT h.*, p.name as place_name FROM goahourfares h LEFT JOIN goaplaces p ON h.place_id = p.id');
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/hourly-fares-by-pickup') {
    $pickup_id = intval($_GET['pickup_id'] ?? 0);
    if (!$pickup_id) jsonResponse(['error' => 'pickup_id required'], 400);
    $rows = dbRows(
        'SELECT h.*, p.name as place_name FROM goahourfares h LEFT JOIN goaplaces p ON h.place_id = p.id WHERE h.place_id = ? ORDER BY h.cab_type ASC',
        'i', [$pickup_id]
    );
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/hourly-pickups') {
    $rows = dbRows('SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goahourfares h ON h.place_id = p.id ORDER BY p.name ASC');
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/tours') {
    $rows = dbRows('SELECT * FROM goatours WHERE is_active = 1 ORDER BY tour_name ASC');
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/tours-by-pickup') {
    $pickup_id = intval($_GET['pickup_id'] ?? 0);
    if (!$pickup_id) jsonResponse(['error' => 'pickup_id required'], 400);
    $rows = dbRows(
        'SELECT t.*, p.name as place_name FROM goatours t LEFT JOIN goaplaces p ON t.place_id = p.id WHERE t.place_id = ? AND t.is_active = 1 ORDER BY t.tour_name ASC',
        'i', [$pickup_id]
    );
    jsonResponse($rows);
}

if ($method === 'GET' && $path === '/tour-pickups') {
    $rows = dbRows('SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goatours t ON t.place_id = p.id AND t.is_active = 1 ORDER BY p.name ASC');
    jsonResponse($rows);
}

// =========================================================
// FCM TOKEN REGISTRATION (Public - client calls this)
// =========================================================

if ($method === 'POST' && ($path === '/save_fcm_token' || ($path === '/fcm_token') || (($_POST['action'] ?? $_GET['action'] ?? '') === 'save_fcm_token'))) {
    $b = getBody();
    $fcmToken  = trim($b['fcm_token'] ?? $b['token'] ?? '');
    $userEmail = trim($b['user_email'] ?? $b['email'] ?? ($_SESSION['user']['email'] ?? ''));
    $userPhone = cleanPhoneDigits($b['user_mobile'] ?? $b['phone'] ?? ($_SESSION['user']['mobile'] ?? ''));

    if (!$fcmToken) jsonResponse(['success' => false, 'message' => 'No token provided'], 200);

    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online, last_active_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, last_active_at = NOW(), updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param('sss', $fcmToken, $userEmail, $userPhone);
        $stmt->execute();
    }

    if ($userPhone) {
        $clean10 = substr($userPhone, -10);
        $fSafe = $conn->real_escape_string($fcmToken);
        $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
        $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
        $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");

        $stmt2 = $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1, last_active_at = NOW() WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ?");
        if ($stmt2) { $stmt2->bind_param('ss', $fcmToken, $clean10); $stmt2->execute(); }
        $stmt3 = $conn->prepare("UPDATE app_team_members SET fcm_token = ?, is_online = 1 WHERE RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = ?");
        if ($stmt3) { $stmt3->bind_param('ss', $fcmToken, $clean10); $stmt3->execute(); }
        $stmt4 = $conn->prepare("UPDATE app_drivers SET fcm_token = ?, is_online = 1 WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?");
        if ($stmt4) { $stmt4->bind_param('ss', $fcmToken, $clean10); $stmt4->execute(); }
    } else {
        $stmtClear = $conn->prepare("UPDATE app_fcm_tokens SET user_mobile = NULL, user_email = NULL, is_online = 0 WHERE fcm_token = ?");
        if ($stmtClear) { $stmtClear->bind_param('s', $fcmToken); $stmtClear->execute(); }
    }

    if ($userEmail) {
        $cleanEmail = strtolower($userEmail);
        $stmt5 = $conn->prepare("UPDATE app_users SET fcm_token = ?, last_active_at = NOW() WHERE LOWER(email) = ?");
        if ($stmt5) { $stmt5->bind_param('ss', $fcmToken, $cleanEmail); $stmt5->execute(); }
    }

    jsonResponse(['success' => true, 'message' => 'FCM token registered successfully']);
}

// =========================================================
// FCM DEBUG LOGGER
// =========================================================

if (($method === 'POST' || $method === 'GET') && ($path === '/fcm_debug' || (($_POST['action'] ?? $_GET['action'] ?? '') === 'fcm_debug'))) {
    $b = getBody();
    $logMsg = trim($b['msg'] ?? $b['error'] ?? $_GET['msg'] ?? 'FCM debug ping');
    $userPhone = cleanPhoneDigits($b['user_mobile'] ?? $b['phone'] ?? ($_SESSION['user']['mobile'] ?? ''));
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_notifications (booking_id, type, title, message, recipient_email) VALUES (NULL, 'FCM_DEBUG', ?, ?, ?)");
    if ($stmt) {
        $title = "Client FCM Debug: " . substr($logMsg, 0, 50);
        $stmt->bind_param('sss', $title, $logMsg, $userPhone);
        $stmt->execute();
    }
    jsonResponse(['success' => true]);
}

// =========================================================
// HEARTBEAT (tracks online status)
// =========================================================

if ($method === 'POST' && ($path === '/heartbeat' || (($_POST['action'] ?? $_GET['action'] ?? '') === 'heartbeat'))) {
    $b = getBody();
    $userPhone = cleanPhoneDigits($b['user_mobile'] ?? $b['phone'] ?? ($_SESSION['user']['mobile'] ?? ''));
    $userEmail = trim($b['user_email'] ?? $b['email'] ?? ($_SESSION['user']['email'] ?? ''));
    $isOnline  = isset($b['is_online']) ? intval($b['is_online']) : 1;
    $deviceInfo = trim($b['device_info'] ?? substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250));
    $fcmToken  = trim($b['fcm_token'] ?? '');

    $conn = db();
    if ($userPhone) {
        $clean10 = substr($userPhone, -10);
        if ($fcmToken) {
            $stmtF = $conn->prepare("UPDATE app_fcm_tokens SET is_online = ?, last_active_at = NOW(), device_info = IFNULL(?, device_info) WHERE fcm_token = ?");
            if ($stmtF) { $stmtF->bind_param('iss', $isOnline, $deviceInfo, $fcmToken); $stmtF->execute(); }
        }
        if ($isOnline === 1) {
            $stmt = $conn->prepare("UPDATE app_users SET is_online = 1, last_active_at = NOW(), device_info = IFNULL(?, device_info) WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ?");
            if ($stmt) { $stmt->bind_param('ss', $deviceInfo, $clean10); $stmt->execute(); }
        } else {
            $activeTokens = dbRows("SELECT id FROM app_fcm_tokens WHERE RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = ? AND is_online = 1", 's', [$clean10]);
            $finalOnline = !empty($activeTokens) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE app_users SET is_online = ?, last_active_at = NOW() WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ?");
            if ($stmt) { $stmt->bind_param('is', $finalOnline, $clean10); $stmt->execute(); }
        }
    }
    if ($userEmail) {
        $cleanEmail = strtolower($userEmail);
        $stmt5 = $conn->prepare("UPDATE app_users SET is_online = ?, last_active_at = NOW() WHERE LOWER(email) = ?");
        if ($stmt5) { $stmt5->bind_param('is', $isOnline, $cleanEmail); $stmt5->execute(); }
    }
    if ($fcmToken) {
        $stmtTok = $conn->prepare("UPDATE app_fcm_tokens SET is_online = ?, last_active_at = NOW(), device_info = IFNULL(?, device_info) WHERE fcm_token = ?");
        if ($stmtTok) { $stmtTok->bind_param('iss', $isOnline, $deviceInfo, $fcmToken); $stmtTok->execute(); }
    }

    jsonResponse(['success' => true, 'is_online' => $isOnline]);
}

// =========================================================
// SOS EMERGENCY ALERTS
// =========================================================

if ($method === 'POST' && ($path === '/trigger_sos' || ($path === '/sos') || (($_POST['action'] ?? $_GET['action'] ?? '') === 'trigger_sos'))) {
    $b = getBody();
    $userPhone = cleanPhoneDigits($b['user_phone'] ?? $b['phone'] ?? $b['customer_phone'] ?? '');
    $userName  = trim($b['user_name'] ?? $b['name'] ?? $b['customer_name'] ?? '');
    $bookingId = intval($b['booking_id'] ?? 0);
    $lat       = isset($b['latitude']) ? floatval($b['latitude']) : null;
    $lng       = isset($b['longitude']) ? floatval($b['longitude']) : null;
    
    if (!$userPhone && isset($_SESSION['user']['mobile'])) $userPhone = cleanPhoneDigits($_SESSION['user']['mobile']);
    if (!$userName && isset($_SESSION['user']['name'])) $userName = $_SESSION['user']['name'];

    if (!$userPhone && !$bookingId) jsonResponse(['error' => 'Phone number or Booking ID required for Emergency SOS'], 400);

    $mapsLink = '';
    if ($lat && $lng) $mapsLink = "https://www.google.com/maps?q={$lat},{$lng}";

    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_emergency_alerts (booking_id, user_phone, user_name, latitude, longitude, google_maps_link, status) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE')");
    $stmt->bind_param('issdds', $bookingId, $userPhone, $userName, $lat, $lng, $mapsLink);
    $stmt->execute();
    $sosId = $conn->insert_id;

    notifyAdminAndTeamSOS($sosId, $bookingId, $userPhone, $userName, $lat, $lng, $mapsLink);

    jsonResponse([
        'success' => true,
        'message' => '🚨 EMERGENCY SOS ALERT FIRED! Admin & Team have been notified via WhatsApp & Push.',
        'sos_id' => $sosId,
        'maps_link' => $mapsLink
    ]);
}

if ($method === 'POST' && ($path === '/resolve_sos' || (($_POST['action'] ?? $_GET['action'] ?? '') === 'resolve_sos'))) {
    requireAdminAuth();
    $b = getBody();
    $sosId = intval($b['sos_id'] ?? 0);
    $notes = trim($b['notes'] ?? 'Resolved by Dispatch Tower');
    $resolver = $_SESSION['user']['name'] ?? 'Admin Dispatch';
    if (!$sosId) jsonResponse(['error' => 'sos_id required'], 400);

    $conn = db();
    $stmt = $conn->prepare("UPDATE app_emergency_alerts SET status = 'RESOLVED', notes = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?");
    $stmt->bind_param('ssi', $notes, $resolver, $sosId);
    $stmt->execute();

    jsonResponse(['success' => true, 'message' => 'Emergency SOS marked as resolved']);
}

if ($method === 'GET' && ($path === '/get_emergency_alerts' || (($_GET['action'] ?? '') === 'get_emergency_alerts'))) {
    requireAdminAuth();
    $status = $_GET['status'] ?? 'ACTIVE';
    if ($status === 'ALL') {
        $rows = dbRows("SELECT * FROM app_emergency_alerts ORDER BY id DESC LIMIT 50");
    } else {
        $rows = dbRows("SELECT * FROM app_emergency_alerts WHERE status = ? ORDER BY id DESC LIMIT 50", 's', [$status]);
    }
    jsonResponse(['success' => true, 'alerts' => $rows]);
}

// =========================================================
// RIDE REPORTS
// =========================================================

if ($method === 'POST' && ($path === '/submit_ride_report' || ($path === '/report_ride') || (($_POST['action'] ?? $_GET['action'] ?? '') === 'submit_ride_report') || (($_POST['action'] ?? $_GET['action'] ?? '') === 'report_issue'))) {
    $b = getBody();
    $bookingId     = intval($b['booking_id'] ?? 0);
    $reporterPhone = cleanPhoneDigits($b['reporter_phone'] ?? $b['phone'] ?? '');
    $reporterName  = trim($b['reporter_name'] ?? $b['name'] ?? '');
    $category      = trim($b['issue_category'] ?? 'SAFETY');
    $severity      = trim($b['severity'] ?? 'MEDIUM');
    $description   = trim($b['description'] ?? '');
    $rideStatus    = trim($b['ride_status_at_report'] ?? 'ONGOING');

    if (!$reporterPhone && isset($_SESSION['user']['mobile'])) $reporterPhone = cleanPhoneDigits($_SESSION['user']['mobile']);
    if (!$reporterName && isset($_SESSION['user']['name'])) $reporterName = $_SESSION['user']['name'];

    if (!$bookingId || !$description) jsonResponse(['error' => 'Booking ID and Issue Description are required to submit a report'], 400);

    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_ride_reports (booking_id, reporter_phone, reporter_name, issue_category, severity, description, ride_status_at_report, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
    $stmt->bind_param('issssss', $bookingId, $reporterPhone, $reporterName, $category, $severity, $description, $rideStatus);
    $stmt->execute();
    $reportId = $conn->insert_id;

    notifyAdminAndTeamRideReport($reportId, $bookingId, $reporterPhone, $reporterName, $category, $severity, $description);

    jsonResponse([
        'success' => true,
        'message' => '⚠️ Ride report submitted successfully. Pavan Cab Team has been notified.',
        'report_id' => $reportId
    ]);
}

if ($method === 'GET' && ($path === '/get_user_reports' || (($_GET['action'] ?? '') === 'get_user_reports'))) {
    $phone = cleanPhoneDigits($_GET['phone'] ?? '');
    if (!$phone && isset($_SESSION['user']['mobile'])) $phone = cleanPhoneDigits($_SESSION['user']['mobile']);
    if (!$phone) jsonResponse(['success' => true, 'reports' => []]);

    $clean10 = substr($phone, -10);
    $rows = dbRows("SELECT * FROM app_ride_reports WHERE RIGHT(REPLACE(REPLACE(reporter_phone, '+', ''), ' ', ''), 10) = ? ORDER BY id DESC", 's', [$clean10]);
    jsonResponse(['success' => true, 'reports' => $rows]);
}

if ($method === 'GET' && ($path === '/get_all_reports' || (($_GET['action'] ?? '') === 'get_all_reports'))) {
    requireAdminAuth();
    $rows = dbRows("SELECT r.*, b.booking_ref, b.pickup_location, b.drop_location, b.driver_name, b.driver_phone, b.vehicle_number 
                    FROM app_ride_reports r 
                    LEFT JOIN app_bookings b ON r.booking_id = b.id 
                    ORDER BY r.id DESC LIMIT 100");
    jsonResponse(['success' => true, 'reports' => $rows]);
}

if ($method === 'POST' && ($path === '/update_report_status' || (($_POST['action'] ?? $_GET['action'] ?? '') === 'update_report_status'))) {
    requireAdminAuth();
    $b = getBody();
    $reportId = intval($b['report_id'] ?? 0);
    $status   = trim($b['status'] ?? 'INVESTIGATING');
    $response = trim($b['admin_response'] ?? '');

    if (!$reportId) jsonResponse(['error' => 'report_id required'], 400);

    $conn = db();
    $stmt = $conn->prepare("UPDATE app_ride_reports SET status = ?, admin_response = ? WHERE id = ?");
    $stmt->bind_param('ssi', $status, $response, $reportId);
    $stmt->execute();

    $rep = dbRows("SELECT * FROM app_ride_reports WHERE id = ?", 'i', [$reportId]);
    if (!empty($rep) && !empty($rep[0]['reporter_phone'])) {
        $rData = $rep[0];
        $msg = "PAVAN CAB UPDATE ON REPORT #{$reportId} (Booking #{$rData['booking_id']})\n"
             . "Status: {$status}\n"
             . ($response ? "Note: {$response}\n" : "")
             . "Thank you for helping keep Pavan Cab safe!";
        sendMetaWhatsApp($rData['reporter_phone'], $msg);
    }

    jsonResponse(['success' => true, 'message' => 'Report status updated successfully']);
}

// =========================================================
// WHATSAPP WEBHOOK (Meta Cloud API verification + incoming messages)
// =========================================================

if ($method === 'GET' && ($path === '/webhook' || $path === '/whatsapp/webhook')) {
    $mode      = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && defined('META_VERIFY_TOKEN') && $token === META_VERIFY_TOKEN) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($method === 'POST' && ($path === '/webhook' || $path === '/whatsapp/webhook')) {
    $body = getBody();
    if (($body['object'] ?? '') === 'whatsapp_business_account') {
        $message = $body['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if ($message && isset($message['text'])) {
            $fromPhone = $message['from'];
            $textBody  = strtoupper(trim($message['text']['body'] ?? ''));
            $cleanFrom = preg_replace('/\D/', '', $fromPhone);

            $rows = dbRows(
                "SELECT * FROM app_bookings WHERE (driver_phone LIKE CONCAT('%', RIGHT(?, 10)) OR customer_phone LIKE CONCAT('%', RIGHT(?, 10))) AND status IN ('CONFIRMED','ASSIGNED','PENDING','IN_TRANSIT') ORDER BY id DESC LIMIT 1",
                'ss', [$cleanFrom, $cleanFrom]
            );

            if (!empty($rows)) {
                $bk = $rows[0];
                $bid = $bk['id'];

                if (in_array($textBody, ['1', 'ACCEPT', 'YES', 'CONFIRM'])) {
                    dbExec("UPDATE app_bookings SET driver_decision = 'ACCEPTED', status = 'CONFIRMED' WHERE id = ?", 'i', [$bid]);
                    sendMetaWhatsApp($fromPhone, "RIDE ACCEPTED!\nYou have ACCEPTED ride #{$bk['booking_ref']}.\nPassenger: {$bk['customer_name']} ({$bk['customer_phone']})\nPickup: {$bk['pickup_location']}\nReply 3 or START when trip begins.");
                    sendMetaWhatsApp($bk['customer_phone'], "DRIVER ACCEPTED YOUR RIDE!\nDriver {$bk['driver_name']} ({$bk['driver_phone']}) has accepted booking #{$bk['booking_ref']}. Cab is en route!");
                    broadcastRideLifecycleFCM('DRIVER_ACCEPTED', $bid);

                } elseif (in_array($textBody, ['2', 'DECLINE', 'REJECT', 'NO'])) {
                    dbExec("UPDATE app_bookings SET driver_decision = 'DECLINED', status = 'PENDING', driver_id = NULL WHERE id = ?", 'i', [$bid]);
                    if ($bk['driver_id']) dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$bk['driver_id']]);
                    sendMetaWhatsApp($fromPhone, "RIDE DECLINED\nYou have DECLINED ride #{$bk['booking_ref']}. Returned to dispatch tower.");
                    broadcastRideLifecycleFCM('DRIVER_DECLINED', $bid);

                } elseif (in_array($textBody, ['3', 'START'])) {
                    dbExec("UPDATE app_bookings SET driver_decision = 'IN_TRANSIT', status = 'IN_TRANSIT' WHERE id = ?", 'i', [$bid]);
                    sendMetaWhatsApp($fromPhone, "RIDE STARTED!\nTrip #{$bk['booking_ref']} is now in transit. Have a safe ride!");
                    broadcastRideLifecycleFCM('RIDE_STARTED', $bid);

                } elseif (in_array($textBody, ['4', 'COMPLETE', 'FINISH', 'DONE'])) {
                    dbExec("UPDATE app_bookings SET driver_decision = 'COMPLETED', status = 'COMPLETED' WHERE id = ?", 'i', [$bid]);
                    if ($bk['driver_id']) dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$bk['driver_id']]);
                    sendMetaWhatsApp($fromPhone, "RIDE COMPLETED!\nTrip #{$bk['booking_ref']} marked as completed. Great job!");
                    broadcastRideLifecycleFCM('RIDE_COMPLETED', $bid);
                }
            }
        }
    }
    jsonResponse(['status' => 'EVENT_RECEIVED']);
}

// =========================================================
// HEALTH CHECK
// =========================================================

if ($method === 'GET' && $path === '/health') {
    jsonResponse(['status' => 'ok', 'server' => 'PAVANCAB PHP API', 'time' => date('Y-m-d H:i:s')]);
}

// =========================================================
// 404 fallback
// =========================================================
jsonResponse(['error' => "Route not found: $method $path"], 404);
