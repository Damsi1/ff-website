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
        'text_body' => buildNotificationText($name, $email, $safeMessageText),
        'html_body' => buildNotificationHtml($safeName, $safeEmail, $safeMessageHtml),
    ]);

    $mailer->send([
        'to_email' => $email,
        'to_name' => $name,
        'subject' => 'Bestätigung deiner Nachricht an die FF Viehdorf',
        'text_body' => buildConfirmationText($name, $safeMessageText),
        'html_body' => buildConfirmationHtml($config, $safeName, $safeMessageHtml),
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

function buildNotificationText(string $name, string $email, string $message): string
{
    return "Neue Nachricht über das Website-Kontaktformular" . PHP_EOL . PHP_EOL
        . "Name: {$name}" . PHP_EOL
        . "E-Mail: {$email}" . PHP_EOL . PHP_EOL
        . "Nachricht:" . PHP_EOL
        . $message;
}

function buildNotificationHtml(string $safeName, string $safeEmail, string $safeMessageHtml): string
{
    return '<div style="font-family:Arial,sans-serif;background:#f4f4f4;padding:24px;color:#1f2937;">'
        . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">'
        . '<div style="background:#b70000;padding:20px 24px;color:#ffffff;">'
        . '<p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">FF Viehdorf</p>'
        . '<h1 style="margin:8px 0 0;font-size:24px;line-height:1.3;">Neue Kontaktanfrage</h1>'
        . '</div>'
        . '<div style="padding:24px;">'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.6;">'
        . 'Es wurde eine neue Nachricht über das Kontaktformular der Website übermittelt.'
        . '</p>'
        . '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;font-weight:700;width:140px;">Name</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">' . $safeName . '</td></tr>'
        . '<tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;font-weight:700;">E-Mail</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;">' . $safeEmail . '</td></tr>'
        . '</table>'
        . '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">'
        . '<p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#111827;">Nachricht</p>'
        . '<p style="margin:0;font-size:15px;line-height:1.7;color:#374151;">' . $safeMessageHtml . '</p>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div>';
}

function buildConfirmationText(string $name, string $message): string
{
    return "Hallo {$name}," . PHP_EOL . PHP_EOL
        . "danke fuer deine Nachricht an die FF Viehdorf. Wir haben dein Anliegen erhalten und melden uns so bald wie moeglich." . PHP_EOL . PHP_EOL
        . "Deine uebermittelte Nachricht:" . PHP_EOL
        . $message . PHP_EOL . PHP_EOL
        . "Viele Gruesse" . PHP_EOL
        . "FF Viehdorf";
}

function buildConfirmationHtml(array $config, string $safeName, string $safeMessageHtml): string
{
    $siteUrl = sanitize((string) ($config['site_url'] ?? ''));
    $replyToEmail = sanitize((string) ($config['smtp']['reply_to_email'] ?? ''));

    return '<div style="font-family:Arial,sans-serif;background:#f3f4f6;padding:24px;color:#1f2937;">'
        . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e5e7eb;">'
        . '<div style="background:linear-gradient(135deg,#d5e000 0%,#eef78a 100%);padding:28px 24px;color:#17181b;border-bottom:1px solid #d9e2a2;">'
        . '<p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:700;">Kontaktformular</p>'
        . '<h1 style="margin:10px 0 0;font-size:28px;line-height:1.25;color:#121212;">Wir haben deine Nachricht erhalten</h1>'
        . '</div>'
        . '<div style="padding:28px 24px;">'
        . '<p style="margin:0 0 14px;font-size:16px;line-height:1.7;">Hallo <strong style="color:#111827;">' . $safeName . '</strong>,</p>'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.7;">'
        . 'vielen Dank fuer deine Nachricht an die FF Viehdorf. Wir haben dein Anliegen erfolgreich erhalten und melden uns so bald wie moeglich bei dir.'
        . '</p>'
        . '<div style="background:#fbfde8;border:1px solid #d5e000;border-radius:14px;padding:18px 16px;margin:0 0 20px;box-shadow:inset 0 0 0 1px rgba(213,224,0,0.18);">'
        . '<p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#4d5700;">Deine uebermittelte Nachricht</p>'
        . '<p style="margin:0;font-size:15px;line-height:1.7;color:#374151;">' . $safeMessageHtml . '</p>'
        . '</div>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;">'
        . 'Falls du uns noch weitere Informationen schicken moechtest, kannst du einfach auf diese E-Mail antworten.'
        . '</p>'
        . '<div style="margin:24px 0;">'
        . '<a href="' . $siteUrl . '" style="display:inline-block;background:#d5e000;color:#111827;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;border:1px solid #bec72a;">Zur Website</a>'
        . '</div>'
        . '<p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">Viele Gruesse<br>FF Viehdorf<br>'
        . $replyToEmail
        . '</p>'
        . '</div>'
        . '</div>'
        . '</div>';
}

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
