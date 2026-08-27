<?php
/**
 * PAVANCAB - Per-Minute Cron Job
 * Path: app/each-min-cron.php
 * 
 * Call: https://pavancab.com/app/each-min-cron.php?key=pavancab_cron_2026
 * Hostinger cron: * * * * * curl -s "https://pavancab.com/app/each-min-cron.php?key=pavancab_cron_2026" > /dev/null 2>&1
 */

// Skip if key wrong
if (($_GET['key'] ?? '') !== 'pavancab_cron_2026') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Kolkata');
$conn = db();
$nowTs = time();
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$log = [];
$errors = [];
$startMs = microtime(true);

// 1. OTP cleanup (expired)
try {
    $del = $conn->query("DELETE FROM app_otp_store WHERE expires_at < NOW()");
    if ($del && $del->affected_rows > 0) $log[] = "OTP: -{$del->affected_rows}";
} catch (Exception $e) { $errors[] = "otp: " . $e->getMessage(); }

// 2. Ride-soon reminder to driver (60 min before pickup)
try {
    $rides = dbRows(
        "SELECT b.id, b.booking_ref, b.customer_name, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.driver_id, b.driver_phone, b.total_fare, b.reminder_sent, b.created_at, COALESCE(NULLIF(b.driver_phone, ''), d.phone) as dphone FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE UPPER(b.status) IN ('CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent = 0) AND b.pickup_date IN (?, ?)",
        'ss', [$today, $tomorrow]
    );
    foreach ($rides as $ride) {
        $diffMin = (strtotime("{$ride['pickup_date']} {$ride['pickup_time']}") - $nowTs) / 60;
        $ageMin = ($nowTs - strtotime($ride['created_at'] ?? 'now')) / 60;
        if ($ageMin < 30) continue;
        if ($diffMin > 0 && $diffMin <= 60) {
            $dP = $ride['dphone'] ?? '';
            $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
            if ($dP) @sendMetaWhatsApp($dP, "RIDE REMINDER\n\nRef: #{$ride['booking_ref']}\nPassenger: {$ride['customer_name']}\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: {$ride['total_fare']}\n\nRide in " . intval($diffMin) . " min! Head to pickup now.");
            @sendFCMPushToDriver($ride['driver_id'] ?: $dP, "Ride in " . intval($diffMin) . " min!", "Pickup {$ride['customer_name']}", ['type' => 'RIDE_REMINDER', 'booking_id' => strval($ride['id'])]);
            dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
            $log[] = "Reminder #{$ride['booking_ref']}";
        }
    }
} catch (Exception $e) { $errors[] = "ride_reminder: " . $e->getMessage(); }

// 3. Unassigned urgent ride alert to admin (90 min before pickup)
try {
    $ua = dbRows(
        "SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.cab_type, b.total_fare, b.reminder_sent FROM app_bookings b WHERE UPPER(b.status) IN ('PENDING','CONFIRMED') AND (b.driver_id IS NULL OR b.driver_id = 0) AND b.pickup_date IN (?, ?)",
        'ss', [$today, $tomorrow]
    );
    foreach ($ua as $ride) {
        $diffMin = (strtotime("{$ride['pickup_date']} {$ride['pickup_time']}") - $nowTs) / 60;
        if ($diffMin > 0 && $diffMin <= 90 && ($ride['reminder_sent'] ?? 0) < 3) {
            $urg = $diffMin <= 30 ? "URGENT" : "NEEDS DRIVER";
            $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
            @sendMetaWhatsApp('+919000000000', "$urg - Ride #{$ride['booking_ref']}\n\nPassenger: {$ride['customer_name']} ({$ride['customer_phone']})\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: {$ride['total_fare']}\n\nPickup in " . intval($diffMin) . " min! Assign driver NOW.");
            @sendFCMPushToAdmins("$urg Ride #{$ride['booking_ref']}", "Pickup in " . intval($diffMin) . " min!", ['type' => 'UNASSIGNED_URGENT']);
            dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
            $log[] = "Unassigned #{$ride['booking_ref']}";
        }
    }
} catch (Exception $e) { $errors[] = "unassigned: " . $e->getMessage(); }

// 4. Night ride alert to admin (10PM-6AM pickup)
try {
    $cHour = intval(date('G'));
    if ($cHour >= 22 || $cHour < 1) {
        $nr = dbRows(
            "SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_date, b.pickup_time, b.total_fare, b.reminder_sent FROM app_bookings b WHERE UPPER(b.status) IN ('PENDING','CONFIRMED','ASSIGNED','ACCEPTED') AND (b.reminder_sent IS NULL OR b.reminder_sent < 5) AND b.pickup_date IN (?, ?)",
            'ss', [$today, $tomorrow]
        );
        foreach ($nr as $ride) {
            $pH = intval(date('G', strtotime("{$ride['pickup_date']} {$ride['pickup_time']}")));
            if ($pH >= 22 || $pH < 6) {
                $pDT = formatIndianDateTime($ride['pickup_date'], $ride['pickup_time']);
                @sendMetaWhatsApp('+919000000000', "NIGHT RIDE\nRef: #{$ride['booking_ref']}\n{$ride['customer_name']} ({$ride['customer_phone']})\nPickup: {$ride['pickup_location']}\nDrop: {$ride['drop_location']}\nTime: $pDT\nFare: {$ride['total_fare']}\n1.5x multiplier.");
                dbExec("UPDATE app_bookings SET reminder_sent = IFNULL(reminder_sent, 0) + 1 WHERE id = ?", 'i', [$ride['id']]);
                $log[] = "Night #{$ride['booking_ref']}";
            }
        }
    }
} catch (Exception $e) { $errors[] = "night: " . $e->getMessage(); }

// 5. Subscription expiry reminders (3d, 1d, expired)
try {
    $subs = dbRows(
        "SELECT ds.*, d.phone as dphone, d.name as dname FROM driver_subscriptions ds JOIN app_drivers d ON ds.driver_id = d.id WHERE ds.status = 'active' AND ds.end_date >= ? AND ds.end_date <= DATE_ADD(?, INTERVAL 7 DAY)",
        'ss', [$today, $today]
    );
    foreach ($subs as $sub) {
        $daysLeft = max(0, (strtotime($sub['end_date']) - strtotime($today)) / 86400);
        $dP = $sub['dphone'];
        $dN = $sub['dname'] ?: 'Driver';
        $lr = $sub['last_reminder_sent'] ?? '';
        if ($daysLeft <= 0 && $lr !== $today) {
            @sendMetaWhatsApp($dP, "SUBSCRIPTION EXPIRED\nHi $dN, your PAVANCAB subscription expired today.\nPay 200/ride commission or renew 1000/month!\nOpen Driver App to subscribe.");
            dbExec("UPDATE driver_subscriptions SET status = 'expired', last_reminder_sent = ? WHERE id = ?", 'si', [$today, $sub['id']]);
            dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [$sub['driver_id']]);
            $log[] = "Sub expired #{$sub['driver_id']}";
        } elseif ($daysLeft <= 1 && $lr !== '1d') {
            @sendMetaWhatsApp($dP, "SUBSCRIPTION EXPIRES TOMORROW\nHi $dN, your subscription expires {$sub['end_date']}.\nRenew 1000/month in Driver App!");
            dbExec("UPDATE driver_subscriptions SET last_reminder_sent = '1d' WHERE id = ?", 'i', [$sub['id']]);
            $log[] = "Sub 1d #{$sub['driver_id']}";
        } elseif ($daysLeft <= 3 && $lr !== '3d') {
            @sendMetaWhatsApp($dP, "SUBSCRIPTION EXPIRES IN 3 DAYS\nHi $dN, expires {$sub['end_date']}. Renew 1000/month!");
            dbExec("UPDATE driver_subscriptions SET last_reminder_sent = '3d' WHERE id = ?", 'i', [$sub['id']]);
            $log[] = "Sub 3d #{$sub['driver_id']}";
        }
    }
} catch (Exception $e) { $errors[] = "subscription: " . $e->getMessage(); }

// 6. Pending commission reminders (every 6h)
try {
    if (intval(date('G')) % 6 === 0) {
        $up = dbRows(
            "SELECT dp.driver_id, d.phone as dphone, d.name as dname, COUNT(*) as cnt, SUM(dp.amount) as total FROM driver_payments dp JOIN app_drivers d ON dp.driver_id = d.id WHERE dp.status = 'pending' GROUP BY dp.driver_id HAVING cnt > 0"
        );
        foreach ($up as $row) {
            @sendMetaWhatsApp($row['dphone'], "PENDING COMMISSION\nHi {$row['dname']}, {$row['cnt']} unpaid ride(s) = {$row['total']}.\nPay in Driver App or subscribe 1000/month!");
            $log[] = "Comm reminder #{$row['driver_id']}";
        }
    }
} catch (Exception $e) { $errors[] = "commission: " . $e->getMessage(); }

// 7. Driver offline cleanup (>5min no activity)
try {
    $off = $conn->query("UPDATE app_drivers SET is_online = 0 WHERE is_online = 1 AND last_active_at IS NOT NULL AND last_active_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    if ($off && $off->affected_rows > 0) $log[] = "Offline: -{$off->affected_rows}";
} catch (Exception $e) { $errors[] = "offline: " . $e->getMessage(); }

// 8. Stale FCM token cleanup (90 days)
try {
    $fc = $conn->query("DELETE FROM app_fcm_tokens WHERE last_active_at IS NOT NULL AND last_active_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    if ($fc && $fc->affected_rows > 0) $log[] = "FCM: -{$fc->affected_rows}";
} catch (Exception $e) { $errors[] = "fcm: " . $e->getMessage(); }

// 9. Daily summary to admin (9AM)
try {
    if (date('H:i') === '09:00') {
        $yd = date('Y-m-d', strtotime('-1 day'));
        $bk = dbRows("SELECT COUNT(*) as cnt, COALESCE(SUM(total_fare),0) as rev FROM app_bookings WHERE DATE(created_at) = ?", 's', [$yd]);
        $comp = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status = 'COMPLETED' AND pickup_date = ?", 's', [$yd]);
        $canc = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status LIKE '%CANCELLED%' AND DATE(created_at) = ?", 's', [$yd]);
        $pend = dbRows("SELECT COUNT(*) as cnt FROM app_bookings WHERE status IN ('PENDING','CONFIRMED') AND pickup_date = ?", 's', [$today]);
        $on = dbRows("SELECT COUNT(*) as cnt FROM app_drivers WHERE is_online = 1");
        $new = dbRows("SELECT COUNT(*) as cnt FROM app_users WHERE DATE(created_at) = ?", 's', [$yd]);
        @sendMetaWhatsApp('+919000000000', "DAILY SUMMARY - $yd\n\nBookings: {$bk[0]['cnt']}\nCompleted: {$comp[0]['cnt']}\nCancelled: {$canc[0]['cnt']}\nRevenue: {$bk[0]['rev']}\nToday Pending: {$pend[0]['cnt']}\nOnline Drivers: {$on[0]['cnt']}\nNew Users: {$new[0]['cnt']}");
        $log[] = "Daily summary";
    }
} catch (Exception $e) { $errors[] = "summary: " . $e->getMessage(); }

// 10. Expired subscription cleanup
try {
    $exp = $conn->query("UPDATE driver_subscriptions SET status = 'expired' WHERE status = 'active' AND end_date < CURDATE()");
    if ($exp && $exp->affected_rows > 0) {
        $er = $conn->query("SELECT driver_id FROM driver_subscriptions WHERE status = 'expired' AND end_date < CURDATE()");
        if ($er) while ($r = $er->fetch_assoc()) dbExec("UPDATE app_drivers SET has_active_subscription = 0 WHERE id = ?", 'i', [intval($r['driver_id'])]);
        $log[] = "Expired: -{$exp->affected_rows}";
    }
} catch (Exception $e) { $errors[] = "exp_cleanup: " . $e->getMessage(); }

// 11. URGENT RIDE WATCHDOG â€” alert admins about urgent (Â±1hr) rides still unassigned
// after the 3-minute admin-only window + 2 min grace. Admin can always assign manually.
try {
    $aging = dbRows("SELECT b.id, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.pickup_time, b.special_notes, TIMESTAMPDIFF(MINUTE, b.created_at, NOW()) as age_min
                     FROM app_bookings b
                     WHERE b.status = 'PENDING' AND b.driver_id IS NULL
                     AND CONCAT(b.pickup_date, ' ', b.pickup_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
                     AND b.created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                     LIMIT 5");
    foreach ($aging as $a) {
        // Once per ride: mark in special_notes sentinel to avoid spamming every minute
        $marker = "[CRON_ALERT_" . date('Y-m-d') . "]";
        if (strpos($a['special_notes'] ?? '', $marker) !== false) continue;
        @sendFCMPushToAdmins(
            "Unassigned Urgent Ride (#{$a['booking_ref']})",
            "{$a['age_min']} min passed - no driver accepted. {$a['pickup_location']} to {$a['drop_location']} at {$a['pickup_time']}. Assign now!",
            ['type' => 'URGENT_RIDE_AGING', 'booking_id' => strval($a['id'])]
        );
        dbExec("UPDATE app_bookings SET special_notes = CONCAT(IFNULL(special_notes,''), ?) WHERE id = ?", 'si', [" " . $marker, $a['id']]);
        $log[] = "UrgentAlert:#{$a['id']}";
    }
} catch (Exception $e) { $errors[] = "urgent_watchdog: " . $e->getMessage(); }

// 12. NEW RIDE push notification to drivers â€” DELAYED.
// The ride appears in the driver app IMMEDIATELY (quick-rides has no release window).
// Only the push NOTIFICATION is delayed: 2 minutes for subscribed/premium drivers, 3 minutes for normal.
try {
    $now2 = date('Y-m-d H:i:s', strtotime('-2 minutes'));
    $newRides = dbRows(
        "SELECT id, booking_ref, pickup_location, drop_location, pickup_time, cab_type, total_fare, user_offered_fare, created_at
         FROM app_bookings
         WHERE status = 'PENDING' AND driver_id IS NULL
         AND driver_new_ride_notified_at IS NULL
         AND created_at <= ?", 's', [$now2]);
    foreach ($newRides as $nr) {
        $ageMin = ($nowTs - strtotime($nr['created_at'])) / 60;
        if ($ageMin < 2) continue;
        $sql = "SELECT DISTINCT fcm_token FROM app_drivers WHERE is_online = 1 AND fcm_token IS NOT NULL AND fcm_token != ''";
        if ($ageMin < 3) {
            // At 2min only subscribed/premium drivers get the notification
            $sql .= " AND id IN (SELECT driver_id FROM driver_subscriptions WHERE status = 'active' AND end_date >= CURDATE())";
        }
        $tokens = [];
        $rr = $conn->query($sql);
        if ($rr) {
            while ($row = $rr->fetch_assoc()) {
                $tok = trim($row['fcm_token']);
                if ($tok) $tokens[] = $tok;
            }
        }
        $tokens = array_values(array_unique($tokens));
        if (!empty($tokens)) {
            $fareLabel = floatval($nr['user_offered_fare'] ?? 0) > 0
                ? 'â‚¹' . intval($nr['user_offered_fare']) . ' (user offered)'
                : 'â‚¹' . intval($nr['total_fare']);
            @sendFCMPush($tokens, "ðŸš• New Ride Available #" . $nr['booking_ref'],
                "{$nr['pickup_location']} â†’ {$nr['drop_location']} â€¢ {$nr['cab_type']} â€¢ $fareLabel. Grab it fast!",
                ['type' => 'NEW_RIDE', 'booking_id' => strval($nr['id']), 'booking_ref' => $nr['booking_ref']]);
        }
        dbExec("UPDATE app_bookings SET driver_new_ride_notified_at = NOW() WHERE id = ? AND driver_new_ride_notified_at IS NULL", 'i', [intval($nr['id'])]);
        $log[] = "NewRideNotify:#{$nr['booking_ref']}";
    }
} catch (Exception $e) { $errors[] = "new_ride_notify: " . $e->getMessage(); }

// 13. DISPATCH 2-MIN AUTO-RELEASE â€” when dispatch sends a fare offer (propose/boost/edit-fare) on an
// unassigned ride, it gets a 2-min release window. Once that window passes and no driver was assigned,
// clear the hold so the ride is fully re-available to all drivers and drivers are nudged to re-poll.
try {
    $released = dbRows(
        "SELECT id, booking_ref FROM app_bookings
         WHERE driver_release_ends_at IS NOT NULL
           AND driver_release_ends_at <= NOW()
           AND (driver_id IS NULL OR driver_id = 0)
           AND UPPER(COALESCE(status,'PENDING')) IN ('PENDING','')
         LIMIT 50"
    );
    foreach ($released as $rls) {
        dbExec("UPDATE app_bookings SET driver_release_ends_at = NULL WHERE id = ?", 'i', [intval($rls['id'])]);
        // Nudge all online drivers to re-poll so the released ride is immediately available.
        $rts = [];
        $rq = $conn->query("SELECT DISTINCT fcm_token FROM app_drivers WHERE is_online = 1 AND fcm_token IS NOT NULL AND fcm_token != ''");
        if ($rq) while ($rw = $rq->fetch_assoc()) { $tok = trim($rw['fcm_token']); if ($tok) $rts[] = $tok; }
        if (!empty($rts)) @sendFCMPush(array_values(array_unique($rts)), "Ride #{$rls['booking_ref']} released", "Driver offer window ended \u2014 the ride is now open for you to take.", ['type' => 'RIDE_RELEASED', 'booking_id' => strval($rls['id'])]);
        $log[] = "Released:#{$rls['booking_ref']}";
    }
} catch (Exception $e) { $errors[] = "auto_release: " . $e->getMessage(); }

$elapsed = round((microtime(true) - $startMs) * 1000);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s'), 'ms' => $elapsed, 'tasks' => $log, 'errors' => $errors]);
exit;
