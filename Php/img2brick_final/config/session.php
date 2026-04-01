<?php
/**
 * Secure session initialization.
 * Must be required BEFORE any session_start() call.
 *
 * Cookie flags:
 *   - Secure:   cookie only sent over HTTPS (disabled in local dev)
 *   - HttpOnly: cookie inaccessible to JavaScript (prevents XSS theft)
 *   - SameSite: cookie not sent on cross-site requests (prevents CSRF)
 */
if (session_status() === PHP_SESSION_NONE) {
    $isLocal = isset($_ENV['LOCAL_DEVELOPMENT'])
        && ($_ENV['LOCAL_DEVELOPMENT'] === '1' || $_ENV['LOCAL_DEVELOPMENT'] === 'true');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !$isLocal,
        'httponly'  => true,
        'samesite'  => 'Strict'
    ]);

    session_start();
}
