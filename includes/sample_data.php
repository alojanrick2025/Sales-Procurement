<?php
/*
 * Sample purchase orders with line items, loaded once by migrateSchema() (config.php).
 * Totals are calculated the same way as the New Customer PO / New Supplier PO forms,
 * so every PO's grand total matches its items. Notes start with "Sample:" so these
 * orders are easy to tell apart from real ones.
 */

function loadSamplePurchaseOrders($conn) {
    // Load only once, even if two requests run the upgrade at the same moment
    $conn->query("INSERT IGNORE INTO system_info (meta_field, meta_value) VALUES ('sample_purchase_orders_loaded', '1')");
    if ($conn->affected_rows === 0) {
        return;
    }

    // [catalog code, item name, unit, quantity, unit price, markdown % (supplier POs only)]
    $customerOrders = [
        ['BRICOLAGE PHILIPPINES INC.', '2026-09-22', 'completed', 'Sample: hardware for warehouse line extension', [
            ['MB-5/8X8-HDG', 'BOLT, MACHINE 5/8" X 8", HOT DIP GALVANIZED', 'PCS', 200, 85],
            ['OEB-5/8X10-HDG', 'BOLT, OVAL EYE 5/8" X 10", HOT DIP GALVANIZED, FORGED', 'PCS', 100, 145],
            ['SW-2-1/4X2-1/4', 'WASHER SQUARE 2-1/4" X 2-1/4"', 'PCS', 300, 18],
        ]],
        ['MUNICIPALITY OF CALATRAVA', '2026-09-28', 'processing', 'Sample: street lighting poles, Phase 1', [
            ['SP-30FT-3.0MM', 'STEEL POLE 30FT 3.0MM', 'PCS', 6, 18500],
            ['CA-STL-3X4X8-HDG', 'CROSSARM, STEEL, 3" X 4" X 8\', HOT DIP GALVANIZED', 'PCS', 12, 2350],
            ['INS-SUS-6-52-1', 'INSULATOR, SUSPENSION, 6", ANSI CLASS 52-1', 'PCS', 24, 680],
        ]],
        ['SILAY CITY WATER DISTRICT', '2026-10-02', 'pending', 'Sample: pump station power line', [
            ['COND-INS-1/0-ACSR', 'CONDUCTOR, INSULATED, ACSR #1/0, AWG 6/1', 'MTR', 500, 92],
            ['CC-YHD-200', 'CONNECTOR, COMPRESSION, YHD 200', 'PCS', 40, 210],
            ['GR-5/8X10-HDG', 'ROD, GROUND STEEL, GALVANIZED, 5/8" X 10\'', 'PCS', 10, 1250],
        ]],
    ];
    $supplierOrders = [
        ['FRONTIER TOWER ASSOCIATES PHILIPPINES', '2026-09-18', 'completed', 'Sample: bolt restock', [
            ['MB-5/8X8-HDG', 'BOLT, MACHINE 5/8" X 8", HOT DIP GALVANIZED', 'PCS', 500, 62, 5],
            ['OEB-5/8X10-HDG', 'BOLT, OVAL EYE 5/8" X 10", HOT DIP GALVANIZED, FORGED', 'PCS', 200, 110, 0],
        ]],
        ['ISON TOWER', '2026-09-25', 'processing', 'Sample: poles and crossarms for the Calatrava order', [
            ['SP-30FT-3.0MM', 'STEEL POLE 30FT 3.0MM', 'PCS', 10, 14200, 3],
            ['CA-STL-3X4X8-HDG', 'CROSSARM, STEEL, 3" X 4" X 8\', HOT DIP GALVANIZED', 'PCS', 20, 1780, 0],
        ]],
        ['FRONTIER TOWER ASSOCIATES PHILIPPINES', '2026-10-01', 'pending', 'Sample: conductor and connectors', [
            ['COND-INS-1/0-ACSR', 'CONDUCTOR, INSULATED, ACSR #1/0, AWG 6/1', 'MTR', 1000, 68, 2],
            ['CC-YHD-200', 'CONNECTOR, COMPRESSION, YHD 200', 'PCS', 100, 150, 0],
        ]],
    ];

    $findPartner = $conn->prepare("SELECT id FROM clients WHERE name = ? AND partner_type = ? LIMIT 1");
    $findItem = $conn->prepare("SELECT id FROM item_list WHERE description = ? LIMIT 1");
    $lookup = function ($stmt, $types, ...$params) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
    };

    $conn->begin_transaction();
    try {
        foreach (['customer' => $customerOrders, 'supplier' => $supplierOrders] as $type => $orders) {
            $isSupplier = $type === 'supplier';
            foreach ($orders as [$partnerName, $orderDate, $status, $notes, $lines]) {
                $partnerId = $lookup($findPartner, "ss", $partnerName, $type);

                $items = [];
                $total = 0;
                foreach ($lines as $line) {
                    [$code, $name, $unit, $qty, $price] = $line;
                    $markdown = $line[5] ?? 0;
                    $lineTotal = $qty * $price * (1 - $markdown / 100);
                    $total += $lineTotal;
                    $items[] = [$lookup($findItem, "s", $code), $name, $code, $unit, (float) $qty, (float) $price, (float) $markdown, $lineTotal];
                }

                // Placeholder number, replaced below by the app's own format (PREFIX-YEAR-<id>)
                $tempNumber = 'TMP-' . bin2hex(random_bytes(8));
                if ($isSupplier) {
                    $stmt = $conn->prepare("INSERT INTO supplier_orders (po_number, supplier_id, supplier_name, order_date, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                } else {
                    $stmt = $conn->prepare("INSERT INTO customer_orders (po_number, customer_id, customer_name, order_date, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                }
                $stmt->bind_param("sissdss", $tempNumber, $partnerId, $partnerName, $orderDate, $total, $status, $notes);
                $stmt->execute();
                $orderId = $conn->insert_id;
                $stmt->close();

                $table = $isSupplier ? 'supplier_orders' : 'customer_orders';
                $poNumber = ($isSupplier ? 'SPO-' : 'CPO-') . substr($orderDate, 0, 4) . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
                try {
                    $stmt = $conn->prepare("UPDATE `$table` SET po_number = ? WHERE id = ?");
                    $stmt->bind_param("si", $poNumber, $orderId);
                    $stmt->execute();
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() !== 1062) { // 1062 = a real PO already uses that number
                        throw $e;
                    }
                    $poNumber .= '-S';
                    $stmt = $conn->prepare("UPDATE `$table` SET po_number = ? WHERE id = ?");
                    $stmt->bind_param("si", $poNumber, $orderId);
                    $stmt->execute();
                }
                $stmt->close();

                if ($isSupplier) {
                    $stmt = $conn->prepare("INSERT INTO supplier_order_items (supplier_order_id, item_id, item_name, description, unit, quantity, unit_price, markdown_rate, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($items as [$itemId, $name, $code, $unit, $qty, $price, $markdown, $lineTotal]) {
                        $stmt->bind_param("iisssdddd", $orderId, $itemId, $name, $code, $unit, $qty, $price, $markdown, $lineTotal);
                        $stmt->execute();
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO customer_order_items (customer_order_id, item_id, item_name, description, unit, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($items as [$itemId, $name, $code, $unit, $qty, $price, $markdown, $lineTotal]) {
                        $stmt->bind_param("iisssddd", $orderId, $itemId, $name, $code, $unit, $qty, $price, $lineTotal);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        // Sample data is optional: keep the site working and leave no partial orders behind
        $conn->rollback();
        $conn->query("DELETE FROM system_info WHERE meta_field = 'sample_purchase_orders_loaded'");
        error_log('Sample purchase orders were not loaded: ' . $e->getMessage());
    }
    $findPartner->close();
    $findItem->close();
}

/*
 * Sample quotations with line items. The grand total is calculated like the Create
 * Quotation form: sum of quantity x price x (1 + markup %), plus the S.O.P. amount.
 * Numbers continue after the highest existing QT-YYYY-NNNN for that year.
 */
function loadSampleQuotations($conn) {
    $conn->query("INSERT IGNORE INTO system_info (meta_field, meta_value) VALUES ('sample_quotations_loaded', '1')");
    if ($conn->affected_rows === 0) {
        return;
    }

    // [customer, created, valid until, status, notes, [[catalog code, item name, unit, quantity, unit price, markup %]]]
    $quotations = [
        ['BRICOLAGE PHILIPPINES INC.', '2026-09-20', '2026-10-20', 'sent', 'Sample: hardware for warehouse line extension', [
            ['MB-5/8X8-HDG', 'BOLT, MACHINE 5/8" X 8", HOT DIP GALVANIZED', 'PCS', 200, 70, 20],
            ['OEB-5/8X10-HDG', 'BOLT, OVAL EYE 5/8" X 10", HOT DIP GALVANIZED, FORGED', 'PCS', 100, 120, 20],
            ['SW-2-1/4X2-1/4', 'WASHER SQUARE 2-1/4" X 2-1/4"', 'PCS', 300, 15, 20],
        ]],
        ['MUNICIPALITY OF CALATRAVA', '2026-09-24', '2026-10-24', 'accepted', 'Sample: street lighting poles, Phase 1', [
            ['SP-30FT-3.0MM', 'STEEL POLE 30FT 3.0MM', 'PCS', 6, 15500, 15],
            ['CA-STL-3X4X8-HDG', 'CROSSARM, STEEL, 3" X 4" X 8\', HOT DIP GALVANIZED', 'PCS', 12, 1950, 15],
            ['INS-SUS-6-52-1', 'INSULATOR, SUSPENSION, 6", ANSI CLASS 52-1', 'PCS', 24, 560, 15],
        ]],
        ['SILAY CITY WATER DISTRICT', '2026-10-03', '2026-11-02', 'draft', 'Sample: pump station power line', [
            ['COND-INS-1/0-ACSR', 'CONDUCTOR, INSULATED, ACSR #1/0, AWG 6/1', 'MTR', 500, 75, 18],
            ['CC-YHD-200', 'CONNECTOR, COMPRESSION, YHD 200', 'PCS', 40, 170, 18],
            ['GR-5/8X10-HDG', 'ROD, GROUND STEEL, GALVANIZED, 5/8" X 10\'', 'PCS', 10, 1050, 18],
        ]],
    ];

    $findClient = $conn->prepare("SELECT id, address, phone, email FROM clients WHERE name = ? AND partner_type = 'customer' LIMIT 1");
    $findItem = $conn->prepare("SELECT id FROM item_list WHERE description = ? LIMIT 1");
    $admin = $conn->query("SELECT id FROM users WHERE user_type = 'admin' ORDER BY id LIMIT 1")->fetch_assoc();
    $createdBy = $admin ? (int) $admin['id'] : null;

    $conn->begin_transaction();
    try {
        foreach ($quotations as [$clientName, $created, $validUntil, $status, $notes, $lines]) {
            $findClient->bind_param("s", $clientName);
            $findClient->execute();
            $client = $findClient->get_result()->fetch_assoc() ?: [];
            $clientId = isset($client['id']) ? (int) $client['id'] : null;
            $email = $client['email'] ?? '';
            $phone = $client['phone'] ?? '';
            $address = $client['address'] ?? '';

            $items = [];
            $subtotal = 0;
            foreach ($lines as [$code, $name, $unit, $qty, $price, $markup]) {
                $lineTotal = $qty * $price * (1 + $markup / 100);
                $subtotal += $lineTotal;
                $findItem->bind_param("s", $code);
                $findItem->execute();
                $row = $findItem->get_result()->fetch_assoc();
                $items[] = [$row ? (int) $row['id'] : null, $name, $code, $unit, (float) $qty, (float) $price, (float) $markup, $lineTotal];
            }
            $sopAmount = 0.0;
            $grandTotal = $subtotal + $sopAmount;
            $zero = 0.0;

            // Next number for that year, the same way Create Quotation numbers them
            $year = substr($created, 0, 4);
            $maxSeq = 0;
            $res = $conn->query("SELECT quotation_number FROM quotations WHERE quotation_number LIKE 'QT-$year-%'");
            while ($r = $res->fetch_assoc()) {
                $parts = explode('-', $r['quotation_number']);
                $maxSeq = max($maxSeq, intval(end($parts)));
            }
            $number = sprintf('QT-%s-%04d', $year, $maxSeq + 1);
            $createdAt = $created . ' 09:00:00';

            $stmt = $conn->prepare("INSERT INTO quotations (quotation_number, client_id, client_name, client_email, client_phone, client_address, total_amount, tax_rate, tax_amount, markup_rate, markup_amount, sop_amount, grand_total, valid_until, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssdddddddsssis", $number, $clientId, $clientName, $email, $phone, $address, $subtotal, $zero, $zero, $zero, $zero, $sopAmount, $grandTotal, $validUntil, $status, $notes, $createdBy, $createdAt);
            $stmt->execute();
            $quoteId = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_id, item_name, description, unit, quantity, unit_price, markup_rate, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($items as [$itemId, $name, $code, $unit, $qty, $price, $markup, $lineTotal]) {
                $stmt->bind_param("iisssdddd", $quoteId, $itemId, $name, $code, $unit, $qty, $price, $markup, $lineTotal);
                $stmt->execute();
            }
            $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        // Sample data is optional: keep the site working and leave no partial quotations behind
        $conn->rollback();
        $conn->query("DELETE FROM system_info WHERE meta_field = 'sample_quotations_loaded'");
        error_log('Sample quotations were not loaded: ' . $e->getMessage());
    }
    $findClient->close();
    $findItem->close();
}
