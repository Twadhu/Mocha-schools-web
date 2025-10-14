<?php
require_once __DIR__ . '/config.php';
// Note: auth is optional here to keep compatibility with existing manager panel.
// If Authorization contains a valid app token, we could extend to enforce later.

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// --- Helpers ---
function project_root_path(): string {
    return dirname(__DIR__); // .../Schools-sites
}
function project_web_base(): string {
    $doc = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $root = str_replace('\\','/', project_root_path());
    if ($doc && substr($root, 0, strlen($doc)) === $doc) {
        $rel = substr($root, strlen($doc));
        return $rel ? $rel : '/';
    }
    // Fallback for XAMPP default
    return '/Schools-sites';
}

function map_school_key(PDO $pdo, $schoolIdOrKey): array {
    // Returns ['id'=>int, 'slug'=>string]
    $map = [
        's1' => ['name' => 'مدرسة الفقيد محمد عبدالله السراجي', 'slug' => 'Al-faqaid'],
        's2' => ['name' => 'مدرسة الشهيد اللقية', 'slug' => 'Aluqaya'],
        's3' => ['name' => 'مدرسة النور بالثوباني', 'slug' => 'Alnoor'],
    ];
    $slug = null; $name = null; $sid = null;
    if (is_string($schoolIdOrKey) && isset($map[$schoolIdOrKey])) {
        $slug = $map[$schoolIdOrKey]['slug'];
        $name = $map[$schoolIdOrKey]['name'];
    } elseif (is_numeric($schoolIdOrKey) && (int)$schoolIdOrKey > 0) {
        $sid = (int)$schoolIdOrKey;
        // Try derive slug by name
        $stmt = $pdo->prepare('SELECT id, name FROM schools WHERE id=?');
        $stmt->execute([$sid]);
        if ($row = $stmt->fetch()) {
            $n = $row['name'];
            foreach ($map as $mk => $m) { if ($m['name'] === $n) { $slug = $m['slug']; break; } }
        }
    }
    if ($name !== null) {
        // Ensure exists and get numeric id
        $stmt = $pdo->prepare('SELECT id FROM schools WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) { $sid = (int)$row['id']; }
        else {
            $ins = $pdo->prepare('INSERT INTO schools (name) VALUES (?)');
            $ins->execute([$name]);
            $sid = (int)$pdo->lastInsertId();
        }
    }
    if (!$sid || !$slug) { abort_json(422, 'Unknown school'); }
    return ['id'=>$sid, 'slug'=>$slug];
}

function normalize_activity_rows(array $rows): array {
    $base = project_web_base();
    foreach ($rows as &$r) {
        // Add UI-friendly aliases
        $url = $r['media_url'] ?? null;
        if ($url && substr($url, 0, 1) === '/') {
            $r['file_url'] = $url; // absolute URL from web root
        } else {
            $r['file_url'] = $url ? ($base . '/' . ltrim($url,'/')) : null;
        }
        $r['download_url'] = $r['file_url'];
    }
    return $rows;
}

function record_activity_event(string $type, array $payload): void {
    // Writes to a shared file consumed by SSE endpoint
    $file = __DIR__ . '/stream/_last_event.json';
    $payload['type'] = $type;
    $payload['ts'] = time();
    @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE));
}

if ($method === 'GET') {
    $schoolParam = $_GET['school_id'] ?? '';
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 24;
    if ($schoolParam === '' || $schoolParam === null) { json_response(['ok'=>false,'message'=>'school_id required'],422); }
    try {
        $m = map_school_key($pdo, $schoolParam);
        $stmt = $pdo->prepare('SELECT id, school_id, title, description, media_url, media_type, created_at FROM activities WHERE school_id=? ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1,$m['id'],PDO::PARAM_INT);
        $stmt->bindValue(2,$limit,PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        json_response(['ok'=>true,'activities'=> normalize_activity_rows($rows)]);
    } catch (Throwable $e) {
        json_response(['ok'=>false,'message'=>'DB error'],500);
    }
}

if ($method === 'POST') {
    // Two modes: JSON (legacy) and multipart (file upload from manager)
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'multipart/form-data') !== false) {
        // Upload with fields: title, description, file, school_key (s1/s2/s3) or school_id
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $schoolParam = $_POST['school_key'] ?? ($_POST['school_id'] ?? '');
        if ($title === '' || $schoolParam === '' || !isset($_FILES['file'])) {
            json_response(['ok'=>false,'message'=>'Missing fields'],422);
        }
        try {
            $m = map_school_key($pdo, $schoolParam);
            // Validate file
            $f = $_FILES['file'];
            if (!is_array($f) || ($f['error']??UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { json_response(['ok'=>false,'message'=>'Upload failed'],400); }
            $allowed = [
                'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp',
                'video/mp4'=>'mp4', 'video/webm'=>'webm'
            ];
            $mime = function_exists('mime_content_type') ? @mime_content_type($f['tmp_name']) : '';
            if (!$mime) { $mime = $f['type'] ?? ''; }
            if (!isset($allowed[$mime])) { json_response(['ok'=>false,'message'=>'Unsupported file type'],415); }
            $ext = $allowed[$mime];
            $size = (int)$f['size'];
            if ($size > 100*1024*1024) { json_response(['ok'=>false,'message'=>'File too large (max 100MB)'],413); }

            // Build target path: /front-mocha-schools-website/Schools-active/<slug>/uploads/
            $root = project_root_path();
            $slug = $m['slug'];
            $uploadDir = $root . DIRECTORY_SEPARATOR . 'front-mocha-schools-website' . DIRECTORY_SEPARATOR . 'Schools-active' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            $baseName = preg_replace('/[^A-Za-z0-9_-]+/','-', pathinfo($f['name'], PATHINFO_FILENAME));
            $rand = bin2hex(random_bytes(4));
            $fileName = date('Ymd-His') . '-' . ($baseName?:'file') . '-' . $rand . '.' . $ext;
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
            if (!move_uploaded_file($f['tmp_name'], $destPath)) { json_response(['ok'=>false,'message'=>'Failed to save file'],500); }

            $mediaType = str_starts_with($mime,'video/') ? 'video' : 'image';
            // Web URL (absolute from web root)
            $webBase = project_web_base();
            $relUrl = '/front-mocha-schools-website/Schools-active/' . $slug . '/uploads/' . $fileName;
            $mediaUrl = ($webBase === '/Schools-sites' || substr($webBase, -13) === '/Schools-sites') ? ('/Schools-sites' . $relUrl) : ($webBase . $relUrl);

            // Insert
            $stmt = $pdo->prepare('INSERT INTO activities (school_id,title,description,media_url,media_type) VALUES (?,?,?,?,?)');
            $stmt->execute([$m['id'],$title,$desc?:null,$mediaUrl,$mediaType]);
            $newId = (int)$pdo->lastInsertId();

            // notify SSE
            record_activity_event('activity:new', [
                'id'=>$newId,
                'school_id'=>$m['id'],
                'school_key'=>$schoolParam,
                'title'=>$title,
                'media_type'=>$mediaType
            ]);

            json_response(['ok'=>true,'id'=>$newId,'file_url'=>$mediaUrl,'download_url'=>$mediaUrl]);
        } catch (Throwable $e) {
            json_response(['ok'=>false,'message'=>'Server error'],500);
        }
    } else {
        // JSON flow (kept for compatibility with earlier code; not used by manager page)
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $required = ['school_id','title'];
        foreach ($required as $r) { if (empty($input[$r])) json_response(['ok'=>false,'message'=>'Missing field: '.$r],422); }
        $m = map_school_key($pdo, $input['school_id']);
        $title = trim((string)$input['title']);
        $desc = isset($input['description']) ? trim((string)$input['description']) : null;
        $media = isset($input['media_url']) ? trim((string)$input['media_url']) : null;
        $type = isset($input['media_type']) && in_array($input['media_type'], ['image','video','activity','news'], true) ? $input['media_type'] : 'activity';
        try {
            $stmt = $pdo->prepare('INSERT INTO activities (school_id,title,description,media_url,media_type) VALUES (?,?,?,?,?)');
            $stmt->execute([$m['id'],$title,$desc?:null,$media?:null,$type]);
            json_response(['ok'=>true,'id'=>$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            json_response(['ok'=>false,'message'=>'DB error'],500);
        }
    }
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { json_response(['ok'=>false,'message'=>'id required'],422); }
    try {
        $stmt = $pdo->prepare('SELECT media_url FROM activities WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { json_response(['ok'=>false,'message'=>'Not found'],404); }
    $pdo->prepare('DELETE FROM activities WHERE id=?')->execute([$id]);
        // Try delete file if within uploads folder
        $url = $row['media_url'] ?? '';
        if ($url) {
            $root = project_root_path();
            // Expecting url like /Schools-sites/front-mocha-schools-website/Schools-active/<slug>/uploads/<file>
            $prefix = '/Schools-sites/front-mocha-schools-website/Schools-active/';
            if (substr($url, 0, strlen($prefix)) === $prefix) {
                $rel = substr($url, strlen('/Schools-sites/')); // strip '/Schools-sites/'
                $fsPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($fsPath)) { @unlink($fsPath); }
            }
        }
    // notify SSE
    record_activity_event('activity:deleted', ['id'=>$id]);
    json_response(['ok'=>true]);
    } catch (Throwable $e) {
        json_response(['ok'=>false,'message'=>'DB error'],500);
    }
}

json_response(['ok'=>false,'message'=>'Method not allowed'],405);
