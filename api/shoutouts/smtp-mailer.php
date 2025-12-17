<?php
/**
 * SMTP mailer for shoutouts - uses centralized email_config table
 * Adapted from original shoutouts/api/smtp-mailer.php
 */

require_once __DIR__ . '/../email/helpers.php';

/**
 * Send an HTML email via SMTP.
 *
 * @param string $to
 * @param string $subject
 * @param string $htmlBody
 * @param string $fromEmail
 * @param string $fromName
 * @param string|null $replyTo
 * @return array [success => bool, log => string]
 */
function smtpSendHtml($to, $subject, $htmlBody, $fromEmail, $fromName, $replyTo = null) {
    $log = [];

    // Get SMTP settings from centralized config
    $config = get_email_config();
    
    $host = $config['smtp_host'] ?? '';
    $port = (int)($config['smtp_port'] ?? 465);
    $username = $config['smtp_user'] ?? '';
    $password = $config['smtp_pass'] ?? '';
    $encryption = strtolower($config['smtp_encryption'] ?? 'ssl');

    if (!$host || !$port || !$username || !$password) {
        return [
            'success' => false,
            'log' => 'SMTP not fully configured in database',
        ];
    }

    $remote = $encryption === 'ssl' ? "ssl://{$host}" : $host;

    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$fp) {
        return [
            'success' => false,
            'log' => "fsockopen failed: {$errno} {$errstr}",
        ];
    }

    stream_set_timeout($fp, 15);

    $read = function() use ($fp, &$log) {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') break; // end of multi-line
        }
        $log[] = 'S: ' . trim($data);
        return $data;
    };

    $write = function($cmd) use ($fp, &$log) {
        $log[] = 'C: ' . trim($cmd);
        fwrite($fp, $cmd . "\r\n");
    };

    $resp = $read();
    if (strpos($resp, '220') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["Unexpected greeting: {$resp}"])),
        ];
    }

    // Extract domain from email for EHLO
    $ehloDomain = 'localhost';
    if (preg_match('/@(.+)$/', $username, $matches)) {
        $ehloDomain = $matches[1];
    }
    
    $write("EHLO {$ehloDomain}");
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["EHLO failed: {$resp}"])),
        ];
    }

    if ($encryption === 'tls') {
        $write('STARTTLS');
        $resp = $read();
        if (strpos($resp, '220') !== 0) {
            fclose($fp);
            return [
                'success' => false,
                'log' => implode("\n", array_merge($log, ["STARTTLS failed: {$resp}"])),
            ];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return [
                'success' => false,
                'log' => implode("\n", array_merge($log, ["TLS enable failed"])),
            ];
        }
        // Re-EHLO after STARTTLS
        $write("EHLO {$ehloDomain}");
        $resp = $read();
        if (strpos($resp, '250') !== 0) {
            fclose($fp);
            return [
                'success' => false,
                'log' => implode("\n", array_merge($log, ["EHLO after STARTTLS failed: {$resp}"])),
            ];
        }
    }

    // AUTH LOGIN
    $write('AUTH LOGIN');
    $resp = $read();
    if (strpos($resp, '334') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["AUTH LOGIN not accepted: {$resp}"])),
        ];
    }

    $write(base64_encode($username));
    $resp = $read();
    if (strpos($resp, '334') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["Username not accepted: {$resp}"])),
        ];
    }

    $write(base64_encode($password));
    $resp = $read();
    if (strpos($resp, '235') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["Password not accepted: {$resp}"])),
        ];
    }

    // MAIL FROM and RCPT TO
    // Hostinger requires the authenticated user address as the envelope sender
    $mailFrom = $username;
    $write('MAIL FROM: <' . $mailFrom . '>');
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["MAIL FROM failed: {$resp}"])),
        ];
    }

    $write('RCPT TO: <' . $to . '>');
    $resp = $read();
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["RCPT TO failed: {$resp}"])),
        ];
    }

    $write('DATA');
    $resp = $read();
    if (strpos($resp, '354') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["DATA not accepted: {$resp}"])),
        ];
    }

    // Build headers and message
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . ($replyTo ?: $fromEmail);
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $data = '';
    $data .= 'Subject: ' . $subject . "\r\n";
    $data .= 'To: ' . $to . "\r\n";
    $data .= implode("\r\n", $headers) . "\r\n\r\n";
    $data .= $htmlBody . "\r\n.\r\n";

    fwrite($fp, $data);
    $log[] = 'C: [message data]';
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        fclose($fp);
        return [
            'success' => false,
            'log' => implode("\n", array_merge($log, ["DATA send failed: {$resp}"])),
        ];
    }

    $write('QUIT');
    $read();
    fclose($fp);

    return [
        'success' => true,
        'log' => implode("\n", $log),
    ];
}
