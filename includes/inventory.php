<?php
/*
 * Inventory: every change to an item's stock goes through these functions, which
 * update item_list.stocks and record the change in stock_movements (the stock history).
 *
 * Purchase orders: a supplier PO adds its items to stock when it is Completed, a
 * customer PO deducts them; moving an order away from Completed reverses that.
 * orders.stock_applied remembers whether an order's items are currently counted.
 */

require_once __DIR__ . '/../config.php';

// Movement types shown in the stock history
define('STOCK_MOVEMENT_TYPES', [
    'receipt'    => 'Received (Supplier PO)',
    'delivery'   => 'Delivered (Customer PO)',
    'reversal'   => 'PO reversed',
    'adjustment' => 'Manual adjustment',
    'opening'    => 'Opening stock',
]);

/**
 * Change one item's stock by $delta and record it. Must run inside a transaction
 * (the item row is locked until commit). Returns the new balance, or null if the
 * item does not exist.
 */
function moveStock($conn, $itemId, $delta, $type, $note = null, $reference = [null, null, null]) {
    $stmt = $conn->prepare("SELECT name, stocks FROM item_list WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$item) {
        return null;
    }

    $delta = round((float) $delta, 2);
    $balance = round((float) $item['stocks'] + $delta, 2);
    $stmt = $conn->prepare("UPDATE item_list SET stocks = ? WHERE id = ?");
    $stmt->bind_param("di", $balance, $itemId);
    $stmt->execute();
    $stmt->close();

    [$refType, $refId, $refNumber] = $reference;
    $name = html_entity_decode(str_ireplace('&quot;', '"', (string) $item['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $note = $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null;
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO stock_movements (item_id, item_name, quantity_change, balance_after, movement_type, reference_type, reference_id, reference_number, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isddssissi", $itemId, $name, $delta, $balance, $type, $refType, $refId, $refNumber, $note, $userId);
    $stmt->execute();
    $stmt->close();

    return $balance;
}

/**
 * Manual adjustment from the Stocks / Items pages, in its own transaction.
 * $mode: 'add', 'subtract' or 'set'. Stock never goes below zero.
 * Returns ['ok' => true, 'balance' => float, 'change' => float, 'name' => string]
 * or ['ok' => false, 'error' => string].
 */
function adjustStock($conn, $itemId, $mode, $quantity, $note = null, $type = 'adjustment') {
    $quantity = round((float) $quantity, 2);
    if ($quantity < 0 || !in_array($mode, ['add', 'subtract', 'set'], true)) {
        return ['ok' => false, 'error' => 'Invalid quantity.'];
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT name, stocks FROM item_list WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'Item not found.'];
        }
        $current = round((float) $item['stocks'], 2);
        if ($mode === 'add') {
            $target = $current + $quantity;
        } elseif ($mode === 'subtract') {
            $target = max(0, $current - $quantity);
        } else {
            $target = $quantity;
        }
        $delta = round($target - $current, 2);
        $balance = $current;
        if ($delta != 0) {
            $balance = moveStock($conn, $itemId, $delta, $type, $note);
        }
        $conn->commit();
        return ['ok' => true, 'balance' => $balance, 'change' => $delta, 'name' => $item['name']];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Stock adjustment failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'The stock could not be updated. Please try again.'];
    }
}

function orderStockConfig($kind) {
    return $kind === 'supplier'
        ? ['table' => 'supplier_orders', 'items' => 'supplier_order_items', 'fk' => 'supplier_order_id', 'sign' => 1, 'type' => 'receipt', 'label' => 'Supplier PO']
        : ['table' => 'customer_orders', 'items' => 'customer_order_items', 'fk' => 'customer_order_id', 'sign' => -1, 'type' => 'delivery', 'label' => 'Customer PO'];
}

/**
 * Bring an order's stock in line with its status: apply its items when it is
 * Completed and not yet applied, reverse them when it is no longer Completed.
 * Must run inside the caller's transaction (after the status is saved).
 * Nothing is changed if any item would go below zero.
 * Returns ['ok' => true, 'action' => 'applied'|'reversed'|null, 'items' => n, 'skipped' => [names]]
 * or ['ok' => false, 'error' => string].
 */
function syncOrderStock($conn, $kind, $orderId) {
    $order = lockOrderForStock($conn, $kind, $orderId);
    if (!$order) {
        return ['ok' => false, 'error' => 'Order not found.'];
    }
    $shouldApply = $order['status'] === 'completed';
    if ($shouldApply === (bool) $order['stock_applied']) {
        return ['ok' => true, 'action' => null, 'items' => 0, 'skipped' => [], 'item_ids' => []];
    }
    return moveOrderStock($conn, $kind, $order, $shouldApply);
}

/**
 * Take an order's items back out of stock if they are currently counted, whatever
 * its status (used before editing an order's items; syncOrderStock() re-applies them).
 * Must run inside the caller's transaction.
 */
function reverseOrderStock($conn, $kind, $orderId, $checkStock = true) {
    $order = lockOrderForStock($conn, $kind, $orderId);
    if (!$order) {
        return ['ok' => false, 'error' => 'Order not found.'];
    }
    if (!$order['stock_applied']) {
        return ['ok' => true, 'action' => null, 'items' => 0, 'skipped' => [], 'item_ids' => []];
    }
    return moveOrderStock($conn, $kind, $order, false, $checkStock);
}

/**
 * Catalog items currently counted for an order, as [item_id => quantity]
 * (used to tell whether an edit changed the items at all)
 */
function orderStockItems($conn, $kind, $orderId) {
    $cfg = orderStockConfig($kind);
    $stmt = $conn->prepare("SELECT item_id, SUM(quantity) AS qty FROM `{$cfg['items']}` WHERE `{$cfg['fk']}` = ? AND item_id IS NOT NULL GROUP BY item_id");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $items = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $items[(int) $row['item_id']] = round((float) $row['qty'], 2);
    }
    $stmt->close();
    ksort($items);
    return $items;
}

// Items (by id) whose stock is below zero, as "Name (would be -N)"; empty when all is well
function negativeStockItems($conn, $itemIds) {
    $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
    if (!$itemIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $conn->prepare("SELECT name, stocks FROM item_list WHERE id IN ($placeholders) AND stocks < -0.001");
    $stmt->bind_param(str_repeat('i', count($itemIds)), ...$itemIds);
    $stmt->execute();
    $short = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $name = html_entity_decode(str_ireplace('&quot;', '"', $row['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $short[] = $name . ' (stock would be ' . (0 + round((float) $row['stocks'], 2)) . ')';
    }
    $stmt->close();
    return $short;
}

function lockOrderForStock($conn, $kind, $orderId) {
    $cfg = orderStockConfig($kind);
    $stmt = $conn->prepare("SELECT id, po_number, status, stock_applied FROM `{$cfg['table']}` WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $order;
}

// Add ($apply = true) or remove an order's items from stock and set stock_applied
function moveOrderStock($conn, $kind, $order, $shouldApply, $checkStock = true) {
    $cfg = orderStockConfig($kind);
    $orderId = (int) $order['id'];

    // Quantities per catalog item (an item may appear on several lines)
    $markdown = $kind === 'supplier' ? 'markdown_rate' : '0 AS markdown_rate';
    $stmt = $conn->prepare("SELECT oi.item_id, oi.item_name, oi.quantity, oi.unit_price, $markdown, il.id AS catalog_id, il.name AS catalog_name, il.stocks
        FROM `{$cfg['items']}` oi LEFT JOIN item_list il ON il.id = oi.item_id
        WHERE oi.`{$cfg['fk']}` = ? ORDER BY oi.id");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $sign = $shouldApply ? $cfg['sign'] : -$cfg['sign'];
    $changes = [];
    $skipped = [];
    foreach ($rows as $row) {
        if ($row['catalog_id'] === null) {
            $skipped[] = $row['item_name'];
            continue;
        }
        $id = (int) $row['catalog_id'];
        if (!isset($changes[$id])) {
            $changes[$id] = ['delta' => 0, 'name' => $row['catalog_name'], 'stock' => (float) $row['stocks'], 'cost' => null];
        }
        $changes[$id]['delta'] += $sign * (float) $row['quantity'];
        // What was actually paid per unit, after the supplier's markdown
        $changes[$id]['cost'] = round((float) $row['unit_price'] * (1 - (float) $row['markdown_rate'] / 100), 2);
    }

    // Refuse before changing anything if stock would go below zero
    $short = [];
    foreach ($changes as $id => $change) {
        if ($change['delta'] < 0 && $change['stock'] + $change['delta'] < -0.001) {
            $name = html_entity_decode(str_ireplace('&quot;', '"', $change['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $short[] = $name . ' (in stock ' . (0 + round($change['stock'], 2)) . ', needed ' . (0 + round(-$change['delta'], 2)) . ')';
        }
    }
    if ($short && $checkStock) {
        if ($shouldApply) {
            $what = 'complete this order';
        } elseif ($order['status'] === 'completed') {
            $what = 'change this order';
        } else {
            $what = 'reverse this order';
        }
        return ['ok' => false, 'error' => "Not enough stock to $what: " . implode('; ', $short) . '. Receive or adjust the stock first.'];
    }

    $reference = [$kind . '_order', (int) $order['id'], $order['po_number']];
    $type = $shouldApply ? $cfg['type'] : 'reversal';
    if ($shouldApply) {
        $note = $cfg['label'] . ' ' . $order['po_number'] . ' completed';
    } elseif ($order['status'] === 'completed') {
        $note = $cfg['label'] . ' ' . $order['po_number'] . ' edited (previous items reversed)';
    } else {
        $note = $cfg['label'] . ' ' . $order['po_number'] . ' changed from Completed to ' . ucfirst($order['status']);
    }
    foreach ($changes as $id => $change) {
        if (abs($change['delta']) >= 0.005) {
            moveStock($conn, $id, $change['delta'], $type, $note, $reference);
        }
        // Receiving a supplier order records what the item now costs
        if ($kind === 'supplier' && $shouldApply && $change['cost'] > 0) {
            $stmt = $conn->prepare("UPDATE item_list SET cost_price = ? WHERE id = ?");
            $stmt->bind_param("di", $change['cost'], $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $applied = $shouldApply ? 1 : 0;
    $stmt = $conn->prepare("UPDATE `{$cfg['table']}` SET stock_applied = ? WHERE id = ?");
    $stmt->bind_param("ii", $applied, $orderId);
    $stmt->execute();
    $stmt->close();

    return ['ok' => true, 'action' => $shouldApply ? 'applied' : 'reversed', 'items' => count($changes), 'skipped' => $skipped, 'item_ids' => array_keys($changes)];
}

// One-line summary of syncOrderStock() for the page message
function orderStockMessage($kind, $result) {
    if (empty($result['action'])) {
        return '';
    }
    $n = $result['items'];
    $items = $n . ' item' . ($n === 1 ? '' : 's');
    if ($result['action'] === 'applied') {
        $message = $kind === 'supplier' ? " Stock received: $items added to inventory." : " Stock delivered: $items deducted from inventory.";
    } else {
        $message = $kind === 'supplier' ? " Stock reversed: $items removed from inventory." : " Stock reversed: $items returned to inventory.";
    }
    if ($result['skipped']) {
        $message .= ' Not linked to an inventory item, so not counted: ' . implode(', ', $result['skipped']) . '.';
    }
    return $message;
}

// Summary for an edited order: its old items were reversed and/or its new items applied
function orderEditStockMessage($kind, $reverseResult, $applyResult) {
    $reversed = !empty($reverseResult['action']);
    $applied = !empty($applyResult['action']);
    if ($reversed && $applied) {
        $message = ' Stock updated to match the changed items.';
        if ($applyResult['skipped']) {
            $message .= ' Not linked to an inventory item, so not counted: ' . implode(', ', $applyResult['skipped']) . '.';
        }
        return $message;
    }
    return $reversed ? orderStockMessage($kind, $reverseResult) : orderStockMessage($kind, $applyResult);
}
