<?php
/**
 * Loyalty Policy API Endpoint.
 *
 * Returns the current loyalty policy as JSON.
 * The Node.js game backend fetches this to determine reward multipliers,
 * expiration rules, and time-based bonuses.
 *
 * GET /api/loyalty_policy.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=300');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentHour = (int) date('G');   // 0-23
$currentDay  = (int) date('N');   // 1 (Mon) - 7 (Sun)

/**
 * Dynamic temporal multiplier rules.
 * Points are multiplied by the highest matching multiplier.
 */
$temporalRules = [
    [
        'id'         => 'happy_hour_evening',
        'label'      => 'Happy Hour du soir',
        'hours'      => [18, 19, 20, 21],
        'days'       => null,
        'multiplier' => 1.5,
    ],
    [
        'id'         => 'weekend_bonus',
        'label'      => 'Bonus week-end',
        'hours'      => null,
        'days'       => [6, 7],
        'multiplier' => 2.0,
    ],
    [
        'id'         => 'lunch_break',
        'label'      => 'Pause dejeuner',
        'hours'      => [12, 13],
        'days'       => [1, 2, 3, 4, 5],
        'multiplier' => 1.25,
    ],
];

$activeMultiplier = 1.0;
$activeRuleName   = null;

foreach ($temporalRules as $rule) {
    $hourMatch = $rule['hours'] === null || in_array($currentHour, $rule['hours'], true);
    $dayMatch  = $rule['days'] === null || in_array($currentDay, $rule['days'], true);

    if ($hourMatch && $dayMatch && $rule['multiplier'] > $activeMultiplier) {
        $activeMultiplier = $rule['multiplier'];
        $activeRuleName   = $rule['id'];
    }
}

$policy = [
    'version'   => 'policy_v2',
    'updatedAt' => date('c'),

    'expiration' => [
        'defaultDays'  => 30,
        'description'  => 'Les points expirent 30 jours apres leur obtention.',
    ],

    'tiers' => [
        ['points' => 200,  'discountPercent' => 5],
        ['points' => 500,  'discountPercent' => 10],
        ['points' => 1000, 'discountPercent' => 20],
    ],
    'maxDiscountPercent' => 50,

    'temporalRules' => $temporalRules,

    'currentMultiplier' => [
        'value'      => $activeMultiplier,
        'activeRule' => $activeRuleName,
        'serverTime' => date('c'),
        'hour'       => $currentHour,
        'dayOfWeek'  => $currentDay,
    ],
];

echo json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
