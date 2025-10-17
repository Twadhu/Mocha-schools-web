<?php
// Manager (School Admin) API actions
require_once __DIR__.'/db.php';

header('Content-Type: application/json');

// Variables from api.php: $pdo, $userId, $userRole, $action
if (!isset($userRole) || $userRole !== 'manager') {
    send_json(['ok'=>false,'error'=>'forbidden','message'=>'Manager role required'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;

switch ($action) {
    case 'getUser': {
        $stmt = $pdo->prepare("SELECT id, email, type, first_name, last_name, avatar_url, details FROM users WHERE id=? AND type='manager' LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        send_json(['ok'=>true,'data'=>$user]);
    }
    case 'report_requests': {
        // View directorate report requests for awareness
        try {
            $rows = $pdo->query('SELECT * FROM report_requests ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
            send_json(['ok'=>true,'requests'=>$rows]);
        } catch (Throwable $e) {
            send_json(['ok'=>true,'requests'=>[], 'note'=>'report_requests table not found']);
        }
    }
    case 'report_submit': {
        if ($method !== 'POST') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $reqId = (int)($payload['request_id'] ?? 0);
        $schoolId = (int)($payload['school_id'] ?? 0);
        $data = json_encode($payload['data'] ?? new stdClass());
        if ($reqId<=0 || $schoolId<=0) send_json(['ok'=>false,'error'=>'validation','message'=>'request_id and school_id required'], 422);
        try {
            $pdo->query('SELECT COUNT(*) FROM report_submissions');
            $stmt = $pdo->prepare('INSERT INTO report_submissions (request_id, school_id, submitted_by, data) VALUES (?,?,?,?)');
            $stmt->execute([$reqId, $schoolId, $userId, $data]);
            send_json(['ok'=>true,'id'=>$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            send_json(['ok'=>false,'error'=>'unavailable','message'=>'report_submissions table not present'], 501);
        }
    }
    // TODO: migrate legacy manager features here as actions (students, subjects, schedule, grades, attendance ...)
    default:
        send_json(['ok'=>false,'message'=>'Manager action not found'], 404);
}

?>