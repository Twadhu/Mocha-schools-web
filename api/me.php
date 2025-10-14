<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';
$pdo = db();
if ($_SERVER['REQUEST_METHOD']!=='GET') { json_response(['ok'=>false,'message'=>'Method not allowed'],405); }
$user = auth_require_token($pdo, []);
json_response(['ok'=>true,'user'=>$user]);
