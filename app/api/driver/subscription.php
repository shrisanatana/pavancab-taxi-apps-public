<?php
// Included by index.php

// Require driver auth for subscription endpoints
if (in_array($action, ['subscription-status', 'create-order', 'verify-payment', 'payment-history', 'cancel-subscription', 'my-bookings', 'booking-detail', 'respond', 'trip-status', 'earnings', 'quick-rides', 'self-assign', 'decline-ride', 'submit-offer', 'rate-passenger'])) {
    if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
        jsonResponse(['error' => 'Driver auth required'], 401);
    }
}

// Block revoked drivers from offering or accepting fares (viewing remains allowed)
if (in_array($action, ['submit-offer', 'self-assign', 'respond'])) {
    $apCheck = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [intval($_SESSION['driver']['id'])]);
    if (empty($apCheck) || intval($apCheck[0]['is_approved'] ?? 0) !== 1) {
        jsonResponse(['success' => false, 'error' => 'REVOKED', 'revoked' => true, 'message' => 'Your driver account has been revoked. You cannot accept or offer fares. Please contact the admin to be re-approved.']);
    }
}

// SUBSCRIPTION STATUS
if ($action === 'subscription-status') {
    $driverId = intval($_SESSION['driver']['id']);
    $now = date('Y-m-d');
    $active = dbRows("SELECT id, start_date, end_date, amount FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? ORDER BY id DESC LIMIT 1", 'is', [$driverId, $now]);
    $hasActive = !empty($active);
    $endDate = $hasActive ? $active[0]['end_date'] : null;
    $daysLeft = $hasActive ? max(0, (strtotime($endDate) - strtotime($now)) / 86400) : 0;
    
    $pendingPayments = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM driver_payments WHERE driver_id = ? AND status = 'pending'", 'i', [$driverId]);
    
    $settings = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount', 'driver_commission_per_ride')");
    $settingsMap = [];
    foreach ($settings as $s) $settingsMap[$s['setting_key']] = floatval($s['setting_value']);
    
    jsonResponse([
        'success' => true,
        'is_subscribed' => $hasActive,
        'has_active_subscription' => $hasActive,
        'has_password' => !empty($_SESSION['driver']['has_password']),
        'subscription' => $hasActive ? $active[0] : null,
        'end_date' => $endDate ?: '',
        'days_left' => intval($daysLeft),
        'pending_payments_count' => intval($pendingPayments[0]['cnt'] ?? 0),
        'pending_payments_total' => floatval($pendingPayments[0]['total'] ?? 0),
        'pending_payments' => intval($pendingPayments[0]['cnt'] ?? 0),
        'pending_amount' => floatval($pendingPayments[0]['total'] ?? 0),
        'subscription_amount' => $settingsMap['driver_subscription_amount'] ?? 1000,
        'commission_per_ride' => $settingsMap['driver_commission_per_ride'] ?? 200,
        'can_accept' => $hasActive || (intval($pendingPayments[0]['cnt'] ?? 0) === 0)
    ]);
}

// CANCEL SUBSCRIPTION
if ($action === 'cancel-subscription' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $now = date('Y-m-d');
    $active = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$driverId, $now]);
    if (empty($active)) jsonResponse(['success' => false, 'error' => 'No active subscription to cancel']);
    dbExec("UPDATE driver_subscriptions SET status = 'cancelled' WHERE id = ?", 'i', [$active[0]['id']]);
    dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [$driverId]);
    jsonResponse(['success' => true, 'message' => 'Subscription cancelled. You can still use it until the end date.']);
}

// MY BOOKINGS - filter rides >1hr in future (unless assigned to this driver)
if ($action === 'my-bookings') {
    $driverId = intval($_SESSION['driver']['id']);
    $driverPhone = $_SESSION['driver']['phone'] ?? '';
    $cleanPhone10 = '';
    if ($driverPhone) $cleanPhone10 = substr(preg_replace('/\D/', '', $driverPhone), -10);
    
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $oneHourLater = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    if ($cleanPhone10) {
        $rows = dbRows("SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, '') as vehicle_number FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE (b.driver_id = ? OR b.driver_id IN (SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?)) AND NOT (b.status IN ('PENDING') AND CONCAT(b.pickup_date, ' ', b.pickup_time) > ?) ORDER BY FIELD(b.status, 'ASSIGNED', 'ACCEPTED', 'IN_TRANSIT', 'ON_TRIP', 'PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED_BY_USER', 'CANCELLED_BY_ADMIN') ASC, b.pickup_date DESC, b.pickup_time DESC LIMIT 100", 'iss', [$driverId, $cleanPhone10, $oneHourLater]);
    } else {
        $rows = dbRows("SELECT b.*, COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, '') as vehicle_number FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.driver_id = ? AND NOT (b.status IN ('PENDING') AND CONCAT(b.pickup_date, ' ', b.pickup_time) > ?) ORDER BY FIELD(b.status, 'ASSIGNED', 'ACCEPTED', 'IN_TRANSIT', 'ON_TRIP', 'PENDING', 'CONFIRMED', 'COMPLETED') ASC, b.pickup_date DESC, b.pickup_time DESC LIMIT 100", 'is', [$driverId, $oneHourLater]);
    }
    jsonResponse(['success' => true, 'bookings' => $rows]);
}

// QUICK RIDES - PENDING rides within ±1hr window that need a driver.
// URGENT RIDE RELEASE TIMING (handled here + cron):
//   - First 3 minutes after booking: ADMIN/TEAM ONLY window (drivers cannot see the ride)
//   - PREMIUM (subscribed) drivers: released to them after just 2 minutes (1 min before others)
//   - Normal drivers: after 3 minutes
//   - Admin can still assign anytime until a driver accepts.
if ($action === 'quick-rides') {
    $driverId = intval($_SESSION['driver']['id']);
    $isPremium = driverHasActiveSubscription($driverId);
    $maxOffers = max(1, intval(getAppSetting('driver_max_offers_per_ride', 5)));
    
    // Rides appear to drivers IMMEDIATELY (no admin-only window).
    // The 2min/3min delay now applies ONLY to the push notification (see each-min-cron.php).
    // Drivers can counter-offer on ANY pending unassigned ride (no pickup-time window).
    $rows = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.trip_type, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.cab_type, b.total_fare, b.base_fare, b.user_offered_fare, b.status, b.special_notes, b.created_at,
                    (b.fare_proposal_status = 'PENDING') as admin_proposal_pending
                    FROM app_bookings b
                    WHERE b.status = 'PENDING' AND b.driver_id IS NULL
                    AND (b.is_frozen IS NULL OR b.is_frozen = 0)
                    AND b.id NOT IN (SELECT booking_id FROM app_driver_declined_rides WHERE driver_id = ?)
                    ORDER BY b.created_at DESC LIMIT 20", 'i', [$driverId]);
    
    // Attach driver's own pending offer amount, offer count, and offer window state
    foreach ($rows as &$r) {
        $r['admin_proposal_pending'] = intval($r['admin_proposal_pending'] ?? 0);
        $myOffer = dbRows("SELECT id, offer_amount FROM app_driver_ride_offers WHERE booking_id = ? AND driver_id = ? AND status = 'PENDING' LIMIT 1", 'ii', [intval($r['id']), $driverId]);
        $r['my_offer_amount'] = !empty($myOffer) ? floatval($myOffer[0]['offer_amount']) : 0;
        $countRow = dbRows("SELECT COUNT(*) as cnt FROM app_driver_ride_offers WHERE booking_id = ? AND status = 'PENDING'", 'i', [intval($r['id'])]);
        $r['offer_count'] = intval($countRow[0]['cnt'] ?? 0);
        $r['max_offers'] = $maxOffers;
        // Offering window CLOSED when 5 offers already reached
        $r['offer_closed'] = $r['offer_count'] >= $maxOffers ? 1 : 0;
        // can_offer = score-able unless admin fare negotiation pending, offer window closed, or driver already offered
        $r['can_offer'] = ($r['admin_proposal_pending'] == 0 && $r['offer_closed'] == 0 && $r['my_offer_amount'] <= 0) ? 1 : 0;
        // Countdown window: subscribed/premium = 2 min, normal = 3 min (1 min extra chance by subscribing).
        // The card shows a countdown and stays DISABLED until the window elapses after the booking was created.
        // Dispatch can set a 2-min offer release window (driver_release_ends_at) which extends the lock if later.
        $nominal = $isPremium ? 120 : 180;
        $createdEpoch = isset($r['created_at']) ? strtotime($r['created_at']) : time();
        $endAt = $createdEpoch + $nominal;
        if (!empty($r['driver_release_ends_at'])) {
            $ra = strtotime($r['driver_release_ends_at']);
            if ($ra !== false && $ra > $endAt) $endAt = $ra;
        }
        $remaining = max(0, $endAt - time());
        // Keep app countdown in sync: target = now + remaining.
        $r['window_seconds'] = $remaining;
        $r['created_at_epoch'] = time();
        $r['window_remaining'] = $remaining;
        $r['window_active'] = $remaining > 0 ? 1 : 0;
    }
    unset($r);
    
    $commission = driverCommissionPerRide();
    $balance = driverWalletBalance($driverId);
    
    jsonResponse([
        'success' => true,
        'rides' => $rows,
        'is_premium' => $isPremium,
        'is_subscribed' => $isPremium,
        'commission_per_ride' => $commission,
        'wallet_balance' => $balance,
        'min_wallet_required' => $commission,
        'can_accept' => ($isPremium || $balance >= $commission)
    ]);
}

// DECLINE RIDE - hide this PENDING ride from THIS driver only.
// Other drivers can still see & accept it; admin/team can always assign manually.
if ($action === 'decline-ride' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $bookingId = intval($b['booking_id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'booking_id required']);
    
    $bk = dbRows("SELECT id FROM app_bookings WHERE id = ? AND status = 'PENDING' LIMIT 1", 'i', [$bookingId]);
    if (empty($bk)) jsonResponse(['success' => true, 'message' => 'Ride removed']);
    
    dbExec("INSERT IGNORE INTO app_driver_declined_rides (driver_id, booking_id) VALUES (?, ?)", 'ii', [$driverId, $bookingId]);
    jsonResponse(['success' => true, 'message' => 'Ride declined']);
}

// SUBMIT FARE OFFER - driver bids a fare for a PENDING ride within the 1hr window.
// Only allowed when dispatch/admin has NOT already sent the user a boosted-fare proposal.
if ($action === 'submit-offer' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $driverName = $_SESSION['driver']['name'] ?? '';
    $driverPhone = $_SESSION['driver']['phone'] ?? '';
    $bookingId = intval($b['booking_id'] ?? 0);
    $amount = floatval($b['amount'] ?? 0);
    $note = trim(mb_substr($b['note'] ?? '', 0, 300));
    
    if (!$bookingId || $amount <= 0) jsonResponse(['success' => false, 'error' => 'booking_id and amount required']);
    
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ? AND status = 'PENDING' AND driver_id IS NULL LIMIT 1", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Ride not available']);
    $bk = $rows[0];

    // COUNTDOWN WINDOW — ride is disabled for drivers until the release window elapses.
    $subNow = driverHasActiveSubscription($driverId);
    $winSec = $subNow ? 120 : 180;
    $createdEpoch = strtotime($bk['created_at'] ?? 'now');
    $remainingSec = ($createdEpoch + $winSec) - time();
    if ($remainingSec > 0) {
        jsonResponse(['success' => false, 'error' => "This ride opens in " . intval(ceil($remainingSec / 60)) . " min" . ($subNow ? "" : " (subscribe to open 1 min sooner)") . ". Please wait.", 'window_active' => true, 'window_remaining' => $remainingSec]);
    }
    
    $pickupDT = strtotime($bk['pickup_date'] . ' ' . $bk['pickup_time']);
    $now = time();
    if ($pickupDT === false) {
        jsonResponse(['success' => false, 'error' => 'Invalid pickup date/time']);
    }
    
    // MAX 5 OFFERS per ride — drivers can counter-offer on ANY pending ride
    $maxOffers = max(1, intval(getAppSetting('driver_max_offers_per_ride', 5)));
    if (($bk['fare_proposal_status'] ?? '') === 'PENDING') {
        jsonResponse(['success' => false, 'error' => 'A fare is already being negotiated with the passenger for this ride']);
    }
    
    $countRow = dbRows("SELECT COUNT(*) as cnt FROM app_driver_ride_offers WHERE booking_id = ? AND status = 'PENDING'", 'i', [$bookingId]);
    $offerCount = intval($countRow[0]['cnt'] ?? 0);
    $myExisting = dbRows("SELECT id, status FROM app_driver_ride_offers WHERE booking_id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
    $alreadyOffered = !empty($myExisting) && $myExisting[0]['status'] === 'PENDING';
    if ($offerCount >= $maxOffers && !$alreadyOffered) {
        jsonResponse(['success' => false, 'error' => "This ride already has $maxOffers offers. Opening window closed.", 'offer_closed' => true, 'max_offers' => $maxOffers]);
    }
    
    // Freeze check — admin/team can freeze a ride; it stays out of driver reach until unfrozen.
    if (intval($bk['is_frozen'] ?? 0) === 1) {
        jsonResponse(['success' => false, 'error' => 'This ride is frozen by dispatch and not available to drivers.']);
    }

    // NOTE: No active-ride limit — one driver may be assigned to many rides (future + now).

    $sub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= CURDATE() LIMIT 1", 'i', [$driverId]);
    if (empty($sub)) {
        $pending = dbRows("SELECT COUNT(*) as cnt FROM driver_payments WHERE driver_id = ? AND status = 'pending' LIMIT 1", 'i', [$driverId]);
        if (intval($pending[0]['cnt'] ?? 0) > 0) {
            jsonResponse(['success' => false, 'error' => 'You have pending commission payments. Pay first before offering.', 'requires_payment' => true]);
        }
    }

    // WALLET GATE — unsubscribed drivers must have the minimum commission amount in wallet to offer a fare.
    // Mirrors self-assign: no subscription and no wallet balance = cannot offer.
    $isSubscribedNow = driverHasActiveSubscription($driverId);
    $commissionNow = driverCommissionPerRide();
    $balanceNow = driverWalletBalance($driverId);
    if (!$isSubscribedNow && $balanceNow < $commissionNow) {
        jsonResponse([
            'success' => false,
            'error' => "You don't have \u20B9" . intval($commissionNow) . " minimum in your wallet to offer on this ride. Add money to wallet or subscribe.",
            'requires_wallet_topup' => true,
            'min_required' => $commissionNow,
            'balance' => $balanceNow,
            'current_balance' => $balanceNow
        ]);
    }
    
    $plate = '';
    if (!$driverName) {
        $dRow = dbRows("SELECT name, plate_number FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
        if (!empty($dRow)) {
            if (!$driverName) $driverName = $dRow[0]['name'] ?? '';
            $plate = $dRow[0]['plate_number'] ?? '';
        }
    }
    
    // Upsert: one live offer per driver per ride
    if (!empty($myExisting) && $myExisting[0]['status'] === 'ACCEPTED') {
        jsonResponse(['success' => false, 'error' => 'Your offer was already accepted']);
    }
    if (!empty($myExisting)) {
        dbExec("UPDATE app_driver_ride_offers SET offer_amount = ?, offer_note = ?, status = 'PENDING' WHERE id = ?", 'dsi', [$amount, $note, $myExisting[0]['id']]);
    } else {
        dbExec("INSERT INTO app_driver_ride_offers (booking_id, driver_id, driver_name, driver_phone, vehicle_number, offer_amount, offer_note, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')",
            'iisssds', [$bookingId, $driverId, $driverName, $driverPhone, $plate, $amount, $note]);
    }
    
    // Notify passenger
    $ref = $bk['booking_ref'];
    try {
        $tokens = [];
        if (!empty($bk['user_email'])) $tokens = array_merge($tokens, getFCMTokensByEmail($bk['user_email']));
        if (!empty($bk['customer_phone'])) $tokens = array_merge($tokens, getFCMTokensByPhone($bk['customer_phone']));
        $tokens = array_values(array_unique(array_filter(array_map('trim', $tokens))));
        if (!empty($tokens)) {
            sendFCMPush($tokens, "💰 New Fare Offer: ₹" . intval($amount),
                "Driver {$driverName} offers to do your ride #$ref for ₹" . intval($amount) . " (current: ₹" . intval($bk['total_fare']) . "). Open the app to accept or wait.",
                ['type' => 'DRIVER_OFFER', 'booking_id' => strval($bookingId), 'offer_amount' => strval($amount), 'url' => 'https://pavancab.com/app/rides.php?id=' . $bookingId]);
        }
    } catch (Exception $e) {}
    try {
        if (!empty($bk['customer_phone'])) {
            sendMetaWhatsApp($bk['customer_phone'], "💰 *New Fare Offer*\n\nHi " . ($bk['customer_name'] ?: 'there') . ",\n\nDriver *$driverName* offered to do your ride *#$ref* for *₹" . intval($amount) . "*.\nCurrent fare: ₹" . intval($bk['total_fare']) . "\n\nOpen the PavanCab app to see all offers — you can accept one or wait for a driver at the current price.");
        }
    } catch (Exception $e) {}
    try { sendFCMPushToAdmins("💡 Driver Offer (#$ref)", "$driverName offered ₹" . intval($amount) . " for ride #$ref (listed ₹" . intval($bk['total_fare']) . ").", ['type' => 'DRIVER_OFFER', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    
    jsonResponse([
        'success' => true,
        'message' => "Offer of ₹" . intval($amount) . " sent to passenger",
        'is_subscribed' => driverHasActiveSubscription($driverId),
        'commission_amount' => driverCommissionPerRide(),
        'offer_count' => $offerCount + ($alreadyOffered ? 0 : 1),
        'max_offers' => $maxOffers,
        'offer_closed' => (($offerCount + ($alreadyOffered ? 0 : 1)) >= $maxOffers) ? 1 : 0
    ]);
}

// SELF-ASSIGN - driver grabs a PENDING ride directly (commission applies — assigned_by = 'self')
if ($action === 'self-assign' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $driverName = $_SESSION['driver']['name'] ?? '';
    $driverPhone = $_SESSION['driver']['phone'] ?? '';
    $bookingId = intval($b['booking_id'] ?? 0);
    
    if (!$bookingId) jsonResponse(['error' => 'booking_id required']);
    
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ? AND status = 'PENDING' AND driver_id IS NULL LIMIT 1", 'i', [$bookingId]);
    if (empty($rows)) jsonResponse(['error' => 'Ride not available (already assigned or not found)']);
    $bk = $rows[0];

    // Freeze check — admin/team can freeze a ride; it stays out of driver reach until unfrozen.
    if (intval($bk['is_frozen'] ?? 0) === 1) {
        jsonResponse(['success' => false, 'error' => 'This ride is frozen by dispatch and not available to drivers.']);
    }

    // COUNTDOWN WINDOW — ride is disabled for drivers until the release window elapses.
    $subNowSelf = driverHasActiveSubscription($driverId);
    $winSecSelf = $subNowSelf ? 120 : 180;
    $createdEpochSelf = strtotime($bk['created_at'] ?? 'now');
    $remainingSecSelf = ($createdEpochSelf + $winSecSelf) - time();
    if ($remainingSecSelf > 0) {
        jsonResponse(['success' => false, 'error' => "This ride opens in " . intval(ceil($remainingSecSelf / 60)) . " min. Please wait.", 'window_active' => true, 'window_remaining' => $remainingSecSelf]);
    }

    // NOTE: No active-ride limit — one driver may be assigned to many rides (future + now).
    // WALLET GATE — unsubscribed drivers need commission amount in wallet to self-accept.
    // Admin/team-assigned rides are commission-free; only self-grabbed rides are charged.
    $isSubscribedNow = driverHasActiveSubscription($driverId);
    $commission = driverCommissionPerRide();
    $balance = driverWalletBalance($driverId);
    if (!$isSubscribedNow && $balance < $commission) {
        // HTTP 200 with success:false so apps can read the payload (403 breaks app networking)
        jsonResponse([
            'success' => false,
            'error' => "You don't have \u20B9" . intval($commission) . " minimum in your wallet to get this ride. Add money to wallet to accept rides.",
            'requires_wallet_topup' => true,
            'min_required' => $commission,
            'balance' => $balance,
            'current_balance' => $balance
        ]);
    }
    
    // Get driver details
    $driverRows = dbRows("SELECT name, plate_number, car_model FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (!empty($driverRows)) {
        if (!$driverName) $driverName = $driverRows[0]['name'] ?? '';
    }
    
    // Assign — mark as SELF-assigned (commissionable)
    $vn = $driverRows[0]['plate_number'] ?? '';
    $vm = $driverRows[0]['car_model'] ?? '';
    $conn = db();
    $stmt = $conn->prepare("UPDATE app_bookings SET status = 'ASSIGNED', driver_id = ?, driver_name = ?, driver_phone = ?, driver_decision = 'ACCEPTED', vehicle_number = ?, vehicle_model = ?, assigned_by = 'self' WHERE id = ?");
    $stmt->bind_param('isssssi', $driverId, $driverName, $driverPhone, $vn, $vm, $bookingId);
    $stmt->execute();
    
    dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
    
    // This driver won the ride at the listed price — close all other pending offers for it
    try { dbExec("UPDATE app_driver_ride_offers SET status = 'DECLINED' WHERE booking_id = ? AND driver_id != ? AND status = 'PENDING'", 'ii', [$bookingId, $driverId]); } catch (Exception $e) {}
    try { dbExec("INSERT IGNORE INTO app_driver_declined_rides (driver_id, booking_id) VALUES (?, ?)", 'ii', [$driverId, $bookingId]); } catch (Exception $e) {}
    
    // Notify
    try { sendMetaWhatsApp($bk['customer_phone'], "Your ride #{$bk['booking_ref']} has been accepted by driver $driverName. Pickup: {$bk['pickup_location']}. Driver is on the way!"); } catch (Exception $e) {}
    try { broadcastRideLifecycleFCM('DRIVER_ASSIGNED', $bookingId); } catch (Exception $e) {}
    
    jsonResponse(['success' => true, 'message' => 'Ride accepted!', 'booking_id' => $bookingId, 'assigned_by' => 'self']);
}

// BOOKING DETAIL
if ($action === 'booking-detail') {
    $driverId = intval($_SESSION['driver']['id']);
    $bookingId = intval($b['id'] ?? $_GET['id'] ?? 0);
    if (!$bookingId) jsonResponse(['error' => 'Booking ID required']);
    $rows = dbRows("SELECT b.*, COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, '') as vehicle_number FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.id = ? AND (b.driver_id = ? OR ? = 0) LIMIT 1", 'iii', [$bookingId, $driverId, $driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    jsonResponse(['success' => true, 'booking' => $rows[0]]);
}

// RESPOND TO BOOKING
if ($action === 'respond' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $driverName = $_SESSION['driver']['name'] ?? '';
    $driverPhone = $_SESSION['driver']['phone'] ?? '';
    $bookingId = intval($b['booking_id'] ?? 0);
    $decision = strtoupper(trim($b['decision'] ?? ''));
    
    if (!$bookingId || !in_array($decision, ['ACCEPT', 'REJECT'])) {
        jsonResponse(['error' => 'booking_id and decision (ACCEPT/REJECT) required']);
    }
    
    $rows = dbRows("SELECT id, status, driver_id, customer_name, customer_phone, booking_ref FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found or not assigned to you'], 404);
    
    $booking = $rows[0];
    if ($booking['status'] !== 'ASSIGNED' && $booking['status'] !== 'ACCEPTED') {
        jsonResponse(['error' => 'Booking is in ' . $booking['status'] . ' status, cannot respond']);
    }
    
    if ($decision === 'ACCEPT') {
        // Admin/team-assigned ride — NO commission for the driver (assigned_by stays 'admin')
        $hasRide = dbRows("SELECT id FROM app_bookings WHERE driver_id = ? AND status IN ('IN_TRANSIT','ON_TRIP','ARRIVED') LIMIT 1", 'i', [$driverId]);
        if (!empty($hasRide)) {
            jsonResponse(['error' => 'You have an active ride. Complete it first.']);
        }
        dbExec("UPDATE app_bookings SET status = 'ACCEPTED', driver_decision = 'ACCEPTED' WHERE id = ?", 'i', [$bookingId]);
        dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
        try { sendMetaWhatsApp($booking['customer_phone'], "UPDATE: Your ride #{$booking['booking_ref']} has been accepted by driver $driverName. Driver will pick you up soon!"); } catch (Exception $e) {}
        try { broadcastRideLifecycleFCM('DRIVER_ACCEPTED', $bookingId); } catch (Exception $e) {}
    } else {
        dbExec("UPDATE app_bookings SET status = 'PENDING', driver_id = NULL, driver_name = NULL, driver_phone = NULL, driver_decision = 'REJECTED' WHERE id = ?", 'i', [$bookingId]);
        dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
        try { sendFCMPushToAdmins("Driver Declined Ride!", "Driver $driverName declined ride #{$booking['booking_ref']}. Status reset to PENDING.", ['type' => 'DRIVER_DECLINED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => "Booking $decision'd successfully"]);
}

// TRIP STATUS
if ($action === 'trip-status' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $driverName = $_SESSION['driver']['name'] ?? '';
    $driverPhone = $_SESSION['driver']['phone'] ?? '';
    $bookingId = intval($b['booking_id'] ?? 0);
    $status = strtoupper(trim($b['status'] ?? ''));
    
    // Accept common aliases drivers/apps may send
    if (in_array($status, ['ON_TRIP', 'STARTED', 'TRIP_STARTED'])) $status = 'IN_TRANSIT';
    
    if (!$bookingId || !$status) jsonResponse(['error' => 'booking_id and status required']);
    
    $rows = dbRows("SELECT * FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Booking not found'], 404);
    $booking = $rows[0];
    
    // Drivers may skip steps (e.g. start trip directly after accepting, or complete without marking arrival)
    // Only guard against going BACKWARDS or completing twice.
    $validTransitions = [
        'ACCEPTED'   => ['ASSIGNED', 'ACCEPTED'],
        'ARRIVED'    => ['ASSIGNED', 'ACCEPTED', 'ARRIVED'],
        'IN_TRANSIT' => ['ASSIGNED', 'ACCEPTED', 'ARRIVED', 'IN_TRANSIT', 'ON_TRIP'],
        'COMPLETED'  => ['ASSIGNED', 'ACCEPTED', 'ARRIVED', 'IN_TRANSIT', 'ON_TRIP']
    ];
    $allowed = $validTransitions[$status] ?? [];
    if (!in_array($booking['status'], $allowed)) {
        jsonResponse(['error' => 'Ride is already ' . $booking['status'] . ', cannot change to ' . $status]);
    }
    if ($booking['status'] === 'COMPLETED') {
        jsonResponse(['error' => 'Ride already completed']);
    }
    
    dbExec("UPDATE app_bookings SET status = ? WHERE id = ?", 'si', [$status, $bookingId]);
    
    if ($status === 'COMPLETED') dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
    else dbExec("UPDATE app_drivers SET status = 'on_trip' WHERE id = ?", 'i', [$driverId]);
    
    if ($status === 'ARRIVED') {
        try { sendMetaWhatsApp($booking['customer_phone'], "UPDATE: Driver $driverName has arrived at pickup for ride #{$booking['booking_ref']}."); } catch (Exception $e) {}
        try { broadcastRideLifecycleFCM('RIDE_ARRIVED', $bookingId); } catch (Exception $e) {}
    } elseif ($status === 'IN_TRANSIT') {
        try { sendMetaWhatsApp($booking['customer_phone'], "UPDATE: Your ride #{$booking['booking_ref']} is now in transit. Driver $driverName is on the way!"); } catch (Exception $e) {}
        try { broadcastRideLifecycleFCM('RIDE_STARTED', $bookingId); } catch (Exception $e) {}
    } elseif ($status === 'COMPLETED') {
        dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$driverId]);
        
        // AUTO COMMISSION — only for SELF-grabbed rides by unsubscribed drivers.
        // Admin/team-assigned rides are commission-free. Deducted straight from wallet.
        $assignedBy = strtolower($booking['assigned_by'] ?? 'admin');
        $isSub = driverHasActiveSubscription($driverId);
        if ($assignedBy === 'self' && !$isSub) {
            $commission = driverCommissionPerRide();
            $balance = driverWalletBalance($driverId);
            if ($balance >= $commission) {
                $balAfter = driverWalletTxn($driverId, 'commission', -$commission, "Commission for ride {$booking['booking_ref']} (self-accepted)", null, $bookingId);
                dbExec("UPDATE app_bookings SET commission_status = 'paid' WHERE id = ?", 'i', [$bookingId]);
                try { sendMetaWhatsApp($driverPhone, "Commission Rs." . intval($commission) . " deducted from wallet for ride #{$booking['booking_ref']}.\n\nRemaining Balance: *Rs." . intval($balAfter) . "*\n\nSubscribe Rs." . intval(driverSubscriptionAmount()) . "/month for ZERO commission!"); } catch (Exception $e) {}
            } else {
                dbExec("UPDATE app_bookings SET commission_status = 'due' WHERE id = ?", 'i', [$bookingId]);
                try { sendFCMPushToAdmins("Commission Due", "$driverName completed self-accepted ride #{$booking['booking_ref']} but wallet has Rs." . intval($balance) . " (below minimum). Collect manually.", ['type' => 'COMMISSION_DUE', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
            }
        } elseif ($assignedBy === 'self' && $isSub) {
            dbExec("UPDATE app_bookings SET commission_status = 'subscribed' WHERE id = ?", 'i', [$bookingId]);
        }
        
        try { sendMetaWhatsApp($booking['customer_phone'], "Your ride #{$booking['booking_ref']} has been completed. Total fare: {$booking['total_fare']} rupees. Thank you for riding with PAVANCAB!"); } catch (Exception $e) {}
        try { broadcastRideLifecycleFCM('RIDE_COMPLETED', $bookingId); } catch (Exception $e) {}
    }
    
    jsonResponse(['success' => true, 'message' => "Status updated to $status", 'status' => $status]);
}

// RATE PASSENGER - driver rates/reviews the customer after completing a ride
if ($action === 'rate-passenger' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $bookingId = intval($b['booking_id'] ?? 0);
    $rating = intval($b['rating'] ?? 0);
    $review = trim(mb_substr($b['review'] ?? '', 0, 500));
    if (!$bookingId || $rating < 1 || $rating > 5) jsonResponse(['success' => false, 'error' => 'booking_id and rating (1-5) required']);
    
    $rows = dbRows("SELECT id, status, passenger_rating FROM app_bookings WHERE id = ? AND driver_id = ? LIMIT 1", 'ii', [$bookingId, $driverId]);
    if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Booking not found'], 404);
    if (strtoupper($rows[0]['status']) !== 'COMPLETED') jsonResponse(['success' => false, 'error' => 'You can rate the passenger after completing the ride']);
    if (intval($rows[0]['passenger_rating'] ?? 0) > 0) jsonResponse(['success' => false, 'error' => 'Already rated']);
    
    dbExec("UPDATE app_bookings SET passenger_rating = ?, passenger_review = ?, passenger_rated_at = NOW() WHERE id = ?", 'isi', [$rating, $review, $bookingId]);
    try { sendFCMPushToAdmins("⭐ Passenger Rated", "Driver rated a passenger $rating/5" . ($review ? ": \"$review\"" : ""), ['type' => 'PASSENGER_RATED', 'booking_id' => strval($bookingId)]); } catch (Exception $e) {}
    jsonResponse(['success' => true, 'message' => 'Thanks for rating the passenger!']);
}

// EARNINGS
if ($action === 'earnings') {
    $driverId = intval($_SESSION['driver']['id']);
    $todayRow = dbRows("SELECT COUNT(*) as cnt, IFNULL(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND DATE(pickup_date) = CURDATE()", 'i', [$driverId]);
    $weekRow = dbRows("SELECT COUNT(*) as cnt, IFNULL(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND pickup_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)", 'i', [$driverId]);
    $monthRow = dbRows("SELECT COUNT(*) as cnt, IFNULL(SUM(total_fare),0) as total FROM app_bookings WHERE driver_id = ? AND status = 'COMPLETED' AND MONTH(pickup_date) = MONTH(CURDATE()) AND YEAR(pickup_date) = YEAR(CURDATE())", 'i', [$driverId]);
    $settings = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
    $commission = floatval($settings[0]['setting_value'] ?? 200);
    jsonResponse([
        'success' => true,
        'today_rides' => intval($todayRow[0]['cnt'] ?? 0), 'today_earnings' => floatval($todayRow[0]['total'] ?? 0),
        'week_rides' => intval($weekRow[0]['cnt'] ?? 0), 'week_earnings' => floatval($weekRow[0]['total'] ?? 0),
        'month_rides' => intval($monthRow[0]['cnt'] ?? 0), 'month_earnings' => floatval($monthRow[0]['total'] ?? 0),
        'commission_per_ride' => $commission
    ]);
}

// CREATE ORDER (Razorpay)
if ($action === 'create-order' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $type = trim($b['type'] ?? 'subscription');
    $bookingId = intval($b['booking_id'] ?? 0);
    
    $settings = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('driver_subscription_amount', 'driver_commission_per_ride')");
    $settingsMap = [];
    foreach ($settings as $s) $settingsMap[$s['setting_key']] = floatval($s['setting_value']);
    
    if ($type === 'subscription') {
        $amount = $settingsMap['driver_subscription_amount'] ?? 1000;
    } else {
        $amount = $settingsMap['driver_commission_per_ride'] ?? 200;
    }
    
    list($razorpayKey, $razorpaySecret) = razorpayKeys();
    
    $receipt = 'driver_' . $driverId . '_' . $type . '_' . time();
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => "$razorpayKey:$razorpaySecret",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'amount' => intval($amount * 100),
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => ['driver_id' => strval($driverId), 'type' => $type, 'booking_id' => strval($bookingId)]
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $order = json_decode($response, true);
    
    if (empty($order['id'])) {
        jsonResponse(['success' => false, 'error' => 'Failed to create Razorpay order', 'details' => $order], 500);
    }
    
    jsonResponse([
        'success' => true,
        'order_id' => $order['id'],
        'amount' => $amount,
        'key_id' => $razorpayKey,
        'razorpay_key' => $razorpayKey,
        'currency' => 'INR'
    ]);
}

// VERIFY PAYMENT
if ($action === 'verify-payment' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $razorpayOrderId = trim($b['razorpay_order_id'] ?? '');
    $razorpayPaymentId = trim($b['razorpay_payment_id'] ?? '');
    $paymentType = trim($b['type'] ?? $b['payment_type'] ?? 'subscription');
    $bookingId = intval($b['booking_id'] ?? 0);
    
    if (!$razorpayOrderId || !$razorpayPaymentId) jsonResponse(['success' => false, 'error' => 'Missing payment details']);
    
    list($razorpayKey, $razorpaySecret) = razorpayKeys();
    
    $ch = curl_init("https://api.razorpay.com/v1/payments/$razorpayPaymentId");
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => "$razorpayKey:$razorpaySecret",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) jsonResponse(['success' => false, 'error' => 'Could not verify payment with Razorpay']);
    
    $payData = json_decode($response, true);
    $payStatus = $payData['status'] ?? '';
    if ($payStatus !== 'captured' && $payStatus !== 'authorized') {
        jsonResponse(['success' => false, 'error' => 'Payment not completed. Status: ' . $payStatus]);
    }
    
    if ($paymentType === 'subscription') {
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
        if ($bookingId) dbExec("UPDATE app_bookings SET commission_status = 'paid' WHERE id = ?", 'i', [$bookingId]);
        try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "COMMISSION PAID!\n\n₹$amount commission paid for ride #$bookingId.\n\nYou can now accept the next ride!"); } catch (Exception $e) {}
    }
    
    jsonResponse(['success' => true, 'message' => 'Payment verified successfully']);
}

// PAYMENT HISTORY
if ($action === 'payment-history') {
    $driverId = intval($_SESSION['driver']['id']);
    $payments = dbRows("SELECT * FROM driver_payments WHERE driver_id = ? ORDER BY created_at DESC LIMIT 50", 'i', [$driverId]);
    $subscriptions = dbRows("SELECT * FROM driver_subscriptions WHERE driver_id = ? ORDER BY created_at DESC LIMIT 10", 'i', [$driverId]);
    jsonResponse(['success' => true, 'payments' => $payments, 'subscriptions' => $subscriptions]);
}
