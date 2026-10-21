<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/JWT.php';
require_once __DIR__ . '/../config/Config.php';

use Core\JWT;

class JWTHelper {
    public static function encode(array $payload): string {
        $config = Config::getInstance()->getApp()['settings'] ?? [];
        $secret = $config['jwt_secret'] ?? JWT::getDefaultSecret();
        $expiry = (int)($config['jwt_expiration_seconds'] ?? 86400);

        return JWT::encode($payload, $secret, $expiry);
    }

    public static function decode(string $jwt): array|false {
        $config = Config::getInstance()->getApp()['settings'] ?? [];
        $secret = $config['jwt_secret'] ?? JWT::getDefaultSecret();

        return JWT::decode($jwt, $secret);
    }
}
