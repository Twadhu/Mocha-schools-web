<?php
require __DIR__.'/db.php';

// Ensure composer autoload exists for firebase/php-jwt
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Missing vendor autoload. Run composer install in /api']);
    exit();
}
require $vendorAutoload;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

// LOGIN
if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';

    if (!$email || !$password || !in_array($role, ['student','teacher','director','manager'], true)) {
        send_json(['ok'=>false,'message'=>'البيانات غير مكتملة.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=? AND type=? LIMIT 1');
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    // For the provided SQL dump, all users share the same bcrypt hash placeholder; check using password_verify
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $payload = [
            'iss' => 'http://localhost',
            'aud' => 'http://localhost',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 86400, // 1 day
            'data' => [ 'userId' => $user['id'], 'role' => $role ]
        ];
        $jwt = JWT::encode($payload, $JWT_SECRET_KEY, 'HS256');
        send_json(['ok'=>true,'token'=>$jwt,'user'=>$user]);
    }
    send_json(['ok'=>false,'message'=>'البريد الإلكتروني أو كلمة المرور غير صحيحة.'], 401);
}

// PROTECTED ENDPOINTS
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
if (!$authHeader) { send_json(['ok'=>false,'message'=>'Authorization header not found.'], 401); }
if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $m)) { send_json(['ok'=>false,'message'=>'Token not found.'], 401); }
$jwt = $m[1];

try {
    $decoded = JWT::decode($jwt, new Key($JWT_SECRET_KEY, 'HS256'));
} catch (Throwable $e) {
    send_json(['ok'=>false,'message'=>'Invalid token'], 401);
}

$userId = $decoded->data->userId ?? null;
$userRole = $decoded->data->role ?? null;
if (!$userId || !$userRole) { send_json(['ok'=>false,'message'=>'Invalid token payload'], 401); }

// Dispatch by role
if ($userRole === 'student') {
    require __DIR__.'/student_api.php';
    exit;
} elseif ($userRole === 'teacher') {
    require __DIR__.'/teacher_api.php';
    exit;
} elseif ($userRole === 'director') {
    require __DIR__.'/director_api.php';
    exit;
} elseif ($userRole === 'manager') {
    require __DIR__.'/manager_api.php';
    exit;
}

send_json(['ok'=>false,'message'=>'Invalid user role'], 403);
