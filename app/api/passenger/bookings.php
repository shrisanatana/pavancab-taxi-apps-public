<?php
if ($method === 'GET' && ($action === 'my-bookings' || $action === 'my_bookings')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $userEmail = trim($_SESSION['user']['email'] ?? '');
    $userMobile = trim($_SESSION['user']['mobile'] ?? '');
    $conn = db();
    $bookings = dbRows("SELECT b.*, COALESCE(NULLIF(b.vehicle_model, ''), d.car_model, '') as vehicle_model FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.user_email = ? OR b.customer_phone = ? OR b.customer_phone LIKE ? ORDER BY b.id DESC LIMIT 50", 'sss', [$userEmail, $userMobile, '%' . substr(preg_replace('/\D/', '', $userMobile), -10) . '%']);
    foreach ($bookings as &$bk) {
        $bk['user_rating'] = isset($bk['user_rating']) ? intval($bk['user_rating']) : 0;
        $bk['user_review'] = $bk['user_review'] ?? '';
    }
    jsonResponse(['success' => true, 'bookings' => $bookings]);
}

if ($method === 'GET' && ($action === 'booking_detail' || $action === 'booking-detail')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($_GET['id'] ?? $b['id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
    $rows = dbRows("SELECT b.*, COALESCE(NULLIF(b.vehicle_model, ''), d.car_model, '') as vehicle_model FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.id = ? LIMIT 1", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    jsonResponse(['success' => true, 'booking' => $rows[0]]);
}

if ($method === 'POST' && ($action === 'cancel_booking' || $action === 'cancel-booking')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
    $conn = db();
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ?", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    $bk = $rows[0];
    
    // Capture cancellation reason for admin visibility
    $reason = trim(mb_substr($b['reason'] ?? '', 0, 120));
    dbExec("UPDATE app_bookings SET status = 'CANCELLED_BY_USER', special_notes = CONCAT(IFNULL(special_notes,''), ?) WHERE id = ?",
        'si', [$reason !== '' ? "\n[CANCELLED BY USER] Reason: $reason" : "\n[CANCELLED BY USER] No reason given", $bookingId]);
    if ($bk['driver_id']) {
        dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$bk['driver_id']]);
        try { sendFCMPushToDriver($bk['driver_id'], "Ride Cancelled", "Ride #{$bk['booking_ref']} has been cancelled by the passenger." . ($reason !== '' ? " Reason: $reason" : ""), ['type' => 'RIDE_CANCELLED']); } catch (Exception $e) {}
    }
    try { sendFCMPushToAdmins("Passenger Cancelled (#{$bk['booking_ref']})", ($bk['customer_name'] ?: 'Passenger') . " cancelled ride" . ($reason !== '' ? " \u2014 Reason: $reason" : ""), ['type' => 'RIDE_CANCELLED_USER', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    jsonResponse(['success' => true, 'message' => 'Booking cancelled']);
}

if ($method === 'POST' && ($action === 'rate_driver' || $action === 'rate-driver')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    $rating = intval($b['rating'] ?? 0);
    $comment = trim($b['comment'] ?? '');
    if (!$bookingId || $rating < 1 || $rating > 5) jsonResponse(['error' => 'booking_id and rating (1-5) required'], 400);
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ?", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    $bk = $rows[0];
    dbExec("UPDATE app_bookings SET user_rating = ?, user_review = ?, rated_at = NOW() WHERE id = ?", 'isi', [$rating, $comment, $bookingId]);
    if ($bk['driver_id']) {
        $stats = dbRows("SELECT AVG(user_rating) as avg_rating, COUNT(*) as cnt FROM app_bookings WHERE driver_id = ? AND user_rating IS NOT NULL AND user_rating > 0", 'i', [$bk['driver_id']]);
        if (!empty($stats)) {
            dbExec("UPDATE app_drivers SET rating = ?, total_ratings = ? WHERE id = ?", 'dii', [round($stats[0]['avg_rating'], 1), intval($stats[0]['cnt']), $bk['driver_id']]);
        }
        $cust = $bk['customer_name'] ?: 'A passenger';
        $review = $comment !== '' ? " \u201c$comment\u201d" : '';
        try { sendFCMPushToDriver(intval($bk['driver_id']), "\u2605 New Rating!", "$cust rated you $rating/5 for ride #{$bk['booking_ref']}$review", ['type' => 'NEW_RATING', 'booking_id' => strval($bookingId), 'url' => 'https://pavancab.com/app/']); } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => 'Thank you for your rating!']);
}

if ($method === 'POST' && ($action === 'respond_fare' || $action === 'respond-fare')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    $response = strtoupper(trim($b['response'] ?? ''));
    if (!$bookingId || !in_array($response, ['ACCEPTED', 'DECLINED'])) jsonResponse(['error' => 'booking_id and response (ACCEPTED/DECLINED) required'], 400);
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ?", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    $bk = $rows[0];
    if ($response === 'ACCEPTED') {
        $proposedFare = floatval($bk['proposed_fare'] ?? 0);
        if ($proposedFare <= 0) jsonResponse(['error' => 'No pending fare proposal'], 400);
        dbExec("UPDATE app_bookings SET total_fare = ?, fare_proposal_status = 'ACCEPTED' WHERE id = ?", 'di', [$proposedFare, $bookingId]);
    } else {
        dbExec("UPDATE app_bookings SET fare_proposal_status = 'DECLINED' WHERE id = ?", 'i', [$bookingId]);
    }
    jsonResponse(['success' => true, 'message' => $response === 'ACCEPTED' ? 'Fare accepted!' : 'Fare declined']);
}

if ($method === 'POST' && ($action === 'submit_report' || $action === 'submit-report')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    $reason = trim($b['reason'] ?? '');
    $details = trim($b['details'] ?? '');
    $reporterPhone = trim($b['reporter_phone'] ?? $_SESSION['user']['mobile'] ?? '');
    if (!$bookingId || !$reason) jsonResponse(['error' => 'booking_id and reason required'], 400);
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_ride_reports (booking_id, reporter_phone, reason, details, status) VALUES (?, ?, ?, ?, 'OPEN')");
    $stmt->bind_param('isss', $bookingId, $reporterPhone, $reason, $details);
    $stmt->execute();
    try { sendFCMPushToAdmins("Ride Report Filed", "A passenger filed a report for ride #$bookingId: $reason", ['type' => 'RIDE_REPORT', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    jsonResponse(['success' => true, 'message' => 'Report submitted. Our team will review it.']);
}

// RIDE OFFERS - all live driver offers for this passenger's PENDING, unassigned rides (max 5 per ride shown)
if ($method === 'GET' && ($action === 'ride-offers' || $action === 'ride_offers')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $userEmail = trim($_SESSION['user']['email'] ?? '');
    $userMobile = trim($_SESSION['user']['mobile'] ?? '');
    $clean10 = substr(preg_replace('/\D/', '', $userMobile), -10);
    
    // Only offers on rides this user owns that are still open
    $offers = dbRows("SELECT o.* FROM app_driver_ride_offers o
        INNER JOIN app_bookings b ON o.booking_id = b.id
        WHERE o.status = 'PENDING'
        AND b.status = 'PENDING' AND b.driver_id IS NULL
        AND (b.user_email = ? OR RIGHT(REPLACE(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), '-', ''), 10) = ?)
        ORDER BY o.booking_id ASC, o.created_at ASC LIMIT 100", 'ss', [$userEmail, $clean10]);
    
    // Keep only the FIRST 5 offers per booking (in arrival order)
    $perBooking = [];
    $filtered = [];
    foreach ($offers as $o) {
        $bid = intval($o['booking_id']);
        $perBooking[$bid] = ($perBooking[$bid] ?? 0) + 1;
        if ($perBooking[$bid] <= 5) $filtered[] = $o;
    }
    jsonResponse(['success' => true, 'offers' => $filtered]);
}

// ACCEPT OFFER - passenger picks a driver offer; that driver is assigned and fare updated
if ($method === 'POST' && ($action === 'accept-offer' || $action === 'accept_offer')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $offerId = intval($b['offer_id'] ?? 0);
    if (!$offerId) jsonResponse(['error' => 'offer_id required'], 400);
    
    $rows = dbRows("SELECT * FROM app_driver_ride_offers WHERE id = ? AND status = 'PENDING' LIMIT 1", 'i', [$offerId]);
    if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Offer no longer available'], 400);
    $offer = $rows[0];
    
    $bkRows = dbRows("SELECT * FROM app_bookings WHERE id = ? LIMIT 1", 'i', [intval($offer['booking_id'])]);
    if (empty($bkRows)) jsonResponse(['success' => false, 'error' => 'Ride not found'], 404);
    $bk = $bkRows[0];
    if ($bk['status'] !== 'PENDING' || !empty($bk['driver_id'])) jsonResponse(['success' => false, 'error' => 'Ride is no longer available'], 400);
    
    $bookingId = intval($bk['id']);
    $driverId = intval($offer['driver_id']);
    $driverName = $offer['driver_name'] ?: 'Driver';
    $driverPhone = $offer['driver_phone'];
    $newFare = floatval($offer['offer_amount']);
    $vetModelRow = dbRows("SELECT car_model FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    $vetModel = !empty($vetModelRow) ? ($vetModelRow[0]['car_model'] ?? '') : '';
    
    // Assign exactly like self-assign
    dbExec("UPDATE app_bookings SET status = 'ASSIGNED', driver_id = ?, driver_name = ?, driver_phone = ?, vehicle_number = ?, vehicle_model = ?, total_fare = ?, user_offered_fare = ?, driver_decision = 'ACCEPTED', fare_proposal_status = NULL WHERE id = ?",
        'issssddi', [$driverId, $driverName, $driverPhone, $offer['vehicle_number'], $vetModel, $newFare, $newFare, $bookingId]);
    
    dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
    dbExec("UPDATE app_driver_ride_offers SET status = 'ACCEPTED' WHERE id = ?", 'i', [$offerId]);
    dbExec("UPDATE app_driver_ride_offers SET status = 'DECLINED' WHERE booking_id = ? AND id != ? AND status = 'PENDING'", 'ii', [$bookingId, $offerId]);
    try { dbExec("INSERT IGNORE INTO app_driver_declined_rides (driver_id, booking_id) VALUES (?, ?)", 'ii', [$driverId, $bookingId]); } catch (Exception $e) {}
    
    // Notify everyone
    try { sendFCMPushToDriver($driverId, "🎉 Your Offer Was Accepted!", "Passenger accepted your ₹" . intval($newFare) . " offer for ride #{$bk['booking_ref']}. Check My Rides for details.", ['type' => 'OFFER_ACCEPTED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    try { sendMetaWhatsApp($driverPhone, "🎉 *OFFER ACCEPTED!*\n\nThe passenger accepted your offer of *₹" . intval($newFare) . "* for ride *#{$bk['booking_ref']}*.\n\n📍 Pickup: {$bk['pickup_location']}\n📍 Drop: {$bk['drop_location']}\n🗓 " . formatIndianDateTime($bk['pickup_date'], $bk['pickup_time']) . "\n\nOpen the Driver App — the ride is now in your My Rides."); } catch (Exception $e) {}
    try { broadcastRideLifecycleFCM('DRIVER_ASSIGNED', $bookingId); } catch (Exception $e) {}
    
    jsonResponse(['success' => true, 'message' => "Offer accepted! $driverName will do your ride for ₹" . intval($newFare)]);
}

if ($method === 'GET' && $action === 'notification-history') {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $userId = intval($_SESSION['user']['id']);
    $email = trim($_SESSION['user']['email'] ?? '');
    $notifications = dbRows("SELECT * FROM app_notifications WHERE user_id = ? OR LOWER(recipient_email) = ? ORDER BY id DESC LIMIT 50", 'si', [$userId, strtolower($email)]);
    jsonResponse(['success' => true, 'notifications' => $notifications]);
}

if ($method === 'POST' && ($action === 'create_booking' || $action === 'create-booking' || $action === 'book_ride')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $customerName = trim($b['customer_name'] ?? $_SESSION['user']['name'] ?? '');
    $customerPhone = trim($b['customer_phone'] ?? $_SESSION['user']['mobile'] ?? '');
    $tripType = trim($b['trip_type'] ?? 'one_way');
    $pickupLocation = trim($b['pickup_location'] ?? '');
    $dropLocation = trim($b['drop_location'] ?? '');
    $pickupDate = trim($b['pickup_date'] ?? '');
    $pickupTime = trim($b['pickup_time'] ?? '');
    $cabType = trim($b['cab_type'] ?? 'Sedan');
    $totalFare = floatval($b['total_fare'] ?? 0);
    $baseFare = floatval($b['base_fare'] ?? 0);
    $fareOffered = floatval($b['fare_offered'] ?? 0);
    $specialNotes = trim($b['special_notes'] ?? '');

    if (!$pickupLocation || !$dropLocation || !$pickupDate || !$pickupTime || $totalFare <= 0) {
        jsonResponse(['error' => 'pickup_location, drop_location, pickup_date, pickup_time, and total_fare are required'], 400);
    }

    if ($baseFare <= 0) $baseFare = $totalFare;
    if ($fareOffered <= 0) $fareOffered = 0;

    $bookingRef = 'GTA-' . date('ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $userEmail = trim($_SESSION['user']['email'] ?? '');

    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_bookings (booking_ref, user_email, customer_name, customer_phone, trip_type, pickup_location, drop_location, pickup_date, pickup_time, cab_type, total_fare, base_fare, user_offered_fare, special_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
    $stmt->bind_param('ssssssssssddds', $bookingRef, $userEmail, $customerName, $customerPhone, $tripType, $pickupLocation, $dropLocation, $pickupDate, $pickupTime, $cabType, $totalFare, $baseFare, $fareOffered, $specialNotes);
    $stmt->execute();
    $newId = $conn->insert_id;

    try { sendFCMPushToAdmins("New Booking!", "Ride #$bookingRef from $pickupLocation to $dropLocation at $pickupTime for ₹$totalFare", ['type' => 'NEW_BOOKING', 'booking_id' => strval($newId), 'booking_ref' => $bookingRef]); } catch (Exception $e) {}

    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ?", 'i', [$newId]);
    jsonResponse(['success' => true, 'message' => "Booking #$bookingRef created!", 'booking' => !empty($rows) ? $rows[0] : null, 'booking_id' => $newId]);
}

if ($method === 'GET' && $action === 'check-booking-status') {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($_GET['booking_id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'booking_id required'], 400);
    $rows = dbRows("SELECT id, booking_ref, status, driver_name, driver_phone, driver_decision, vehicle_number FROM app_bookings WHERE id = ? LIMIT 1", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    jsonResponse(['success' => true, 'booking' => $rows[0]]);
}

if ($method === 'POST' && ($action === 'complete-ride')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'Booking ID required'], 400);
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ?", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    dbExec("UPDATE app_bookings SET status = 'COMPLETED' WHERE id = ?", 'i', [$bookingId]);
    jsonResponse(['success' => true, 'message' => 'Ride completed']);
}

if ($method === 'POST' && ($action === 'boost-fare')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    $boostAmount = floatval($b['boost_amount'] ?? 0);
    if (!$bookingId || $boostAmount <= 0) jsonResponse(['error' => 'booking_id and boost_amount required'], 400);
    $conn = db();
    $stmt = $conn->prepare("UPDATE app_bookings SET total_fare = total_fare + ? WHERE id = ?");
    $stmt->bind_param('di', $boostAmount, $bookingId);
    $stmt->execute();
    jsonResponse(['success' => true, 'message' => 'Fare boosted by ₹' . $boostAmount]);
}

if ($method === 'POST' && ($action === 'rate-ride' || $action === 'rate_ride')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $bookingId = intval($b['booking_id'] ?? 0);
    $rating = intval($b['rating'] ?? 0);
    $reviewText = trim($b['review_text'] ?? '');
    if (!$bookingId || $rating < 1 || $rating > 5) jsonResponse(['error' => 'booking_id and rating (1-5) required'], 400);
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ?", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    $bk = $rows[0];
    dbExec("UPDATE app_bookings SET user_rating = ?, user_review = ?, rated_at = NOW() WHERE id = ?", 'isi', [$rating, $reviewText, $bookingId]);
    if ($bk['driver_id']) {
        $stats = dbRows("SELECT AVG(user_rating) as avg_rating, COUNT(*) as cnt FROM app_bookings WHERE driver_id = ? AND user_rating IS NOT NULL AND user_rating > 0", 'i', [$bk['driver_id']]);
        if (!empty($stats)) {
            dbExec("UPDATE app_drivers SET rating = ?, total_ratings = ? WHERE id = ?", 'dii', [round($stats[0]['avg_rating'], 1), intval($stats[0]['cnt']), $bk['driver_id']]);
        }
    }
    jsonResponse(['success' => true, 'message' => 'Thank you for your rating!']);
}
