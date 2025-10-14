<?php
// Simple mail sender wrapper. For production configure SMTP via PHPMailer.
// Currently uses mail() if available; adjust headers/encoding for Arabic content.

function send_app_mail(string $to, string $subject, string $body): bool {
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: Mocha Schools <no-reply@mocha.local>';
    $headers[] = 'Reply-To: no-reply@mocha.local';
    $headersStr = implode("\r\n", $headers);
    // NOTE: For Windows + sendmail config maybe needed in php.ini.
    return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $headersStr);
}
