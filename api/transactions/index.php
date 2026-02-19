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
    $where = ['t.user_id = ?'];
    $types = 'i';
    $vals = [$userId];

    // Optional filters
    if (!empty($_GET['category_id'])) {
        $where[] = 't.category_id = ?';
        $types .= 'i';
        $vals[] = (int) $_GET['category_id'];
    }
    if (!empty($_GET['type'])) {
        $where[] = 'c.type = ?';
        $types .= 's';
        $vals[] = $_GET['type'];
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 't.date >= ?';
        $types .= 's';
        $vals[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 't.date <= ?';
        $types .= 's';
        $vals[] = $_GET['date_to'];
    }
    if (!empty($_GET['search'])) {
        $where[] = 't.note LIKE ?';
        $types .= 's';
        $vals[] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);
    $sort = in_array($_GET['sort'] ?? '', ['amount', 'date']) ? $_GET['sort'] : 'date';
    $order = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $sql = "SELECT t.id, t.amount, t.note, t.date, t.created_at,
                   c.id AS category_id, c.name AS category_name, c.icon AS category_icon,
                   c.type AS type, c.color AS category_color
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE $whereClause
            ORDER BY t.$sort $order";

    // Pagination
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT ? OFFSET ?";
    $types .= 'ii';
    $vals[] = $limit;
    $vals[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = (float) $row['amount'];
        $transactions[] = $row;
    }
    $stmt->close();

    // Get total count
    $countSql = "SELECT COUNT(*) AS total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    // bind without page/limit params
    $countTypes = substr($types, 0, -2);
    $countVals = array_slice($vals, 0, -2);
    if (!empty($countVals)) {
        $countStmt->bind_param($countTypes, ...$countVals);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $total = (int) $countResult['total'];
    $countStmt->close();
    $conn->close();

    sendResponse(true, 'Transactions fetched', [
        'transactions' => $transactions,
        'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => ceil($total / $limit)]
    ]);
}

function handlePost(int $userId): void
{
    $body = getRequestBody();
    $categoryId = (int) ($body['category_id'] ?? 0);
    $amount = round((float) ($body['amount'] ?? 0), 2);
    $note = trim($body['note'] ?? '');
    $date = trim($body['date'] ?? '');

    if ($categoryId <= 0 || $amount <= 0 || empty($date)) {
        sendResponse(false, 'Category, amount, and date are required', null, 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        sendResponse(false, 'Invalid date format. Use YYYY-MM-DD', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('INSERT INTO transactions (user_id, category_id, amount, note, date) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('iidss', $userId, $categoryId, $amount, $note, $date);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Failed to add transaction', null, 500);
    }

    $newId = (int) $conn->insert_id;
    $stmt->close();

    // Fetch created transaction
    $stmt2 = $conn->prepare(
        'SELECT t.id, t.amount, t.note, t.date, t.created_at,
                c.id AS category_id, c.name AS category_name, c.icon AS category_icon,
                c.type AS type, c.color AS category_color
         FROM transactions t JOIN categories c ON t.category_id = c.id
         WHERE t.id = ?'
    );
    $stmt2->bind_param('i', $newId);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $row['amount'] = (float) $row['amount'];
    $stmt2->close();
    $conn->close();

    sendResponse(true, 'Transaction added successfully', $row, 201);
}

function handlePut(int $userId, ?int $id): void
{
    if (!$id) {
        sendResponse(false, 'Transaction ID is required', null, 400);
    }

    $body = getRequestBody();
    $categoryId = (int) ($body['category_id'] ?? 0);
    $amount = round((float) ($body['amount'] ?? 0), 2);
    $note = trim($body['note'] ?? '');
    $date = trim($body['date'] ?? '');

    if ($categoryId <= 0 || $amount <= 0 || empty($date)) {
        sendResponse(false, 'Category, amount, and date are required', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'UPDATE transactions SET category_id=?, amount=?, note=?, date=? WHERE id=? AND user_id=?'
    );
    $stmt->bind_param('idssii', $categoryId, $amount, $note, $date, $id, $userId);

    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Transaction not found or no changes made', null, 404);
    }
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Transaction updated successfully', ['id' => $id]);
}

function handleDelete(int $userId, ?int $id): void
{
    if (!$id) {
        sendResponse(false, 'Transaction ID is required', null, 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $userId);

    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Transaction not found', null, 404);
    }
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Transaction deleted successfully', ['id' => $id]);
}
