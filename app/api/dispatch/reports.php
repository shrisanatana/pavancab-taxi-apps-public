<?php
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$startDate = $_REQUEST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_REQUEST['end_date'] ?? date('Y-m-d');

if ($action === 'ride-reports') {
    $rows = dbRows(
        "SELECT DATE(pickup_date) AS date, COUNT(*) AS total_rides,
         SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN 1 ELSE 0 END) AS completed_rides,
         SUM(CASE WHEN UPPER(status) LIKE '%CANCEL%' OR UPPER(status) = 'REJECTED' THEN 1 ELSE 0 END) AS cancelled_rides,
         ROUND(SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare ELSE 0 END), 2) AS total_revenue,
         ROUND(AVG(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare END), 2) AS average_fare
         FROM app_bookings WHERE pickup_date BETWEEN ? AND ?
         GROUP BY DATE(pickup_date) ORDER BY date ASC",
        'ss', [$startDate, $endDate]
    );
    jsonResponse(['data' => $rows]);
}

if ($action === 'ride-reports-summary') {
    $totals = dbRows(
        "SELECT COUNT(*) AS total_rides,
         SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN 1 ELSE 0 END) AS completed,
         SUM(CASE WHEN UPPER(status) LIKE '%CANCEL%' OR UPPER(status) = 'REJECTED' THEN 1 ELSE 0 END) AS cancelled,
         ROUND(SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare ELSE 0 END), 2) AS total_revenue,
         ROUND(AVG(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare END), 2) AS avg_fare
         FROM app_bookings WHERE pickup_date BETWEEN ? AND ?",
        'ss', [$startDate, $endDate]
    );
    $topPickups = dbRows(
        "SELECT pickup_location AS location, COUNT(*) AS count FROM app_bookings
         WHERE pickup_date BETWEEN ? AND ? AND UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS')
         AND pickup_location IS NOT NULL AND pickup_location != ''
         GROUP BY pickup_location ORDER BY count DESC LIMIT 5",
        'ss', [$startDate, $endDate]
    );
    $topDrivers = dbRows(
        "SELECT driver_id, driver_name, COUNT(*) AS completed_rides FROM app_bookings
         WHERE pickup_date BETWEEN ? AND ? AND UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS')
         GROUP BY driver_id, driver_name ORDER BY completed_rides DESC LIMIT 10",
        'ss', [$startDate, $endDate]
    );
    jsonResponse(['totals' => $totals[0] ?? [], 'top_pickup_locations' => $topPickups, 'top_drivers' => $topDrivers]);
}

if ($action === 'commission-report') {
    $days = max(1, intval($_GET['days'] ?? $b['days'] ?? 30));
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    
    // PavanCab model = flat per-ride commission collected from unsubscribed drivers
    $setting = dbRows("SELECT setting_value FROM app_settings WHERE setting_key = 'driver_commission_per_ride' LIMIT 1");
    $cp = !empty($setting) ? floatval($setting[0]['setting_value']) : 200;
    
    try {
        $rows = dbRows(
            "SELECT DATE(pickup_date) AS ride_date,
             COUNT(*) AS ride_count,
             ROUND(SUM(total_fare), 2) AS total_fare,
             ROUND(COUNT(*) * ?, 2) AS commission
             FROM app_bookings
             WHERE UPPER(status) = 'COMPLETED' AND pickup_date BETWEEN ? AND ?
             GROUP BY DATE(pickup_date) ORDER BY ride_date ASC",
            'dss', [$cp, $startDate, $endDate]
        );
        $totalRides = 0; $totalCommission = 0.0; $totalRevenue = 0.0;
        foreach ($rows as $r) {
            $totalRides += intval($r['ride_count']);
            $totalCommission += floatval($r['commission']);
            $totalRevenue += floatval($r['total_fare']);
        }
        jsonResponse([
            'daily' => $rows,
            'data' => $rows,
            'total_commission' => round($totalCommission, 2),
            'total_rides' => $totalRides,
            'total_revenue' => round($totalRevenue, 2),
            'commission_per_ride' => intval($cp),
            'commission_percent' => null,
            'days' => $days
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Report failed: ' . $e->getMessage(), 'daily' => [], 'data' => [], 'total_commission' => 0, 'total_rides' => 0, 'commission_per_ride' => intval($cp)]);
    }
}

if ($action === 'driver-earnings-report') {
    $driverId = intval($_REQUEST['driver_id'] ?? 0);
    $sql = "SELECT b.driver_id, d.name as driver_name, d.phone, COUNT(*) AS total_trips,
            SUM(CASE WHEN UPPER(b.status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN 1 ELSE 0 END) AS completed_trips,
            ROUND(SUM(CASE WHEN UPPER(b.status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN b.total_fare ELSE 0 END), 2) AS total_earnings,
            ROUND(AVG(CASE WHEN UPPER(b.status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN b.total_fare END), 2) AS avg_fare,
            MIN(b.pickup_date) AS first_trip_date, MAX(b.pickup_date) AS last_trip_date
            FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id
            WHERE b.pickup_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate]; $types = 'ss';
    if ($driverId) { $sql .= " AND b.driver_id = ?"; $params[] = $driverId; $types .= 'i'; }
    $sql .= " GROUP BY b.driver_id, d.name, d.phone ORDER BY total_earnings DESC";
    jsonResponse(['data' => dbRows($sql, $types, $params)]);
}

if ($action === 'revenue-trend') {
    $days = max(1, intval($_REQUEST['days'] ?? 30));
    $sinceDate = date('Y-m-d', strtotime("-{$days} days"));
    $rows = dbRows(
        "SELECT DATE(pickup_date) AS date,
         ROUND(SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare ELSE 0 END), 2) AS revenue,
         SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN 1 ELSE 0 END) AS rides
         FROM app_bookings WHERE pickup_date >= ?
         GROUP BY DATE(pickup_date) ORDER BY date ASC",
        's', [$sinceDate]
    );
    jsonResponse(['data' => $rows]);
}

if ($action === 'cab-type-report') {
    $rows = dbRows(
        "SELECT cab_type, COUNT(*) AS count,
         ROUND(SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare ELSE 0 END), 2) AS total_revenue,
         ROUND(AVG(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare END), 2) AS avg_fare
         FROM app_bookings WHERE pickup_date BETWEEN ? AND ?
         GROUP BY cab_type ORDER BY count DESC",
        'ss', [$startDate, $endDate]
    );
    jsonResponse(['data' => $rows]);
}

if ($action === 'trip-type-report') {
    $rows = dbRows(
        "SELECT trip_type, COUNT(*) AS count,
         ROUND(SUM(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare ELSE 0 END), 2) AS total_revenue,
         ROUND(AVG(CASE WHEN UPPER(status) IN ('COMPLETED','COMPLETED_WITH_TIPS') THEN total_fare END), 2) AS avg_fare
         FROM app_bookings WHERE pickup_date BETWEEN ? AND ?
         GROUP BY trip_type ORDER BY count DESC",
        'ss', [$startDate, $endDate]
    );
    jsonResponse(['data' => $rows]);
}

if ($action === 'update-report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    jsonResponse(['success' => true, 'message' => 'Report acknowledged']);
}

jsonResponse(['error' => 'Invalid action'], 400);
