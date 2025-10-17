<?php
// Director (Education Office) API actions
require_once __DIR__.'/db.php';

header('Content-Type: application/json');

// api.php provides $userId, $userRole, $action, $pdo
if (!isset($userRole) || $userRole !== 'director') {
    send_json(['ok'=>false,'error'=>'forbidden','message'=>'Director role required'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// Load current user for convenience when needed
$currentUser = null;
try {
    $st = $pdo->prepare("SELECT id, email, type, first_name, last_name, avatar_url, details FROM users WHERE id=? LIMIT 1");
    $st->execute([$userId]);
    $currentUser = $st->fetch();
} catch (Throwable $e) {}

switch ($action) {
    case 'getUser':
        // Return basic director profile
        send_json(['ok'=>true,'user'=>$currentUser]);
        break;

    case 'dashboard_kpis':
        // Aggregate totals across the system
        $totals = [];
        $stmt = $pdo->query("SELECT type, COUNT(*) AS c FROM users WHERE type IN ('student','teacher') GROUP BY type");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totals['students'] = 0; $totals['teachers'] = 0;
        foreach ($rows as $r) { $totals[$r['type'].'s'] = (int)$r['c']; }

        $classes = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
        $subjects = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
        $assignments = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
        $submissions = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
        $ann = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();

        // attendance last 30 days
        $attPct = 0.0;
        $attRow = $pdo->query("SELECT AVG(CASE WHEN status='present' THEN 1 ELSE 0 END)*100 AS pct FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch(PDO::FETCH_ASSOC);
        if ($attRow && $attRow['pct'] !== null) $attPct = (float)$attRow['pct'];

        send_json(['ok'=>true,'kpis'=>[
            'students'=>$totals['students'] ?? 0,
            'teachers'=>$totals['teachers'] ?? 0,
            'classes'=>$classes,
            'subjects'=>$subjects,
            'assignments'=>$assignments,
            'submissions'=>$submissions,
            'announcements'=>$ann,
            'attendance30d'=>round($attPct,1),
        ]]);
        break;

    case 'schools':
        if ($method === 'GET') {
            // If a schools table exists, return it; otherwise derive from classes/subjects
            try {
                $pdo->query('SELECT COUNT(*) FROM schools');
                $rows = $pdo->query('SELECT id, name, code, director_name, phone, email FROM schools ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
                send_json(['ok'=>true,'schools'=>$rows]);
            } catch (Throwable $e) {
                // derive pseudo schools aggregation by subject name
                $rows = $pdo->query("SELECT s.name AS school_name, COUNT(DISTINCT c.id) AS classes, COUNT(DISTINCT u.id) AS students
                    FROM subjects s
                    LEFT JOIN classes c ON c.subject_id = s.id
                    LEFT JOIN users u ON u.class_id = c.id AND u.type='student'
                    GROUP BY s.name ORDER BY s.name")->fetchAll(PDO::FETCH_ASSOC);
                send_json(['ok'=>true,'schoolsDerived'=>$rows, 'note'=>'schools table not found, derived view']);
            }
        } elseif ($method === 'POST') {
            // Non-destructive insert; only if schools table exists
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            foreach (['name','code'] as $k) { if (empty($payload[$k])) send_json(['ok'=>false,'error'=>'validation','message'=>"Missing $k"], 422); }
            try {
                $stmt = $pdo->prepare('INSERT INTO schools (name, code, director_name, phone, email) VALUES (?,?,?,?,?)');
                $stmt->execute([
                    $payload['name'], $payload['code'], $payload['director_name'] ?? null, $payload['phone'] ?? null, $payload['email'] ?? null
                ]);
                send_json(['ok'=>true,'id'=>$pdo->lastInsertId()]);
            } catch (Throwable $e) {
                send_json(['ok'=>false,'error'=>'unavailable','message'=>'schools table not present'], 501);
            }
        } else {
            send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        }
        break;

    case 'announce':
        if ($method !== 'POST') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $title = trim($payload['title'] ?? '');
        $content = trim($payload['content'] ?? '');
        if ($title === '' || $content === '') send_json(['ok'=>false,'error'=>'validation','message'=>'title and content required'], 422);
        // Some schemas may only have content+teacher_id; for director push into announcements with content field
        try {
            // Try extended announcements table with title/content/audience/created_by if present
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, audience, created_by) VALUES (?,?, 'all', ?)");
            $stmt->execute([$title, $content, $userId]);
        } catch (Throwable $e) {
            // Fallback: minimal announcements table (teacher_id, content, date)
            $stmt = $pdo->prepare("INSERT INTO announcements (teacher_id, content, date) VALUES (?, ?, CURRENT_DATE)");
            $stmt->execute([$userId, $content]);
        }
        send_json(['ok'=>true]);
        break;

    case 'report_requests':
        if ($method === 'GET') {
            // Filters: type, from, to
            $type = $_GET['type'] ?? null; $from = $_GET['from'] ?? null; $to = $_GET['to'] ?? null;
            try {
                $sql = 'SELECT * FROM report_requests WHERE 1=1'; $args=[];
                if ($type) { $sql.=' AND type=?'; $args[]=$type; }
                if ($from) { $sql.=' AND created_at >= ?'; $args[]=$from; }
                if ($to) { $sql.=' AND created_at <= ?'; $args[]=$to; }
                $sql .= ' ORDER BY created_at DESC';
                $st=$pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                // CSV export if format=csv
                if (($_GET['format'] ?? '') === 'csv') {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="report_requests.csv"');
                    $out=fopen('php://output','w'); fputcsv($out, ['id','requested_by','type','params','created_at']);
                    foreach($rows as $r){ fputcsv($out,[$r['id'],$r['requested_by'],$r['type'],$r['params'],$r['created_at']]); }
                    fclose($out); exit;
                }
                send_json(['ok'=>true,'requests'=>$rows]);
            } catch (Throwable $e) {
                send_json(['ok'=>true,'requests'=>[], 'note'=>'report_requests table not found']);
            }
        } elseif ($method === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $type = trim($payload['type'] ?? '');
            if ($type === '') send_json(['ok'=>false,'error'=>'validation','message'=>'type required'], 422);
            $params = json_encode($payload['params'] ?? new stdClass());
            try {
                $pdo->query('SELECT COUNT(*) FROM report_requests');
                $stmt = $pdo->prepare('INSERT INTO report_requests (requested_by, type, params) VALUES (?,?,?)');
                $stmt->execute([$userId, $type, $params]);
                send_json(['ok'=>true,'id'=>$pdo->lastInsertId()]);
            } catch (Throwable $e) {
                send_json(['ok'=>false,'error'=>'unavailable','message'=>'report_requests table not present'], 501);
            }
        } else {
            send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        }
        break;

    case 'report_submissions':
        if ($method === 'GET') {
            $reqId = isset($_GET['requestId']) ? (int)$_GET['requestId'] : 0;
            if ($reqId <= 0) send_json(['ok'=>false,'error'=>'validation','message'=>'requestId required'], 422);
            try {
                $stmt = $pdo->prepare('SELECT rs.*, sc.name AS school_name FROM report_submissions rs JOIN schools sc ON sc.id=rs.school_id WHERE rs.request_id=? ORDER BY rs.submitted_at DESC');
                $stmt->execute([$reqId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (($_GET['format'] ?? '') === 'csv') {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="report_submissions_'.intval($reqId).'.csv"');
                    $out=fopen('php://output','w'); fputcsv($out, ['id','request_id','school_id','school_name','submitted_by','data','submitted_at']);
                    foreach($rows as $r){ fputcsv($out,[$r['id'],$r['request_id'],$r['school_id'],$r['school_name'],$r['submitted_by'],$r['data'],$r['submitted_at']]); }
                    fclose($out); exit;
                }
                send_json(['ok'=>true,'submissions'=>$rows]);
            } catch (Throwable $e) {
                send_json(['ok'=>true,'submissions'=>[], 'note'=>'report_submissions table not found']);
            }
        } elseif ($method === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $reqId = (int)($payload['request_id'] ?? 0);
            $schoolId = (int)($payload['school_id'] ?? 0);
            $data = json_encode($payload['data'] ?? new stdClass());
            if ($reqId <= 0 || $schoolId <= 0) send_json(['ok'=>false,'error'=>'validation','message'=>'request_id and school_id required'], 422);
            try {
                $pdo->query('SELECT COUNT(*) FROM report_submissions');
                $stmt = $pdo->prepare('INSERT INTO report_submissions (request_id, school_id, submitted_by, data) VALUES (?,?,?,?)');
                $stmt->execute([$reqId, $schoolId, $userId, $data]);
                send_json(['ok'=>true,'id'=>$pdo->lastInsertId()]);
            } catch (Throwable $e) {
                send_json(['ok'=>false,'error'=>'unavailable','message'=>'report_submissions table not present'], 501);
            }
        } else {
            send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        }
        break;

    case 'director_change_password':
        if ($method !== 'POST') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $old = $payload['old_password'] ?? '';
        $new = $payload['new_password'] ?? '';
        if(strlen($new) < 8) send_json(['ok'=>false,'error'=>'validation','message'=>'weak password'],422);
        try {
            // Change for special account '@mokha_manager' if exists, otherwise noop
            $pdo->exec("CREATE TABLE IF NOT EXISTS special_accounts (username VARCHAR(128) PRIMARY KEY, password_hash VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
            $st = $pdo->prepare('SELECT password_hash FROM special_accounts WHERE username=?');
            $st->execute(['@mokha_manager']);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if($row){
                if(!password_verify($old, $row['password_hash'])) send_json(['ok'=>false,'error'=>'forbidden','message'=>'bad password'],403);
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $up = $pdo->prepare('UPDATE special_accounts SET password_hash=? WHERE username=?');
                $up->execute([$hash, '@mokha_manager']);
            } else {
                // Initialize on first change if not present, require known default old password
                if($old !== 'Aq12345678') send_json(['ok'=>false,'error'=>'forbidden'],403);
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO special_accounts (username,password_hash) VALUES (?,?)');
                $ins->execute(['@mokha_manager', $hash]);
            }
            send_json(['ok'=>true]);
        } catch (Throwable $e) {
            send_json(['ok'=>false,'error'=>'failed'],500);
        }
        break;

    case 'device_locks':
        // Manage allowed devices for a user (default: special director account)
        $targetUser = $_GET['user'] ?? '@mokha_manager';
        try { $pdo->query('SELECT 1 FROM device_locks LIMIT 1'); } catch (Throwable $e) { send_json(['ok'=>true,'items'=>[],'note'=>'device_locks table not found']); }
        if ($method === 'GET') {
            $st = $pdo->prepare('SELECT id, device_hash, label, created_at FROM device_locks WHERE user_id=? ORDER BY created_at DESC');
            $st->execute([$targetUser]);
            send_json(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($method === 'POST') {
            $b = json_decode(file_get_contents('php://input'), true) ?: [];
            $hash = trim($b['device_hash'] ?? ''); $label = trim($b['label'] ?? '');
            if ($hash==='') send_json(['ok'=>false,'error'=>'validation','message'=>'device_hash required'],422);
            $ins = $pdo->prepare('INSERT INTO device_locks (user_id, device_hash, label) VALUES (?,?,?)');
            $ins->execute([$targetUser, $hash, $label ?: null]);
            send_json(['ok'=>true,'id'=>$pdo->lastInsertId()]);
        } elseif ($method === 'DELETE') {
            $hash = $_GET['device_hash'] ?? '';
            if ($hash==='') send_json(['ok'=>false,'error'=>'validation','message'=>'device_hash required'],422);
            $del = $pdo->prepare('DELETE FROM device_locks WHERE user_id=? AND device_hash=?');
            $del->execute([$targetUser, $hash]);
            send_json(['ok'=>true]);
        } else send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        break;

    default:
        send_json(['ok'=>false,'error'=>'unknown_action'], 400);
}

?>
