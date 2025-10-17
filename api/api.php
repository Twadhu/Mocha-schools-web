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
    $deviceFp = $data['device_fingerprint'] ?? null;

    if (!$email || !$password || !in_array($role, ['student','teacher','director','manager'], true)) {
        send_json(['ok'=>false,'message'=>'البيانات غير مكتملة.'], 400);
    }

    // Special director override using special_accounts
    if ($role === 'director' && $email === '@mokha_manager') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS special_accounts (username VARCHAR(128) PRIMARY KEY, password_hash VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
            $sa = $pdo->prepare('SELECT password_hash FROM special_accounts WHERE username=?');
            $sa->execute(['@mokha_manager']);
            $saRow = $sa->fetch(PDO::FETCH_ASSOC);
            if (!$saRow) {
                // Default password support on first time (from docs): Aq12345678
                if ($password !== 'Aq12345678') send_json(['ok'=>false,'message'=>'غير مصرح'], 401);
                // Initialize record with default changed to hashed default to require change later via API
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO special_accounts (username,password_hash) VALUES (?,?)');
                $ins->execute(['@mokha_manager', $hash]);
            } else {
                if (!password_verify($password, $saRow['password_hash'])) {
                    send_json(['ok'=>false,'message'=>'كلمة المرور غير صحيحة.'], 401);
                }
            }
            // Optional device lock enforcement
            try {
                $pdo->query('SELECT 1 FROM device_locks LIMIT 1');
                $locks = $pdo->prepare('SELECT device_hash FROM device_locks WHERE user_id=?');
                $locks->execute(['@mokha_manager']);
                $allowList = $locks->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if ($allowList && $deviceFp) {
                    if (!in_array($deviceFp, $allowList, true)) {
                        send_json(['ok'=>false,'message'=>'هذا الجهاز غير مسموح به لهذا الحساب.'], 403);
                    }
                } elseif ($allowList && !$deviceFp) {
                    send_json(['ok'=>false,'message'=>'يرجى التحديث والمحاولة من نفس الجهاز'], 400);
                }
            } catch (Throwable $e) { /* table not present, skip */ }

            // Ensure a backing director user exists in users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email='@mokha_manager' AND type='director' LIMIT 1");
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $uid = 'DIR'.time();
                $rndHash = password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);
                $insU = $pdo->prepare("INSERT INTO users (id,email,password,type,first_name,last_name) VALUES (?,?,?,?,?,?)");
                $insU->execute([$uid,'@mokha_manager',$rndHash,'director','Director','Account']);
                $user = ['id'=>$uid,'email'=>'@mokha_manager','type'=>'director','first_name'=>'Director','last_name'=>'Account'];
            }
            unset($user['password']);
            $payload = [
                'iss' => 'http://localhost',
                'aud' => 'http://localhost',
                'iat' => time(),
                'nbf' => time(),
                'exp' => time() + 86400,
                'data' => [ 'userId' => $user['id'], 'role' => 'director' ]
            ];
            $jwt = JWT::encode($payload, $JWT_SECRET_KEY, 'HS256');
            send_json(['ok'=>true,'token'=>$jwt,'user'=>$user]);
        } catch (Throwable $e) {
            send_json(['ok'=>false,'message'=>'تعذر تسجيل الدخول'], 500);
        }
    }

    // Default login flow: user from users table
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=? AND type=? LIMIT 1');
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

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
