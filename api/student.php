<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';

$pdo = db();
$user = auth_require_token($pdo, ['student']);

$path = $_GET['path'] ?? ($_SERVER['PATH_INFO'] ?? '');
$path = trim($path,'/');

if ($path === 'grades') {
    if ($_SERVER['REQUEST_METHOD']==='GET'){
        $term = $_GET['term'] ?? null;
        $sql = 'SELECT subject, month, grade, term, created_at FROM grades WHERE school_id=? AND student_id=?';
        $args = [$user['school_id'], $user['user_id']];
        if ($term){ $sql.=' AND term=?'; $args[]=$term; }
        $sql.=' ORDER BY created_at DESC';
        $stmt=$pdo->prepare($sql); $stmt->execute($args);
        $rows = array_map(function($r){ $r['subject_name']=$r['subject']; return $r; }, $stmt->fetchAll());
        json_response(['ok'=>true,'grades'=>$rows]);
    }
}

if ($path === 'attendance/me') {
    if ($_SERVER['REQUEST_METHOD']==='GET'){
        $from = $_GET['from'] ?? null; $to=$_GET['to'] ?? null;
        $sql = 'SELECT att_date AS date, status FROM attendance WHERE school_id=? AND student_id=?';
        $args = [$user['school_id'],$user['user_id']];
        if($from){ $sql.=' AND att_date>=?'; $args[]=$from; }
        if($to){ $sql.=' AND att_date<=?'; $args[]=$to; }
        $sql.=' ORDER BY att_date DESC';
        $stmt=$pdo->prepare($sql); $stmt->execute($args);
        $rows=$stmt->fetchAll();
        $summary=['present'=>0,'absent'=>0,'late'=>0]; foreach($rows as $r){ $summary[$r['status']] = ($summary[$r['status']]??0)+1; }
        json_response(['ok'=>true,'attendance'=>$rows,'summary'=>$summary]);
    }
}

json_response(['ok'=>false,'message'=>'Not found'],404);
