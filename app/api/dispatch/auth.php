<?php
// Dispatch Auth - included by index.php

// Helper: check if phone is super admin
function isSuperAdminPhone($clean10) {
    $super10 = substr(preg_replace('/\D/', '', SUPER_ADMIN_PHONE), -10);
    return ($clean10 && $clean10 === $super10);
}

// CHECK PHONE
if ($method === 'POST' && ($action === 'check_phone' || $action === 'check-phone')) {
    $phone = trim($b['phone'] ?? '');
    if (!$phone) jsonResponse(['success' => false, 'error' => 'Phone required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $conn = db();
    $hasPassword = false;
    $phoneFound = false;
    $r = $conn->query("SELECT id FROM app_team_members WHERE is_active = 1 AND (RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = '$clean10' OR REPLACE(REPLACE(member_phone,'+',''),' ','') = '" . $conn->real_escape_string($cleanDigits) . "') LIMIT 1");
    if ($r && $r->num_rows > 0) { $phoneFound = true; }
    // Super admin is always valid even if not in team_members
    if (!$phoneFound && isSuperAdminPhone($clean10)) { $phoneFound = true; }
    if ($phoneFound) {
        try {
            $rp = $conn->query("SELECT password_hash FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = '$clean10' OR REPLACE(REPLACE(member_phone,'+',''),' ','') = '" . $conn->real_escape_string($cleanDigits) . "') AND is_active = 1 LIMIT 1");
            if ($rp && $row = $rp->fetch_assoc()) { $hasPassword = !empty($row['password_hash']); }
        } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'has_password' => $hasPassword, 'phone_found' => $phoneFound]);
}

// SEND OTP
if ($method === 'POST' && ($action === 'send_otp' || $action === 'send-otp' || (isset($b['phone']) && !isset($b['otp']) && !in_array($action, ['verify_otp','verify-otp','check_phone','check-phone','login_with_password','login-with-password','set_password','set-password','reset_password','reset-password','logout','save_fcm_token','profile-update'])))) {
    $phone = trim($b['phone'] ?? '');
    if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    if (strlen($cleanDigits) < 7) jsonResponse(['success' => false, 'error' => 'Valid WhatsApp number required'], 400);
    $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $conn = db();
    $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
    $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
    $stmtDel->execute();
    $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->bind_param('ss', $cleanDigits, $otp);
    $stmt->execute();
    $_SESSION['pending_otp'] = ['phone' => $cleanDigits, 'clean10' => $clean10, 'otp' => $otp, 'expires' => time() + 600];
    $waSent = false;
    $waMsg = '';
    $result = sendOTPWhatsAppTemplate($cleanDigits, $otp, 'PAVANCAB', 'Passenger', 'PAVANCAB', 'PAVANCAB', '+919000000000');
    if (is_array($result) && isset($result['success']) && $result['success']) { $waSent = true; }
    else { $waMsg = 'WhatsApp delivery failed. OTP stored for verification.'; }
    jsonResponse(['success' => true, 'message' => $waSent ? "OTP sent to +$cleanDigits" : "OTP generated. WhatsApp failed.", 'phone' => '+' . $cleanDigits, 'wa_sent' => $waSent, 'wa_message' => $waMsg]);
}

// VERIFY OTP
if ($method === 'POST' && ($action === 'verify_otp' || $action === 'verify-otp' || (!empty($b['phone']) && !empty($b['otp']) && $action !== 'send_otp' && $action !== 'send-otp' && $action !== 'check_phone' && $action !== 'check-phone' && $action !== 'login_with_password' && $action !== 'login-with-password' && $action !== 'set_password' && $action !== 'set-password' && $action !== 'reset_password' && $action !== 'reset-password'))) {
    $phone = trim($b['phone'] ?? '');
    $otp = trim($b['otp'] ?? '');
    $name = trim($b['name'] ?? '');
    $email = trim($b['email'] ?? '');
    if (!$phone || !$otp) jsonResponse(['success' => false, 'error' => 'Phone and OTP required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $cleanOtp = trim($otp);
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
        $po = $_SESSION['pending_otp'];
        if (($po['phone'] === $cleanDigits || $po['clean10'] === $clean10) && $po['otp'] === $cleanOtp && $po['expires'] > time()) {
            $otpValid = true;
        }
    }

    if (!$otpValid) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP. Please request a new OTP.'], 200);
    unset($_SESSION['pending_otp']);
    session_regenerate_id(true);
    $role = determineUserRole($cleanDigits, $email);
    $isAdmin = ($role === 'admin');
    $isTeam = ($role === 'team' || $role === 'admin');
    if (!$isAdmin && !$isTeam) jsonResponse(['success' => false, 'error' => 'Your account does not have dispatch access. Please contact admin.'], 200);
    $formattedMobile = '+' . $cleanDigits;
    $finalName = $name;
    $teamMemberId = 0;
    $hasPassword = false;

    if ($isAdmin && !$isTeam) $finalName = $finalName ?: 'Niranjan Yamgar (Admin)';
    elseif ($isTeam) {
        try {
            $stmtTm = $conn->prepare("SELECT id, member_name, password_hash FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), 10) = ? OR REPLACE(REPLACE(member_phone, '+', ''), ' ', '') = ?) AND is_active = 1 LIMIT 1");
            if ($stmtTm) {
                $stmtTm->bind_param('ss', $clean10, $cleanDigits);
                $stmtTm->execute();
                $rTm = $stmtTm->get_result();
                if ($rTm && $rowTm = $rTm->fetch_assoc()) {
                    $finalName = $rowTm['member_name'] ?: $finalName;
                    $teamMemberId = intval($rowTm['id']);
                    $hasPassword = !empty($rowTm['password_hash']);
                }
            }
        } catch (Exception $e) {}
    }
    // Super admin not in team_members â€” auto-create
    if ($isAdmin && $teamMemberId === 0) {
        try {
            $stmtIns = $conn->prepare("INSERT INTO app_team_members (member_name, member_phone, member_email, role, is_active, created_at) VALUES (?, ?, ?, 'admin', 1, NOW()) ON DUPLICATE KEY UPDATE is_active = 1");
            $stmtIns->bind_param('sss', $finalName, $formattedMobile, $email);
            $stmtIns->execute();
            $teamMemberId = intval($conn->insert_id);
        } catch (Exception $e) {}
    }
    if (!$finalName) $finalName = 'Dispatch Admin';
    $reqFcm = trim($b['fcm_token'] ?? '');
    if (!empty($reqFcm)) {
        try {
            $fSafe = $conn->real_escape_string($reqFcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), updated_at = NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $reqFcm, $email, $cleanDigits); $stmtFcm->execute(); }
        } catch (Exception $e) {}
    }
    $userSession = ['id' => $teamMemberId, 'name' => $finalName, 'mobile' => $formattedMobile, 'email' => $email, 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'isLoggedIn' => true];
    $_SESSION['user'] = $userSession;
    jsonResponse(['success' => true, 'message' => 'Dispatch login successful!', 'user' => $userSession, 'isAdmin' => $isAdmin, 'isTeam' => $isTeam, 'has_password' => $hasPassword]);
}

// SESSION CHECK
if ($method === 'GET' && ($action === 'me' || $action === 'check-access' || isset($_GET['me']))) {
    if (isset($_SESSION['user'])) {
        jsonResponse(['success' => true, 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        jsonResponse(['success' => false, 'isLoggedIn' => false, 'user' => null]);
    }
}

// LOGOUT
if ($action === 'logout' || isset($_GET['logout'])) {
    if (isset($_SESSION['user'])) {
        $fcmToken = trim($b['fcm_token'] ?? $_GET['fcm_token'] ?? '');
        $userPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? '');
        try {
            $conn = db();
            if ($fcmToken) {
                $fSafe = $conn->real_escape_string($fcmToken);
                $conn->query("DELETE FROM app_fcm_tokens WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            }
        } catch (Exception $e) {}
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out']);
}

// PROFILE UPDATE
if ($method === 'POST' && $action === 'profile-update') {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 200);
    $name = trim($b['name'] ?? $_SESSION['user']['name'] ?? '');
    $email = trim($b['email'] ?? $_SESSION['user']['email'] ?? '');
    $phone = $_SESSION['user']['mobile'] ?? '';
    $conn = db();
    $clean10 = substr(preg_replace('/\D/', '', $phone), -10);
    if ($name && $clean10) {
        $conn->query("UPDATE app_team_members SET member_name = '" . $conn->real_escape_string($name) . "' WHERE RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = '" . $conn->real_escape_string($clean10) . "' AND is_active = 1");
    }
    if ($email) $_SESSION['user']['email'] = $email;
    $_SESSION['user']['name'] = $name;
    jsonResponse(['success' => true, 'message' => 'Profile updated']);
}

// LOGIN WITH PASSWORD
if ($method === 'POST' && ($action === 'login_with_password' || $action === 'login-with-password')) {
    $phone = trim($b['phone'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $conn = db();
    $r = null;
    try { $r = $conn->query("SELECT id, member_name, member_phone, member_email, role FROM app_team_members WHERE is_active = 1 AND (RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = '$clean10' OR REPLACE(REPLACE(member_phone,'+',''),' ','') = '" . $conn->real_escape_string($cleanDigits) . "') LIMIT 1"); } catch (Exception $e) {}
    if (!$r || $r->num_rows === 0) jsonResponse(['success' => false, 'error' => 'Account not found. Please register first.'], 200);
    $row = $r->fetch_assoc();
    $ph = null;
    try { $phRow = $conn->query("SELECT password_hash FROM app_team_members WHERE id = " . intval($row['id']) . " LIMIT 1"); if ($phRow && $phr = $phRow->fetch_assoc()) $ph = $phr['password_hash'] ?? null; } catch (Exception $e) {}
    if (empty($ph) || !password_verify($password, $ph)) jsonResponse(['success' => false, 'error' => 'Invalid password'], 200);
    session_regenerate_id(true);
    $role = $row['role'] ?? 'team';
    $isAdmin = ($role === 'admin');
    $formattedMobile = '+' . $cleanDigits;
    $userSession = ['id' => intval($row['id']), 'name' => $row['member_name'] ?? 'Dispatch Admin', 'mobile' => $formattedMobile, 'email' => $row['member_email'] ?? '', 'role' => $role, 'isAdmin' => $isAdmin, 'isTeam' => true, 'isLoggedIn' => true];
    $_SESSION['user'] = $userSession;
    $reqFcm = trim($b['fcm_token'] ?? '');
    if (!empty($reqFcm)) {
        try {
            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email=VALUES(user_email), user_mobile=VALUES(user_mobile), is_online=1, updated_at=NOW()");
            if ($stmtFcm) { $stmtFcm->bind_param('sss', $reqFcm, $userSession['email'], $cleanDigits); $stmtFcm->execute(); }
        } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => 'Login successful!', 'user' => $userSession, 'isAdmin' => $isAdmin, 'isTeam' => true, 'role' => $role]);
}

// SET PASSWORD
if ($method === 'POST' && ($action === 'set_password' || $action === 'set-password')) {
    $phone = trim($b['phone'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$password) jsonResponse(['success' => false, 'error' => 'Phone and password required'], 400);
    if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $formattedMobile = '+' . $cleanDigits;
    $conn = db();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Try update first
    $stmt = $conn->prepare("UPDATE app_team_members SET password_hash = ? WHERE is_active = 1 AND (RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = ? OR REPLACE(REPLACE(member_phone,'+',''),' ','') = ?)");
    $stmt->bind_param('sss', $hash, $clean10, $cleanDigits);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        // Phone not in team_members â€” insert first (for super admin)
        try {
            $name = 'Niranjan Yamgar (Admin)';
            $role = 'admin';
            if (!isSuperAdminPhone($clean10)) { $name = 'Dispatch Team'; $role = 'team'; }
            $ins = $conn->prepare("INSERT INTO app_team_members (member_name, member_phone, role, is_active, password_hash, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
            $ins->bind_param('ssss', $name, $formattedMobile, $role, $hash);
            $ins->execute();
        } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => 'Password set successfully']);
}

// RESET PASSWORD (requires OTP verification first)
if ($method === 'POST' && ($action === 'reset_password' || $action === 'reset-password')) {
    $phone = trim($b['phone'] ?? '');
    $otp = trim($b['otp'] ?? '');
    $password = trim($b['password'] ?? '');
    if (!$phone || !$otp || !$password) jsonResponse(['success' => false, 'error' => 'Phone, OTP, and new password required'], 400);
    if (strlen($password) < 4) jsonResponse(['success' => false, 'error' => 'Password must be at least 4 characters'], 400);
    $cleanDigits = cleanPhoneDigits($phone, '91');
    $clean10 = substr($cleanDigits, -10);
    $formattedMobile = '+' . $cleanDigits;
    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM app_otp_store WHERE (phone = ? OR phone = ? OR RIGHT(phone, 10) = ?) AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('sss', $cleanDigits, $clean10, $clean10);
    $stmt->execute();
    $r = $stmt->get_result();
    $otpValid = false;
    if ($r && $row = $r->fetch_assoc()) {
        if (trim($row['otp']) === trim($otp)) $otpValid = true;
    }
    if (!$otpValid) jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP'], 200);
    $conn->query("DELETE FROM app_otp_store WHERE phone = '" . $conn->real_escape_string($cleanDigits) . "' OR RIGHT(phone,10) = '$clean10'");
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("UPDATE app_team_members SET password_hash = ? WHERE is_active = 1 AND (RIGHT(REPLACE(REPLACE(REPLACE(member_phone,'+',''),' ',''),'-',''),10) = ? OR REPLACE(REPLACE(member_phone,'+',''),' ','') = ?)");
    $stmt2->bind_param('sss', $hash, $clean10, $cleanDigits);
    $stmt2->execute();
    if ($stmt2->affected_rows === 0) {
        try {
            $name = isSuperAdminPhone($clean10) ? 'Niranjan Yamgar (Admin)' : 'Dispatch Team';
            $role = isSuperAdminPhone($clean10) ? 'admin' : 'team';
            $ins = $conn->prepare("INSERT INTO app_team_members (member_name, member_phone, role, is_active, password_hash, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
            $ins->bind_param('ssss', $name, $formattedMobile, $role, $hash);
            $ins->execute();
        } catch (Exception $e) {}
    }
    jsonResponse(['success' => true, 'message' => 'Password reset successfully. Login with your new password.']);
}

// CHECK ACCESS BY PHONE
if ($method === 'GET' && $action === 'check-access-phone') {
    $phone = trim($_GET['phone'] ?? '');
    $email = trim($_GET['email'] ?? '');
    if (!$phone && !$email) jsonResponse(['valid' => false, 'message' => 'No credentials'], 200);
    $clean10 = substr(preg_replace('/\D/', '', $phone), -10);
    $conn = db();
    $found = false; $role = ''; $name = '';
    if ($clean10) {
        $r = $conn->query("SELECT role, member_name FROM app_team_members WHERE is_active = 1 AND RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = '$clean10' LIMIT 1");
        if ($r && $r->num_rows > 0) { $row = $r->fetch_assoc(); $found = true; $role = $row['role']; $name = $row['member_name']; }
    }
    if (!$found && $email) {
        $safeEmail = $conn->real_escape_string(strtolower($email));
        $r = $conn->query("SELECT role, member_name FROM app_team_members WHERE is_active = 1 AND LOWER(member_email) = '$safeEmail' LIMIT 1");
        if ($r && $r->num_rows > 0) { $row = $r->fetch_assoc(); $found = true; $role = $row['role']; $name = $row['member_name']; }
    }
    if (!$found) {
        if (isSuperAdminPhone($clean10)) { $found = true; $role = 'admin'; $name = 'Super Admin'; }
    }
    jsonResponse(['valid' => $found, 'role' => $role, 'name' => $name, 'message' => $found ? 'Access valid' : 'Your access has been revoked.']);
}
