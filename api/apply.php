<?php
// API endpoint to handle "Apply Through Us" submissions and send them to email.
// Accepts multipart/form-data or application/json. Returns JSON.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

$METHOD = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($METHOD === 'OPTIONS') { json_response(['ok'=>true]); }
if ($METHOD !== 'POST') { abort_json(405, 'method-not-allowed'); }

// Small helper to fetch param from POST/JSON safely
function param(string $key, $default = ''): string {
    if (isset($_POST[$key])) return trim((string)$_POST[$key]);
    static $json = null;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) { $json = []; }
    }
    if (isset($json[$key])) return trim((string)$json[$key]);
    return (string)$default;
}

$name = param('name');
$email = param('email');
$phone = param('phone');
$country = param('country');
$program = param('program');
$message = param('message');
$source = param('source'); // page source (optional)

// Basic validation
$errors = [];
if ($name === '') $errors['name'] = 'required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'invalid';
if ($message === '') $errors['message'] = 'required';
if (!empty($errors)) {
    json_response(['ok'=>false,'error_code'=>'validation-error','errors'=>$errors], 422);
}

// Compose HTML email
$to = 'a_abbas_ia243@shokan.edu.kz';
$subject = 'طلب تقديم عبر مدارس المخا - ' . ($program !== '' ? $program : 'Apply Form');

$body = '<html><body style="font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right">'
    . '<h2>طلب تقديم عبر مدارس المخا</h2>'
    . '<p><strong>الاسم:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>البريد:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>الهاتف:</strong> ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>الدولة:</strong> ' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>البرنامج/المنحة:</strong> ' . htmlspecialchars($program, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>الرسالة:</strong><br>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
    . '<hr>'
    . '<p style="color:#6b7280"><small>المصدر: ' . htmlspecialchars($source, ENT_QUOTES, 'UTF-8') . '</small></p>'
    . '</body></html>';

$sent = send_app_mail($to, $subject, $body);
if (!$sent) {
    abort_json(500, 'email-send-failed');
}

json_response(['ok'=>true,'message'=>'submitted']);
