<?php
declare(strict_types=1);

function i18n_load_env(): void
{
    $envPath = __DIR__ . '/../.env';
    if (!is_file($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if ((strlen($value) >= 2) && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

i18n_load_env();

function i18n_supported_langs(): array
{
    return [
        'fr' => 'FR',
        'en' => 'EN',
        'es' => 'ES',
    ];
}

function i18n_default_lang(): string
{
    return 'en';
}

function i18n_get_lang(): string
{
    $lang = $_COOKIE['lang'] ?? ($_GET['lang'] ?? i18n_default_lang());
    $lang = strtolower((string)$lang);

    if (!array_key_exists($lang, i18n_supported_langs())) {
        return i18n_default_lang();
    }

    return $lang;
}

function i18n_deepl_key(): ?string
{
    $key = getenv('DEEPL_AUTH_KEY');
    if ($key === false || $key === '') {
        return null;
    }
    return $key;
}

function i18n_target_lang(string $lang): ?string
{
    $lang = strtolower($lang);
    $supported = i18n_supported_langs();
    return $supported[$lang] ?? null;
}

function i18n_load_locale(string $lang): array
{
    static $cache = [];

    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $path = __DIR__ . '/../locales/' . $lang . '.json';
    if (!is_file($path)) {
        $cache[$lang] = [];
        return $cache[$lang];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        $cache[$lang] = [];
        return $cache[$lang];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    $cache[$lang] = $data;
    return $cache[$lang];
}

function tr(string $key, ?string $fallback = null, ?string $lang = null): string
{
    $lang = $lang ?? i18n_get_lang();
    $dict = i18n_load_locale($lang);

    if (isset($dict[$key])) {
        return (string)$dict[$key];
    }

    return $fallback ?? $key;
}

function i18n_should_translate(string $text): bool
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return false;
    }

    if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) || filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return false;
    }

    if (preg_match('/^[\\d\\s\\.,\\-\\+]+$/', $trimmed)) {
        return false;
    }

    return true;
}

function i18n_deepl_translate(array $texts, string $targetLang): ?array
{
    $key = i18n_deepl_key();
    if ($key === null) {
        return null;
    }

    $texts = array_values(array_filter($texts, static function ($text) {
        return is_string($text) && $text !== '';
    }));

    if (empty($texts)) {
        return [];
    }

    $endpoint = getenv('DEEPL_ENDPOINT') ?: 'https://api-free.deepl.com/v2/translate';
    $payload = 'target_lang=' . urlencode($targetLang);
    foreach ($texts as $text) {
        $payload .= '&text=' . urlencode($text);
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: DeepL-Auth-Key ' . $key,
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !isset($decoded['translations'])) {
        return null;
    }

    $result = [];
    foreach ($decoded['translations'] as $translation) {
        if (isset($translation['text'])) {
            $result[] = (string)$translation['text'];
        }
    }

    return $result;
}

function tr_db(string $text, ?string $lang = null): string
{
    $lang = $lang ?? i18n_get_lang();
    if ($lang === i18n_default_lang() || !i18n_should_translate($text)) {
        return $text;
    }

    $target = i18n_target_lang($lang);
    if ($target === null) {
        return $text;
    }

    $translations = i18n_deepl_translate([$text], $target);
    if (!is_array($translations) || !isset($translations[0])) {
        return $text;
    }

    return $translations[0];
}
