<?php
/**
 * EazyMUZE Monnify API Handler
 * API Key: MK_PROD_Z1N1VE409T
 * Secret Key: GVEA65CEST8YR5015RW21J9Y7BBRL4KC
 * Contract Code: 479854013966
 */

require_once dirname(__DIR__) . '/includes/env.php';

define('MONNIFY_API_KEY',       getenv('MONNIFY_API_KEY') ?: 'MK_PROD_Z1N1VE409T');
define('MONNIFY_SECRET_KEY',    getenv('MONNIFY_SECRET_KEY') ?: 'GVEA65CEST8YR5015RW21J9Y7BBRL4KC');
define('MONNIFY_BASE_URL',      getenv('MONNIFY_BASE_URL') ?: 'https://api.monnify.com');
define('MONNIFY_CONTRACT_CODE', getenv('MONNIFY_CONTRACT_CODE') ?: '479854013966');
define('MONNIFY_BRAND_NAME',    'EazyMUZE');

/**
 * Get Monnify access token (basic auth)
 */
function monnify_getToken(): ?string {
    $credentials = base64_encode(MONNIFY_API_KEY . ':' . MONNIFY_SECRET_KEY);
    $ch = curl_init(MONNIFY_BASE_URL . '/api/v1/auth/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("Monnify Token Error: $err");
        return null;
    }

    $data = json_decode($response, true);
    return $data['responseBody']['accessToken'] ?? null;
}

/**
 * Create a reserved/dedicated virtual account for a user
 * This account permanently bears the user's name and can receive wallet top-ups anytime.
 */
function monnify_createReservedAccount(string $accountRef, string $username, string $email, string $phone): ?array {
    $token = monnify_getToken();
    if (!$token) return null;

    $payload = json_encode([
        'accountReference'  => $accountRef,
        'accountName'       => MONNIFY_BRAND_NAME . ' - ' . $username,
        'currencyCode'      => 'NGN',
        'contractCode'      => MONNIFY_CONTRACT_CODE,
        'customerEmail'     => $email,
        'customerName'      => $username,
        'customerBvn'       => '',
        'nin'               => '',
        'getAllAvailableBanks' => true,
        'preferredBanks'    => ['035', '058'], // Wema & GTB virtual accounts
    ]);

    $ch = curl_init(MONNIFY_BASE_URL . '/api/v2/bank-transfer/reserved-accounts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("Monnify Reserved Account Error: $err");
        return null;
    }

    $data = json_decode($response, true);
    if (isset($data['responseBody']['accounts']) && count($data['responseBody']['accounts']) > 0) {
        $account = $data['responseBody']['accounts'][0];
        return [
            'accountNumber' => $account['accountNumber'] ?? '',
            'bankName'      => $account['bankName'] ?? '',
            'bankCode'      => $account['bankCode'] ?? '',
            'accountRef'    => $accountRef,
        ];
    }

    error_log("Monnify Reserved Account Response: $response");
    return null;
}

/**
 * Initialize a one-time transaction for checkout
 */
function monnify_initTransaction(string $ref, float $amount, string $email, string $name, string $description): ?array {
    $token = monnify_getToken();
    if (!$token) return null;

    $payload = json_encode([
        'amount'              => $amount,
        'customerName'        => $name,
        'customerEmail'       => $email,
        'paymentReference'    => $ref,
        'paymentDescription'  => $description,
        'currencyCode'        => 'NGN',
        'contractCode'        => MONNIFY_CONTRACT_CODE,
        'redirectUrl'         => 'https://eazymuze.ng',
        'paymentMethods'      => ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'],
    ]);

    $ch = curl_init(MONNIFY_BASE_URL . '/api/v1/merchant/transactions/init-transaction');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("Monnify Init Transaction Error: $err");
        return null;
    }

    $data = json_decode($response, true);
    return $data['responseBody'] ?? null;
}

/**
 * Verify a transaction by reference
 */
function monnify_verifyTransaction(string $paymentRef): ?array {
    $token = monnify_getToken();
    if (!$token) return null;

    $encodedRef = urlencode($paymentRef);
    $ch = curl_init(MONNIFY_BASE_URL . '/api/v2/merchant/transactions/query?paymentReference=' . $encodedRef);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['responseBody'] ?? null;
}

// Handle API calls from frontend
if (isset($_GET['action'])) {
    require_once 'db.php';
    session_start();
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Content-Type: application/json");

    $action = $_GET['action'];

    // Get or auto-provision virtual account for logged-in user
    if ($action === 'get_account') {
        if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
        $stmt = $pdo->prepare('SELECT monnify_account_number, monnify_bank_name, monnify_ref, username, email, phone FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && $user['monnify_account_number']) {
            echo json_encode(['status' => 'success', 'data' => $user]);
        } else if ($user) {
            // Provision live reserved account on the fly!
            $accountRef = 'EAZYMUZE-' . strtoupper($user['username']) . '-' . $_SESSION['user_id'];
            $monnifyAccount = monnify_createReservedAccount($accountRef, $user['username'], $user['email'], $user['phone'] ?: '08011112222');
            if ($monnifyAccount && !empty($monnifyAccount['accountNumber'])) {
                $pdo->prepare('UPDATE users SET monnify_ref = ?, monnify_account_number = ?, monnify_bank_name = ?, monnify_bank_code = ? WHERE id = ?')
                    ->execute([
                        $accountRef,
                        $monnifyAccount['accountNumber'],
                        $monnifyAccount['bankName'],
                        $monnifyAccount['bankCode'],
                        $_SESSION['user_id']
                    ]);
                $user['monnify_account_number'] = $monnifyAccount['accountNumber'];
                $user['monnify_bank_name'] = $monnifyAccount['bankName'];
                $user['monnify_ref'] = $accountRef;
                echo json_encode(['status' => 'success', 'data' => $user]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Virtual account setup failed on Monnify API. Please verify account profile phone and email format.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No virtual account yet.']);
        }
        exit;
    }

    // Init a transaction (for checkout)
    if ($action === 'init_transaction' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
        $input  = json_decode(file_get_contents('php://input'), true);
        $amount = floatval($input['amount'] ?? 0);
        $desc   = htmlspecialchars($input['description'] ?? 'EazyMUZE Purchase');
        if ($amount <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid amount']); exit; }

        $stmt = $pdo->prepare('SELECT email, username FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        $ref  = 'EMZ-' . strtoupper(uniqid());
        $resp = monnify_initTransaction($ref, $amount, $user['email'], $user['username'], $desc);
        if ($resp) {
            echo json_encode(['status' => 'success', 'data' => $resp]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Payment initialization failed.']);
        }
        exit;
    }
}
