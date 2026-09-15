<?php
/**
 * Create item_list Table Script
 * This script will create the item_list table in the database
 */

require_once __DIR__ . '/../config.php';

// Only allow admin access
if (!isAdminLoggedIn()) {
    die("Access denied. Admin login required.");
}

$conn = getDBConnection();

// Check if table already exists
$table_check = $conn->query("SHOW TABLES LIKE 'item_list'");

$table_exists = $table_check->num_rows > 0;
$data_inserted = false;
$items_count = 0;

if ($table_exists) {
    // Check if data already exists
    $count_result = $conn->query("SELECT COUNT(*) as count FROM item_list");
    if ($count_result) {
        $count_row = $count_result->fetch_assoc();
        $items_count = $count_row['count'];
    }
    
    if ($items_count > 0) {
        $message = "Table 'item_list' already exists with {$items_count} items!";
        $alert_type = "warning";
        $action = "exists";
    } else {
        $action = "exists_empty";
    }
} else {
    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS item_list (
        id INT(30) NOT NULL AUTO_INCREMENT,
        description VARCHAR(250) NOT NULL,
        unit TEXT NOT NULL,
        price FLOAT NOT NULL,
        name TEXT NOT NULL,
        stocks FLOAT NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Active, 0 = Inactive',
        date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql) === TRUE) {
        $action = "created";
    } else {
        $message = "Error creating table: " . $conn->error;
        $alert_type = "danger";
        $action = "error";
    }
}

// Insert data if table was just created or is empty
if ($action == "created" || $action == "exists_empty") {
    // Prepare the INSERT statement
    $insert_sql = "INSERT INTO item_list (id, description, unit, price, name, stocks, status, date_created) VALUES 
(7, 'ANE8', 'UNIT', 0, 'ANCHOR, EXPANDING, 8 -WAYS, HOT DIP GALVANIZED', 1, 1, '2023-10-08 16:35:58'),
(8, 'ANLOGW', 'UNIT', 0, 'ANCHOR, LOG, WOOD 8&QUOT; X 4&#039; ', 100, 1, '2023-10-08 16:36:17'),
(9, 'ANLOGC', 'UNIT', 0, 'ANCHOR, LOG, CONCRETE, 8&QUOT; X 4&#039; ', 100, 1, '2023-10-08 16:36:36'),
(10, 'ANSC348', 'UNIT', 0, 'ANCHOR, SCREW EYE SHAFT, 3/4&QUOT; X 8&#039;, HDG, FORGED ', 100, 1, '2023-10-08 16:36:55'),
(11, 'ANSH34', 'UNIT', 0, 'ANCHOR, SHACKLE, 3/4&QUOT;, DROP FORGED STEEL, 69KV ', 91, 1, '2023-10-08 16:37:12'),
(12, 'ATTG1116', 'UNIT', 0, 'ATTACHMENT, GUY, MALLEABLE TYPE WITH 11/16', 100, 1, '2023-10-08 16:37:34'),
(17, 'BOLTM588', 'PCS', 0, 'BOLT, MACHINE 5/8&QUOT; X 8&QUOT;, HOT DIP GALVANIZED ', 51, 1, '2023-11-03 18:22:41'),
(18, 'BOLTM5810', 'PCS', 0, 'BOLT, MACHINE 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED ', 100, 1, '2023-11-03 18:23:08'),
(19, 'BOLTOE5810', 'PCS', 0, 'BOLT, OVAL EYE 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED, FORGED ', 100, 1, '2023-11-03 18:25:04'),
(20, 'BRACKETSWOS', 'PCS', 0, 'BRACKET, SECONDARY WITHOUT SPOOL ', 100, 1, '2023-11-03 18:25:32'),
(21, 'CLAMPLOOPDE2', 'PCS', 0, 'CLAMP, LOOP DEAD-END, #2 ACSR ', 100, 1, '2023-11-03 18:26:02'),
(22, 'BOLTDUPST5810', 'PCS', 0, 'BOLT, DOUBLE UPSET 5/8&QUOT; X 10&QUOT;, HOT OLP GALVANIZED, FORGED ', 41, 1, '2023-11-03 18:26:36'),
(23, 'WASHERSF223', 'PCS', 0, 'WASHER SQUARE 2&FRAC14; X 2&FRAC14;', 100, 1, '2023-11-05 03:42:38'),
(24, 'WASHERSF441', 'PCS', 0, 'WASHER SQUARE 4X4X&FRAC12;', 100, 1, '2023-11-05 03:43:32'),
(25, 'POLE30S', 'PCS', 0, 'STEEL POLE 30FT 3.0MM', 100, 1, '2023-11-05 03:43:59'),
(26, 'POLE35S', 'PCS', 0, 'STEEL POLE 35FT 3.0MM', 61, 1, '2023-11-05 03:44:19'),
(27, 'CLEVISSCSWOS', 'PCS', 0, 'CLEVIS, SECONDARY SWINGING WITHOUT SPOOL, HOT DIP GALVANIZED ', 31, 1, '2023-11-05 03:53:39'),
(28, 'CONDBARE10', 'MTS', 0, 'CONDUCTOR, BARE, ACSR #1/0, AWG 6/1 (METERS) ', 100, 1, '2023-11-05 03:54:17'),
(29, 'NUTE58CON', 'PCS', 0, 'NUT, EYE, 5/8&QUOT;, CONVENTIONAL, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 03:54:55'),
(30, 'RODATSE587', 'PCS', 0, 'ROD, ANCHOR, THREADED, SINGLE EYE, 5/8&QUOT; X 7&#039;, HOT DIP GALVANIZED, FORGED ', 100, 1, '2023-11-05 03:56:57'),
(31, 'BOLTM1210', 'PCS', 0, 'BOLT, MACHINE 1/2&QUOT; X 10&QUOT;, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 03:57:55'),
(32, 'BOLTSUPS5810', 'PCS', 0, 'BOLT, SINGLE UPSET 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED, FORGED ', 100, 1, '2023-11-05 04:00:03'),
(33, 'RODGS5810', 'PCS', 0, 'ROD, GROUND STEEL, GALVANIZED, 5/8&QUOT; X 10&#039;, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 04:01:42'),
(34, 'CLAMPDES2', 'PCS', 0, 'CLAMP, DEAD-END STRAIN, #2 ACSR ', 100, 1, '2023-11-05 04:02:03'),
(35, 'INSSUS652-1', 'PCS', 0, 'INSULATOR, SUSPENSION, 6&QUOT;, ANSI CLASS 52-1 ', 100, 1, '2023-11-05 04:02:22'),
(36, 'CONCOMPYHD150', 'PCS', 0, 'CONNECTOR COMPRESSION YHD 150	', 100, 1, '2023-11-05 04:04:23'),
(37, 'CONCOMPYHD200', 'PCS', 0, 'CONNECTOR, COMPRESSION, YHD 200, RUN #1/0 -#2/0 -TAP #6 -#2 ', 100, 1, '2023-11-05 04:05:21'),
(38, 'CONDINS10', 'MTR', 0, 'CONDUCTOR, INSULATED, ACSR #1/0, AWG 6/1 (METERS) ', 100, 1, '2023-11-05 04:09:51'),
(39, 'INSSPOOL53-1', 'PCS', 0, 'INSULATOR, SPOOL, 1-3/8&QUOT;, ANSI, CLASS 53-1 ', 100, 1, '2023-11-05 04:17:15'),
(40, 'INSSPOOL53-4', 'PCS', 0, 'INSULATOR, SPOOL, 3&QUOT;, ANSI, CLASS 53-4 ', 100, 1, '2023-11-05 04:18:57'),
(41, 'NUTLOCK38', 'PCS', 0, 'NUT, LOCK, MF TYPE, 3/8&QUOT; ', 100, 1, '2023-11-05 04:19:22'),
(42, 'RODATE110', 'PCS', 0, 'ROD, ANCHOR, THIMBLE EYE, 1&QUOT; X 10&#039;, HOT DIP GALVANIZED, FORGED ', 100, 1, '2023-11-05 04:20:09'),
(43, 'WASHERSC442', 'PCS', 0, 'WASHER, SQUARE, CURVED, 4&QUOT; X 4&QUOT; X 1/2&QUOT; W/ 7/8&QUOT; DIA. HOLE ', 100, 1, '2023-11-05 04:22:59'),
(44, 'BOLTCR384', 'PCS', 0, 'BOLT, CARRIAGE 3/8&QUOT; X 4-1/2&QUOT;, HOT DIP GALVANIZED ', 81, 1, '2023-11-05 04:23:41'),
(45, 'BOLTM5812', 'PCS', 0, 'BOLT, MACHINE 5/8&QUOT; X 12&QUOT;, HOT DIP GALVANLZED ', 100, 1, '2023-11-05 04:26:52'),
(46, 'BRACEX18D48S', 'PCS', 0, 'BRACE CROSS-ARM, 18&QUOT; DROP 48&QUOT; SPAN ', 100, 1, '2023-11-05 04:27:23'),
(47, 'BRACEX18D60S', 'PCS', 0, 'BRACE CROSS-ARM, 18&QUOT; DROP 60&QUOT; SPAN ', 100, 1, '2023-11-05 04:29:20'),
(48, 'CLAMPDES20', 'PCS', 0, 'CLAMP, DEAD-END STRAIN, #2/0 ACSR ', 100, 1, '2023-11-05 04:30:14'),
(49, 'CLAMPGUYB', 'PCS', 0, 'CLAMP, GUY BOND ', 100, 1, '2023-11-05 04:30:38'),
(50, 'SHACKLEACH58', 'PCS', 0, 'SHACKLE, ANCHOR, 5/8&QUOT;, FORGED STEEL, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 04:39:49'),
(51, 'BOLTARM5824', 'PCS', 0, 'BOLT, DOUBLE ARMING 5/8&QUOT; X 24&QUOT;, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 04:40:59'),
(52, 'BOLTM126', 'PCS', 0, 'BOLT, MACHINE 1/2&QUOT; X 6&QUOT;, HOT OLP GALVANIZED ', 100, 1, '2023-11-05 04:41:45'),
(53, 'BOLTM586', 'PCS', 0, 'BOLT, MACHINE 5/8&QUOT; X 6&QUOT;, HOT OLP GALVANIZED', 100, 1, '2023-11-05 04:42:19'),
(54, 'BOLTOE5812', 'PCS', 0, 'BOLT, OVAL EYE 5/8&QUOT; X 12&QUOT;, HOT DIP GALVANIZED, FORGED ', 100, 1, '2023-11-05 04:46:58'),
(55, 'BOLTOE5818', 'PCS', 0, 'BOLT, OVAL EYE 5/8&QUOT; X 18&QUOT;, HOT DIP GALVANIZED, FORGED ', 100, 1, '2023-11-05 04:47:28'),
(56, 'CLAMPDES10', 'PCS', 0, 'CLAMP, DEAD-END STRAIN, #1/0 ACSR ', 100, 1, '2023-11-05 04:50:25'),
(57, 'CLAMPLOOPDE10', 'PCS', 0, 'CLAMP, LOOP DEAD-END, #1/0 ACSR ', 100, 1, '2023-11-05 04:51:01'),
(58, 'CONCOMP240', 'PCS', 0, 'CONNECTOR, COMPRESSION, #2 -#410 ACSR RUN TO #6 -#2 ', 100, 1, '2023-11-05 04:52:22'),
(59, 'CONCOMP6', 'PCS', 0, 'CONNECTOR, COMPRESSION, #6 -#4 ACSR RUN TO #6 -#4 ', 100, 1, '2023-11-05 04:52:50'),
(60, 'CLAMPHL2-40', 'PCS', 0, 'CLAMP, HOT LINE, #2 -#410 ACSR ', 100, 1, '2023-11-05 04:54:42'),
(61, 'XARMS348', 'PCS', 0, 'CROSSARM, STEEL, 3&QUOT; X 4&QUOT; X 8&#039;, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 05:04:06'),
(62, 'INSSPOOL53-2', 'PCS', 0, 'INSULATOR, SPOOL, 1-3/4&QUOT;, ANSI, CLASS 53-2 ', 100, 1, '2023-11-05 05:07:28'),
(63, 'NUTLOCK12', 'PCS', 0, 'NUT, LOCK, MF TYPE, 1/2&QUOT; ', 100, 1, '2023-11-05 05:10:06'),
(64, 'NUTLOCK58', 'PCS', 0, 'NUT, LOCK, MF TYPE, 5/8&QUOT; ', 100, 1, '2023-11-05 05:10:38'),
(65, 'WIRETAPEA', 'MTR', 0, 'WIRE, TAPE, ARMOR, ALUMINUM ALLOY, 0.5&QUOT; X 0.3&QUOT; (FEET) ', 100, 1, '2023-11-05 05:11:24'),
(66, 'WASHERR138', 'PCS', 0, 'WASHER, ROUND, 1-3/8&QUOT; DIAMETER WITH 9/16&QUOT; DIAMETER HOLE', 100, 1, '2023-11-05 05:12:29'),
(67, 'CLAMPABSE58', 'PCS', 0, 'CLAMP, ANCHOR BONDING, SINGLE EYE ROD, 5/8 ', 100, 1, '2023-11-05 05:15:12'),
(68, 'CLAMP3BOLT', 'PCS', 0, 'CLAMP, GUY STRAIGHT, 3 BOLT, HEAVY DUTY STEEL, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 05:15:42'),
(69, 'CONCOMPYHO125', 'PCS', 0, 'CONNECTOR, COMPRESSION, YHO 125, RUN #6 -#1/0 -TAP #6 -#2 ', 100, 1, '2023-11-05 05:17:20'),
(70, 'WIREG4', 'MTR', 0, 'WIRE, GROUNDING, ALUMINUM ALLOY, #4 AWG (METERS) ', 100, 1, '2023-11-05 05:22:04'),
(71, 'WIREGS38', 'MTR', 0, 'WIRE, GUY, STEEL, 3/8&QUOT;, 7 STRAND, HIGH STRENGTH (METERS) ', 100, 1, '2023-11-05 05:22:44'),
(72, 'INSPIN23KV56-1', 'PCS', 0, 'INSULATOR, PIN TYPE, 23KV, 56-1 ', 100, 1, '2023-11-05 05:33:36'),
(73, 'INSPIN23KV56-2', 'PCS', 0, 'INSULATOR, PIN TYPE, 23KV, 56-2 ', 100, 1, '2023-11-05 05:33:52'),
(74, 'PINPOLETOP20', 'PCS', 0, 'PIN, POLE TOP, CHANNEL, 1&QUOT; THREAD, 20&QUOT; LONG, HOT DIP GALVANIZED ', 100, 1, '2023-11-05 05:34:56'),
(75, 'DT25KVA', 'UNIT', 0, 'DISTRIBUTION TRANSFORMER 25KVA 7620/13200, 120-240V 60HZ', 100, 1, '2023-11-05 18:16:00'),
(76, 'KWHRMETER2', 'UNIT', 0, 'KWHR METER KV2C F IS 2 WIRE', 100, 1, '2023-11-05 18:17:09'),
(77, 'TRNSCHSINGLE', 'PCS', 0, 'TRANSFORMER CLUSTER HANGER SINGLE PHASE', 100, 1, '2023-11-05 19:40:47'),
(78, 'C&ABRACKETL', 'PCS', 0, 'CUTOUT &AMP; ARRESTER BRACKET L-TYPE', 100, 1, '2023-11-05 19:42:10'),
(79, 'AMPSTIR4', 'PCS', 22, 'AMPACT STIRRUP 4/0', 106, 1, '2023-11-05 19:43:12'),
(80, 'BOLTARM5822', 'PCS', 0, 'BOLT, DOUBLE ARMING 5/8&QUOT; X 22&QUOT;, HOT DIP GALVANIZED ', 100, 1, '2023-11-06 00:49:01'),
(81, 'BOLTM5814', 'PCS', 0, 'BOLT, MACHINE 5/8&QUOT; X 14&QUOT;, HOT OLP GALVANIZED ', 100, 1, '2023-11-06 00:50:16'),
(82, 'BRACKETCDEWOS', 'PCS', 0, 'BRACKET, CLEVIS DEAD-END WITHOUT SPOOL ', 100, 1, '2023-11-06 00:52:06'),
(83, 'CLAMPDES4', 'PCS', 0, 'CLAMP, DEAD-END STRAIN, #4 ACSR ', 100, 1, '2023-11-06 00:53:38'),
(84, 'CONDBARE2', 'MTR', 0, 'CONDUCTOR, BARE, ACSR #2, AWG 6/1 (METERS) ', 100, 1, '2023-11-06 00:55:33'),
(85, 'CONCOMPYHO150', 'PCS', 0, 'CONNECTOR, COMPRESSION, YHO 150, RUN #3 -#1/0 -TAP #6-#2 ', 100, 1, '2023-11-06 00:58:47'),
(86, 'CONCOMPYHD400', 'PCS', 0, 'CONNECTOR, COMPRESSION, YHD 400, RUN #2/0 -#410 -TAP #2/0 -#410 ', 100, 1, '2023-11-06 01:02:40'),
(87, 'CONGRCLMP58', 'PCS', 0, 'CONNECTOR, GROUND ROD CLAMP, 5/8&QUOT; ', 100, 1, '2023-11-06 01:03:36'),
(88, 'FCOLA100', 'SET', 0, 'FUSE CUT-OUT &AMP; ARRESTER COMBINATION, 15KV, CLASS 100 ', 100, 1, '2023-11-06 01:04:58'),
(89, 'INSDECTPOLY', 'PCS', 0, 'INSULATOR, DEAD-END, CLEVIS TYPE, POLYMER,15KV ', 100, 1, '2023-11-06 01:07:28'),
(90, 'INSPINPOLY55-5', 'PCS', 0, 'INSULATOR, PIN TYPE, POLYMER, ANSI, CLASS 55-5 ', 100, 1, '2023-11-06 01:08:36'),
(91, 'INSPINPORY55-5', 'PCS', 0, 'INSULATOR, PIN TYPE, PORCELAIN, ANSI, CLASS 55-5 ', 100, 1, '2023-11-06 01:09:02'),
(92, 'PINXS5813', 'PCS', 0, 'PIN, CROSSARM, STEEL, 5/8&QUOT; X 13-3/4&QUOT;, HOT DIP GALVANIZED ', 100, 1, '2023-11-06 01:13:04'),
(93, 'POLEC35C3', 'PCS', 0, 'POLE, CONCRETE, 35&#039;, CLASS 3, 1400 KGS. (MINIMUM LOAD BREAK) ', 100, 1, '2023-11-06 01:15:15'),
(94, 'RODAP10D', 'PCS', 0, 'ROD, ARMOR, PREFORMED, #1/0 ACSR, DOUBLE SUPPORT ', 100, 1, '2023-11-06 01:17:47'),
(95, 'RODAP10S', 'PCS', 0, 'ROD, ARMOR, PREFORMED, #1/0 ACSR, SINGLE SUPPORT ', 100, 1, '2023-11-06 01:18:07'),
(96, 'SPACERP34', 'PCS', 0, 'SPACER, PIPE, 3/4&QUOT; X 1-1/2&QUOT;, HOT DIP GALVANIZED ', 100, 1, '2023-11-06 01:22:26'),
(97, 'WASHERC3313', 'PCS', 0, 'WASHER, CURVED, 3&QUOT; X 3&QUOT; X 1/4&QUOT;, 13/16 ', 100, 1, '2023-11-06 01:23:24'),
(98, 'WIRETIE4', 'PCS', 0, 'WIRE, TIE, ALUMINUM ALLOY, SOFT, #4 AWG (FEET) ', 100, 1, '2023-11-06 01:25:27'),
(99, 'CLIPGW', 'PCS', 0, 'CLLP, GROUND WIRE ', 100, 1, '2023-11-06 06:53:38'),
(100, 'WIREG3', 'FEET', 0, 'WIRE, GROUNDING, GALVANIZED, 3 STRAND, 5/16&QUOT; DIA. (FEET) ', 100, 1, '2023-11-06 06:58:18'),
(101, 'NUTLOCK34', 'PCS', 0, 'NUT, LOCK, MF TYPE, 3/4&QUOT; ', 100, 1, '2023-11-06 07:02:24'),
(102, 'POLEC35C7A', 'PCS', 0, 'POLE, CONCRETE, 35&#039;, CLASS 7A, 500 KGS. (MINIMUM LOAD BREAK) ', 100, 1, '2023-11-06 07:04:19'),
(103, 'CONSPTBOLT', 'PCS', 0, 'CONNECTOR, SPLIT BOLT ', 100, 1, '2023-11-06 07:08:35'),
(104, 'CONSDRLSSCU40', 'PCS', 0, 'CONNECTOR, SOLDERLESS, COPPER, #4/0 ', 100, 1, '2023-11-06 07:09:07'),
(105, 'CLAMPSUS4-40', 'PCS', 0, 'CLAMP, SUSPENSION, ALUMINUM ALLOY CLEVIS, 2 BOLTS, #2/0 ACSR MAX.', 100, 1, '2023-11-06 07:10:50'),
(106, 'FCO15KV100', 'ASS', 0, 'FUSE CUT-OUT, 15KV, CLASS 100 ', 100, 1, '2023-11-08 07:57:38'),
(107, 'LINKFUSE6-1', 'PCS', 0, 'LINK, FUSE, UNIVERSAL, BOTTOM HEAD, TYPE K, 6A ', 100, 1, '2023-11-08 07:59:02'),
(108, 'BRACKETMFCO', 'SET', 0, 'BRACKET, MOUNTING FOR FUSE CUT-OUT &AMP; ARRESTER ', 100, 1, '2023-11-08 07:59:27'),
(109, 'BRACKETMTRANSCT', 'SET', 0, 'BRACKET, MOUNTING TRANSFORMER, CLUSTER TYPE, HOT DIP GALVANIZED ', 100, 1, '2023-11-08 07:59:52'),
(110, 'RACKS2W', 'SET', 0, 'RACK, SECONDARY, 2 WIRE GALVANIZED WITH SPOOL', 100, 1, '2023-11-08 08:00:12'),
(111, 'CT15KV1005', 'PCS', 0, 'CURRENT TRANSFORMER 100: 5, 15KV, EXTENDED RANGE', 100, 1, '2023-11-08 08:01:17'),
(114, 'TEST', '', 0, 'TEST', 100, 0, '2023-11-15 05:40:53'),
(115, 'EWIRETHW', '', 0, 'ELECTRICAL WIRE 150MM2 THW', 100, 1, '2023-11-17 03:32:54'),
(116, 'RACKS3W', '', 0, 'RACK, SECONDARY, 3 WIRE WITH SPOOL', 100, 1, '2023-11-17 03:34:01'),
(117, 'RSCPIPE', '', 0, 'RSC PIPE 65MM DIAMETER', 100, 1, '2023-11-17 03:35:36'),
(118, 'RSCLONGBEND', '', 0, 'RCS LONG BEND ELBOW', 100, 1, '2023-11-17 03:36:15'),
(119, 'RSCCOUP', '', 0, 'RSC COUPLING 2 1/2&QUOT;', 100, 1, '2023-11-17 03:38:52'),
(120, 'ECAP', '', 0, 'SERVICE ENTRANCE CAAP 1/2', 100, 1, '2023-11-17 03:39:39'),
(121, 'LUGS', '', 0, 'TERMINAL LUGS FOR 150MM2 THW WIRE', 100, 1, '2023-11-17 03:40:25'),
(122, 'EXBOLT', '', 0, 'EXPANSION BOLT 3/4&QUOT;', 100, 1, '2023-11-17 03:41:00'),
(128, 'CTBOX101418', 'UNIT', 0, 'CURRENT TRANSFORMER BOX STAINLESS', 100, 1, '2023-11-20 03:14:16'),
(137, 'AS', 'UNIT', 210, 'S', 100, 1, '2023-11-20 13:41:38'),
(140, 'B', 'PCS', 123425, 'B', 100, 1, '2023-11-20 14:18:10')";
    
    // Execute the insert
    if ($conn->multi_query($insert_sql)) {
        // Process all results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        // Set AUTO_INCREMENT
        $conn->query("ALTER TABLE item_list AUTO_INCREMENT = 141");
        
        $data_inserted = true;
        $items_count = 134; // Total number of items inserted
        
        if ($action == "created") {
            $message = "Table 'item_list' created successfully and {$items_count} items inserted!";
            $alert_type = "success";
        } else {
            $message = "{$items_count} items inserted into existing table!";
            $alert_type = "success";
        }
    } else {
        $message = "Error inserting data: " . $conn->error;
        $alert_type = "danger";
        $action = "error";
    }
}

// Get table structure for display
$table_info = null;
if ($table_check->num_rows > 0 || $action == "created") {
    $result = $conn->query("DESCRIBE item_list");
    if ($result) {
        $table_info = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Item List Table</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="bi bi-table"></i> Create Item List Table</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-<?php echo $alert_type; ?>">
                            <strong><?php echo $message; ?></strong>
                            <?php if ($data_inserted || $items_count > 0): ?>
                                <br><small>Total items in table: <?php echo $items_count; ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($table_info): ?>
                            <h5 class="mt-4">Table Structure:</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Type</th>
                                            <th>Null</th>
                                            <th>Key</th>
                                            <th>Default</th>
                                            <th>Extra</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($table_info as $field): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($field['Field']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($field['Type']); ?></td>
                                                <td><?php echo htmlspecialchars($field['Null']); ?></td>
                                                <td><?php echo htmlspecialchars($field['Key']); ?></td>
                                                <td><?php echo htmlspecialchars($field['Default'] ?? 'NULL'); ?></td>
                                                <td><?php echo htmlspecialchars($field['Extra']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <?php if ($action == "created" || $action == "exists"): ?>
                                <a href="import_items.php" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Import Items Data
                                </a>
                                <a href="items.php" class="btn btn-success">
                                    <i class="bi bi-list"></i> View Items
                                </a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-house"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

