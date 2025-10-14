<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../auth_helpers.php';

$pdo = db();
$user = auth_require_token($pdo, ['teacher']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance.csv"');
$out=fopen('php://output','w');
fputcsv($out,['date','student','status']);
$from = $_GET['from'] ?? null; $to=$_GET['to'] ?? null;
$sql = "SELECT a.att_date, CONCAT(u.first_name,' ',u.last_name) AS student, a.status FROM attendance a JOIN users u ON u.id=a.student_id WHERE a.school_id=? AND a.teacher_id=?";
$args=[$user['school_id'],$user['user_id']];
if($from){ $sql.=' AND a.att_date>=?'; $args[]=$from; }
if($to){ $sql.=' AND a.att_date<=?'; $args[]=$to; }
$sql.=' ORDER BY a.att_date DESC';
$stmt=$pdo->prepare($sql); $stmt->execute($args);
while($row=$stmt->fetch(PDO::FETCH_NUM)) fputcsv($out,$row);
fclose($out);
exit;
