<?php
/**
 * PAVANCAB GOA TAXI - Standalone PHP Application Database & Helper Utility
 * Location: /app/db.php
 * 
 * --------------------------------------------------------------------------
 * EASY-TO-READ CODE DOCUMENTATION FOR BEGINNERS:
 * 1. This file opens a connection to our MySQL Database.
 * 2. It creates required database tables automatically if they don't exist yet.
 * 3. It provides simple helper functions like getBody(), cleanPhoneDigits(), and dbRows().
 * --------------------------------------------------------------------------
 */

// Step 1: Start the PHP user session if it is not already running (persists 30 days)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 30 * 24 * 60 * 60,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly'  => true,
        'samesite' => 'Lax'
    ]);
    @ini_set('session.cookie_lifetime', strval(30 * 24 * 60 * 60));
    @ini_set('session.gc_maxlifetime', strval(30 * 24 * 60 * 60));
    session_start();
}

// Step 2: Load .env file if present, otherwise use fallback defaults
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!defined($key)) {
            putenv("$key=$value");
        }
    }
}

// Step 3: Define Database Connection Credentials
define('DB_HOST', getenv('DB_HOST') ?: 'your_db_host');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');

// Step 4: Define Default System Tokens and Admin Contact Information
define('DEFAULT_META_WA_TOKEN', getenv('DEFAULT_META_WA_TOKEN') ?: '');
define('DEFAULT_META_WA_PHONE_ID', getenv('DEFAULT_META_WA_PHONE_ID') ?: '711799862018733');
define('SUPER_ADMIN_EMAIL', getenv('SUPER_ADMIN_EMAIL') ?: 'admin@pavancab-demo.local');
define('SUPER_ADMIN_PHONE', getenv('SUPER_ADMIN_PHONE') ?: '8199000000');
define('FCM_VAPID_KEY', getenv('FCM_VAPID_KEY') ?: '');
if (!defined('FCM_SERVER_KEY')) define('FCM_SERVER_KEY', FCM_VAPID_KEY);

// Step 4b: Razorpay credentials from .env (single source of truth)
define('RAZORPAY_MODE', strtolower(getenv('RAZORPAY_MODE') ?: 'test'));
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: '');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');
define('RAZORPAY_PROD_KEY_ID', getenv('RAZORPAY_PROD_KEY_ID') ?: '');
define('RAZORPAY_PROD_KEY_SECRET', getenv('RAZORPAY_PROD_KEY_SECRET') ?: '');

/**
 * Return active Razorpay [key, secret] pair based on RAZORPAY_MODE.
 * mode = 'live' uses the PROD keys; anything else (default 'test') uses TEST keys.
 */
function razorpayKeys() {
    if (RAZORPAY_MODE === 'live') {
        return [RAZORPAY_PROD_KEY_ID, RAZORPAY_PROD_KEY_SECRET];
    }
    return [RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET];
}

// Step 4: Include FCM Push Notification Engine
require_once __DIR__ . '/fcm_v1.php';

// Step 5: Set Time Zone to Indian Standard Time (IST +05:30)
date_default_timezone_set('Asia/Kolkata');

/**
 * Connect to MySQL Database (Reuses single connection safely)
 */
function db() {
    static $conn = null;
    if ($conn === null) {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
            $conn->query("SET time_zone = '+05:30'");
        } catch (Exception $e) {
            http_response_code(500);
            jsonResponse(['error' => 'Database connection failed. Please try again later.']);
        }

        // Auto-migrations (isolated so failures don't crash the app)
        mysqli_report(MYSQLI_REPORT_OFF);
        $migrations = [
            "CREATE TABLE IF NOT EXISTS app_otp_store (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(20) NOT NULL,
                otp VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phone (phone)
            )",
            "CREATE TABLE IF NOT EXISTS app_config (
                config_key VARCHAR(50) PRIMARY KEY,
                config_value TEXT
            )",
            "CREATE TABLE IF NOT EXISTS app_fcm_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fcm_token VARCHAR(500) NOT NULL,
                user_email VARCHAR(100) NULL,
                user_mobile VARCHAR(50) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_fcm_token (fcm_token(191))
            )",
            "ALTER TABLE app_fcm_tokens MODIFY COLUMN fcm_token VARCHAR(500) NOT NULL",
            "ALTER TABLE app_fcm_tokens MODIFY COLUMN user_mobile VARCHAR(50) NULL",
            "CREATE TABLE IF NOT EXISTS app_team_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                added_by_email VARCHAR(255) NOT NULL DEFAULT 'admin@pavancab-demo.local',
                member_email VARCHAR(255) NULL,
                member_phone VARCHAR(50) NULL,
                member_name VARCHAR(255) NOT NULL,
                permissions TEXT DEFAULT NULL,
                role VARCHAR(50) DEFAULT 'team',
                fcm_token VARCHAR(500) NULL,
                is_active TINYINT(1) DEFAULT 1,
                invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_team_phone (member_phone)
            )",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'team'",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS permissions TEXT NULL",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS member_name VARCHAR(255) NULL",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS member_phone VARCHAR(50) NULL",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS member_email VARCHAR(255) NULL",
            "ALTER TABLE app_team_members ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS driver_decision VARCHAR(50) DEFAULT 'NONE'",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS user_rating INT DEFAULT 0",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS user_review TEXT NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS rated_at DATETIME NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS rating INT DEFAULT 0",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS rating_comment TEXT NULL",
            "ALTER TABLE app_bookings MODIFY COLUMN status VARCHAR(50) DEFAULT 'PENDING'",
            "UPDATE app_bookings SET status = 'PENDING' WHERE status IS NULL OR status = ''",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS rating DECIMAL(3,2) DEFAULT 5.0",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS total_ratings INT DEFAULT 0",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'user'",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS last_active_at DATETIME NULL",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS device_info VARCHAR(255) NULL",
            "ALTER TABLE app_fcm_tokens ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0",
            "ALTER TABLE app_fcm_tokens ADD COLUMN IF NOT EXISTS last_active_at DATETIME NULL",
            "ALTER TABLE app_fcm_tokens ADD COLUMN IF NOT EXISTS device_info VARCHAR(255) NULL",
            "DELETE FROM app_fcm_tokens WHERE fcm_token LIKE 'dTestToken%' OR fcm_token LIKE 'mock%' OR LENGTH(fcm_token) < 50",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS booking_source VARCHAR(20) DEFAULT 'app'",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS booked_by_phone VARCHAR(20) NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS booked_by_name VARCHAR(255) NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS proposed_fare DECIMAL(10,2) NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS fare_proposal_status VARCHAR(20) DEFAULT NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS fare_proposed_by VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS fare_proposal_reason VARCHAR(500) DEFAULT NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS reminder_sent TINYINT(1) DEFAULT 0",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS is_banned TINYINT(1) DEFAULT 0",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL",
            "CREATE TABLE IF NOT EXISTS app_emergency_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NULL,
                user_phone VARCHAR(20) NOT NULL,
                user_name VARCHAR(100) NULL,
                latitude DECIMAL(10,8) NULL,
                longitude DECIMAL(11,8) NULL,
                google_maps_link VARCHAR(255) NULL,
                status VARCHAR(50) DEFAULT 'ACTIVE',
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                resolved_by VARCHAR(100) NULL,
                INDEX idx_status (status),
                INDEX idx_phone (user_phone)
            )",
            "CREATE TABLE IF NOT EXISTS app_ride_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                reporter_phone VARCHAR(20) NOT NULL,
                reporter_name VARCHAR(100) NULL,
                issue_category VARCHAR(50) NOT NULL DEFAULT 'SAFETY',
                severity VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
                description TEXT NOT NULL,
                ride_status_at_report VARCHAR(50) DEFAULT 'ONGOING',
                status VARCHAR(50) DEFAULT 'PENDING',
                admin_response TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_booking (booking_id),
                INDEX idx_status (status),
                INDEX idx_phone (reporter_phone)
            )",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL",
            "CREATE TABLE IF NOT EXISTS app_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS driver_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                driver_id INT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 1000,
                razorpay_order_id VARCHAR(100) NULL,
                razorpay_payment_id VARCHAR(100) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                last_reminder_sent VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_driver (driver_id),
                INDEX idx_status (status)
            )",
            "CREATE TABLE IF NOT EXISTS driver_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                driver_id INT NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'commission',
                booking_id INT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                razorpay_order_id VARCHAR(100) NULL,
                razorpay_payment_id VARCHAR(100) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                INDEX idx_driver (driver_id),
                INDEX idx_status (status),
                INDEX idx_booking (booking_id)
            )",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS has_active_subscription TINYINT(1) DEFAULT 0",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS last_active_at DATETIME NULL",
            "ALTER TABLE driver_subscriptions ADD COLUMN IF NOT EXISTS last_reminder_sent VARCHAR(20) NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS commission_status VARCHAR(20) DEFAULT NULL",
            "ALTER TABLE app_fcm_tokens ADD COLUMN IF NOT EXISTS app_type VARCHAR(20) DEFAULT 'passenger'",
            "CREATE TABLE IF NOT EXISTS app_driver_declined_rides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                driver_id INT NOT NULL,
                booking_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_driver_booking (driver_id, booking_id),
                INDEX idx_driver (driver_id),
                INDEX idx_booking (booking_id)
            )",
            "CREATE TABLE IF NOT EXISTS app_driver_ride_offers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                driver_id INT NOT NULL,
                driver_name VARCHAR(120) DEFAULT '',
                driver_phone VARCHAR(20) DEFAULT '',
                vehicle_number VARCHAR(30) DEFAULT '',
                offer_amount DECIMAL(10,2) NOT NULL,
                offer_note VARCHAR(300) DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_driver_offer (booking_id, driver_id),
                INDEX idx_booking (booking_id),
                INDEX idx_status (status)
            )",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS profile_image VARCHAR(500) NULL",
            "ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_available TINYINT(1) DEFAULT 1",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS passenger_rating INT DEFAULT 0",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS passenger_review TEXT NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS passenger_rated_at DATETIME NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS assigned_by VARCHAR(20) DEFAULT 'admin'",
            "UPDATE app_bookings SET assigned_by = 'admin' WHERE assigned_by IS NULL OR assigned_by = ''",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS base_fare DECIMAL(10,2) DEFAULT 0",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS user_offered_fare DECIMAL(10,2) DEFAULT 0",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS driver_new_ride_notified_at DATETIME NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS is_frozen TINYINT(1) DEFAULT 0",
            "ALTER TABLE app_bookings ADD INDEX IF NOT EXISTS idx_is_frozen (is_frozen)",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS driver_release_ends_at DATETIME NULL",
            "ALTER TABLE app_bookings ADD COLUMN IF NOT EXISTS vehicle_model VARCHAR(120) DEFAULT ''",
            "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('driver_max_offers_per_ride', '5')",
            "ALTER TABLE app_users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL",
            "ALTER TABLE app_users ADD INDEX IF NOT EXISTS idx_remember_token (remember_token)",
            "UPDATE app_bookings SET cab_type='Sedan' WHERE TRIM(cab_type) IN ('0','4','5','6') OR (cab_type IS NOT NULL AND cab_type REGEXP '^[0-9]+$' AND CAST(cab_type AS UNSIGNED) NOT IN (1,2,3))",
            "UPDATE app_bookings SET cab_type='Ertiga' WHERE TRIM(cab_type)='1'",
            "UPDATE app_bookings SET cab_type='SUV' WHERE TRIM(cab_type)='2'",
            "UPDATE app_bookings SET cab_type='Crysta' WHERE TRIM(cab_type)='3'",
            "CREATE TABLE IF NOT EXISTS app_driver_wallet_txns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                driver_id INT NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'deposit',
                amount DECIMAL(10,2) NOT NULL,
                balance_after DECIMAL(10,2) NOT NULL DEFAULT 0,
                reference VARCHAR(100) NULL,
                note VARCHAR(255) NULL,
                booking_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_driver (driver_id),
                INDEX idx_type (type)
            )"
        ];
        foreach ($migrations as $sql) {
            @$conn->query($sql);
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    return $conn;
}

if (!function_exists('classifyRideStatus')) {
    function classifyRideStatus($status) {
        $s = strtoupper(trim((string)$status));
        if (strpos($s, 'CANCEL') !== false || $s === 'REJECTED') return 'CANCELLED';
        if ($s === 'COMPLETED' || $s === 'FINISHED') return 'COMPLETED';
        if ($s === 'IN_TRANSIT' || $s === 'ON_TRIP' || $s === 'ARRIVED') return 'IN_TRANSIT';
        if ($s === 'CONFIRMED' || $s === 'ASSIGNED' || $s === 'ACCEPTED' || $s === 'DRIVER_ASSIGNED') return 'CONFIRMED';
        return 'PENDING';
    }
}

function determineUserRole($phone, $email = '') {
    $clean10 = substr(preg_replace('/\D/', '', (string)$phone), -10);
    
    // 1. Super Admin is strictly by phone number only â€” never by claimed email
    if ($clean10 === '8199000000') {
        return 'admin';
    }
    
    // 2. Check team members table
    try {
        $conn = db();
        if ($clean10) {
            $stmt = $conn->prepare("SELECT role FROM app_team_members WHERE (RIGHT(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), 10) = ? OR member_phone = ?) AND is_active = 1 LIMIT 1");
            $stmt->bind_param('ss', $clean10, $clean10);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && $row = $r->fetch_assoc()) {
                return strtolower($row['role'] ?: 'team');
            }
        }
        if ($email) {
            $emailLower = strtolower($email);
            $stmtEmail = $conn->prepare("SELECT role FROM app_team_members WHERE LOWER(member_email) = ? AND is_active = 1 LIMIT 1");
            $stmtEmail->bind_param('s', $emailLower);
            $stmtEmail->execute();
            $r = $stmtEmail->get_result();
            if ($r && $row = $r->fetch_assoc()) {
                return strtolower($row['role'] ?: 'team');
            }
        }
        if ($clean10) {
            $stmtUserRole = $conn->prepare("SELECT role FROM app_users WHERE RIGHT(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), 10) = ? LIMIT 1");
            $stmtUserRole->bind_param('s', $clean10);
            $stmtUserRole->execute();
            $r = $stmtUserRole->get_result();
            if ($r && $row = $r->fetch_assoc()) {
                $existingRole = strtolower($row['role'] ?? '');
                if ($existingRole === 'team') return 'team';
                if ($existingRole === 'admin' && $clean10 === '8199000000') return 'admin';
            }
        }
    } catch (Exception $e) {}

    return 'user';
}

$conn = db();

function isJsonRequest() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (isset($_GET['json']) || isset($_POST['json'])) return true;
    if (strpos($contentType, 'application/json') !== false) return true;
    if (strpos($accept, 'text/html') !== false) return false;
    return (strpos($accept, 'application/json') !== false);
}

function requireAdminAuth() {
    if (empty($_SESSION['user'])) {
        jsonResponse(['error' => 'Authentication required. Please log in.'], 401);
    }
    $role = $_SESSION['user']['role'] ?? '';
    $isAdmin = !empty($_SESSION['user']['isAdmin']);
    $isTeam = !empty($_SESSION['user']['isTeam']);
    if (!$isAdmin && !$isTeam && $role !== 'admin' && $role !== 'team' && $role !== 'owner') {
        jsonResponse(['error' => 'Admin or Team access required.'], 403);
    }
}

function jsonResponse($data, $code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS, PUT');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getBody() {
    static $parsed = null;
    static $lastRequestTime = 0;
    $now = time();
    if ($parsed !== null && !empty($parsed) && $lastRequestTime === $now) return $parsed;
    $raw = @file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    $parsed = array_merge($_POST, $data);
    $lastRequestTime = $now;
    return $parsed;
}

function cleanPhoneDigits($phone, $countryCode = '91') {
    if (!$phone) return '';
    $str = preg_replace('/\D/', '', (string)$phone);
    if (strlen($str) === 10) {
        $str = $countryCode . $str;
    }
    return $str;
}

function formatIndianDateTime($dateStr, $timeStr) {
    if (!$dateStr) return 'As Soon As Possible';
    $formattedDate = $dateStr;
    try {
        $parts = explode('-', $dateStr);
        if (count($parts) === 3) {
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $m = intval($parts[1]) - 1;
            $formattedDate = intval($parts[2]) . ' ' . ($months[$m] ?? $parts[1]) . ' ' . $parts[0];
        }
    } catch (Exception $e) {}

    $formattedTime = $timeStr ?: '';
    try {
        if ($timeStr && strpos($timeStr, ':') !== false) {
            $timeParts = explode(':', $timeStr);
            $h = intval($timeParts[0]);
            $min = substr($timeParts[1], 0, 2);
            $ampm = $h >= 12 ? 'PM' : 'AM';
            $h = $h % 12;
            $h = $h ? $h : 12;
            $formattedTime = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . $min . ' ' . $ampm;
        }
    } catch (Exception $e) {}
    return $formattedDate . ' at ' . $formattedTime . ' (IST)';
}

function getMetaWaToken() {
    // Env is the single source of truth (set in .env). No DB override.
    return DEFAULT_META_WA_TOKEN;
}

function getMetaWaPhoneId() {
    // Env is the single source of truth (set in .env). No DB override.
    return DEFAULT_META_WA_PHONE_ID;
}

function logWhatsApp($to, $text, $result) {
    $line = date('Y-m-d H:i:s') . " | TO: $to | OK: " . ($result['success'] ? 'YES' : 'NO') . " | ERR: " . ($result['error'] ?? 'none') . " | TEXT: " . mb_substr(trim(str_replace("\n", " ", $text)), 0, 80) . "\n";
    @file_put_contents(__DIR__ . '/whatsapp_log.txt', $line, FILE_APPEND | LOCK_EX);
}

function sendMetaWhatsApp($to, $text) {
    if (!$to) return ['success' => false, 'error' => 'No recipient phone number provided'];
    $target = cleanPhoneDigits($to);
    if (!$target) return ['success' => false, 'error' => 'Invalid phone number format'];
    $token = getMetaWaToken();
    $phoneId = getMetaWaPhoneId();

    $autoFooter = "\n\n---\nThis is auto msg, for more info WhatsApp +919000000000";
    $text = $text . $autoFooter;

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $target,
        'type' => 'text',
        'text' => ['body' => $text]
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init("https://graph.facebook.com/v20.0/{$phoneId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);

    if ($httpCode === 200 && isset($data['messages'])) {
        logWhatsApp($target, $text, ['success' => true]);
        return ['success' => true, 'data' => $data];
    }

    $errMsg = $data['error']['message'] ?? 'Meta WhatsApp API error';
    logWhatsApp($target, $text, ['success' => false, 'error' => $errMsg . ' (HTTP ' . $httpCode . ')']);
    return ['success' => false, 'error' => $errMsg, 'data' => $data];
}

define('OTP_TEMPLATE_NAME', 'pavancabotp');
define('OTP_TEMPLATE_LANG', 'en_US');
define('OTP_TEMPLATE_ID', '1079226261209455');
define('OTP_SUPPORT_PHONE', '+919000000000');
define('OTP_BRAND_NAME', 'PAVANCAB');

function sendOTPWhatsAppTemplate($to, $otp, $serviceName = null, $accountType = null, $appName = null, $companyName = null, $supportPhone = null) {
    if (!$to) return ['success' => false, 'error' => 'No recipient phone number provided'];
    $target = cleanPhoneDigits($to);
    if (!$target) return ['success' => false, 'error' => 'Invalid phone number format'];
    $token = getMetaWaToken();
    $phoneId = getMetaWaPhoneId();

    $svc = $serviceName ?: OTP_BRAND_NAME;
    $acct = $accountType ?: OTP_BRAND_NAME;
    $app = $appName ?: OTP_BRAND_NAME;
    $company = $companyName ?: OTP_BRAND_NAME;
    $supPhone = $supportPhone ?: OTP_SUPPORT_PHONE;

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => $target,
        'type' => 'template',
        'template' => [
            'name' => OTP_TEMPLATE_NAME,
            'language' => ['code' => OTP_TEMPLATE_LANG],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $svc],
                        ['type' => 'text', 'text' => $acct],
                        ['type' => 'text', 'text' => $app],
                        ['type' => 'text', 'text' => strval($otp)],
                        ['type' => 'text', 'text' => strval($company)],
                        ['type' => 'text', 'text' => $supPhone]
                    ]
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $otp]
                    ]
                ]
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init("https://graph.facebook.com/v20.0/{$phoneId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);

    if ($httpCode === 200 && isset($data['messages'])) {
        logWhatsApp($target, "OTP:$otp", ['success' => true]);
        return ['success' => true, 'data' => $data];
    }

    $errMsg = $data['error']['message'] ?? 'Meta WhatsApp template API error';
    logWhatsApp($target, "OTP:$otp FAILED", ['success' => false, 'error' => $errMsg . ' (HTTP ' . $httpCode . ')']);
    return ['success' => false, 'error' => $errMsg, 'data' => $data];
}

function sendMetaWhatsAppParallel(array $targets, $text) {
    if (empty($targets)) return;
    $cleanTargets = array_unique(array_filter(array_map('cleanPhoneDigits', $targets)));
    if (empty($cleanTargets)) return;

    $token = getMetaWaToken();
    $phoneId = getMetaWaPhoneId();
    $mh = curl_multi_init();
    $handles = [];

    foreach ($cleanTargets as $target) {
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $target,
            'type' => 'text',
            'text' => ['body' => $text]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init("https://graph.facebook.com/v20.0/{$phoneId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json"
            ],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.1);
    } while ($running > 0);

    foreach ($handles as $ch) {
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
}

function sendFCMPush($tokens, $title, $body, $data = []) {
    return sendFCMv1Push($tokens, $title, $body, $data);
}

function getFCMTokensByPhone($phone) {
    if (!$phone) return [];
    $rawPhone    = trim((string)$phone);
    $digitsOnly  = preg_replace('/\D/', '', $rawPhone);
    $clean10     = substr($digitsOnly, -10);
    if (!$clean10 || strlen($clean10) < 10) return [];

    $conn = db();
    $tokens = [];
    // PASSENGER-side lookup only: never pulls tokens from app_drivers.
    // app_fcm_tokens rows tagged app_type='driver' are excluded so drivers
    // never receive passenger-facing notifications (e.g. "Driver Accepted").
    $r = $conn->query("SELECT DISTINCT fcm_token FROM (
        SELECT fcm_token FROM app_fcm_tokens WHERE fcm_token IS NOT NULL AND fcm_token != '' AND (app_type IS NULL OR app_type = '' OR app_type = 'passenger') AND RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'
        UNION
        SELECT fcm_token FROM app_users WHERE fcm_token IS NOT NULL AND fcm_token != '' AND RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'
        UNION
        SELECT fcm_token FROM app_team_members WHERE is_active = 1 AND fcm_token IS NOT NULL AND fcm_token != '' AND RIGHT(REPLACE(REPLACE(REPLACE(member_phone, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'
    ) t");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $tok = trim($row['fcm_token']);
            if ($tok) $tokens[] = $tok;
        }
    }
    return array_values(array_unique($tokens));
}

function getFCMTokensByEmail($email) {
    if (!$email) return [];
    $conn = db();
    $cleanEmail = $conn->real_escape_string(strtolower(trim($email)));
    $tokens = [];
    $r = $conn->query("SELECT DISTINCT fcm_token FROM (
        SELECT fcm_token FROM app_users WHERE LOWER(email) = '$cleanEmail' AND fcm_token IS NOT NULL AND fcm_token != ''
        UNION
        SELECT fcm_token FROM app_fcm_tokens WHERE LOWER(user_email) = '$cleanEmail' AND fcm_token IS NOT NULL AND fcm_token != ''
        UNION
        SELECT fcm_token FROM app_team_members WHERE is_active = 1 AND LOWER(member_email) = '$cleanEmail' AND fcm_token IS NOT NULL AND fcm_token != ''
    ) t");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $tok = trim($row['fcm_token']);
            if ($tok) $tokens[] = $tok;
        }
    }
    return array_values(array_unique($tokens));
}

function getDriverFCMTokens($driverIdOrPhone) {
    if (!$driverIdOrPhone) return [];
    $conn = db();
    $clean10 = '';
    $tokens = [];

    if (is_numeric($driverIdOrPhone) && intval($driverIdOrPhone) > 0) {
        $driverId = intval($driverIdOrPhone);
        $r = $conn->query("SELECT fcm_token FROM app_drivers WHERE id = $driverId AND fcm_token IS NOT NULL AND fcm_token != '' LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) {
            $tok = trim($row['fcm_token']);
            if ($tok) $tokens[] = $tok;
        }
    }
    $digitsOnly = preg_replace('/\D/', '', (string)$driverIdOrPhone);
    $clean10 = substr($digitsOnly, -10);
    if ($clean10 && strlen($clean10) >= 10) {
        $r = $conn->query("SELECT DISTINCT fcm_token FROM (
            SELECT fcm_token FROM app_drivers WHERE fcm_token IS NOT NULL AND fcm_token != '' AND RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'
            UNION
            SELECT fcm_token FROM app_fcm_tokens WHERE fcm_token IS NOT NULL AND fcm_token != '' AND app_type = 'driver' AND RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$clean10'
        ) t");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $tok = trim($row['fcm_token']);
                if ($tok) $tokens[] = $tok;
            }
        }
    }
    return array_values(array_unique($tokens));
}

function getAdminFCMTokens() {
    $conn = db();
    $adminPhone = '8199000000';
    $adminEmail = strtolower(SUPER_ADMIN_EMAIL);
    $r = $conn->query("SELECT DISTINCT fcm_token FROM (
        SELECT fcm_token FROM app_users WHERE fcm_token IS NOT NULL AND fcm_token != '' AND (
            LOWER(email) = '$adminEmail'
            OR RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = '$adminPhone'
            OR role IN ('ADMIN', 'OWNER', 'team')
        )
        UNION
        SELECT fcm_token FROM app_fcm_tokens WHERE fcm_token IS NOT NULL AND fcm_token != '' AND (
            LOWER(user_email) = '$adminEmail'
            OR RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = '$adminPhone'
        )
        UNION
        SELECT fcm_token FROM app_team_members WHERE is_active = 1 AND fcm_token IS NOT NULL AND fcm_token != ''
    ) t");
    $tokens = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $tok = trim($row['fcm_token']);
            if ($tok) $tokens[] = $tok;
        }
    }
    return array_values(array_unique($tokens));
}

function sendFCMPushToUser($email, $phone, $title, $body, $data = []) {
    $tokens = [];
    if (!empty($email)) $tokens = array_merge($tokens, getFCMTokensByEmail($email));
    if (!empty($phone)) $tokens = array_merge($tokens, getFCMTokensByPhone($phone));
    $tokens = array_values(array_unique(array_map('trim', array_filter($tokens))));
    if (!empty($tokens)) {
        return sendFCMPush($tokens, $title, $body, $data);
    }
    return ['sent' => 0, 'failed' => 0, 'errors' => ['No active device token for recipient user']];
}

function sendFCMPushToDriver($driverIdOrPhone, $title, $body, $data = []) {
    $tokens = getDriverFCMTokens($driverIdOrPhone);
    if (!empty($tokens)) {
        return sendFCMPush($tokens, $title, $body, $data);
    }
    return ['sent' => 0, 'failed' => 0, 'errors' => ['No active device token for driver']];
}

function sendFCMPushToAdmins($title, $body, $data = []) {
    $tokens = getAdminFCMTokens();
    if (!empty($tokens)) {
        return sendFCMPush($tokens, $title, $body, $data);
    }
    return ['sent' => 0, 'failed' => 0, 'errors' => ['No active admin/team tokens']];
}

/**
 * Universal Ride Lifecycle Push Dispatcher
 * Guarantees that every party (Passenger, Driver, Admin/Team) receives accurate notifications
 * with direct deep-links on every state transition.
 */
function broadcastRideLifecycleFCM($event, $bookingId, $customData = []) {
    $conn = db();
    $bookingId = intval($bookingId);
    if (!$bookingId) return false;

    $rows = dbRows("SELECT b.*, 
                    COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
                    COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
                    COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, '') as vehicle_number,
                    d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
                    FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id WHERE b.id = ?", 'i', [$bookingId]);
    if (empty($rows)) return false;
    $bk = $rows[0];

    $ref = $bk['booking_ref'] ?: $bk['id'];
    $custName = $bk['customer_name'] ?: 'Passenger';
    $custPhone = $bk['customer_phone'] ?: '';
    $custEmail = $bk['user_email'] ?: '';
    $driverName = $bk['driver_name'] ?: 'Goa Cab Driver';
    $driverPhone = $bk['driver_phone'] ?: '';
    $vehNo = $bk['vehicle_number'] ?: '';
    $cabType = $bk['cab_type'] ?: 'Sedan';
    $pickup = $bk['pickup_location'] ?: 'Goa';
    $drop = $bk['drop_location'] ?: 'Destination';
    $fare = floatval($bk['total_fare'] ?? 0);

    $baseData = array_merge([
        'booking_id'  => strval($bookingId),
        'booking_ref' => strval($ref),
        'status'      => strval($bk['status']),
        'event'       => strval($event),
        'event_type'  => strval($event),
        'url'         => 'https://pavancab.com/app/rides.php?id=' . $bookingId
    ], $customData);

    $adminData = array_merge($baseData, [
        'url' => 'https://pavancab.com/app/dashboard/index.php'
    ]);

    $fcmResults = [];
    $broadcastSentTokens = [];

    function sendToUserDedup($email, $phone, $title, $body, $data, &$sent) {
        $tokens = [];
        if (!empty($email)) $tokens = array_merge($tokens, getFCMTokensByEmail($email));
        if (!empty($phone)) $tokens = array_merge($tokens, getFCMTokensByPhone($phone));
        $tokens = array_values(array_unique(array_map('trim', array_filter($tokens))));
        $fresh = array_values(array_diff($tokens, $sent));
        $sent = array_merge($sent, $fresh);
        if (!empty($fresh)) return sendFCMPush($fresh, $title, $body, $data);
        return ['sent' => 0, 'failed' => 0, 'errors' => ['Deduped - already sent to this device']];
    }

    function sendToAdminsDedup($title, $body, $data, &$sent) {
        $tokens = getAdminFCMTokens();
        $fresh = array_values(array_diff($tokens, $sent));
        $sent = array_merge($sent, $fresh);
        if (!empty($fresh)) return sendFCMPush($fresh, $title, $body, $data);
        return ['sent' => 0, 'failed' => 0, 'errors' => ['Deduped - already sent to this device']];
    }

    function sendToDriverDedup($phone, $title, $body, $data, &$sent) {
        $tokens = getDriverFCMTokens($phone);
        $fresh = array_values(array_diff($tokens, $sent));
        $sent = array_merge($sent, $fresh);
        if (!empty($fresh)) return sendFCMPush($fresh, $title, $body, $data);
        return ['sent' => 0, 'failed' => 0, 'errors' => ['Deduped - already sent to this device']];
    }

    switch (strtoupper($event)) {
        case 'NEW_BOOKING':
            // Admin FCM handled by notifyAdminAndTeamNewBooking (with placer exclusion)
            if ($custPhone) {
                $isPhoneBooking = (($bk['booking_source'] ?? 'app') === 'phone');
                $trackLine = $isPhoneBooking
                    ? "\n\nðŸ“± To track your booking, download *Pavancab App* from Play Store and login with your WhatsApp number to see live booking status."
                    : "\n\nWe'll assign a driver shortly. You'll receive driver details on WhatsApp.";
                $waResult = sendMetaWhatsApp($custPhone, "ðŸŽ‰ *PAVANCAB Booking Placed!*\n\nHi *$custName*,\n\nYour booking *#$ref* has been placed successfully.\n\nðŸ“ Pickup: $pickup\nðŸ“ Drop: $drop\nðŸš— Cab: $cabType\nðŸ’° Fare: â‚¹$fare\nðŸ“… Date: " . ($bk['pickup_date'] ?? '') . " | â° Time: " . ($bk['pickup_time'] ?? '') . $trackLine . "\n\nThank you for choosing PAVANCAB! ðŸ™");
                $fcmResults['whatsapp'] = $waResult;
            }
            break;

        case 'DRIVER_ASSIGNED':
            $fcmResults['admins'] = sendToAdminsDedup(
                "âœ… Booking Confirmed (#$ref)", 
                "$driverName ($driverPhone) assigned to ride #$ref for $custName. Vehicle: $vehNo", 
                array_merge($adminData, ['type' => 'BOOKING_CONFIRMED']), $broadcastSentTokens
            );
            $fcmResults['user'] = sendToUserDedup($custEmail, $custPhone, 
                "âœ… Booking Confirmed!", 
                "Driver $driverName ($driverPhone) has been assigned to your ride #$ref. Vehicle: $vehNo. Driver is on the way!", 
                array_merge($baseData, ['type' => 'DRIVER_ASSIGNED', 'status' => 'CONFIRMED']), $broadcastSentTokens
            );
            if ($driverPhone) {
                $fcmResults['driver'] = sendToDriverDedup($driverPhone, 
                    "ðŸš• New Ride Assignment (#$ref)", 
                    "Assigned to ride for $custName ($custPhone). Pickup: $pickup -> $drop. Fare: â‚¹$fare", 
                    $baseData, $broadcastSentTokens
                );
                $tripType = $bk['trip_type'] ?? 'one_way';
                $pickupDT = formatIndianDateTime($bk['pickup_date'] ?? '', $bk['pickup_time'] ?? '');
                $notes = $bk['special_notes'] ?? '';
                $notesLine = $notes ? "\nðŸ“ Notes: $notes" : '';
                $collectMsg = "ðŸ’° *Collect from guest: â‚¹$fare*";
                $tripTypeLabel = strtoupper(str_replace('_', ' ', $tripType));
                $subOffer = '';
                $cleanDriver10 = substr(preg_replace('/\D/', '', $driverPhone), -10);
                if ($cleanDriver10) {
                    $drvRow = dbRows("SELECT id FROM app_drivers WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$cleanDriver10]);
                    if (!empty($drvRow)) {
                        $drvId = intval($drvRow[0]['id']);
                        $now = date('Y-m-d');
                        $drvSub = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= ? LIMIT 1", 'is', [$drvId, $now]);
                        if (empty($drvSub)) {
                            $subOffer = "\n\nðŸ’¡ *SUBSCRIPTION OFFER*\nPay â‚¹1000/month for unlimited rides with zero commission! Or pay â‚¹200 per ride after each trip.\nOpen Driver App to subscribe.";
                        }
                    }
                }
                sendMetaWhatsApp($driverPhone, "ðŸš• *NEW RIDE ASSIGNMENT*\n\nðŸ“‹ Booking: *#$ref*\nðŸ—“ Trip: *$tripTypeLabel*\n\nðŸ‘¤ *Customer Details:*\nName: *$custName*\nPhone: $custPhone\nWhatsApp: https://wa.me/91" . substr(preg_replace('/\D/', '', $custPhone), -10) . "\n\nðŸ“ *Pickup:* $pickup\nðŸ“ *Drop:* $drop\nðŸ• *Schedule:* $pickupDT\nðŸš— *Cab:* $cabType | $vehNo$notesLine\n\n$collectMsg\n\nðŸ‘‰ Drive safe and reach pickup on time!" . $subOffer);
            }
            break;

        case 'DRIVER_ACCEPTED':
            $fcmResults['user'] = sendToUserDedup($custEmail, $custPhone, 
                "ðŸš– Driver Accepted Your Ride!", 
                "Driver $driverName accepted booking #$ref! Cab is on the way to $pickup.", 
                array_merge($baseData, ['type' => 'DRIVER_ACCEPTED', 'status' => 'CONFIRMED']), $broadcastSentTokens
            );
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "âœ… *Driver Accepted Your Ride!*\n\nHi *$custName*,\n\nDriver *$driverName* has accepted your booking *#$ref*.\n\nðŸ“± Contact: $driverPhone\nðŸ“ Pickup: $pickup\n\nCab is on the way to pick you up!");
            }
            break;

        case 'DRIVER_DECLINED':
            $fcmResults['admins'] = sendToAdminsDedup(
                "ðŸš¨ Driver Declined Ride (#$ref)!", 
                "Driver $driverName DECLINED ride #$ref. Please re-assign a driver immediately!", 
                $adminData, $broadcastSentTokens
            );
            break;

        case 'RIDE_STARTED':
        case 'IN_TRANSIT':
            $fcmResults['user'] = sendToUserDedup($custEmail, $custPhone, 
                "ðŸš– Ride Started / On Trip!", 
                "Driver $driverName has started trip #$ref to $drop. Enjoy your Goa ride!", 
                array_merge($baseData, ['type' => 'RIDE_STARTED', 'status' => 'IN_TRANSIT']), $broadcastSentTokens
            );
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "ðŸš— *Ride Started!*\n\nHi *$custName*,\n\nYour ride *#$ref* with driver *$driverName* has started.\n\nðŸ“ Drop: $drop\nðŸ’° Fare: â‚¹$fare\n\nEnjoy your ride with PAVANCAB! ðŸŒ´");
            }
            break;

        case 'RIDE_COMPLETED':
        case 'COMPLETED':
            $fcmResults['user'] = sendToUserDedup($custEmail, $custPhone, 
                "ðŸŽ‰ Ride Completed!", 
                "Trip #$ref completed. Total fare: â‚¹$fare. Thank you for choosing PAVANCAB Goa!", 
                array_merge($baseData, ['type' => 'RIDE_COMPLETED', 'status' => 'COMPLETED']), $broadcastSentTokens
            );
            if ($driverPhone) {
                $fcmResults['driver'] = sendToDriverDedup($driverPhone, 
                    "ðŸŽ‰ Ride Completed (#$ref)", 
                    "Trip #$ref has been completed successfully. Great job!", 
                    $baseData, $broadcastSentTokens
                );
            }
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "ðŸŽ‰ *Ride Completed!*\n\nHi *$custName*,\n\nYour ride *#$ref* has been completed successfully!\n\nðŸ’° Total Fare: â‚¹$fare\nðŸ“ Trip: $pickup â†’ $drop\n\nThank you for choosing PAVANCAB! ðŸ™\n\nWe'd love your feedback! Please leave us a review on Trustpilot:\nhttps://www.trustpilot.com/review/pavancab.com\n\nYour support means the world to us! â¤ï¸");
            }
            break;

        case 'RIDE_RESET':
        case 'RESET':
        case 'PENDING':
            $prevDriverPhone = $customData['prev_driver_phone'] ?? '';
            if ($prevDriverPhone) {
                sendToDriverDedup($prevDriverPhone, 
                    "âš ï¸ Ride Unassigned (#$ref)", 
                    "Ride #$ref has been reset by dispatch and unassigned.", 
                    $baseData, $broadcastSentTokens
                );
                sendMetaWhatsApp($prevDriverPhone, "âš ï¸ *Ride Unassigned*\n\nRide *#$ref* has been reset by dispatch and unassigned from you.");
            }
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "ðŸ”„ *Ride Reset*\n\nHi *$custName*,\n\nYour ride *#$ref* has been reset by our dispatch team.\n\nðŸ“ Pickup: $pickup\nðŸ“ Drop: $drop\n\nPlease wait â€” we're assigning a new driver for you. You'll receive driver details shortly.\n\nTrack: https://pavancab.com/app/rides.php?id=$bookingId\n\nSorry for the inconvenience! ðŸ™");
            }
            break;

        case 'CANCELLED_BY_USER':
            if ($driverPhone) {
                sendToDriverDedup($driverPhone, 
                    "ðŸš« Ride Cancelled by Passenger (#$ref)", 
                    "Ride #$ref for $custName was cancelled by the passenger.", 
                    $baseData, $broadcastSentTokens
                );
                sendMetaWhatsApp($driverPhone, "ðŸš« *Ride Cancelled by Passenger*\n\nRide *#$ref* for $custName ($custPhone) has been cancelled by the passenger.\nPickup: $pickup â†’ Drop: $drop");
            }
            sendToAdminsDedup(
                "ðŸš« Passenger Cancelled (#$ref)", 
                "Passenger $custName ($custPhone) cancelled ride #$ref.", 
                $adminData, $broadcastSentTokens
            );
            break;

        case 'CANCELLED_BY_ADMIN':
            $fcmResults['user'] = sendToUserDedup($custEmail, $custPhone, 
                "ðŸš« Ride Cancelled by Dispatch", 
                "Booking #$ref was cancelled by PAVANCAB Dispatch. Contact +91 8199000000 for help.", 
                array_merge($baseData, ['type' => 'RIDE_CANCELLED', 'status' => 'CANCELLED_BY_ADMIN']), $broadcastSentTokens
            );
            if ($driverPhone) {
                $fcmResults['driver'] = sendToDriverDedup($driverPhone, 
                    "ðŸš« Ride Cancelled by Dispatch (#$ref)", 
                    "Ride #$ref has been cancelled by Dispatch.", 
                    $baseData, $broadcastSentTokens
                );
                sendMetaWhatsApp($driverPhone, "ðŸš« *Ride Cancelled*\n\nRide *#$ref* has been cancelled by dispatch. No further action needed.");
            }
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "ðŸš« *Ride Cancelled*\n\nHi *$custName*,\n\nYour booking *#$ref* has been cancelled by PAVANCAB Dispatch.\n\nðŸ“ Pickup: $pickup\nðŸ“ Drop: $drop\n\nFor any queries, contact:\nðŸ“ž +91 8199000000\n\nWe're sorry for the inconvenience. ðŸ™");
            }
            break;

        case 'FARE_BOOSTED':
            $boostAmt = floatval($customData['boost_amount'] ?? 0);
            if ($driverPhone) {
                sendToDriverDedup($driverPhone, 
                    "ðŸ”¥ Passenger Boosted Fare! (#$ref)", 
                    "Fare for ride #$ref increased by +â‚¹$boostAmt to â‚¹$fare! Come pick up now!", 
                    $baseData, $broadcastSentTokens
                );
                sendMetaWhatsApp($driverPhone, "ðŸ”¥ *Fare Boosted!*\n\nRide *#$ref* fare increased by +â‚¹$boostAmt to *â‚¹$fare*! Head to pickup now!");
            }
            sendToAdminsDedup(
                "ðŸ”¥ Fare Boosted (#$ref)", 
                "Passenger $custName boosted fare +â‚¹$boostAmt to â‚¹$fare for ride #$ref.", 
                $adminData, $broadcastSentTokens
            );
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "ðŸ”¥ *Fare Boosted!*\n\nHi *$custName*,\n\nYour fare for ride *#$ref* has been boosted by +â‚¹$boostAmt.\n\nðŸ’° New Fare: â‚¹$fare\n\nDriver has been notified and is on the way!");
            }
            break;

        case 'FARE_UPDATED':
            $reason = $customData['reason'] ?? 'Dispatch adjustment';
            $fcmResults['user'] = sendToUserDedup($custEmail, $custPhone, 
                "ðŸ’° Fare Updated (#$ref)", 
                "Ride #$ref fare updated to â‚¹$fare ($reason).", 
                array_merge($baseData, ['type' => 'FARE_UPDATED']), $broadcastSentTokens
            );
            if ($driverPhone) {
                sendToDriverDedup($driverPhone, 
                    "ðŸ’° Fare Updated (#$ref)", 
                    "Ride #$ref fare updated to â‚¹$fare ($reason).", 
                    $baseData, $broadcastSentTokens
                );
                sendMetaWhatsApp($driverPhone, "ðŸ’° *Fare Updated*\n\nRide *#$ref* fare updated to *â‚¹$fare*.\nReason: $reason");
            }
            if ($custPhone) {
                sendMetaWhatsApp($custPhone, "ðŸ’° *Fare Updated*\n\nHi *$custName*,\n\nYour ride *#$ref* fare has been updated to *â‚¹$fare*.\nReason: $reason");
            }
            break;

        case 'RIDE_FROZEN':
            if ($driverPhone) {
                sendToDriverDedup($driverPhone, 
                    "â¸ï¸ Ride Frozen by Dispatch (#$ref)", 
                    "Ride #$ref has been frozen by dispatch and removed from your ride list.", 
                    $baseData, $broadcastSentTokens
                );
            }
            sendToAdminsDedup(
                "â¸ï¸ Ride Frozen (#$ref)", 
                "Ride #$ref for $custName has been frozen by dispatch.", 
                $adminData, $broadcastSentTokens
            );
            break;

        case 'RIDE_UNFROZEN':
            if ($driverPhone) {
                sendToDriverDedup($driverPhone, 
                    "â–¶ï¸ Ride Unfrozen (#$ref)", 
                    "Ride #$ref has been unfrozen and is available again.", 
                    $baseData, $broadcastSentTokens
                );
            }
            sendToAdminsDedup(
                "â–¶ï¸ Ride Unfrozen (#$ref)", 
                "Ride #$ref for $custName has been unfrozen by dispatch.", 
                $adminData, $broadcastSentTokens
            );
            break;
    }

    return $fcmResults ?: true;
}

function dbRows($sql, $types = '', $params = []) {
    $conn = db();
    if (empty($params)) {
        $r = $conn->query($sql);
        $rows = [];
        while ($row = $r->fetch_assoc()) $rows[] = $row;
        return $rows;
    }
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $r = $stmt->get_result();
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function dbExec($sql, $types = '', $params = []) {
    $conn = db();
    if (empty($params)) {
        return $conn->query($sql);
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

if (!function_exists('driverWalletBalance')) {
    /** Current wallet balance = SUM of ledger amounts */
    function driverWalletBalance($driverId) {
        $rows = dbRows("SELECT COALESCE(SUM(amount),0) as bal FROM app_driver_wallet_txns WHERE driver_id = ?", 'i', [intval($driverId)]);
        return round(floatval($rows[0]['bal'] ?? 0), 2);
    }
}

if (!function_exists('driverWalletTxn')) {
    /** Append a wallet ledger row atomically with fresh balance_after */
    function driverWalletTxn($driverId, $type, $amount, $note = '', $reference = null, $bookingId = null) {
        $balAfter = round(driverWalletBalance($driverId) + floatval($amount), 2);
        dbExec("INSERT INTO app_driver_wallet_txns (driver_id, type, amount, balance_after, reference, note, booking_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
            'isdsssi', [$driverId, $type, round(floatval($amount), 2), $balAfter, $reference, $note, $bookingId]);
        return $balAfter;
    }
}

if (!function_exists('getAppSetting')) {
    function getAppSetting($key, $default = null) {
        try {
            $rows = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1", 's', [$key]);
            if (!empty($rows)) return $rows[0]['setting_value'];
        } catch (Exception $e) {}
        return $default;
    }
}

if (!function_exists('driverCommissionPerRide')) {
    function driverCommissionPerRide() {
        return floatval(getAppSetting('driver_commission_per_ride', 200));
    }
}

if (!function_exists('driverSubscriptionAmount')) {
    function driverSubscriptionAmount() {
        $rows = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_subscription_amount' LIMIT 1");
        return floatval($rows[0]['setting_value'] ?? 1000);
    }
}

if (!function_exists('driverHasActiveSubscription')) {
    function driverHasActiveSubscription($driverId) {
        $rows = dbRows("SELECT id FROM driver_subscriptions WHERE driver_id = ? AND status = 'active' AND end_date >= CURDATE() LIMIT 1", 'i', [intval($driverId)]);
        return !empty($rows);
    }
}

function getAdminAndTeamPhones() {
    $conn = db();
    $phones = [SUPER_ADMIN_PHONE, '8199000000'];
    try {
        $r = $conn->query("SELECT member_phone FROM app_team_members WHERE is_active = 1 AND member_phone IS NOT NULL AND member_phone != ''");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $p = cleanPhoneDigits($row['member_phone']);
                if ($p) $phones[] = $p;
            }
        }
    } catch (Exception $e) {}
    return array_values(array_unique(array_filter($phones)));
}

function notifyAdminAndTeamSOS($sosId, $bookingId, $userPhone, $userName, $lat, $lng, $mapsLink) {
    $nameStr = $userName ? $userName : 'Passenger';
    $phoneStr = $userPhone ? $userPhone : 'Unknown';
    $bookingStr = $bookingId ? "#" . $bookingId : 'General Ride';

    // 1. Instant FCM Push to all Admin and Team devices
    $fcmTokens = getAdminFCMTokens();
    if (!empty($fcmTokens)) {
        sendFCMPush(
            $fcmTokens,
            "ðŸš¨ EMERGENCY SOS - PAVAN CAB",
            "SOS from {$nameStr} ({$phoneStr})! Tap to view live location.",
            [
                'type' => 'EMERGENCY_SOS',
                'sos_id' => (string)$sosId,
                'booking_id' => (string)$bookingId,
                'maps_link' => (string)$mapsLink
            ]
        );
    }

    // 2. WhatsApp message dispatch
    try {
        $recipients = getAdminAndTeamPhones();
        $waMessage = "ðŸš¨ *PAVAN CAB EMERGENCY SOS ALERT!* ðŸš¨\n\n"
                   . "âš ï¸ Passenger *{$nameStr}* (ðŸ“ž +{$phoneStr}) has triggered an EMERGENCY SOS alert!\n"
                   . "ðŸš– Booking Reference: *{$bookingStr}*\n"
                   . "ðŸ“ GPS Location Link: " . ($mapsLink ?: "Not provided") . "\n"
                   . "ðŸ•’ Time: " . date('Y-m-d H:i:s') . " IST\n\n"
                   . "ðŸ‘‰ Please contact rider immediately or dispatch emergency assistance from Pavan Cab Tower!";

        foreach ($recipients as $targetPhone) {
            sendMetaWhatsApp($targetPhone, $waMessage);
        }
    } catch (Exception $e) {}
}

function notifyAdminAndTeamRideReport($reportId, $bookingId, $reporterPhone, $reporterName, $category, $severity, $description) {
    $nameStr = $reporterName ? $reporterName : 'Passenger';
    $catStr = strtoupper(str_replace('_', ' ', $category));
    $sevStr = strtoupper($severity);

    // 1. Instant FCM Push
    $fcmTokens = getAdminFCMTokens();
    if (!empty($fcmTokens)) {
        sendFCMPush(
            $fcmTokens,
            "âš ï¸ Ride Reported (#{$bookingId})",
            "Category: {$catStr} - Severity: {$sevStr}",
            [
                'type' => 'RIDE_REPORT',
                'report_id' => (string)$reportId,
                'booking_id' => (string)$bookingId
            ]
        );
    }

    // 2. WhatsApp message dispatch
    try {
        $recipients = getAdminAndTeamPhones();
        $waMessage = "âš ï¸ *PAVAN CAB RIDE REPORT FIRED!* âš ï¸\n\n"
                   . "ðŸš– Booking ID: *#{$bookingId}*\n"
                   . "ðŸ‘¤ Reported By: *{$nameStr}* (ðŸ“ž +{$reporterPhone})\n"
                   . "ðŸ“Œ Issue Category: *{$catStr}*\n"
                   . "ðŸš¨ Severity Level: *{$sevStr}*\n"
                   . "ðŸ“ Summary: \"{$description}\"\n\n"
                   . "ðŸ‘‰ Check Dispatch Tower to review and resolve this report.";

        sendMetaWhatsAppParallel($recipients, $waMessage);
    } catch (Exception $e) {}
}

function notifyAdminAndTeamNewBooking($bookingId, $bookingRef, $customerName, $customerPhone, $pickup, $drop, $pickupDate, $pickupTime, $cabType, $totalFare, $bookedByPhone = '', $bookedByName = '') {
    $nameStr = $customerName ? $customerName : 'Passenger';
    $indianDT = formatIndianDateTime($pickupDate, $pickupTime);

    // FCM push to admin/team (exclude placer for phone bookings)
    try {
        $allTokens = getAdminFCMTokens();
        if (!empty($bookedByPhone) && count($allTokens) > 1) {
            $placerTokens = getFCMTokensByPhone($bookedByPhone);
            $allTokens = array_values(array_diff($allTokens, $placerTokens));
        }
        if (!empty($allTokens)) {
            $isPhone = !empty($bookedByName);
            $title = $isPhone ? "ðŸ“ž Phone Booking (#$bookingRef)" : "ðŸš• New Goa Taxi Booking (#$bookingRef)";
            $body = $isPhone
                ? "Phone booking by $bookedByName for $nameStr ($customerPhone). Pickup: $pickup -> $drop. Cab: $cabType. Fare: â‚¹$totalFare"
                : "New $cabType booking from $nameStr ($customerPhone) for $pickup -> $drop. Fare: â‚¹$totalFare";
            $fcmData = [
                'booking_id' => strval($bookingId),
                'booking_ref' => strval($bookingRef),
                'status' => 'PENDING',
                'event' => $isPhone ? 'PHONE_BOOKING' : 'NEW_BOOKING',
                'event_type' => $isPhone ? 'PHONE_BOOKING' : 'NEW_BOOKING',
                'type' => $isPhone ? 'PHONE_BOOKING' : 'NEW_BOOKING',
                'url' => 'https://pavancab.com/app/dashboard/index.php'
            ];
            sendFCMPush($allTokens, $title, $body, $fcmData);
        }
    } catch (Exception $e) {}

    // WhatsApp message dispatch
    try {
        $recipients = getAdminAndTeamPhones();
        $sourceLabel = !empty($bookedByName) ? "\n\nðŸ“ž Booked by: *$bookedByName*" : "";
        $waMessage = "ðŸš¨ *NEW GOA TAXI BOOKING!*\n"
                   . "Ref: *#{$bookingRef}*\n"
                   . "Passenger: *{$nameStr}* (ðŸ“ž +{$customerPhone})\n"
                   . "Pickup: *{$pickup}*\n"
                   . "Drop: *{$drop}*\n"
                   . "Cab: *{$cabType}*\n"
                   . "Schedule: *{$indianDT}*\n"
                   . "Total Fare: *â‚¹{$totalFare}*"
                   . "$sourceLabel\n\n"
                   . "ðŸ‘‰ Open Dispatch Tower to assign driver now!";

        sendMetaWhatsAppParallel($recipients, $waMessage);
    } catch (Exception $e) {}
}

