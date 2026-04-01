<?php
header('Content-Type: application/json; charset=utf-8');

$coupons = [
    'WELCOME10' => ['discount' => 10, 'type' => 'percent', 'label' => '10% off'],
    'BRICK20'   => ['discount' => 20, 'type' => 'percent', 'label' => '20% off'],
    'FIRST5'    => ['discount' => 5,  'type' => 'fixed',   'label' => '5 EUR off'],
];

$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code === '') {
    http_response_code(400);
    echo json_encode(['valid' => false, 'error' => 'No coupon code provided']);
    exit;
}

if (isset($coupons[$code])) {
    $c = $coupons[$code];
    echo json_encode([
        'valid'    => true,
        'code'     => $code,
        'discount' => $c['discount'],
        'type'     => $c['type'],
        'label'    => $c['label'],
    ]);
} else {
    http_response_code(404);
    echo json_encode(['valid' => false, 'error' => 'Invalid coupon code']);
}
