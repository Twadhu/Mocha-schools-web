<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';
require_once __DIR__.'/crypto.php';

$pdo = db();
// Authenticate any logged-in user; we'll enforce roles per-endpoint
$user = auth_require_token($pdo, []);

function ensure_manager(array $u){ if(($u['role']??'')!=='manager'){ json_response(['ok'=>false,'message'=>'Forbidden'],403); } }
function ensure_manager_or_teacher(array $u){ if(!in_array(($u['role']??''), ['manager','teacher'], true)){ json_response(['ok'=>false,'message'=>'Forbidden'],403); } }

function get_subject_id_by_name(PDO $pdo, int $schoolId, string $subjectName){
    $s = $pdo->prepare('SELECT id FROM subjects WHERE school_id=? AND name=? LIMIT 1');
    $s->execute([$schoolId, $subjectName]);
    $sid = $s->fetchColumn();
    return $sid ? intval($sid) : null;
}
function teacher_assigned_to(PDO $pdo, int $teacherId, int $schoolId, string $subjectName, string $gradeLevel): bool {
    $sid = get_subject_id_by_name($pdo, $schoolId, $subjectName);
    if(!$sid) return false;
    $st = $pdo->prepare('SELECT 1 FROM teacher_assignments WHERE teacher_id=? AND subject_id=? AND class_level=? LIMIT 1');
    $st->execute([$teacherId, $sid, $gradeLevel]);
    return (bool)$st->fetchColumn();
}
function fetch_student_grade_section(PDO $pdo, int $schoolId, int $studentId): array {
    $st = $pdo->prepare('SELECT grade_level, section FROM users WHERE id=? AND school_id=? AND role=\'student\' LIMIT 1');
    $st->execute([$studentId, $schoolId]);
    $r = $st->fetch();
    return $r?: ['grade_level'=>null,'section'=>null];
}
function normalize_term($termIn){ return ($termIn==='2' || strtolower((string)$termIn)==='second') ? 'second' : 'first'; }
function load_school_score_limits(PDO $pdo, int $schoolId): array {
    // Defaults
    $maxFirst = 20; $maxFinal = 30; $passPct = 50;
    try{
        $st=$pdo->prepare('SELECT max_first, max_final, pass_threshold FROM school_settings WHERE school_id=? LIMIT 1');
        $st->execute([$schoolId]);
        if($r=$st->fetch()){
            if(isset($r['max_first']) && (int)$r['max_first']>0) $maxFirst=(int)$r['max_first'];
            if(isset($r['max_final']) && (int)$r['max_final']>0) $maxFinal=(int)$r['max_final'];
            if(isset($r['pass_threshold']) && (int)$r['pass_threshold']>0) $passPct=(int)$r['pass_threshold'];
        }
    }catch(Throwable $e){ /* keep defaults */ }
    return ['max_first'=>$maxFirst,'max_final'=>$maxFinal,'pass_threshold'=>$passPct];
}

// Load school feature flags (activities, grades, broadcasts, attendance)
function load_school_feature_flags(PDO $pdo, int $schoolId): array {
    try {
        $st = $pdo->prepare('SELECT activities_enabled, grades_enabled, broadcasts_enabled, attendance_enabled FROM school_settings WHERE school_id=? LIMIT 1');
        $st->execute([$schoolId]);
        if($r=$st->fetch()){
            return [
                'activities_enabled'=>(int)$r['activities_enabled']===1,
                'grades_enabled'=>(int)$r['grades_enabled']===1,
                'broadcasts_enabled'=>(int)$r['broadcasts_enabled']===1,
                'attendance_enabled'=>(int)$r['attendance_enabled']===1,
            ];
        }
    } catch(Throwable $e){ /* ignore */ }
    return [
        'activities_enabled'=>true,
        'grades_enabled'=>true,
        'broadcasts_enabled'=>true,
        'attendance_enabled'=>true,
    ];
}

function body_json(){ $d=json_decode(file_get_contents('php://input'),true); return is_array($d)?$d:[]; }

$path = trim($_GET['path'] ?? ($_SERVER['PATH_INFO'] ?? ''), '/');

// Subjects: list by school
if ($path === 'subjects' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager($user);
    $stmt=$pdo->prepare('SELECT id,name FROM subjects WHERE school_id=? ORDER BY name');
    $stmt->execute([$user['school_id']]);
    json_response(['ok'=>true,'subjects'=>$stmt->fetchAll()]);
}
// Subjects: create new
if ($path === 'subjects' && $_SERVER['REQUEST_METHOD']==='POST'){
    ensure_manager($user);
    $b = body_json();
    $name = trim($b['name'] ?? '');
    if($name===''){ json_response(['ok'=>false,'message'=>'Missing name'],422); }
    try{
        // Upsert: if exists, update to same name and set LAST_INSERT_ID to existing id to return it
        $sql = "INSERT INTO subjects (school_id, name) VALUES (?, ?) \
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), name=VALUES(name)";
        $ins=$pdo->prepare($sql);
        $ins->execute([$user['school_id'], $name]);
        $newId = (int)$pdo->lastInsertId();
        if($newId === 0){
            // Fallback: fetch existing id
            $s=$pdo->prepare('SELECT id FROM subjects WHERE school_id=? AND name=? LIMIT 1');
            $s->execute([$user['school_id'],$name]);
            $newId = (int)($s->fetchColumn() ?: 0);
        }
        json_response(['ok'=>true,'id'=>$newId]);
    }catch(Throwable $e){
        json_response(['ok'=>false,'message'=>'Subject exists or failed'],409);
    }
}
// Subjects: update name
if (preg_match('#^subjects\/(\d+)$#',$path,$m) && $_SERVER['REQUEST_METHOD']==='PUT'){
    ensure_manager($user);
    $id = intval($m[1]);
    $b = body_json();
    $name = trim($b['name'] ?? '');
    if($name===''){ json_response(['ok'=>false,'message'=>'Missing name'],422); }
    try{
        $stmt=$pdo->prepare('UPDATE subjects SET name=? WHERE id=? AND school_id=?');
        $stmt->execute([$name,$id,$user['school_id']]);
        json_response(['ok'=>true]);
    }catch(PDOException $e){
        if((int)$e->getCode()===23000){ // duplicate key
            json_response(['ok'=>false,'message'=>'Subject name already exists', 'error_code'=>'duplicate'],409);
        }
        json_response(['ok'=>false,'message'=>'Update failed'],500);
    }
}

// Subjects: delete
if (preg_match('#^subjects\/(\d+)$#',$path,$m) && $_SERVER['REQUEST_METHOD']==='DELETE'){
    ensure_manager($user);
    $id = intval($m[1]);
    try{
        $stmt=$pdo->prepare('DELETE FROM subjects WHERE id=? AND school_id=?');
        $stmt->execute([$id,$user['school_id']]);
        json_response(['ok'=>true]);
    }catch(PDOException $e){
        json_response(['ok'=>false,'message'=>'Delete failed'],500);
    }
}

// Subject assignments summary: teacher, grade (class_level), subject
if ($path === 'subject-assignments' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager($user);
    $sql = "SELECT CONCAT(u.first_name,' ',u.last_name) AS teacher, ta.class_level AS grade, s.name AS subject
            FROM teacher_assignments ta
            JOIN users u ON u.id=ta.teacher_id AND u.school_id=ta.school_id
            JOIN subjects s ON s.id=ta.subject_id AND s.school_id=ta.school_id
            WHERE ta.school_id=?
            ORDER BY teacher, grade, subject";
    $stmt=$pdo->prepare($sql); $stmt->execute([$user['school_id']]);
    json_response(['ok'=>true,'items'=>$stmt->fetchAll()]);
}

// Students CRUD
if ($path === 'students'){
    if ($_SERVER['REQUEST_METHOD']==='GET'){
        ensure_manager($user);
        $grade = $_GET['grade'] ?? ''; $status=$_GET['status'] ?? ''; $search=trim($_GET['search'] ?? '');
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : null;
        $pageSize = isset($_GET['pageSize']) ? max(1, min(200, intval($_GET['pageSize']))) : null;

        $base = "FROM users WHERE school_id=? AND role='student'";
        $args=[$user['school_id']];
        if ($grade!==''){ $base.=' AND grade_level=?'; $args[]=$grade; }
        if ($status!==''){ $base.=' AND status=?'; $args[]=$status==='inactive'?'disabled':'active'; }
        if ($search!==''){ $like='%'.$search.'%'; $base.=' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $args[]=$like; $args[]=$like; $args[]=$like; }

        // total count (for pagination UI)
        $total = null;
        if ($page!==null && $pageSize!==null){
            $cnt=$pdo->prepare("SELECT COUNT(*) ".$base); $cnt->execute($args); $total = (int)$cnt->fetchColumn();
        }

        $sql = "SELECT id,first_name,last_name,email,phone,grade_level,section,status,id_number,dob ".$base." ORDER BY first_name,last_name";
        if ($page!==null && $pageSize!==null){
            $offset = ($page-1) * $pageSize;
            $sql .= " LIMIT ".$pageSize." OFFSET ".$offset;
        }
        $stmt=$pdo->prepare($sql); $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $resp = ['ok'=>true,'students'=>$rows];
        if ($total!==null) $resp['total']=$total; else $resp['total']=count($rows);
        json_response($resp);
    }
    if ($_SERVER['REQUEST_METHOD']==='POST'){
        ensure_manager($user);
        $b = body_json();
        foreach(['first_name','last_name','email','grade_level'] as $k){ if(empty($b[$k])) json_response(['ok'=>false,'message'=>'Missing '.$k],422); }
        $tmpPass = bin2hex(random_bytes(3));
        $hash = password_hash($tmpPass, PASSWORD_DEFAULT);
        $gender = isset($b['gender']) && in_array($b['gender'], ['male','female'], true) ? $b['gender'] : null;
        $stmt=$pdo->prepare("INSERT INTO users (school_id,role,first_name,last_name,email,password_hash,grade_level,section,phone,id_number,dob,status,gender) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user['school_id'],'student',$b['first_name'],$b['last_name'],strtolower($b['email']),$hash,$b['grade_level'],$b['section']??null,$b['phone']??null,$b['id_number']??null,$b['dob']??null, ($b['status']??'active')==='inactive'?'disabled':'active', $gender]);
        json_response(['ok'=>true,'id'=>$pdo->lastInsertId(),'tempPassword'=>$tmpPass]);
    }
}
if (preg_match('#^students/(\d+)$#',$path,$m)){
    $id=intval($m[1]);
    if ($_SERVER['REQUEST_METHOD']==='PUT'){
        ensure_manager($user);
        $b=body_json();
        $stmt=$pdo->prepare("UPDATE users SET first_name=?, last_name=?, grade_level=?, section=?, phone=?, id_number=?, dob=?, status=?, gender=? WHERE id=? AND school_id=? AND role='student'");
        $status = ($b['status']??'active')==='inactive'?'disabled':'active';
        $gender = isset($b['gender']) && in_array($b['gender'], ['male','female'], true) ? $b['gender'] : null;
        $stmt->execute([$b['first_name'],$b['last_name'],$b['grade_level'],$b['section'],$b['phone'],$b['id_number'],$b['dob'],$status,$gender,$id,$user['school_id']]);
        json_response(['ok'=>true]);
    }
    if ($_SERVER['REQUEST_METHOD']==='DELETE'){
        ensure_manager($user);
        $stmt=$pdo->prepare("DELETE FROM users WHERE id=? AND school_id=? AND role='student'");
        $stmt->execute([$id,$user['school_id']]);
        json_response(['ok'=>true]);
    }
}

// Students: signed access code for ID card QR
if (preg_match('#^students/(\d+)/access-code$#',$path,$m) && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager($user);
    $sid = intval($m[1]);
    // Ensure student exists and belongs to this school
    $chk = $pdo->prepare("SELECT id,status FROM users WHERE id=? AND school_id=? AND role='student' LIMIT 1");
    $chk->execute([$sid, $user['school_id']]);
    $stu = $chk->fetch();
    if(!$stu){ json_response(['ok'=>false,'message'=>'Student not found'],404); }
    if(($stu['status']??'active')==='disabled'){ json_response(['ok'=>false,'message'=>'Student inactive'],403); }

    // Load key(s) and select active kid
    $keys = load_access_keys();
    // prefer env ACCESS_CODE_KID; otherwise first key
    $kid = getenv('ACCESS_CODE_KID') ?: array_key_first($keys);
    $secret = $keys[$kid] ?? null;
    if(!$secret){ json_response(['ok'=>false,'message'=>'Access-code secret missing'],500); }

    // Generate short-lived code (8 chars), versioned payload and jti
    $v = '1';
    $code = strtoupper(substr(bin2hex(random_bytes(4)),0,8));
    $expTs = time() + 3600; // 1 hour validity
    $jti = substr(bin2hex(random_bytes(6)),0,12); // short unique id for replay diagnostics
    $payloadNoSig = "v=$v|sid=$sid|code=$code|exp=$expTs|kid=$kid|jti=$jti";
    $sig = hmac_sign_b64url($payloadNoSig, $secret);
    $payload = $payloadNoSig."|sig=$sig";
    json_response(['ok'=>true,'code'=>$code,'exp'=>$expTs,'kid'=>$kid,'jti'=>$jti,'payload'=>$payload]);
}

// Optional centralized verify endpoint for door readers (HMAC)
if ($path === 'verify-access' && $_SERVER['REQUEST_METHOD']==='POST'){
    ensure_manager($user);
    $b = body_json();
    $payloadFull = (string)($b['payload'] ?? '');
    if ($payloadFull===''){ json_response(['ok'=>false,'error'=>'no-payload'],400); }
    $parts = explode('|sig=', $payloadFull, 2);
    if (count($parts)!==2){ json_response(['ok'=>false,'error'=>'bad-format'],400); }
    $payloadNoSig = $parts[0]; $sig = $parts[1];
    $kv = parse_versioned_payload($payloadNoSig);
    $v=$kv['v']??null; $sid=$kv['sid']??null; $exp=intval($kv['exp']??0); $kid=$kv['kid']??null; $code=$kv['code']??null; $jti=$kv['jti']??null;
    if($v!=='1' || !$sid || !$kid || !$exp || !$code){ json_response(['ok'=>false,'error'=>'bad-fields'],400); }
    $keys = load_access_keys();
    if(!isset($keys[$kid])){ json_response(['ok'=>false,'error'=>'unknown-kid'],401); }
    if(!hmac_verify_b64url($payloadNoSig, $sig, $keys[$kid])){ json_response(['ok'=>false,'error'=>'bad-signature'],401); }
    $now=time(); if ($now > $exp + 120){ json_response(['ok'=>false,'error'=>'expired'],401); }
    // Optional: check status/revocation
    $chk = $pdo->prepare("SELECT status FROM users WHERE id=? AND role='student' LIMIT 1"); $chk->execute([intval($sid)]);
    $st = $chk->fetchColumn(); if(!$st || $st==='disabled'){ json_response(['ok'=>false,'error'=>'inactive'],403); }
    // Optional: hook for replay, rate limit, and audit logs could be added here
    json_response(['ok'=>true,'sid'=>intval($sid),'exp'=>$exp,'kid'=>$kid,'jti'=>$jti]);
}

// Teachers CRUD
if ($path === 'teachers'){
    if ($_SERVER['REQUEST_METHOD']==='GET'){
        ensure_manager($user);
        $subject = $_GET['subject'] ?? ''; $status=$_GET['status'] ?? ''; $search=trim($_GET['search'] ?? '');
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : null;
        $pageSize = isset($_GET['pageSize']) ? max(1, min(200, intval($_GET['pageSize']))) : null;

        // Build base WHERE with optional EXISTS for subject filter
        $where = "FROM users u WHERE u.school_id=? AND u.role='teacher'";
        $args=[$user['school_id']];
        if ($status!==''){ $where.=' AND u.status=?'; $args[]=$status==='inactive'?'disabled':'active'; }
        if ($search!==''){ $like='%'.$search.'%'; $where.=' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.specialization LIKE ?)'; $args[]=$like; $args[]=$like; $args[]=$like; $args[]=$like; }
        if ($subject!==''){
            $sid = intval($subject);
            $where .= ' AND EXISTS (SELECT 1 FROM teacher_assignments ta WHERE ta.teacher_id=u.id AND ta.subject_id=?)';
            $args[] = $sid;
        }

        // total count (for pagination UI)
        $total = null;
        if ($page!==null && $pageSize!==null){
            $cnt=$pdo->prepare("SELECT COUNT(*) ".$where); $cnt->execute($args); $total = (int)$cnt->fetchColumn();
        }

        // main query with optional pagination
        $sql = "SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.specialization,u.status ".$where." ORDER BY u.first_name,u.last_name";
        if ($page!==null && $pageSize!==null){
            $offset = ($page-1) * $pageSize;
            $sql .= " LIMIT ".$pageSize." OFFSET ".$offset;
        }
        $stmt=$pdo->prepare($sql); $stmt->execute($args);
        $rows=$stmt->fetchAll();
        // subjects/classes via assignments (N+1 acceptable for small page)
        foreach($rows as &$t){
            $a=$pdo->prepare('SELECT ta.class_level AS class, s.id AS subject_id, s.name FROM teacher_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=?');
            $a->execute([$t['id']]); $as=$a->fetchAll();
            $t['subjects']=array_values(array_reduce($as,function($acc,$x){ $acc[$x['subject_id']] = ['id'=>$x['subject_id'],'name'=>$x['name']]; return $acc;},[]));
            $t['classes']=array_values(array_unique(array_map(function($x){return $x['class'];},$as)));
        }
        $resp = ['ok'=>true,'teachers'=>$rows];
        if ($total!==null) $resp['total']=$total; else $resp['total']=count($rows);
        json_response($resp);
    }
    if ($_SERVER['REQUEST_METHOD']==='POST'){
        ensure_manager($user);
        $b=body_json();
        foreach(['first_name','last_name','email'] as $k){ if(empty($b[$k])) json_response(['ok'=>false,'message'=>'Missing '.$k],422); }
        $tmpPass = bin2hex(random_bytes(3)); $hash=password_hash($tmpPass,PASSWORD_DEFAULT);
        $stmt=$pdo->prepare("INSERT INTO users (school_id,role,first_name,last_name,email,password_hash,phone,specialization,status) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$user['school_id'],'teacher',strtolower($b['first_name']),$b['last_name'],strtolower($b['email']),$hash,$b['phone']??null,$b['specialization']??null, 'active']);
        $tid=$pdo->lastInsertId();
        // subjects/classes assignments
        $subs = is_array($b['subjects']??null)?$b['subjects']:[];
        $classes = is_array($b['classes']??null)?$b['classes']:[];
        $ins=$pdo->prepare('INSERT INTO teacher_assignments (school_id, teacher_id, subject_id, class_level) VALUES (?,?,?,?)');
        foreach($classes as $cl){ foreach($subs as $sid){ $ins->execute([$user['school_id'],$tid,intval($sid),$cl]); }}
        json_response(['ok'=>true,'id'=>$tid,'tempPassword'=>$tmpPass]);
    }
}

// Update/Delete specific teacher
if (preg_match('#^teachers/(\d+)$#',$path,$m)){
    $tid = intval($m[1]);
    if ($_SERVER['REQUEST_METHOD']==='PUT'){
        ensure_manager($user);
        $b=body_json();
    // Load current values to support partial updates
    $curStmt=$pdo->prepare("SELECT first_name,last_name,email,phone,specialization,status FROM users WHERE id=? AND school_id=? AND role='teacher'");
    $curStmt->execute([$tid,$user['school_id']]);
    $cur=$curStmt->fetch();
    if(!$cur){ json_response(['ok'=>false,'message'=>'Not found'],404); }
    $first_name = array_key_exists('first_name',$b) ? $b['first_name'] : $cur['first_name'];
    $last_name = array_key_exists('last_name',$b) ? $b['last_name'] : $cur['last_name'];
    $email = array_key_exists('email',$b) ? strtolower($b['email']) : $cur['email'];
    $phone = array_key_exists('phone',$b) ? $b['phone'] : $cur['phone'];
    $specialization = array_key_exists('specialization',$b) ? $b['specialization'] : $cur['specialization'];
    $statusIn = array_key_exists('status',$b) ? $b['status'] : ($cur['status']==='disabled'?'inactive':'active');
    $status = $statusIn==='inactive' ? 'disabled' : 'active';
    $stmt=$pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, specialization=?, status=? WHERE id=? AND school_id=? AND role='teacher'");
    $stmt->execute([$first_name,$last_name,$email,$phone,$specialization,$status,$tid,$user['school_id']]);
        // Optionally update assignments if provided
        if (isset($b['subjects']) || isset($b['classes'])){
            $subs = is_array($b['subjects']??null)?array_map('intval',$b['subjects']):null;
            $classes = is_array($b['classes']??null)?$b['classes']:null;
            if ($subs!==null || $classes!==null){
                // Rebuild from provided arrays; if one is null, fetch existing dimension
                if ($subs===null){ $s=$pdo->prepare('SELECT DISTINCT subject_id FROM teacher_assignments WHERE teacher_id=?'); $s->execute([$tid]); $subs=array_map('intval',array_column($s->fetchAll(), 'subject_id')); }
                if ($classes===null){ $c=$pdo->prepare('SELECT DISTINCT class_level FROM teacher_assignments WHERE teacher_id=?'); $c->execute([$tid]); $classes=array_map(function($r){return $r['class_level'];}, $c->fetchAll()); }
                $pdo->beginTransaction();
                try{
                    $del=$pdo->prepare('DELETE FROM teacher_assignments WHERE teacher_id=?'); $del->execute([$tid]);
                    if ($subs && $classes){ $ins=$pdo->prepare('INSERT INTO teacher_assignments (school_id, teacher_id, subject_id, class_level) VALUES (?,?,?,?)'); foreach($classes as $cl){ foreach($subs as $sid){ $ins->execute([$user['school_id'],$tid,intval($sid),$cl]); }} }
                    $pdo->commit();
                }catch(Exception $e){ $pdo->rollBack(); json_response(['ok'=>false,'message'=>'Failed to update assignments'],500); }
            }
        }
        json_response(['ok'=>true]);
    }
    if ($_SERVER['REQUEST_METHOD']==='DELETE'){
        ensure_manager($user);
        $pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM teacher_assignments WHERE teacher_id=?')->execute([$tid]);
            $pdo->prepare("DELETE FROM users WHERE id=? AND school_id=? AND role='teacher'")->execute([$tid,$user['school_id']]);
            $pdo->commit();
            json_response(['ok'=>true]);
        }catch(Exception $e){ $pdo->rollBack(); json_response(['ok'=>false,'message'=>'Failed'],500); }
    }
}

if (preg_match('#^teacher-assignments/(\d+)$#',$path,$m)){
    ensure_manager($user);
    $tid=intval($m[1]);
    if ($_SERVER['REQUEST_METHOD']==='GET'){
        $stmt=$pdo->prepare('SELECT ta.class_level AS class, ta.subject_id FROM teacher_assignments ta WHERE ta.teacher_id=?');
        $stmt->execute([$tid]);
        json_response(['ok'=>true,'assignments'=>$stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD']==='PUT'){
        $b=body_json(); $items = is_array($b['assignments']??null)?$b['assignments']:[];
        $pdo->beginTransaction();
        try{
            $del=$pdo->prepare('DELETE FROM teacher_assignments WHERE teacher_id=?'); $del->execute([$tid]);
            if($items){ $ins=$pdo->prepare('INSERT INTO teacher_assignments (school_id, teacher_id, subject_id, class_level) VALUES (?,?,?,?)'); foreach($items as $it){ $ins->execute([$user['school_id'],$tid,intval($it['subject_id']),$it['class']]); } }
            $pdo->commit(); json_response(['ok'=>true]);
        }catch(Exception $e){ $pdo->rollBack(); json_response(['ok'=>false,'message'=>'Failed'],500); }
    }
}

// View a teacher schedule (manager-only)
if (preg_match('#^teacher-schedule/(\d+)$#',$path,$m)){
    ensure_manager($user);
    $tid=intval($m[1]);
    $semester = isset($_GET['semester']) ? intval($_GET['semester']) : null;
    $sql = "SELECT semester, grade, section, day, period, subject FROM schedules WHERE school_id=? AND teacher_id=?";
    $args = [$user['school_id'],$tid];
    if ($semester){ $sql.=' AND semester=?'; $args[]=$semester; }
    $sql.=" ORDER BY semester, FIELD(day,'Sat','Sun','Mon','Tue','Wed','Thu'), period";
    $stmt=$pdo->prepare($sql); $stmt->execute($args);
    json_response(['ok'=>true,'items'=>$stmt->fetchAll()]);
}

// Grade schedule view (by grade/section/semester)
if ($path === 'schedule' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager_or_teacher($user);
    $grade = trim($_GET['grade'] ?? '');
    $semester = intval($_GET['semester'] ?? 0);
    $section = trim($_GET['section'] ?? '');
    if($grade==='' || !$semester){ json_response(['ok'=>false,'message'=>'Missing grade or semester'],422); }
    $sql = "SELECT s.semester, s.grade, s.section, s.day, s.period, s.subject, s.teacher_id,
                   CONCAT(u.first_name,' ',u.last_name) AS teacher_name
            FROM schedules s
            LEFT JOIN users u ON u.id = s.teacher_id AND u.school_id = s.school_id
            WHERE s.school_id=? AND s.grade=? AND s.semester=?";
    $args = [$user['school_id'],$grade,$semester];
    if($section!==''){ $sql.=' AND s.section=?'; $args[]=$section; }
    $sql .= " ORDER BY FIELD(s.day,'Sat','Sun','Mon','Tue','Wed','Thu'), s.period";
    $st = $pdo->prepare($sql); $st->execute($args);
    json_response(['ok'=>true,'items'=>$st->fetchAll()]);
}

// Schedule update (replace entries for grade/semester/section)
if ($path==='schedule' && $_SERVER['REQUEST_METHOD']==='PUT'){
    ensure_manager_or_teacher($user);
    $b=body_json();
    $semester=intval($b['semester']??0); $grade=$b['grade']??''; $section=$b['section']??null; $entries=is_array($b['entries']??null)?$b['entries']:[];
    if(!$semester||!$grade) json_response(['ok'=>false,'message'=>'Missing'],422);
    $pdo->beginTransaction();
    try{
        $del=$pdo->prepare('DELETE FROM schedules WHERE school_id=? AND semester=? AND grade=? AND ((? IS NULL AND section IS NULL) OR section=?)');
        $del->execute([$user['school_id'],$semester,$grade,$section,$section]);
        if($entries){ $ins=$pdo->prepare('INSERT INTO schedules (school_id, semester, grade, section, day, period, subject, teacher_id) VALUES (?,?,?,?,?,?,?,?)');
            foreach($entries as $e){ $ins->execute([$user['school_id'],$semester,$grade,$section,$e['day'],$e['period'],$e['subject'],$e['teacher_id']??null]); }
        }
        $pdo->commit(); json_response(['ok'=>true]);
    }catch(Exception $e){ $pdo->rollBack(); json_response(['ok'=>false,'message'=>'Failed'],500); }
}

// Results: list per grade/term/year or per student
if ($path === 'results' && $_SERVER['REQUEST_METHOD']==='GET'){
    $grade = $_GET['grade'] ?? '';
    $section = trim($_GET['section'] ?? '');
    $termIn = $_GET['term'] ?? '';
    $year = $_GET['year'] ?? '';
    $term = normalize_term($termIn);
    // If teacher, ensure they are assigned to this grade in at least one subject (or simply require grade param)
    if(($user['role']??'')==='teacher'){
        if($grade===''){ json_response(['ok'=>false,'message'=>'grade-required'],422); }
        // allow listing roster for grade; subject filter not provided here
        // Optional: ensure teacher has any assignment for this grade
        $chk=$pdo->prepare('SELECT 1 FROM teacher_assignments WHERE teacher_id=? AND class_level=? LIMIT 1');
        $chk->execute([$user['user_id'],$grade]); if(!$chk->fetch()){ json_response(['ok'=>false,'message'=>'forbidden'],403); }
    }
    // base: students of this school (optionally filter grade)
    $sqlStu = "SELECT id, CONCAT(first_name,' ',last_name) AS name, grade_level, section FROM users WHERE school_id=? AND role='student'";
    $argsStu = [$user['school_id']];
    if($grade!==''){ $sqlStu.=' AND grade_level=?'; $argsStu[]=$grade; }
    if($section!==''){ $sqlStu.=' AND section=?'; $argsStu[]=$section; }
    $sqlStu.=' ORDER BY first_name,last_name';
    $st = $pdo->prepare($sqlStu); $st->execute($argsStu); $students = $st->fetchAll();
    $ids = array_map(function($r){return intval($r['id']);}, $students);
    $items = [];
    if ($ids){
        // Fetch results joined by student
        $in = implode(',', array_fill(0, count($ids), '?'));
        $args = $ids;
        $q = "SELECT tr.student_id, tr.subject, tr.first_score, tr.final_score, tr.year_label, tr.term
              FROM term_results tr
              WHERE tr.school_id=? AND tr.student_id IN ($in)";
        array_unshift($args, $user['school_id']);
        if($year!==''){ $q.=' AND tr.year_label=?'; $args[]=$year; }
        if($term){ $q.=' AND tr.term=?'; $args[]=$term; }
        $rs = $pdo->prepare($q); $rs->execute($args); $rows = $rs->fetchAll();
        // group by student
        $byStu = [];
        foreach($rows as $r){ $byStu[$r['student_id']][] = ['name'=>$r['subject'],'first'=>intval($r['first_score']),'final'=>intval($r['final_score'])]; }
        foreach($students as $stu){
            $items[] = [
                'id'=>intval($stu['id']),
                'name'=>$stu['name'],
                'class'=>$stu['grade_level'],
                'section'=>$stu['section'] ?? null,
                'year'=>$year ?: date('Y'),
                'term'=>$term,
                'subjects'=>array_values($byStu[$stu['id']] ?? [])
            ];
        }
    }
    json_response(['ok'=>true,'items'=>$items]);
}

// Results: upsert per student for a subject/term/year
if ($path === 'results' && $_SERVER['REQUEST_METHOD']==='POST'){
    ensure_manager_or_teacher($user);
    $b = body_json();
    $student_id = intval($b['student_id'] ?? 0);
    $subject = trim($b['subject'] ?? '');
    $termIn = $b['term'] ?? 'first';
    $year = trim($b['year'] ?? date('Y'));
    $first = intval($b['first'] ?? 0);
    $final = intval($b['final'] ?? 0);
    if(!$student_id || $subject===''){ json_response(['ok'=>false,'message'=>'Missing'],422); }
    $term = normalize_term($termIn);
    // verify student belongs to this school
    $chk=$pdo->prepare("SELECT 1 FROM users WHERE id=? AND school_id=? AND role='student'"); $chk->execute([$student_id,$user['school_id']]); if(!$chk->fetch()){ json_response(['ok'=>false,'message'=>'Student not found'],404); }
    // enforce teacher assignment if teacher
    if(($user['role']??'')==='teacher'){
        // derive student's grade for assignment check
        $sg = fetch_student_grade_section($pdo, $user['school_id'], $student_id);
        $stGrade = (string)($sg['grade_level'] ?? '');
        if(!teacher_assigned_to($pdo, intval($user['user_id']), intval($user['school_id']), $subject, $stGrade)){
            json_response(['ok'=>false,'message'=>'forbidden'],403);
        }
    }
    // Check locks (by grade/section/subject/term/year)
    $sg = fetch_student_grade_section($pdo, $user['school_id'], $student_id);
    $stGrade = (string)($sg['grade_level'] ?? ''); $stSection = (string)($sg['section'] ?? '');
    $lock = $pdo->prepare('SELECT locked, approved FROM result_locks WHERE school_id=? AND grade=? AND section=? AND subject=? AND term=? AND year_label=? LIMIT 1');
    $lock->execute([$user['school_id'],$stGrade, ($stSection===''? '' : $stSection), $subject, $term, $year]);
    $lk = $lock->fetch();
    if($lk && (intval($lk['locked'])===1 || intval($lk['approved'])===1)){
        json_response(['ok'=>false,'message'=>'locked'],423);
    }
    // Validate score bounds (server-side)
    $limits = load_school_score_limits($pdo, intval($user['school_id']));
    if($first < 0 || $first > $limits['max_first'] || $final < 0 || $final > $limits['max_final']){
        json_response(['ok'=>false,'message'=>'invalid-score-range','meta'=>$limits],422);
    }
    // Audit previous before upsert
    $prevStmt = $pdo->prepare('SELECT id, first_score, final_score FROM term_results WHERE school_id=? AND student_id=? AND subject=? AND term=? AND year_label=? LIMIT 1');
    $prevStmt->execute([$user['school_id'],$student_id,$subject,$term,$year]);
    $prev = $prevStmt->fetch();
    $sql = "INSERT INTO term_results (school_id, student_id, subject, term, year_label, first_score, final_score)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE first_score=VALUES(first_score), final_score=VALUES(final_score)";
    $stmt=$pdo->prepare($sql); $stmt->execute([$user['school_id'],$student_id,$subject,$term,$year,$first,$final]);
    // write audit if changed
    if(!$prev || intval($prev['first_score'])!==$first || intval($prev['final_score'])!==$final){
        $insA=$pdo->prepare('INSERT INTO grade_audits (school_id, actor_id, student_id, subject, term, year_label, prev_first, prev_final, new_first, new_final) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $insA->execute([$user['school_id'], intval($user['user_id']), $student_id, $subject, $term, $year, intval($prev['first_score']??0), intval($prev['final_score']??0), $first, $final]);
    }
    json_response(['ok'=>true]);
}

// Subject-results: consolidated list for a subject for a grade/section/term/year
if ($path === 'subject-results' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager_or_teacher($user);
    $grade = trim($_GET['grade'] ?? ''); $section = trim($_GET['section'] ?? '');
    $subject = trim($_GET['subject'] ?? '');
    $term = normalize_term($_GET['term'] ?? 'first');
    $year = trim($_GET['year'] ?? '');
    if($grade==='' || $subject===''){ json_response(['ok'=>false,'message'=>'Missing'],422); }
    // Teachers must be assigned to this grade and subject
    if(($user['role']??'')==='teacher'){
        if(!teacher_assigned_to($pdo, intval($user['user_id']), intval($user['school_id']), $subject, $grade)){
            json_response(['ok'=>false,'message'=>'forbidden'],403);
        }
    }
    // Roster for grade/section
    $sqlStu = "SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE school_id=? AND role='student' AND grade_level=?";
    $argsStu = [$user['school_id'],$grade];
    if($section!==''){ $sqlStu.=' AND section=?'; $argsStu[]=$section; }
    $sqlStu.=' ORDER BY first_name,last_name';
    $st=$pdo->prepare($sqlStu); $st->execute($argsStu); $students=$st->fetchAll();
    $ids = array_map(function($r){return intval($r['id']);}, $students);
    $byId = [];
    foreach($students as $s){ $byId[intval($s['id'])] = ['id'=>intval($s['id']),'name'=>$s['name'],'first'=>0,'final'=>0]; }
    if($ids){
        $in = implode(',', array_fill(0, count($ids), '?'));
        $args = $ids; array_unshift($args, $user['school_id']);
        $q = "SELECT student_id, first_score, final_score FROM term_results WHERE school_id=? AND student_id IN ($in) AND subject=?";
        $args[] = $subject;
        if($year!==''){ $q.=' AND year_label=?'; $args[]=$year; }
        if($term){ $q.=' AND term=?'; $args[]=$term; }
        $rs=$pdo->prepare($q); $rs->execute($args);
        foreach($rs->fetchAll() as $r){ $sid=intval($r['student_id']); if(isset($byId[$sid])){ $byId[$sid]['first']=intval($r['first_score']??0); $byId[$sid]['final']=intval($r['final_score']??0); } }
    }
    $items = array_values($byId);
    json_response(['ok'=>true,'items'=>$items]);
}

// Results: delete a subject from a student's result
if (preg_match('#^results/(\d+)$#',$path,$m) && $_SERVER['REQUEST_METHOD']==='DELETE'){
    ensure_manager($user);
    $id = intval($m[1]); // id of term_results
    $del=$pdo->prepare('DELETE FROM term_results WHERE id=? AND school_id=?');
    $del->execute([$id,$user['school_id']]);
    json_response(['ok'=>true]);
}

// Results raw list for a student (to find ids for deletion)
if ($path === 'results-raw' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager($user);
    $student_id = intval($_GET['student_id'] ?? 0);
    $termIn = $_GET['term'] ?? '';
    $year = $_GET['year'] ?? '';
    if(!$student_id){ json_response(['ok'=>false,'message'=>'Missing student'],422); }
    $term = normalize_term($termIn);
    $sql = 'SELECT id, subject, first_score, final_score, term, year_label FROM term_results WHERE school_id=? AND student_id=?';
    $args = [$user['school_id'], $student_id];
    if($year!==''){ $sql.=' AND year_label=?'; $args[]=$year; }
    if($term){ $sql.=' AND term=?'; $args[]=$term; }
    $stmt=$pdo->prepare($sql); $stmt->execute($args);
    json_response(['ok'=>true,'items'=>$stmt->fetchAll()]);
}

// Result-one: fetch a single student's result for a subject/term/year
if ($path === 'result-one' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager_or_teacher($user);
    $student_id = intval($_GET['student_id'] ?? 0);
    $subject = trim($_GET['subject'] ?? '');
    $term = normalize_term($_GET['term'] ?? 'first');
    $year = trim($_GET['year'] ?? '');
    if(!$student_id || $subject===''){ json_response(['ok'=>false,'message'=>'Missing'],422); }
    // Verify student in school
    $chk=$pdo->prepare('SELECT grade_level FROM users WHERE id=? AND school_id=? AND role=\'student\''); $chk->execute([$student_id,$user['school_id']]);
    $gradeOfStudent = $chk->fetchColumn(); if(!$gradeOfStudent){ json_response(['ok'=>true,'first'=>0,'final'=>0]); }
    // If teacher, ensure assignment
    if(($user['role']??'')==='teacher'){
        if(!teacher_assigned_to($pdo, intval($user['user_id']), intval($user['school_id']), $subject, (string)$gradeOfStudent)){
            json_response(['ok'=>false,'message'=>'forbidden'],403);
        }
    }
    $sql='SELECT first_score, final_score FROM term_results WHERE school_id=? AND student_id=? AND subject=? AND term=?';
    $args=[$user['school_id'],$student_id,$subject,$term];
    if($year!==''){ $sql.=' AND year_label=?'; $args[]=$year; }
    $st=$pdo->prepare($sql); $st->execute($args); $r=$st->fetch();
    json_response(['ok'=>true,'first'=>intval($r['first_score']??0),'final'=>intval($r['final_score']??0)]);
}

// Locks: state
if ($path === 'results/lock-state' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager_or_teacher($user);
    $grade = trim($_GET['grade'] ?? ''); $section = trim($_GET['section'] ?? ''); $subject = trim($_GET['subject'] ?? '');
    $term = normalize_term($_GET['term'] ?? 'first'); $year = trim($_GET['year'] ?? '');
    if($grade==='' || $subject===''){ json_response(['ok'=>false,'message'=>'Missing'],422); }
    // If teacher, ensure assignment
    if(($user['role']??'')==='teacher'){
        if(!teacher_assigned_to($pdo, intval($user['user_id']), intval($user['school_id']), $subject, $grade)){
            json_response(['ok'=>false,'message'=>'forbidden'],403);
        }
    }
    $st=$pdo->prepare('SELECT locked, approved FROM result_locks WHERE school_id=? AND grade=? AND section=? AND subject=? AND term=? AND year_label=? LIMIT 1');
    $st->execute([$user['school_id'],$grade, ($section===''? '':$section), $subject, $term, $year]);
    $r=$st->fetch();
    json_response(['ok'=>true,'locked'=>intval($r['locked']??0)===1,'approved'=>intval($r['approved']??0)===1]);
}

// Locks: toggle (teacher can lock/unlock their subject; manager can do any)
if ($path === 'results/lock' && $_SERVER['REQUEST_METHOD']==='POST'){
    ensure_manager_or_teacher($user);
    $b=body_json(); $grade=trim($b['grade']??''); $section=trim($b['section']??''); $subject=trim($b['subject']??''); $term=normalize_term($b['term']??'first'); $year=trim($b['year']??''); $want=!!($b['lock']??false);
    if($grade==='' || $subject===''){ json_response(['ok'=>false,'message'=>'Missing'],422); }
    if(($user['role']??'')==='teacher'){
        if(!teacher_assigned_to($pdo, intval($user['user_id']), intval($user['school_id']), $subject, $grade)){
            json_response(['ok'=>false,'message'=>'forbidden'],403);
        }
    }
    $pdo->beginTransaction();
    try{
    $upd=$pdo->prepare('INSERT INTO result_locks (school_id, grade, section, subject, term, year_label, locked, locked_by, locked_at, approved) VALUES (?,?,?,?,?,?,?,?,NOW(),0)
                             ON DUPLICATE KEY UPDATE locked=VALUES(locked), locked_by=VALUES(locked_by), locked_at=VALUES(locked_at)');
    $upd->execute([$user['school_id'],$grade, ($section===''? '':$section), $subject, $term, $year, $want?1:0, intval($user['user_id'])]);
        $pdo->commit(); json_response(['ok'=>true]);
    }catch(Exception $e){ $pdo->rollBack(); json_response(['ok'=>false,'message'=>'Failed'],500); }
}

// Approve: manager only
if ($path === 'results/approve' && $_SERVER['REQUEST_METHOD']==='POST'){
    ensure_manager($user);
    $b=body_json(); $grade=trim($b['grade']??''); $section=trim($b['section']??''); $subject=trim($b['subject']??''); $term=normalize_term($b['term']??'first'); $year=trim($b['year']??''); $want=!!($b['approve']??false);
    if($grade==='' || $subject===''){ json_response(['ok'=>false,'message'=>'Missing'],422); }
    $pdo->beginTransaction();
    try{
    $upd=$pdo->prepare('INSERT INTO result_locks (school_id, grade, section, subject, term, year_label, approved, approved_by, approved_at) VALUES (?,?,?,?,?,?,?,?,NOW())
                             ON DUPLICATE KEY UPDATE approved=VALUES(approved), approved_by=VALUES(approved_by), approved_at=VALUES(approved_at)');
    $upd->execute([$user['school_id'],$grade, ($section===''? '':$section), $subject, $term, $year, $want?1:0, intval($user['user_id'])]);
        $pdo->commit(); json_response(['ok'=>true]);
    }catch(Exception $e){ $pdo->rollBack(); json_response(['ok'=>false,'message'=>'Failed'],500); }
}

// School meta (name/principal)
if ($path === 'school-meta' && $_SERVER['REQUEST_METHOD']==='GET'){
    // Any logged-in role
    $st=$pdo->prepare('SELECT id,name,principal_name FROM schools WHERE id=?');
    $st->execute([$user['school_id']]);
    $r=$st->fetch();
    json_response(['ok'=>true,'school'=>$r]);
}

// Score limits (max_first, max_final, pass_threshold) for dynamic client configuration
if ($path === 'score-limits' && $_SERVER['REQUEST_METHOD']==='GET'){
    $limits = load_school_score_limits($pdo, intval($user['school_id']));
    json_response(['ok'=>true] + $limits);
}

// Feature flags: GET current
if ($path === 'feature-flags' && $_SERVER['REQUEST_METHOD']==='GET'){
    $flags = load_school_feature_flags($pdo, intval($user['school_id']));
    json_response(['ok'=>true] + $flags);
}
// Feature flags: update (manager only)
if ($path === 'feature-flags' && $_SERVER['REQUEST_METHOD']==='PUT'){
    ensure_manager($user);
    $b = body_json();
    $allowed = ['activities_enabled','grades_enabled','broadcasts_enabled','attendance_enabled'];
    $updates = [];
    $params = [];
    foreach($allowed as $k){
        if(isset($b[$k])){ $updates[] = "$k=?"; $params[] = $b[$k] ? 1 : 0; }
    }
    if(!$updates){ json_response(['ok'=>false,'message'=>'No changes'],422); }
    $params[] = $user['school_id'];
    $sql = 'UPDATE school_settings SET '.implode(',', $updates).' WHERE school_id=?';
    $st=$pdo->prepare($sql); $st->execute($params);
    json_response(['ok'=>true]);
}

// Attendance (manager overview) - summary list for a given date or range
if ($path === 'attendance' && $_SERVER['REQUEST_METHOD']==='GET'){
    ensure_manager($user);
    $date = trim($_GET['date'] ?? '');
    $grade = trim($_GET['grade'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    if($date && ($from || $to)) json_response(['ok'=>false,'message'=>'Provide either date OR from/to'],422);
    $params = [$user['school_id']];
    $where = 'a.school_id=?';
    if($grade!==''){ $where .= ' AND s.grade_level=?'; $params[]=$grade; }
    if($date!==''){ $where .= ' AND a.att_date=?'; $params[]=$date; }
    else {
        if($from!==''){ $where .= ' AND a.att_date>=?'; $params[]=$from; }
        if($to!==''){ $where .= ' AND a.att_date<=?'; $params[]=$to; }
    }
    $sql = "SELECT a.att_date, s.id AS student_id, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.grade_level, a.status
            FROM attendance a JOIN users s ON s.id=a.student_id WHERE $where ORDER BY a.att_date DESC, s.first_name";
    $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
    json_response(['ok'=>true,'items'=>$rows]);
}

// Change password for the currently authenticated manager
if ($path === 'change-password' && $_SERVER['REQUEST_METHOD']==='POST'){
    $b = body_json();
    $current = (string)($b['current'] ?? '');
    $new = (string)($b['new'] ?? '');
    if(strlen($new) < 8){ json_response(['ok'=>false,'message'=>'Password too short'],422); }
    // Load manager account
    $stm = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND school_id=? AND role="manager" LIMIT 1');
    $stm->execute([$user['user_id'], $user['school_id']]);
    $row = $stm->fetch();
    if(!$row){ json_response(['ok'=>false,'message'=>'Manager not found'],404); }
    if(!password_verify($current, $row['password_hash'])){ json_response(['ok'=>false,'message'=>'Current password is incorrect'],403); }
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $up = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND school_id=? AND role="manager"');
    $up->execute([$newHash, $user['user_id'], $user['school_id']]);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Not found'],404);
