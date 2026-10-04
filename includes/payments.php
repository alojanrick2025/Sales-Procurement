<?php
/*
 * Payments against purchase orders: customers paying us (receivables, customer POs)
 * and us paying suppliers (payables, supplier POs). An order's balance is its total
 * minus its payments; it is overdue when a balance remains after its due date.
 * Cancelled orders are left out of balances.
 */

require_once __DIR__ . '/../config.php';

define('PAYMENT_METHODS', ['Cash', 'Bank Transfer', 'Check', 'GCash', 'Other']);

function paymentOrderTable($kind) {
    return $kind === 'supplier' ? 'supplier_orders' : 'customer_orders';
}

// Payments per order, for joining onto an orders table as "p"
function paidSubquery($kind) {
    $kind = $kind === 'supplier' ? 'supplier' : 'customer';
    return "(SELECT order_id, SUM(amount) AS paid FROM payments WHERE order_type = '$kind' GROUP BY order_id)";
}

/**
 * Payment status of an order: paid amount, balance, and a label and badge class.
 * $dueDate is 'YYYY-MM-DD' or null.
 */
function paymentSummary($total, $paid, $dueDate, $orderStatus) {
    $total = round((float) $total, 2);
    $paid = round((float) $paid, 2);
    $balance = round($total - $paid, 2);
    $today = date('Y-m-d');
    $summary = ['total' => $total, 'paid' => $paid, 'balance' => $balance, 'overdue' => false, 'days_overdue' => 0];

    if ($orderStatus === 'cancelled') {
        return $summary + ['label' => 'Cancelled', 'badge' => 'bg-secondary'];
    }
    if ($balance < -0.004) {
        return $summary + ['label' => 'Overpaid', 'badge' => 'bg-info text-dark'];
    }
    if ($balance <= 0.004) {
        return $summary + ['label' => 'Paid', 'badge' => 'bg-success'];
    }
    if ($dueDate && $dueDate < $today) {
        $summary['overdue'] = true;
        $summary['days_overdue'] = (int) ((strtotime($today) - strtotime($dueDate)) / 86400);
        return $summary + ['label' => 'Overdue', 'badge' => 'bg-danger'];
    }
    if ($paid > 0) {
        return $summary + ['label' => 'Partially Paid', 'badge' => 'bg-warning text-dark'];
    }
    return $summary + ['label' => 'Unpaid', 'badge' => 'bg-light text-dark border'];
}

function orderPayments($conn, $kind, $orderId) {
    $stmt = $conn->prepare("SELECT p.*, u.full_name, u.username FROM payments p LEFT JOIN users u ON u.id = p.created_by
        WHERE p.order_type = ? AND p.order_id = ? ORDER BY p.payment_date ASC, p.id ASC");
    $stmt->bind_param("si", $kind, $orderId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Record a payment. The amount must be positive and not more than the balance.
 * Returns ['ok' => true] or ['ok' => false, 'error' => string].
 */
function addPayment($conn, $kind, $orderId, $amount, $date, $method, $reference, $notes) {
    $amount = round((float) $amount, 2);
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Enter a payment amount greater than zero.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || !strtotime($date)) {
        return ['ok' => false, 'error' => 'Enter a valid payment date.'];
    }
    if (!in_array($method, PAYMENT_METHODS, true)) {
        $method = 'Other';
    }
    $table = paymentOrderTable($kind);
    $conn->begin_transaction();
    try {
        // Lock the order so two payments saved at once cannot both pass the balance check
        $stmt = $conn->prepare("SELECT status, total_amount FROM `$table` WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if ($order['status'] === 'cancelled') {
            $conn->rollback();
            return ['ok' => false, 'error' => 'Payments cannot be recorded on a cancelled order.'];
        }
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS paid FROM payments WHERE order_type = ? AND order_id = ?");
        $stmt->bind_param("si", $kind, $orderId);
        $stmt->execute();
        $paid = (float) $stmt->get_result()->fetch_assoc()['paid'];
        $stmt->close();
        $balance = round((float) $order['total_amount'] - $paid, 2);
        if ($amount > $balance + 0.004) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'The amount is more than the balance of ₱' . number_format(max(0, $balance), 2) . '.'];
        }
        $userId = $_SESSION['user_id'] ?? null;
        $reference = mb_substr(trim((string) $reference), 0, 100);
        $notes = mb_substr(trim((string) $notes), 0, 255);
        $stmt = $conn->prepare("INSERT INTO payments (order_type, order_id, amount, payment_date, method, reference, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sidssssi", $kind, $orderId, $amount, $date, $method, $reference, $notes, $userId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Payment not saved: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'The payment could not be saved. Please try again.'];
    }
}

function deletePayment($conn, $kind, $orderId, $paymentId) {
    $stmt = $conn->prepare("DELETE FROM payments WHERE id = ? AND order_type = ? AND order_id = ?");
    $stmt->bind_param("isi", $paymentId, $kind, $orderId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    return $deleted;
}

/**
 * Receivables (customer) or payables (supplier) for the dashboard:
 * total outstanding, overdue amount and count, and the most overdue orders.
 */
function outstandingSummary($conn, $kind, $limit = 5) {
    $table = paymentOrderTable($kind);
    $party = $kind === 'supplier' ? 'supplier_name' : 'customer_name';
    $today = date('Y-m-d');
    $base = "FROM `$table` o LEFT JOIN " . paidSubquery($kind) . " p ON p.order_id = o.id
        WHERE o.status != 'cancelled' AND o.total_amount - COALESCE(p.paid, 0) > 0.004";

    $stmt = $conn->prepare("SELECT COUNT(*) AS orders,
            COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0)), 0) AS outstanding,
            COUNT(CASE WHEN o.due_date < ? THEN 1 END) AS overdue_count,
            COALESCE(SUM(CASE WHEN o.due_date < ? THEN o.total_amount - COALESCE(p.paid, 0) END), 0) AS overdue_amount
        $base");
    $stmt->bind_param("ss", $today, $today);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT o.id, o.po_number, o.$party AS party, o.due_date, o.total_amount - COALESCE(p.paid, 0) AS balance
        $base AND o.due_date < ? ORDER BY o.due_date ASC, o.id ASC LIMIT ?");
    $stmt->bind_param("si", $today, $limit);
    $stmt->execute();
    $overdue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $totals + ['overdue_orders' => $overdue];
}
