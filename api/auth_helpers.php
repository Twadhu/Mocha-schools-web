<?php
require_once __DIR__.'/config.php';

function auth_require_token(PDO $pdo, array $roles = []): array {
    // Local development bypass (explicitly opt-in):
    // If DEV_ALLOW_SCHOOL_PANEL=1 and request comes from localhost and carries X-Dev-Bypass:1,
    // we synthesize a temporary user context for the active school. This should NEVER be enabled in production.
    $devBypass = getenv('DEV_ALLOW_SCHOOL_PANEL');
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($remote, ['127.0.0.1','::1']) || preg_match('/^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[0-1])\./', $remote);
    if ($devBypass === '1' && $isLocal && (($_SERVER['HTTP_X_DEV_BYPASS'] ?? '') === '1')) {
        // Determine school from header or default to 1
        $sid = $_SERVER['HTTP_X_SCHOOL_ID'] ?? '1';
        $sid = is_numeric($sid) ? intval($sid) : $sid; // allow s1 style for front; map below
        if (!is_int($sid)) {
            // Map s1/s2/s3 -> 1/2/3 if provided
            if (preg_match('/^s(\d+)$/i', (string)$sid, $m)) { $sid = intval($m[1]); } else { $sid = 1; }
        }
        // create or find a dev manager for this school
        $stmt = $pdo->prepare("SELECT id, role, email, school_id, first_name, last_name FROM users WHERE school_id=? AND role='manager' LIMIT 1");
        $stmt->execute([$sid]);
        $mgr = $stmt->fetch();
        if (!$mgr) {
            // Insert minimal manager user for dev
            $hash = password_hash('dev-pass', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (school_id, role, first_name, last_name, email, password_hash, status) VALUES (?,?,?,?,?,?, 'active')")
                ->execute([$sid, 'manager', 'Dev', 'Manager', 'dev.manager@local', $hash]);
            $id = intval($pdo->lastInsertId());
            $mgr = ['id'=>$id,'role'=>'manager','email'=>'dev.manager@local','school_id'=>$sid,'first_name'=>'Dev','last_name'=>'Manager'];
        }
        if ($roles && !in_array('manager', $roles, true)) {
            json_response(['ok'=>false,'message'=>'Forbidden (dev bypass role)'],403);
        }
        $mgr['user_id'] = $mgr['id'];
        $mgr['token'] = 'DEV_BYPASS';
        return $mgr;
    }

    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $hdr, $m)) {
        json_response(['ok'=>false,'message'=>'Missing or invalid token'],401);
    }
    $token = $m[1];
    $stmt = $pdo->prepare('SELECT t.user_id, u.role, u.email, u.school_id, u.first_name, u.last_name FROM user_tokens t JOIN users u ON u.id=t.user_id WHERE t.token=? AND (t.expires_at IS NULL OR t.expires_at>NOW()) LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) { json_response(['ok'=>false,'message'=>'Token expired'],401); }
    if ($roles && !in_array($row['role'],$roles,true)) { json_response(['ok'=>false,'message'=>'Forbidden'],403); }
    $row['token'] = $token; // keep token
    return $row; // return user info
}
