<?php
// EazyMUZE v2.5 - Database Connection (PDO)
require_once dirname(__DIR__) . '/includes/env.php';

// Global Backend Error Logger
if (!function_exists('writeBackendErrorLog')) {
    function writeBackendErrorLog($message, $exception = null) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [Source: Backend PHP]\n";
        $logMessage .= "Error: $message\n";
        if ($exception instanceof Throwable) {
            $logMessage .= "File: " . $exception->getFile() . " | Line: " . $exception->getLine() . "\n";
            $logMessage .= "Stack: " . $exception->getTraceAsString() . "\n";
        }
        $logMessage .= "--------------------------------------------------\n";
        
        $logsDir = dirname(__DIR__) . '/logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0777, true);
        }
        file_put_contents($logsDir . '/app_errors.log', $logMessage, FILE_APPEND);
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'zcxynyvl_Muze';
$user = getenv('DB_USER') ?: 'zcxynyvl_Muze'; 
$pass = getenv('DB_PASS') ?: 'zcxynyvl_Muze';     
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if (function_exists('writeBackendErrorLog')) {
        writeBackendErrorLog('Database connection failed', $e);
    }
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed.',
        'debug' => $e->getMessage()
    ]));
}


// Global Response Helper
if (!function_exists('sendResponse')) {
    function sendResponse($status, $data = null, $message = '') {
        header('Content-Type: application/json');
        echo json_encode(['status' => $status, 'data' => $data, 'message' => $message]);
        exit;
    }
}
?>
