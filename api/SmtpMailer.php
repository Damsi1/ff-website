<?php

declare(strict_types=1);

final class SmtpMailer
{
    private array $config;
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $message): void
    {
        $this->connect();

        try {
            $this->command('EHLO localhost', [250]);

            if (($this->config['security'] ?? 'ssl') === 'tls') {
                $this->command('STARTTLS', [220]);

                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS-Verbindung zum Mailserver konnte nicht aufgebaut werden.');
                }

                $this->command('EHLO localhost', [250]);
            }

            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode((string) $this->config['username']), [334]);
            $this->command(base64_encode((string) $this->config['password']), [235]);

            $this->command('MAIL FROM:<' . $this->config['from_email'] . '>', [250]);
            $this->command('RCPT TO:<' . $message['to_email'] . '>', [250, 251]);
            $this->command('DATA', [354]);
            $this->write($this->buildMimeMessage($message) . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }
    }

    private function connect(): void
    {
        $transport = ($this->config['security'] ?? 'ssl') === 'ssl' ? 'ssl://' : '';
        $host = $transport . $this->config['host'];
        $port = (int) ($this->config['port'] ?? 465);
        $timeout = (int) ($this->config['timeout'] ?? 20);

        $this->socket = @fsockopen($host, $port, $errorNumber, $errorString, $timeout);

        if (!$this->socket) {
            throw new RuntimeException('Mailserver konnte nicht erreicht werden: ' . $errorString . ' (' . $errorNumber . ')');
        }

        stream_set_timeout($this->socket, $timeout);
        $this->expect([220]);
    }

    private function command(string $command, array $expectedCodes): void
    {
        $this->write($command . "\r\n");
        $this->expect($expectedCodes);
    }

    private function write(string $data): void
    {
        fwrite($this->socket, $data);
    }

    private function expect(array $expectedCodes): void
    {
        $response = '';

        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;

            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Keine Antwort vom Mailserver erhalten.');
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Unerwartete Mailserver-Antwort: ' . trim($response));
        }
    }

    private function buildMimeMessage(array $message): string
    {
        $boundary = 'ffviehdorf-' . bin2hex(random_bytes(12));
        $subject = $this->encodeHeader($message['subject']);
        $fromName = $this->encodeHeader((string) $this->config['from_name']);
        $replyToName = $this->encodeHeader((string) ($message['reply_to_name'] ?? $this->config['reply_to_name']));

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromName . ' <' . $this->config['from_email'] . '>',
            'To: ' . $this->encodeHeader($message['to_name']) . ' <' . $message['to_email'] . '>',
            'Reply-To: ' . $replyToName . ' <' . ($message['reply_to_email'] ?? $this->config['reply_to_email']) . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $text = $this->normalizeBody($message['text_body']);
        $html = $this->normalizeBody($message['html_body']);

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $this->escapeDots($text) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $this->escapeDots($html) . "\r\n";
        $body .= '--' . $boundary . '--';

        return $body;
    }

    private function normalizeBody(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }

    private function escapeDots(string $body): string
    {
        return preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body)) ?? $body;
    }

    private function encodeHeader(string $value): string
    {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
}
