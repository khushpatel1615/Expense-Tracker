<?php
require_once __DIR__ . '/../config.php';
setHeaders();

$userId = authenticate();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

match ($method) {
    'GET' => handleGet($userId),
    'POST' => handlePost($userId),
    'PUT' => handlePut($userId, $id),
    'DELETE' => handleDelete($userId, $id),
    default => sendResponse(false, 'Method not allowed', null, 405)
};

function handleGet(int $userId): void
{
    $conn = getDBConnection();
    $month = (int) date('m');
    $year = (int) date('Y');

    $stmt = $conn->prepare(
        'SELECT b.id, b.amount, b.month, b.year,
                c.id AS category_id, c.name, c.icon, c.color
         FROM budgets b
         JOIN categories c ON b.category_id = c.id
         WHERE b.user_id = ? AND b.month = ? AND b.year = ?
         ORDER BY c.name ASC'
    );
    $stmt->bind_param('iii', $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $budgets = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = (float) $row['amount'];
        $budgets[] = $row;
    }
    $stmt->close();
    $conn->close();
    sendResponse(true, 'Budgets fetched', $budgets);
}

function handlePost(int $userId): void
{
    $body = getRequestBody();
    $categoryId = (int) ($body['category_id'] ?? 0);
    $amount = (float) ($body['amount'] ?? 0);
    $month = (int) ($body['month'] ?? 0);
    $year = (int) ($body['year'] ?? 0);

    if ($categoryId <= 0 || $amount <= 0 || $month < 1 || $month > 12 || $year < 2000) {
        sendResponse(false, 'Invalid budget data', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'INSERT INTO budgets (user_id, category_id, amount, month, year)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
    );
    $stmt->bind_param('iidii', $userId, $categoryId, $amount, $month, $year);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Failed to save budget', null, 500);
    }
    $newId = (int) $conn->insert_id;
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Budget saved', ['id' => $newId], 201);
}

function handleDelete(int $userId, ?int $id): void
{
    if (!$id) {
        sendResponse(false, 'Budget ID required', null, 400);
    }
    $conn = getDBConnection();
    $stmt = $conn->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $userId);

    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Budget not found', null, 404);
    }
    $stmt->close();
    $conn->close();
    sendResponse(true, 'Budget deleted', ['id' => $id]);
}

function handlePut(int $userId, ?int $id): void
{
    if (!$id) {
        sendResponse(false, 'Budget ID required', null, 400);
    }

    $body = getRequestBody();
    $categoryId = (int) ($body['category_id'] ?? 0);
    $amount = (float) ($body['amount'] ?? 0);
    $month = (int) ($body['month'] ?? 0);
    $year = (int) ($body['year'] ?? 0);

    if ($categoryId <= 0 || $amount <= 0 || $month < 1 || $month > 12 || $year < 2000) {
        sendResponse(false, 'Invalid budget data', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'UPDATE budgets SET category_id=?, amount=?, month=?, year=? WHERE id=? AND user_id=?'
    );
    $stmt->bind_param('idiiii', $categoryId, $amount, $month, $year, $id, $userId);

    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Budget not found or no changes made', null, 404);
    }
    $stmt->close();
    $conn->close();
    sendResponse(true, 'Budget updated', ['id' => $id]);
}
