<?php
declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Native JWT Implementation (HMAC-SHA256)
 * 
 * Generates and validates standard JSON Web Tokens using native PHP hash functions.
 * No third-party dependencies.
 */
class JWT {
    private const ALGORITHM = 'HS256';
    private const TYPE = 'JWT';

    /**
     * Encode a payload into a signed JWT string.
     *
     * @param array<string, mixed> $payload Custom claims
     * @param string $secret HMAC secret key
     * @param int $expirySeconds Expiration duration in seconds (defaults to 86400 / 24 hrs)
     * @return string Complete encoded JWT token (header.payload.signature)
     */
    public static function encode(array $payload, string $secret, int $expirySeconds = 86400): string {
        if (empty($secret)) {
            throw new InvalidArgumentException("JWT secret cannot be empty.");
        }

        $currentTime = time();
        $tokenPayload = array_merge([
            'iat' => $currentTime,
            'nbf' => $currentTime,
            'exp' => $currentTime + $expirySeconds,
        ], $payload);

        // 1. Header
        $header = [
            'typ' => self::TYPE,
            'alg' => self::ALGORITHM
        ];
        $base64UrlHeader = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));

        // 2. Payload
        $base64UrlPayload = self::base64UrlEncode(json_encode($tokenPayload, JSON_UNESCAPED_SLASHES));

        // 3. Signature
        $signature = hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", $secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSignature}";
    }

    /**
     * Decode and verify a JWT token.
     *
     * @param string $token
     * @param string $secret
     * @return array<string, mixed>|false Decoded payload on success, false if invalid or expired
     */
    public static function decode(string $token, string $secret): array|false {
        if (empty($token) || empty($secret)) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify header
        $headerJson = self::base64UrlDecode($headerB64);
        if (!$headerJson) {
            return false;
        }
        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== self::ALGORITHM) {
            return false;
        }

        // Verify signature with timing-attack resistant comparison
        $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
        $expectedSignatureB64 = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedSignatureB64, $signatureB64)) {
            return false;
        }

        // Verify payload and claims
        $payloadJson = self::base64UrlDecode($payloadB64);
        if (!$payloadJson) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return false;
        }

        $currentTime = time();

        // Expiration check
        if (isset($payload['exp']) && $currentTime > $payload['exp']) {
            return false; // Token has expired
        }

        // Not Before check
        if (isset($payload['nbf']) && $currentTime < $payload['nbf']) {
            return false; // Token not yet valid
        }

        return $payload;
    }

    /**
     * Helper to retrieve secret from app configuration if not provided directly.
     */
    public static function getDefaultSecret(): string {
        $candidatePaths = [
            dirname(__DIR__, 2) . '/config/app.xml',
            dirname(__DIR__) . '/config/app.xml'
        ];

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                $xml = simplexml_load_file($path);
                if ($xml && isset($xml->settings->jwt_secret)) {
                    return (string)$xml->settings->jwt_secret;
                }
            }
        }

        return 'vinjack_super_secret_key_change_me_in_production';
    }

    /**
     * Standard URL-safe Base64 Encoding
     */
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Standard URL-safe Base64 Decoding
     */
    public static function base64UrlDecode(string $data): string|false {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
