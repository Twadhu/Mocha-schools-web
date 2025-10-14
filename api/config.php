<?php
// Unified configuration for Schools-sites project

declare(strict_types=1);

// Database credentials (adjust if needed)
const DB_HOST = '127.0.0.1';
const DB_NAME = 'schools_db';
const DB_USER = 'root';
const DB_PASS = '';

date_default_timezone_set('Asia/Riyadh');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (Throwable $e) {
            // Log detailed error internally (do not expose sensitive info to client)
            $logDir = __DIR__ . '/_logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            $msg = date('c') . " DB_CONNECT_FAIL: " . $e->getMessage() . "\n";
            @file_put_contents($logDir . '/errors.log', $msg, FILE_APPEND);

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'DB connection failed']);
            exit;
        }
    }
    return $pdo;
}

function json_response(array $data, int $code = 200): void {
    // Backward compatible wrapper adding optional standard shape
    // If caller sets 'code' (string) we keep it; else we can derive from HTTP status classes
    if (!isset($data['ok'])) {
        // Ensure ok boolean exists
        $data['ok'] = $code >= 200 && $code < 300;
    }
    if (!$data['ok'] && !isset($data['error_code'])) {
        // Provide lightweight generic error_code if not supplied (kebab-case)
        $map = [
            400=>'bad-request',401=>'unauthorized',403=>'forbidden',404=>'not-found',405=>'method-not-allowed',409=>'conflict',422=>'validation-error',500=>'server-error'
        ];
        if (isset($map[$code])) { $data['error_code'] = $map[$code]; }
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function abort_json(int $code, string $message): void {
    json_response(['ok'=>false,'message'=>$message], $code);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true]);
}
