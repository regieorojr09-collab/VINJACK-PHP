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
 * Manages the single persistent PDO database connection for the application.
 * Parses connection parameters from XML configuration using SimpleXML.
 */
class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Private constructor to enforce Singleton pattern.
     * Loads database configuration from XML and initializes PDO.
     */
    private function __construct() {
        $xmlPath = $this->resolveConfigPath();
        
        if (!file_exists($xmlPath)) {
            throw new RuntimeException("Database configuration file not found at: {$xmlPath}");
        }

        // Parse XML using SimpleXML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlPath);
        if ($xml === false) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
            libxml_clear_errors();
            throw new RuntimeException("Failed to parse database.xml: " . implode(', ', $errors));
        }

        $connection = $xml->connection;
        $optionsNode = $xml->options;

        $host     = (string)($connection->host ?: 'localhost');
        $port     = (int)($connection->port ?: 3306);
        $dbname   = (string)($connection->dbname ?: 'vinjack_db');
        $username = (string)($connection->username ?: 'root');
        $password = (string)($connection->password ?? '');
        $charset  = (string)($connection->charset ?: 'utf8mb4');

        $isPersistent = filter_var((string)($optionsNode->persistent ?? 'true'), FILTER_VALIDATE_BOOLEAN);
        $errorMode    = strtoupper((string)($optionsNode->error_mode ?? 'EXCEPTION')) === 'EXCEPTION' 
                        ? PDO::ERRMODE_EXCEPTION 
                        : PDO::ERRMODE_SILENT;
        $fetchMode    = strtoupper((string)($optionsNode->fetch_mode ?? 'ASSOC')) === 'ASSOC'
                        ? PDO::FETCH_ASSOC
                        : PDO::FETCH_OBJ;

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $pdoOptions = [
            PDO::ATTR_ERRMODE            => $errorMode,
            PDO::ATTR_DEFAULT_FETCH_MODE => $fetchMode,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => $isPersistent,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $pdoOptions);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failure: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Resolves the path to database.xml across environments.
     */
    private function resolveConfigPath(): string {
        $candidatePaths = [
            dirname(__DIR__, 2) . '/config/database.xml', // Root config/database.xml
            dirname(__DIR__) . '/config/database.xml',    // api/config/database.xml
        ];

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $candidatePaths[0];
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
     * Get underlying PDO instance.
     */
    public function getPdo(): PDO {
        return $this->pdo;
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
