<?php
require_once __DIR__ . '/../config.php';
setHeaders();

$userId = authenticate();
$method = $_SERVER['REQUEST_METHOD'];

match ($method) {
    'GET' => getProfile($userId),
    'PUT' => updateProfile($userId),
    default => sendResponse(false, 'Method not allowed', null, 405)
};

function getProfile(int $userId): void
{
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    sendResponse(true, 'Profile fetched', $user);
}

function updateProfile(int $userId): void
{
    $body = getRequestBody();
    $name = trim($body['name'] ?? '');
    $currentPass = $body['current_password'] ?? '';
    $newPass = $body['new_password'] ?? '';

    if (empty($name)) {
        sendResponse(false, 'Name cannot be empty', null, 400);
    }

    $conn = getDBConnection();

    // Fetch current hash for password verification
    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->close();
        sendResponse(false, 'User not found', null, 404);
    }

    // If user wants to change password, verify current password first
    if (!empty($newPass)) {
        if (empty($currentPass)) {
            $conn->close();
            sendResponse(false, 'Current password is required to set a new password', null, 400);
        }
        if (!password_verify($currentPass, $row['password_hash'])) {
            $conn->close();
            sendResponse(false, 'Current password is incorrect', null, 401);
        }
        if (strlen($newPass) < 6) {
            $conn->close();
            sendResponse(false, 'New password must be at least 6 characters', null, 400);
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upStmt = $conn->prepare('UPDATE users SET name = ?, password_hash = ? WHERE id = ?');
        $upStmt->bind_param('ssi', $name, $newHash, $userId);
    } else {
        $upStmt = $conn->prepare('UPDATE users SET name = ? WHERE id = ?');
        $upStmt->bind_param('si', $name, $userId);
    }

    $upStmt->execute();
    $upStmt->close();

    // Return updated user info
    $fetchStmt = $conn->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $fetchStmt->bind_param('i', $userId);
    $fetchStmt->execute();
    $updated = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();
    $conn->close();

    sendResponse(true, 'Profile updated successfully', $updated);
}
