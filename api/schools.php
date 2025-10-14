<?php
require_once __DIR__ . '/config.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query('SELECT id,name FROM schools ORDER BY id ASC');
        json_response(['ok'=>true,'schools'=>$stmt->fetchAll()]);
    } catch (Throwable $e) {
        json_response(['ok'=>false,'message'=>'DB error'],500);
    }
}
json_response(['ok'=>false,'message'=>'Method not allowed'],405);
