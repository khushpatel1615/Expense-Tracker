<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'expense_tracker');

// JWT Configuration
define('JWT_SECRET', 'ExpenseTracker_SuperSecret_Key_2025_Khush');
define('JWT_EXPIRY', 86400); // 24 hours in seconds

// App Configuration
define('APP_NAME', 'Expense Tracker');
define('CORS_ORIGIN', '*');

/**
 * Get MySQL database connection
 */
function getDBConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Set CORS and JSON headers
 */
function setHeaders(): void {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=UTF-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Send JSON response
 */
function sendResponse(bool $success, string $message, mixed $data = null, int $code = 200): void {
    http_response_code($code);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

/**
 * Generate a JWT token
 */
function generateJWT(int $userId, string $email): string {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'sub'   => $userId,
        'email' => $email,
        'iat'   => time(),
        'exp'   => time() + JWT_EXPIRY
    ]));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

/**
 * Validate a JWT token and return payload data
 */
function validateJWT(string $token): array|false {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    [$header, $payload, $signature] = $parts;
    $expectedSig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expectedSig, $signature)) {
        return false;
    }
    $data = json_decode(base64_decode($payload), true);
    if ($data['exp'] < time()) {
        return false;
    }
    return $data;
}

/**
 * Authenticate request via Bearer token and return userId
 */
function authenticate(): int {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) {
        sendResponse(false, 'Unauthorized: No token provided', null, 401);
    }
    $token = substr($auth, 7);
    $payload = validateJWT($token);
    if (!$payload) {
        sendResponse(false, 'Unauthorized: Invalid or expired token', null, 401);
    }
    return (int)$payload['sub'];
}

/**
 * Get JSON body from request
 */
function getRequestBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
