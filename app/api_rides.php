<?php
/**
 * PAVANCAB GOA TAXI - User Rides API Module
 * Path: app/api_rides.php
 * 
 * Handles: View bookings, Cancel, Boost fare, Rate ride
 * All actions verify caller owns the booking via phone/email match.
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? $_GET['action'] ?? '';
$b      = getBody();

function handleRidesModule($method, $path, $b) {

    // GET /user-bookings
    if ($method === 'GET' && ($path === '/user-bookings' || $path === '/rides' || strpos($path, 'user-bookings') !== false)) {
        $sessionPhone = $_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? $_SESSION['user']['member_phone'] ?? $_SESSION['active_booking_phone'] ?? '';
        $phone = trim($b['phone'] ?? $_GET['phone'] ?? $sessionPhone);
        $email = trim($b['email'] ?? $_GET['email'] ?? ($_SESSION['user']['email'] ?? ''));
        $bookingIdReq = intval($b['id'] ?? $_GET['id'] ?? $b['booking_id'] ?? $_GET['booking_id'] ?? 0);

        $sql = "SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
                COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, '') as vehicle_number,
                d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
                FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE 1=1";
        $types = '';
        $params = [];

        $clean10 = $phone ? substr(preg_replace('/\D/', '', $phone), -10) : '';

        if ($bookingIdReq > 0 && $clean10) {
            $sql .= " AND (b.id = ? OR (b.customer_phone IS NOT NULL AND RIGHT(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), 10) = ?))";
            $types .= 'is';
            $params[] = $bookingIdReq;
            $params[] = $clean10;
        } elseif ($bookingIdReq > 0) {
            $sql .= " AND b.id = ?";
            $types .= 'i';
            $params[] = $bookingIdReq;
        } elseif ($email && $clean10) {
            $sql .= " AND (b.user_email = ? OR (b.customer_phone IS NOT NULL AND RIGHT(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), 10) = ?))";
            $types .= 'ss';
            $params[] = $email;
            $params[] = $clean10;
        } elseif ($email) {
            $sql .= " AND b.user_email = ?";
            $types .= 's';
            $params[] = $email;
        } elseif ($clean10) {
            $sql .= " AND (b.customer_phone IS NOT NULL AND RIGHT(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), 10) = ?)";
            $types .= 's';
            $params[] = $clean10;
        } else {
            $isPrivileged = isset($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', ['admin', 'team', 'owner']);
            if (!$isPrivileged) {
                jsonResponse([]);
            }
        }

        $sql .= ' ORDER BY b.id DESC';
        $rows = dbRows($sql, $types, $params);
        jsonResponse($rows);
    }

    // Helper: Verify caller owns booking (session OR request body phone/email)
    function verifyBookingOwnership($bk, $b = []) {
        $role = $_SESSION['user']['role'] ?? '';
        if (in_array($role, ['admin', 'team', 'owner']) || !empty($_SESSION['user']['isAdmin']) || !empty($_SESSION['user']['isTeam'])) {
            return true;
        }
        
        $bkPhone = substr(preg_replace('/\D/', '', $bk['customer_phone'] ?? ''), -10);
        $bkEmail = strtolower(trim($bk['user_email'] ?? ''));
        
        // 1. Check session
        $callerPhone = $_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? $_SESSION['active_booking_phone'] ?? '';
        $callerEmail = strtolower(trim($_SESSION['user']['email'] ?? ''));
        $clean10 = substr(preg_replace('/\D/', '', $callerPhone), -10);
        if ($clean10 && $bkPhone && $clean10 === $bkPhone) return true;
        if ($callerEmail && $bkEmail && $callerEmail === $bkEmail) return true;
        
        // 2. Fallback: check phone/email from request body (for expired sessions)
        $reqPhone = trim($b['customer_phone'] ?? $b['phone'] ?? '');
        $reqEmail = strtolower(trim($b['email'] ?? $b['user_email'] ?? ''));
        $reqClean10 = substr(preg_replace('/\D/', '', $reqPhone), -10);
        if ($reqClean10 && $bkPhone && $reqClean10 === $bkPhone) return true;
        if ($reqEmail && $bkEmail && $reqEmail === $bkEmail) return true;
        
        jsonResponse(['error' => 'You can only manage your own bookings.'], 403);
    }

    // POST /user/cancel-booking
    if ($method === 'POST' && ($path === '/user/cancel-booking' || $path === '/cancel-booking')) {
        $booking_id = intval($b['booking_id'] ?? $_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
        if (!$booking_id) jsonResponse(['error' => 'booking_id required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];

        verifyBookingOwnership($bk, $b);

        $statusUpper = strtoupper($bk['status']);
        if (in_array($statusUpper, ['COMPLETED', 'CANCELLED_BY_USER', 'CANCELLED_BY_ADMIN', 'CANCELLED'])) {
            jsonResponse(['error' => 'This ride has already been ' . strtolower($bk['status']) . '. Cannot cancel.'], 400);
        }
        if ($statusUpper === 'IN_TRANSIT' || $statusUpper === 'ON_TRIP') {
            jsonResponse(['error' => 'Ride is already in progress. Please contact dispatch to cancel.'], 400);
        }

        dbExec("UPDATE app_bookings SET status = 'CANCELLED_BY_USER' WHERE id = ?", 'i', [$booking_id]);
        if ($bk['driver_id']) dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$bk['driver_id']]);

        $msg = "Passenger {$bk['customer_name']} ({$bk['customer_phone']}) cancelled ride #{$bk['booking_ref']}";
        $conn = db();
        $stmt = $conn->prepare("INSERT INTO app_notifications (booking_id, type, title, message, recipient_email) VALUES (?, 'CANCELLED_BY_USER', 'Passenger Cancelled Ride', ?, '" . SUPER_ADMIN_EMAIL . "')");
        $stmt->bind_param('is', $booking_id, $msg);
        $stmt->execute();

        broadcastRideLifecycleFCM('CANCELLED_BY_USER', $booking_id);

        sendMetaWhatsApp($bk['customer_phone'], "RIDE CANCELLED BY YOU\nYour Goa cab booking #{$bk['booking_ref']} has been CANCELLED.");
        if ($bk['driver_phone']) {
            sendMetaWhatsApp($bk['driver_phone'], "RIDE CANCELLED BY PASSENGER\nRide #{$bk['booking_ref']} was cancelled by passenger.");
        }

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        jsonResponse(['success' => true, 'message' => 'Ride cancelled successfully', 'booking' => $updated[0]]);
    }

    // POST /user/complete-ride
    if ($method === 'POST' && ($path === '/user/complete-ride' || $path === '/complete-ride')) {
        $booking_id = intval($b['booking_id'] ?? $_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
        if (!$booking_id) jsonResponse(['error' => 'booking_id required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];

        verifyBookingOwnership($bk, $b);

        $statusUpper = strtoupper($bk['status']);
        if ($statusUpper === 'COMPLETED') {
            jsonResponse(['error' => 'This ride is already completed.'], 400);
        }
        if (in_array($statusUpper, ['CANCELLED_BY_USER', 'CANCELLED_BY_ADMIN', 'CANCELLED'])) {
            jsonResponse(['error' => 'This ride has been cancelled.'], 400);
        }

        dbExec("UPDATE app_bookings SET status = 'COMPLETED' WHERE id = ?", 'i', [$booking_id]);
        if ($bk['driver_id']) dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$bk['driver_id']]);

        // Send notifications individually (avoid broadcastRideLifecycleFCM nested function fatal error)
        $ref = $bk['booking_ref'] ?? $bk['id'];
        $custName = $bk['customer_name'] ?? 'Passenger';
        $custPhone = $bk['customer_phone'] ?? '';
        $driverPhone = $bk['driver_phone'] ?? '';
        $pickup = $bk['pickup_location'] ?? 'Goa';
        $drop = $bk['drop_location'] ?? 'Destination';
        $fare = floatval($bk['total_fare'] ?? 0);

        // FCM to customer
        try {
            if ($bk['user_email'] || $custPhone) {
                $fcmtokens = [];
                if (!empty($bk['user_email'])) $fcmtokens = array_merge($fcmtokens, getFCMTokensByEmail($bk['user_email']));
                if (!empty($custPhone)) $fcmtokens = array_merge($fcmtokens, getFCMTokensByPhone($custPhone));
                $fcmtokens = array_values(array_unique(array_map('trim', array_filter($fcmtokens))));
                if (!empty($fcmtokens)) sendFCMPush($fcmtokens, "Ride Completed!", "Trip #$ref completed. Total fare: Rs.$fare. Thank you for choosing PAVANCAB!");
            }
        } catch (\Throwable $e) {}

        // FCM to driver
        try {
            if ($driverPhone) {
                $dtokens = getDriverFCMTokens($driverPhone);
                $dtokens = array_values(array_unique(array_map('trim', array_filter($dtokens))));
                if (!empty($dtokens)) sendFCMPush($dtokens, "Ride Completed (#$ref)", "Trip #$ref has been completed successfully. Great job!");
            }
        } catch (\Throwable $e) {}

        // No FCM to admins for ride completed (cleaner dispatch experience)

        // WhatsApp to customer
        try { sendMetaWhatsApp($custPhone, "🎉 *Ride Completed!*\n\nYour Goa cab ride *#$ref* has been completed.\n💰 Fare: ₹$fare\n\nThank you for riding with PAVANCAB! 🙏\n\nPlease leave us a review on Trustpilot:\nhttps://www.trustpilot.com/review/pavancab.com"); } catch (\Throwable $e) {}

        // WhatsApp to driver
        try { if ($driverPhone) sendMetaWhatsApp($driverPhone, "RIDE COMPLETED\nRide #$ref has been completed. Great job!"); } catch (\Throwable $e) {}

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        jsonResponse(['success' => true, 'message' => 'Ride marked as completed!', 'booking' => $updated[0]]);
    }

    // POST /user/boost-fare
    if ($method === 'POST' && ($path === '/user/boost-fare' || $path === '/boost-fare')) {
        $booking_id = intval($b['booking_id'] ?? $_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
        $boost = floatval($b['boost_amount'] ?? $_POST['boost_amount'] ?? $_GET['boost_amount'] ?? 0);
        if (!$booking_id || $boost <= 0) jsonResponse(['error' => 'booking_id and positive boost_amount required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];

        verifyBookingOwnership($bk, $b);

        $statusUpper = strtoupper($bk['status']);
        if (!in_array($statusUpper, ['PENDING', 'CONFIRMED', 'ASSIGNED'])) {
            jsonResponse(['error' => 'Fare boost is only available before the ride starts.'], 400);
        }

        $currentFare = floatval($bk['total_fare'] ?? 0);
        $newFare = $currentFare + $boost;
        $timestamp = date('h:i A');
        $noteEntry = "\n[PEAK BOOST] +Rs.$boost added at $timestamp (Total: Rs.$newFare)";

        $conn = db();
        $stmt = $conn->prepare("UPDATE app_bookings SET total_fare = ?, special_notes = CONCAT(IFNULL(special_notes, ''), ?) WHERE id = ?");
        $stmt->bind_param('dsi', $newFare, $noteEntry, $booking_id);
        $stmt->execute();

        broadcastRideLifecycleFCM('FARE_BOOSTED', $booking_id, ['boost_amount' => $boost]);

        $updated = dbRows('SELECT b.*, d.name as driver_name_full, d.phone as driver_phone_full FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.id = ?', 'i', [$booking_id]);
        jsonResponse(['success' => true, 'message' => "Fare increased by Rs.$boost! New Total: Rs.$newFare", 'booking' => $updated[0]]);
    }

    // POST /user/rate-ride
    if ($method === 'POST' && ($path === '/user/rate-ride' || $path === '/rate-ride')) {
        $booking_id  = intval($b['booking_id'] ?? $_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
        $rating      = intval($b['rating'] ?? $_POST['rating'] ?? $_GET['rating'] ?? 0);
        $review_text = trim($b['review_text'] ?? $_POST['review_text'] ?? $_GET['review_text'] ?? '');

        if (!$booking_id || $rating < 1 || $rating > 5) {
            jsonResponse(['error' => 'Valid booking_id and rating (1 to 5) are required'], 400);
        }

        $conn = db();
        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];

        verifyBookingOwnership($bk, $b);

        if (strtoupper($bk['status']) !== 'COMPLETED') {
            jsonResponse(['error' => 'You can only rate completed rides.'], 400);
        }

        $stmt = $conn->prepare("UPDATE app_bookings SET user_rating = ?, user_review = ?, rated_at = NOW() WHERE id = ?");
        $stmt->bind_param('isi', $rating, $review_text, $booking_id);
        $stmt->execute();

        $driverId = $bk['driver_id'];
        $driverAvg = 5.0;
        if ($driverId) {
            $stmtAvg = $conn->prepare("SELECT AVG(user_rating) as avg_rating, COUNT(*) as rating_count FROM app_bookings WHERE driver_id = ? AND user_rating > 0");
            $stmtAvg->bind_param('i', $driverId);
            $stmtAvg->execute();
            $rAvg = $stmtAvg->get_result();
            if ($rowAvg = $rAvg->fetch_assoc()) {
                $calcAvg = round(floatval($rowAvg['avg_rating']), 2);
                $cnt = intval($rowAvg['rating_count']);
                $stmtUpd = $conn->prepare("UPDATE app_drivers SET rating = ?, total_ratings = ? WHERE id = ?");
                $stmtUpd->bind_param('dii', $calcAvg, $cnt, $driverId);
                $stmtUpd->execute();
                $driverAvg = $calcAvg;
            }
        }

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        jsonResponse([
            'success' => true,
            'message' => 'Thank you for rating your ride!',
            'booking' => $updated[0],
            'driver_rating' => $driverAvg
        ]);
    }

    // POST /user/respond-fare - Customer accepts or declines fare proposal
    if ($method === 'POST' && ($path === '/user/respond-fare' || $path === '/respond-fare')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        $response = strtoupper(trim($b['response'] ?? ''));
        if (!$booking_id || !in_array($response, ['ACCEPTED', 'DECLINED'])) jsonResponse(['error' => 'booking_id and response (ACCEPTED/DECLINED) required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];
        verifyBookingOwnership($bk, $b);

        if ($response === 'ACCEPTED') {
            $proposedFare = floatval($bk['proposed_fare'] ?? 0);
            if ($proposedFare <= 0) jsonResponse(['error' => 'No pending fare proposal'], 400);
            $conn = db();
            $stmt = $conn->prepare("UPDATE app_bookings SET total_fare = ?, fare_proposal_status = 'ACCEPTED' WHERE id = ?");
            $stmt->bind_param('di', $proposedFare, $booking_id);
            $stmt->execute();
            $message = "Fare accepted! Updated to ₹$proposedFare";
        } else {
            dbExec("UPDATE app_bookings SET fare_proposal_status = 'DECLINED' WHERE id = ?", 'i', [$booking_id]);
            $message = "Fare proposal declined";
        }

        // Notify admin/team
        $ref = $bk['booking_ref'];
        $custName = $bk['customer_name'] ?: 'Passenger';
        $adminTokens = getAdminFCMTokens();
        if (!empty($adminTokens)) {
            $notifTitle = $response === 'ACCEPTED' ? "✅ Fare Accepted (#$ref)" : "❌ Fare Declined (#$ref)";
            $notifBody = $response === 'ACCEPTED' ? "$custName accepted ₹{$bk['proposed_fare']} for ride #$ref" : "$custName declined fare proposal for ride #$ref";
            sendFCMPush($adminTokens, $notifTitle, $notifBody, [
                'booking_id' => strval($booking_id),
                'booking_ref' => strval($ref),
                'type' => $response === 'ACCEPTED' ? 'FARE_ACCEPTED' : 'FARE_DECLINED',
                'event' => $response === 'ACCEPTED' ? 'FARE_ACCEPTED' : 'FARE_DECLINED',
                'url' => 'https://pavancab.com/app/dashboard/index.php'
            ]);
        }

        jsonResponse(['success' => true, 'message' => $message]);
    }
}

// Router
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = '/' . ltrim(preg_replace('#^.*?(?:rides|api_rides)\.php#i', '', $path), '/');
if ($action) {
    if ($action === 'boost-fare' || $action === '/boost-fare') $path = '/user/boost-fare';
    elseif ($action === 'cancel-booking' || $action === '/cancel-booking') $path = '/user/cancel-booking';
    elseif ($action === 'complete-ride' || $action === '/complete-ride') $path = '/user/complete-ride';
    elseif ($action === 'rate-ride' || $action === '/rate-ride' || $action === 'rate_ride' || $action === '/rate_ride') $path = '/user/rate-ride';
    elseif ($action === 'user-bookings' || $action === '/user-bookings') $path = '/user-bookings';
    elseif ($action === 'respond-fare' || $action === '/respond-fare') $path = '/user/respond-fare';
    else $path = '/' . ltrim($action, '/');
}
handleRidesModule($method, $path, $b);
