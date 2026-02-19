<?php
require_once __DIR__ . '/../config.php';
setHeaders();

$userId = authenticate();
$endpoint = $_GET['endpoint'] ?? 'summary';

match ($endpoint) {
    'summary' => getSummary($userId),
    'by-category' => getByCategory($userId),
    'monthly-trend' => getMonthlyTrend($userId),
    'top-expenses' => getTopExpenses($userId),
    'budget-status' => getBudgetStatus($userId),
    default => sendResponse(false, 'Unknown dashboard endpoint', null, 404)
};

/**
 * Current month summary: total income, expenses, balance, transaction count
 */
function getSummary(int $userId): void
{
    $conn = getDBConnection();
    $month = (int) date('m');
    $year = (int) date('Y');

    $sql = "SELECT
              SUM(CASE WHEN c.type = 'Income'  THEN t.amount ELSE 0 END) AS total_income,
              SUM(CASE WHEN c.type = 'Expense' THEN t.amount ELSE 0 END) AS total_expense,
              COUNT(*) AS transaction_count
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND MONTH(t.date) = ? AND YEAR(t.date) = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $userId, $month, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // All-time balance
    $balSql = "SELECT SUM(CASE WHEN c.type='Income' THEN t.amount ELSE -t.amount END) AS balance
               FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ?";
    $balStmt = $conn->prepare($balSql);
    $balStmt->bind_param('i', $userId);
    $balStmt->execute();
    $balRow = $balStmt->get_result()->fetch_assoc();
    $balStmt->close();
    $conn->close();

    sendResponse(true, 'Summary fetched', [
        'month' => date('F Y'),
        'total_income' => (float) ($row['total_income'] ?? 0),
        'total_expense' => (float) ($row['total_expense'] ?? 0),
        'net_this_month' => (float) ($row['total_income'] ?? 0) - (float) ($row['total_expense'] ?? 0),
        'all_time_balance' => (float) ($balRow['balance'] ?? 0),
        'transaction_count' => (int) $row['transaction_count']
    ]);
}

/**
 * Spending breakdown by category for current month
 */
function getByCategory(int $userId): void
{
    $conn = getDBConnection();
    $month = (int) date('m');
    $year = (int) date('Y');
    $type = $_GET['type'] ?? 'Expense';

    $sql = "SELECT c.name, c.icon, c.color, c.type,
                   SUM(t.amount) AS total,
                   COUNT(t.id) AS count
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND c.type = ? AND MONTH(t.date) = ? AND YEAR(t.date) = ?
            GROUP BY c.id, c.name, c.icon, c.color, c.type
            ORDER BY total DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isii', $userId, $type, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $categories = [];
    $grandTotal = 0;
    while ($row = $result->fetch_assoc()) {
        $row['total'] = (float) $row['total'];
        $grandTotal += $row['total'];
        $categories[] = $row;
    }
    $stmt->close();
    $conn->close();

    // Add percentage
    foreach ($categories as &$cat) {
        $cat['percentage'] = $grandTotal > 0 ? round(($cat['total'] / $grandTotal) * 100, 1) : 0;
    }
    unset($cat);

    sendResponse(true, 'Category breakdown fetched', [
        'categories' => $categories,
        'grand_total' => $grandTotal
    ]);
}

/**
 * Monthly income vs expense trend (last 6 months)
 */
function getMonthlyTrend(int $userId): void
{
    $conn = getDBConnection();

    $sql = "SELECT
              DATE_FORMAT(t.date, '%Y-%m') AS month_key,
              DATE_FORMAT(t.date, '%b %Y')  AS month_label,
              SUM(CASE WHEN c.type = 'Income'  THEN t.amount ELSE 0 END) AS income,
              SUM(CASE WHEN c.type = 'Expense' THEN t.amount ELSE 0 END) AS expense
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND t.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month_key, month_label
            ORDER BY month_key ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $months = [];
    while ($row = $result->fetch_assoc()) {
        $row['income'] = (float) $row['income'];
        $row['expense'] = (float) $row['expense'];
        $months[] = $row;
    }
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Monthly trend fetched', $months);
}

/**
 * Top 5 expense categories this month
 */
function getTopExpenses(int $userId): void
{
    $conn = getDBConnection();
    $month = (int) date('m');
    $year = (int) date('Y');

    $sql = "SELECT c.name, c.icon, c.color, SUM(t.amount) AS total
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND c.type = 'Expense'
              AND MONTH(t.date) = ? AND YEAR(t.date) = ?
            GROUP BY c.id, c.name, c.icon, c.color
            ORDER BY total DESC
            LIMIT 5";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $top = [];
    while ($row = $result->fetch_assoc()) {
        $row['total'] = (float) $row['total'];
        $top[] = $row;
    }
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Top expenses fetched', $top);
}

/**
 * Budget status — show each category budget vs actual spend this month
 */
function getBudgetStatus(int $userId): void
{
    $conn = getDBConnection();
    $month = (int) date('m');
    $year = (int) date('Y');

    $sql = "SELECT b.id, b.category_id, c.name, c.icon, c.color,
                   b.amount AS budget, b.month, b.year,
                   COALESCE(SUM(t.amount), 0) AS spent
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            LEFT JOIN transactions t
              ON t.category_id = b.category_id
             AND t.user_id = b.user_id
             AND MONTH(t.date) = b.month
             AND YEAR(t.date) = b.year
            WHERE b.user_id = ? AND b.month = ? AND b.year = ?
            GROUP BY b.id, c.name, c.icon, c.color, b.amount
            ORDER BY c.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $budgets = [];
    while ($row = $result->fetch_assoc()) {
        $row['budget'] = (float) $row['budget'];
        $row['spent'] = (float) $row['spent'];
        $row['remaining'] = $row['budget'] - $row['spent'];
        $row['percent'] = $row['budget'] > 0 ? round(($row['spent'] / $row['budget']) * 100, 1) : 0;
        $row['warning'] = $row['percent'] >= 80;
        $budgets[] = $row;
    }
    $stmt->close();
    $conn->close();

    sendResponse(true, 'Budget status fetched', $budgets);
}
