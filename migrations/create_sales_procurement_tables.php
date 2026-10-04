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

echo "MIGRATION COMPLETE!\n";
