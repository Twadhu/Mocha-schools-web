<?php
require_once __DIR__.'/config.php';
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') { json_response(['ok'=>false,'message'=>'Method not allowed'],405); }
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';
$role = $input['role'] ?? '';
// Allow manager role too
if (!$email || !$password || !in_array($role,['student','teacher','manager'],true)) {
    json_response(['ok'=>false,'message'=>'Invalid credentials','error_code'=>'invalid-credentials'],422);
}
// Basic rate limiting (email + IP) over sliding window (15 minutes)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$windowMinutes = 15; $maxAttempts = 10; $lockMinutes = 15; $lockThreshold = 5; // if last 5 attempts failed -> lock
try {
    // Count total attempts in window
    $limStmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) AS fails,
        SUM(CASE WHEN success=1 THEN 1 ELSE 0 END) AS successes,
        MAX(CASE WHEN success=0 THEN created_at ELSE NULL END) AS last_fail
      FROM login_attempts WHERE email=? AND created_at > (NOW() - INTERVAL {$windowMinutes} MINUTE)");
    $limStmt->execute([$email]);
    $lim = $limStmt->fetch() ?: ['fails'=>0,'successes'=>0,'last_fail'=>null];
    if ((int)$lim['fails'] >= $maxAttempts) {
        json_response(['ok'=>false,'message'=>'Too many attempts, try later','error_code'=>'rate-limited'],429);
    }
    // Check recent consecutive failures for lock
    $seqStmt = $pdo->prepare("SELECT success FROM login_attempts WHERE email=? ORDER BY id DESC LIMIT ?");
    $seqStmt->execute([$email,$lockThreshold]);
    $rows = $seqStmt->fetchAll();
    if ($rows && count($rows)===$lockThreshold && !array_filter($rows, fn($r)=>$r['success'])) {
        // All last N are failures -> enforce temporary lock (based on last fail time + lockMinutes)
        $lastFailTime = $lim['last_fail'];
        if ($lastFailTime) {
            $lockStmt = $pdo->prepare("SELECT TIMESTAMPDIFF(MINUTE, ?, NOW()) AS diff");
            $lockStmt->execute([$lastFailTime]);
            $diff = (int)($lockStmt->fetch()['diff'] ?? 0);
            if ($diff < $lockMinutes) {
                json_response(['ok'=>false,'message'=>'Account temporarily locked','error_code'=>'temporary-lock','meta'=>['minutes_remaining'=>$lockMinutes-$diff]],423);
            }
        }
    }
} catch (Throwable $e) {
    // Fail open: do not block login if logging fails
}
try {
    $stmt = $pdo->prepare('SELECT id, school_id, role, first_name, last_name, email, password_hash, status FROM users WHERE email=? AND role=? LIMIT 1');
    $stmt->execute([$email,$role]);
    $user = $stmt->fetch();
    if (!$user) {
        $insAtt = $pdo->prepare('INSERT INTO login_attempts (email, ip, success, reason) VALUES (?,?,0,?)');
        $insAtt->execute([$email,$ip,'user-not-found']);
        json_response(['ok'=>false,'message'=>'User not found','error_code'=>'user-not-found'],401);
    }
    if ($user['status']!=='active') {
        $insAtt = $pdo->prepare('INSERT INTO login_attempts (email, ip, success, reason) VALUES (?,?,0,?)');
        $insAtt->execute([$email,$ip,'account-disabled']);
        json_response(['ok'=>false,'message'=>'Account disabled','error_code'=>'account-disabled'],403);
    }
    if (!password_verify($password, $user['password_hash'])) {
        $insAtt = $pdo->prepare('INSERT INTO login_attempts (email, ip, success, reason) VALUES (?,?,0,?)');
        $insAtt->execute([$email,$ip,'wrong-password']);
        json_response(['ok'=>false,'message'=>'Wrong password','error_code'=>'wrong-password'],401);
    }
    // توليد توكن بسيط
    $token = bin2hex(random_bytes(32));
    $exp = (new DateTime('+2 days'))->format('Y-m-d H:i:s');
    $ins = $pdo->prepare('INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?,?,?)');
    $ins->execute([$user['id'],$token,$exp]);
    // log success
    try {
        $pdo->prepare('INSERT INTO login_attempts (email, ip, success, reason) VALUES (?,?,1,?)')->execute([$email,$ip,'ok']);
    } catch (Throwable $e) {}
    unset($user['password_hash']);
    json_response(['ok'=>true,'token'=>$token,'user'=>$user,'meta'=>['expires_at'=>$exp]]);
} catch (Throwable $e) {
    json_response(['ok'=>false,'message'=>'DB error','error_code'=>'db-error'],500);
}
