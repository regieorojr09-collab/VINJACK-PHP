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
        $path = __DIR__ . '/database.xml';
        if (file_exists($path)) {
            $xml = simplexml_load_file($path);
            if ($xml) {
                $this->dbConfig = json_decode(json_encode($xml), true);
            } else {
                throw new Exception("Failed to parse database.xml");
            }
        } else {
            throw new Exception("database.xml not found");
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
