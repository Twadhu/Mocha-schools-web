<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';

$pdo = db();
$user = auth_require_token($pdo, ['teacher']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $sid = intval($b['student_id'] ?? 0);
    $status = $b['status'] ?? '';
    if (!$sid || !in_array($status,['present','absent','late'],true)) json_response(['ok'=>false,'message'=>'Invalid'],422);
    $st=$pdo->prepare('SELECT id, school_id FROM users WHERE id=? AND role=\'student\' AND school_id=?');
    $st->execute([$sid, $user['school_id']]);
    $stu = $st->fetch();
    if(!$stu) json_response(['ok'=>false,'message'=>'Student not found'],404);
    $stmt=$pdo->prepare('INSERT INTO attendance (school_id, student_id, status, att_date, teacher_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), teacher_id=VALUES(teacher_id)');
    $stmt->execute([$user['school_id'],$sid,$status,date('Y-m-d'),$user['user_id']]);
    json_response(['ok'=>true]);
}

if ($_SERVER['REQUEST_METHOD']==='GET') {
    $from = $_GET['from'] ?? null; $to = $_GET['to'] ?? null;
    $sql = 'SELECT a.att_date, u.first_name, u.last_name, a.status FROM attendance a JOIN users u ON u.id=a.student_id WHERE a.school_id=? AND a.teacher_id=?';
    $args = [$user['school_id'],$user['user_id']];
    if ($from) { $sql.=' AND a.att_date>=?'; $args[]=$from; }
    if ($to) { $sql.=' AND a.att_date<=?'; $args[]=$to; }
    $sql.=' ORDER BY a.att_date DESC';
    $stmt=$pdo->prepare($sql); $stmt->execute($args);
    json_response(['ok'=>true,'items'=>$stmt->fetchAll()]);
}

json_response(['ok'=>false,'message'=>'Method not allowed'],405);
