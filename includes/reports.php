<?php
/*
 * Queries shared by the report pages and the CSV export (reports/export.php).
 */

require_once __DIR__ . '/../config.php';

// Quotations, customer POs and supplier POs as one list (Transaction History)
function transactionsUnionSql() {
    return "
SELECT 
    'Quotation' AS transaction_type,
    quotation_number AS ref_number,
    client_name AS party_name,
    DATE(created_at) AS trans_date,
    created_at AS trans_datetime,
    grand_total AS amount,
    CASE 
        WHEN status = 'accepted' THEN 'approved'
        WHEN status IN ('draft', 'sent') THEN 'pending'
        WHEN status = 'rejected' THEN 'cancelled'
        ELSE status
    END AS status,
    CONCAT('/quotation/view_quotation.php?id=', id) AS view_link
FROM quotations

UNION ALL

SELECT 
    'Customer Purchase Order' AS transaction_type,
    po_number AS ref_number,
    customer_name AS party_name,
    order_date AS trans_date,
    created_at AS trans_datetime,
    total_amount AS amount,
    status,
    CONCAT('/orders/view_customer_po.php?id=', id) AS view_link
FROM customer_orders

UNION ALL

SELECT 
    'Supplier Purchase Order' AS transaction_type,
    po_number AS ref_number,
    supplier_name AS party_name,
    order_date AS trans_date,
    created_at AS trans_datetime,
    total_amount AS amount,
    status,
    CONCAT('/orders/view_supplier_po.php?id=', id) AS view_link
FROM supplier_orders
";
}

// WHERE clause for the Transaction History filters: [$where, $types, $params]
function transactionsFilter($typeFilter, $statusFilter, $search) {
    $where = " WHERE 1=1";
    $params = [];
    $types = '';

    if (!empty($typeFilter)) {
        $where .= " AND transaction_type = ?";
        $params[] = $typeFilter;
        $types .= 's';
    }

    if (!empty($statusFilter)) {
        $where .= " AND status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }

    if (!empty($search)) {
        $where .= " AND (ref_number LIKE ? OR party_name LIKE ?)";
        $sParam = "%$search%";
        $params[] = $sParam;
        $params[] = $sParam;
        $types .= 'ss';
    }

    return [$where, $types, $params];
}

/*
 * Profit lines: each customer PO line with its sales amount and its cost
 * (quantity x the cost saved when sold, or the item's current cost price; NULL
 * when the item has no cost price). $basis: 'completed' or 'all' (not cancelled).
 */
function profitLinesSql($basis) {
    $statusCondition = $basis === 'all' ? "o.status != 'cancelled'" : "o.status = 'completed'";
    return "SELECT o.id AS order_id, o.po_number, o.order_date, o.customer_name, oi.total_price AS revenue,
            oi.quantity * COALESCE(NULLIF(oi.unit_cost, 0), NULLIF(il.cost_price, 0)) AS cost
        FROM customer_orders o
        JOIN customer_order_items oi ON oi.customer_order_id = o.id
        LEFT JOIN item_list il ON il.id = oi.item_id
        WHERE o.order_date BETWEEN ? AND ? AND $statusCondition";
}

define('PROFIT_SUMS', "COUNT(DISTINCT order_id) AS orders,
    COALESCE(SUM(revenue), 0) AS revenue,
    COALESCE(SUM(cost), 0) AS cost,
    COALESCE(SUM(CASE WHEN cost IS NOT NULL THEN revenue END), 0) AS costed_revenue,
    COALESCE(SUM(CASE WHEN cost IS NULL THEN revenue END), 0) AS uncosted_revenue");

// Profit and margin from a row of PROFIT_SUMS (profit only on lines with a cost price)
function profitFigures($row) {
    $profit = (float) $row['costed_revenue'] - (float) $row['cost'];
    $margin = (float) $row['costed_revenue'] > 0 ? $profit / (float) $row['costed_revenue'] * 100 : null;
    return [$profit, $margin];
}
