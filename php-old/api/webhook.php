<?php
/**
 * EazyMUZE Monnify Webhook Handler & Virtual Account Manager
 * Monnify calls this URL when a payment is confirmed.
 * URL to register in Monnify Dashboard: https://eazymuze.ng/webhook/monnify/
 */

// Start session at the VERY beginning for API endpoints
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/smtp.php';

// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ============ CONFIGURATION ============
// Use environment variables for security (recommended)
define('MONNIFY_SECRET', getenv('MONNIFY_SECRET') ?: 'GVEA65CEST8YR5015RW21J9Y7BBRL4KC');
define('MONNIFY_API_KEY', getenv('MONNIFY_API_KEY') ?: 'MK_PROD_Z1N1VE409T');
define('MONNIFY_CONTRACT_CODE', getenv('MONNIFY_CONTRACT_CODE') ?: '479854013966');
define('MONNIFY_BASE_URL', 'https://api.monnify.com');

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'zcxynyvl_Muze');
define('DB_USER', getenv('DB_USER') ?: 'zcxynyvl_Muze');
define('DB_PASS', getenv('DB_PASS') ?: 'zcxynyvl_Muze');

// ============ DATABASE CONNECTION ============
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit('Database error');
}

// ============ CREATE REQUIRED TABLES IF NOT EXISTS ============
try {
    // Users table (if not exists with required columns)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(20),
        wallet_balance DECIMAL(10,2) DEFAULT 0.00,
        monnify_account_number VARCHAR(20),
        monnify_bank_name VARCHAR(100),
        monnify_ref VARCHAR(255),
        virtual_account_created_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Processed webhooks table for idempotency
    $pdo->exec("CREATE TABLE IF NOT EXISTS processed_webhooks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_ref VARCHAR(255) UNIQUE NOT NULL,
        payment_ref VARCHAR(255),
        amount DECIMAL(10,2),
        user_id INT,
        processed_at DATETIME,
        INDEX idx_transaction_ref (transaction_ref),
        INDEX idx_user_id (user_id)
    )");
    
    // Transactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('credit', 'debit') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reference VARCHAR(255),
        status VARCHAR(50) DEFAULT 'pending',
        created_at DATETIME,
        INDEX idx_user_id (user_id),
        INDEX idx_reference (reference),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50),
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at DATETIME,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Add missing columns to users table if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('monnify_account_number', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_account_number VARCHAR(20)");
    }
    if (!in_array('monnify_bank_name', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_bank_name VARCHAR(100)");
    }
    if (!in_array('monnify_ref', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_ref VARCHAR(255)");
    }
    if (!in_array('virtual_account_created_at', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN virtual_account_created_at DATETIME");
    }
    
} catch (PDOException $e) {
    error_log("Table creation failed: " . $e->getMessage());
}

// ============ EMAIL FUNCTION (FALLBACK) ============
if (!function_exists('sendMuzeEmail')) {
    function sendMuzeEmail($to, $name, $subject, $body) {
        // Log email attempt
        error_log("EMAIL would be sent to: $to - $subject");
        
        // Optionally use PHP mail as fallback
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: EazyMUZE <noreply@eazymuze.ng>" . "\r\n";
        
        return @mail($to, $subject, $body, $headers);
    }
}

// ============ EMAIL TEMPLATE FUNCTION ============
if (!function_exists('emailTemplate_WalletFunded')) {
    function emailTemplate_WalletFunded($username, $amount, $accountNumber) {
        return "
        <html>
        <head><title>Wallet Funded</title></head>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Hello {$username}!</h2>
            <p>Your wallet has been credited with <strong>₦{$amount}</strong>.</p>
            <p>Virtual Account: {$accountNumber}</p>
            <p>Thank you for using EazyMUZE!</p>
        </body>
        </html>";
    }
}

// ============ WEBHOOK HANDLER - MAIN LOGIC ============
// Only run webhook processing if no action parameter is present
if (!isset($_GET['action'])) {
    try {
        $body = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_MONNIFY_SIGNATURE'] ?? '';
        
        // Calculate hash correctly (Monnify uses secret + request body)
        $hash = hash_hmac('sha512', $body, MONNIFY_SECRET);
        
        // Log all webhooks for debugging
        file_put_contents(__DIR__ . '/webhook_log.txt', 
            date('[Y-m-d H:i:s] ') . "Signature Valid: " . (hash_equals($hash, $signature) ? 'YES' : 'NO') . "\n" . $body . "\n---\n", 
            FILE_APPEND);
        
        // Verify signature
        if (!hash_equals($hash, $signature)) {
            http_response_code(401);
            file_put_contents(__DIR__ . '/webhook_errors.txt', 
                date('[Y-m-d H:i:s] ') . "Invalid signature attempt - Expected: $hash, Got: $signature\n", FILE_APPEND);
            exit('Invalid signature');
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['eventType'])) {
            http_response_code(400);
            exit('Bad Request');
        }
        
        $eventType = $data['eventType'];
        
        // Handle different event types
        if ($eventType === 'SUCCESSFUL_TRANSACTION' || $eventType === 'SUCCESSFUL_RESERVATION') {
            handleSuccessfulPayment($pdo, $data);
        } elseif ($eventType === 'REFUND_COMPLETION') {
            handleRefundCompletion($pdo, $data);
        } elseif ($eventType === 'DISBURSEMENT') {
            handleDisbursement($pdo, $data);
        } elseif ($eventType === 'SETTLEMENT') {
            handleSettlement($pdo, $data);
        }
        
        http_response_code(200);
        echo 'OK';
        
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/webhook_fatal.txt', 
            date('[Y-m-d H:i:s]') . " FATAL: {$e->getMessage()}\n" . $e->getTraceAsString() . "\n---\n", 
            FILE_APPEND);
        http_response_code(500);
        echo 'Internal Server Error';
    }
    exit;
}

// ============ PAYMENT HANDLING FUNCTION ============
function handleSuccessfulPayment($pdo, $data) {
    $paymentRef = $data['eventData']['paymentReference'] ?? '';
    $transactionRef = $data['eventData']['transactionReference'] ?? '';
    $amountPaid = floatval($data['eventData']['amountPaid'] ?? 0);
    $amountSettled = floatval($data['eventData']['amountSettled'] ?? 0);
    $accountNum = $data['eventData']['destinationAccountNumber'] ?? $data['eventData']['product']['reference'] ?? '';
    $paymentStatus = $data['eventData']['paymentStatus'] ?? '';
    $customerEmail = $data['eventData']['customer']['email'] ?? '';
    
    // Verify transaction is truly completed
    if ($paymentStatus !== 'PAID' || $amountSettled <= 0) {
        file_put_contents(__DIR__ . '/webhook_pending.txt', 
            date('[Y-m-d H:i:s]') . " Pending settlement: $transactionRef - Status: $paymentStatus\n", FILE_APPEND);
        return;
    }
    
    if ($amountPaid <= 0) {
        return;
    }
    
    // Idempotency check - Prevent duplicate processing
    $stmt = $pdo->prepare('SELECT id FROM processed_webhooks WHERE transaction_ref = ?');
    $stmt->execute([$transactionRef]);
    if ($stmt->fetch()) {
        file_put_contents(__DIR__ . '/webhook_duplicates.txt', 
            date('[Y-m-d H:i:s]') . " Duplicate: $transactionRef\n", FILE_APPEND);
        return;
    }
    
    // Find user by virtual account, reference, or email
    $user = null;
    
    // Try reserved account match first
    if ($accountNum) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE monnify_account_number = ? OR monnify_ref = ?');
        $stmt->execute([$accountNum, $accountNum]);
        $user = $stmt->fetch();
    }
    
    // Try by email from webhook
    if (!$user && $customerEmail) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$customerEmail]);
        $user = $stmt->fetch();
    }
    
    // Try payment reference pattern EMZ-<user_id>
    if (!$user && preg_match('/EMZ-(\d+)/', $paymentRef, $matches)) {
        $userId = intval($matches[1]);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    
    if ($user) {
        try {
            $pdo->beginTransaction();
            
            // Record webhook to prevent duplicates
            $stmt = $pdo->prepare('INSERT INTO processed_webhooks (transaction_ref, payment_ref, amount, user_id, processed_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$transactionRef, $paymentRef, $amountPaid, $user['id']]);
            
            // Credit wallet
            $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
            $stmt->execute([$amountPaid, $user['id']]);
            
            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, reference, status, created_at) 
                VALUES (?, 'credit', ?, ?, 'completed', NOW())");
            $stmt->execute([$user['id'], $amountPaid, $transactionRef]);
            
            // Insert notification
            $message = "₦" . number_format($amountPaid, 2) . " has been credited to your wallet! 💰";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'wallet', ?, NOW())");
            $stmt->execute([$user['id'], $message]);
            
            $pdo->commit();
            
            // Send email notification
            $emailBody = emailTemplate_WalletFunded(
                $user['username'],
                number_format($amountPaid, 2),
                $user['monnify_account_number'] ?: 'N/A'
            );
            @sendMuzeEmail($user['email'], $user['username'], '💰 Your EazyMUZE Wallet Has Been Funded!', $emailBody);
            
            file_put_contents(__DIR__ . '/webhook_success.txt', 
                date('[Y-m-d H:i:s]') . " Credited ₦{$amountPaid} to user {$user['id']} ({$user['email']}) - TX: $transactionRef\n", FILE_APPEND);
                
        } catch (Exception $e) {
            $pdo->rollBack();
            file_put_contents(__DIR__ . '/webhook_errors.txt', 
                date('[Y-m-d H:i:s]') . " DB Error: {$e->getMessage()}\n", FILE_APPEND);
        }
    } else {
        file_put_contents(__DIR__ . '/webhook_unmatched.txt', 
            date('[Y-m-d H:i:s]') . " No user found for account: $accountNum, ref: $paymentRef, email: $customerEmail\n", FILE_APPEND);
    }
}

// ============ REFUND HANDLING FUNCTION ============
function handleRefundCompletion($pdo, $data) {
    $transactionRef = $data['eventData']['transactionReference'] ?? '';
    $amount = floatval($data['eventData']['amount'] ?? 0);
    
    file_put_contents(__DIR__ . '/webhook_refunds.txt', 
        date('[Y-m-d H:i:s]') . " Refund processed: $transactionRef - Amount: ₦$amount\n", FILE_APPEND);
    
    // Implement your refund logic here (e.g., debit user wallet)
}

// ============ DISBURSEMENT HANDLING FUNCTION ============
function handleDisbursement($pdo, $data) {
    $reference = $data['eventData']['reference'] ?? '';
    $amount = floatval($data['eventData']['amount'] ?? 0);
    $status = $data['eventData']['status'] ?? '';
    
    file_put_contents(__DIR__ . '/webhook_disbursements.txt', 
        date('[Y-m-d H:i:s]') . " Disbursement: $reference - Amount: ₦$amount - Status: $status\n", FILE_APPEND);
    
    // Implement your disbursement logic here
}

// ============ SETTLEMENT HANDLING FUNCTION ============
function handleSettlement($pdo, $data) {
    $reference = $data['eventData']['reference'] ?? '';
    $amount = floatval($data['eventData']['amount'] ?? 0);
    
    file_put_contents(__DIR__ . '/webhook_settlements.txt', 
        date('[Y-m-d H:i:s]') . " Settlement: $reference - Amount: ₦$amount\n", FILE_APPEND);
    
    // Implement your settlement logic here
}

// ============ VIRTUAL ACCOUNT GENERATION FUNCTION ============
function createVirtualAccount($pdo, $userId, $userName, $userEmail, $userPhone) {
    try {
        // Check if user already has a virtual account
        $stmt = $pdo->prepare('SELECT monnify_account_number, monnify_bank_name FROM users WHERE id = ? AND monnify_account_number IS NOT NULL');
        $stmt->execute([$userId]);
        if ($existing = $stmt->fetch()) {
            return [
                'success' => true,
                'accountNumber' => $existing['monnify_account_number'],
                'bankName' => $existing['monnify_bank_name'],
                'already_exists' => true
            ];
        }
        
        // Get Monnify access token
        $auth = base64_encode(MONNIFY_API_KEY . ':' . MONNIFY_SECRET);
        $ch = curl_init(MONNIFY_BASE_URL . '/api/v1/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $auth]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to get token. HTTP ' . $httpCode);
        }
        
        $tokenData = json_decode($response, true);
        $accessToken = $tokenData['responseBody']['accessToken'] ?? null;
        
        if (!$accessToken) {
            throw new Exception('No access token in response');
        }
        
        // Generate unique reference
        $accountRef = 'EMZ-VA-' . $userId . '-' . time();
        
        // Create reserved account
        $payload = [
            'contractCode' => MONNIFY_CONTRACT_CODE,
            'accountReference' => $accountRef,
            'accountName' => substr($userName . ' - EazyMUZE', 0, 50),
            'currencyCode' => 'NGN',
            'customerEmail' => $userEmail,
            'customerName' => substr($userName, 0, 100),
            'customerPhone' => $userPhone,
            'getAllAvailableBanks' => true,
            'preferredBanks' => ['035', '058', '033', '057']
        ];
        
        $ch = curl_init(MONNIFY_BASE_URL . '/api/v1/bank-transfer/reserved-accounts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error on account creation: ' . $curlError);
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception('Account creation failed. HTTP ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        // Handle response structure
        $accounts = $result['responseBody']['accounts'] ?? $result['accounts'] ?? null;
        
        if ($accounts && count($accounts) > 0) {
            $account = $accounts[0];
            $accountNumber = $account['accountNumber'] ?? null;
            $bankName = $account['bankName'] ?? null;
            
            if ($accountNumber && $bankName) {
                $stmt = $pdo->prepare('UPDATE users SET 
                    monnify_account_number = ?, 
                    monnify_bank_name = ?, 
                    monnify_ref = ?,
                    virtual_account_created_at = NOW()
                    WHERE id = ?');
                $stmt->execute([$accountNumber, $bankName, $accountRef, $userId]);
                
                $message = "🎉 Your virtual account has been created!\nBank: $bankName\nAccount: $accountNumber";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'account', ?, NOW())");
                $stmt->execute([$userId, $message]);
                
                return [
                    'success' => true,
                    'accountNumber' => $accountNumber,
                    'bankName' => $bankName,
                    'accountReference' => $accountRef
                ];
            }
        }
        
        throw new Exception('Invalid response structure from Monnify');
        
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/va_errors.txt', 
            date('[Y-m-d H:i:s]') . " User $userId: {$e->getMessage()}\n", FILE_APPEND);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ============ API ENDPOINTS ============

// Create Virtual Account Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'create_va' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? $_SESSION['user_id'] ?? 0;
    $userName = $input['name'] ?? '';
    $userEmail = $input['email'] ?? '';
    $userPhone = $input['phone'] ?? '';
    
    if (!$userId || !$userName || !$userEmail) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields: user_id, name, email']);
        exit;
    }
    
    $result = createVirtualAccount($pdo, $userId, $userName, $userEmail, $userPhone);
    echo json_encode($result);
    exit;
}

// Check Virtual Account Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'check_va' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $userId = $_GET['user_id'] ?? 0;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'user_id required']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT monnify_account_number, monnify_bank_name, virtual_account_created_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['monnify_account_number']) {
        echo json_encode([
            'success' => true,
            'hasAccount' => true,
            'accountNumber' => $user['monnify_account_number'],
            'bankName' => $user['monnify_bank_name'],
            'createdAt' => $user['virtual_account_created_at']
        ]);
    } else {
        echo json_encode(['success' => true, 'hasAccount' => false]);
    }
    exit;
}

// Get Wallet Balance Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'get_balance' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $userId = $_GET['user_id'] ?? 0;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'user_id required']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'balance' => floatval($user['wallet_balance']),
            'balanceFormatted' => '₦' . number_format($user['wallet_balance'], 2)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    exit;
}