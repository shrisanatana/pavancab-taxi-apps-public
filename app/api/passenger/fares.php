<?php
// Included by passenger index.php — $action, $method, $b available

if ($method === 'GET' && ($action === 'pickups' || $action === 'pickup')) {
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
        while ($row = $r->fetch_assoc()) $places[] = $row;
        jsonResponse(['success' => true, 'pickups' => $places]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch pickup locations: ' . $e->getMessage()], 500);
    }
}

if ($method === 'GET' && ($action === 'drops' || $action === 'drop')) {
    $pickup_id = isset($_GET['pickup_id']) ? intval($_GET['pickup_id']) : 0;
    if ($pickup_id <= 0) jsonResponse(['error' => 'pickup_id parameter is required'], 400);
    try {
        $fares = dbRows("SELECT DISTINCT id, destination, distance, sedan_fare, suv_fare FROM goafares WHERE goaplace_id = ? ORDER BY destination ASC", "i", [$pickup_id]);
        jsonResponse(['success' => true, 'drops' => $fares]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch drop locations: ' . $e->getMessage()], 500);
    }
}

if ($method === 'GET' && ($action === 'hourly' || $action === 'hourly_packages')) {
    $place_id = intval($_GET['place_id'] ?? 0);
    try {
        $conn = db();
        $rates = [
            'Sedan'  => ['4' => 1700, '8' => 2500, '12' => 3500],
            'Ertiga' => ['4' => 2100, '8' => 3100, '12' => 4300],
            'SUV'    => ['4' => 2500, '8' => 3700, '12' => 5100],
            'Crysta' => ['4' => 3100, '8' => 4600, '12' => 6400]
        ];
        $extra = ['km_rate' => 20, 'hr_rate' => 200, 'night_rate' => 500];
        if ($place_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM goahourfares WHERE place_id = ?");
            $stmt->bind_param('i', $place_id);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $cab = trim($row['cab_type']);
                if (isset($row['km_rate']) && intval($row['km_rate']) > 0) $extra['km_rate'] = intval($row['km_rate']);
                if (isset($row['hr_rate']) && intval($row['hr_rate']) > 0) $extra['hr_rate'] = intval($row['hr_rate']);
                if (isset($row['night_rate']) && intval($row['night_rate']) > 0) $extra['night_rate'] = intval($row['night_rate']);
                if (stripos($cab, 'sedan') !== false || stripos($cab, 'swift') !== false || stripos($cab, 'dzire') !== false) {
                    $rates['Sedan'] = ['4' => intval($row['4']), '8' => intval($row['8']), '12' => intval($row['12'])];
                } elseif (stripos($cab, 'ertiga') !== false || stripos($cab, 'eartiga') !== false) {
                    $rates['Ertiga'] = ['4' => intval($row['4']), '8' => intval($row['8']), '12' => intval($row['12'])];
                } elseif (stripos($cab, 'innova') !== false || stripos($cab, 'suv') !== false) {
                    $rates['SUV'] = ['4' => intval($row['4']), '8' => intval($row['8']), '12' => intval($row['12'])];
                } elseif (stripos($cab, 'crysta') !== false) {
                    $rates['Crysta'] = ['4' => intval($row['4']), '8' => intval($row['8']), '12' => intval($row['12'])];
                }
            }
        }
        if ($rates['Ertiga']['4'] <= 0) $rates['Ertiga'] = ['4' => round($rates['Sedan']['4'] * 1.2), '8' => round($rates['Sedan']['8'] * 1.2), '12' => round($rates['Sedan']['12'] * 1.2)];
        if ($rates['SUV']['4'] <= 0) $rates['SUV'] = ['4' => round($rates['Sedan']['4'] * 1.45), '8' => round($rates['Sedan']['8'] * 1.45), '12' => round($rates['Sedan']['12'] * 1.45)];
        if ($rates['Crysta']['4'] <= 0) $rates['Crysta'] = ['4' => round($rates['Sedan']['4'] * 1.8), '8' => round($rates['Sedan']['8'] * 1.8), '12' => round($rates['Sedan']['12'] * 1.8)];
        jsonResponse(['success' => true, 'place_id' => $place_id, 'fares' => $rates, 'extra' => $extra]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch hourly rates: ' . $e->getMessage()], 500);
    }
}

if ($method === 'GET' && ($action === 'tours' || $action === 'tour_packages')) {
    $place_id = intval($_GET['place_id'] ?? 0);
    try {
        $conn = db();
        $meta = [
            'North Goa' => ['title' => 'North Goa Beaches & Forts Tour', 'desc' => 'Fort Aguada, Sinquerim Beach, Calangute, Baga, Anjuna Beach, Chapora Fort & Vagator Sunset Point.', 'duration' => '8-10 Hours', 'inclusions' => ['AC Private Cab', 'Fuel & Parking', 'Driver Allowance', 'Zero Hidden Fees']],
            'South Goa' => ['title' => 'South Goa Heritage & Churches Tour', 'desc' => 'Old Goa Churches, Mangueshi Temple, Miramar Beach, Dona Paula Viewpoint & Mandovi Cruise.', 'duration' => '8-10 Hours', 'inclusions' => ['AC Private Cab', 'Fuel & Parking', 'Driver Allowance', 'Zero Hidden Fees']],
            'Dudhsagar' => ['title' => 'Dudhsagar Waterfall & Spice Farm Tour', 'desc' => 'Dudhsagar Waterfall Jeep Safari Transfer, Tropical Spice Plantation Guided Tour with Traditional Goan Buffet Lunch.', 'duration' => 'Full Day (8-10 Hrs)', 'inclusions' => ['AC Private Cab', 'Fuel & Tolls', 'Driver Allowance', 'Pickup & Drop']]
        ];
        if ($place_id <= 0) {
            $r = $conn->query("SELECT DISTINCT tour_name FROM goatours WHERE is_active = 1 ORDER BY tour_name ASC");
            $toursList = [];
            while ($row = $r->fetch_assoc()) {
                $tName = trim($row['tour_name']);
                $toursList[] = ['tour_name' => $tName, 'title' => $meta[$tName]['title'] ?? ($tName . ' Sightseeing Tour'), 'desc' => $meta[$tName]['desc'] ?? 'Full Day Private AC Cab Tour across Goa.', 'duration' => $meta[$tName]['duration'] ?? '8-10 Hours'];
            }
            jsonResponse(['success' => true, 'tours' => $toursList]);
            return;
        }
        $stmt = $conn->prepare("SELECT tour_name, car_type, rate FROM goatours WHERE place_id = ? AND is_active = 1 ORDER BY tour_name ASC");
        $stmt->bind_param('i', $place_id);
        $stmt->execute();
        $r = $stmt->get_result();
        $availableTours = [];
        while ($row = $r->fetch_assoc()) {
            $tName = trim($row['tour_name']);
            $cType = trim($row['car_type']);
            $rate = intval($row['rate']);
            if (!isset($availableTours[$tName])) {
                $availableTours[$tName] = ['tour_name' => $tName, 'title' => $meta[$tName]['title'] ?? ($tName . ' Sightseeing Tour'), 'desc' => $meta[$tName]['desc'] ?? 'Full Day Private AC Cab Tour across Goa attractions.', 'duration' => $meta[$tName]['duration'] ?? '8-10 Hours', 'inclusions' => $meta[$tName]['inclusions'] ?? ['AC Cab', 'Fuel Included', 'Driver Allowance'], 'Sedan' => 0, 'Ertiga' => 0, 'SUV' => 0, 'Crysta' => 0];
            }
            if (stripos($cType, 'swift') !== false || stripos($cType, 'sedan') !== false || stripos($cType, 'dzire') !== false) {
                $availableTours[$tName]['Sedan'] = $rate;
            } elseif (stripos($cType, 'ertiga') !== false || stripos($cType, 'eartiga') !== false) {
                $availableTours[$tName]['Ertiga'] = $rate;
            } elseif (stripos($cType, 'innova') !== false || stripos($cType, 'suv') !== false) {
                $availableTours[$tName]['SUV'] = $rate;
            }
        }
        foreach ($availableTours as $k => $t) {
            if ($t['Sedan'] > 0 && $t['Ertiga'] <= 0) $availableTours[$k]['Ertiga'] = round($t['Sedan'] * 1.2);
            if ($t['Ertiga'] > 0 && $t['SUV'] <= 0) $availableTours[$k]['SUV'] = round($t['Ertiga'] * 1.2);
            elseif ($t['Sedan'] > 0 && $t['SUV'] <= 0) $availableTours[$k]['SUV'] = round($t['Sedan'] * 1.45);
            if ($t['SUV'] > 0) $availableTours[$k]['Crysta'] = round($t['SUV'] * 1.25);
            elseif ($t['Sedan'] > 0) $availableTours[$k]['Crysta'] = round($t['Sedan'] * 1.8);
        }
        jsonResponse(['success' => true, 'place_id' => $place_id, 'count' => count($availableTours), 'tours' => array_values($availableTours)]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch tours: ' . $e->getMessage()], 500);
    }
}
