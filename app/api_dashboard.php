<?php
/**
 * PAVANCAB GOA TAXI - Advanced Admin & Team Dispatch Tower API Engine
 * Path: app/api_dashboard.php (v2.1.0)
 * 
 * --------------------------------------------------------------------------
 * EASY-TO-READ CODE DOCUMENTATION FOR BEGINNERS:
 * 1. This file powers the Admin & Team Dispatch Tower control panel.
 * 2. It fetches live stats, user details, active driver locations, and booking rosters.
 * 3. It triggers Custom Push Notifications to individual devices or broadcast groups.
 * --------------------------------------------------------------------------
 */

// Step 1: Include Database connection and helper functions
require_once __DIR__ . '/db.php';

// Force no-cache on all API responses (bypass LiteSpeed/OPcache)
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('X-Accel-Cache-Control: no-store');
    header('X-LiteSpeed-Cache-Control: no-cache, private, no-store');
}

// Step 2: Extract HTTP request method, action, and request body parameters
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? $_GET['action'] ?? '';
$b      = getBody();

// Step 3: Core Dashboard Module Router Function
function handleDashboardModule($method, $path, $b) {
    requireAdminAuth();
    // 1. Get all bookings with complete relations (supports pagination, date range filter)
    if ($method === 'GET' && ($path === '/admin/bookings' || $path === '/bookings')) {
        $statusFilter = trim($_GET['status'] ?? '');
        $searchQuery = trim($_GET['search'] ?? '');
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(200, max(10, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        
        $conditions = [];
        $countConditions = [];
        $types = '';
        $countTypes = '';
        $params = [];
        $countParams = [];
        
        if ($statusFilter) {
            $sf = strtoupper($statusFilter);
            if ($sf === 'PENDING') {
                $cond = "(b.status IS NULL OR TRIM(b.status) = '' OR (UPPER(b.status) NOT IN ('COMPLETED','IN_TRANSIT','ON_TRIP','ARRIVED','CONFIRMED','ASSIGNED','ACCEPTED','DRIVER_ASSIGNED','CANCELLED','CANCELLED_BY_USER','CANCELLED_BY_ADMIN','REJECTED') AND UPPER(b.status) NOT LIKE 'CANCEL%'))";
                $conditions[] = $cond;
                $countConditions[] = $cond;
            } else {
                $conditions[] = "UPPER(b.status) = ?";
                $countConditions[] = "UPPER(b.status) = ?";
                $types .= 's';
                $countTypes .= 's';
                $params[] = $sf;
                $countParams[] = $sf;
            }
        }
        if ($searchQuery) {
            $searchWild = "%{$searchQuery}%";
            $cond = "(b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.booking_ref LIKE ?)";
            $conditions[] = $cond;
            $countConditions[] = $cond;
            $types .= 'sss';
            $countTypes .= 'sss';
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
            $countParams[] = $searchWild;
            $countParams[] = $searchWild;
            $countParams[] = $searchWild;
        }
        if ($startDate) {
            $cond = "(b.pickup_date >= ? OR DATE(b.created_at) >= ?)";
            $conditions[] = $cond;
            $countConditions[] = $cond;
            $types .= 'ss';
            $countTypes .= 'ss';
            $params[] = $startDate;
            $params[] = $startDate;
            $countParams[] = $startDate;
            $countParams[] = $startDate;
        }
        if ($endDate) {
            $cond = "(b.pickup_date <= ? OR DATE(b.created_at) <= ?)";
            $conditions[] = $cond;
            $countConditions[] = $cond;
            $types .= 'ss';
            $countTypes .= 'ss';
            $params[] = $endDate;
            $params[] = $endDate;
            $countParams[] = $endDate;
            $countParams[] = $endDate;
        }
        
        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = " WHERE " . implode(" AND ", $conditions);
        }
        $countWhereClause = '';
        if (!empty($countConditions)) {
            $countWhereClause = " WHERE " . implode(" AND ", $countConditions);
        }
        
        $conn = db();
        $totalQuery = "SELECT COUNT(*) as c FROM app_bookings b" . $countWhereClause;
        if ($countTypes) {
            $cstmt = $conn->prepare($totalQuery);
            if ($cstmt) { $cstmt->bind_param($countTypes, ...$countParams); $cstmt->execute(); $total = intval($cstmt->get_result()->fetch_assoc()['c']); }
            else { $total = 0; }
        } else {
            $total = intval($conn->query($totalQuery)->fetch_assoc()['c']);
        }
        
        $sql = "SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
                COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, '') as vehicle_number,
                d.name as assigned_driver_name, d.phone as assigned_driver_phone, d.car_model, d.plate_number 
                FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id"
                . $whereClause . " ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
        
        if ($types) {
            $rows = dbRows($sql, $types, $params);
        } else {
            $rows = dbRows($sql);
        }
        jsonResponse(['bookings' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => ceil($total / $limit)]);
    }

    // 2. Get dispatch stats (using SQL aggregates, no full table load)
    if ($method === 'GET' && ($path === '/admin/stats' || $path === '/stats')) {
        $conn = db();
        $total     = intval($conn->query('SELECT COUNT(*) as c FROM app_bookings')->fetch_assoc()['c']);
        // Match classifyRideStatus() logic: cancelled/COMPLETED/IN_TRANSIT/CONFIRMED first, everything else = PENDING
        $completed = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) = 'COMPLETED'")->fetch_assoc()['c']);
        $inTransit = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) IN ('IN_TRANSIT','ON_TRIP','ARRIVED')")->fetch_assoc()['c']);
        $assigned  = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) IN ('CONFIRMED','ASSIGNED','ACCEPTED','DRIVER_ASSIGNED')")->fetch_assoc()['c']);
        $cancelledTotal = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) LIKE 'CANCEL%' OR UPPER(status) = 'REJECTED'")->fetch_assoc()['c']);
        $cancelledUser  = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) = 'CANCELLED_BY_USER'")->fetch_assoc()['c']);
        $cancelledAdmin = intval($conn->query("SELECT COUNT(*) as c FROM app_bookings WHERE UPPER(status) IN ('CANCELLED_BY_ADMIN','CANCELLED')")->fetch_assoc()['c']);
        // Pending = everything not classified (matches classifyRideStatus() fallback)
        $pending   = $total - $completed - $inTransit - $assigned - $cancelledTotal;
        if ($pending < 0) $pending = 0;
        $revenue   = floatval($conn->query("SELECT IFNULL(SUM(total_fare),0) as t FROM app_bookings WHERE UPPER(status) = 'COMPLETED'")->fetch_assoc()['t']);
        $driversAvail = intval($conn->query("SELECT COUNT(*) as c FROM app_drivers WHERE LOWER(status) = 'available'")->fetch_assoc()['c']);
        $driversTotal = intval($conn->query("SELECT COUNT(*) as c FROM app_drivers")->fetch_assoc()['c']);
        $active = $assigned + $inTransit;

        jsonResponse([
            'total' => $total, 
            'pending' => $pending, 
            'assigned' => $assigned,
            'inTransit' => $inTransit,
            'active' => $active, 
            'completed' => $completed,
            'cancelledUser' => $cancelledUser,
            'cancelledAdmin' => $cancelledAdmin,
            'cancelledTotal' => $cancelledTotal,
            'totalRevenue' => floatval($revenue), 
            'availableDrivers' => intval($driversAvail),
            'totalDrivers' => intval($driversTotal)
        ]);
    }

    // 2b. Register / Sync FCM Token for Dashboard Dispatchers
    if ($method === 'POST' && ($path === '/admin/save_fcm_token' || $path === '/save_fcm_token' || $path === '/fcm_token')) {
        $fcmToken  = trim($b['fcm_token'] ?? $b['token'] ?? '');
        $userEmail = trim($b['user_email'] ?? $b['email'] ?? ($_SESSION['user']['email'] ?? SUPER_ADMIN_EMAIL));
        $userPhone = cleanPhoneDigits($b['user_mobile'] ?? $b['phone'] ?? ($_SESSION['user']['mobile'] ?? SUPER_ADMIN_PHONE));
        $userRole  = trim($b['role'] ?? ($_SESSION['user']['role'] ?? 'admin'));

        if (!$fcmToken) jsonResponse(['success' => false, 'message' => 'No token provided'], 200);

        $conn = db();
        $stmt = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), updated_at = NOW()");
        if ($stmt) {
            $stmt->bind_param('sss', $fcmToken, $userEmail, $userPhone);
            $stmt->execute();
        }

        if ($userPhone) {
            $clean10 = substr($userPhone, -10);
            $stmt2 = $conn->prepare("UPDATE app_users SET fcm_token = ?, role = IFNULL(role, ?) WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ?");
            if ($stmt2) {
                $stmt2->bind_param('sss', $fcmToken, $userRole, $clean10);
                $stmt2->execute();
            }
            $stmt3 = $conn->prepare("UPDATE app_team_members SET fcm_token = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = ?");
            if ($stmt3) {
                $stmt3->bind_param('ss', $fcmToken, $clean10);
                $stmt3->execute();
            }
        }

        jsonResponse(['success' => true, 'message' => 'Dashboard FCM token registered successfully']);
    }

    // 3. Assign / Re-assign Driver
    if ($method === 'POST' && ($path === '/admin/assign-driver' || $path === '/assign-driver')) {
        $booking_id    = intval($b['booking_id'] ?? $_POST['booking_id'] ?? 0);
        $driver_id_req = isset($b['driver_id']) ? intval($b['driver_id']) : 0;
        $driver_name   = trim($b['driver_name'] ?? $_POST['driver_name'] ?? '');
        $driver_phone  = trim($b['driver_phone'] ?? $_POST['driver_phone'] ?? '');
        $vehicle_no    = trim($b['vehicle_number'] ?? $_POST['vehicle_number'] ?? '');

        if (!$booking_id) jsonResponse(['error' => 'booking_id is required'], 400);

        $conn = db();
        $finalDriverId = $driver_id_req > 0 ? $driver_id_req : null;

        // If only driver_id provided, look up name/phone/plate from app_drivers
        if ($finalDriverId && (!$driver_name || !$driver_phone)) {
            $driverRow = dbRows("SELECT name, phone, car_model, plate_number FROM app_drivers WHERE id = ?", 'i', [$finalDriverId]);
            if (!empty($driverRow)) {
                if (!$driver_name) $driver_name = $driverRow[0]['name'];
                if (!$driver_phone) $driver_phone = $driverRow[0]['phone'];
                if (!$vehicle_no) $vehicle_no = $driverRow[0]['plate_number'] ?? '';
                dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$finalDriverId]);
            }
        }

        if (!$driver_name || !$driver_phone) jsonResponse(['error' => 'Driver name and phone number are required'], 400);

        if (!$vehicle_no) $vehicle_no = '';

        $cleanP = preg_replace('/\D/', '', $driver_phone);
        $clean10 = substr($cleanP, -10);

        if ($clean10) {
            $stmtFind = $conn->prepare("SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?");
            $stmtFind->bind_param('s', $clean10);
            $stmtFind->execute();
            $r = $stmtFind->get_result();
            if ($r && $row = $r->fetch_assoc()) {
                $finalDriverId = intval($row['id']);
                $stmtUpd = $conn->prepare("UPDATE app_drivers SET name = ?, plate_number = ?, status = 'on_trip' WHERE id = ?");
                $stmtUpd->bind_param('ssi', $driver_name, $vehicle_no, $finalDriverId);
                $stmtUpd->execute();
            } else {
                $stmtIns = $conn->prepare("INSERT INTO app_drivers (name, phone, car_model, plate_number, status) VALUES (?, ?, 'Goa Cab', ?, 'on_trip')");
                $stmtIns->bind_param('sss', $driver_name, $driver_phone, $vehicle_no);
                $stmtIns->execute();
                $finalDriverId = intval($conn->insert_id);
            }
        }

        $stmt = $conn->prepare("UPDATE app_bookings SET status = 'CONFIRMED', driver_id = ?, driver_name = ?, driver_phone = ?, vehicle_number = ?, driver_decision = 'ACCEPTED' WHERE id = ?");
        $stmt->bind_param('isssi', $finalDriverId, $driver_name, $driver_phone, $vehicle_no, $booking_id);
        $stmt->execute();

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found after update'], 404);
        $bk = $rows[0];

        $indianDT = formatIndianDateTime($bk['pickup_date'], $bk['pickup_time']);

        $textCustomer = "🚕 *PAVANCAB GOA RIDE DISPATCHED!*\n\nRef: #{$bk['booking_ref']}\nCab: {$bk['cab_type']} ($vehicle_no)\nDriver: $driver_name ($driver_phone)\nPickup: {$bk['pickup_location']}\nDrop: {$bk['drop_location']}\nDate & Time: $indianDT\nTotal Fare: ₹{$bk['total_fare']} (Fixed)\n\nYour driver is en route. Have a wonderful ride!";

        $driverSubOffer = '';
        if ($finalDriverId) {
            $driverNow = date('Y-m-d');
            $driverSub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$finalDriverId, $driverNow]);
            if (empty($driverSub)) {
                $dCfg = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount','driver_commission_per_ride')");
                $dCfgMap = [];
                foreach ($dCfg as $row) $dCfgMap[$row['setting_key']] = $row['setting_value'];
                $dSubAmt = intval($dCfgMap['driver_subscription_amount'] ?? 1000);
                $dCommAmt = intval($dCfgMap['driver_commission_per_ride'] ?? 200);
                $driverSubOffer = "\n\n💡 *SUBSCRIPTION OFFER*\nPay ₹$dSubAmt/month for unlimited rides with zero commission! Or pay ₹$dCommAmt per ride commission after each trip.\nOpen Driver App to subscribe.";
            }
        }
        $textDriver   = "🚕 *PAVANCAB GOA DRIVER DISPATCH ORDER*\n\nRef: #{$bk['booking_ref']}\nCab Tier: {$bk['cab_type']} ($vehicle_no)\nPassenger: {$bk['customer_name']} ({$bk['customer_phone']})\nPickup Location: {$bk['pickup_location']}\nDrop Destination: {$bk['drop_location']}\nPickup Time: $indianDT\nTotal Fare: ₹{$bk['total_fare']}\n\nYou are assigned to this ride by PAVANCAB Dispatch Tower." . $driverSubOffer;

        @sendMetaWhatsApp($bk['customer_phone'], $textCustomer);
        @sendMetaWhatsApp($driver_phone, $textDriver);

        // FCM Broadcast to all 3 parties (Passenger, Driver, Admin/Team)
        broadcastRideLifecycleFCM('DRIVER_ASSIGNED', $booking_id);

        $waDriverLink = 'https://wa.me/' . cleanPhoneDigits($driver_phone) . '?text=' . rawurlencode($textDriver);
        $waCustomerLink = 'https://wa.me/' . cleanPhoneDigits($bk['customer_phone']) . '?text=' . rawurlencode($textCustomer);

        jsonResponse([
            'success' => true, 
            'message' => "Driver $driver_name successfully assigned & dispatched!", 
            'booking' => $bk, 
            'wa_driver_link' => $waDriverLink,
            'wa_customer_link' => $waCustomerLink
        ]);
    }

    // 4. Update Status Lifecycle
    if ($method === 'POST' && ($path === '/admin/update-status' || $path === '/update-status')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        $status = trim($b['status'] ?? '');
        if (!$booking_id || !$status) jsonResponse(['error' => 'booking_id and status required'], 400);

        $finalStatus = ($status === 'CANCELLED') ? 'CANCELLED_BY_ADMIN' : $status;
        dbExec('UPDATE app_bookings SET status = ? WHERE id = ?', 'si', [$finalStatus, $booking_id]);

        if ($finalStatus === 'PENDING') {
            $rows = dbRows('SELECT driver_id, driver_phone FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
            $prevDriverPhone = $rows[0]['driver_phone'] ?? '';
            if (!empty($rows) && $rows[0]['driver_id']) {
                dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$rows[0]['driver_id']]);
            }
            dbExec("UPDATE app_bookings SET driver_id = NULL, driver_name = NULL, driver_phone = NULL, driver_decision = 'NONE' WHERE id = ?", 'i', [$booking_id]);
            broadcastRideLifecycleFCM('RIDE_RESET', $booking_id, ['prev_driver_phone' => $prevDriverPhone]);
        } elseif (strpos(strtoupper($finalStatus), 'CANCEL') === 0) {
            $rows = dbRows('SELECT driver_id FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
            if (!empty($rows) && $rows[0]['driver_id']) {
                dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$rows[0]['driver_id']]);
            }
            dbExec("UPDATE app_bookings SET driver_id = NULL, driver_name = NULL, driver_phone = NULL, driver_decision = 'NONE' WHERE id = ?", 'i', [$booking_id]);
            broadcastRideLifecycleFCM('CANCELLED_BY_ADMIN', $booking_id);
        } elseif ($finalStatus === 'COMPLETED') {
            $rows = dbRows('SELECT driver_id FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
            if (!empty($rows) && $rows[0]['driver_id']) {
                dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$rows[0]['driver_id']]);
            }
            broadcastRideLifecycleFCM('RIDE_COMPLETED', $booking_id);
        } elseif ($finalStatus === 'IN_TRANSIT') {
            $rows = dbRows('SELECT driver_id FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
            if (!empty($rows) && $rows[0]['driver_id']) {
                dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$rows[0]['driver_id']]);
            }
            broadcastRideLifecycleFCM('RIDE_STARTED', $booking_id);
        } elseif ($finalStatus === 'CONFIRMED') {
            $rows = dbRows('SELECT driver_id FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
            if (!empty($rows) && $rows[0]['driver_id']) {
                dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$rows[0]['driver_id']]);
            }
            broadcastRideLifecycleFCM('DRIVER_ASSIGNED', $booking_id);
        }

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($updated)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $updated[0];

        jsonResponse(['success' => true, 'message' => "Status updated to $finalStatus", 'booking' => $bk]);
    }

    // 5. Adjust / Boost Fare Directly from Dashboard (Admin Surge / Boost Control)
    if ($method === 'POST' && ($path === '/admin/edit-fare' || $path === '/edit-fare' || $path === '/boost-fare')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        $new_fare   = floatval($b['new_fare'] ?? 0);
        $boost_add  = floatval($b['boost_amount'] ?? 0);
        $reason     = trim($b['reason'] ?? 'Admin / Dispatch adjustment');

        if (!$booking_id) jsonResponse(['error' => 'booking_id required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];

        $currentFare = floatval($bk['total_fare'] ?? 0);
        $finalFare = $new_fare > 0 ? $new_fare : ($currentFare + $boost_add);

        if ($finalFare <= 0) jsonResponse(['error' => 'Valid fare amount required'], 400);

        $timestamp = date('h:i A');
        $noteEntry = "\n[FARE ADJUSTMENT] Updated to Rs.$finalFare ($reason) at $timestamp";

        $conn = db();
        $stmt = $conn->prepare("UPDATE app_bookings SET total_fare = ?, special_notes = CONCAT(IFNULL(special_notes, ''), ?) WHERE id = ?");
        $stmt->bind_param('dsi', $finalFare, $noteEntry, $booking_id);
        $stmt->execute();

        broadcastRideLifecycleFCM('FARE_UPDATED', $booking_id, ['reason' => $reason]);
        
        $textMsg = "🚕 *PAVANCAB FARE UPDATE*\n\nRide Ref: #{$bk['booking_ref']}\nUpdated Total Fare: ₹$finalFare\nNote: $reason\n\nYour driver fare has been updated.";
        @sendMetaWhatsApp($bk['customer_phone'], $textMsg);
        if ($bk['driver_phone']) @sendMetaWhatsApp($bk['driver_phone'], $textMsg);

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        jsonResponse(['success' => true, 'message' => "Fare updated to Rs.$finalFare successfully!", 'booking' => $updated[0]]);
    }

    // 5b. Propose Fare (admin suggests fare, user must accept/decline)
    if ($method === 'POST' && ($path === '/admin/propose-fare' || $path === '/propose-fare')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        $proposed_fare = floatval($b['proposed_fare'] ?? 0);
        $reason = trim($b['reason'] ?? 'Driver asking minimum fare');
        if (!$booking_id || $proposed_fare <= 0) jsonResponse(['error' => 'booking_id and proposed_fare required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];
        $currentFare = floatval($bk['total_fare'] ?? 0);
        if ($proposed_fare <= $currentFare) jsonResponse(['error' => 'Proposed fare must be higher than current fare'], 400);

        $proposedBy = determineUserRole(trim($b['proposed_by'] ?? ''));
        $conn = db();
        $stmt = $conn->prepare("UPDATE app_bookings SET proposed_fare = ?, fare_proposal_status = 'PENDING', fare_proposed_by = ?, fare_proposal_reason = ? WHERE id = ?");
        $stmt->bind_param('dssi', $proposed_fare, $proposedBy, $reason, $booking_id);
        $stmt->execute();

        // FCM to customer with accept/decline
        $ref = $bk['booking_ref'];
        $custName = $bk['customer_name'] ?: 'Passenger';
        $custPhone = $bk['customer_phone'] ?: '';
        $custEmail = $bk['user_email'] ?: '';
        $pickup = $bk['pickup_location'] ?: '';
        $drop = $bk['drop_location'] ?: '';
        $cabType = $bk['cab_type'] ?: 'Sedan';

        $fcmData = [
            'booking_id' => strval($booking_id),
            'booking_ref' => strval($ref),
            'type' => 'FARE_PROPOSED',
            'event' => 'FARE_PROPOSED',
            'proposed_fare' => strval($proposed_fare),
            'current_fare' => strval($currentFare),
            'reason' => $reason,
            'title' => "💰 Driver Asking ₹$proposed_fare for Ride #$ref",
            'body' => "Driver is asking minimum ₹$proposed_fare for your $cabType ride from $pickup to $drop. Current fare: ₹$currentFare. Do you accept?",
            'url' => 'https://pavancab.com/app/'
        ];

        $tokens = [];
        if (!empty($custEmail)) $tokens = array_merge($tokens, getFCMTokensByEmail($custEmail));
        if (!empty($custPhone)) $tokens = array_merge($tokens, getFCMTokensByPhone($custPhone));
        $tokens = array_values(array_unique(array_map('trim', array_filter($tokens))));
        if (!empty($tokens)) sendFCMPush($tokens, "💰 Driver Asking ₹$proposed_fare for Ride #$ref", "Driver is asking minimum ₹$proposed_fare for your $cabType ride from $pickup to $drop. Current fare: ₹$currentFare. Do you accept?", $fcmData);

        // WA to customer
        if ($custPhone) {
            @sendMetaWhatsApp($custPhone, "💰 *Fare Update Request*\n\nHi *$custName*,\n\nFor your ride *#$ref* ($cabType: $pickup → $drop):\n\nDriver asking minimum: *₹$proposed_fare*\nCurrent fare: ₹$currentFare\nReason: $reason\n\nPlease confirm in the PavanCab app to accept this fare.");
        }

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        jsonResponse(['success' => true, 'message' => "Fare proposal of ₹$proposed_fare sent to customer!", 'booking' => $updated[0]]);
    }

    // 5c. Customer Respond to Fare Proposal (accept/decline)
    if ($method === 'POST' && ($path === '/admin/respond-fare' || $path === '/respond-fare')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        $response = strtoupper(trim($b['response'] ?? ''));
        if (!$booking_id || !in_array($response, ['ACCEPTED', 'DECLINED'])) jsonResponse(['error' => 'booking_id and response (ACCEPTED/DECLINED) required'], 400);

        $rows = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        $bk = $rows[0];

        if ($response === 'ACCEPTED') {
            $proposedFare = floatval($bk['proposed_fare'] ?? 0);
            if ($proposedFare <= 0) jsonResponse(['error' => 'No pending fare proposal'], 400);
            $conn = db();
            $stmt = $conn->prepare("UPDATE app_bookings SET total_fare = ?, fare_proposal_status = 'ACCEPTED' WHERE id = ?");
            $stmt->bind_param('di', $proposedFare, $booking_id);
            $stmt->execute();
            $message = "Fare accepted! Updated to ₹$proposedFare";
        } else {
            $conn = db();
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

    // 5d. Send Personal Push Notification to a User
    if ($method === 'POST' && ($path === '/admin/send-personal-push' || $path === '/send-personal-push')) {
        $user_phone = trim($b['user_phone'] ?? '');
        $user_email = trim($b['user_email'] ?? '');
        $title = trim($b['title'] ?? '');
        $body = trim($b['body'] ?? '');
        if (!$title || !$body) jsonResponse(['error' => 'title and body required'], 400);

        $tokens = [];
        if (!empty($user_email)) $tokens = array_merge($tokens, getFCMTokensByEmail($user_email));
        if (!empty($user_phone)) $tokens = array_merge($tokens, getFCMTokensByPhone($user_phone));
        $tokens = array_values(array_unique(array_map('trim', array_filter($tokens))));

        if (empty($tokens)) jsonResponse(['error' => 'No active device found for this user'], 404);

        sendFCMPush($tokens, $title, $body, [
            'type' => 'PERSONAL_MESSAGE',
            'event' => 'PERSONAL_MESSAGE',
            'title' => $title,
            'body' => $body,
            'url' => 'https://pavancab.com/app/'
        ]);

        jsonResponse(['success' => true, 'message' => 'Notification sent to ' . count($tokens) . ' device(s)']);
    }

    // 5e. Bulk FCM Push to Multiple Users
    if ($method === 'POST' && ($path === '/admin/bulk-push' || $path === '/bulk-push')) {
        $tokens = $b['tokens'] ?? [];
        $title = $b['title'] ?? '';
        $body = $b['body'] ?? '';
        if (empty($tokens) || !$title || !$body) jsonResponse(['error' => 'tokens, title, and body required'], 400);
        if (!is_array($tokens)) $tokens = [$tokens];
        $cleanTokens = array_filter(array_unique(array_map('trim', $tokens)));
        if (empty($cleanTokens)) jsonResponse(['error' => 'No valid FCM tokens'], 400);
        sendFCMPush(array_values($cleanTokens), $title, $body, ['type' => 'ADMIN_BROADCAST', 'event' => 'ADMIN_BROADCAST']);
        jsonResponse(['success' => true, 'message' => "Push sent to " . count($cleanTokens) . " devices"]);
    }

    // 5f. Bulk WhatsApp to Multiple Users
    if ($method === 'POST' && ($path === '/admin/bulk-whatsapp' || $path === '/bulk-whatsapp')) {
        $phones = $b['phones'] ?? [];
        $message = $b['message'] ?? '';
        if (empty($phones) || !$message) jsonResponse(['error' => 'phones and message required'], 400);
        if (!is_array($phones)) $phones = [$phones];
        $cleanPhones = array_filter(array_unique(array_map(function($p) { return substr(preg_replace('/\D/', '', $p), -10); }, $phones)));
        if (empty($cleanPhones)) jsonResponse(['error' => 'No valid phone numbers'], 400);
        sendMetaWhatsAppParallel(array_values($cleanPhones), $message);
        jsonResponse(['success' => true, 'message' => "WhatsApp sent to " . count($cleanPhones) . " users"]);
    }

    // 5g. Get Bulk FCM Tokens for Users
    if ($method === 'POST' && ($path === '/admin/bulk-tokens' || $path === '/bulk-tokens')) {
        $phones = $b['phones'] ?? [];
        $emails = $b['emails'] ?? [];
        if (!is_array($phones)) $phones = [$phones];
        if (!is_array($emails)) $emails = [$emails];
        $conn = db();
        $tokens = [];
        foreach ($phones as $phone) {
            $clean = '%' . substr(preg_replace('/\D/', '', $phone), -10) . '%';
            $r = $conn->query("SELECT fcm_token FROM app_fcm_tokens WHERE user_mobile LIKE '$clean' AND fcm_token != ''");
            if ($r) while ($row = $r->fetch_assoc()) { if ($row['fcm_token']) $tokens[] = $row['fcm_token']; }
        }
        foreach ($emails as $email) {
            $clean = $conn->real_escape_string(strtolower(trim($email)));
            $r = $conn->query("SELECT fcm_token FROM app_fcm_tokens WHERE LOWER(user_email) = '$clean' AND fcm_token != ''");
            if ($r) while ($row = $r->fetch_assoc()) { if ($row['fcm_token']) $tokens[] = $row['fcm_token']; }
        }
        jsonResponse(['tokens' => array_values(array_unique($tokens))]);
    }

    // 6. Edit Full Booking Details
    if ($method === 'POST' && ($path === '/admin/edit-booking' || $path === '/edit-booking')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        if (!$booking_id) jsonResponse(['error' => 'booking_id required'], 400);

        $name   = trim($b['customer_name'] ?? '');
        $phone  = trim($b['customer_phone'] ?? '');
        $pickup = trim($b['pickup_location'] ?? '');
        $drop   = trim($b['drop_location'] ?? '');
        $date   = trim($b['pickup_date'] ?? date('Y-m-d'));
        $time   = trim($b['pickup_time'] ?? date('H:i'));
        $cab    = trim($b['cab_type'] ?? 'Sedan');
        $fare   = floatval($b['total_fare'] ?? 0);
        $notes  = trim($b['special_notes'] ?? '');

        $conn = db();
        $stmt = $conn->prepare("UPDATE app_bookings SET customer_name = ?, customer_phone = ?, pickup_location = ?, drop_location = ?, pickup_date = ?, pickup_time = ?, cab_type = ?, total_fare = ?, special_notes = ? WHERE id = ?");
        $stmt->bind_param('sssssssdsi', $name, $phone, $pickup, $drop, $date, $time, $cab, $fare, $notes, $booking_id);
        $stmt->execute();

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        jsonResponse(['success' => true, 'message' => 'Booking details updated successfully!', 'booking' => $updated[0]]);
    }

    // 7. Manual Phone / Walk-in Booking Creation
    if ($method === 'POST' && ($path === '/admin/create-booking' || $path === '/create-booking')) {
        $name   = trim($b['customer_name'] ?? 'Walk-in Passenger');
        $phone  = trim($b['customer_phone'] ?? '');
        $pickup = trim($b['pickup_location'] ?? '');
        $drop   = trim($b['drop_location'] ?? 'Goa');
        $date   = trim($b['pickup_date'] ?? date('Y-m-d'));
        $time   = trim($b['pickup_time'] ?? date('H:i'));
        $cab    = trim($b['cab_type'] ?? 'Sedan');
        $fare   = floatval($b['total_fare'] ?? 0);
        $trip_type = trim($b['trip_type'] ?? 'one_way');
        $notes  = trim($b['special_notes'] ?? 'Manual Phone Booking by Dispatch');
        $booked_by_name = trim($b['booked_by_name'] ?? '');
        $booked_by_phone = trim($b['booked_by_phone'] ?? '');

        if (!$phone || !$pickup || $fare <= 0) {
            jsonResponse(['error' => 'Passenger Phone, Pickup Location, and Total Fare are required'], 400);
        }

        $booking_ref = 'GTA-' . date('ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $conn = db();
        $stmt = $conn->prepare("INSERT INTO app_bookings (booking_ref, user_email, customer_name, customer_phone, trip_type, pickup_location, drop_location, pickup_date, pickup_time, cab_type, total_fare, special_notes, status, booking_source, booked_by_phone, booked_by_name) VALUES (?, 'admin@pavancab.com', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', 'phone', ?, ?)");
        $stmt->bind_param('ssssssssssdss', $booking_ref, $name, $phone, $trip_type, $pickup, $drop, $date, $time, $cab, $fare, $notes, $booked_by_phone, $booked_by_name);
        $stmt->execute();
        $newId = $conn->insert_id;

        // Notify admin/team (FCM to all OTHER devices + WhatsApp)
        notifyAdminAndTeamNewBooking($newId, $booking_ref, $name, $phone, $pickup, $drop, $date, $time, $cab, $fare, $booked_by_phone, $booked_by_name);

        // WhatsApp confirmation to customer (phone booking style)
        $indianDT = formatIndianDateTime($date, $time);
        $custWA = "🎉 *PAVANCAB Booking Placed!*\n\nHi *$name*,\n\nYour booking *#$booking_ref* has been placed successfully by our dispatch team.\n\n📍 Pickup: $pickup\n📍 Drop: $drop\n🚗 Cab: $cab\n💰 Fare: ₹$fare\n📅 Date: $indianDT\n\n📱 To track your booking, download *Pavancab App* from Play Store and login with your WhatsApp number to see live booking status.\n\nThank you for choosing PAVANCAB! 🙏";
        @sendMetaWhatsApp($phone, $custWA);

        $updated = dbRows('SELECT * FROM app_bookings WHERE id = ?', 'i', [$newId]);
        jsonResponse(['success' => true, 'message' => "Manual booking #$booking_ref created!", 'booking' => $updated[0]]);
    }

    // 8. Driver Fleet Management
    if ($method === 'GET' && ($path === '/admin/drivers' || $path === '/drivers')) {
        $statusFilter = trim($_GET['status'] ?? $b['status'] ?? '');
        if ($statusFilter) {
            $drivers = dbRows("SELECT d.*, 
                (SELECT COUNT(*) FROM app_bookings WHERE driver_id = d.id AND status = 'COMPLETED') as completed_trips 
                FROM app_drivers d WHERE LOWER(d.status) = ? ORDER BY d.name ASC", 's', [strtolower($statusFilter)]);
        } else {
            $drivers = dbRows("SELECT d.*, 
                (SELECT COUNT(*) FROM app_bookings WHERE driver_id = d.id AND status = 'COMPLETED') as completed_trips 
                FROM app_drivers d ORDER BY d.name ASC");
        }
        jsonResponse($drivers);
    }

    if ($method === 'POST' && ($path === '/admin/add-driver' || $path === '/add-driver')) {
        $name  = trim($b['name'] ?? '');
        $phone = trim($b['phone'] ?? '');
        $model = trim($b['car_model'] ?? 'Goa Cab');
        $plate = trim($b['plate_number'] ?? '');
        if (!$name || !$phone) jsonResponse(['error' => 'Driver name and phone are required'], 400);

        $conn = db();
        $stmt = $conn->prepare("INSERT INTO app_drivers (name, phone, car_model, plate_number, status) VALUES (?, ?, ?, ?, 'available')");
        $stmt->bind_param('ssss', $name, $phone, $model, $plate);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => "Driver $name added to fleet!"]);
    }

    if ($method === 'POST' && ($path === '/admin/toggle-driver-status' || $path === '/toggle-driver-status')) {
        $driver_id = intval($b['driver_id'] ?? 0);
        if (!$driver_id) jsonResponse(['error' => 'driver_id required'], 400);

        $conn = db();
        $row = dbRows("SELECT status FROM app_drivers WHERE id = ?", 'i', [$driver_id]);
        $currentStatus = !empty($row) ? strtolower($row[0]['status']) : 'available';
        $newStatus = ($currentStatus === 'available') ? 'inactive' : 'available';

        $stmt = $conn->prepare("UPDATE app_drivers SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $driver_id);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => "Driver status toggled to $newStatus"]);
    }

    if ($method === 'POST' && ($path === '/admin/delete-driver' || $path === '/delete-driver')) {
        $driver_id = intval($b['driver_id'] ?? 0);
        if (!$driver_id) jsonResponse(['error' => 'driver_id required'], 400);
        $conn = db();
        $conn->query("DELETE FROM app_drivers WHERE id = $driver_id");
        jsonResponse(['success' => true, 'message' => 'Driver removed from fleet']);
    }

    if ($method === 'POST' && ($path === '/admin/edit-driver' || $path === '/edit-driver')) {
        $driver_id = intval($b['driver_id'] ?? 0);
        $name  = trim($b['name'] ?? '');
        $phone = trim($b['phone'] ?? '');
        $model = trim($b['car_model'] ?? '');
        $plate = trim($b['plate_number'] ?? '');
        if (!$driver_id) jsonResponse(['error' => 'driver_id required'], 400);

        $conn = db();
        $sets = []; $types = ''; $params = [];
        if ($name !== '')  { $sets[] = 'name = ?'; $types .= 's'; $params[] = $name; }
        if ($phone !== '') { $sets[] = 'phone = ?'; $types .= 's'; $params[] = $phone; }
        if ($model !== '') { $sets[] = 'car_model = ?'; $types .= 's'; $params[] = $model; }
        if ($plate !== '') { $sets[] = 'plate_number = ?'; $types .= 's'; $params[] = $plate; }
        if (empty($sets)) jsonResponse(['error' => 'No fields to update'], 400);

        $params[] = $driver_id;
        $types .= 'i';
        $sql = "UPDATE app_drivers SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) { $stmt->bind_param($types, ...$params); $stmt->execute(); }
        jsonResponse(['success' => true, 'message' => 'Driver details updated!']);
    }

    // 9. Meta WhatsApp API Credentials
    if ($method === 'GET' && ($path === '/admin/whatsapp-config' || $path === '/whatsapp-config')) {
        $token = getMetaWaToken();
        $phoneId = getMetaWaPhoneId();
        jsonResponse(['phone_id' => $phoneId, 'token_masked' => substr($token, 0, 10) . '...' . substr($token, -6)]);
    }

    if ($method === 'POST' && ($path === '/admin/whatsapp-config' || $path === '/whatsapp-config')) {
        $conn = db();
        if (!empty($b['token'])) {
            $stmtT = $conn->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('meta_wa_token', ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmtT->bind_param('ss', $b['token'], $b['token']);
            $stmtT->execute();
        }
        if (!empty($b['phone_id'])) {
            $stmtP = $conn->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('meta_wa_phone_id', ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmtP->bind_param('ss', $b['phone_id'], $b['phone_id']);
            $stmtP->execute();
        }
        jsonResponse(['success' => true, 'message' => 'WhatsApp API credentials updated!']);
    }

    // 9b. Firebase Cloud Messaging (FCM HTTP v1) Config & Diagnostic
    if ($method === 'GET' && ($path === '/admin/fcm-config' || $path === '/fcm-config')) {
        $sa = getFirebaseServiceAccount();
        $conn = db();
        
        $tokenCount = 0;
        $r1 = $conn->query("SELECT COUNT(*) as c FROM app_fcm_tokens");
        if ($r1 && $row = $r1->fetch_assoc()) $tokenCount = intval($row['c']);

        $adminTokens = getAdminFCMTokens();

        $lastStatus = null;
        $r2 = $conn->query("SELECT config_value FROM app_config WHERE config_key = 'fcm_last_status' LIMIT 1");
        if ($r2 && $row2 = $r2->fetch_assoc()) {
            $lastStatus = json_decode($row2['config_value'] ?? '', true);
        }

        // Determine source
        $source = 'none';
        $r3 = $conn->query("SELECT config_key FROM app_config WHERE config_key = 'fcm_service_account_json' AND config_value != '' LIMIT 1");
        if ($r3 && $r3->num_rows > 0) {
            $source = 'database';
        } elseif (file_exists(__DIR__ . '/firebase-service-account.json') || file_exists(__DIR__ . '/service-account.json')) {
            $source = 'file';
        }

        jsonResponse([
            'configured'            => ($sa !== null),
            'source'                => $source,
            'project_id'            => $sa['project_id'] ?? DEFAULT_FCM_PROJECT_ID,
            'client_email'          => !empty($sa['client_email']) ? (substr($sa['client_email'], 0, 8) . '...' . substr($sa['client_email'], -18)) : '',
            'vapid_key'             => defined('FCM_VAPID_KEY') ? FCM_VAPID_KEY : '',
            'total_tokens'          => $tokenCount,
            'admin_tokens_count'    => count($adminTokens),
            'last_status'           => $lastStatus
        ]);
    }

    if ($method === 'POST' && ($path === '/admin/fcm-config' || $path === '/fcm-config')) {
        $conn = db();
        $jsonStr = trim($b['service_account_json'] ?? $_POST['service_account_json'] ?? '');

        if ($jsonStr === 'CLEAR' || $jsonStr === 'DELETE') {
            $conn->query("DELETE FROM app_config WHERE config_key = 'fcm_service_account_json'");
            $conn->query("DELETE FROM app_config WHERE config_key = 'fcm_oauth_token_cache'");
            jsonResponse(['success' => true, 'message' => 'Firebase Service Account credentials removed.']);
        }

        $decoded = json_decode($jsonStr, true);
        if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            jsonResponse(['error' => 'Invalid Service Account JSON. Must contain client_email and private_key.'], 400);
        }

        $stmt = $conn->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('fcm_service_account_json', ?) ON DUPLICATE KEY UPDATE config_value = ?");
        $stmt->bind_param('ss', $jsonStr, $jsonStr);
        $stmt->execute();

        // Invalidate cached token so new credentials take effect immediately
        $conn->query("DELETE FROM app_config WHERE config_key = 'fcm_oauth_token_cache'");

        jsonResponse(['success' => true, 'message' => 'Firebase Service Account JSON saved successfully!']);
    }

    // 9c. Firebase Live Test Push
    if ($method === 'POST' && ($path === '/admin/fcm-test' || $path === '/fcm-test')) {
        $target = trim($b['target'] ?? 'admins'); // 'admins', 'my_token', or custom token
        $customToken = trim($b['token'] ?? '');
        $title = trim($b['title'] ?? '🔔 PAVANCAB Test Push Alert');
        $body  = trim($b['body'] ?? 'Live Firebase Cloud Messaging (HTTP v1) test notification triggered from Dispatch Tower at ' . date('H:i:s'));

        $tokens = [];
        if ($target === 'my_token' && $customToken) {
            $tokens = [$customToken];
        } elseif ($customToken) {
            $tokens = [$customToken];
        } else {
            $tokens = getAdminFCMTokens();
        }

        if (empty($tokens)) {
            jsonResponse(['error' => 'No active FCM tokens found for target. Please enable browser notifications on this device or log in.'], 400);
        }

        $res = sendFCMv1Push($tokens, $title, $body, [
            'type'        => 'test_push',
            'url'         => 'https://pavancab.com/app/dashboard/index.php',
            'tested_by'   => $_SESSION['user']['name'] ?? 'Admin',
            'tested_time' => date('Y-m-d H:i:s')
        ]);

        if ($res['sent'] > 0) {
            jsonResponse([
                'success' => true,
                'message' => "FCM Push sent successfully to {$res['sent']} device(s)!",
                'details' => $res
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error'   => !empty($res['errors']) ? implode('; ', $res['errors']) : 'Failed to send push notification',
                'details' => $res
            ], 400);
        }
    }

    // 10. Team Members Management
    if ($method === 'GET' && ($path === '/admin/team' || $path === '/team')) {
        try {
            $rows = dbRows("SELECT * FROM app_team_members ORDER BY id DESC");
            jsonResponse($rows);
        } catch (Exception $e) {
            jsonResponse([]);
        }
    }

    if ($method === 'POST' && ($path === '/admin/team' || $path === '/team')) {
        $name  = trim($b['member_name'] ?? $b['name'] ?? $_POST['member_name'] ?? $_POST['name'] ?? $_GET['member_name'] ?? $_GET['name'] ?? '');
        $phone = trim($b['member_phone'] ?? $b['phone'] ?? $_POST['member_phone'] ?? $_POST['phone'] ?? $_GET['member_phone'] ?? $_GET['phone'] ?? '');
        $role  = trim($b['role'] ?? $_POST['role'] ?? $_GET['role'] ?? 'team');
        // Only admins can assign admin role; team members can only create team
        if ($role === 'admin' && ($_SESSION['user']['role'] ?? '') !== 'admin') $role = 'team';
        $email = trim($b['member_email'] ?? $b['email'] ?? $_POST['member_email'] ?? $_POST['email'] ?? $_GET['member_email'] ?? $_GET['email'] ?? '');
        if (!$name || (!$phone && !$email)) jsonResponse(['error' => 'Name and either Phone or Email are required'], 400);

        try {
            $clean10 = substr(preg_replace('/\D/', '', $phone), -10);
            
            $existing = [];
            if ($clean10) {
                $existing = dbRows("SELECT id FROM app_team_members WHERE member_phone LIKE ? OR member_phone = ?", 'ss', ["%$clean10%", $phone]);
            }
            if (empty($existing) && $email) {
                $existing = dbRows("SELECT id FROM app_team_members WHERE member_email = ?", 's', [$email]);
            }

            if (!empty($existing)) {
                dbExec("UPDATE app_team_members SET member_name = ?, member_phone = ?, member_email = ?, role = ?, is_active = 1 WHERE id = ?", 'ssssi', [$name, $phone, $email, $role, $existing[0]['id']]);
            } else {
                dbExec("INSERT INTO app_team_members (member_name, member_phone, member_email, role, is_active) VALUES (?, ?, ?, ?, 1)", 'ssss', [$name, $phone, $email, $role]);
            }

            jsonResponse(['success' => true, 'message' => "$name added to dispatch team!"]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Database error adding team member: ' . $e->getMessage()], 500);
        }
    }

    if ($method === 'POST' && ($path === '/admin/remove-team' || $path === '/remove-team')) {
        $member_id = intval($b['member_id'] ?? $b['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$member_id) jsonResponse(['error' => 'id required'], 400);
        try {
            dbExec("DELETE FROM app_team_members WHERE id = ?", 'i', [$member_id]);
            jsonResponse(['success' => true, 'message' => 'Team member removed']);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // 11. Ride Issue Reports Desk
    if ($method === 'GET' && ($path === '/admin/reports' || $path === '/reports')) {
        $reports = dbRows("SELECT r.*, b.booking_ref, b.pickup_location, b.drop_location, b.driver_name, b.driver_phone, b.vehicle_number 
                           FROM app_ride_reports r 
                           LEFT JOIN app_bookings b ON r.booking_id = b.id 
                           ORDER BY r.id DESC LIMIT 100");
        jsonResponse($reports);
    }

    if ($method === 'POST' && ($path === '/admin/update-report' || $path === '/update-report' || $path === '/update_report_status')) {
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
            @sendMetaWhatsApp($rData['reporter_phone'], $msg);
        }

        jsonResponse(['success' => true, 'message' => 'Report status updated successfully']);
    }

    // 12. App Users Directory (Aggregated Customer Dossier & Online Tracking)
    // 13. Delete Booking Permanently (admin only)
    if ($method === 'POST' && ($path === '/admin/delete-booking' || $path === '/delete-booking')) {
        requireAdminAuth();
        $bookingId = intval($b['booking_id'] ?? 0);
        if (!$bookingId) jsonResponse(['error' => 'booking_id required'], 400);
        try {
            $conn = db();
            $conn->query("DELETE FROM app_bookings WHERE id = $bookingId");
            jsonResponse(['success' => true, 'message' => 'Booking permanently deleted']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Failed to delete: ' . $e->getMessage()], 500);
        }
    }

    // 14. Ban User (admin only) - prevents login
    if ($method === 'POST' && ($path === '/admin/ban-user' || $path === '/ban-user')) {
        requireAdminAuth();
        $userId = intval($b['user_id'] ?? 0);
        $ban = intval($b['ban'] ?? 1);
        if (!$userId) jsonResponse(['error' => 'user_id required'], 400);
        try {
            $conn = db();
            $conn->query("UPDATE app_users SET is_banned = $ban WHERE id = $userId");
            jsonResponse(['success' => true, 'message' => $ban ? 'User banned' : 'User unbanned']);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // 15. Delete User Permanently (admin only)
    if ($method === 'POST' && ($path === '/admin/delete-user' || $path === '/delete-user')) {
        requireAdminAuth();
        $userId = intval($b['user_id'] ?? 0);
        if (!$userId) jsonResponse(['error' => 'user_id required'], 400);
        try {
            $conn = db();
            $conn->query("DELETE FROM app_fcm_tokens WHERE user_mobile IN (SELECT mobile FROM app_users WHERE id = $userId)");
            $conn->query("DELETE FROM app_users WHERE id = $userId");
            jsonResponse(['success' => true, 'message' => 'User permanently deleted']);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // 16. Active Users Directory (date range, ride filter, admin controls)
    if ($method === 'GET' && ($path === '/admin/users' || $path === '/users')) {
        $conn = db();
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');
        
        $usersMap = [];
        $resUsers = $conn->query("SELECT id, name, mobile, email, role, fcm_token, is_online, last_active_at, device_info, created_at, IFNULL(is_banned, 0) as is_banned FROM app_users ORDER BY id DESC");
        if ($resUsers) {
            while ($u = $resUsers->fetch_assoc()) {
                $rawPhone = $u['mobile'] ?: '';
                $clean10 = substr(preg_replace('/\D/', '', $rawPhone), -10);
                $key = $clean10 ?: ('email_' . strtolower(trim($u['email'] ?? '')));
                if (!$key || $key === 'email_') $key = 'user_' . $u['id'];

                $usersMap[$key] = [
                    'user_id' => intval($u['id']),
                    'name' => $u['name'] ?: 'Goa Customer',
                    'mobile' => $u['mobile'] ?: '',
                    'clean_phone' => $clean10,
                    'email' => $u['email'] ?: '',
                    'role' => $u['role'] ?: 'user',
                    'is_online' => intval($u['is_online'] ?? 0),
                    'is_banned' => intval($u['is_banned'] ?? 0),
                    'last_active_at' => $u['last_active_at'] ?: $u['created_at'],
                    'device_info' => $u['device_info'] ?: '',
                    'created_at' => $u['created_at'],
                    'total_bookings' => 0,
                    'completed_bookings' => 0,
                    'cancelled_bookings' => 0,
                    'total_spent' => 0,
                    'fcm_tokens_count' => !empty($u['fcm_token']) ? 1 : 0,
                    'latest_booking_ref' => '',
                    'latest_booking_date' => ''
                ];
            }
        }

        // Merge from app_fcm_tokens
        $resFcm = $conn->query("SELECT fcm_token, user_email, user_mobile, is_online, last_active_at, device_info, updated_at FROM app_fcm_tokens ORDER BY updated_at DESC");
        if ($resFcm) {
            while ($f = $resFcm->fetch_assoc()) {
                $rawPhone = $f['user_mobile'] ?: '';
                $clean10 = substr(preg_replace('/\D/', '', $rawPhone), -10);
                $key = $clean10 ?: ('email_' . strtolower(trim($f['user_email'] ?? '')));
                if (!$key || $key === 'email_') continue;

                if (!isset($usersMap[$key])) {
                    $usersMap[$key] = [
                        'user_id' => 0,
                        'name' => 'Goa App User',
                        'mobile' => $f['user_mobile'] ?: '',
                        'clean_phone' => $clean10,
                        'email' => $f['user_email'] ?: '',
                        'role' => 'user',
                        'is_online' => intval($f['is_online'] ?? 0),
                        'last_active_at' => $f['last_active_at'] ?: $f['updated_at'],
                        'device_info' => $f['device_info'] ?: '',
                        'created_at' => $f['updated_at'],
                        'total_bookings' => 0,
                        'completed_bookings' => 0,
                        'cancelled_bookings' => 0,
                        'total_spent' => 0,
                        'fcm_tokens_count' => 1,
                        'latest_booking_ref' => '',
                        'latest_booking_date' => ''
                    ];
                } else {
                    $usersMap[$key]['fcm_tokens_count']++;
                    if (!empty($f['is_online'])) $usersMap[$key]['is_online'] = 1;
                    if (!empty($f['last_active_at']) && (empty($usersMap[$key]['last_active_at']) || strtotime($f['last_active_at']) > strtotime($usersMap[$key]['last_active_at']))) {
                        $usersMap[$key]['last_active_at'] = $f['last_active_at'];
                    }
                    if (!empty($f['device_info']) && empty($usersMap[$key]['device_info'])) {
                        $usersMap[$key]['device_info'] = $f['device_info'];
                    }
                }
            }
        }

        // Merge from app_bookings (with optional date range filter)
        $bookingQuery = "SELECT id, booking_ref, customer_name, customer_phone, user_email, total_fare, status, created_at, pickup_date FROM app_bookings";
        $bookingConditions = [];
        if (!empty($startDate)) $bookingConditions[] = "pickup_date >= '" . $conn->real_escape_string($startDate) . "'";
        if (!empty($endDate)) $bookingConditions[] = "pickup_date <= '" . $conn->real_escape_string($endDate) . "'";
        if (!empty($bookingConditions)) $bookingQuery .= " WHERE " . implode(" AND ", $bookingConditions);
        $bookingQuery .= " ORDER BY id DESC";
        $resBk = $conn->query($bookingQuery);
        if ($resBk) {
            while ($bRow = $resBk->fetch_assoc()) {
                $rawPhone = $bRow['customer_phone'] ?: '';
                $clean10 = substr(preg_replace('/\D/', '', $rawPhone), -10);
                $key = $clean10 ?: ('email_' . strtolower(trim($bRow['user_email'] ?? '')));
                if (!$key || $key === 'email_') continue;

                if (!isset($usersMap[$key])) {
                    $usersMap[$key] = [
                        'user_id' => 0,
                        'name' => $bRow['customer_name'] ?: 'Passenger',
                        'mobile' => $bRow['customer_phone'] ?: '',
                        'clean_phone' => $clean10,
                        'email' => $bRow['user_email'] ?: '',
                        'role' => 'user',
                        'is_online' => 0,
                        'is_banned' => 0,
                        'last_active_at' => $bRow['created_at'],
                        'device_info' => '',
                        'created_at' => $bRow['created_at'],
                        'total_bookings' => 0,
                        'completed_bookings' => 0,
                        'cancelled_bookings' => 0,
                        'total_spent' => 0,
                        'fcm_tokens_count' => 0,
                        'latest_booking_ref' => $bRow['booking_ref'],
                        'latest_booking_date' => $bRow['created_at']
                    ];
                }

                $usersMap[$key]['total_bookings']++;
                $uKey = classifyRideStatus($bRow['status']);
                if ($uKey === 'COMPLETED') {
                    $usersMap[$key]['completed_bookings']++;
                    $usersMap[$key]['total_spent'] += floatval($bRow['total_fare'] ?? 0);
                } elseif ($uKey === 'CANCELLED') {
                    $usersMap[$key]['cancelled_bookings']++;
                } elseif ($uKey === 'IN_TRANSIT' || $uKey === 'CONFIRMED') {
                    $usersMap[$key]['total_spent'] += floatval($bRow['total_fare'] ?? 0);
                }

                if (empty($usersMap[$key]['latest_booking_ref'])) {
                    $usersMap[$key]['latest_booking_ref'] = $bRow['booking_ref'];
                    $usersMap[$key]['latest_booking_date'] = $bRow['created_at'];
                }
                if ($usersMap[$key]['name'] === 'Goa Customer' || $usersMap[$key]['name'] === 'Goa App User') {
                    if (!empty($bRow['customer_name'])) $usersMap[$key]['name'] = $bRow['customer_name'];
                }
            }
        }

        $nowTs = time();
        $usersList = array_values($usersMap);
        foreach ($usersList as &$uObj) {
            $lastTs = !empty($uObj['last_active_at']) ? strtotime($uObj['last_active_at']) : 0;
            if ($uObj['is_online'] && ($nowTs - $lastTs <= 300)) {
                $uObj['live_app_status'] = 'ONLINE_OPEN';
            } else {
                $uObj['live_app_status'] = 'OFFLINE_CLOSED';
                $uObj['is_online'] = 0;
            }
        }

        usort($usersList, function($a, $b) {
            if ($a['is_online'] !== $b['is_online']) {
                return $b['is_online'] - $a['is_online'];
            }
            return strtotime($b['last_active_at'] ?? '1970-01-01') - strtotime($a['last_active_at'] ?? '1970-01-01');
        });

        jsonResponse($usersList);
    }

    // 13. Individual User Detail & Complete Dossier
    if ($method === 'GET' && ($path === '/admin/user-detail' || $path === '/user-detail')) {
        $phone = cleanPhoneDigits($_GET['phone'] ?? $b['phone'] ?? '');
        $userId = intval($_GET['id'] ?? $b['id'] ?? 0);
        $email = trim($_GET['email'] ?? $b['email'] ?? '');
        $clean10 = $phone ? substr($phone, -10) : '';

        $conn = db();

        $userRow = null;
        if ($userId > 0) {
            $r = dbRows("SELECT * FROM app_users WHERE id = ? LIMIT 1", 'i', [$userId]);
            if (!empty($r)) $userRow = $r[0];
        }
        if (!$userRow && $clean10) {
            $r = dbRows("SELECT * FROM app_users WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
            if (!empty($r)) $userRow = $r[0];
        }
        if (!$userRow && $email) {
            $r = dbRows("SELECT * FROM app_users WHERE LOWER(email) = ? LIMIT 1", 's', [strtolower($email)]);
            if (!empty($r)) $userRow = $r[0];
        }

        $tokens = [];
        if ($clean10 && $email) {
            $tokens = dbRows("SELECT * FROM app_fcm_tokens WHERE is_online = 1 AND user_mobile IS NOT NULL AND (RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = ? OR LOWER(user_email) = ?) ORDER BY updated_at DESC", 'ss', [$clean10, strtolower($email)]);
        } elseif ($clean10) {
            $tokens = dbRows("SELECT * FROM app_fcm_tokens WHERE is_online = 1 AND user_mobile IS NOT NULL AND RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = ? ORDER BY updated_at DESC", 's', [$clean10]);
        } elseif ($email) {
            $tokens = dbRows("SELECT * FROM app_fcm_tokens WHERE is_online = 1 AND user_email IS NOT NULL AND LOWER(user_email) = ? ORDER BY updated_at DESC", 's', [strtolower($email)]);
        }

        $bookings = [];
        if ($clean10 && $email) {
            $bookings = dbRows("SELECT * FROM app_bookings WHERE RIGHT(REPLACE(REPLACE(REPLACE(customer_phone, '+', ''), ' ', ''), '-', ''), 10) = ? OR LOWER(user_email) = ? ORDER BY id DESC", 'ss', [$clean10, strtolower($email)]);
        } elseif ($clean10) {
            $bookings = dbRows("SELECT * FROM app_bookings WHERE RIGHT(REPLACE(REPLACE(REPLACE(customer_phone, '+', ''), ' ', ''), '-', ''), 10) = ? ORDER BY id DESC", 's', [$clean10]);
        } elseif ($email) {
            $bookings = dbRows("SELECT * FROM app_bookings WHERE LOWER(user_email) = ? ORDER BY id DESC", 's', [strtolower($email)]);
        }

        $notifications = [];
        $searchEmail = strtolower($userRow['email'] ?? $email);
        $notifications = dbRows("SELECT * FROM app_notifications WHERE LOWER(recipient_email) = ? OR recipient_email LIKE ? ORDER BY id DESC LIMIT 50", 'ss', [$searchEmail, "%$clean10%"]);

        if (!$userRow) {
            $firstBk = $bookings[0] ?? null;
            $userRow = [
                'id' => 0,
                'name' => $firstBk['customer_name'] ?? 'Passenger',
                'mobile' => $phone ?: ($firstBk['customer_phone'] ?? ''),
                'email' => $email ?: ($firstBk['user_email'] ?? ''),
                'role' => 'user',
                'is_online' => !empty($tokens[0]['is_online']) ? intval($tokens[0]['is_online']) : 0,
                'last_active_at' => $tokens[0]['last_active_at'] ?? ($firstBk['created_at'] ?? date('Y-m-d H:i:s')),
                'device_info' => $tokens[0]['device_info'] ?? '',
                'created_at' => $firstBk['created_at'] ?? date('Y-m-d H:i:s')
            ];
        }

        $nowTs = time();
        $lastTs = !empty($userRow['last_active_at']) ? strtotime($userRow['last_active_at']) : 0;
        $isLiveOnline = (!empty($userRow['is_online']) && ($nowTs - $lastTs <= 300));
        $userRow['live_app_status'] = $isLiveOnline ? 'ONLINE_OPEN' : 'OFFLINE_CLOSED';

        jsonResponse([
            'user' => $userRow,
            'fcm_tokens' => $tokens,
            'bookings' => $bookings,
            'notifications' => $notifications
        ]);
    }

    // 13b. Get Single Booking Detail (for dispatch app)
    if ($method === 'GET' && ($path === '/admin/booking-detail' || $path === '/booking-detail')) {
        $bookingId = intval($_GET['id'] ?? $b['id'] ?? 0);
        if (!$bookingId) jsonResponse(['error' => 'id required'], 400);
        $rows = dbRows("SELECT b.*, 
            COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
            COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
            COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number) as vehicle_number,
            d.car_model, d.plate_number 
            FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.id = ?", 'i', [$bookingId]);
        if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
        jsonResponse(['booking' => $rows[0]]);
    }

    // 13c. Cancel Ride (alias for update-status with CANCELLED_BY_ADMIN)
    if ($method === 'POST' && ($path === '/admin/cancel-ride' || $path === '/cancel-ride')) {
        $booking_id = intval($b['booking_id'] ?? 0);
        if (!$booking_id) jsonResponse(['error' => 'booking_id required'], 400);
        $rows = dbRows('SELECT driver_id FROM app_bookings WHERE id = ?', 'i', [$booking_id]);
        if (!empty($rows) && $rows[0]['driver_id']) {
            dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$rows[0]['driver_id']]);
        }
        dbExec("UPDATE app_bookings SET status = 'CANCELLED_BY_ADMIN', driver_id = NULL, driver_name = NULL, driver_phone = NULL, driver_decision = 'NONE' WHERE id = ?", 'i', [$booking_id]);
        broadcastRideLifecycleFCM('CANCELLED_BY_ADMIN', $booking_id);
        jsonResponse(['success' => true, 'message' => 'Ride cancelled']);
    }

    // 13d. Broadcast Push to All Tokens
    if ($method === 'POST' && ($path === '/admin/broadcast-push' || $path === '/broadcast-push')) {
        $title = trim($b['title'] ?? 'PAVANCAB Alert');
        $bodyText = trim($b['body'] ?? 'New update from PavanCab');
        $conn = db();
        $tokens = [];
        $r = $conn->query("SELECT DISTINCT fcm_token FROM app_fcm_tokens WHERE fcm_token IS NOT NULL AND fcm_token != ''");
        if ($r) while ($row = $r->fetch_assoc()) $tokens[] = $row['fcm_token'];
        if (empty($tokens)) jsonResponse(['success' => true, 'message' => 'No tokens found', 'sent' => 0]);
        $res = sendFCMPush($tokens, $title, $bodyText, ['type' => 'BROADCAST', 'title' => $title, 'body' => $bodyText]);
        jsonResponse(['success' => true, 'sent' => $res['sent'], 'failed' => $res['failed']]);
    }

    // 14. Send Custom FCM Push Notification to Individual or Broadcast Group
    if (($method === 'POST' || $method === 'GET') && ($path === '/admin/send-custom-fcm' || $path === '/send-custom-fcm')) {
        $params = is_array($b) ? array_merge(getBody(), $b) : getBody();

        $targetPhone = cleanPhoneDigits($params['target_phone'] ?? $params['phone'] ?? $params['mobile'] ?? $_POST['target_phone'] ?? $_POST['phone'] ?? $_GET['target_phone'] ?? $_GET['phone'] ?? '');
        $targetEmail = trim($params['target_email'] ?? $params['email'] ?? $_POST['target_email'] ?? $_POST['email'] ?? $_GET['target_email'] ?? $_GET['email'] ?? '');
        $targetToken = trim($params['target_token'] ?? $params['token'] ?? $params['fcm_token'] ?? $_POST['target_token'] ?? $_GET['target_token'] ?? '');
        $broadcastAll = !empty($params['broadcast_all']) || !empty($_POST['broadcast_all']);
        $broadcastOnline = !empty($params['broadcast_online']) || !empty($_POST['broadcast_online']);

        $title = trim($params['title'] ?? $_POST['title'] ?? $_GET['title'] ?? '🚕 PAVANCAB Alert');
        $bodyText = trim($params['body'] ?? $params['message'] ?? $params['text'] ?? $_POST['body'] ?? $_POST['message'] ?? $_GET['body'] ?? $_GET['message'] ?? '');
        $clickAction = trim($params['click_action'] ?? $params['url'] ?? $_POST['click_action'] ?? '/app/rides.php');
        $imageUrl = trim($params['image_url'] ?? $_POST['image_url'] ?? '');

        if (!$title) $title = '🚕 PAVANCAB Alert';
        if (!$bodyText) $bodyText = 'Your ride update is ready on Pavan Cab!';

        $tokens = [];
        $conn = db();

        if ($targetToken) {
            $tokens[] = $targetToken;
        } elseif ($broadcastAll) {
            $r = $conn->query("SELECT DISTINCT fcm_token FROM app_fcm_tokens WHERE fcm_token IS NOT NULL AND fcm_token != ''");
            if ($r) while ($row = $r->fetch_assoc()) $tokens[] = $row['fcm_token'];
        } elseif ($broadcastOnline) {
            $r = $conn->query("SELECT DISTINCT fcm_token FROM app_fcm_tokens WHERE is_online = 1 AND last_active_at >= NOW() - INTERVAL 5 MINUTE AND fcm_token IS NOT NULL AND fcm_token != ''");
            if ($r) while ($row = $r->fetch_assoc()) $tokens[] = $row['fcm_token'];
        } elseif ($targetPhone || $targetEmail) {
            if ($targetPhone) {
                $tokens = array_merge($tokens, getFCMTokensByPhone($targetPhone));
            }
            if ($targetEmail) {
                $tokens = array_merge($tokens, getFCMTokensByEmail($targetEmail));
            }
        }

        $tokens = array_values(array_unique(array_filter($tokens)));

        if (empty($tokens)) {
            jsonResponse(['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'No active device found for this user']);
        }

        $pushData = [
            'type' => 'CUSTOM_ANNOUNCEMENT',
            'click_action' => $clickAction,
            'url' => $clickAction,
            'title' => $title,
            'body' => $bodyText
        ];
        if ($imageUrl) $pushData['image'] = $imageUrl;

        $res = sendFCMPush($tokens, $title, $bodyText, $pushData);

        $stmt = $conn->prepare("INSERT INTO app_notifications (booking_id, type, title, message, recipient_email) VALUES (NULL, 'CUSTOM_FCM', ?, ?, ?)");
        if ($stmt) {
            $recip = $targetEmail ?: ($targetPhone ?: 'Broadcast');
            $stmt->bind_param('sss', $title, $bodyText, $recip);
            $stmt->execute();
        }

        jsonResponse([
            'success' => true,
            'message' => "Custom FCM Push Dispatched! Sent: {$res['sent']}, Failed: {$res['failed']}",
            'sent' => $res['sent'],
            'failed' => $res['failed'],
            'errors' => $res['errors'] ?? []
        ]);
    }

    // 15. Commission Report (admin only) — ₹300 per completed ride grouped by ride date
    if ($method === 'GET' && ($path === '/admin/commission-report' || $path === '/commission-report')) {
        $days = min(90, max(1, intval($_GET['days'] ?? 30)));
        $conn = db();
        $rows = dbRows(
            "SELECT DATE(pickup_date) as ride_date, COUNT(*) as ride_count, SUM(total_fare) as total_fare
             FROM app_bookings
             WHERE UPPER(status) = 'COMPLETED' AND pickup_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(pickup_date)
             ORDER BY ride_date DESC",
            'i', [$days]
        );
        $totalCommission = 0;
        $totalRides = 0;
        $commRate = getCommissionRate();
        foreach ($rows as &$r) {
            $r['commission'] = intval($r['ride_count']) * $commRate;
            $totalCommission += $r['commission'];
            $totalRides += intval($r['ride_count']);
            unset($r);
        }
        jsonResponse([
            'days' => $days,
            'commission_per_ride' => $commRate,
            'total_commission' => $totalCommission,
            'total_rides' => $totalRides,
            'daily' => $rows
        ]);
    }

    // 16. Upcoming Rides needing reminders (for background worker + exact alarms)
    if ($method === 'GET' && ($path === '/admin/upcoming-rides' || $path === '/upcoming-rides')) {
        $conn = db();
        $now = new DateTime();
        $today = $now->format('Y-m-d');
        $tomorrow = (new DateTime())->modify('+1 day')->format('Y-m-d');
        $nowTs = time();

        $needsReminder = [];

        // Part A: Assigned rides (with driver) — ride_soon alert (60 min before pickup)
        $sqlAssigned = "SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name,
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone
                FROM app_bookings b 
                LEFT JOIN app_drivers d ON b.driver_id = d.id
                WHERE UPPER(b.status) IN ('CONFIRMED','ASSIGNED','ACCEPTED')
                AND (b.reminder_sent IS NULL OR b.reminder_sent = 0)
                AND b.pickup_date IN (?, ?)
                ORDER BY b.pickup_date ASC, b.pickup_time ASC";
        $assignedRides = dbRows($sqlAssigned, 'ss', [$today, $tomorrow]);

        foreach ($assignedRides as $ride) {
            $pickupDate = $ride['pickup_date'];
            $pickupTime = $ride['pickup_time'];
            if (!$pickupDate || !$pickupTime) continue;

            $pickupTs = strtotime("$pickupDate $pickupTime");
            $diffMinutes = ($pickupTs - $nowTs) / 60;
            $createdAtTs = strtotime($ride['created_at'] ?? 'now');
            $bookingAgeMinutes = ($nowTs - $createdAtTs) / 60;

            if ($bookingAgeMinutes < 30) continue;

            // ride_soon: within 60 min of pickup
            if ($diffMinutes > 0 && $diffMinutes <= 60) {
                $ride['reminder_type'] = 'ride_soon';
                $needsReminder[] = $ride;
                continue;
            }
        }

        // Part B1: Night ride alert — admin+team notified at 10PM for rides pickup between 10PM-6AM
        $nightRides = dbRows("SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name,
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone
                FROM app_bookings b 
                LEFT JOIN app_drivers d ON b.driver_id = d.id
                WHERE UPPER(b.status) IN ('CONFIRMED','ASSIGNED','ACCEPTED','PENDING')
                AND (b.reminder_sent IS NULL OR b.reminder_sent < 5)
                AND b.pickup_date IN (?, ?)
                ORDER BY b.pickup_date ASC, b.pickup_time ASC", 'ss', [$today, $tomorrow]);

        $tenPM = strtotime("today 22:00");
        foreach ($nightRides as $ride) {
            $pickupDate = $ride['pickup_date'];
            $pickupTime = $ride['pickup_time'];
            if (!$pickupDate || !$pickupTime) continue;

            $pickupTs = strtotime("$pickupDate $pickupTime");
            $diffMinutes = ($pickupTs - $nowTs) / 60;
            $pickupHour = intval(date('G', strtotime("$pickupDate $pickupTime")));

            // Night ride: pickup between 10PM-6AM
            if ($pickupHour >= 22 || $pickupHour < 6) {
                // Fire at 10PM today if ride is tonight or tomorrow morning, and hasn't passed pickup yet
                if ($diffMinutes > 0 && $nowTs >= $tenPM) {
                    $alreadyNight = false;
                    foreach ($needsReminder as $nr) {
                        if ($nr['id'] == $ride['id'] && $nr['reminder_type'] == 'night_ride') { $alreadyNight = true; break; }
                    }
                    if (!$alreadyNight) {
                        $ride['reminder_type'] = 'night_ride';
                        $needsReminder[] = $ride;
                    }
                }
            }
        }

        // Part B2: PENDING rides approaching pickup (need driver assignment)
        $sqlPending = "SELECT b.* FROM app_bookings b 
                WHERE UPPER(b.status) = 'PENDING'
                AND b.pickup_date IN (?, ?)
                AND (b.reminder_sent IS NULL OR b.reminder_sent = 0 OR b.reminder_sent < 3)
                ORDER BY b.pickup_date ASC, b.pickup_time ASC";
        $pendingRides = dbRows($sqlPending, 'ss', [$today, $tomorrow]);

        foreach ($pendingRides as $ride) {
            $pickupDate = $ride['pickup_date'];
            $pickupTime = $ride['pickup_time'];
            if (!$pickupDate || !$pickupTime) continue;

            $pickupTs = strtotime("$pickupDate $pickupTime");
            $diffMinutes = ($pickupTs - $nowTs) / 60;
            $createdAtTs = strtotime($ride['created_at'] ?? 'now');
            $bookingAgeMinutes = ($nowTs - $createdAtTs) / 60;

            if ($bookingAgeMinutes < 30) continue;

            // unassigned_urgent: Pickup within 90 minutes and still no driver
            if ($diffMinutes > 0 && $diffMinutes <= 90) {
                $ride['reminder_type'] = 'unassigned_urgent';
                $needsReminder[] = $ride;
                continue;
            }

            // unassigned: Pickup within 6 hours and still no driver
            if ($diffMinutes > 0 && $diffMinutes <= 360) {
                $ride['reminder_type'] = 'unassigned';
                $needsReminder[] = $ride;
            }
        }

        jsonResponse(['rides' => $needsReminder, 'count' => count($needsReminder)]);
    }

    // 17. Mark reminder sent for a booking
    if ($method === 'POST' && ($path === '/admin/mark-reminder-sent' || $path === '/mark-reminder-sent')) {
        $bookingId = intval($b['booking_id'] ?? 0);
        if (!$bookingId) jsonResponse(['error' => 'booking_id required'], 400);
        dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$bookingId]);
        jsonResponse(['success' => true, 'message' => 'Reminder marked as sent']);
    }

    // 18. Check if current user still has team/admin access (for force logout)
    if ($method === 'GET' && ($path === '/admin/check-access' || $path === '/check-access')) {
        $phone = trim($_GET['phone'] ?? '');
        $email = trim($_GET['email'] ?? '');
        if (!$phone && !$email) jsonResponse(['valid' => false, 'message' => 'No credentials'], 200);

        $clean10 = substr(preg_replace('/\D/', '', $phone), -10);
        $conn = db();

        $found = false;
        $role = '';
        $name = '';

        // Check team members table
        if ($clean10) {
            $r = $conn->query("SELECT role, member_name FROM app_team_members WHERE is_active = 1 AND RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = '$clean10' LIMIT 1");
            if ($r && $r->num_rows > 0) { $row = $r->fetch_assoc(); $found = true; $role = $row['role']; $name = $row['member_name']; }
        }
        if (!$found && $email) {
            $safeEmail = $conn->real_escape_string(strtolower($email));
            $r = $conn->query("SELECT role, member_name FROM app_team_members WHERE is_active = 1 AND LOWER(member_email) = '$safeEmail' LIMIT 1");
            if ($r && $r->num_rows > 0) { $row = $r->fetch_assoc(); $found = true; $role = $row['role']; $name = $row['member_name']; }
        }

        // Check super admin phone
        if (!$found) {
            $superPhone = substr(preg_replace('/\D/', '', SUPER_ADMIN_PHONE), -10);
            if ($clean10 && $clean10 === $superPhone) { $found = true; $role = 'admin'; $name = 'Super Admin'; }
        }

        jsonResponse([
            'valid' => $found,
            'role' => $role,
            'name' => $name,
            'message' => $found ? 'Access valid' : 'Your access has been revoked. Contact admin.'
        ]);
    }

    // 19. Toggle team member active/inactive (admin only)
    if ($method === 'POST' && ($path === '/admin/toggle-team' || $path === '/toggle-team')) {
        $memberId = intval($b['member_id'] ?? 0);
        if (!$memberId) jsonResponse(['error' => 'member_id required'], 400);
        $conn = db();
        $conn->query("UPDATE app_team_members SET is_active = IF(is_active = 1, 0, 1) WHERE id = $memberId");
        $newState = intval($conn->query("SELECT is_active FROM app_team_members WHERE id = $memberId")->fetch_assoc()['is_active'] ?? 0);
        jsonResponse(['success' => true, 'is_active' => $newState, 'message' => $newState ? 'Member activated' : 'Member deactivated']);
    }

    // 20. Update team member role (admin only)
    if ($method === 'POST' && ($path === '/admin/update-team-role' || $path === '/update-team-role')) {
        $memberId = intval($b['member_id'] ?? 0);
        $newRole = trim($b['role'] ?? 'team');
        if (!$memberId || !in_array($newRole, ['team', 'admin'])) jsonResponse(['error' => 'member_id and valid role (team/admin) required'], 400);
        $conn = db();
        $conn->query("UPDATE app_team_members SET role = '" . $conn->real_escape_string($newRole) . "' WHERE id = " . intval($memberId));
        jsonResponse(['success' => true, 'message' => "Role updated to $newRole"]);
    }

    // 21. Update profile (name/email for dispatch team members)
    if ($method === 'POST' && ($path === '/admin/profile-update' || $path === '/profile-update')) {
        if (empty($_SESSION['user'])) jsonResponse(['error' => 'Login required'], 401);
        $name = trim($b['name'] ?? $_SESSION['user']['name'] ?? '');
        $email = trim($b['email'] ?? $_SESSION['user']['email'] ?? '');
        $phone = $_SESSION['user']['mobile'] ?? '';
        $conn = db();
        $clean10 = substr(preg_replace('/\D/', '', $phone), -10);
        if ($name && $clean10) {
            $conn->query("UPDATE app_team_members SET member_name = '" . $conn->real_escape_string($name) . "' WHERE RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = '" . $conn->real_escape_string($clean10) . "' AND is_active = 1");
        }
        if ($email) {
            $_SESSION['user']['email'] = $email;
        }
        $_SESSION['user']['name'] = $name;
        jsonResponse(['success' => true, 'message' => 'Profile updated']);
    }

    // 22. Driver detail — booking history + stats for a specific driver
    if ($method === 'GET' && ($path === '/admin/driver-detail' || $path === '/driver-detail')) {
        $driverId = intval($_GET['driver_id'] ?? 0);
        if (!$driverId) jsonResponse(['error' => 'driver_id required'], 400);
        $conn = db();
        $driver = dbRows("SELECT * FROM app_drivers WHERE id = ?", 'i', [$driverId]);
        if (empty($driver)) jsonResponse(['error' => 'Driver not found'], 404);
        $bookings = dbRows("SELECT id, booking_ref, customer_name, customer_phone, pickup_location, drop_location, pickup_date, pickup_time, cab_type, total_fare, status, driver_name, driver_decision, created_at FROM app_bookings WHERE driver_id = ? ORDER BY id DESC LIMIT 50", 'i', [$driverId]);
        $stats = dbRows("SELECT 
            COUNT(*) as total_rides,
            SUM(CASE WHEN UPPER(status) = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN UPPER(status) LIKE 'CANCEL%' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN UPPER(status) = 'COMPLETED' THEN total_fare ELSE 0 END) as total_earnings
            FROM app_bookings WHERE driver_id = ?", 'i', [$driverId]);
        jsonResponse([
            'driver' => $driver[0],
            'bookings' => $bookings,
            'stats' => !empty($stats) ? $stats[0] : ['total_rides' => 0, 'completed' => 0, 'cancelled' => 0, 'total_earnings' => 0]
        ]);
    }

    // 23. Ride reports — list all
    if ($method === 'GET' && ($path === '/admin/ride-reports' || $path === '/ride-reports')) {
        $conn = db();
        $reports = dbRows("SELECT r.*, b.booking_ref, b.pickup_location, b.drop_location, b.driver_name, b.driver_phone, b.vehicle_number FROM app_ride_reports r LEFT JOIN app_bookings b ON r.booking_id = b.id ORDER BY r.id DESC LIMIT 100");
        jsonResponse($reports);
    }

    // 24. Commission config (admin only) — get/set commission per ride rate
    if ($method === 'GET' && ($path === '/admin/commission-config' || $path === '/commission-config')) {
        $conn = db();
        $rate = 300;
        $r = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'commission_per_ride' LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) $rate = intval($row['setting_value']);
        jsonResponse(['commission_per_ride' => $rate]);
    }
    if ($method === 'POST' && ($path === '/admin/commission-config' || $path === '/commission-config')) {
        $rate = max(0, intval($b['commission_per_ride'] ?? 0));
        if (!$rate) jsonResponse(['error' => 'commission_per_ride required'], 400);
        $conn = db();
        $conn->query("INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES ('commission_per_ride', '$rate', NOW()) ON DUPLICATE KEY UPDATE setting_value = '$rate', updated_at = NOW()");
        jsonResponse(['success' => true, 'commission_per_ride' => $rate, 'message' => 'Commission rate updated']);
    }

    // Driver Subscription & Commission Config
    if ($method === 'GET' && ($path === '/admin/driver-config' || $path === '/driver-config')) {
        $conn = db();
        $settings = [];
        $r = $conn->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount', 'driver_commission_per_ride')");
        if ($r) while ($row = $r->fetch_assoc()) $settings[$row['setting_key']] = floatval($row['setting_value']);
        jsonResponse([
            'driver_subscription_amount' => $settings['driver_subscription_amount'] ?? 1000,
            'driver_commission_per_ride' => $settings['driver_commission_per_ride'] ?? 200
        ]);
    }
    if ($method === 'POST' && ($path === '/admin/driver-config' || $path === '/driver-config')) {
        $subAmt = max(0, floatval($b['driver_subscription_amount'] ?? -1));
        $rideComm = max(0, floatval($b['driver_commission_per_ride'] ?? -1));
        $conn = db();
        if ($subAmt >= 0) {
            $conn->query("INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES ('driver_subscription_amount', '$subAmt', NOW()) ON DUPLICATE KEY UPDATE setting_value = '$subAmt', updated_at = NOW()");
        }
        if ($rideComm >= 0) {
            $conn->query("INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES ('driver_commission_per_ride', '$rideComm', NOW()) ON DUPLICATE KEY UPDATE setting_value = '$rideComm', updated_at = NOW()");
        }
        jsonResponse(['success' => true, 'message' => 'Driver config updated', 'driver_subscription_amount' => $subAmt >= 0 ? $subAmt : null, 'driver_commission_per_ride' => $rideComm >= 0 ? $rideComm : null]);
    }

    // Driver Payments Admin View
    if ($method === 'GET' && ($path === '/admin/driver-payments' || $path === '/driver-payments')) {
        $driverId = intval($b['driver_id'] ?? 0);
        if ($driverId) {
            $payments = dbRows("SELECT * FROM driver_payments WHERE driver_id = ? ORDER BY created_at DESC LIMIT 100", 'i', [$driverId]);
            $subs = dbRows("SELECT * FROM driver_subscriptions WHERE driver_id = ? ORDER BY created_at DESC LIMIT 50", 'i', [$driverId]);
        } else {
            $payments = dbRows("SELECT dp.*, d.name as driver_name, d.phone as driver_phone FROM driver_payments dp LEFT JOIN app_drivers d ON dp.driver_id = d.id ORDER BY dp.created_at DESC LIMIT 100");
            $subs = dbRows("SELECT ds.*, d.name as driver_name, d.phone as driver_phone FROM driver_subscriptions ds LEFT JOIN app_drivers d ON ds.driver_id = d.id ORDER BY ds.created_at DESC LIMIT 50");
        }
        jsonResponse(['payments' => $payments, 'subscriptions' => $subs]);
    }

    // 25. Get commission rate helper (used by commission-report)
    // 26. Respond to fare proposal (accept/decline)
    if ($method === 'POST' && ($path === '/admin/respond-fare' || $path === '/respond-fare')) {
        $bookingId = intval($b['booking_id'] ?? 0);
        $response = trim($b['response'] ?? ''); // ACCEPT or DECLINE
        $newFare = floatval($b['new_fare'] ?? 0);
        if (!$bookingId || !in_array(strtoupper($response), ['ACCEPT', 'DECLINE'])) jsonResponse(['error' => 'booking_id and response (ACCEPT/DECLINE) required'], 400);
        $conn = db();
        if (strtoupper($response) === 'ACCEPT' && $newFare > 0) {
            $conn->query("UPDATE app_bookings SET total_fare = $newFare, fare_proposal_status = 'ACCEPTED' WHERE id = $bookingId");
        } else {
            $conn->query("UPDATE app_bookings SET fare_proposal_status = 'DECLINED' WHERE id = $bookingId");
        }
        jsonResponse(['success' => true, 'message' => "Fare proposal $response'd"]);
    }
}

// Helper: get commission rate from settings
function getCommissionRate() {
    try {
        $conn = db();
        $r = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'commission_per_ride' LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) return intval($row['setting_value']);
    } catch (Exception $e) {}
    return 300;
}

// Router
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = '/' . ltrim(preg_replace('#^.*?(?:dashboard|api_dashboard)\.php#i', '', $path), '/');
if ($action) {
    if ($action === 'assign-driver' || $action === '/assign-driver') $path = '/admin/assign-driver';
    elseif ($action === 'update-status' || $action === '/update-status') $path = '/admin/update-status';
    elseif ($action === 'edit-fare' || $action === '/edit-fare' || $action === 'boost-fare') $path = '/admin/edit-fare';
    elseif ($action === 'edit-booking' || $action === '/edit-booking') $path = '/admin/edit-booking';
    elseif ($action === 'create-booking' || $action === '/create-booking') $path = '/admin/create-booking';
    elseif ($action === 'drivers' || $action === '/drivers') $path = '/admin/drivers';
    elseif ($action === 'add-driver' || $action === '/add-driver') $path = '/admin/add-driver';
    elseif ($action === 'toggle-driver-status' || $action === '/toggle-driver-status') $path = '/admin/toggle-driver-status';
    elseif ($action === 'delete-driver' || $action === '/delete-driver') $path = '/admin/delete-driver';
    elseif ($action === 'edit-driver' || $action === '/edit-driver') $path = '/admin/edit-driver';
    elseif ($action === 'whatsapp-config' || $action === '/whatsapp-config') $path = '/admin/whatsapp-config';
    elseif ($action === 'team' || $action === '/team') $path = '/admin/team';
    elseif ($action === 'remove-team' || $action === '/remove-team') $path = '/admin/remove-team';
    elseif ($action === 'reports' || $action === '/reports') $path = '/admin/reports';
    elseif ($action === 'update-report' || $action === 'update_report_status' || $action === '/update_report_status') $path = '/admin/update-report';
    elseif ($action === 'bookings' || $action === '/bookings') $path = '/admin/bookings';
    elseif ($action === 'all-bookings' || $action === '/all-bookings') $path = '/admin/bookings';
    elseif ($action === 'booking-detail' || $action === '/booking-detail') $path = '/admin/booking-detail';
    elseif ($action === 'cancel-ride' || $action === '/cancel-ride') $path = '/admin/cancel-ride';
    elseif ($action === 'add-team' || $action === '/add-team') $path = '/admin/team';
    elseif ($action === 'send-push' || $action === '/send-push') $path = '/admin/send-custom-fcm';
    elseif ($action === 'broadcast-push' || $action === '/broadcast-push') $path = '/admin/broadcast-push';
    elseif ($action === 'stats' || $action === '/stats') $path = '/admin/stats';
    elseif ($action === 'users' || $action === '/users') $path = '/admin/users';
    elseif ($action === 'user-detail' || $action === '/user-detail') $path = '/admin/user-detail';
    elseif ($action === 'propose-fare' || $action === '/propose-fare') $path = '/admin/propose-fare';
    elseif ($action === 'respond-fare' || $action === '/respond-fare') $path = '/admin/respond-fare';
    elseif ($action === 'send-personal-push' || $action === '/send-personal-push') $path = '/admin/send-personal-push';
    elseif ($action === 'send-custom-fcm' || $action === '/send-custom-fcm') $path = '/admin/send-custom-fcm';
    elseif ($action === 'bulk-push' || $action === '/bulk-push') $path = '/admin/bulk-push';
    elseif ($action === 'bulk-whatsapp' || $action === '/bulk-whatsapp') $path = '/admin/bulk-whatsapp';
    elseif ($action === 'bulk-tokens' || $action === '/bulk-tokens') $path = '/admin/bulk-tokens';
    elseif ($action === 'commission-report' || $action === '/commission-report') $path = '/admin/commission-report';
    elseif ($action === 'upcoming-rides' || $action === '/upcoming-rides') $path = '/admin/upcoming-rides';
    elseif ($action === 'mark-reminder-sent' || $action === '/mark-reminder-sent') $path = '/admin/mark-reminder-sent';
    elseif ($action === 'check-access' || $action === '/check-access') $path = '/admin/check-access';
    elseif ($action === 'delete-booking' || $action === '/delete-booking') $path = '/admin/delete-booking';
    elseif ($action === 'ban-user' || $action === '/ban-user') $path = '/admin/ban-user';
    elseif ($action === 'delete-user' || $action === '/delete-user') $path = '/admin/delete-user';
    elseif ($action === 'toggle-team' || $action === '/toggle-team') $path = '/admin/toggle-team';
    elseif ($action === 'update-team-role' || $action === '/update-team-role') $path = '/admin/update-team-role';
    elseif ($action === 'profile-update' || $action === '/profile-update') $path = '/admin/profile-update';
    elseif ($action === 'driver-detail' || $action === '/driver-detail') $path = '/admin/driver-detail';
    elseif ($action === 'ride-reports' || $action === '/ride-reports') $path = '/admin/ride-reports';
    elseif ($action === 'commission-config' || $action === '/commission-config') $path = '/admin/commission-config';
    elseif ($action === 'driver-config' || $action === '/driver-config') $path = '/admin/driver-config';
    elseif ($action === 'driver-payments' || $action === '/driver-payments') $path = '/admin/driver-payments';
    else $path = '/' . ltrim($action, '/');
}
handleDashboardModule($method, $path, $b);
