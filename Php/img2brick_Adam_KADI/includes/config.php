<?php

// includes/config.php
// Configuration file (session, DB, mail, helpers)


// Helpers


/**
 * Get env variable (Apache/Nginx/CLI).
 * Returns $default if variable is null or empty.
 */
function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

/**
 * Load .env (KEY=VALUE) if exists
 * Goal: make local installation easier 
 */
function load_dotenv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Do not override an env variable.
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}


// Session + language

// Harden session cookie before starting the session.
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax', // helps prevent CSRF on top-level POSTs
    ]);
    session_start();
}

// Load .env 
load_dotenv(__DIR__ . '/../.env');

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

// Switch language ?lang=en|fr (redirection to clean URL)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'fr'], true)) {
    $_SESSION['lang'] = $_GET['lang'];

    $params = $_GET;
    unset($params['lang']);

    $query = http_build_query($params);
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

    header('Location: ' . $path . ($query ? ('?' . $query) : ''));
    exit;
}

// Base URL 
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    define('BASE_URL', $scheme . '://' . $host . $path);
}

// i18n

$langFile = __DIR__ . '/../lang/' . ($_SESSION['lang'] ?? 'en') . '.php';
$translation = file_exists($langFile) ? require $langFile : [];

function t(string $key): string
{
    global $translation;
    return $translation[$key] ?? $key;
}


// Database (PostgreSQL)


define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '5432'));
define('DB_NAME', env('DB_NAME', 'img2brick'));
define('DB_USER', env('DB_USER', 'img2brick_user'));

// Do NOT commit passwords/id here
define('DB_PASS', env('DB_PASS', ''));

try {
    $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    // Message générique pour l'utilisateur, détails côté logs.
    error_log('DB connection error: ' . $e->getMessage());
    die(t('dberrcon'));
}


// Image upload

define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);
define('MIN_DIM', 512);


// Mail (PHPMailer)


if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', env('SMTP_HOST', 'sandbox.smtp.mailtrap.io'));
    define('SMTP_PORT', (int) env('SMTP_PORT', 2525));
    define('SMTP_USER', env('SMTP_USER', ''));
    define('SMTP_PASS', env('SMTP_PASS', ''));
    define('SMTP_FROM', env('SMTP_FROM', 'noreply@img2brick.local'));
    define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'img2brick'));
    define('SMTP_SECURE', env('SMTP_SECURE', 'tls'));
}


// Helpers auth + logs
// CSRF helpers

/**
 * Return the per-session CSRF token (creates if missing).
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Validate CSRF token on POST requests; aborts with 400 on failure.
 * Add a hidden input named "_csrf" with csrf_token() in forms.
 */
function require_csrf_token(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $token = $_POST['_csrf'] ?? '';
    if (!$token || !isset($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], (string)$token)) {
        http_response_code(400);
        echo 'Invalid CSRF token.';
        exit;
    }
}

/**
 * Regenerate session id to prevent fixation.
 */
function regenerate_session_id_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}


function log_event(
    ?int $userId,
    string $type,
    string $action,
    ?string $message = null,
    array $meta = []
): void {
    global $pdo;

    if (!isset($pdo) || !$pdo) {
        return;
    }

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare(
            'INSERT INTO event_logs (user_id, event_type, event_action, message, ip, user_agent, meta)
             VALUES (:uid, :type, :action, :msg, :ip, :ua, :meta::jsonb)'
        );

        $stmt->execute([
            'uid'    => $userId,
            'type'   => $type,
            'action' => $action,
            'msg'    => $message,
            'ip'     => $ip,
            'ua'     => $ua,
            'meta'   => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('log_event error: ' . $e->getMessage());
    }
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}
