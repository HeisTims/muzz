<?php
/**
 * EazyMUZE Custom SMTP Mailer
 * Connects to mail.eazymuze.ng on port 465 (SSL)
 */

require_once dirname(__DIR__) . '/includes/env.php';

define('SMTP_HOST',      getenv('SMTP_HOST') ?: 'mail.eazymuze.ng');
define('SMTP_PORT',      intval(getenv('SMTP_PORT') ?: 465));
define('SMTP_USER',      getenv('SMTP_USER') ?: 'help@eazymuze.ng');
define('SMTP_PASS',      getenv('SMTP_PASS') ?: 'miracletims');
define('SMTP_FROM',      getenv('SMTP_FROM') ?: 'help@eazymuze.ng');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'EazyMUZE 💋');

function sendMuzeEmail(string $to, string $toName, string $subject, string $htmlBody): bool {
    $errno = 0;
    $errstr = '';
    $timeout = 15; // 15 seconds timeout
    
    // Disable SSL verification to bypass local cPanel cert mismatches
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    // Connect to SMTP server over SSL (SSL is on 465, STARTTLS on 587)
    $socket = null;
    $useSTARTTLS = false;
    
    if (SMTP_PORT === 465) {
        $socket = @stream_socket_client('ssl://' . SMTP_HOST . ':465', $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    } else {
        $socket = @stream_socket_client('tcp://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket && SMTP_PORT === 587) {
            $useSTARTTLS = true;
        }
    }
    
    // If connection fails, log error and return false
    if (!$socket) {
        error_log("EazyMUZE SMTP Connection Failed: $errstr ($errno)");
        return false;
    }
    
    try {
        // Read Greeting
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '220') {
            throw new Exception("Bad greeting: " . $response);
        }
        
        // EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("EHLO failed: " . $response);
        }
        
        // STARTTLS if on port 587
        if ($useSTARTTLS) {
            fputs($socket, "STARTTLS\r\n");
            $response = smtpGetResponse($socket);
            if (substr($response, 0, 3) !== '220') {
                throw new Exception("STARTTLS failed: " . $response);
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS encryption failed");
            }
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            $response = smtpGetResponse($socket);
            if (substr($response, 0, 3) !== '250') {
                throw new Exception("EHLO after TLS failed: " . $response);
            }
        }
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("AUTH LOGIN rejected: " . $response);
        }
        
        // Username
        fputs($socket, base64_encode(SMTP_USER) . "\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("Username rejected: " . $response);
        }
        
        // Password
        fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '235') {
            throw new Exception("Authentication failed: " . $response);
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM:<" . SMTP_FROM . ">\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("MAIL FROM rejected: " . $response);
        }
        
        // RCPT TO
        fputs($socket, "RCPT TO:<$to>\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("RCPT TO rejected: " . $response);
        }
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = smtpGetResponse($socket);
        if (substr($response, 0, 3) !== '354') {
            throw new Exception("DATA rejected: " . $response);
        }
        
        // Headers & Message
        $messageId = "<" . bin2hex(random_bytes(16)) . "@" . SMTP_HOST . ">";
        $dateHeader = date('r');
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . smtpEncodeHeader(SMTP_FROM_NAME) . " <" . SMTP_FROM . ">\r\n";
        $headers .= "Reply-To: " . smtpEncodeHeader(SMTP_FROM_NAME) . " <" . SMTP_FROM . ">\r\n";
        $headers .= "Return-Path: <" . SMTP_FROM . ">\r\n";
        $headers .= "To: " . smtpEncodeHeader($toName) . " <$to>\r\n";
        $headers .= "Subject: " . smtpEncodeHeader($subject) . "\r\n";
        $headers .= "Date: $dateHeader\r\n";
        $headers .= "Message-ID: $messageId\r\n";
        $headers .= "X-Mailer: EazyMUZE SMTP Mailer v2.7\r\n";
        $headers .= "X-Priority: 3\r\n";
        $headers .= "Auto-Submitted: auto-generated\r\n";
        
        $message = $headers . "\r\n" . $htmlBody . "\r\n";
        $message = str_replace("\r\n.", "\r\n..", $message);
        
        fputs($socket, $message . "\r\n.\r\n");
        $response = smtpGetResponse($socket);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($response, 0, 3) === '250') {
            return true;
        } else {
            throw new Exception("Send failed after DATA: " . $response);
        }
        
    } catch (Exception $e) {
        error_log("EazyMUZE SMTP Error: " . $e->getMessage());
        if ($socket) {
            @fputs($socket, "QUIT\r\n");
            @fclose($socket);
        }
        return false;
    }
}

/**
 * Helper function to read SMTP response
 */
function smtpGetResponse($socket): string {
    $response = '';
    while ($line = fgets($socket, 1024)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

/**
 * Helper function to encode non-ASCII headers
 */
function smtpEncodeHeader(string $text): string {
    if (!preg_match('/[^\x20-\x7E]/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}
?>