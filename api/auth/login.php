<?php
require_once __DIR__ . '/../config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    handleLogin();
} else {
    sendResponse(false, 'Method not allowed', null, 405);
}

function handleLogin(): void
{
    $body = getRequestBody();
    $email = trim($body['email'] ?? '');
    $pass = $body['password'] ?? '';

    if (empty($email) || empty($pass)) {
        sendResponse(false, 'Email and password are required', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        sendResponse(false, 'Invalid email or password', null, 401);
    }

    $token = generateJWT((int) $user['id'], $user['email']);
    sendResponse(true, 'Login successful', [
        'token' => $token,
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);
}
