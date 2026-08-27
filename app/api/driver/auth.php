<?php
// Included by index.php - $method, $action, $b, db() all available

// SEND OTP
if ($method === 'POST' && ($action === 'send-otp' || $action === 'send_otp' || (isset($b['phone']) && !in_array($action, ['verify-otp', 'check-phone', 'set-password', 'login-with-password', 'reset-password', 'check-session', 'check-approval', 'profile', 'toggle-online', 'save-fcm-token', 'logout'])))) {
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    if (!$phone || strlen($phone) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone number required'], 400);
    
    $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $clean10 = substr($phone, -10);
    $conn = db();
    
    $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
    $stmtDel->bind_param('sss', $phone, $clean10, $clean10);
    $stmtDel->execute();
    
    $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->bind_param('ss', $phone, $otp);
    $stmt->execute();
    
    $_SESSION['pending_otp'] = ['phone' => $phone, 'clean10' => $clean10, 'otp' => $otp, 'expires' => time() + 600];
    
    $result = sendOTPWhatsAppTemplate($phone, $otp, 'PAVANCAB', 'Driver', 'PAVANCAB Driver', 'PAVANCAB', '+919518541625');
    $waSent = true;
    if (is_array($result) && isset($result['success']) && !$result['success']) $waSent = false;
    jsonResponse(['success' => $waSent, 'message' => $waSent ? "OTP sent to +$phone" : "WhatsApp failed", 'phone' => '+' . $phone, 'wa_sent' => $waSent]);
}

// VERIFY OTP
if ($action === 'verify-otp' && $method === 'POST') {
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    $otp = trim($b['otp'] ?? '');
    if (!$phone || !$otp) jsonResponse(['success' => false, 'error' => 'Phone and OTP required'], 400);
    
    $clean10 = substr($phone, -10);
    $conn = db();
    
    $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('sss', $phone, $clean10, trim($otp));
    $stmt->execute();
    $r = $stmt->get_result();
    $otpRow = $r ? $r->fetch_assoc() : null;
    
    if (!$otpRow) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP. Please try again.'], 401);
    dbExec("DELETE FROM app_otp_store WHERE id = ?", 'i', [$otpRow['id']]);
    
    $stmt = $conn->prepare("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1");
    $stmt->bind_param('s', $clean10);
    $stmt->execute();
    $r = $stmt->get_result();
    $driver = $r ? $r->fetch_assoc() : null;
    
    if ($driver) {
        dbExec("UPDATE app_drivers SET is_online = 1, last_active_at = NOW() WHERE id = ?", 'i', [intval($driver['id'])]);
        $_SESSION['driver'] = ['id' => intval($driver['id']), 'name' => $driver['name'], 'phone' => $phone, 'car_model' => $driver['car_model'] ?? '', 'plate_number' => $driver['plate_number'] ?? '', 'isLoggedIn' => true];
        $approved = intval($driver['is_approved'] ?? 0);
        jsonResponse(['success' => true, 'approved' => $approved == 1, 'existing_driver' => true, 'driver' => ['id' => intval($driver['id']), 'name' => $driver['name'], 'phone' => $phone, 'car_model' => $driver['car_model'] ?? '', 'plate_number' => $driver['plate_number'] ?? '', 'rating' => floatval($driver['rating'] ?? 5.0), 'is_approved' => $approved]]);
    } else {
        $stmt = $conn->prepare("INSERT INTO app_drivers (name, phone, car_model, plate_number, is_approved, status, is_online, last_active_at) VALUES (?, ?, '', '', 1, 'available', 1, NOW())");
        $stmt->bind_param('ss', $phone, $phone);
        $stmt->execute();
        $newId = $conn->insert_id;
        $_SESSION['driver'] = ['id' => $newId, 'name' => '', 'phone' => $phone, 'car_model' => '', 'plate_number' => '', 'isLoggedIn' => true];
        try { sendFCMPushToAdmins("New Driver Registered", "Phone: $phone has registered and is auto-approved. Revoke from Admin app if needed.", ['type' => 'NEW_DRIVER', 'driver_id' => strval($newId)]); } catch (Exception $e) {}
        jsonResponse(['success' => true, 'approved' => true, 'existing_driver' => false, 'driver' => ['id' => $newId, 'name' => '', 'phone' => $phone, 'car_model' => '', 'plate_number' => '', 'rating' => 5.0, 'is_approved' => 1]]);
    }
}

// CHECK SESSION
if ($action === 'check-session') {
    if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
    $driverId = intval($_SESSION['driver']['id']);
    $rows = dbRows("SELECT is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    $approved = !empty($rows) ? intval($rows[0]['is_approved'] ?? 0) : 0;
    jsonResponse(['success' => true, 'approved' => $approved == 1, 'driver' => $_SESSION['driver']]);
}

// CHECK APPROVAL
if ($action === 'check-approval') {
    if (empty($_SESSION['driver']['isLoggedIn'])) jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
    $driverId = intval($_SESSION['driver']['id']);
    $rows = dbRows("SELECT is_approved, name, car_model, plate_number FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
    jsonResponse(['success' => true, 'approved' => intval($rows[0]['is_approved'] ?? 0) == 1, 'driver' => $rows[0]]);
}

// CHECK PHONE
if ($action === 'check-phone' && $method === 'POST') {
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    if (!$phone || strlen($phone) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone required'], 400);
    $clean10 = substr($phone, -10);
    $rows = dbRows("SELECT id, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
    jsonResponse(['success' => true, 'exists' => !empty($rows), 'has_password' => !empty($rows[0]['password_hash'] ?? null)]);
}

// SET PASSWORD
if ($action === 'set-password' && $method === 'POST') {
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
    $clean10 = substr($phone, -10);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    dbExec("UPDATE app_drivers SET password_hash = ? WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$hash, $clean10]);
    jsonResponse(['success' => true, 'message' => 'Password set successfully']);
}

// LOGIN WITH PASSWORD
if ($action === 'login-with-password' && $method === 'POST') {
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    $clean10 = substr($phone, -10);
    $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, is_approved, is_online, password_hash FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
    if (empty($rows)) jsonResponse(['success' => false, 'error' => 'Driver account not found. Register with OTP first.'], 404);
    $driver = $rows[0];
    if (empty($driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
    if (!password_verify($password, $driver['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
    $approved = intval($driver['is_approved'] ?? 0);
    dbExec("UPDATE app_drivers SET is_online = 1, last_active_at = NOW() WHERE id = ?", 'i', [intval($driver['id'])]);
    $_SESSION['driver'] = ['id' => intval($driver['id']), 'name' => $driver['name'], 'phone' => $phone, 'car_model' => $driver['car_model'] ?? '', 'plate_number' => $driver['plate_number'] ?? '', 'isLoggedIn' => true];
    jsonResponse(['success' => true, 'approved' => $approved == 1, 'driver' => ['id' => intval($driver['id']), 'name' => $driver['name'], 'phone' => $phone, 'car_model' => $driver['car_model'] ?? '', 'plate_number' => $driver['plate_number'] ?? '', 'rating' => floatval($driver['rating'] ?? 5.0), 'is_approved' => $approved]]);
}

// RESET PASSWORD
if ($action === 'reset-password' && $method === 'POST') {
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    $otp = trim($b['otp'] ?? '');
    $password = trim($b['password'] ?? '');
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

// LOGOUT
if ($action === 'logout' && $method === 'POST') {
    if (!empty($_SESSION['driver']['id'])) {
        $driverId = intval($_SESSION['driver']['id']);
        // Clear this driver's FCM so they stop receiving notifications after logout
        try {
            $drTok = dbRows("SELECT fcm_token, phone FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
            $oldTok = $drTok[0]['fcm_token'] ?? '';
            $oldPhone = cleanPhoneDigits($drTok[0]['phone'] ?? '');
            dbExec("UPDATE app_drivers SET is_online = 0, fcm_token = NULL, last_active_at = NOW() WHERE id = ?", 'i', [$driverId]);
            if ($oldTok) {
                $old10 = substr($oldPhone, -10);
                dbExec("DELETE FROM app_fcm_tokens WHERE fcm_token = ? AND app_type = 'driver'", 's', [$oldTok]);
                dbExec("DELETE FROM app_fcm_tokens WHERE fcm_token = ? AND user_mobile IS NOT NULL AND RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$oldTok, $old10]);
            }
        } catch (Exception $e) {}
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    jsonResponse(['success' => true]);
}

// Require driver auth for these endpoints
if (in_array($action, ['profile', 'update-profile', 'toggle-online', 'save-fcm-token'])) {
    if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
        jsonResponse(['error' => 'Driver authentication required. Please login.'], 401);
    }
}

// PROFILE
if ($action === 'profile') {
    $driverId = intval($_SESSION['driver']['id']);
    $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, status, is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
    jsonResponse(['driver' => $rows[0]]);
}

// UPDATE PROFILE (vehicle model, number plate, optional name/tax group)
if ($action === 'update-profile' && $method === 'POST') {
    $driverId = intval($_SESSION['driver']['id']);
    $rows = dbRows("SELECT id FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    if (empty($rows)) jsonResponse(['error' => 'Driver not found'], 404);
    $sets = []; $types = ''; $params = [];
    $name = trim($b['name'] ?? '');
    $carModel = trim($b['car_model'] ?? '');
    $plateNumber = trim($b['plate_number'] ?? '');
    if ($name !== '') { $sets[] = 'name = ?'; $types .= 's'; $params[] = $name; }
    if ($carModel !== '') { $sets[] = 'car_model = ?'; $types .= 's'; $params[] = $carModel; }
    if ($plateNumber !== '') { $sets[] = 'plate_number = ?'; $types .= 's'; $params[] = strtoupper(preg_replace('/\s+/', '', $plateNumber)); }
    if (empty($sets)) jsonResponse(['error' => 'No fields to update'], 400);
    $types .= 'i'; $params[] = $driverId;
    dbExec("UPDATE app_drivers SET " . implode(', ', $sets) . " WHERE id = ?", $types, $params);
    if ($name !== '') $_SESSION['driver']['name'] = $name;
    if ($carModel !== '') $_SESSION['driver']['car_model'] = $carModel;
    if ($plateNumber !== '') $_SESSION['driver']['plate_number'] = $plateNumber;
    $rows = dbRows("SELECT id, name, phone, car_model, plate_number, rating, total_ratings, status, is_approved FROM app_drivers WHERE id = ? LIMIT 1", 'i', [$driverId]);
    jsonResponse(['success' => true, 'message' => 'Profile updated', 'driver' => $rows[0]]);
}

// SAVE FCM TOKEN
if ($action === 'save-fcm-token' && $method === 'POST') {
    $fcmToken = trim($b['fcm_token'] ?? '');
    $phone = cleanPhoneDigits($b['phone'] ?? '');
    if (!$phone && !empty($_SESSION['driver']['phone'])) $phone = cleanPhoneDigits($_SESSION['driver']['phone']);
    if ($fcmToken) {
        $tokEsc = $conn->real_escape_string($fcmToken);
        // FCM tokens are per-device, not per-account. Reassign ownership strictly to the
        // currently-logged-in driver: remove this token from ALL other drivers so the
        // previous account on this device stops receiving this driver's notifications.
        try { $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$tokEsc'"); } catch (Exception $e) {}
        if ($phone) {
            $clean10 = substr($phone, -10);
            dbExec("UPDATE app_drivers SET fcm_token = ?, last_active_at = NOW() WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ?", 'ss', [$fcmToken, $clean10]);
        }
        // Tag as driver so passenger lookups never pull this token (prevents cross-app notification leaks)
        $existing = dbRows("SELECT id FROM app_fcm_tokens WHERE fcm_token = ? LIMIT 1", 's', [$fcmToken]);
        if (empty($existing)) {
            dbExec("INSERT INTO app_fcm_tokens (fcm_token, user_mobile, app_type) VALUES (?, ?, 'driver')", 'ss', [$fcmToken, $phone ?: null]);
        } else {
            dbExec("UPDATE app_fcm_tokens SET user_mobile = ?, app_type = 'driver', updated_at = NOW() WHERE fcm_token = ?", 'ss', [$phone ?: null, $fcmToken]);
        }
    }
    jsonResponse(['success' => true]);
}
