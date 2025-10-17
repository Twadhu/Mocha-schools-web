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
    // Subjects CRUD (manager scope on unified simple schema)
    case 'subjects': {
        if ($method === 'GET') {
            $rows = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
            send_json(['ok'=>true,'subjects'=>$rows]);
        } elseif ($method === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $name = trim($payload['name'] ?? '');
            if ($name==='') send_json(['ok'=>false,'error'=>'validation','message'=>'name required'], 422);
            // Upsert by name
            try {
                $ins = $pdo->prepare('INSERT INTO subjects (id, name) VALUES (?, ?)');
                // generate id if not provided
                $sid = $payload['id'] ?? ('SUBJ'.time());
                $ins->execute([$sid, $name]);
                send_json(['ok'=>true,'id'=>$sid]);
            } catch (Throwable $e) {
                // fallback: find existing
                $s=$pdo->prepare('SELECT id FROM subjects WHERE name=? LIMIT 1');
                $s->execute([$name]);
                $sid = $s->fetchColumn();
                if ($sid) send_json(['ok'=>true,'id'=>$sid]);
                send_json(['ok'=>false,'error'=>'conflict','message'=>'subject exists or failed'], 409);
            }
        } else send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
    }
    case 'subjects_update': {
        if ($method !== 'PUT') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $id = $_GET['id'] ?? null; if(!$id) send_json(['ok'=>false,'error'=>'validation','message'=>'id required'], 422);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = trim($payload['name'] ?? ''); if($name==='') send_json(['ok'=>false,'error'=>'validation','message'=>'name required'], 422);
        $st = $pdo->prepare('UPDATE subjects SET name=? WHERE id=?'); $st->execute([$name, $id]);
        send_json(['ok'=>true]);
    }
    case 'subjects_delete': {
        if ($method !== 'DELETE') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $id = $_GET['id'] ?? null; if(!$id) send_json(['ok'=>false,'error'=>'validation','message'=>'id required'], 422);
        $st = $pdo->prepare('DELETE FROM subjects WHERE id=?'); $st->execute([$id]); send_json(['ok'=>true]);
    }
    // Students list/create/update/delete using users table (type='student'). Stores extra fields in details JSON.
    case 'students': {
        if ($method === 'GET') {
            $grade = $_GET['grade'] ?? '';
            $search = trim($_GET['search'] ?? '');
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;
            $pageSize = isset($_GET['pageSize']) ? max(1, min(200, (int)$_GET['pageSize'])) : null;
            $base = "FROM users WHERE type='student'"; $args=[];
            if ($grade!=='') { $base .= " AND JSON_EXTRACT(COALESCE(details,'{}'),'$.grade_level') = ?"; $args[] = $grade; }
            if ($search!==''){ $like='%'.$search.'%'; $base.=' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $args[]=$like; $args[]=$like; $args[]=$like; }
            $total = null; if($page && $pageSize){ $cnt=$pdo->prepare('SELECT COUNT(*) '+$base); $cnt->execute($args); $total=(int)$cnt->fetchColumn(); }
            $sql = 'SELECT id, first_name, last_name, email, class_id, details '+$base+' ORDER BY first_name,last_name';
            if ($page && $pageSize){ $offset = ($page-1)*$pageSize; $sql .= ' LIMIT '.$pageSize.' OFFSET '.$offset; }
            $st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
            // derive flat fields
            foreach($rows as &$r){
                $det = json_decode($r['details'] ?? 'null', true) ?: [];
                $r['grade_level'] = $det['grade_level'] ?? null;
                $r['dob'] = $det['dob'] ?? null;
                unset($r['details']);
            }
            send_json(['ok'=>true,'students'=>$rows,'total'=>$total ?? count($rows)]);
        } elseif ($method==='POST') {
            $b = json_decode(file_get_contents('php://input'), true) ?: [];
            foreach (['first_name','last_name','email'] as $k) { if (empty($b[$k])) send_json(['ok'=>false,'error'=>'validation','message'=>'Missing '.$k], 422); }
            $det = [ 'grade_level'=>$b['grade_level'] ?? null, 'dob'=>$b['dob'] ?? null ];
            $hash = password_hash(bin2hex(random_bytes(3)), PASSWORD_DEFAULT);
            $stmt=$pdo->prepare('INSERT INTO users (id,email,password,type,first_name,last_name,avatar_url,class_id,details) VALUES (?,?,?,?,?,?,?,?,?)');
            $id = 'STU'.time();
            $stmt->execute([$id, strtolower($b['email']), $hash, 'student', $b['first_name'],$b['last_name'], null, null, json_encode($det, JSON_UNESCAPED_UNICODE)]);
            send_json(['ok'=>true,'id'=>$id]);
        } else send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
    }
    case 'students_update': {
        if ($method!=='PUT') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $id = $_GET['id'] ?? null; if(!$id) send_json(['ok'=>false,'error'=>'validation','message'=>'id required'], 422);
        $b = json_decode(file_get_contents('php://input'), true) ?: [];
        $sel=$pdo->prepare("SELECT details FROM users WHERE id=? AND type='student' LIMIT 1"); $sel->execute([$id]); $cur=$sel->fetch(PDO::FETCH_ASSOC);
        if(!$cur) send_json(['ok'=>false,'error'=>'not_found'], 404);
        $det = json_decode($cur['details'] ?? 'null', true) ?: [];
        if (array_key_exists('grade_level',$b)) $det['grade_level'] = $b['grade_level'];
        if (array_key_exists('dob',$b)) $det['dob'] = $b['dob'];
        $st=$pdo->prepare("UPDATE users SET first_name=?, last_name=?, details=? WHERE id=? AND type='student'");
        $st->execute([$b['first_name'] ?? null, $b['last_name'] ?? null, json_encode($det, JSON_UNESCAPED_UNICODE), $id]);
        send_json(['ok'=>true]);
    }
    case 'students_delete': {
        if ($method!=='DELETE') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $id = $_GET['id'] ?? null; if(!$id) send_json(['ok'=>false,'error'=>'validation','message'=>'id required'], 422);
        $st=$pdo->prepare("DELETE FROM users WHERE id=? AND type='student'"); $st->execute([$id]); send_json(['ok'=>true]);
    }
    case 'student_access_code': {
        if ($method!=='GET') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $sid = $_GET['studentId'] ?? null; if(!$sid) send_json(['ok'=>false,'error'=>'validation','message'=>'studentId required'], 422);
        // simple unsigned payload for demo purposes
        $code = strtoupper(substr(bin2hex(random_bytes(4)),0,8));
        $exp = time()+3600;
        $payload = json_encode(['v'=>1,'sid'=>$sid,'code'=>$code,'exp'=>$exp], JSON_UNESCAPED_UNICODE);
        send_json(['ok'=>true,'code'=>$code,'exp'=>$exp,'payload'=>$payload]);
    }
    // Teachers basic CRUD (store specialization, subjects, classes in details JSON)
    case 'teachers': {
        if ($method==='GET') {
            $rows=$pdo->query("SELECT id, first_name, last_name, email, details FROM users WHERE type='teacher' ORDER BY first_name,last_name")->fetchAll(PDO::FETCH_ASSOC);
            $list=[]; foreach($rows as $r){ $det=json_decode($r['details']??'null',true)?:[]; $list[]=[ 'id'=>$r['id'], 'first_name'=>$r['first_name'], 'last_name'=>$r['last_name'], 'email'=>$r['email'], 'specialization'=>$det['specialization']??null, 'status'=>'active', 'subjects'=>array_map(function($n){return ['name'=>$n];}, ($det['subjects']??[])), 'classes'=>$det['classes']??[] ]; }
            send_json(['ok'=>true,'teachers'=>$list]);
        } elseif ($method==='POST') {
            $b=json_decode(file_get_contents('php://input'), true) ?: [];
            foreach(['first_name','last_name','email'] as $k){ if(empty($b[$k])) send_json(['ok'=>false,'error'=>'validation','message'=>'Missing '.$k],422); }
            $det=[ 'specialization'=>$b['specialization']??null, 'subjects'=>[], 'classes'=>[] ];
            // subjects may be array of ids; convert to names
            $subs = is_array($b['subjects']??null)? $b['subjects']: [];
            if ($subs){
                $in = implode(',', array_fill(0, count($subs), '?'));
                $s=$pdo->prepare("SELECT id,name FROM subjects WHERE id IN ($in)"); $s->execute($subs); $map=[]; while($row=$s->fetch(PDO::FETCH_ASSOC)){ $map[$row['id']]=$row['name']; }
                foreach($subs as $sid){ if(isset($map[$sid])) $det['subjects'][]=$map[$sid]; }
            }
            $det['classes'] = is_array($b['classes']??null)? $b['classes'] : [];
            $hash=password_hash(bin2hex(random_bytes(3)), PASSWORD_DEFAULT); $id='TCH'.time();
            $st=$pdo->prepare('INSERT INTO users (id,email,password,type,first_name,last_name,details) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$id, strtolower($b['email']), $hash, 'teacher', $b['first_name'],$b['last_name'], json_encode($det, JSON_UNESCAPED_UNICODE)]);
            send_json(['ok'=>true,'id'=>$id]);
        } else send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
    }
    case 'teacher_update': {
        if ($method!=='PUT') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $tid=$_GET['id']??null; if(!$tid) send_json(['ok'=>false,'error'=>'validation','message'=>'id required'], 422);
        $b=json_decode(file_get_contents('php://input'),true)?:[];
        $sel=$pdo->prepare("SELECT details FROM users WHERE id=? AND type='teacher' LIMIT 1"); $sel->execute([$tid]); $cur=$sel->fetch(PDO::FETCH_ASSOC); if(!$cur) send_json(['ok'=>false,'error'=>'not_found'],404);
        $det=json_decode($cur['details']??'null',true)?:[];
        if(isset($b['specialization'])) $det['specialization']=$b['specialization'];
        if(isset($b['classes']) && is_array($b['classes'])) $det['classes']=$b['classes'];
        if(isset($b['subjects'])){
            $det['subjects']=[]; $subs=is_array($b['subjects'])?$b['subjects']:[]; if($subs){ $in=implode(',',array_fill(0,count($subs),'?')); $s=$pdo->prepare("SELECT id,name FROM subjects WHERE id IN ($in)"); $s->execute($subs); while($row=$s->fetch(PDO::FETCH_ASSOC)){ $det['subjects'][]=$row['name']; } }
        }
        $st=$pdo->prepare('UPDATE users SET first_name=COALESCE(?,first_name), last_name=COALESCE(?,last_name), email=COALESCE(?,email), details=? WHERE id=? AND type="teacher"');
        $st->execute([$b['first_name']??null,$b['last_name']??null, isset($b['email'])?strtolower($b['email']):null, json_encode($det, JSON_UNESCAPED_UNICODE), $tid]);
        send_json(['ok'=>true]);
    }
    case 'teacher_delete': {
        if ($method!=='DELETE') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $tid=$_GET['id']??null; if(!$tid) send_json(['ok'=>false,'error'=>'validation','message'=>'id required'], 422);
        $st=$pdo->prepare("DELETE FROM users WHERE id=? AND type='teacher'"); $st->execute([$tid]); send_json(['ok'=>true]);
    }
    // Schedule: simple mapping to schedule table; ignores grade/section/semester, operates on a default class C1 unless classId provided
    case 'schedule': {
        if ($method==='GET'){
            $classId = $_GET['classId'] ?? 'C1';
            $stmt=$pdo->prepare("SELECT s.day, s.period, sub.name AS subject, c.name AS class_name, c.teacher_id, u.first_name, u.last_name
                                 FROM schedule s JOIN classes c ON c.id=s.class_id JOIN subjects sub ON sub.id=s.subject_id
                                 LEFT JOIN users u ON u.id=c.teacher_id WHERE s.class_id=? ORDER BY s.day, s.period");
            $stmt->execute([$classId]); $items=[]; while($r=$stmt->fetch(PDO::FETCH_ASSOC)){ $items[]=[ 'day'=>$r['day'], 'period'=>(int)$r['period'], 'subject'=>$r['subject'], 'teacher_id'=>$r['teacher_id']??null, 'teacher_name'=> trim(($r['first_name']??'').' '.($r['last_name']??'')) ]; }
            send_json(['ok'=>true,'items'=>$items]);
        } elseif ($method==='PUT'){
            $b=json_decode(file_get_contents('php://input'),true)?:[]; $classId=$b['classId'] ?? 'C1'; $entries=is_array($b['entries']??null)?$b['entries']:[];
            // Build subject name -> id map
            $subs=$pdo->query('SELECT id,name FROM subjects')->fetchAll(PDO::FETCH_KEY_PAIR);
            // Replace schedule for classId
            $pdo->beginTransaction();
            try{
                $pdo->prepare('DELETE FROM schedule WHERE class_id=?')->execute([$classId]);
                if ($entries){ $ins=$pdo->prepare('INSERT INTO schedule (class_id, day, period, subject_id) VALUES (?,?,?,?)'); foreach($entries as $e){ $sid=null; $nm=trim($e['subject']??''); if($nm!==''){ foreach($subs as $id=>$n){ if($n===$nm){ $sid=$id; break; } } if(!$sid){ // create subject on the fly
                                $newId = 'SUBJ'.(time()).rand(1,9); $pdo->prepare('INSERT INTO subjects (id,name) VALUES (?,?)')->execute([$newId,$nm]); $subs[$newId]=$nm; $sid=$newId; }
                            $ins->execute([$classId, $e['day'], (int)$e['period'], $sid]); } } }
                $pdo->commit(); send_json(['ok'=>true]);
            }catch(Throwable $e){ $pdo->rollBack(); send_json(['ok'=>false,'error'=>'failed','message'=>'save failed'],500); }
        } else send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
    }
    case 'teacher_schedule': {
        if ($method!=='GET') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $tid = $_GET['teacherId'] ?? null; if(!$tid) send_json(['ok'=>false,'error'=>'validation','message'=>'teacherId required'],422);
        $stmt=$pdo->prepare("SELECT s.day, s.period, sub.name AS subject FROM schedule s JOIN classes c ON c.id=s.class_id JOIN subjects sub ON sub.id=s.subject_id WHERE c.teacher_id=? ORDER BY s.day,s.period");
        $stmt->execute([$tid]); $items=$stmt->fetchAll(PDO::FETCH_ASSOC); send_json(['ok'=>true,'items'=>$items]);
    }
    // Gradebook: minimal implementation using optional tables term_results and result_locks
    case 'score_limits': {
        send_json(['ok'=>true,'max_first'=>20,'max_final'=>30,'pass_threshold'=>50]);
    }
    case 'subject_results': {
        if ($method!=='GET') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $grade = $_GET['grade'] ?? null; $section = $_GET['section'] ?? '';
        $subject = $_GET['subject'] ?? null; $term = $_GET['term'] ?? 'first'; $year = $_GET['year'] ?? date('Y');
        if(!$subject || !$grade) send_json(['ok'=>true,'items'=>[]]);
        // list students from users (all students) for demo
        $rows = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE type='student' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
        // load existing if table exists
        $map = [];
        try{
            $st=$pdo->prepare('SELECT student_id, first_score, final_score FROM term_results WHERE subject=? AND term=? AND year_label=?');
            $st->execute([$subject,$term,$year]); while($r=$st->fetch(PDO::FETCH_ASSOC)){ $map[$r['student_id']] = $r; }
        }catch(Throwable $e){ /* table may not exist */ }
        $items=[]; foreach($rows as $r){ $ex=$map[$r['id']]??null; $items[]=['id'=>$r['id'],'name'=>$r['name'],'first'=>$ex['first_score']??0,'final'=>$ex['final_score']??0]; }
        send_json(['ok'=>true,'items'=>$items]);
    }
    case 'results': {
        if ($method!=='POST') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $b=json_decode(file_get_contents('php://input'),true)?:[]; $sid=$b['student_id']??null; $subject=$b['subject']??null; $term=$b['term']??'first'; $year=$b['year']??date('Y'); $first=(int)($b['first']??0); $final=(int)($b['final']??0);
        if(!$sid || !$subject) send_json(['ok'=>false,'error'=>'validation'],422);
        try{
            $pdo->query('SELECT 1 FROM term_results LIMIT 1');
            $up=$pdo->prepare("INSERT INTO term_results (student_id, subject, term, year_label, first_score, final_score) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE first_score=VALUES(first_score), final_score=VALUES(final_score)");
            $up->execute([$sid,$subject,$term,$year,$first,$final]);
            send_json(['ok'=>true]);
        }catch(Throwable $e){ send_json(['ok'=>true]); }
    }
    case 'results_lock_state': {
        $grade=$_GET['grade']??''; $section=$_GET['section']??''; $subject=$_GET['subject']??''; $term=$_GET['term']??'first'; $year=$_GET['year']??date('Y');
        $locked=false; $approved=false; try{ $st=$pdo->prepare('SELECT locked, approved FROM result_locks WHERE grade=? AND section=? AND subject=? AND term=? AND year_label=? LIMIT 1'); $st->execute([$grade,$section,$subject,$term,$year]); if($r=$st->fetch(PDO::FETCH_ASSOC)){ $locked = (int)$r['locked']===1; $approved=(int)$r['approved']===1; } }catch(Throwable $e){}
        send_json(['ok'=>true,'locked'=>$locked,'approved'=>$approved]);
    }
    case 'results_lock': {
        if ($method!=='POST') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $b=json_decode(file_get_contents('php://input'),true)?:[]; $grade=$b['grade']??''; $section=$b['section']??''; $subject=$b['subject']??''; $term=$b['term']??'first'; $year=$b['year']??date('Y'); $lock = !empty($b['lock']);
        try{ $pdo->prepare('INSERT INTO result_locks (grade,section,subject,term,year_label,locked,approved) VALUES (?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE locked=VALUES(locked)')->execute([$grade,$section,$subject,$term,$year, $lock?1:0]); }catch(Throwable $e){}
        send_json(['ok'=>true]);
    }
    case 'results_approve': {
        if ($method!=='POST') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $b=json_decode(file_get_contents('php://input'),true)?:[]; $grade=$b['grade']??''; $section=$b['section']??''; $subject=$b['subject']??''; $term=$b['term']??'first'; $year=$b['year']??date('Y'); $approve = !empty($b['approve']);
        try{ $pdo->prepare('INSERT INTO result_locks (grade,section,subject,term,year_label,locked,approved) VALUES (?,?,?,?,?,0,?) ON DUPLICATE KEY UPDATE approved=VALUES(approved)')->execute([$grade,$section,$subject,$term,$year, $approve?1:0]); }catch(Throwable $e){}
        send_json(['ok'=>true]);
    }
    case 'attendance': {
        if ($method !== 'GET') send_json(['ok'=>false,'error'=>'method_not_allowed'], 405);
        $from = $_GET['from'] ?? null; $to = $_GET['to'] ?? null;
        $sql = "SELECT a.date AS att_date, u.first_name, u.last_name, a.status FROM attendance a JOIN users u ON u.id=a.student_id WHERE 1=1";
        $args = [];
        if (!empty($from)) { $sql .= " AND a.date >= ?"; $args[] = $from; }
        if (!empty($to)) { $sql .= " AND a.date <= ?"; $args[] = $to; }
        $sql .= " ORDER BY a.date DESC, u.first_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        send_json(['ok'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
    // Schools list and basic metrics (for dashboard)
    case 'schools': {
        if ($method !== 'GET') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        try {
            // Prefer extended schema if available
            try {
                $rows = $pdo->query('SELECT id, name, code, email FROM schools ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                // Fallback minimal schema
                $rows = $pdo->query('SELECT id, name FROM schools ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
            }
            send_json(['ok'=>true,'schools'=>$rows]);
        } catch (Throwable $e) {
            send_json(['ok'=>true,'schools'=>[],'note'=>'schools table not found']);
        }
    }
    case 'school_metrics': {
        if ($method !== 'GET') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $sid = isset($_GET['schoolId']) ? (int)$_GET['schoolId'] : 0;
        if ($sid <= 0) send_json(['ok'=>false,'error'=>'validation','message'=>'schoolId required'],422);
        $out = ['students'=>0,'teachers'=>0,'activities'=>0,'pending_requests'=>0];
        try {
            // Unified manager schema (init.sql) shape
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE school_id=? AND role="student"');
            $st->execute([$sid]); $out['students'] = (int)$st->fetchColumn();
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE school_id=? AND role="teacher"');
            $st->execute([$sid]); $out['teachers'] = (int)$st->fetchColumn();
        } catch (Throwable $e) { /* ignore if table differs */ }
        try { $st = $pdo->prepare('SELECT COUNT(*) FROM activities WHERE school_id=?'); $st->execute([$sid]); $out['activities'] = (int)$st->fetchColumn(); } catch (Throwable $e) {}
        try { $st = $pdo->prepare('SELECT COUNT(*) FROM account_requests WHERE school_id=? AND status="pending"'); $st->execute([$sid]); $out['pending_requests'] = (int)$st->fetchColumn(); } catch (Throwable $e) {}
        send_json(['ok'=>true] + $out);
    }
    // Pending account requests (optional table)
    case 'pending_requests': {
        if ($method!=='GET') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        try{
            $rows=$pdo->query("SELECT id, role, first_name, last_name, email, gender, grade_level, status, created_at FROM account_requests WHERE status='pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            send_json(['ok'=>true,'requests'=>$rows]);
        }catch(Throwable $e){ send_json(['ok'=>true,'requests'=>[],'note'=>'account_requests table not found']); }
    }
    case 'request_decision': {
        if ($method!=='POST') send_json(['ok'=>false,'error'=>'method_not_allowed'],405);
        $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); $decision=$b['decision']??'';
        if($id<=0 || !in_array($decision,['approved','rejected'],true)) send_json(['ok'=>false,'error'=>'validation'],422);
        try{
            $pdo->prepare("UPDATE account_requests SET status=?, decided_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$decision,$id]);
            send_json(['ok'=>true]);
        }catch(Throwable $e){ send_json(['ok'=>false,'error'=>'failed'],500); }
    }
    // TODO: migrate legacy manager features here as actions (students, subjects, schedule, grades, attendance ...)
    default:
        send_json(['ok'=>false,'message'=>'Manager action not found'], 404);
}

?>