<?php

class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    public static function xml($xmlString, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/xml');
        echo $xmlString;
        exit();
    }
}
