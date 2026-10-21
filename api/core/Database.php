<?php
declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use RuntimeException;
use SimpleXMLElement;

/**
 * Database Singleton
 * 
 * Manages PDO database connection for the application with:
 * - Lazy connection initialization (only connects when a query is executed)
 * - Cloud 12-factor Environment Variable overrides (Render, TiDB Cloud, Aiven, etc.)
 * - XML configuration fallback (config/database.xml)
 * - SSL configuration for remote cloud MySQL providers
 * - Fast timeout and masked diagnostic error logging
 */
class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private ?array $config = null;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct() {
        // Configuration is resolved, but connection is deferred until getPdo()
        $this->config = $this->loadConfig();
    }

    /**
     * Loads database configuration with high-precedence Environment Variable overrides
     * and fallback to config/database.xml.
     */
    public function loadConfig(): array {
        if ($this->config !== null) {
            return $this->config;
        }

        // Safe defaults
        $config = [
            'environment' => 'production',
            'host'        => 'localhost',
            'port'        => 3306,
            'dbname'      => 'vinjack_db',
            'username'    => 'root',
            'password'    => '',
            'charset'     => 'utf8mb4',
            'persistent'  => false,
            'timeout'     => 5,
            'error_mode'  => PDO::ERRMODE_EXCEPTION,
            'fetch_mode'  => PDO::FETCH_ASSOC,
            'ssl_verify'  => false,
            'ssl_ca'      => null
        ];

        // 1. Fallback to XML configuration file
        $xmlPath = $this->resolveConfigPath();
        if ($xmlPath && file_exists($xmlPath)) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($xmlPath);
            if ($xml !== false) {
                if (isset($xml->environment)) {
                    $config['environment'] = (string)$xml->environment;
                }
                if (isset($xml->connection)) {
                    $conn = $xml->connection;
                    if (!empty($conn->host))     $config['host']     = (string)$conn->host;
                    if (!empty($conn->port))     $config['port']     = (int)$conn->port;
                    if (!empty($conn->dbname))   $config['dbname']   = (string)$conn->dbname;
                    if (isset($conn->username))  $config['username'] = (string)$conn->username;
                    if (isset($conn->password))  $config['password'] = (string)$conn->password;
                    if (!empty($conn->charset))  $config['charset']  = (string)$conn->charset;
                }
                if (isset($xml->options)) {
                    $opts = $xml->options;
                    if (isset($opts->persistent)) {
                        $config['persistent'] = filter_var((string)$opts->persistent, FILTER_VALIDATE_BOOLEAN);
                    }
                    if (isset($opts->timeout)) {
                        $config['timeout'] = (int)$opts->timeout;
                    }
                    if (isset($opts->error_mode)) {
                        $config['error_mode'] = strtoupper((string)$opts->error_mode) === 'EXCEPTION' 
                            ? PDO::ERRMODE_EXCEPTION 
                            : PDO::ERRMODE_SILENT;
                    }
                    if (isset($opts->fetch_mode)) {
                        $config['fetch_mode'] = strtoupper((string)$opts->fetch_mode) === 'ASSOC'
                            ? PDO::FETCH_ASSOC
                            : PDO::FETCH_OBJ;
                    }
                    if (isset($opts->ssl_verify)) {
                        $config['ssl_verify'] = filter_var((string)$opts->ssl_verify, FILTER_VALIDATE_BOOLEAN);
                    }
                    if (!empty($opts->ssl_ca)) {
                        $config['ssl_ca'] = (string)$opts->ssl_ca;
                    }
                }
            } else {
                error_log("Database: Failed to parse XML at {$xmlPath}, proceeding with defaults/env vars.");
            }
            libxml_clear_errors();
        }

        // 2. High-Precedence Cloud Environment Overrides (Render, Railway, TiDB, Aiven)
        // Check for full database connection URL (e.g. DATABASE_URL, MYSQL_URL)
        $databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: ($_ENV['DATABASE_URL'] ?? null) ?: ($_SERVER['DATABASE_URL'] ?? null);
        if (!empty($databaseUrl)) {
            $parsed = parse_url($databaseUrl);
            if ($parsed !== false) {
                if (!empty($parsed['host'])) $config['host'] = $parsed['host'];
                if (!empty($parsed['port'])) $config['port'] = (int)$parsed['port'];
                if (!empty($parsed['user'])) $config['username'] = urldecode($parsed['user']);
                if (isset($parsed['pass']))  $config['password'] = urldecode($parsed['pass']);
                if (!empty($parsed['path'])) $config['dbname'] = ltrim($parsed['path'], '/');
            }
        }

        // Check individual environment variables
        $envHost = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: ($_ENV['DB_HOST'] ?? null);
        if (!empty($envHost)) $config['host'] = (string)$envHost;

        $envPort = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: ($_ENV['DB_PORT'] ?? null);
        if (!empty($envPort)) $config['port'] = (int)$envPort;

        $envDb = getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: ($_ENV['DB_NAME'] ?? null);
        if (!empty($envDb)) $config['dbname'] = (string)$envDb;

        $envUser = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: ($_ENV['DB_USER'] ?? null);
        if (!empty($envUser)) $config['username'] = (string)$envUser;

        $envPass = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? null);
        if ($envPass !== false && $envPass !== null) $config['password'] = (string)$envPass;

        $envSslVerify = getenv('MYSQL_SSL_VERIFY') ?: ($_ENV['MYSQL_SSL_VERIFY'] ?? null);
        if ($envSslVerify !== false && $envSslVerify !== null) {
            $config['ssl_verify'] = filter_var($envSslVerify, FILTER_VALIDATE_BOOLEAN);
        }

        $envSslCa = getenv('MYSQL_SSL_CA') ?: getenv('MYSQL_ATTR_SSL_CA') ?: ($_ENV['MYSQL_SSL_CA'] ?? null);
        if (!empty($envSslCa)) {
            $config['ssl_ca'] = (string)$envSslCa;
        }

        $this->config = $config;
        return $config;
    }

    /**
     * Connects to MySQL using PDO with configured parameters.
     */
    private function connect(): void {
        $config = $this->loadConfig();

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";

        $pdoOptions = [
            PDO::ATTR_ERRMODE            => $config['error_mode'],
            PDO::ATTR_DEFAULT_FETCH_MODE => $config['fetch_mode'],
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => $config['persistent'],
            PDO::ATTR_TIMEOUT            => $config['timeout'],
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE utf8mb4_unicode_ci"
        ];

        // Cloud SSL configuration (e.g. TiDB Cloud / Aiven)
        if (defined('PDO::MYSQL_ATTR_SSL_CA') && !empty($config['ssl_ca']) && file_exists($config['ssl_ca'])) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl_ca'];
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') && $config['ssl_verify'] === false) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $pdoOptions);
        } catch (PDOException $e) {
            $maskedUser = !empty($config['username']) ? substr($config['username'], 0, 3) . '***' : '(empty)';
            $logMsg = sprintf(
                "Database connection failure to [%s:%s/%s] as user '%s': %s",
                $config['host'],
                $config['port'],
                $config['dbname'],
                $maskedUser,
                $e->getMessage()
            );
            error_log($logMsg);

            $helpHint = '';
            if (in_array($config['host'], ['localhost', '127.0.0.1'])) {
                $helpHint = " (Note: In Render or Docker environments, 'localhost' points inside the container where MySQL is not running. Configure a remote MySQL host such as TiDB Cloud or Render MySQL via DB_HOST environment variable).";
            }

            throw new RuntimeException("Database connection failure: " . $e->getMessage() . $helpHint, (int)$e->getCode(), $e);
        }
    }

    /**
     * Resolves the path to database.xml across environments.
     */
    private function resolveConfigPath(): ?string {
        $candidatePaths = [
            dirname(__DIR__, 2) . '/config/database.xml', // Root config/database.xml
            dirname(__DIR__) . '/config/database.xml',    // api/config/database.xml
        ];

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $candidatePaths[0] ?? null;
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Convenience method to retrieve the active PDO instance directly.
     */
    public static function getConnection(): PDO {
        return self::getInstance()->getPdo();
    }

    /**
     * Get underlying PDO instance (lazy-connected).
     */
    public function getPdo(): PDO {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Diagnostic helper: Tests connectivity without crashing or throwing unhandled exceptions.
     *
     * @return array{connected: bool, message: string, host?: string, database?: string, latency_ms?: float}
     */
    public static function checkConnection(): array {
        $startTime = microtime(true);
        try {
            $instance = self::getInstance();
            $pdo = $instance->getPdo();
            $stmt = $pdo->query("SELECT 1");
            $stmt->fetch();
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $config = $instance->loadConfig();
            return [
                'connected'  => true,
                'message'    => 'Database connection established successfully.',
                'host'       => $config['host'],
                'database'   => $config['dbname'],
                'latency_ms' => $latency
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'message'   => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Returns sanitized configuration metadata (without passwords) for diagnostics.
     */
    public static function getConfigSummary(): array {
        $config = self::getInstance()->loadConfig();
        return [
            'environment' => $config['environment'],
            'host'        => $config['host'],
            'port'        => $config['port'],
            'dbname'      => $config['dbname'],
            'username'    => !empty($config['username']) ? substr($config['username'], 0, 3) . '***' : '',
            'persistent'  => $config['persistent'],
            'timeout'     => $config['timeout'],
            'ssl_verify'  => $config['ssl_verify']
        ];
    }

    /**
     * Transaction helper: Begin transaction.
     */
    public static function beginTransaction(): bool {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Transaction helper: Commit transaction.
     */
    public static function commit(): bool {
        return self::getConnection()->commit();
    }

    /**
     * Transaction helper: Roll back transaction.
     */
    public static function rollBack(): bool {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }

    /**
     * Check if currently inside a transaction.
     */
    public static function inTransaction(): bool {
        return self::getConnection()->inTransaction();
    }

    // Prevent cloning and unserializing
    private function __clone() {}
    public function __wakeup() {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}

