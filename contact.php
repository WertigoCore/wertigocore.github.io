<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = 'https://nova-design.cz';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$config = require __DIR__ . '/config.local.php';
$recipient = 'novak@nova-design.cz';
$logFile = __DIR__ . '/contact-error.log';

function logError(string $msg, array $context = []): void {
    global $logFile;
    $line = sprintf(
        "[%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $msg,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );
    error_log($line, 3, $logFile);
}

function fail(string $error, int $code = 400): never {
    logError('request_failed', ['error' => $error, 'code' => $code]);
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

set_exception_handler(function (Throwable $e): void {
    logError('uncaught_exception', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal_error']);
    exit;
});

/**
 * Minimal SMTP client (AUTH LOGIN, implicit TLS). Returns [success, error].
 */
function smtpSend(array $cfg, string $fromEmail, string $toEmail, string $subject, string $body, string $replyTo): array {
    $sock = @stream_socket_client(
        "ssl://{$cfg['smtp_host']}:{$cfg['smtp_port']}",
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT
    );
    if (!$sock) {
        return [false, "connect_failed: {$errstr} ({$errno})"];
    }
    stream_set_timeout($sock, 15);

    $readResponse = function () use ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $expect = function (string $resp, array $codes) use (&$err): bool {
        $code = (int)substr($resp, 0, 3);
        return in_array($code, $codes, true);
    };
    $cmd = function (string $command) use ($sock): void {
        fwrite($sock, $command . "\r\n");
    };

    $resp = $readResponse();
    if (!$expect($resp, [220])) { fclose($sock); return [false, "greeting_failed: {$resp}"]; }

    $cmd('EHLO nova-design.cz');
    $resp = $readResponse();
    if (!$expect($resp, [250])) { fclose($sock); return [false, "ehlo_failed: {$resp}"]; }

    $cmd('AUTH LOGIN');
    $resp = $readResponse();
    if (!$expect($resp, [334])) { fclose($sock); return [false, "auth_start_failed: {$resp}"]; }

    $cmd(base64_encode($cfg['smtp_user']));
    $resp = $readResponse();
    if (!$expect($resp, [334])) { fclose($sock); return [false, "auth_user_failed: {$resp}"]; }

    $cmd(base64_encode($cfg['smtp_pass']));
    $resp = $readResponse();
    if (!$expect($resp, [235])) { fclose($sock); return [false, "auth_pass_failed: {$resp}"]; }

    $cmd("MAIL FROM:<{$fromEmail}>");
    $resp = $readResponse();
    if (!$expect($resp, [250])) { fclose($sock); return [false, "mail_from_failed: {$resp}"]; }

    $cmd("RCPT TO:<{$toEmail}>");
    $resp = $readResponse();
    if (!$expect($resp, [250, 251])) { fclose($sock); return [false, "rcpt_to_failed: {$resp}"]; }

    $cmd('DATA');
    $resp = $readResponse();
    if (!$expect($resp, [354])) { fclose($sock); return [false, "data_start_failed: {$resp}"]; }

    $headers = [
        'From: ' . $fromEmail,
        'To: ' . $toEmail,
        'Reply-To: ' . $replyTo,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Date: ' . date('r'),
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\r\n.", "\r\n..", $body) . "\r\n.";
    $cmd($payload);
    $resp = $readResponse();
    if (!$expect($resp, [250])) { fclose($sock); return [false, "data_send_failed: {$resp}"]; }

    $cmd('QUIT');
    fclose($sock);
    return [true, null];
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

// Honeypot - skryté pole, které vyplňují jen boti
if (!empty($data['website'] ?? '')) {
    echo json_encode(['ok' => true]);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || mb_strlen($name) > 100) {
    fail('invalid_name');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('invalid_email');
}
if ($message === '' || mb_strlen($message) > 5000) {
    fail('invalid_message');
}

$email = str_replace(["\r", "\n"], '', $email);

$subject = '=?UTF-8?B?' . base64_encode('Nová zpráva z webu — ' . $name) . '?=';

$body = "Jméno: {$name}\n"
    . "Email: {$email}\n\n"
    . "Zpráva:\n{$message}\n";

[$sent, $smtpError] = smtpSend($config, $config['smtp_user'], $recipient, $subject, $body, $email);

if (!$sent) {
    logError('smtp_send_failed', ['smtp_error' => $smtpError, 'recipient' => $recipient, 'from_email' => $email]);
    fail('send_failed', 500);
}

logError('mail_sent', ['recipient' => $recipient, 'from_email' => $email]);
echo json_encode(['ok' => true]);
