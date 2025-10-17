<?php
// Included by api.php after JWT verification
// Variables available: $pdo (PDO), $userId (string), $userRole (string), $action (string)

switch ($action) {
    case 'getUser': {
        $stmt = $pdo->prepare("SELECT id, email, type, first_name, last_name, avatar_url, details FROM users WHERE id=? AND type='teacher' LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        // classes taught by teacher with subject names
        $cls = $pdo->prepare("SELECT c.*, s.name AS subject_name FROM classes c JOIN subjects s ON s.id=c.subject_id WHERE c.teacher_id=?");
        $cls->execute([$userId]);
        $user['classes'] = $cls->fetchAll();
        // derive mainSubject from details JSON if present
        if (!empty($user['details'])) {
            $details = json_decode($user['details'], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($details['mainSubject'])) {
                $user['mainSubject'] = $details['mainSubject'];
            }
        }
        send_json(['ok'=>true,'data'=>$user]);
    }
    case 'classes': {
        $stmt = $pdo->prepare("SELECT c.*, s.name AS subject_name, COUNT(u.id) AS student_count
                               FROM classes c
                               JOIN subjects s ON s.id=c.subject_id
                               LEFT JOIN users u ON u.class_id=c.id AND u.type='student'
                               WHERE c.teacher_id=?
                               GROUP BY c.id");
        $stmt->execute([$userId]);
        send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    case 'assignments': {
        // list assignments for teacher's classes
        $stmt = $pdo->prepare("SELECT a.id, a.class_id, a.title, a.type, a.due_date, a.created_at, c.name AS className,
                                      (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id) AS submissionCount,
                                      (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id AND s.grade IS NOT NULL) AS gradedCount
                               FROM assignments a JOIN classes c ON c.id=a.class_id
                               WHERE c.teacher_id=? ORDER BY a.created_at DESC");
        $stmt->execute([$userId]);
        send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    case 'save_assignment': {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        if (empty($data['title']) || empty($data['classId']) || empty($data['type']) || empty($data['dueDate'])) {
            send_json(['ok'=>false,'message'=>'Missing fields'], 422);
        }
        if (empty($data['id'])) {
            $id = 'A'.time();
            $stmt = $pdo->prepare("INSERT INTO assignments (id, class_id, title, type, due_date) VALUES (?,?,?,?,?)");
            $stmt->execute([$id, $data['classId'], $data['title'], $data['type'], $data['dueDate']]);
        } else {
            $id = $data['id'];
            $stmt = $pdo->prepare("UPDATE assignments SET class_id=?, title=?, type=?, due_date=? WHERE id=?");
            $stmt->execute([$data['classId'], $data['title'], $data['type'], $data['dueDate'], $id]);
        }
        send_json(['ok'=>true,'id'=>$id]);
    }
    case 'delete_assignment': {
        $id = $_GET['id'] ?? '';
        if (!$id) send_json(['ok'=>false,'message'=>'Missing id'], 422);
        $stmt = $pdo->prepare('DELETE FROM assignments WHERE id=?');
        $stmt->execute([$id]);
        send_json(['ok'=>true]);
    }
    case 'students': {
        // students in classId
        $classId = $_GET['class'] ?? $_GET['classId'] ?? '';
        if (!$classId) send_json(['ok'=>false,'message'=>'Missing classId'], 422);
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, avatar_url, CONCAT(first_name,' ',last_name) AS full_name, email FROM users WHERE type='student' AND class_id=? ORDER BY first_name");
        $stmt->execute([$classId]);
        send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    case 'submissions': {
        // list submissions for a given assignment
        $assignmentId = $_GET['assignmentId'] ?? '';
        if (!$assignmentId) send_json(['ok'=>false,'message'=>'Missing assignmentId'], 422);
        $stmt = $pdo->prepare("SELECT u.id AS student_id, CONCAT(u.first_name,' ',u.last_name) AS full_name,
                                      s.status, s.grade
                               FROM users u
                               LEFT JOIN submissions s ON s.student_id=u.id AND s.assignment_id=?
                               WHERE u.type='student' AND u.class_id=(SELECT class_id FROM assignments WHERE id=? )
                               ORDER BY u.first_name");
        $stmt->execute([$assignmentId,$assignmentId]);
        send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    case 'save_grade':
    case 'grade': {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $studentEmail = $payload['email'] ?? '';
        $studentId = $payload['student_id'] ?? null;
        $grade = isset($payload['grade']) ? (int)$payload['grade'] : null;
        $assignmentId = $payload['assignmentId'] ?? null;
        if ((!$studentEmail && !$studentId) || $grade===null) send_json(['ok'=>false,'message'=>'Missing fields'], 422);
        // find student id if only email provided
        if (!$studentId) {
            $s = $pdo->prepare("SELECT id FROM users WHERE email=? AND type='student' LIMIT 1");
            $s->execute([$studentEmail]);
            $studentId = $s->fetch()['id'] ?? null;
        }
        $sid = $studentId;
        if (!$sid) send_json(['ok'=>false,'message'=>'Student not found'], 404);
        if (!$assignmentId) {
            // If not provided, use the latest assignment in the student's class
            $aidStmt = $pdo->prepare("SELECT a.id FROM assignments a WHERE a.class_id=(SELECT class_id FROM users WHERE id=?) ORDER BY created_at DESC LIMIT 1");
            $aidStmt->execute([$sid]);
            $assignmentId = $aidStmt->fetch()['id'] ?? null;
        }
        if (!$assignmentId) send_json(['ok'=>false,'message'=>'Assignment not found'], 404);
        // upsert submission
        $exists = $pdo->prepare('SELECT id FROM submissions WHERE student_id=? AND assignment_id=? LIMIT 1');
        $exists->execute([$sid,$assignmentId]);
        if ($row = $exists->fetch()) {
            $upd = $pdo->prepare('UPDATE submissions SET status=\'graded\', submitted_at=COALESCE(submitted_at, CURRENT_DATE), grade=? WHERE id=?');
            $upd->execute([$grade, $row['id']]);
        } else {
            $ins = $pdo->prepare('INSERT INTO submissions (student_id, assignment_id, status, submitted_at, grade) VALUES (?,?,\'graded\',CURRENT_DATE,?)');
            $ins->execute([$sid,$assignmentId,$grade]);
        }
        send_json(['ok'=>true]);
    }
    case 'announce': {
        if ($method !== 'POST') send_json(['ok'=>false,'message'=>'Method not allowed'], 405);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $content = trim($payload['content'] ?? '');
        if (!$content) send_json(['ok'=>false,'message'=>'Missing content'], 422);
        $stmt = $pdo->prepare('INSERT INTO announcements (teacher_id, content, date) VALUES (?,?, CURRENT_DATE)');
        $stmt->execute([$userId, $content]);
        send_json(['ok'=>true]);
    }
    case 'attendance': {
        if ($method === 'GET') {
            // list attendance for class/date
            $classId = $_GET['classId'] ?? '';
            $date = $_GET['date'] ?? date('Y-m-d');
            if (!$classId) send_json(['ok'=>false,'message'=>'Missing classId'], 422);
            $stmt = $pdo->prepare("SELECT u.id AS student_id, CONCAT(u.first_name,' ',u.last_name) AS full_name,
                                          COALESCE(a.status,'present') AS status
                                   FROM users u
                                   LEFT JOIN attendance a ON a.student_id=u.id AND a.date=?
                                   WHERE u.type='student' AND u.class_id=?
                                   ORDER BY u.first_name");
            $stmt->execute([$date,$classId]);
            send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $date = $payload['date'] ?? date('Y-m-d');
            $items = $payload['items'] ?? null; // bulk: [{student_id, status}]
            if (is_array($items)) {
                $pdo->beginTransaction();
                foreach ($items as $it) {
                    $sid = $it['student_id'] ?? null; $status = $it['status'] ?? null;
                    if (!$sid || !$status) continue;
                    $exists = $pdo->prepare('SELECT id FROM attendance WHERE student_id=? AND date=? LIMIT 1');
                    $exists->execute([$sid,$date]);
                    if ($row = $exists->fetch()) {
                        $upd = $pdo->prepare('UPDATE attendance SET status=? WHERE id=?');
                        $upd->execute([$status, $row['id']]);
                    } else {
                        $ins = $pdo->prepare('INSERT INTO attendance (student_id, class_id, date, status) VALUES (?, (SELECT class_id FROM users WHERE id=?), ?, ?)');
                        $ins->execute([$sid,$sid,$date,$status]);
                    }
                }
                $pdo->commit();
                send_json(['ok'=>true]);
            } else {
                // single record upsert
                $sid = $payload['student_id'] ?? null; $status = $payload['status'] ?? null;
                if (!$sid || !$status) send_json(['ok'=>false,'message'=>'Missing fields'], 422);
                $exists = $pdo->prepare('SELECT id FROM attendance WHERE student_id=? AND date=? LIMIT 1');
                $exists->execute([$sid,$date]);
                if ($row = $exists->fetch()) {
                    $upd = $pdo->prepare('UPDATE attendance SET status=? WHERE id=?');
                    $upd->execute([$status, $row['id']]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO attendance (student_id, class_id, date, status) VALUES (?, (SELECT class_id FROM users WHERE id=?), ?, ?)');
                    $ins->execute([$sid,$sid,$date,$status]);
                }
                send_json(['ok'=>true]);
            }
        }
        send_json(['ok'=>false,'message'=>'Method not allowed'], 405);
    }
    case 'materials': {
        if ($method === 'GET') {
            // List materials by teacher
            $stmt = $pdo->prepare("SELECT m.id, m.title, m.type, m.url, m.date, c.name AS className
                                   FROM materials m JOIN classes c ON c.id=m.class_id
                                   WHERE m.added_by=? ORDER BY m.date DESC, m.id DESC");
            $stmt->execute([$userId]);
            send_json(['ok'=>true,'data'=>$stmt->fetchAll()]);
        } elseif ($method === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            if (empty($payload['title']) || empty($payload['type']) || empty($payload['classId']) || empty($payload['url'])) {
                send_json(['ok'=>false,'message'=>'Missing fields'], 422);
            }
            $id = 'M'.time();
            $stmt = $pdo->prepare('INSERT INTO materials (id, class_id, title, type, url, added_by, date) VALUES (?,?,?,?,?,?,CURRENT_DATE)');
            $stmt->execute([$id, $payload['classId'], $payload['title'], $payload['type'], $payload['url'], $userId]);
            send_json(['ok'=>true,'id'=>$id]);
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? '';
            if (!$id) send_json(['ok'=>false,'message'=>'Missing id'], 422);
            $stmt = $pdo->prepare('DELETE FROM materials WHERE id=? AND added_by=?');
            $stmt->execute([$id,$userId]);
            send_json(['ok'=>true]);
        }
        send_json(['ok'=>false,'message'=>'Method not allowed'], 405);
    }
    case 'schedule': {
        // teacher's schedule from classes they teach
        $stmt = $pdo->prepare("SELECT s.day, s.period, sub.name AS subject, c.name AS class_name
                               FROM classes c
                               JOIN schedule s ON s.class_id=c.id
                               JOIN subjects sub ON sub.id=s.subject_id
                               WHERE c.teacher_id=?
                               ORDER BY FIELD(s.day,'الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس'), s.period");
        $stmt->execute([$userId]);
        send_json(['ok'=>true,'items'=>$stmt->fetchAll()]);
    }
    case 'attendance_report': {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $sql = "SELECT a.date AS att_date, CONCAT(u.first_name,' ',u.last_name) AS student, c.name AS class_name, a.status
                FROM attendance a
                JOIN users u ON u.id = a.student_id AND u.type='student'
                JOIN classes c ON c.id = a.class_id
                WHERE c.teacher_id = ?";
        $args = [$userId];
        if ($from) { $sql .= " AND a.date >= ?"; $args[] = $from; }
        if ($to) { $sql .= " AND a.date <= ?"; $args[] = $to; }
        $sql .= " ORDER BY a.date DESC, student";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['date','student','class','status']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [$row['att_date'], $row['student'], $row['class_name'], $row['status']]);
        }
        fclose($out);
        exit;
    }
    default:
        send_json(['ok'=>false,'message'=>'Teacher action not found'], 404);
}
