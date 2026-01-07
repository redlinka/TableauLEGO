<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$text = $data['text'] ?? null;
$target = $data['target_lang'] ?? ($data['lang'] ?? null);

if ($target === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing target_lang']);
    exit;
}

$targetLang = i18n_target_lang((string)$target);
if ($targetLang === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported language']);
    exit;
}

if (is_string($text)) {
    $texts = [$text];
} elseif (is_array($text)) {
    $texts = $text;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing text']);
    exit;
}

$payloadTexts = [];
$positions = [];
$results = array_fill(0, count($texts), '');

foreach ($texts as $idx => $value) {
    $value = is_string($value) ? $value : '';
    if (!i18n_should_translate($value)) {
        $results[$idx] = $value;
        continue;
    }
    $positions[] = $idx;
    $payloadTexts[] = $value;
}

if (!empty($payloadTexts)) {
    $translations = i18n_deepl_translate($payloadTexts, $targetLang);
    if ($translations === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Translation failed']);
        exit;
    }

    foreach ($positions as $i => $pos) {
        $results[$pos] = $translations[$i] ?? $payloadTexts[$i];
    }
}

echo json_encode(['translations' => $results]);
