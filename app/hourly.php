<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

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
                $rates['Sedan'] = [
                    '4' => intval($row['4']),
                    '8' => intval($row['8']),
                    '12' => intval($row['12'])
                ];
            } elseif (stripos($cab, 'ertiga') !== false || stripos($cab, 'eartiga') !== false) {
                $rates['Ertiga'] = [
                    '4' => intval($row['4']),
                    '8' => intval($row['8']),
                    '12' => intval($row['12'])
                ];
            } elseif (stripos($cab, 'innova') !== false || stripos($cab, 'suv') !== false) {
                $rates['SUV'] = [
                    '4' => intval($row['4']),
                    '8' => intval($row['8']),
                    '12' => intval($row['12'])
                ];
            } elseif (stripos($cab, 'crysta') !== false) {
                $rates['Crysta'] = [
                    '4' => intval($row['4']),
                    '8' => intval($row['8']),
                    '12' => intval($row['12'])
                ];
            }
        }
    }

    if ($rates['Ertiga']['4'] <= 0) {
        $rates['Ertiga'] = [
            '4' => round($rates['Sedan']['4'] * 1.2),
            '8' => round($rates['Sedan']['8'] * 1.2),
            '12' => round($rates['Sedan']['12'] * 1.2)
        ];
    }
    if ($rates['SUV']['4'] <= 0) {
        $rates['SUV'] = [
            '4' => round($rates['Sedan']['4'] * 1.45),
            '8' => round($rates['Sedan']['8'] * 1.45),
            '12' => round($rates['Sedan']['12'] * 1.45)
        ];
    }
    if ($rates['Crysta']['4'] <= 0) {
        $rates['Crysta'] = [
            '4' => round($rates['Sedan']['4'] * 1.8),
            '8' => round($rates['Sedan']['8'] * 1.8),
            '12' => round($rates['Sedan']['12'] * 1.8)
        ];
    }

    echo json_encode([
        'success' => true,
        'place_id' => $place_id,
        'fares' => $rates,
        'extra' => $extra
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch hourly rates: ' . $e->getMessage()]);
}
