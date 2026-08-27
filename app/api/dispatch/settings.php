<?php
require_once __DIR__ . '/../../db.php';

if (empty($_SESSION['user'])) jsonResponse(['error' => 'Authentication required'], 401);

$conn = db();
$action = $_REQUEST['action'] ?? $_GET['action'] ?? '';
$b = getBody();

function upsertSetting($key, $value) {
    $conn = db();
    $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

function upsertSettings($pairs) {
    foreach ($pairs as $key => $value) {
        upsertSetting($key, $value);
    }
}

if ($action === 'whatsapp-config') {
    $rows = dbRows("SELECT id, setting_key, setting_value, updated_at FROM app_settings WHERE setting_key LIKE '%whatsapp%' OR setting_key LIKE '%meta%' OR setting_key LIKE '%phone_id%'");
    jsonResponse(['settings' => $rows]);
}

if ($action === 'update-whatsapp-config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pairs = [];
    foreach ($b as $key => $value) {
        if ($key === 'action') continue;
        $kl = strtolower($key);
        if (strpos($kl, 'whatsapp') !== false || strpos($kl, 'meta') !== false || strpos($kl, 'phone_id') !== false) {
            $pairs[$key] = trim((string)$value);
        }
    }
    if (empty($pairs)) jsonResponse(['error' => 'No whatsapp-related key-value pairs provided'], 400);
    upsertSettings($pairs);
    jsonResponse(['success' => true, 'message' => 'WhatsApp config updated', 'updated' => array_keys($pairs)]);
}

if ($action === 'fcm-config') {
    $rows = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE '%fcm%' OR setting_key LIKE '%firebase%' OR setting_key LIKE '%server_key%' OR setting_key LIKE '%project_id%'");
    jsonResponse(['settings' => $rows]);
}

if ($action === 'update-fcm-config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pairs = [];
    foreach ($b as $key => $value) {
        if ($key === 'action') continue;
        $kl = strtolower($key);
        if (strpos($kl, 'fcm') !== false || strpos($kl, 'firebase') !== false || strpos($kl, 'server_key') !== false || strpos($kl, 'project_id') !== false) {
            $pairs[$key] = trim((string)$value);
        }
    }
    if (empty($pairs)) jsonResponse(['error' => 'No FCM-related key-value pairs provided'], 400);
    upsertSettings($pairs);
    jsonResponse(['success' => true, 'message' => 'FCM config updated', 'updated' => array_keys($pairs)]);
}

if ($action === 'commission-config') {
    $rows = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('platform_commission_percent', 'driver_commission_per_ride', 'driver_subscription_amount', 'driver_ride_min_commission')");
    jsonResponse(['settings' => $rows]);
}

if ($action === 'update-commission-config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed = ['platform_commission_percent', 'driver_commission_per_ride', 'driver_subscription_amount', 'driver_ride_min_commission', 'platform_commission', 'subscription_amount', 'commission_per_ride', 'ride_commission', 'monthly_subscription'];
    $pairs = [];
    foreach ($b as $key => $value) {
        if ($key === 'action') continue;
        $kl = strtolower($key);
        // Accept any key that looks like it's about driver/commission/subscription
        $isAllowed = in_array($kl, $allowed);
        $isDriverRelated = strpos($kl, 'driver') !== false || strpos($kl, 'commission') !== false || strpos($kl, 'subscription') !== false || strpos($kl, 'platform') !== false;
        if ($isAllowed || $isDriverRelated) {
            $pairs[$key] = trim((string)$value);
        }
    }
    if (empty($pairs)) jsonResponse(['error' => 'No commission settings provided. Expected keys: ' . implode(', ', $allowed)], 400);
    upsertSettings($pairs);
    jsonResponse(['success' => true, 'message' => 'Commission config updated', 'updated' => array_keys($pairs)]);
}

if ($action === 'driver-config') {
    $rows = dbRows("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE '%driver%'");
    jsonResponse(['settings' => $rows]);
}

if ($action === 'update-driver-config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pairs = [];
    foreach ($b as $key => $value) {
        if ($key === 'action') continue;
        if (strtolower(strpos($key, 'driver')) === 0 || strpos(strtolower($key), 'driver') !== false) {
            $pairs[$key] = trim((string)$value);
        }
    }
    if (empty($pairs)) jsonResponse(['error' => 'No driver-related key-value pairs provided'], 400);
    upsertSettings($pairs);
    jsonResponse(['success' => true, 'message' => 'Driver config updated', 'updated' => array_keys($pairs)]);
}

if ($action === 'all-settings') {
    $rows = dbRows("SELECT id, setting_key, setting_value, updated_at FROM app_settings ORDER BY setting_key ASC");
    jsonResponse(['settings' => $rows]);
}

if ($action === 'update-setting' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim($b['setting_key'] ?? '');
    $value = trim($b['setting_value'] ?? '');
    if (!$key) jsonResponse(['error' => 'setting_key is required'], 400);
    upsertSetting($key, $value);
    jsonResponse(['success' => true, 'message' => "Setting '$key' updated"]);
}
// No catch-all here — later includes (reports.php) must get a chance to handle their actions.
// index.php provides the final fallback for genuinely unknown actions.
