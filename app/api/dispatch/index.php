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

try {
    $conn = db();
    $cols = $conn->query("SHOW COLUMNS FROM app_team_members LIKE 'password_hash'");
    if ($cols && $cols->num_rows === 0) {
        $conn->query("ALTER TABLE app_team_members ADD COLUMN password_hash VARCHAR(255) NULL");
    }
} catch (Exception $e) {}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/drivers.php';
require_once __DIR__ . '/team.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/reports.php';

jsonResponse(['error' => 'Unknown action: ' . $action], 400);