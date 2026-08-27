<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$type = trim($_GET['type'] ?? 'all');
$conn = db();

try {
    if ($type === 'oneway') {
        $sql = "SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goafares f ON f.goaplace_id = p.id ORDER BY p.name ASC";
    } elseif ($type === 'hourly') {
        $sql = "SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goahourfares h ON h.place_id = p.id ORDER BY p.name ASC";
    } elseif ($type === 'tour' || $type === 'sightseeing') {
        $sql = "SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goatours t ON t.place_id = p.id AND t.is_active = 1 ORDER BY p.name ASC";
    } else {
        $sql = "SELECT id, name FROM goaplaces ORDER BY name ASC";
    }

    $r = $conn->query($sql);
    $places = [];
    while ($row = $r->fetch_assoc()) {
        $places[] = $row;
    }

    echo json_encode(['success' => true, 'pickups' => $places], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch pickup locations: " . $e->getMessage()]);
}
