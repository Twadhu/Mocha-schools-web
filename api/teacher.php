<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';

$pdo = db();
$user = auth_require_token($pdo, ['teacher']);

$path = $_GET['path'] ?? ($_SERVER['PATH_INFO'] ?? '');
$path = trim($path, '/');

function get_json_body(){
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($path === 'homework') {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $stmt = $pdo->prepare('SELECT id, subject, class_level, section, title, content, due_date FROM homework WHERE school_id=? AND teacher_id=? ORDER BY created_at DESC');
        $stmt->execute([$user['school_id'],$user['user_id']??0]);
        $items = $stmt->fetchAll();
        json_response(['ok'=>true,'items'=>$items]);
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') {
        $b = get_json_body();
        if (!($b['subject']??'') || !($b['class_level']??'') || !($b['title']??'') || !($b['content']??'') || !($b['due_date']??'')) {
            json_response(['ok'=>false,'message'=>'Missing fields'],422);
        }
        $stmt=$pdo->prepare('INSERT INTO homework (school_id, teacher_id, subject, class_level, section, title, content, due_date) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$user['school_id'],$user['user_id'],$b['subject'],$b['class_level'],$b['section']??null,$b['title'],$b['content'],$b['due_date']]);
        json_response(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
}

if ($path === 'exams' || $path==='exam') {
    if ($_SERVER['REQUEST_METHOD']==='GET' && $path==='exams') {
        $stmt = $pdo->prepare('SELECT id, subject, class_level, section, title, duration, exam_date FROM exams WHERE school_id=? AND teacher_id=? ORDER BY exam_date DESC');
        $stmt->execute([$user['school_id'],$user['user_id']??0]);
        $items = $stmt->fetchAll();
        json_response(['ok'=>true,'items'=>$items]);
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') {
        $b = get_json_body();
        if (!($b['subject']??'') || !($b['class_level']??'') || !($b['title']??'') || !($b['duration']??'') || !($b['exam_date']??'')) {
            json_response(['ok'=>false,'message'=>'Missing fields'],422);
        }
        $stmt=$pdo->prepare('INSERT INTO exams (school_id, teacher_id, subject, class_level, section, title, duration, exam_date) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$user['school_id'],$user['user_id'],$b['subject'],$b['class_level'],$b['section']??null,$b['title'],intval($b['duration']),$b['exam_date']]);
        json_response(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
}

if ($path === 'students') {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $grade = $_GET['class'] ?? ($_GET['grade_level'] ?? '');
        $section = $_GET['section'] ?? '';
        $stmt = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS full_name, email FROM users WHERE school_id=? AND role='student' AND grade_level=? AND (section IS NULL OR section=?) ORDER BY first_name, last_name");
        $stmt->execute([$user['school_id'],$grade,$section]);
        $rows = $stmt->fetchAll();
        json_response(['ok'=>true,'data'=>$rows,'students'=>$rows]);
    }
}

if ($path === 'grade') {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $b = get_json_body();
        $email = $b['email'] ?? '';
        $subject = $b['subject'] ?? '';
        $term = $b['term'] ?? '';
        $month = intval($b['month'] ?? 0);
        $grade = floatval($b['grade'] ?? 0);
        if (!$email || !$subject || !$term || !$month) json_response(['ok'=>false,'message'=>'Missing'],422);
        $st=$pdo->prepare("SELECT id FROM users WHERE school_id=? AND role='student' AND email=? LIMIT 1");
        $st->execute([$user['school_id'],$email]);
        $stu=$st->fetch();
        if(!$stu) json_response(['ok'=>false,'message'=>'Student not found'],404);
        $ins=$pdo->prepare('INSERT INTO grades (school_id, student_id, subject, term, month, grade, teacher_id) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$user['school_id'],$stu['id'],$subject,$term,$month,$grade,$user['user_id']]);
        json_response(['ok'=>true]);
    }
}

if ($path === 'schedule') {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $semester = isset($_GET['semester']) ? intval($_GET['semester']) : null;
        $sql = 'SELECT semester, grade, section, day, period, subject FROM schedules WHERE school_id=? AND teacher_id=?';
        $args = [$user['school_id'],$user['user_id']];
        if ($semester) { $sql.=' AND semester=?'; $args[]=$semester; }
        $sql.=' ORDER BY semester, FIELD(day,\'Sat\',\'Sun\',\'Mon\',\'Tue\',\'Wed\',\'Thu\'), period';
        $stmt=$pdo->prepare($sql);
        $stmt->execute($args);
        $items=$stmt->fetchAll();
        json_response(['ok'=>true,'items'=>$items]);
    }
}

if ($path === 'schedule/print') {
    // Printable HTML (Sun–Thu, 7 periods) with teacher name header
    $stmt=$pdo->prepare("SELECT semester, grade, section, day, period, subject FROM schedules WHERE school_id=? AND teacher_id=? ORDER BY semester, FIELD(day,'Sun','Mon','Tue','Wed','Thu'), period");
    $stmt->execute([$user['school_id'],$user['user_id']]);
    $rows=$stmt->fetchAll();
    $bySem = [];
    foreach($rows as $r){ $bySem[$r['semester']][]=$r; }
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="teacher-schedule.html"');
    echo "<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='UTF-8'><title>جدول المعلم</title>".
         "<style>body{font-family:Tajawal,Segoe UI,Arial,sans-serif;padding:20px}h1{margin:0 0 8px}h2{margin:16px 0 8px}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{border:1px solid #ddd;padding:8px;text-align:center}th{background:#f8fafc}</style></head><body>";
    $teacherName = trim(($user['first_name']??'').' '.($user['last_name']??''));
    echo "<h1>جدول المعلم: ".$teacherName."</h1>";
    $dayCols=['Sun','Mon','Tue','Wed','Thu'];
    $dayTitle=['Sun'=>'الأحد','Mon'=>'الاثنين','Tue'=>'الثلاثاء','Wed'=>'الأربعاء','Thu'=>'الخميس'];
    if(!$bySem){ echo "<p>لا توجد بيانات للجدول.</p>"; }
    foreach($bySem as $sem=>$items){
        $grid=[]; for($p=1;$p<=7;$p++){ foreach($dayCols as $dc){ $grid[$p][$dc]=''; } }
        foreach($items as $it){ $p=intval($it['period']); $d=$it['day']; if(isset($grid[$p][$d])){ $grid[$p][$d] = ($it['subject']??'').($it['grade']?(' ('.$it['grade'].($it['section']?(' / '.$it['section']):'').')'):''); } }
        echo "<h2>الفصل الدراسي: ".$sem."</h2>";
        echo "<table><thead><tr><th>الحصة/اليوم</th>".
             implode('', array_map(function($k) use ($dayTitle){ return '<th>'.$dayTitle[$k].'</th>'; }, $dayCols)).
             "</tr></thead><tbody>";
        for($p=1;$p<=7;$p++){
            echo "<tr><td>الحصة ".$p."</td>";
            foreach($dayCols as $dc){ echo "<td>".htmlspecialchars($grid[$p][$dc]??'')."</td>"; }
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
    echo "</body></html>"; exit;
}

if ($path === 'id-card') {
    header('Content-Type: application/json');
    json_response(['ok'=>true,'message'=>'Use student/teacher data to render card on client.']);
}

json_response(['ok'=>false,'message'=>'Not found'],404);
