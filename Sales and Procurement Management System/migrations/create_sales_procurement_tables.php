<?php
require_once __DIR__ . '/../config.php';
$conn = getDBConnection();

// 1. Create customer_orders table
$sql1 = "CREATE TABLE IF NOT EXISTS `customer_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL UNIQUE,
  `quotation_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `order_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// 2. Create supplier_orders table
$sql2 = "CREATE TABLE IF NOT EXISTS `supplier_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL UNIQUE,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `order_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!$conn->query($sql1)) {
    die("Error creating customer_orders table: " . $conn->error . "\n");
}
if (!$conn->query($sql2)) {
    die("Error creating supplier_orders table: " . $conn->error . "\n");
}
echo "[OK] Tables customer_orders and supplier_orders created.\n";

// 3. Seed suppliers if empty
$supCheck = $conn->query("SELECT COUNT(*) as c FROM supplier");
if ($supCheck->fetch_assoc()['c'] == 0) {
    $conn->query("INSERT INTO supplier (name, contact_person, email, phone, address, city, country, status) VALUES
    ('Justly Electrical Hardware Supplies', 'Robert Ramos', 'sales@justlyelectrical.com', '09953508617', 'Capitan Sabi, Brgy. Zone 4', 'Talisay City', 'Philippines', 'active'),
    ('SteelAsia Manufacturing Corp.', 'Maria Santos', 'orders@steelasia.com', '09171234567', 'Bacolod Port Area', 'Bacolod City', 'Philippines', 'active'),
    ('PhilMetal Products Inc.', 'David Tan', 'sales@philmetal.com.ph', '09228889999', 'Mandaue Industrial Park', 'Cebu City', 'Philippines', 'active'),
    ('Apex Galvanizing & Fasteners Ltd.', 'Elena Cruz', 'info@apexfas.ph', '09185551234', 'Subic Bay Freeport Zone', 'Olongapo City', 'Philippines', 'active')");
    echo "[OK] Seeded supplier records.\n";
}

// 4. Seed customer_orders if empty
$custCheck = $conn->query("SELECT COUNT(*) as c FROM customer_orders");
if ($custCheck->fetch_assoc()['c'] == 0) {
    $conn->query("INSERT INTO customer_orders (po_number, quotation_id, customer_id, customer_name, order_date, total_amount, status, notes) VALUES
    ('CPO-2026-0001', 1, 1, 'BRICOLAGE PHILIPPINES INC.', '2026-09-12', 50400.00, 'processing', 'PO for HDG Bolts and Machine Assemblies'),
    ('CPO-2026-0002', 2, 2, 'FRONTIER TOWER ASSOCIATES PHILIPPINES', '2026-09-10', 35000.00, 'completed', 'Tower grounding and hardware installation package'),
    ('CPO-2026-0003', NULL, 4, 'SILAY CITY WATER DISTRICT', '2026-09-08', 42500.00, 'completed', 'Flange bolts, machine bolts and hardware accessories'),
    ('CPO-2026-0004', NULL, 3, 'MUNICIPALITY OF CALATRAVA', '2026-09-13', 18750.00, 'pending', 'Awaiting municipal council approval signature'),
    ('CPO-2026-0005', NULL, 5, 'Sagay City Water District', '2026-09-05', 12000.00, 'cancelled', 'Client requested project cancellation due to revised specs')");
    echo "[OK] Seeded customer_orders records.\n";
}

// 5. Seed supplier_orders if empty
$suppCheck = $conn->query("SELECT COUNT(*) as c FROM supplier_orders");
if ($suppCheck->fetch_assoc()['c'] == 0) {
    $conn->query("INSERT INTO supplier_orders (po_number, supplier_id, supplier_name, order_date, total_amount, status, notes) VALUES
    ('SPO-2026-0001', 2, 'SteelAsia Manufacturing Corp.', '2026-09-11', 28500.00, 'completed', 'HDG Round Bars and structural bolts restock'),
    ('SPO-2026-0002', 3, 'PhilMetal Products Inc.', '2026-09-12', 19800.00, 'processing', 'Machine bolts and carriage bolts batch replenish'),
    ('SPO-2026-0003', 1, 'Justly Electrical Hardware Supplies', '2026-09-13', 8400.00, 'pending', 'Procurement of specialized eye nuts and lag screws'),
    ('SPO-2026-0004', 4, 'Apex Galvanizing & Fasteners Ltd.', '2026-09-06', 15200.00, 'cancelled', 'Duplicate procurement order cancelled')");
    echo "[OK] Seeded supplier_orders records.\n";
}

echo "MIGRATION COMPLETE!\n";
