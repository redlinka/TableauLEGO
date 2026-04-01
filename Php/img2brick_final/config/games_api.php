<?php
/**
 * Games Platform API integration.
 * Handles JWT generation, token exchange, loyalty balance retrieval, and point redemption.
 */

/**
 * Check if we are in local development mode (HTTP allowed).
 */
function games_is_local(): bool
{
    return isset($_ENV['LOCAL_DEVELOPMENT'])
        && ($_ENV['LOCAL_DEVELOPMENT'] === '1' || $_ENV['LOCAL_DEVELOPMENT'] === 'true');
}

/**
 * Validate that the API URL uses HTTPS in production.
 * FIX 4: Block insecure HTTP calls in non-local environments.
 */
function games_validate_url(string $url): void
{
    if (!games_is_local() && !str_starts_with($url, 'https://')) {
        error_log("[SECURITY] Games API URL must use HTTPS in production: $url");
        throw new RuntimeException("Games API URL must use HTTPS in production.");
    }
}

/**
 * Generate a PHP-signed JWT for the games platform bridge.
 * Uses HMAC-SHA256 without external dependencies.
 */
function games_generate_jwt(int $userId, string $username = ''): string
{
    $secret = $_ENV['PHP_GAME_TOKEN_SECRET'] ?? '';
    if (strlen($secret) < 32 && !games_is_local()) {
        throw new RuntimeException("PHP_GAME_TOKEN_SECRET must be at least 32 characters in production.");
    }

    $issuer = $_ENV['PHP_TOKEN_ISSUER'] ?? 'tableaulego-php';
    $audience = $_ENV['PHP_TOKEN_AUDIENCE'] ?? 'tableaulego-games';

    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    $now = time();
    $payload = base64url_encode(json_encode([
        'iss' => $issuer,
        'aud' => $audience,
        'sub' => (string)$userId,
        'alias' => $username ?: ('User-' . $userId),
        'loyaltyId' => (string)$userId,
        'iat' => $now,
        'exp' => $now + 300 // 5 minutes
    ]));

    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payload", $secret, true)
    );

    return "$header.$payload.$signature";
}

/**
 * Exchange a PHP JWT for a games platform access token.
 * FIX 7: Cache reduced to 5 minutes.
 */
function games_exchange_token(int $userId, string $username = ''): ?string
{
    if (!empty($_SESSION['games_access_token']) && !empty($_SESSION['games_token_expires'])) {
        if (time() < $_SESSION['games_token_expires']) {
            return $_SESSION['games_access_token'];
        }
    }

    $apiUrl = $_ENV['GAMES_API_URL'] ?? 'http://127.0.0.1:3001';
    games_validate_url($apiUrl);
    $phpJwt = games_generate_jwt($userId, $username);

    $response = games_http_post("$apiUrl/api/v1/auth/php/exchange", [
        'phpToken' => $phpJwt
    ]);

    if (!$response || !isset($response['accessToken'])) {
        return null;
    }

    $_SESSION['games_access_token'] = $response['accessToken'];
    $_SESSION['games_token_expires'] = time() + 270; // 4.5 min cache (token lives 5 min)

    return $response['accessToken'];
}

/**
 * Get the player's loyalty balance and available tiers.
 */
function games_get_balance(string $accessToken): ?array
{
    $apiUrl = $_ENV['GAMES_API_URL'] ?? 'http://127.0.0.1:3001';
    games_validate_url($apiUrl);

    $response = games_http_get("$apiUrl/api/v1/loyalty/balance", $accessToken);

    if (!$response || !isset($response['summary'])) {
        return null;
    }

    return $response;
}

/**
 * Get loyalty tiers and max discount from the bootstrap endpoint.
 * FIX 7: Cache with 5-minute TTL.
 */
function games_get_tiers(): ?array
{
    if (!empty($_SESSION['games_loyalty_tiers']) && !empty($_SESSION['games_tiers_expires'])) {
        if (time() < $_SESSION['games_tiers_expires']) {
            return $_SESSION['games_loyalty_tiers'];
        }
    }

    $apiUrl = $_ENV['GAMES_API_URL'] ?? 'http://127.0.0.1:3001';
    games_validate_url($apiUrl);

    $response = games_http_get("$apiUrl/api/v1/bootstrap", null);

    if (!$response || !isset($response['loyalty'])) {
        return null;
    }

    $_SESSION['games_loyalty_tiers'] = $response['loyalty'];
    $_SESSION['games_tiers_expires'] = time() + 300; // 5 min TTL
    return $response['loyalty'];
}

/**
 * Redeem loyalty points for a discount.
 */
function games_redeem_points(string $accessToken, int $points, ?string $orderRef = null): ?array
{
    $apiUrl = $_ENV['GAMES_API_URL'] ?? 'http://127.0.0.1:3001';
    games_validate_url($apiUrl);

    $payload = ['points' => $points];
    if ($orderRef !== null) {
        $payload['orderRef'] = substr($orderRef, 0, 64);
    }

    return games_http_post("$apiUrl/api/v1/loyalty/redeem", $payload, $accessToken);
}

/**
 * Build the URL to redirect a user to the games platform with auto-auth.
 * FIX 11: Use URL fragment (#) instead of query parameter to avoid server-side logging.
 */
function games_play_url(int $userId, string $username = ''): string
{
    $gameWebUrl = $_ENV['GAME_WEB_URL'] ?? 'http://127.0.0.1:5173';
    $phpJwt = games_generate_jwt($userId, $username);

    return $gameWebUrl . '#phpToken=' . urlencode($phpJwt);
}

// --- HTTP helpers ---

function games_http_get(string $url, ?string $bearerToken): ?array
{
    $headers = ['Accept: application/json'];
    if ($bearerToken) {
        $headers[] = "Authorization: Bearer $bearerToken";
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

function games_http_post(string $url, array $body, ?string $bearerToken = null): ?array
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($bearerToken) {
        $headers[] = "Authorization: Bearer $bearerToken";
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => json_encode($body),
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
