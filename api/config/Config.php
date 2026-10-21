<?php

class Config {
    private static $instance = null;
    private $dbConfig = null;
    private $appConfig = null;

    private function __construct() {
        $this->loadDbConfig();
        $this->loadAppConfig();
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    private function loadDbConfig() {
        $candidatePaths = [
            __DIR__ . '/database.xml',
            dirname(__DIR__, 2) . '/config/database.xml',
        ];

        $path = null;
        foreach ($candidatePaths as $candidate) {
            if (file_exists($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path) {
            $xml = simplexml_load_file($path);
            if ($xml) {
                $this->dbConfig = json_decode(json_encode($xml), true);
            } else {
                throw new Exception("Failed to parse database.xml at: {$path}");
            }
        } else {
            throw new Exception("database.xml not found in config directories");
        }
    }

    private function loadAppConfig() {
        $path = __DIR__ . '/app.xml';
        if (file_exists($path)) {
            $xml = simplexml_load_file($path);
            if ($xml) {
                $this->appConfig = json_decode(json_encode($xml), true);
            } else {
                throw new Exception("Failed to parse app.xml");
            }
        } else {
            throw new Exception("app.xml not found");
        }
    }

    public function getDb() {
        return $this->dbConfig;
    }

    public function getApp() {
        return $this->appConfig;
    }
}
