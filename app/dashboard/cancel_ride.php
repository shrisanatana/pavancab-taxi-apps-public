<?php
require_once __DIR__ . '/../db.php';
$conn = db();

$user = $_SESSION['user'] ?? null;
if (!$user || !in_array($user['role'] ?? '', ['admin', 'team'])) {
    header('Location: ../auth.php');
    exit;
}

$bookingId = intval($_POST['booking_id'] ?? $_GET['id'] ?? 0);
$filter = $_POST['filter'] ?? $_GET['filter'] ?? 'PENDING';

if (!$bookingId) {
    header('Location: index.php?filter=' . urlencode($filter));
    exit;
}

$bookingBefore = dbRows('SELECT id, status, driver_id, customer_phone, user_email, customer_name, booking_ref FROM app_bookings WHERE id = ?', 'i', [$bookingId]);

$conn->query("UPDATE app_bookings SET status = 'CANCELLED_BY_ADMIN', driver_id = NULL, driver_name = NULL, driver_phone = NULL, driver_decision = 'NONE' WHERE id = $bookingId");

if (!empty($bookingBefore[0]['driver_id'])) {
    dbExec("UPDATE app_drivers SET status = 'available' WHERE id = ?", 'i', [$bookingBefore[0]['driver_id']]);
}

$fcmResult = broadcastRideLifecycleFCM('CANCELLED_BY_ADMIN', $bookingId);

$fcmLog = date('Y-m-d H:i:s') . " | Cancel #$bookingId | Before: " . json_encode($bookingBefore[0] ?? []) . " | FCM: " . json_encode($fcmResult) . "\n";
@file_put_contents(__DIR__ . '/../fcm_cancel_log.txt', $fcmLog, FILE_APPEND | LOCK_EX);

header('Location: index.php?filter=' . urlencode($filter));
exit;
