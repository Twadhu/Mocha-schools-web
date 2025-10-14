<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
header('Content-Type: application/json; charset=utf-8');

$schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;

// Basic counts guarded by school id if provided (>0)
try {
    $where = $schoolId > 0 ? ' WHERE school_id='.$schoolId.' ' : ' ';
    $students = (int)$pdo->query('SELECT COUNT(*) FROM users '.$where.'AND role="student"')->fetchColumn();
    $teachers = (int)$pdo->query('SELECT COUNT(*) FROM users '.$where.'AND role="teacher"')->fetchColumn();
    $activities = (int)$pdo->query('SELECT COUNT(*) FROM activities'.($schoolId>0? ' WHERE school_id='.$schoolId : ''))->fetchColumn();
    $pending = (int)$pdo->query('SELECT COUNT(*) FROM account_requests'.($schoolId>0? ' WHERE school_id='.$schoolId.' AND status="pending"':' WHERE status="pending"'))->fetchColumn();
    echo json_encode(['ok'=>true,'students'=>$students,'teachers'=>$teachers,'activities'=>$activities,'pending_requests'=>$pending]);
} catch(Throwable $e){
    echo json_encode(['ok'=>false,'message'=>'DB error']);
}