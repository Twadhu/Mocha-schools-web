<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';

$pdo = db();
$user = auth_require_token($pdo, []); // any logged-in user

function ensure_manager_or_teacher($u){ if(!in_array($u['role'],['teacher','manager'],true)) { json_response(['ok'=>false,'message'=>'Forbidden'],403);} }

if ($_SERVER['REQUEST_METHOD']==='GET') {
    $semester = intval($_GET['semester'] ?? 0);
    $grade = $_GET['grade'] ?? '';
    $section = $_GET['section'] ?? '';
    if (!$semester || !$grade) json_response(['ok'=>false,'message'=>'Missing'],422);
    $sql = 'SELECT s.day, s.period, s.subject, s.teacher_id, s.section, CONCAT(u.first_name, " ", u.last_name) AS teacher_name FROM schedules s LEFT JOIN users u ON u.id=s.teacher_id WHERE s.school_id=? AND s.semester=? AND s.grade=?';
    $args = [$user['school_id'],$semester,$grade];
    if ($section!=='') { $sql.=' AND (section IS NULL OR section=?)'; $args[]=$section; }
    $sql.=' ORDER BY FIELD(day,\'Sat\',\'Sun\',\'Mon\',\'Tue\',\'Wed\',\'Thu\'), period';
    $stmt=$pdo->prepare($sql); $stmt->execute($args);
    json_response(['ok'=>true,'items'=>$stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD']==='PUT') {
    // Replace schedule for a class/section/semester
    ensure_manager_or_teacher($user);
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $semester = intval($b['semester'] ?? 0);
    $grade = $b['grade'] ?? '';
    $section = $b['section'] ?? null;
    $entries = $b['entries'] ?? [];
    if (!$semester || !$grade || !is_array($entries)) json_response(['ok'=>false,'message'=>'Invalid'],422);
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM schedules WHERE school_id=? AND semester=? AND grade=? AND ((? IS NULL AND section IS NULL) OR section=?)');
        $del->execute([$user['school_id'],$semester,$grade,$section,$section]);
        if (!empty($entries)) {
            $ins = $pdo->prepare('INSERT INTO schedules (school_id, semester, grade, section, day, period, subject, teacher_id) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($entries as $e) {
                $ins->execute([$user['school_id'],$semester,$grade,$section,$e['day'],$e['period'],$e['subject'], $e['teacher_id']??null]);
            }
        }
        $pdo->commit();
        json_response(['ok'=>true]);
    } catch (Exception $ex) {
        $pdo->rollBack();
        json_response(['ok'=>false,'message'=>'Failed'],500);
    }
}

json_response(['ok'=>false,'message'=>'Method not allowed'],405);
