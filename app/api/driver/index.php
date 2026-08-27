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

// Auto-migrate driver table columns
try {
    $conn = db();
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_online TINYINT(1) DEFAULT 0");
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0");
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) NULL");
    @$conn->query("ALTER TABLE app_drivers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL");
} catch (Exception $e) {}

// Route to sub-modules
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/wallet.php';

jsonResponse(['error' => 'Unknown action: ' . $action], 400);
