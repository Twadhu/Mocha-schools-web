<?php
require_once __DIR__.'/config.php';

// One-time bootstrap endpoint to create an initial manager account safely.
// Security:
// - Requires a shared secret via header X-Bootstrap-Secret.
// - Only allows creation if there is no manager yet in the database for any school.
// - Creates the manager under school_id=1 by default unless provided.

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') { json_response(['ok'=>false,'message'=>'Method not allowed'],405); }

$secret = $_SERVER['HTTP_X_BOOTSTRAP_SECRET'] ?? '';
// Change this value after first use and/or disable the file.
$EXPECTED = getenv('BOOTSTRAP_SECRET') ?: 'change-this-once';
if (!$secret || !hash_equals($EXPECTED, $secret)) {
    json_response(['ok'=>false,'message'=>'Forbidden'],403);
}

$pdo = db();
try {
    // If any manager exists, abort to avoid duplicate bootstrap
    $chk = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role='manager'");
    $has = (int)($chk->fetch()['c'] ?? 0);
    if ($has > 0) { json_response(['ok'=>false,'message'=>'Manager already exists'],409); }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim($input['email'] ?? 'admin@school.local'));
    $password = $input['password'] ?? bin2hex(random_bytes(4));
    $first = trim($input['first_name'] ?? 'School');
    $last = trim($input['last_name'] ?? 'Admin');
    $school_id = (int)($input['school_id'] ?? 1);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (school_id, role, first_name, last_name, email, password_hash, status) VALUES (?,?,?,?,?,?,?)');
    $ins->execute([$school_id, 'manager', $first, $last, $email, $hash, 'active']);
    $id = (int)$pdo->lastInsertId();
    json_response(['ok'=>true,'id'=>$id,'email'=>$email,'password'=>$password]);
} catch (Throwable $e) {
    json_response(['ok'=>false,'message'=>'DB error'],500);
}
