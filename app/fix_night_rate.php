<?php
require_once __DIR__ . '/db.php';
$conn = db();
$conn->query("UPDATE goahourfares SET night_rate = 500 WHERE night_rate = 400");
echo json_encode(['affected' => $conn->affected_rows]);
