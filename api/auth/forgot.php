<?php
require_once __DIR__ . '/../config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];

match ($method) {
    'POST' => handleForgotRequest(),
    'PUT' => handleResetPassword(),
    default => sendResponse(false, 'Method not allowed', null, 405)
};

/**
 * Step 1 — Request OTP (POST with email)
 * Step 2 — Verify OTP + set new password (PUT with email + otp + new_password)
 */
function handleForgotRequest(): void
{
    $body = getRequestBody();
    $email = trim($body['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Valid email is required', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Always return success to prevent email enumeration
    if (!$user) {
        $conn->close();
        sendResponse(true, 'If that email exists, an OTP has been sent.', ['dev_otp' => null]);
    }

    // Generate 6-digit OTP, expires in 15 minutes
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 900); // 15 min

    // Store OTP in users table (reuse a transient column or a separate table)
    // We'll store as JSON in a reset_token column (added via ALTER if needed)
    $payload = json_encode(['otp' => $otp, 'expires' => $expires]);

    // Ensure reset_token column exists (safe to run each time)
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token TEXT DEFAULT NULL");

    $upStmt = $conn->prepare('UPDATE users SET reset_token = ? WHERE id = ?');
    $upStmt->bind_param('si', $payload, $user['id']);
    $upStmt->execute();
    $upStmt->close();
    $conn->close();

    // Send OTP via email (simulated for dev)
    sendResponse(true, 'OTP sent to email', [
        'expires_in' => 900,
        'dev_otp' => $otp
    ]);
}

function handleResetPassword(): void
{
    $body = getRequestBody();
    $email = trim($body['email'] ?? '');
    $otp = trim($body['otp'] ?? '');
    $newPass = $body['new_password'] ?? '';

    if (empty($email) || empty($otp) || empty($newPass)) {
        sendResponse(false, 'Email, OTP, and new password are all required', null, 400);
    }
    if (strlen($newPass) < 6) {
        sendResponse(false, 'Password must be at least 6 characters', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, reset_token FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['reset_token'])) {
        $conn->close();
        sendResponse(false, 'Invalid or expired OTP', null, 400);
    }

    $tokenData = json_decode($user['reset_token'], true);
    if (!$tokenData || $tokenData['otp'] !== $otp) {
        $conn->close();
        sendResponse(false, 'Incorrect OTP', null, 400);
    }
    if (strtotime($tokenData['expires']) < time()) {
        $conn->close();
        sendResponse(false, 'OTP has expired. Please request a new one.', null, 400);
    }

    // All good — update password and clear token
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $upStmt = $conn->prepare('UPDATE users SET password_hash = ?, reset_token = NULL WHERE id = ?');
    $upStmt->bind_param('si', $hash, $user['id']);
    $upStmt->execute();
    $upStmt->close();
    $conn->close();

    sendResponse(true, 'Password reset successfully. Please sign in.', null);
}
