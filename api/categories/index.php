<?php
require_once __DIR__ . '/../config.php';
setHeaders();

authenticate(); // Must be logged in
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet();
} else {
    sendResponse(false, 'Method not allowed', null, 405);
}

function handleGet(): void
{
    $conn = getDBConnection();
    $type = $_GET['type'] ?? '';
    $sql = 'SELECT id, name, icon, type, color FROM categories';
    $params = [];

    if (in_array($type, ['Income', 'Expense'])) {
        $sql .= ' WHERE type = ?';
        $params = [$type];
    }
    $sql .= ' ORDER BY type ASC, name ASC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param('s', $params[0]);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Categories fetched', $categories);
}
