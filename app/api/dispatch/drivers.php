<?php
// Dispatch Drivers - included by index.php

if (in_array($action, ['drivers','add-driver','edit-driver','delete-driver','toggle-driver-status','driver-pending','approve-driver','driver-detail','drivers-availability','driver-config','update-driver-config','driver-trip-history','mark-commission-paid'])) {
    if (empty($_SESSION['user'])) jsonResponse(['error' => 'Admin auth required'], 401);
}

// === LIST DRIVERS ===
if ($action === 'drivers') {
    $conn = db();
    $sq = trim($_GET['search'] ?? $b['search'] ?? '');
    $st = trim($_GET['status'] ?? $b['status'] ?? '');
    $sort = trim($_GET['sort'] ?? $b['sort'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(200, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $conds = []; $types = ''; $params = [];
    if ($sq) { $w = "%{$sq}%"; $conds[] = "(d.name LIKE ? OR d.phone LIKE ?)"; $types .= 'ss'; $params[] = $w; $params[] = $w; }
    if ($st !== '' && strtolower($st) !== 'all') { $conds[] = "LOWER(d.status) = LOWER(?)"; $types .= 's'; $params[] = $st; }
    $wc = !empty($conds) ? " WHERE " . implode(" AND ", $conds) : '';
    $tq = "SELECT COUNT(*) as c FROM app_drivers d" . $wc;
    if ($types) { $cs = $conn->prepare($tq); if ($cs) { $cs->bind_param($types, ...$params); $cs->execute(); $total = intval($cs->get_result()->fetch_assoc()['c']); } else { $total = 0; } } else { $total = intval($conn->query($tq)->fetch_assoc()['c']); }
    $orderBy = 'd.id DESC';
    if ($sort === 'name') $orderBy = 'd.name ASC';
    elseif ($sort === 'status') $orderBy = 'd.status ASC';
    elseif ($sort === 'last_active_at') $orderBy = 'ISNULL(d.last_active_at), d.last_active_at DESC';
    $sql = "SELECT d.id, d.name, d.phone, d.status, d.car_model, d.plate_number, d.is_online, d.is_approved, d.rating, d.total_ratings, d.last_active_at, d.created_at, (SELECT COUNT(*) FROM app_bookings WHERE driver_id = d.id AND UPPER(status) = 'COMPLETED') as rides_completed FROM app_drivers d" . $wc . " ORDER BY $orderBy LIMIT $limit OFFSET $offset";
    $rows = $types ? dbRows($sql, $types, $params) : dbRows($sql);
    jsonResponse(['drivers' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => ceil($total / $limit)]);
}

// === DRIVERS AVAILABILITY COUNT ===
if ($action === 'drivers-availability') {
    $conn = db();
    $count = intval($conn->query("SELECT COUNT(*) as c FROM app_drivers WHERE LOWER(status) = 'available'")->fetch_assoc()['c']);
    jsonResponse(['available_drivers' => $count]);
}

// === ADD DRIVER ===
if ($action === 'add-driver' && $method === 'POST') {
    $name  = trim($b['name'] ?? '');
    $phone = trim($b['phone'] ?? '');
    $email = trim($b['email'] ?? '');
    $model = trim($b['car_model'] ?? 'Goa Cab');
    $plate = trim($b['plate_number'] ?? '');
    $rawPass = trim($b['password'] ?? '');
    $status = trim($b['status'] ?? 'available');
    if (!$name || !$phone) jsonResponse(['error' => 'Driver name and phone are required'], 400);
    if (!in_array($status, ['available', 'offline', 'pending', 'on_trip'])) $status = 'available';
    $passHash = null;
    if ($rawPass) {
        $passHash = password_hash($rawPass, PASSWORD_BCRYPT);
    } else {
        $autoPass = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $passHash = password_hash($autoPass, PASSWORD_BCRYPT);
    }
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_drivers (name, phone, email, car_model, plate_number, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssss', $name, $phone, $email, $model, $plate, $passHash, $status);
    $stmt->execute();
    jsonResponse(['success' => true, 'message' => "Driver $name added to fleet!", 'driver_id' => $conn->insert_id]);
}

// === EDIT DRIVER ===
if ($action === 'edit-driver' && $method === 'POST') {
    $driver_id = intval($b['id'] ?? $b['driver_id'] ?? 0);
    if (!$driver_id) jsonResponse(['error' => 'id required'], 400);
    $conn = db();
    $sets = []; $types = ''; $params = [];
    $fields = ['name', 'phone', 'email', 'car_model', 'plate_number', 'status'];
    foreach ($fields as $f) {
        if (isset($b[$f]) && $b[$f] !== '') { $sets[] = "$f = ?"; $types .= 's'; $params[] = trim($b[$f]); }
    }
    if (isset($b['password']) && trim($b['password']) !== '') {
        $sets[] = 'password_hash = ?'; $types .= 's'; $params[] = password_hash(trim($b['password']), PASSWORD_BCRYPT);
    }
    if (empty($sets)) jsonResponse(['error' => 'No fields to update'], 400);
    $params[] = $driver_id; $types .= 'i';
    $sql = "UPDATE app_drivers SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) { $stmt->bind_param($types, ...$params); $stmt->execute(); }
    jsonResponse(['success' => true, 'message' => 'Driver details updated!']);
}

// === DELETE DRIVER ===
if ($action === 'delete-driver' && $method === 'POST') {
    $driver_id = intval($b['id'] ?? $b['driver_id'] ?? 0);
    if (!$driver_id) jsonResponse(['error' => 'id required'], 400);
    $conn = db();
    $conn->query("DELETE FROM app_drivers WHERE id = $driver_id");
    jsonResponse(['success' => true, 'message' => 'Driver removed from fleet']);
}

// === TOGGLE DRIVER STATUS ===
if ($action === 'toggle-driver-status' && $method === 'POST') {
    $driver_id = intval($b['id'] ?? $b['driver_id'] ?? 0);
    $newStatus = trim($b['status'] ?? '');
    if (!$driver_id) jsonResponse(['error' => 'id required'], 400);
    $conn = db();
    if ($newStatus && in_array($newStatus, ['available', 'offline'])) {
        $targetStatus = $newStatus;
    } else {
        $row = dbRows("SELECT status FROM app_drivers WHERE id = ?", 'i', [$driver_id]);
        $currentStatus = !empty($row) ? strtolower($row[0]['status']) : 'available';
        $targetStatus = ($currentStatus === 'available') ? 'offline' : 'available';
    }
    $stmt = $conn->prepare("UPDATE app_drivers SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $targetStatus, $driver_id);
    $stmt->execute();
    jsonResponse(['success' => true, 'message' => "Driver status set to $targetStatus", 'status' => $targetStatus]);
}

// === PENDING DRIVERS ===
if ($action === 'driver-pending') {
    $rows = dbRows("SELECT d.id, d.name, d.phone, d.email, d.car_model, d.plate_number, d.status, d.created_at FROM app_drivers d WHERE LOWER(d.status) = 'pending' ORDER BY d.created_at DESC");
    jsonResponse(['drivers' => $rows, 'total' => count($rows)]);
}

// === APPROVE DRIVER ===
if ($action === 'approve-driver' && $method === 'POST') {
    $driver_id = intval($b['id'] ?? $b['driver_id'] ?? 0);
    $approve = intval($b['approve'] ?? 1) === 1;
    if (!$driver_id) jsonResponse(['error' => 'id required'], 400);
    dbExec("UPDATE app_drivers SET is_approved = ? WHERE id = ?", 'ii', [$approve ? 1 : 0, $driver_id]);
    if ($approve) {
        dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driver_id]);
        try { sendFCMPushToDriver($driver_id, "✅ Account Approved!", "Your driver account has been approved. You can now start accepting rides.", ['type' => 'APPROVED']); } catch (Exception $e) {}
    } else {
        dbExec("UPDATE app_drivers SET status = 'offline' WHERE id = ?", 'i', [$driver_id]);
        try { sendFCMPushToDriver($driver_id, "⛔ Account Revoked", "Your driver account access has been revoked by dispatch. You cannot accept or offer fares. Please contact the admin to get re-approved.", ['type' => 'REVOKED']); } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => $approve ? 'Driver approved and set available' : 'Driver approval revoked']);
}

// === MARK COMMISSION PAID ===
if ($action === 'mark-commission-paid' && $method === 'POST') {
    $bookingId = intval($b['booking_id'] ?? $b['id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'booking_id required'], 400);
    $bk = dbRows("SELECT id, driver_id, booking_ref, status FROM app_bookings WHERE id = ?", 'i', [$bookingId]);
    if (empty($bk)) jsonResponse(['error' => 'Booking not found'], 404);
    if (strtoupper($bk[0]['status']) !== 'COMPLETED') jsonResponse(['error' => 'Only completed rides can have commission marked'], 400);
    $driverId = intval($bk[0]['driver_id'] ?? 0);
    
    $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
    $amount = floatval($settings[0]['setting_value'] ?? 200);
    
    dbExec("UPDATE app_bookings SET commission_status = 'paid' WHERE id = ?", 'i', [$bookingId]);
    if ($driverId > 0) {
        // Record in ledger (avoid duplicates for same ride)
        $exists = dbRows("SELECT id FROM driver_payments WHERE driver_id = ? AND booking_id = ? AND type = 'commission' LIMIT 1", 'ii', [$driverId, $bookingId]);
        if (empty($exists)) {
            dbExec("INSERT INTO driver_payments (driver_id, type, booking_id, amount, status, paid_at, collected_by) VALUES (?, 'commission', ?, ?, 'paid', NOW(), ?)",
                'iidss', [$driverId, $bookingId, $amount, ($_SESSION['user']['name'] ?? 'Admin')]);
        } else {
            dbExec("UPDATE driver_payments SET status = 'paid', paid_at = NOW() WHERE id = ?", 'i', [$exists[0]['id']]);
        }
        try { sendFCMPushToDriver($driverId, "💰 Commission Received", "Commission ₹" . intval($amount) . " for ride #{$bk[0]['booking_ref']} was collected by our team. Thank you!", ['type' => 'COMMISSION_PAID', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => "Commission ₹" . intval($amount) . " marked as paid"]);
}

// === DRIVER DETAIL ===
if ($action === 'driver-detail') {
    $driverId = intval($_GET['id'] ?? $_GET['driver_id'] ?? $b['id'] ?? $b['driver_id'] ?? 0);
    if (!$driverId) jsonResponse(['error' => 'id required'], 400);
    $driver = dbRows("SELECT * FROM app_drivers WHERE id = ?", 'i', [$driverId]);
    if (empty($driver)) jsonResponse(['error' => 'Driver not found'], 404);
    
    $bookings = dbRows("SELECT b.*, COALESCE(NULLIF(b.driver_name,''),d.name) as driver_name, COALESCE(NULLIF(b.driver_phone,''),d.phone) as driver_phone,
                        COALESCE(NULLIF(b.vehicle_number,''), d.plate_number, '') as vehicle_number
                        FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id
                        WHERE b.driver_id = ? ORDER BY b.id DESC LIMIT 100", 'i', [$driverId]);
    
    // Commission badge per completed ride:
    // FREE (admin-assigned, never charged) | SUBSCRIBED | PAID | DUE | NA
    $subRanges = dbRows("SELECT start_date, end_date FROM driver_subscriptions WHERE driver_id = ? AND status = 'active'", 'i', [$driverId]);
    foreach ($bookings as &$bkk) {
        if (strtoupper($bkk['status']) !== 'COMPLETED') { $bkk['commission_badge'] = 'NA'; continue; }
        $assignedByB = strtolower($bkk['assigned_by'] ?? 'admin');
        if ($assignedByB !== 'self') { $bkk['commission_badge'] = 'FREE'; continue; }
        if (($bkk['commission_status'] ?? '') === 'paid') { $bkk['commission_badge'] = 'PAID'; continue; }
        $pd = $bkk['pickup_date'] ?? '';
        $covered = false;
        foreach ($subRanges as $sr) {
            if ($pd && $pd >= $sr['start_date'] && $pd <= $sr['end_date']) { $covered = true; break; }
        }
        $bkk['commission_badge'] = $covered ? 'SUBSCRIBED' : 'DUE';
    }
    unset($bkk);
    
    $stats = dbRows("SELECT
        COUNT(*) as total_rides,
        COALESCE(SUM(CASE WHEN UPPER(status) = 'COMPLETED' THEN 1 ELSE 0 END), 0) as completed,
        COALESCE(SUM(CASE WHEN UPPER(status) LIKE 'CANCEL%' THEN 1 ELSE 0 END), 0) as cancelled,
        COALESCE(SUM(CASE WHEN UPPER(status) = 'COMPLETED' THEN total_fare ELSE 0 END), 0) as total_earnings,
        COALESCE(SUM(CASE WHEN UPPER(status) = 'COMPLETED' AND commission_status IS NULL THEN 1 ELSE 0 END), 0) as commission_due_count,
        COALESCE(SUM(CASE WHEN UPPER(status) = 'COMPLETED' AND commission_status = 'paid' THEN 1 ELSE 0 END), 0) as commission_paid_count
        FROM app_bookings WHERE driver_id = ?", 'i', [$driverId]);
    
    // Subscription snapshot
    $now = date('Y-m-d');
    $activeSub = dbRows("SELECT * FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? ORDER BY end_date DESC LIMIT 1", 'is', [$driverId, $now]);
    $pendingPay = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM driver_payments WHERE driver_id = ? AND status = 'pending'", 'i', [$driverId]);
    
    jsonResponse([
        'driver' => $driver[0],
        'bookings' => $bookings,
        'recent_rides' => $bookings,
        'stats' => !empty($stats) ? $stats[0] : ['total_rides' => 0, 'completed' => 0, 'cancelled' => 0, 'total_earnings' => 0, 'commission_due_count' => 0, 'commission_paid_count' => 0],
        'subscription' => !empty($activeSub) ? $activeSub[0] : null,
        'has_active_subscription' => !empty($activeSub),
        'pending_payments_count' => intval($pendingPay[0]['cnt'] ?? 0),
        'pending_payments_total' => floatval($pendingPay[0]['total'] ?? 0)
    ]);
}

// === DRIVER CONFIG (GET) ===
if ($action === 'driver-config') {
    $conn = db();
    $keys = ['driver_subscription_amount', 'driver_commission_per_ride', 'driver_otp_enabled', 'driver_otp_number', 'driver_ride_min_commission'];
    $qk = implode(',', array_map(function($k) use ($conn) { return "'" . $conn->real_escape_string($k) . "'"; }, $keys));
    $settings = [];
    $r = $conn->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($qk)");
    if ($r) while ($row = $r->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];
    jsonResponse([
        'driver_subscription_amount' => floatval($settings['driver_subscription_amount'] ?? 1000),
        'driver_commission_per_ride' => floatval($settings['driver_commission_per_ride'] ?? 200),
        'driver_otp_enabled' => intval($settings['driver_otp_enabled'] ?? 0),
        'driver_otp_number' => $settings['driver_otp_number'] ?? '',
        'driver_ride_min_commission' => floatval($settings['driver_ride_min_commission'] ?? 0)
    ]);
}

// === UPDATE DRIVER CONFIG ===
if ($action === 'update-driver-config' && $method === 'POST') {
    $conn = db();
    $updatable = ['driver_subscription_amount', 'driver_commission_per_ride', 'driver_otp_enabled', 'driver_otp_number', 'driver_ride_min_commission'];
    $updated = [];
    foreach ($updatable as $key) {
        if (isset($b[$key])) {
            $val = trim($b[$key]);
            $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param('sss', $key, $val, $val);
            $stmt->execute();
            $updated[$key] = $val;
        }
    }
    if (empty($updated)) jsonResponse(['error' => 'No config keys provided'], 400);
    jsonResponse(['success' => true, 'message' => 'Driver config updated', 'updated' => $updated]);
}

// === DRIVER TRIP HISTORY ===
if ($action === 'driver-trip-history') {
    $driverId = intval($_GET['driver_id'] ?? $b['driver_id'] ?? 0);
    if (!$driverId) jsonResponse(['error' => 'driver_id required'], 400);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(200, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $conn = db();
    $tq = $conn->prepare("SELECT COUNT(*) as c FROM app_bookings WHERE driver_id = ?");
    $tq->bind_param('i', $driverId); $tq->execute();
    $total = intval($tq->get_result()->fetch_assoc()['c']);
    $sql = "SELECT b.*, COALESCE(NULLIF(b.driver_name,''),d.name) as driver_name, COALESCE(NULLIF(b.driver_phone,''),d.phone) as driver_phone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
    $rows = dbRows($sql, 'i', [$driverId]);
    jsonResponse(['bookings' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => ceil($total / $limit)]);
}
