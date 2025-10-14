<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';

$pdo = db();
$user = auth_require_token($pdo, []); // any logged-in user

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT name FROM subjects WHERE school_id=? ORDER BY name');
    $stmt->execute([$user['school_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    json_response(['ok'=>true,'subjects'=>array_map(function($n){return ['name'=>$n];}, $rows)]);
}

json_response(['ok'=>false,'message'=>'Method not allowed'],405);
