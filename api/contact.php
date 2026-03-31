<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/contact-config.php';
require __DIR__ . '/SmtpMailer.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, [
            'success' => false,
            'message' => 'Nur POST-Anfragen sind erlaubt.',
        ]);
    }

    ensureConfigured($config);

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $captchaToken = trim((string) ($_POST['h-captcha-response'] ?? ''));
    $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        throw new RuntimeException('Bitte fülle Name, E-Mail und Nachricht vollständig aus.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Bitte gib eine gültige E-Mail-Adresse ein.');
    }

    if ($captchaToken === '') {
        throw new RuntimeException('hCaptcha konnte nicht geprüft werden. Bitte versuche es erneut.');
    }

    verifyHCaptcha($config['hcaptcha'], $captchaToken, $remoteIp);

    $mailer = new SmtpMailer($config['smtp']);

    $safeName = sanitize($name);
    $safeEmail = sanitize($email);
    $safeMessageHtml = nl2br(sanitize($message));
    $safeMessageText = preg_replace("/\R/u", PHP_EOL, $message) ?? $message;

    $mailer->send([
        'to_email' => $config['recipient_email'],
        'to_name' => $config['recipient_name'],
        'reply_to_email' => $email,
        'reply_to_name' => $name,
        'subject' => 'Neue Kontaktanfrage von ' . $name,
        'text_body' => "Neue Nachricht über das Website-Kontaktformular" . PHP_EOL . PHP_EOL
            . "Name: {$name}" . PHP_EOL
            . "E-Mail: {$email}" . PHP_EOL . PHP_EOL
            . "Nachricht:" . PHP_EOL
            . $safeMessageText,
        'html_body' => '<h2>Neue Nachricht über das Website-Kontaktformular</h2>'
            . '<p><strong>Name:</strong> ' . $safeName . '</p>'
            . '<p><strong>E-Mail:</strong> ' . $safeEmail . '</p>'
            . '<p><strong>Nachricht:</strong><br>' . $safeMessageHtml . '</p>',
    ]);

    $mailer->send([
        'to_email' => $email,
        'to_name' => $name,
        'subject' => 'Bestätigung deiner Nachricht an die FF Viehdorf',
        'text_body' => "Hallo {$name}," . PHP_EOL . PHP_EOL
            . "danke für deine Nachricht an die FF Viehdorf. Wir haben dein Anliegen erhalten und melden uns so bald wie möglich." . PHP_EOL . PHP_EOL
            . "Deine Nachricht:" . PHP_EOL
            . $safeMessageText . PHP_EOL . PHP_EOL
            . "Viele Grüße" . PHP_EOL
            . "FF Viehdorf",
        'html_body' => '<p>Hallo ' . $safeName . ',</p>'
            . '<p>danke für deine Nachricht an die FF Viehdorf. Wir haben dein Anliegen erhalten und melden uns so bald wie möglich.</p>'
            . '<p><strong>Deine Nachricht:</strong><br>' . $safeMessageHtml . '</p>'
            . '<p>Viele Grüße<br>FF Viehdorf</p>',
    ]);

    respond(200, [
        'success' => true,
        'message' => 'Danke! Deine Nachricht wurde versendet. Du bekommst gleich eine Bestätigung per E-Mail.',
    ]);
} catch (Throwable $exception) {
    respond(400, [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}

function verifyHCaptcha(array $config, string $token, string $remoteIp): void
{
    $payload = http_build_query([
        'secret' => $config['secret'],
        'response' => $token,
        'remoteip' => $remoteIp,
        'sitekey' => $config['sitekey'],
    ]);

    $responseBody = null;

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.hcaptcha.com/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('hCaptcha konnte nicht geprüft werden: ' . $error);
        }

        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 20,
            ],
        ]);

        $responseBody = @file_get_contents('https://api.hcaptcha.com/siteverify', false, $context);

        if ($responseBody === false) {
            throw new RuntimeException('hCaptcha konnte serverseitig nicht geprüft werden.');
        }
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded) || !($decoded['success'] ?? false)) {
        throw new RuntimeException('hCaptcha-Prüfung fehlgeschlagen. Bitte versuche es erneut.');
    }
}

function ensureConfigured(array $config): void
{
    $placeholders = [
        'https://deine-domain.at',
        'feuerwehr@deine-domain.at',
        'dein-postfach@deine-domain.at',
        'HIER_HOSTINGER_SMTP_PASSWORT_EINTRAGEN',
    ];

    $values = [
        $config['site_url'] ?? '',
        $config['recipient_email'] ?? '',
        $config['smtp']['username'] ?? '',
        $config['smtp']['password'] ?? '',
        $config['smtp']['from_email'] ?? '',
        $config['smtp']['reply_to_email'] ?? '',
    ];

    foreach ($values as $value) {
        if (in_array($value, $placeholders, true)) {
            throw new RuntimeException('Bitte zuerst die Hostinger- und Mail-Konfiguration in api/contact-config.php eintragen.');
        }
    }
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
