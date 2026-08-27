<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
$conn = db();
if (!$conn) { echo json_encode(['error' => 'DB fail']); exit; }
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
$r = $conn->query("SELECT * FROM app_crash_logs ORDER BY id DESC LIMIT 50");
$rows = [];
if ($r) while ($row = $r->fetch_assoc()) $rows[] = $row;
echo json_encode($rows, JSON_PRETTY_PRINT);
