<?php
/**
 * PAVANCAB GOA TAXI - Authentication & WhatsApp OTP Module
 * Path: app/auth.php
 * 
 * --------------------------------------------------------------------------
 * EASY-TO-READ CODE DOCUMENTATION FOR BEGINNERS:
 * 1. This file handles user login, logout, and WhatsApp 6-digit OTP verification.
 * 2. When a user requests an OTP, it sends a WhatsApp message via Meta Cloud API.
 * 3. When an OTP is verified, it starts a PHP session and links the FCM token.
 * 4. When a user logs out, it deletes active tokens and destroys the session.
 * --------------------------------------------------------------------------
 */

// Step 1: Include Database helper utilities
require_once __DIR__ . '/db.php';

// Step 2: Read request method and action name
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$b      = getBody();

// Step 3: Handle User Logout Request (Purges session and clears push token)
if ($action === 'logout' || isset($_GET['logout']) || isset($_POST['logout'])) {
    $fcmToken  = trim($b['fcm_token'] ?? $_GET['fcm_token'] ?? $_POST['fcm_token'] ?? '');
    $userId    = intval($_SESSION['user']['id'] ?? 0);
    $userPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? '');

    try {
        $conn = db();
        if ($conn) {
            if ($fcmToken) {
                $fSafe = $conn->real_escape_string($fcmToken);
                $conn->query("DELETE FROM app_fcm_tokens WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_users SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_drivers SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
                $conn->query("UPDATE app_team_members SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
            }
            if ($userPhone) {
                $clean10 = substr($userPhone, -10);
                $conn->query("DELETE FROM app_fcm_tokens WHERE RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
                $conn->query("UPDATE app_users SET is_online = 0, fcm_token = NULL WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
            }
        }
    } catch (Exception $e) {}

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
    if (isset($_COOKIE[session_name()])) {
        @setcookie(session_name(), '', time() - 86400, '/');
    }
    if (isset($_COOKIE['PHPSESSID'])) {
        @setcookie('PHPSESSID', '', time() - 86400, '/');
    }

    if (isJsonRequest()) {
        jsonResponse(['success' => true, 'message' => 'Logged out successfully and FCM push token cleared']);
    }
    
    header('Location: ./index.php');
    exit;
}

// 2. Get Current Session User
if ($method === 'GET' && ($action === 'me' || isset($_GET['me']))) {
    if (isset($_SESSION['user'])) {
        jsonResponse(['success' => true, 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        jsonResponse(['success' => false, 'isLoggedIn' => false, 'user' => null]);
    }
}

if ($method === 'POST') {
    // 3. Verify OTP Code (PRIORITY ACTION - never blocked by FCM)
    if ($action === 'verify_otp' || !empty($b['otp'])) {
        $phone = trim($b['phone'] ?? $_POST['phone'] ?? $_GET['phone'] ?? '');
        $otp   = trim($b['otp'] ?? $_POST['otp'] ?? $_GET['otp'] ?? '');
        $name  = trim($b['name'] ?? $_POST['name'] ?? $_GET['name'] ?? '');
        $email = trim($b['email'] ?? $_POST['email'] ?? $_GET['email'] ?? '');

        if (!$phone || !$otp) {
            jsonResponse(['success' => false, 'error' => 'Phone number and 6-digit OTP code are required.'], 400);
        }

        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);
        $cleanOtp    = trim($otp);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $conn = db();
        $otpValid = false;

        // Check in app_otp_store (matching full digits or 10 digits, only non-expired)
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

        // Check session fallback
        if (!$otpValid && isset($_SESSION['pending_otp'])) {
            if (($_SESSION['pending_otp']['phone'] === $cleanDigits || $_SESSION['pending_otp']['clean10'] === $clean10) && $_SESSION['pending_otp']['otp'] === $cleanOtp) {
                if ($_SESSION['pending_otp']['expires'] > time()) {
                    $otpValid = true;
                }
            }
        }

        if (!$otpValid) {
            jsonResponse(['success' => false, 'error' => 'Invalid or expired OTP code. Please request a new OTP.'], 401);
        }

        unset($_SESSION['pending_otp']);

        // Determine user role strictly
        $role = determineUserRole($cleanDigits, $email);
        $isAdmin = ($role === 'admin');
        $isTeam  = ($role === 'team' || $role === 'admin');

        // Look up member name from app_team_members if team
        $finalName = $name;
        if ($isAdmin) {
            $finalName = 'Niranjan Yamgar (Admin)';
        } elseif ($isTeam) {
            $stmtTm = $conn->prepare("SELECT member_name FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), 10) = ? OR REPLACE(REPLACE(member_phone, '+', ''), ' ', '') = ?) AND is_active = 1 LIMIT 1");
            if ($stmtTm) {
                $stmtTm->bind_param('ss', $clean10, $cleanDigits);
                $stmtTm->execute();
                $rTm = $stmtTm->get_result();
                if ($rTm && $rowTm = $rTm->fetch_assoc()) {
                    $finalName = $rowTm['member_name'];
                }
            }
        }

        if (!$finalName) {
            $finalName = 'Goa Traveler';
        }

        $formattedMobile = '+' . $cleanDigits;

        // Upsert into app_users using prepared statements
        $reqFcm = trim($b['fcm_token'] ?? $_POST['fcm_token'] ?? $_GET['fcm_token'] ?? '');

        $stmtUser = $conn->prepare("SELECT id, name, email, role FROM app_users WHERE mobile = ? OR RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
        $stmtUser->bind_param('ss', $formattedMobile, $clean10);
        $stmtUser->execute();
        $rUser = $stmtUser->get_result();
        $isNewUser = true;

        if ($rUser && $rowU = $rUser->fetch_assoc()) {
            $isNewUser = false;
            $userId = $rowU['id'];
            if (!$name && $rowU['name']) $finalName = $rowU['name'];
            $stmtUpd = $conn->prepare("UPDATE app_users SET name = ?, mobile = ?, role = ? WHERE id = ?");
            $stmtUpd->bind_param('sssi', $finalName, $formattedMobile, $role, $userId);
            $stmtUpd->execute();
        } else {
            $stmtIns = $conn->prepare("INSERT INTO app_users (name, mobile, email, role) VALUES (?, ?, ?, ?)");
            $stmtIns->bind_param('ssss', $finalName, $formattedMobile, $email, $role);
            $stmtIns->execute();
            $userId = $conn->insert_id;
        }

        if (!empty($reqFcm)) {
            $fSafe = $conn->real_escape_string($reqFcm);
            $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");

            $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, updated_at = NOW()");
            if ($stmtFcm) {
                $stmtFcm->bind_param('sss', $reqFcm, $email, $cleanDigits);
                $stmtFcm->execute();
            }
            $stmtFcmUpd = $conn->prepare("UPDATE app_users SET fcm_token = ?, is_online = 1 WHERE id = ?");
            $stmtFcmUpd->bind_param('si', $reqFcm, $userId);
            $stmtFcmUpd->execute();
        }

        // Establish PHP session
        $userSession = [
            'id' => $userId,
            'name' => $finalName,
            'mobile' => $formattedMobile,
            'email' => $email,
            'role' => $role,
            'isAdmin' => $isAdmin,
            'isTeam' => $isTeam,
            'isLoggedIn' => true
        ];

        $_SESSION['user'] = $userSession;

        if ($role === 'user') {
            $loginType = $isNewUser ? 'joined & logged in' : 'logged in';
            $notifBody = "$finalName ($formattedMobile) $loginType to PAVANCAB.";
            if ($isNewUser) $notifBody .= " 🆕 New user!";
            try {
                sendFCMPushToAdmins(
                    "👤 Passenger " . ucfirst($loginType) . " (#$finalName)", 
                    $notifBody, 
                    ['url' => 'https://pavancab.com/app/dashboard/users.php', 'event_type' => 'NEW_BOOKING']
                );
            } catch (Exception $e) {}
        }

        jsonResponse([
            'success' => true,
            'message' => 'Login verified successfully!',
            'user' => $userSession,
            'isAdmin' => $isAdmin,
            'isTeam' => $isTeam,
            'redirect' => ($isAdmin || $isTeam) ? './dashboard/index.html' : './index.php'
        ]);
    } elseif ($action === 'send_otp' || isset($b['phone'])) {
        $phone = trim($b['phone'] ?? '');
        if (!$phone) jsonResponse(['success' => false, 'error' => 'Mobile number is required'], 400);

        $cleanDigits = cleanPhoneDigits($phone, '91');
        $clean10     = substr($cleanDigits, -10);

        if (strlen($cleanDigits) < 7) {
            jsonResponse(['success' => false, 'error' => 'Please enter a valid WhatsApp mobile number with country code.'], 400);
        }

        $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $conn = db();

        // Clear existing OTPs for phone
        $stmtDel = $conn->prepare("DELETE FROM app_otp_store WHERE phone = ? OR phone = ? OR RIGHT(phone, 10) = ?");
        $stmtDel->bind_param('sss', $cleanDigits, $clean10, $clean10);
        $stmtDel->execute();

        $stmt = $conn->prepare("INSERT INTO app_otp_store (phone, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->bind_param('ss', $cleanDigits, $otp);
        $stmt->execute();

        $_SESSION['pending_otp'] = [
            'phone'   => $cleanDigits,
            'clean10' => $clean10,
            'otp'     => $otp,
            'expires' => time() + 600
        ];

        // Send via Meta WhatsApp API
        $text = "🚕 *PAVANCAB GOA TAXI*\n\nYour WhatsApp Verification OTP is: *$otp*\n\nValid for 10 minutes. Do not share this OTP with anyone.";
        $result = sendMetaWhatsApp($cleanDigits, $text);

        $waSent = true;
        $formattedDisplay = '+' . $cleanDigits;
        $msg = "WhatsApp OTP sent to $formattedDisplay!";

        if (is_array($result) && isset($result['success']) && !$result['success']) {
            $waSent = false;
            $msg = "WhatsApp delivery failed. Please check if the API token is active in Admin settings.";
        }

        jsonResponse([
            'success' => $waSent,
            'message' => $msg,
            'phone' => $formattedDisplay,
            'wa_sent' => $waSent
        ]);
    }

    // 6. Standalone FCM Token Registration (optional push sync)
    if ($action === 'save_fcm_token' || $action === 'fcm_token') {
        $email    = trim($b['email'] ?? $b['user_email'] ?? $_SESSION['user']['email'] ?? '');
        $mobile   = trim($b['mobile'] ?? $b['user_mobile'] ?? $_SESSION['user']['mobile'] ?? '');
        $fcmToken = trim($b['fcm_token'] ?? $b['token'] ?? '');
        if (!$fcmToken) {
            jsonResponse(['success' => false, 'message' => 'No token provided'], 200);
        }

        $conn = db();
        $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), updated_at = NOW()");
        if ($stmtFcm) {
            $stmtFcm->bind_param('sss', $fcmToken, $email, $mobile);
            $stmtFcm->execute();
        }

        if (isset($_SESSION['user'])) {
            $_SESSION['user']['fcm_token'] = $fcmToken;
        }

        jsonResponse(['success' => true, 'message' => 'FCM token saved']);
    }

    // 7. Update Profile (name / email)
    if ($action === 'update_profile') {
        if (empty($_SESSION['user'])) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }
        $newName  = trim($b['name'] ?? '');
        $newEmail = trim($b['email'] ?? '');
        $userId   = intval($_SESSION['user']['id'] ?? 0);
        $conn = db();

        if ($newName) {
            $stmt = $conn->prepare("UPDATE app_users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $newName, $userId);
            $stmt->execute();
            $_SESSION['user']['name'] = $newName;
        }
        if ($newEmail) {
            $stmt2 = $conn->prepare("UPDATE app_users SET email = ? WHERE id = ?");
            $stmt2->bind_param('si', $newEmail, $userId);
            $stmt2->execute();
            $_SESSION['user']['email'] = $newEmail;
        }
        jsonResponse(['success' => true, 'message' => 'Profile updated', 'user' => $_SESSION['user']]);
    }
}

if (!isJsonRequest()) {
    $redir = trim($_GET['redirect'] ?? '');
    $target = './index.php?login=1';
    if ($redir) $target .= '&redirect=' . urlencode($redir);
    header('Location: ' . $target);
    exit;
}

jsonResponse(['error' => 'Invalid request method or action'], 400);
