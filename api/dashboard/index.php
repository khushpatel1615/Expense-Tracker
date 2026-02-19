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
 * Helper — build date range filters from GET params or default to current month
 */
function getDateRange(): array
{
    $now = new DateTime();

    if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
        return ['from' => $_GET['date_from'], 'to' => $_GET['date_to']];
    }

    // Default: current month
    $from = $now->format('Y-m-01');
    $to = $now->format('Y-m-t');
    return ['from' => $from, 'to' => $to];
}

/**
 * Current period summary: total income, expenses, balance, transaction count
 * Accepts ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD for flexible filtering
 */
function getSummary(int $userId): void
{
    $conn = getDBConnection();
    $range = getDateRange();
    $from = $range['from'];
    $to = $range['to'];

    // Current period
    $sql = "SELECT
              SUM(CASE WHEN c.type = 'Income'  THEN t.amount ELSE 0 END) AS total_income,
              SUM(CASE WHEN c.type = 'Expense' THEN t.amount ELSE 0 END) AS total_expense,
              COUNT(*) AS transaction_count
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND t.date BETWEEN ? AND ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $userId, $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $curIncome = (float) ($row['total_income'] ?? 0);
    $curExpense = (float) ($row['total_expense'] ?? 0);

    // Previous period (same length) for smart insight comparison
    $fromDate = new DateTime($from);
    $toDate = new DateTime($to);
    $diff = (int) $fromDate->diff($toDate)->days + 1;
    $prevFrom = (clone $fromDate)->modify("-{$diff} days")->format('Y-m-d');
    $prevTo = $fromDate->modify('-1 day')->format('Y-m-d');

    $prevStmt = $conn->prepare($sql);
    $prevStmt->bind_param('iss', $userId, $prevFrom, $prevTo);
    $prevStmt->execute();
    $prevRow = $prevStmt->get_result()->fetch_assoc();
    $prevStmt->close();

    $prevExpense = (float) ($prevRow['total_expense'] ?? 0);
    $prevIncome = (float) ($prevRow['total_income'] ?? 0);

    // Smart insight
    $insight = null;
    if ($prevExpense > 0) {
        $pctChange = round((($curExpense - $prevExpense) / $prevExpense) * 100, 1);
        if ($pctChange > 10) {
            $insight = ['type' => 'warning', 'msg' => "⚠️ You spent {$pctChange}% more than the previous period."];
        } elseif ($pctChange < -10) {
            $abs = abs($pctChange);
            $insight = ['type' => 'success', 'msg' => "✅ Great! You spent {$abs}% less than the previous period."];
        } else {
            $insight = ['type' => 'info', 'msg' => "📊 Spending is similar to the previous period."];
        }
    } elseif ($curIncome > 0 && $curExpense === 0.0) {
        $insight = ['type' => 'success', 'msg' => '🎉 No expenses recorded this period — great savings!'];
    }

    // All-time balance
    $balSql = "SELECT SUM(CASE WHEN c.type='Income' THEN t.amount ELSE -t.amount END) AS balance
                FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ?";
    $balStmt = $conn->prepare($balSql);
    $balStmt->bind_param('i', $userId);
    $balStmt->execute();
    $balRow = $balStmt->get_result()->fetch_assoc();
    $balStmt->close();
    $conn->close();

    $label = ($from === date('Y-m-01') && $to === date('Y-m-t'))
        ? date('F Y')
        : date('M j', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));

    sendResponse(true, 'Summary fetched', [
        'period_label' => $label,
        'total_income' => $curIncome,
        'total_expense' => $curExpense,
        'net_this_month' => $curIncome - $curExpense,
        'all_time_balance' => (float) ($balRow['balance'] ?? 0),
        'transaction_count' => (int) $row['transaction_count'],
        'prev_expense' => $prevExpense,
        'prev_income' => $prevIncome,
        'insight' => $insight
    ]);
}

/**
 * Spending breakdown by category for current month
 */
function getByCategory(int $userId): void
{
    $conn = getDBConnection();
    $range = getDateRange();
    $type = $_GET['type'] ?? 'Expense';

    $sql = "SELECT c.name, c.icon, c.color, c.type,
                   SUM(t.amount) AS total,
                   COUNT(t.id) AS count
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND c.type = ? AND t.date BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.icon, c.color, c.type
            ORDER BY total DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isss', $userId, $type, $range['from'], $range['to']);
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
    $range = getDateRange();

    $sql = "SELECT c.name, c.icon, c.color, SUM(t.amount) AS total
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = ? AND c.type = 'Expense'
              AND t.date BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.icon, c.color
            ORDER BY total DESC
            LIMIT 5";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $userId, $range['from'], $range['to']);
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
