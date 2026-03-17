# PHP Signed Token Bridge

This document defines the expected contract between the legacy PHP site and the Node.js game backend.

## Goal

The PHP website remains the source of user authentication.
The Node.js backend only accepts a signed technical token and never stores raw personal data.

## Exchange flow

1. The PHP site authenticates the member on its side.
2. PHP signs a short-lived JWT dedicated to the game platform.
3. The frontend calls `POST /api/v1/auth/php/exchange` with that JWT.
4. The Node backend verifies the signature and issues its own internal access token.
5. All subsequent HTTP and WebSocket calls use the Node access token.

## Endpoint

- `POST /api/v1/auth/php/exchange`

Request body:

```json
{
  "phpToken": "<signed-jwt>"
}
```

Response body:

```json
{
  "accessToken": "<node-jwt>",
  "tokenType": "Bearer",
  "expiresAt": "2026-03-13T16:00:00.000Z",
  "player": {
    "playerId": "php_a1b2c3d4e5f6",
    "authSource": "php",
    "publicAlias": "Member-AB12CD"
  },
  "bridge": {
    "issuer": "tableaulego-php",
    "audience": "tableaulego-games",
    "subjectHash": "0cf4...",
    "technicalLoyaltyId": "loy_7f8e..."
  }
}
```

## Required JWT claims from PHP

Required:

- `iss`: must match `PHP_TOKEN_ISSUER`
- `aud`: must match `PHP_TOKEN_AUDIENCE`
- `sub`: stable PHP-side user identifier
- `exp`: short expiration

Optional:

- `alias`: public alias to display in the game UI
- `loyaltyId` or `loy`: loyalty identifier from the PHP side

## Example PHP payload

```json
{
  "iss": "tableaulego-php",
  "aud": "tableaulego-games",
  "sub": "legacy-user-42",
  "alias": "Alice",
  "loyaltyId": "CARD-12345",
  "exp": 1773417600
}
```

## Signature configuration

The same shared secret must exist on both sides:

- PHP side: secret used to sign the bridge JWT
- Node side: `PHP_GAME_TOKEN_SECRET`

The backend also checks:

- `PHP_TOKEN_ISSUER`
- `PHP_TOKEN_AUDIENCE`

## Pseudonymization rules

The Node backend does not store raw PHP identifiers.

- `playerId` is derived from `HMAC_SHA256(GAME_TOKEN_SECRET, issuer + ":" + sub)`
- the stored external reference keeps the hashed subject only
- `technicalLoyaltyId` is derived from the loyalty claim using the same HMAC approach

As a result:

- no email, first name, last name or address is stored in Node
- the game platform still keeps a stable technical identity for history, loyalty and duplicate resume

## Recommended operational rules

- PHP bridge tokens should be short-lived, ideally 1 to 5 minutes
- Node-issued access tokens can stay longer for gameplay continuity
- the PHP bridge token must be dedicated to the game platform only
- do not reuse the main website session cookie directly in Node

## Test setup

To enable the bridge locally, set:

```env
PHP_GAME_TOKEN_SECRET=change-me-for-local-bridge
PHP_TOKEN_ISSUER=tableaulego-php
PHP_TOKEN_AUDIENCE=tableaulego-games
```

Then sign a JWT with the same `PHP_GAME_TOKEN_SECRET` and call the exchange endpoint.
