<?php
session_start();
require_once __DIR__ . '/../../db.php';

if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Content-Type: application/json');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$b = getBody();

if ($action === 'logout' || isset($_GET['logout'])) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['isLoggedIn'])) {
        jsonResponse(['success' => false, 'error' => 'Not logged in'], 401);
    }
    $fcmToken = trim($b['fcm_token'] ?? $_GET['fcm_token'] ?? '');
    $userId = intval($_SESSION['user']['id'] ?? 0);
    $userPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? '');
    try {
        $conn = db();
        if ($fcmToken) {
            $fSafe = $conn->real_escape_string($fcmToken);
            $conn->query("DELETE FROM app_fcm_tokens WHERE fcm_token = '$fSafe'");
            $conn->query("UPDATE app_users SET fcm_token = NULL, is_online = 0 WHERE fcm_token = '$fSafe'");
        }
        if ($userPhone) {
            $clean10 = substr($userPhone, -10);
            $conn->query("UPDATE app_users SET is_online = 0 WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'");
        }
        if ($userId) {
            $conn->query("UPDATE app_users SET remember_token = NULL WHERE id = " . intval($userId));
        }
    } catch (Exception $e) {}
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out']);
}

if ($method === 'GET' && ($action === 'me' || isset($_GET['me']))) {
    if (isset($_SESSION['user'])) {
        jsonResponse(['success' => true, 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        jsonResponse(['success' => false, 'isLoggedIn' => false, 'user' => null]);
    }
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/fares.php';

if ($method === 'POST' && ($action === 'save_fcm_token' || $action === 'fcm_token')) {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'message' => 'Login required'], 401);
    $email = trim($b['email'] ?? $b['user_email'] ?? $_SESSION['user']['email'] ?? '');
    $mobile = trim($b['mobile'] ?? $b['user_mobile'] ?? $_SESSION['user']['mobile'] ?? '');
    $fcmToken = trim($b['fcm_token'] ?? $b['token'] ?? '');
    if (!$fcmToken) jsonResponse(['success' => false, 'message' => 'No token provided'], 200);
    $conn = db();
    // FCM tokens are per-device. Reassign ownership strictly to the currently-logged-in user
    // so a previously-logged-in account on this device stops receiving this user's notifications.
    $fSafe = $conn->real_escape_string($fcmToken);
    $conn->query("UPDATE app_users SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
    $conn->query("UPDATE app_drivers SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
    $conn->query("UPDATE app_team_members SET fcm_token = NULL WHERE fcm_token = '$fSafe'");
    $stmtFcm = $conn->prepare("INSERT INTO app_fcm_tokens (fcm_token, user_email, user_mobile, is_online, last_active_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), user_mobile = VALUES(user_mobile), is_online = 1, last_active_at = NOW()");
    if ($stmtFcm) { $stmtFcm->bind_param('sss', $fcmToken, $email, $mobile); $stmtFcm->execute(); }
    if (!empty($mobile)) {
        $clean10 = substr(preg_replace('/\D/', '', $mobile), -10);
        $conn->query("UPDATE app_users SET last_active_at = NOW(), is_online = 1 WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '" . $conn->real_escape_string($clean10) . "'");
    }
    jsonResponse(['success' => true, 'message' => 'FCM token saved']);
}

if ($method === 'POST' && $action === 'update_profile') {
    if (empty($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
    $newName = trim($b['name'] ?? '');
    $newEmail = trim($b['email'] ?? '');
    $userId = intval($_SESSION['user']['id'] ?? 0);
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

jsonResponse(['error' => 'Unknown action: ' . $action], 400);
