<?php
/**
 * PAVANCAB GOA TAXI - Booking Creation & History API Endpoint
 * Path: app/bookings.php
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
    $input = getBody();

    $user_email      = isset($input['user_email']) ? trim($input['user_email']) : ($_SESSION['user']['email'] ?? 'guest@goataxi.com');
    $customer_name   = isset($input['customer_name']) ? trim($input['customer_name']) : ($_SESSION['user']['name'] ?? '');
    $customer_phone  = isset($input['customer_phone']) ? cleanPhoneDigits($input['customer_phone']) : cleanPhoneDigits($_SESSION['user']['mobile'] ?? '');

    // Allow session OR request body phone/email as auth
    $authed = !empty($_SESSION['user']);
    if (!$authed && !empty($customer_phone)) {
        // Verify user exists in app_users by phone
        $conn = db();
        $clean10 = substr(preg_replace('/\D/', '', $customer_phone), -10);
        if ($clean10) {
            $stmtCheck = $conn->prepare("SELECT id FROM app_users WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ?");
            $stmtCheck->bind_param('s', $clean10);
            $stmtCheck->execute();
            if ($stmtCheck->get_result()->num_rows > 0) {
                $authed = true;
            }
        }
    }
    if (!$authed && !empty($user_email) && $user_email !== 'guest@goataxi.com') {
        $conn = db();
        $stmtCheck = $conn->prepare("SELECT id FROM app_users WHERE LOWER(email) = ?");
        $stmtCheck->bind_param('s', strtolower($user_email));
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            $authed = true;
        }
    }
    if (!$authed) {
        http_response_code(401);
        echo json_encode(["error" => "WhatsApp Login Required! Please log in first before booking a cab."]);
        exit();
    }
    $trip_type       = isset($input['trip_type']) ? trim($input['trip_type']) : 'one_way';
    $pickup_location = isset($input['pickup_location']) ? trim($input['pickup_location']) : '';
    $drop_location   = isset($input['drop_location']) ? trim($input['drop_location']) : 'N/A';
    $pickup_date     = isset($input['pickup_date']) ? trim($input['pickup_date']) : '';
    $pickup_time     = isset($input['pickup_time']) ? trim($input['pickup_time']) : '';
    $cab_type        = isset($input['cab_type']) ? trim($input['cab_type']) : '';
    $total_fare      = isset($input['total_fare']) ? floatval($input['total_fare']) : 0.00;
    $special_notes   = isset($input['special_notes']) ? trim($input['special_notes']) : '';
    $fcm_token       = isset($input['fcm_token']) ? trim($input['fcm_token']) : '';

    if (empty($customer_name) || empty($customer_phone) || empty($pickup_location) || empty($pickup_date) || empty($pickup_time) || empty($cab_type) || $total_fare <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required booking parameters"]);
        exit();
    }

    $booking_ref = 'GTA-' . date('ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    try {
        $conn = db();

        // If FCM token passed from passenger browser, associate it immediately
        if (!empty($fcm_token)) {
            $cleanPhone = cleanPhoneDigits($customer_phone);
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), updated_at = NOW()");
            if ($stmtFcm) {
                $stmtFcm->bind_param('sss', $fcm_token, $user_email, $cleanPhone);
                $stmtFcm->execute();
            }
            if ($cleanPhone) {
                $stmtFcmUser = $conn->prepare("UPDATE app_users SET fcm_token = ? WHERE RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?");
                if ($stmtFcmUser) {
                    $clean10 = substr($cleanPhone, -10);
                    $stmtFcmUser->bind_param('ss', $fcm_token, $clean10);
                    $stmtFcmUser->execute();
                }
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO app_bookings 
            (booking_ref, user_email, customer_name, customer_phone, trip_type, pickup_location, drop_location, pickup_date, pickup_time, cab_type, total_fare, special_notes, status, booking_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', 'app')
        ");
        $stmt->bind_param('ssssssssssds', 
            $booking_ref,
            $user_email,
            $customer_name,
            $customer_phone,
            $trip_type,
            $pickup_location,
            $drop_location,
            $pickup_date,
            $pickup_time,
            $cab_type,
            $total_fare,
            $special_notes
        );
        $stmt->execute();
        $booking_id = $conn->insert_id;

        // Broadcast FCM Push to Passenger and Admin/Team
        broadcastRideLifecycleFCM('NEW_BOOKING', $booking_id);

        // Broadcast WhatsApp notifications to Super Admin & Team Dispatchers
        notifyAdminAndTeamNewBooking(
            $booking_id,
            $booking_ref,
            $customer_name,
            $customer_phone,
            $pickup_location,
            $drop_location,
            $pickup_date,
            $pickup_time,
            $cab_type,
            $total_fare
        );

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Booking created successfully!",
            "booking" => [
                "id" => $booking_id,
                "booking_ref" => $booking_ref,
                "user_email" => $user_email,
                "customer_name" => $customer_name,
                "customer_phone" => $customer_phone,
                "trip_type" => $trip_type,
                "pickup_location" => $pickup_location,
                "drop_location" => $drop_location,
                "pickup_date" => $pickup_date,
                "pickup_time" => $pickup_time,
                "cab_type" => $cab_type,
                "total_fare" => $total_fare,
                "status" => "PENDING",
                "special_notes" => $special_notes,
                "created_at" => date("Y-m-d H:i:s")
            ]
        ]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create booking: " . $e->getMessage()]);
        exit();
    }
} elseif ($method === 'GET') {
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    $phone = isset($_GET['phone']) ? cleanPhoneDigits($_GET['phone']) : '';

    if (empty($email) && empty($phone) && isset($_SESSION['user'])) {
        $phone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? '');
        $email = $_SESSION['user']['email'] ?? '';
    }

    if (empty($email) && empty($phone)) {
        http_response_code(400);
        echo json_encode(["error" => "Email or phone parameter is required"]);
        exit();
    }

    try {
        $rows = dbRows("
            SELECT b.*, 
            COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
            COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
            COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, 'GA-03-T-1234') as vehicle_number
            FROM app_bookings b 
            LEFT JOIN app_drivers d ON b.driver_id = d.id 
            WHERE (b.user_email = ? AND ? != '') OR RIGHT(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), 10) = ?
            ORDER BY b.id DESC", 'sss', [$email, $email, substr($phone, -10)]);
            
        echo json_encode($rows);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch bookings: " . $e->getMessage()]);
        exit();
    }
}
