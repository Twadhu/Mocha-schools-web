<?php
// Included by api.php after JWT verification
// Variables available: $pdo (PDO), $userId (string), $userRole (string), $action (string)

switch ($action) {
    case 'getUser': {
        $stmt = $pdo->prepare("SELECT u.id, u.email, u.type, u.first_name, u.last_name, u.avatar_url, u.class_id, u.details, c.name AS class_name
                               FROM users u
                               LEFT JOIN classes c ON c.id = u.class_id
                               LEFT JOIN subjects s ON s.id = c.subject_id
                               WHERE u.id = ? AND u.type='student' LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        send_json(['ok'=>true,'data'=>$user?:null]);
    }
    case 'submit_assignment': {
        if ($method !== 'POST') send_json(['ok'=>false,'message'=>'Method not allowed'], 405);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $assignmentId = $payload['assignmentId'] ?? null;
        if (!$assignmentId) send_json(['ok'=>false,'message'=>'Missing assignmentId'], 422);
        // upsert submission as 'submitted'
        $exists = $pdo->prepare('SELECT id FROM submissions WHERE student_id=? AND assignment_id=? LIMIT 1');
        $exists->execute([$userId,$assignmentId]);
        if ($row = $exists->fetch()) {
            $upd = $pdo->prepare("UPDATE submissions SET status='submitted', submitted_at=COALESCE(submitted_at, CURRENT_DATE) WHERE id=?");
            $upd->execute([$row['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO submissions (student_id, assignment_id, status, submitted_at) VALUES (?,?, 'submitted', CURRENT_DATE)");
            $ins->execute([$userId,$assignmentId]);
        }
        send_json(['ok'=>true]);
    }
    case 'assignments': {
        // List assignments for student's class with student's submission status/grade
        $cls = $pdo->prepare('SELECT class_id FROM users WHERE id=? LIMIT 1');
        $cls->execute([$userId]);
        $classId = $cls->fetch()['class_id'] ?? null;
        if (!$classId) { send_json(['ok'=>true,'data'=>[]]); }
        $stmt = $pdo->prepare("SELECT a.id, a.title, a.type, a.due_date, sub.name AS subject_name,
                                      s.status AS submission_status, s.grade AS submission_grade
                               FROM assignments a
                               JOIN classes c ON c.id = a.class_id
                               JOIN subjects sub ON sub.id = c.subject_id
                               LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
                               WHERE a.class_id=?
                               ORDER BY a.due_date ASC, a.created_at DESC");
        $stmt->execute([$userId, $classId]);
        $rows = $stmt->fetchAll();
        $data = array_map(function($r){
            return [
                'id' => $r['id'],
                'title' => $r['title'],
                'type' => $r['type'],
                'dueDate' => $r['due_date'],
                'subject' => $r['subject_name'],
                'submission' => [
                    'status' => $r['submission_status'],
                    'grade' => $r['submission_grade'] !== null ? (int)$r['submission_grade'] : null,
                ],
            ];
        }, $rows);
        send_json(['ok'=>true,'data'=>$data]);
    }
    case 'dashboard': {
        // attendance percentage over last 30 days
        $attStmt = $pdo->prepare("SELECT status FROM attendance WHERE student_id=? AND date >= (CURRENT_DATE - INTERVAL 30 DAY)");
        $attStmt->execute([$userId]);
        $rows = $attStmt->fetchAll();
        $total = count($rows);
        $present = $rows ? count(array_filter($rows, fn($r)=>$r['status']==='present')) : 0;
        $attendancePercentage = $total>0 ? round($present*100/$total) : 0;

        // GPA proxy: average of graded submissions (out of 100)
        $gStmt = $pdo->prepare("SELECT AVG(grade) AS avg_grade FROM submissions WHERE student_id=? AND grade IS NOT NULL");
        $gStmt->execute([$userId]);
        $gpaRow = $gStmt->fetch();
        $gpa = $gpaRow && $gpaRow['avg_grade'] !== null ? round((float)$gpaRow['avg_grade']/20, 2) : null; // map 100 => 5.0

        // registered courses: distinct subjects for student's class in schedule
        $cls = $pdo->prepare('SELECT class_id FROM users WHERE id=? LIMIT 1');
        $cls->execute([$userId]);
        $classId = $cls->fetch()['class_id'] ?? null;
        $rc = 0;
        if ($classId) {
            $cStmt = $pdo->prepare("SELECT COUNT(DISTINCT subject_id) AS c FROM schedule WHERE class_id=?");
            $cStmt->execute([$classId]);
            $rc = (int)($cStmt->fetch()['c'] ?? 0);
        }

        // announcements latest 5
        $an = $pdo->query("SELECT id, teacher_id, content, date FROM announcements ORDER BY date DESC, id DESC LIMIT 5")->fetchAll();

        send_json(['ok'=>true,'data'=>[
            'attendancePercentage'=>$attendancePercentage,
            'gpa'=>$gpa,
            'registeredCourses'=>$rc,
            'outstandingFees'=>0,
            'latestAnnouncements'=>$an,
        ]]);
    }
    case 'schedule': {
        $cls = $pdo->prepare('SELECT class_id FROM users WHERE id=? LIMIT 1');
        $cls->execute([$userId]);
        $classId = $cls->fetch()['class_id'] ?? null;
        if (!$classId) { send_json(['ok'=>true,'data'=>new stdClass()]); }
        $stmt = $pdo->prepare("SELECT s.day, s.period, sub.name AS subject
                               FROM schedule s JOIN subjects sub ON sub.id = s.subject_id
                               WHERE s.class_id=? ORDER BY FIELD(s.day,'الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس'), s.period");
        $stmt->execute([$classId]);
        $byDay = [];
        foreach ($stmt->fetchAll() as $r) { $byDay[$r['day']][] = ['period'=>(int)$r['period'], 'subject'=>$r['subject']]; }
        send_json(['ok'=>true,'data'=>$byDay]);
    }
    case 'attendance': {
        $stmt = $pdo->prepare("SELECT date, 
              CASE status WHEN 'present' THEN 'حاضر' WHEN 'late' THEN 'متأخر' ELSE 'غياب' END AS status,
              DATE_FORMAT(date, '%W') AS weekday
            FROM attendance WHERE student_id=? ORDER BY date DESC LIMIT 30");
        $stmt->execute([$userId]);
        $rows = array_map(function($r){
            // Map English weekday to Arabic if needed (MySQL locale may vary)
            $map=['Sunday'=>'الأحد','Monday'=>'الاثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
            $day = $map[$r['weekday']] ?? $r['weekday'];
            return ['date'=>$r['date'], 'day'=>$day, 'status'=>$r['status']];
        }, $stmt->fetchAll());
        send_json(['ok'=>true,'data'=>$rows]);
    }
    case 'grades': {
        // Return per-assignment grades with subject and assignment title
        $stmt = $pdo->prepare("SELECT sub.name AS subject_name, a.title AS assignment_title, s.grade
                               FROM assignments a
                               JOIN classes c ON c.id = a.class_id
                               JOIN subjects sub ON sub.id = c.subject_id
                               LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id=?
                               WHERE a.class_id = (SELECT class_id FROM users WHERE id=? )
                               ORDER BY a.created_at DESC LIMIT 20");
        $stmt->execute([$userId,$userId]);
        $rows = $stmt->fetchAll();
        $data = array_map(fn($r)=>[
            'subject'=>$r['subject_name'],
            'assignment'=>['title'=>$r['assignment_title']],
            'grade'=>$r['grade'] !== null ? (int)$r['grade'] : null,
        ], $rows);
        send_json(['ok'=>true,'data'=>$data]);
    }
    case 'announcements': {
        $an = $pdo->query("SELECT id, content, date FROM announcements ORDER BY date DESC, id DESC LIMIT 20")->fetchAll();
        // normalize keys with expected shape
        $data = array_map(fn($a)=>['id'=>(int)$a['id'],'title'=>mb_substr($a['content'],0,40),'date'=>$a['date'],'content'=>$a['content']], $an);
        send_json(['ok'=>true,'data'=>$data]);
    }
    case 'materials': {
        // materials for student's class
        $cls = $pdo->prepare('SELECT class_id FROM users WHERE id=? LIMIT 1');
        $cls->execute([$userId]);
        $classId = $cls->fetch()['class_id'] ?? null;
        if (!$classId) { send_json(['ok'=>true,'data'=>[]]); }
        $stmt = $pdo->prepare("SELECT id, title, type, url, date FROM materials WHERE class_id=? ORDER BY date DESC, id DESC");
        $stmt->execute([$classId]);
        send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    case 'events': {
        $ev = $pdo->query("SELECT id, title, date, type FROM events ORDER BY date ASC LIMIT 50")->fetchAll();
        send_json(['ok'=>true,'data'=>$ev]);
    }
    default:
        send_json(['ok'=>false,'message'=>'Student action not found'], 404);
}
