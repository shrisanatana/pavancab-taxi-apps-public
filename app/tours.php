<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$place_id = intval($_GET['place_id'] ?? 0);

try {
    $conn = db();
    
    // Detailed descriptions & titles mapped to tour names
    $meta = [
        'North Goa' => [
            'title' => '🌴 North Goa Beaches & Forts Tour',
            'desc' => 'Fort Aguada • Sinquerim Beach • Calangute • Baga • Anjuna Beach • Chapora Fort & Vagator Sunset Point.',
            'duration' => '8-10 Hours',
            'inclusions' => ['AC Private Cab', 'Fuel & Parking', 'Driver Allowance', 'Zero Hidden Fees']
        ],
        'South Goa' => [
            'title' => '⛪ South Goa Heritage & Churches Tour',
            'desc' => 'Old Goa Churches (Basilica of Bom Jesus, Se Cathedral) • Mangueshi Temple • Miramar Beach • Dona Paula Viewpoint & Mandovi Cruise.',
            'duration' => '8-10 Hours',
            'inclusions' => ['AC Private Cab', 'Fuel & Parking', 'Driver Allowance', 'Zero Hidden Fees']
        ],
        'Dudhsagar' => [
            'title' => '🌊 Dudhsagar Waterfall & Spice Farm Tour',
            'desc' => 'Dudhsagar Waterfall Jeep Safari Transfer • Tropical Spice Plantation Guided Tour with Traditional Goan Buffet Lunch.',
            'duration' => 'Full Day (8-10 Hrs)',
            'inclusions' => ['AC Private Cab', 'Fuel & Tolls', 'Driver Allowance', 'Pickup & Drop']
        ]
    ];

    if ($place_id <= 0) {
        // Return distinct active tour names across all places
        $r = $conn->query("SELECT DISTINCT tour_name FROM goatours WHERE is_active = 1 ORDER BY tour_name ASC");
        $toursList = [];
        while ($row = $r->fetch_assoc()) {
            $tName = trim($row['tour_name']);
            $toursList[] = [
                'tour_name' => $tName,
                'title' => $meta[$tName]['title'] ?? ($tName . ' Sightseeing Tour'),
                'desc' => $meta[$tName]['desc'] ?? 'Full Day Private AC Cab Tour across Goa.',
                'duration' => $meta[$tName]['duration'] ?? '8-10 Hours'
            ];
        }
        echo json_encode(['success' => true, 'tours' => $toursList]);
        exit;
    }

    // Query exact available tours for this specific place_id
    $stmt = $conn->prepare("SELECT tour_name, car_type, rate FROM goatours WHERE place_id = ? AND is_active = 1 ORDER BY tour_name ASC");
    $stmt->bind_param('i', $place_id);
    $stmt->execute();
    $r = $stmt->get_result();

    $availableTours = [];

    while ($row = $r->fetch_assoc()) {
        $tName = trim($row['tour_name']);
        $cType = trim($row['car_type']);
        $rate  = intval($row['rate']);

        if (!isset($availableTours[$tName])) {
            $availableTours[$tName] = [
                'tour_name'   => $tName,
                'title'       => $meta[$tName]['title'] ?? ($tName . ' Sightseeing Tour'),
                'desc'        => $meta[$tName]['desc'] ?? 'Full Day Private AC Cab Tour across Goa attractions.',
                'duration'    => $meta[$tName]['duration'] ?? '8-10 Hours',
                'inclusions'  => $meta[$tName]['inclusions'] ?? ['AC Cab', 'Fuel Included', 'Driver Allowance'],
                'Sedan'       => 0,
                'Ertiga'      => 0,
                'SUV'         => 0,
                'Crysta'      => 0
            ];
        }

        if (stripos($cType, 'swift') !== false || stripos($cType, 'sedan') !== false || stripos($cType, 'dzire') !== false) {
            $availableTours[$tName]['Sedan'] = $rate;
        } elseif (stripos($cType, 'ertiga') !== false || stripos($cType, 'eartiga') !== false) {
            $availableTours[$tName]['Ertiga'] = $rate;
        } elseif (stripos($cType, 'innova') !== false || stripos($cType, 'suv') !== false) {
            $availableTours[$tName]['SUV'] = $rate;
        }
    }

    // Compute Crysta or missing tier scales
    foreach ($availableTours as $k => $t) {
        if ($t['Sedan'] > 0 && $t['Ertiga'] <= 0) {
            $availableTours[$k]['Ertiga'] = round($t['Sedan'] * 1.2);
        }
        if ($t['Ertiga'] > 0 && $t['SUV'] <= 0) {
            $availableTours[$k]['SUV'] = round($t['Ertiga'] * 1.2);
        } elseif ($t['Sedan'] > 0 && $t['SUV'] <= 0) {
            $availableTours[$k]['SUV'] = round($t['Sedan'] * 1.45);
        }
        if ($t['SUV'] > 0) {
            $availableTours[$k]['Crysta'] = round($t['SUV'] * 1.25);
        } elseif ($t['Sedan'] > 0) {
            $availableTours[$k]['Crysta'] = round($t['Sedan'] * 1.8);
        }
    }

    echo json_encode([
        'success' => true,
        'place_id' => $place_id,
        'count' => count($availableTours),
        'tours' => array_values($availableTours)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch tours: ' . $e->getMessage()]);
}
