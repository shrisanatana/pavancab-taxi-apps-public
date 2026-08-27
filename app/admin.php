<?php
/**
 * Direct route to admin dashboard API or Dispatch Command Center
 */
require_once __DIR__ . '/db.php';

if (isJsonRequest() || isset($_GET['json']) || isset($_GET['action']) || isset($_POST['action'])) {
    require_once __DIR__ . '/api_dashboard.php';
    exit;
}

header('Location: ./dashboard/index.php');
exit;
