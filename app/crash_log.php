<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = getBody();
    $logType = trim($b['type'] ?? 'CRASH');
    $message = trim($b['message'] ?? '');
    $stackTrace = trim($b['stacktrace'] ?? $b['stack_trace'] ?? '');
    $deviceInfo = trim($b['device_info'] ?? $b['device'] ?? '');
    $appVersion = trim($b['app_version'] ?? '');
    $screen = trim($b['screen'] ?? '');

    if (empty($message) && empty($stackTrace)) {
        echo json_encode(['success' => false, 'message' => 'No data']);
        exit;
    }

    try {
        $conn = db();
        $conn->query("CREATE TABLE IF NOT EXISTS app_crash_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_type VARCHAR(50) DEFAULT 'CRASH',
            message TEXT,
            stack_trace TEXT,
            device_info TEXT,
            app_version VARCHAR(50),
            screen_name VARCHAR(100),
            created_at DATETIME DEFAULT NOW()
        )");

        $stmt = $conn->prepare("INSERT INTO app_crash_logs (log_type, message, stack_trace, device_info, app_version, screen_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $logType, $message, $stackTrace, $deviceInfo, $appVersion, $screen);
        $stmt->execute();

        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'POST only']);
