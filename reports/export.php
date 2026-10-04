<?php
/*
 * CSV downloads (open in Excel): /reports/export.php?type=...
 * Each type takes the same filters as the page it is exported from.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/reports.php';
require_once __DIR__ . '/../includes/payments.php';
require_once __DIR__ . '/../includes/inventory.php';
requireLogin();

$conn = getDBConnection();
$type = $_GET['type'] ?? '';
$isDate = function ($value) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1;
};
$cleanName = function ($name) {
    return html_entity_decode(str_ireplace('&quot;', '"', (string) $name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$money = function ($amount) {
    return round((float) $amount, 2);
};

// A cell starting with = + - @ would run as a formula in Excel; prefix text cells with '
function csvCell($value) {
    if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value)) {
        return "'" . $value;
    }
    return $value;
}

function sendCsv($name, $header, $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows ₱ and accents correctly
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csvCell', $row));
    }
    fclose($out);
    exit();
}

function fetchRows($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

if ($type === 'inventory') {
    $rows = [];
    foreach (fetchRows($conn, "SELECT * FROM item_list ORDER BY TRIM(name) ASC") as $item) {
        $rows[] = [$item['description'], $cleanName($item['name']), $item['unit'], $money($item['cost_price'] ?? 0), $money($item['price']),
            round((float) $item['stocks'], 2), round((float) ($item['reorder_level'] ?? 0), 2), (int) $item['status'] === 1 ? 'Active' : 'Inactive'];
    }
    sendCsv('inventory', ['Code', 'Name', 'Unit', 'Cost Price', 'Selling Price', 'Stock', 'Reorder Level', 'Status'], $rows);
}

if ($type === 'stock_history') {
    $where = " WHERE 1=1";
    $types = '';
    $params = [];
    if (intval($_GET['item_id'] ?? 0) > 0) {
        $where .= " AND m.item_id = ?";
        $types .= 'i';
        $params[] = intval($_GET['item_id']);
    }
    if (isset(STOCK_MOVEMENT_TYPES[$_GET['type_filter'] ?? ''])) {
        $where .= " AND m.movement_type = ?";
        $types .= 's';
        $params[] = $_GET['type_filter'];
    }
    if ($isDate($_GET['from'] ?? '')) {
        $where .= " AND m.created_at >= ?";
        $types .= 's';
        $params[] = $_GET['from'] . ' 00:00:00';
    }
    if ($isDate($_GET['to'] ?? '')) {
        $where .= " AND m.created_at <= ?";
        $types .= 's';
        $params[] = $_GET['to'] . ' 23:59:59';
    }
    $rows = [];
    foreach (fetchRows($conn, "SELECT m.*, u.full_name, u.username, il.description AS code, il.unit FROM stock_movements m
            LEFT JOIN users u ON u.id = m.created_by LEFT JOIN item_list il ON il.id = m.item_id" . $where . "
            ORDER BY m.created_at DESC, m.id DESC", $types, $params) as $m) {
        $rows[] = [$m['created_at'], $m['item_name'], $m['code'] ?? '', round((float) $m['quantity_change'], 2), round((float) $m['balance_after'], 2), $m['unit'] ?? '',
            STOCK_MOVEMENT_TYPES[$m['movement_type']] ?? $m['movement_type'], $m['reference_number'] ?? '', $m['note'] ?? '', $m['full_name'] ?: ($m['username'] ?? '')];
    }
    sendCsv('stock-history', ['Date', 'Item', 'Code', 'Change', 'Balance', 'Unit', 'Type', 'Reference', 'Note', 'By'], $rows);
}

if ($type === 'transactions') {
    [$where, $types, $params] = transactionsFilter(trim($_GET['type_filter'] ?? ''), trim($_GET['status'] ?? ''), trim($_GET['search'] ?? ''));
    $rows = [];
    foreach (fetchRows($conn, "SELECT * FROM (" . transactionsUnionSql() . ") AS all_trans" . $where . " ORDER BY trans_datetime DESC, trans_date DESC", $types, $params) as $tx) {
        $rows[] = [$tx['ref_number'], $tx['transaction_type'], $tx['party_name'], $tx['trans_date'], $money($tx['amount']), ucfirst($tx['status'])];
    }
    sendCsv('transactions', ['Reference', 'Type', 'Customer / Supplier', 'Date', 'Amount', 'Status'], $rows);
}

if ($type === 'customer_orders' || $type === 'supplier_orders') {
    $kind = $type === 'supplier_orders' ? 'supplier' : 'customer';
    $party = $kind === 'supplier' ? 'supplier_name' : 'customer_name';
    $rows = [];
    foreach (fetchRows($conn, "SELECT o.*, COALESCE(p.paid, 0) AS paid FROM `$type` o LEFT JOIN " . paidSubquery($kind) . " p ON p.order_id = o.id
            ORDER BY o.order_date DESC, o.id DESC") as $po) {
        $pay = paymentSummary($po['total_amount'], $po['paid'], $po['due_date'] ?? null, $po['status']);
        $rows[] = [$po['po_number'], $po[$party], $po['order_date'], $po['due_date'] ?? '', ucfirst($po['status']), $money($po['total_amount']),
            $money($pay['paid']), $po['status'] === 'cancelled' ? 0 : $money($pay['balance']), $pay['label'], $po['notes'] ?? ''];
    }
    sendCsv($kind . '-purchase-orders', ['PO #', $kind === 'supplier' ? 'Supplier' : 'Customer', 'Order Date', 'Due Date', 'Status', 'Total', 'Paid', 'Balance', 'Payment', 'Notes'], $rows);
}

if ($type === 'profit') {
    $from = $isDate($_GET['from'] ?? '') ? $_GET['from'] : date('Y-01-01');
    $to = $isDate($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
    $lines = profitLinesSql(($_GET['basis'] ?? '') === 'all' ? 'all' : 'completed');
    $group = $_GET['group'] ?? 'order';
    if ($group === 'month') {
        $sql = "SELECT DATE_FORMAT(order_date, '%Y-%m') AS label, " . PROFIT_SUMS . " FROM ($lines) l GROUP BY label ORDER BY label DESC";
        $first = 'Month';
    } elseif ($group === 'customer') {
        $sql = "SELECT customer_name AS label, " . PROFIT_SUMS . " FROM ($lines) l GROUP BY customer_name ORDER BY customer_name";
        $first = 'Customer';
    } else {
        $group = 'order';
        $sql = "SELECT CONCAT(po_number, ' (', customer_name, ', ', order_date, ')') AS label, " . PROFIT_SUMS . " FROM ($lines) l
            GROUP BY order_id, po_number, customer_name, order_date ORDER BY order_date DESC, order_id DESC";
        $first = 'Order';
    }
    $rows = [];
    foreach (fetchRows($conn, $sql, 'ss', [$from, $to]) as $row) {
        [$profit, $margin] = profitFigures($row);
        $rows[] = [$row['label'], (int) $row['orders'], $money($row['revenue']), $money($row['cost']), $money($profit),
            $margin === null ? '' : round($margin, 1), $money($row['uncosted_revenue'])];
    }
    sendCsv('profit-by-' . $group, [$first, 'Orders', 'Sales', 'Cost', 'Profit', 'Margin %', 'Sales Without Cost Price'], $rows);
}

http_response_code(404);
echo 'Unknown export.';
