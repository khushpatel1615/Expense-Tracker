<?php
require_once __DIR__ . '/../config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    handleRegister();
} else {
    sendResponse(false, 'Method not allowed', null, 405);
}

function handleRegister(): void
{
    $body = getRequestBody();
    $name = trim($body['name'] ?? '');
    $email = trim($body['email'] ?? '');
    $pass = $body['password'] ?? '';

    // Validate inputs
    if (empty($name) || empty($email) || empty($pass)) {
        sendResponse(false, 'Name, email, and password are required', null, 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format', null, 400);
    }
    if (strlen($pass) < 6) {
        sendResponse(false, 'Password must be at least 6 characters', null, 400);
    }

    $conn = getDBConnection();

    // Check if email already exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'An account with this email already exists', null, 409);
    }
    $stmt->close();

    // Hash password and insert user
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt2 = $conn->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt2->bind_param('sss', $name, $email, $hash);

    if (!$stmt2->execute()) {
        $stmt2->close();
        $conn->close();
        sendResponse(false, 'Registration failed. Please try again.', null, 500);
    }

    $userId = (int) $conn->insert_id;
    $stmt2->close();
    $conn->close();

    $token = generateJWT($userId, $email);
    sendResponse(true, 'Registration successful', [
        'token' => $token,
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email]
    ], 201);
}
