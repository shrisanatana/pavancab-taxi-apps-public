<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$pickup_id = isset($_GET['pickup_id']) ? intval($_GET['pickup_id']) : 0;

if ($pickup_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "pickup_id parameter is required"]);
    exit();
}

try {
    $fares = dbRows("SELECT DISTINCT id, destination, distance, sedan_fare, suv_fare FROM goafares WHERE goaplace_id = ? ORDER BY destination ASC", "i", [$pickup_id]);
    echo json_encode(['success' => true, 'drops' => $fares]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch drop locations: " . $e->getMessage()]);
}
?>
