<?php
// api/log_error.php
// EazyMUZE v2.5 - Client-side Error Logger API

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Always start session to associate logged errors with user sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data) {
        $message = $data['message'] ?? 'Unknown error';
        $source  = $data['source']  ?? 'Client-side JS';
        $file    = $data['file']    ?? 'N/A';
        $line    = $data['line']    ?? 'N/A';
        $col     = $data['column']  ?? 'N/A';
        $stack   = $data['stack']   ?? '';
        $user    = $data['user']    ?? ($_SESSION['username'] ?? 'Guest');
        $url     = $data['url']     ?? $_SERVER['HTTP_REFERER'] ?? 'N/A';
        
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [Source: $source] [User: $user]\n";
        $logMessage .= "Error: $message\n";
        $logMessage .= "File: $file | Line: $line | Col: $col\n";
        if (!empty($url)) {
            $logMessage .= "URL: $url\n";
        }
        if (!empty($stack)) {
            $logMessage .= "Stack: $stack\n";
        }
        $logMessage .= "--------------------------------------------------\n";
        
        // Save in root 'logs' directory
        $logsDir = dirname(__DIR__) . '/logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0777, true);
        }
        
        file_put_contents($logsDir . '/app_errors.log', $logMessage, FILE_APPEND);
        
        echo json_encode(['status' => 'success', 'message' => 'Logged successfully']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>
