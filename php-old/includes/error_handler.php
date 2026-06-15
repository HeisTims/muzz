<?php
// includes/error_handler.php
// EazyMUZE v2.5 - Global Backend Error Handler

// Enable all error reporting internally
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable outputting errors directly to visitors in production
ini_set('log_errors', 1);

if (!function_exists('logBackendError')) {
    function logBackendError($severity, $message, $filepath, $line, $stack = '') {
        $severityMap = [
            E_ERROR             => 'Fatal Error',
            E_WARNING           => 'Warning',
            E_PARSE             => 'Parsing Error',
            E_NOTICE            => 'Notice',
            E_CORE_ERROR        => 'Core Error',
            E_CORE_WARNING      => 'Core Warning',
            E_COMPILE_ERROR     => 'Compile Error',
            E_COMPILE_WARNING   => 'Compile Warning',
            E_USER_ERROR        => 'User Error',
            E_USER_WARNING      => 'User Warning',
            E_USER_NOTICE       => 'User Notice',
            E_STRICT            => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED        => 'Deprecated Warning',
            E_USER_DEPRECATED   => 'User Deprecated Warning'
        ];

        $severityName = $severityMap[$severity] ?? 'Unknown Error (' . $severity . ')';
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [Source: PHP $severityName]\n";
        $logMessage .= "Error: $message\n";
        $logMessage .= "File: $filepath | Line: $line\n";
        if (!empty($stack)) {
            $logMessage .= "Stack Trace:\n$stack\n";
        }
        $logMessage .= "--------------------------------------------------\n";

        $logsDir = dirname(__DIR__) . '/logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0777, true);
        }
        file_put_contents($logsDir . '/app_errors.log', $logMessage, FILE_APPEND);
    }
}

// 1. Error Handler (for warnings, notices, user-triggered errors)
set_error_handler(function($severity, $message, $filepath, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    logBackendError($severity, $message, $filepath, $line);
    // Don't execute PHP's internal error handler
    return true;
});

// 2. Exception Handler (for unhandled exceptions)
set_exception_handler(function($exception) {
    $severity = E_ERROR;
    if ($exception instanceof Error) {
        // Convert PHP Engine errors (like TypeError, ParseError)
        $severity = E_CORE_ERROR;
    }
    logBackendError(
        $severity, 
        $exception->getMessage(), 
        $exception->getFile(), 
        $exception->getLine(), 
        $exception->getTraceAsString()
    );
    
    // Output a clean JSON error response if this was an AJAX/API call
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
               || (isset($_SERVER['CONTENT_TYPE']) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false)
               || (isset($_GET['action']))
               || (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
               
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'An unexpected server error occurred.'
        ]);
    } else {
        echo "<div style='background:rgba(255,42,109,0.1); border:1px solid #ff2a6d; color:#fff; padding:20px; border-radius:12px; margin:20px; font-family:sans-serif;'>";
        echo "<h2 style='color:#ff2a6d; margin-top:0;'>Muze Temple Warning 💋</h2>";
        echo "<p>Something went wrong on our end. The spirits have logged the issue.</p>";
        echo "</div>";
    }
    exit;
});

// 3. Fatal Error/Shutdown Handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        logBackendError(
            $error['type'], 
            $error['message'], 
            $error['file'], 
            $error['line']
        );
    }
});
?>
