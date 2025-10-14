<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
try {
    $rows = $pdo->query('SELECT id,name FROM schools ORDER BY id ASC')->fetchAll();
    json_response(['ok'=>true,'schools'=>$rows]);
} catch (Throwable $e) {
    json_response(['ok'=>false,'message'=>'DB error'],500);
}
