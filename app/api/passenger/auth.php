<?php
if ($method === 'POST' && ($action === 'verify_otp' || $action === 'verify-otp' || (!empty($b['otp']) && $action !== 'driver_verify-otp'))) {
    $phone = trim($b['phone'] ?? '');
    $otp = trim($b['otp'] ?? '');
    $name = trim($b['name'] ?? '');
    $email = trim($b['email'] ?? '');

    if (!$phone || !$otp) jsonResponse(['success' => false, 'error' => 'Phone number and 6-digit OTP code are required.'], 400);

    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $cleanOtp = trim($otp);

    if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);

    $conn = db();
    $otpValid = false;

    $stmt = $conn->prepare("SELECT * FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('sss', $cleanDigits, $clean10, $clean10);
    $stmt->execute();
    $r = $stmt->get_result();

    if ($r && $row = $r->fetch_assoc()) {
        if (trim($row['otp']) === $cleanOtp) {
            $otpValid = true;
            $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
            $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
            $stmtDel->execute();
        }
    }

    if (!$otpValid && isset($_SESSION['pending_otp'])) {
        if (($_SESSION['pending_otp']['phone'] === $cleanDigits || $_SESSION['pending_otp']['clean10'] === $clean10) && $_SESSION['pending_otp']['otp'] === $cleanOtp) {
            if ($_SESSION['pending_otp']['expires'] > time()) $otpValid = true;
        }
    }

    if (!$otpValid) {
        if (!isset($_SESSION['otp_attempts'])) $_SESSION['otp_attempts'] = [];
        $attempts = $_SESSION['otp_attempts'][$cleanDigits] ?? ['count' => 0, 'first' => time()];
        if (time() - $attempts['first'] > 600) $attempts = ['count' => 0, 'first' => time()];
        $attempts['count']++;
        $_SESSION['otp_attempts'][$cleanDigits] = $attempts;
        if ($attempts['count'] > 5) jsonResponse(['success' => false, 'error' => 'Too many failed attempts. Please wait 10 minutes and try again.'], 429);
        jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP code. Please request a new OTP.'], 401);
    }
    if (isset($_SESSION['otp_attempts'][$cleanDigits])) unset($_SESSION['otp_attempts'][$cleanDigits]);
    unset($_SESSION['pending_otp']);
    session_regenerate_id(true);

    $role = determineUserRole($cleanDigits, $email);
    $isAdmin = ($role === 'admin');
    $isTeam = ($role === 'team' || $role === 'admin');
    $finalName = $name ?: ($isAdmin ? 'Niranjan Yamgar (Admin)' : 'Goa Traveler');

    $formattedMobile = '+' . $cleanDigits;
    $reqFcm = trim($b['fcm_token'] ?? '');

    $stmtUser = $conn->prepare("SELECT id, name, email, role FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
    $stmtUser->bind_param('ss', $formattedMobile, $clean10);
    $stmtUser->execute();
    $rUser = $stmtUser->get_result();
    $isNewUser = true;
    $isBanned = false;

    if ($rUser && $rowU = $rUser->fetch_assoc()) {
        $isNewUser = false;
        $userId = $rowU['id'];
        $stmtBan = $conn->prepare("SELECT IFNULL(is_banned, 0) as banned FROM app_users WHERE id = ? LIMIT 1");
        if ($stmtBan) {
            $stmtBan->bind_param('i', $userId);
            $stmtBan->execute();
            $rBan = $stmtBan->get_result();
            if ($rBan && $rowBan = $rBan->fetch_assoc()) $isBanned = intval($rowBan['banned']) === 1;
        }
        if ($isBanned) jsonResponse(['success' => false, 'error' => 'Your account has been banned by the administrator. Please contact support.'], 403);
        if (!$name && $rowU['name']) $finalName = $rowU['name'];
        $stmtUpd = $conn->prepare("UPDATE app_users SET name = ?, mobile = ?, role = ?, last_active_at = NOW() WHERE id = ?");
        $stmtUpd->bind_param('sssi', $finalName, $formattedMobile, $role, $userId);
        $stmtUpd->execute();
    } else {
        $stmtIns = $conn->prepare("INSERT INTO app_users (name, mobile, email, role, last_active_at) VALUES (?, ?, ?, ?, NOW())");
        $stmtIns->bind_param('ssss', $finalName, $formattedMobile, $email, $role);
        $stmtIns->execute();
        $userId = $conn->insert_id;
    }

    if (!empty($reqFcm)) {
        $fSafe = $conn->real_escape_string($reqFcm);
        $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
        $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
        $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), updated_at = NOW()");
        if ($stmtFcm) { $stmtFcm->bind_param('sss', $reqFcm, $email, $cleanDigits); $stmtFcm->execute(); }
        $conn->prepare("UPDATE app_users SET fcm_token = ?, last_active_at = NOW() WHERE id = ?")->bind_param('si', $reqFcm, $userId);
        $conn->query("UPDATE app_users SET fcm_token = '$fSafe', last_active_at = NOW() WHERE id = $userId");
    }

    $userSession = ['id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $email, 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
    $_SESSION['user'] = $userSession;

    // Persistent remember-token â€” app stays logged in across restarts even if PHP session dies
    $rememberToken = bin2hex(random_bytes(32));
    $conn->prepare("UPDATE app_users SET remember_token = ? WHERE id = ?")->bind_param('si', $rememberToken, $userId);
    $conn->query("UPDATE app_users SET remember_token = '" . $rememberToken . "' WHERE id = " . intval($userId));

    jsonResponse([
        'success' => true, 'message' => 'Login verified successfully!', 'user' => $userSession,
        'remember_token' => $rememberToken,
        'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'has_password' => !empty($rowU['password_hash'] ?? null)
    ]);

} elseif ($action === 'auto_login') {
    // Silent re-authentication: PHP session died but device still holds its remember token
    $token = trim($b['remember_token'] ?? '');
    if (strlen($token) < 32) jsonResponse(['success' => false, 'error' => 'Invalid token'], 400);
    $conn = db();
    $stmt = $conn->prepare("SELECT id, name, mobile, email, role, is_banned FROM app_users WHERE remember_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $r = $stmt->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    if (!$row) jsonResponse(['success' => false, 'error' => 'Token invalid â€” please login again'], 401);
    if (intval($row['is_banned'] ?? 0) === 1) jsonResponse(['success' => false, 'error' => 'Account banned'], 403);
    $userId = intval($row['id']);
    $role = $row['role'] ?? 'user';
    $isAdmin = ($role === 'admin');
    $isTeam = ($role === 'team' || $role === 'admin');
    $finalName = $row['name'] ?: 'Goa Traveler';
    $userSession = ['id' => $userId, 'name' => $finalName, 'mobile' => $row['mobile'], 'email' => $row['email'] ?? '', 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
    $_SESSION['user'] = $userSession;
    $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE id = $userId");
    $conn->query("UPDATE app_users SET last_active_at = NOW() WHERE id = $userId");
    jsonResponse(['success' => true, 'message' => 'Auto login successful', 'user' => $userSession, 'remember_token' => $token, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam]);

} elseif ($action === 'check_phone') {
    $phone = trim($b['phone'] ?? '');
    if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Valid phone number required'], 400);
    $conn = db();
    $stmt = $conn->prepare("SELECT id, name, password_hash FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
    $mobileFull = '+' . $cleanDigits;
    $stmt->bind_param('ss', $mobileFull, $clean10);
    $stmt->execute();
    $r = $stmt->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    jsonResponse(['success' => true, 'exists' => !empty($row), 'has_password' => !empty($row['password_hash'] ?? null), 'name' => $row['name'] ?? '']);

} elseif ($action === 'set_password') {
    $phone = trim($b['phone'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $formattedMobile = '+' . $cleanDigits;
    $conn = db();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?");
    $stmt->bind_param('sss', $hash, $formattedMobile, $clean10);
    $stmt->execute();
    jsonResponse(['success' => true, 'message' => 'Password set successfully']);

} elseif ($action === 'login_with_password') {
    $phone = trim($b['phone'] ?? '');
    $password = trim($b['password'] ?? '');
    $fcm = trim($b['fcm_token'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $formattedMobile = '+' . $cleanDigits;
    $conn = db();
    $stmt = $conn->prepare("SELECT id, name, email, role, password_hash, is_banned FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
    $stmt->bind_param('ss', $formattedMobile, $clean10);
    $stmt->execute();
    $r = $stmt->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    if (!$row) jsonResponse(['success' => false, 'error' => 'Account not found. Please register first with OTP.'], 404);
    if (empty($row['password_hash'])) jsonResponse(['success' => false, 'error' => 'No password set. Please login with OTP first.'], 400);
    if (!password_verify($password, $row['password_hash'])) jsonResponse(['success' => false, 'error' => 'Invalid password'], 401);
    if (intval($row['is_banned'] ?? 0) === 1) jsonResponse(['success' => false, 'error' => 'Account banned'], 403);
    $userId = intval($row['id']);
    $role = $row['role'] ?? 'user';
    $isAdmin = ($role === 'admin');
    $isTeam = ($role === 'team' || $role === 'admin');
    $finalName = $row['name'] ?: 'Goa Traveler';
    session_regenerate_id(true);
    $conn->query("UPDATE app_users SET last_active_at = NOW() WHERE id = $userId");
    if (!empty($fcm)) {
        $fSafe = $conn->real_escape_string($fcm);
        $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
        $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
        $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), updated_at = NOW()");
        if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcm, $row['email'] ?? '', $formattedMobile); $stmtFcm->execute(); }
        $conn->query("UPDATE app_users SET fcm_token = '$fSafe', last_active_at = NOW() WHERE id = $userId");
    }
    $userSession = ['id' => $userId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $row['email'] ?? '', 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
    $_SESSION['user'] = $userSession;
    $rememberToken = bin2hex(random_bytes(32));
    $conn->query("UPDATE app_users SET remember_token = '" . $rememberToken . "' WHERE id = " . intval($userId));
    jsonResponse(['success' => true, 'message' => 'Login successful!', 'user' => $userSession, 'remember_token' => $rememberToken, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam]);

} elseif ($action === 'reset_password') {
    $phone = trim($b['phone'] ?? '');
    $otp = trim($b['otp'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP and new password required'], 400);
    if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $conn = db();
    $stmt = $conn->prepare("SELECT id FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ssss', $cleanDigits, $cleanDigits, $clean10, $otp);
    $stmt->execute();
    $r = $stmt->get_result();
    if (!$r || !$r->fetch_assoc()) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 401);
    $formattedMobile = '+' . $cleanDigits;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $conn->prepare("UPDATE app_users SET password_hash = ? WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ?")->bind_param('sss', $hash, $formattedMobile, $clean10);
    $conn->query("DELETE FROM app_otp_store WHERE phone = '$cleanDigits' OR RIGHT(phone, 10) = '$clean10'");
    jsonResponse(['success' => true, 'message' => 'Password reset successfully']);

} elseif ($action === 'send_otp' || $action === 'send-otp' || (isset($b['phone']) && $action !== 'auto_login' && strpos($action, 'driver_') !== 0 && $action !== 'verify_otp' && $action !== 'verify-otp' && $action !== 'check_phone' && $action !== 'set_password' && $action !== 'login_with_password' && $action !== 'reset_password')) {
    $phone = trim($b['phone'] ?? '');
    if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);

    $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $conn = db();
    $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
    $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
    $stmtDel->execute();
    $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->bind_param('ss', $cleanDigits, $otp);
    $stmt->execute();
    $_SESSION['pending_otp'] = ['phone' => $cleanDigits, 'clean10' => $clean10, 'otp' => $otp, 'expires' => time() + 600];

    $svc = 'PAVANCAB';
    $appN = 'PAVANCAB';
    $supP = '+919000000000';
    $result = sendOTPWhatsAppTemplate($cleanDigits, $otp, $svc, 'Passenger', $appN, 'PAVANCAB', $supP);

    $waSent = true;
    $formattedDisplay = '+' . $cleanDigits;
    $msg = "WhatsApp OTP sent to $formattedDisplay!";
    if (is_array($result) && isset($result['success']) && !$result['success']) {
        $waSent = false;
        $msg = "WhatsApp delivery failed. " . ($result['error'] ?? 'Check API token.');
    }
    jsonResponse(['success' => $waSent, 'message' => $msg, 'phone' => $formattedDisplay, 'wa_sent' => $waSent]);
}
