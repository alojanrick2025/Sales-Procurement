-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sales_procurement
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `partner_type` enum('customer','supplier') NOT NULL DEFAULT 'customer',
  `address` text NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'BRICOLAGE PHILIPPINES INC.','customer','BRICOLAGE PHILIPPINES INC. Victorias City',10.90109000,123.07041000,'Victorias','09171234567','sales@bricolage.ph','2026-09-13 17:10:29'),(2,'FRONTIER TOWER ASSOCIATES PHILIPPINES','supplier','FRONTIER TOWER ASSOCIATES PHILIPPINES Taguig City',14.54968090,121.04837290,'Taguig','09181234568','contact@frontiertower.ph','2026-09-13 17:10:29'),(3,'MUNICIPALITY OF CALATRAVA','customer','MUNICIPALITY OF CALATRAVA',10.59404420,123.47638440,'Calatrava','09191234569','treasury@calatrava.gov.ph','2026-09-13 17:10:29'),(4,'SILAY CITY WATER DISTRICT','customer','MUNICIPALITY OF SILAY',10.79941260,122.97561490,'Silay','09201234570','support@silaywater.gov.ph','2026-09-13 17:10:29'),(5,'Sagay City Water District','customer','Sagay City Water District',10.89606810,123.41546170,'Sagay','09211234571','info@sagaywater.gov.ph','2026-09-13 17:10:29'),(6,'ISON TOWER','supplier','ISON TOWER Makati City',14.55009000,121.01435000,'Makati','09221234572','procurement@isontower.com','2026-09-13 17:10:29');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_orders`
--

DROP TABLE IF EXISTS `customer_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
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
  UNIQUE KEY `po_number` (`po_number`),
  KEY `customer_id` (`customer_id`),
  KEY `quotation_id` (`quotation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_orders`
--

LOCK TABLES `customer_orders` WRITE;
/*!40000 ALTER TABLE `customer_orders` DISABLE KEYS */;
INSERT INTO `customer_orders` VALUES (1,'CPO-2026-0001',1,1,'BRICOLAGE PHILIPPINES INC.','2026-09-12',50400.00,'processing','PO for HDG Bolts and Machine Assemblies','2026-09-13 18:43:39','2026-09-13 18:43:39'),(2,'CPO-2026-0002',2,3,'MUNICIPALITY OF CALATRAVA','2026-09-10',35000.00,'completed','Tower grounding and hardware installation package','2026-09-13 18:43:39','2026-09-13 19:07:21'),(3,'CPO-2026-0003',NULL,4,'SILAY CITY WATER DISTRICT','2026-09-08',42500.00,'completed','Flange bolts, machine bolts and hardware accessories','2026-09-13 18:43:39','2026-09-13 18:43:39'),(4,'CPO-2026-0004',NULL,3,'MUNICIPALITY OF CALATRAVA','2026-09-13',18750.00,'pending','Awaiting municipal council approval signature','2026-09-13 18:43:39','2026-09-13 18:43:39'),(5,'CPO-2026-0005',NULL,5,'Sagay City Water District','2026-09-05',12000.00,'cancelled','Client requested project cancellation due to revised specs','2026-09-13 18:43:39','2026-09-13 18:43:39');
/*!40000 ALTER TABLE `customer_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_list`
--

DROP TABLE IF EXISTS `item_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_list` (
  `id` int(30) NOT NULL AUTO_INCREMENT,
  `description` varchar(250) NOT NULL,
  `unit` text NOT NULL,
  `price` float NOT NULL,
  `name` text NOT NULL,
  `stocks` float NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Active, 0 = Inactive',
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_list`
--

LOCK TABLES `item_list` WRITE;
/*!40000 ALTER TABLE `item_list` DISABLE KEYS */;
INSERT INTO `item_list` VALUES (7,'EA-8W-HDG','PCS',100,'ANCHOR, EXPANDING, 8 -WAYS, HOT DIP GALVANIZED',10,1,'2023-10-08 16:35:58'),(8,'LA-W-8X4','PCS',100,'ANCHOR, LOG, WOOD 8&QUOT; X 4&#039; ',100,1,'2023-10-08 16:36:17'),(9,'LA-C-8X4','PCS',100,'ANCHOR, LOG, CONCRETE, 8&QUOT; X 4&#039; ',100,1,'2023-10-08 16:36:36'),(10,'SA-3/4X8-HDG','PCS',100,'ANCHOR, SCREW EYE SHAFT, 3/4&QUOT; X 8&#039;, HDG, FORGED ',100,1,'2023-10-08 16:36:55'),(11,'AS-3/4-69KV','PCS',100,'ANCHOR, SHACKLE, 3/4&QUOT;, DROP FORGED STEEL, 69KV ',91,1,'2023-10-08 16:37:12'),(12,'GA-11/16','PCS',100,'ATTACHMENT, GUY, MALLEABLE TYPE WITH 11/16',100,1,'2023-10-08 16:37:34'),(17,'MB-5/8X8-HDG','PCS',100,'BOLT, MACHINE 5/8&QUOT; X 8&QUOT;, HOT DIP GALVANIZED ',51,1,'2023-11-03 18:22:41'),(18,'MB-5/8X10-HDG','PCS',100,'BOLT, MACHINE 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED ',100,1,'2023-11-03 18:23:08'),(19,'OEB-5/8X10-HDG','PCS',100,'BOLT, OVAL EYE 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED, FORGED ',100,1,'2023-11-03 18:25:04'),(20,'BRK-SEC-WOS','PCS',100,'BRACKET, SECONDARY WITHOUT SPOOL ',100,1,'2023-11-03 18:25:32'),(21,'CL-LDE-#2','PCS',100,'CLAMP, LOOP DEAD-END, #2 ACSR ',100,1,'2023-11-03 18:26:02'),(22,'DUB-5/8X10-HDG','PCS',100,'BOLT, DOUBLE UPSET 5/8&QUOT; X 10&QUOT;, HOT OLP GALVANIZED, FORGED ',41,1,'2023-11-03 18:26:36'),(23,'SW-2-1/4X2-1/4','PCS',100,'WASHER SQUARE 2&FRAC14; X 2&FRAC14;',100,1,'2023-11-05 03:42:38'),(24,'SW-4X4X1/2','PCS',100,'WASHER SQUARE 4X4X&FRAC12;',100,1,'2023-11-05 03:43:32'),(25,'SP-30FT-3.0MM','PCS',100,'STEEL POLE 30FT 3.0MM',100,1,'2023-11-05 03:43:59'),(26,'SP-35FT-3.0MM','PCS',100,'STEEL POLE 35FT 3.0MM',61,1,'2023-11-05 03:44:19'),(27,'CLEV-SS-WOS-HDG','PCS',100,'CLEVIS, SECONDARY SWINGING WITHOUT SPOOL, HOT DIP GALVANIZED ',31,1,'2023-11-05 03:53:39'),(28,'COND-BARE-1/0-ACSR','MTR',100,'CONDUCTOR, BARE, ACSR #1/0, AWG 6/1 (METERS) ',100,1,'2023-11-05 03:54:17'),(29,'EN-5/8-HDG','PCS',100,'NUT, EYE, 5/8&QUOT;, CONVENTIONAL, HOT DIP GALVANIZED ',100,1,'2023-11-05 03:54:55'),(30,'AR-SE-5/8X7-HDG','PCS',100,'ROD, ANCHOR, THREADED, SINGLE EYE, 5/8&QUOT; X 7&#039;, HOT DIP GALVANIZED, FORGED ',100,1,'2023-11-05 03:56:57'),(31,'MB-1/2X10-HDG','PCS',100,'BOLT, MACHINE 1/2&QUOT; X 10&QUOT;, HOT DIP GALVANIZED ',100,1,'2023-11-05 03:57:55'),(32,'SUB-5/8X10-HDG','PCS',100,'BOLT, SINGLE UPSET 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED, FORGED ',100,1,'2023-11-05 04:00:03'),(33,'GR-5/8X10-HDG','PCS',100,'ROD, GROUND STEEL, GALVANIZED, 5/8&QUOT; X 10&#039;, HOT DIP GALVANIZED ',100,1,'2023-11-05 04:01:42'),(34,'CL-DES-#2','PCS',100,'CLAMP, DEAD-END STRAIN, #2 ACSR ',100,1,'2023-11-05 04:02:03'),(35,'INS-SUS-6-52-1','PCS',100,'INSULATOR, SUSPENSION, 6&QUOT;, ANSI CLASS 52-1 ',100,1,'2023-11-05 04:02:22'),(36,'CC-YHD-150','PCS',100,'CONNECTOR COMPRESSION YHD 150	',100,1,'2023-11-05 04:04:23'),(37,'CC-YHD-200','PCS',100,'CONNECTOR, COMPRESSION, YHD 200, RUN #1/0 -#2/0 -TAP #6 -#2 ',100,1,'2023-11-05 04:05:21'),(38,'COND-INS-1/0-ACSR','MTR',100,'CONDUCTOR, INSULATED, ACSR #1/0, AWG 6/1 (METERS) ',100,1,'2023-11-05 04:09:51'),(39,'INS-SP-1-3/8-53-1','PCS',100,'INSULATOR, SPOOL, 1-3/8&QUOT;, ANSI, CLASS 53-1 ',100,1,'2023-11-05 04:17:15'),(40,'INS-SP-3-53-4','PCS',100,'INSULATOR, SPOOL, 3&QUOT;, ANSI, CLASS 53-4 ',100,1,'2023-11-05 04:18:57'),(41,'LN-MF-3/8','PCS',100,'NUT, LOCK, MF TYPE, 3/8&QUOT; ',100,1,'2023-11-05 04:19:22'),(42,'AR-TE-1X10-HDG','PCS',100,'ROD, ANCHOR, THIMBLE EYE, 1&QUOT; X 10&#039;, HOT DIP GALVANIZED, FORGED ',100,1,'2023-11-05 04:20:09'),(43,'CW-4X4X1/2-7/8','PCS',100,'WASHER, SQUARE, CURVED, 4&QUOT; X 4&QUOT; X 1/2&QUOT; W/ 7/8&QUOT; DIA. HOLE ',100,1,'2023-11-05 04:22:59'),(44,'CB-3/8X4-1/2-HDG','PCS',100,'BOLT, CARRIAGE 3/8&QUOT; X 4-1/2&QUOT;, HOT DIP GALVANIZED ',81,1,'2023-11-05 04:23:41'),(45,'MB-5/8X12-HDG','PCS',100,'BOLT, MACHINE 5/8&QUOT; X 12&QUOT;, HOT DIP GALVANLZED ',100,1,'2023-11-05 04:26:52'),(46,'BA-18D-48S','PCS',100,'BRACE CROSS-ARM, 18&QUOT; DROP 48&QUOT; SPAN ',100,1,'2023-11-05 04:27:23'),(47,'BA-18D-60S','PCS',100,'BRACE CROSS-ARM, 18&QUOT; DROP 60&QUOT; SPAN ',100,1,'2023-11-05 04:29:20'),(48,'CL-DES-#2/0','PCS',100,'CLAMP, DEAD-END STRAIN, #2/0 ACSR ',100,1,'2023-11-05 04:30:14'),(49,'CL-GB','PCS',100,'CLAMP, GUY BOND ',100,1,'2023-11-05 04:30:38'),(50,'AS-5/8-HDG','PCS',100,'SHACKLE, ANCHOR, 5/8&QUOT;, FORGED STEEL, HOT DIP GALVANIZED ',100,1,'2023-11-05 04:39:49'),(51,'DAB-5/8X24-HDG','PCS',100,'BOLT, DOUBLE ARMING 5/8&QUOT; X 24&QUOT;, HOT DIP GALVANIZED ',100,1,'2023-11-05 04:40:59'),(52,'MB-1/2X6-HDG','PCS',100,'BOLT, MACHINE 1/2&QUOT; X 6&QUOT;, HOT OLP GALVANIZED ',100,1,'2023-11-05 04:41:45'),(53,'MB-5/8X6-HDG','PCS',100,'BOLT, MACHINE 5/8\" X 6\", HOT OLP GALVANIZED',100,1,'2023-11-05 04:42:19'),(54,'OEB-5/8X12-HDG','PCS',100,'BOLT, OVAL EYE 5/8&QUOT; X 12&QUOT;, HOT DIP GALVANIZED, FORGED ',100,1,'2023-11-05 04:46:58'),(55,'OEB-5/8X18-HDG','PCS',100,'BOLT, OVAL EYE 5/8&QUOT; X 18&QUOT;, HOT DIP GALVANIZED, FORGED ',100,1,'2023-11-05 04:47:28'),(56,'CL-DES-#1/0','PCS',100,'CLAMP, DEAD-END STRAIN, #1/0 ACSR ',100,1,'2023-11-05 04:50:25'),(57,'CL-LDE-#1/0','PCS',100,'CLAMP, LOOP DEAD-END, #1/0 ACSR ',100,1,'2023-11-05 04:51:01'),(58,'CC-#2-4/0-#6-2','PCS',100,'CONNECTOR, COMPRESSION, #2 -#410 ACSR RUN TO #6 -#2 ',100,1,'2023-11-05 04:52:22'),(59,'CC-#6-4-#6-4','PCS',100,'CONNECTOR, COMPRESSION, #6 -#4 ACSR RUN TO #6 -#4 ',100,1,'2023-11-05 04:52:50'),(60,'CL-HL-#2-4/0','PCS',100,'CLAMP, HOT LINE, #2 -#410 ACSR ',100,1,'2023-11-05 04:54:42'),(61,'CA-STL-3X4X8-HDG','PCS',100,'CROSSARM, STEEL, 3&QUOT; X 4&QUOT; X 8&#039;, HOT DIP GALVANIZED ',100,1,'2023-11-05 05:04:06'),(62,'INS-SP-1-3/4-53-2','PCS',100,'INSULATOR, SPOOL, 1-3/4&QUOT;, ANSI, CLASS 53-2 ',100,1,'2023-11-05 05:07:28'),(63,'LN-MF-1/2','PCS',100,'NUT, LOCK, MF TYPE, 1/2&QUOT; ',100,1,'2023-11-05 05:10:06'),(64,'LN-MF-5/8','PCS',100,'NUT, LOCK, MF TYPE, 5/8&QUOT; ',100,1,'2023-11-05 05:10:38'),(65,'WT-ARM-AL-0.5X0.3','MTR',100,'WIRE, TAPE, ARMOR, ALUMINUM ALLOY, 0.5&QUOT; X 0.3&QUOT; (FEET) ',100,1,'2023-11-05 05:11:24'),(66,'RW-1-3/8X9/16','PCS',100,'WASHER, ROUND, 1-3/8&QUOT; DIAMETER WITH 9/16&QUOT; DIAMETER HOLE',100,1,'2023-11-05 05:12:29'),(67,'CL-AB-5/8','PCS',100,'CLAMP, ANCHOR BONDING, SINGLE EYE ROD, 5/8 ',100,1,'2023-11-05 05:15:12'),(68,'CL-GS-3B-HDG','PCS',100,'CLAMP, GUY STRAIGHT, 3 BOLT, HEAVY DUTY STEEL, HOT DIP GALVANIZED ',100,1,'2023-11-05 05:15:42'),(69,'CC-YHO-125','PCS',100,'CONNECTOR, COMPRESSION, YHO 125, RUN #6 -#1/0 -TAP #6 -#2 ',100,1,'2023-11-05 05:17:20'),(70,'WG-AL-#4','MTR',100,'WIRE, GROUNDING, ALUMINUM ALLOY, #4 AWG (METERS) ',100,1,'2023-11-05 05:22:04'),(71,'WG-STL-3/8-7S','MTR',100,'WIRE, GUY, STEEL, 3/8&QUOT;, 7 STRAND, HIGH STRENGTH (METERS) ',100,1,'2023-11-05 05:22:44'),(72,'INS-PIN-23KV-56-1','PCS',100,'INSULATOR, PIN TYPE, 23KV, 56-1 ',100,1,'2023-11-05 05:33:36'),(73,'INS-PIN-23KV-56-2','PCS',100,'INSULATOR, PIN TYPE, 23KV, 56-2 ',100,1,'2023-11-05 05:33:52'),(74,'PTP-1X20-HDG','PCS',100,'PIN, POLE TOP, CHANNEL, 1&QUOT; THREAD, 20&QUOT; LONG, HOT DIP GALVANIZED ',100,1,'2023-11-05 05:34:56'),(75,'DT-25KVA-7.6/13.2KV','PCS',100,'DISTRIBUTION TRANSFORMER 25KVA 7620/13200, 120-240V 60HZ',100,1,'2023-11-05 18:16:00'),(76,'KM-KV2C-2W','PCS',100,'KWHR METER KV2C F IS 2 WIRE',100,1,'2023-11-05 18:17:09'),(77,'TCH-1PH','PCS',100,'TRANSFORMER CLUSTER HANGER SINGLE PHASE',100,1,'2023-11-05 19:40:47'),(78,'BRK-COA-L','PCS',100,'CUTOUT &AMP; ARRESTER BRACKET L-TYPE',100,1,'2023-11-05 19:42:10'),(79,'AS-4/0','PCS',100,'AMPACT STIRRUP 4/0',106,1,'2023-11-05 19:43:12'),(80,'DAB-5/8X22-HDG','PCS',100,'BOLT, DOUBLE ARMING 5/8&QUOT; X 22&QUOT;, HOT DIP GALVANIZED ',100,1,'2023-11-06 00:49:01'),(81,'MB-5/8X14-HDG','PCS',100,'BOLT, MACHINE 5/8&QUOT; X 14&QUOT;, HOT OLP GALVANIZED ',100,1,'2023-11-06 00:50:16'),(82,'BRK-CDE-WOS','PCS',100,'BRACKET, CLEVIS DEAD-END WITHOUT SPOOL ',100,1,'2023-11-06 00:52:06'),(83,'CL-DES-#4','PCS',100,'CLAMP, DEAD-END STRAIN, #4 ACSR ',100,1,'2023-11-06 00:53:38'),(84,'COND-BARE-#2-ACSR','MTR',100,'CONDUCTOR, BARE, ACSR #2, AWG 6/1 (METERS) ',100,1,'2023-11-06 00:55:33'),(85,'CC-YHO-150','PCS',100,'CONNECTOR, COMPRESSION, YHO 150, RUN #3 -#1/0 -TAP #6-#2 ',100,1,'2023-11-06 00:58:47'),(86,'CC-YHD-400','PCS',100,'CONNECTOR, COMPRESSION, YHD 400, RUN #2/0 -#410 -TAP #2/0 -#410 ',100,1,'2023-11-06 01:02:40'),(87,'CC-GRC-5/8','PCS',100,'CONNECTOR, GROUND ROD CLAMP, 5/8&QUOT; ',100,1,'2023-11-06 01:03:36'),(88,'FCOA-15KV-C100','SET',100,'FUSE CUT-OUT &AMP; ARRESTER COMBINATION, 15KV, CLASS 100 ',100,1,'2023-11-06 01:04:58'),(89,'INS-DE-POLY-15KV','PCS',100,'INSULATOR, DEAD-END, CLEVIS TYPE, POLYMER,15KV ',100,1,'2023-11-06 01:07:28'),(90,'INS-PIN-POLY-55-5','PCS',100,'INSULATOR, PIN TYPE, POLYMER, ANSI, CLASS 55-5 ',100,1,'2023-11-06 01:08:36'),(91,'INS-PIN-PORC-55-5','PCS',100,'INSULATOR, PIN TYPE, PORCELAIN, ANSI, CLASS 55-5 ',100,1,'2023-11-06 01:09:02'),(92,'CAP-5/8X13-3/4-HDG','PCS',100,'PIN, CROSSARM, STEEL, 5/8&QUOT; X 13-3/4&QUOT;, HOT DIP GALVANIZED ',100,1,'2023-11-06 01:13:04'),(93,'CP-35FT-C3','PCS',100,'POLE, CONCRETE, 35&#039;, CLASS 3, 1400 KGS. (MINIMUM LOAD BREAK) ',100,1,'2023-11-06 01:15:15'),(94,'AR-1/0-DS','PCS',100,'ROD, ARMOR, PREFORMED, #1/0 ACSR, DOUBLE SUPPORT ',100,1,'2023-11-06 01:17:47'),(95,'AR-1/0-SS','PCS',100,'ROD, ARMOR, PREFORMED, #1/0 ACSR, SINGLE SUPPORT ',100,1,'2023-11-06 01:18:07'),(96,'SP-3/4X1-1/2-HDG','PCS',100,'SPACER, PIPE, 3/4&QUOT; X 1-1/2&QUOT;, HOT DIP GALVANIZED ',100,1,'2023-11-06 01:22:26'),(97,'CW-3X3X1/4-13/16','PCS',100,'WASHER, CURVED, 3&QUOT; X 3&QUOT; X 1/4&QUOT;, 13/16 ',100,1,'2023-11-06 01:23:24'),(98,'WT-AL-SOFT-#4','PCS',100,'WIRE, TIE, ALUMINUM ALLOY, SOFT, #4 AWG (FEET) ',100,1,'2023-11-06 01:25:27'),(99,'CP-GW','PCS',100,'CLLP, GROUND WIRE ',100,1,'2023-11-06 06:53:38'),(100,'WG-GALV-3S-5/16','FEET',100,'WIRE, GROUNDING, GALVANIZED, 3 STRAND, 5/16&QUOT; DIA. (FEET) ',100,1,'2023-11-06 06:58:18'),(101,'LN-MF-3/4','PCS',100,'NUT, LOCK, MF TYPE, 3/4&QUOT; ',100,1,'2023-11-06 07:02:24'),(102,'CP-35FT-C7A','PCS',100,'POLE, CONCRETE, 35&#039;, CLASS 7A, 500 KGS. (MINIMUM LOAD BREAK) ',100,1,'2023-11-06 07:04:19'),(103,'CC-SB','PCS',100,'CONNECTOR, SPLIT BOLT ',100,1,'2023-11-06 07:08:35'),(104,'CC-SL-CU-#4/0','PCS',100,'CONNECTOR, SOLDERLESS, COPPER, #4/0 ',100,1,'2023-11-06 07:09:07'),(105,'CL-SUS-2B-#2/0','PCS',100,'CLAMP, SUSPENSION, ALUMINUM ALLOY CLEVIS, 2 BOLTS, #2/0 ACSR MAX.',100,1,'2023-11-06 07:10:50'),(106,'FCO-15KV-C100','ASS',100,'FUSE CUT-OUT, 15KV, CLASS 100 ',100,1,'2023-11-08 07:57:38'),(107,'FL-UNIV-K-6A','PCS',100,'LINK, FUSE, UNIVERSAL, BOTTOM HEAD, TYPE K, 6A ',100,1,'2023-11-08 07:59:02'),(108,'BRK-FCO-AR','SET',100,'BRACKET, MOUNTING FOR FUSE CUT-OUT &AMP; ARRESTER ',100,1,'2023-11-08 07:59:27'),(109,'BRK-TR-CLUST-HDG','SET',100,'BRACKET, MOUNTING TRANSFORMER, CLUSTER TYPE, HOT DIP GALVANIZED ',100,1,'2023-11-08 07:59:52'),(110,'SR-2W-GALV-WS','SET',100,'RACK, SECONDARY, 2 WIRE GALVANIZED WITH SPOOL',100,1,'2023-11-08 08:00:12'),(111,'CT-100:5-15KV','PCS',100,'CURRENT TRANSFORMER 100: 5, 15KV, EXTENDED RANGE',100,1,'2023-11-08 08:01:17'),(115,'EW-150MM2-THW','PCS',100,'ELECTRICAL WIRE 150MM2 THW',100,1,'2023-11-17 03:32:54'),(116,'SR-3W-WS','PCS',100,'RACK, SECONDARY, 3 WIRE WITH SPOOL',100,1,'2023-11-17 03:34:01'),(117,'RSC-PIPE-65MM','PCS',100,'RSC PIPE 65MM DIAMETER',100,1,'2023-11-17 03:35:36'),(118,'RSC-ELB-LB','PCS',100,'RCS LONG BEND ELBOW',100,1,'2023-11-17 03:36:15'),(119,'RSC-COUP-2-1/2','PCS',100,'RSC COUPLING 2 1/2&QUOT;',100,1,'2023-11-17 03:38:52'),(120,'SEC-1/2','PCS',100,'SERVICE ENTRANCE CAAP 1/2',100,1,'2023-11-17 03:39:39'),(121,'TL-150MM2-THW','PCS',100,'TERMINAL LUGS FOR 150MM2 THW WIRE',100,1,'2023-11-17 03:40:25'),(122,'EB-3/4','PCS',100,'EXPANSION BOLT 3/4&QUOT;',100,1,'2023-11-17 03:41:00'),(128,'CT-BOX-SS','PCS',100,'CURRENT TRANSFORMER BOX STAINLESS',100,1,'2023-11-20 03:14:16');
/*!40000 ALTER TABLE `item_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_items`
--

DROP TABLE IF EXISTS `quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item_list` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_items`
--

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
INSERT INTO `quotation_items` VALUES (1,2,81,'BOLT, MACHINE 5/8&QUOT; X 14&QUOT;, HOT OLP GALVANIZED','MB-5/8X14-HDG','PCS',1.00,0.00,0.00),(2,2,18,'BOLT, MACHINE 5/8&QUOT; X 10&QUOT;, HOT DIP GALVANIZED','MB-5/8X10-HDG','PCS',1.00,0.00,0.00),(3,2,116,'RACK, SECONDARY, 3 WIRE WITH SPOOL','SR-3W-WS','',1.00,0.00,0.00);
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(150) NOT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `origin` varchar(150) DEFAULT NULL,
  `destination` varchar(150) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `valid_until` date DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `client_id` (`client_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT INTO `quotations` VALUES (1,'QT-2026-0001',1,'BRICOLAGE PHILIPPINES INC.','info@bricolage.ph','555-0199','Victorias City','Main Warehouse, Talisay City','Victorias City',45000.00,12.00,5400.00,50400.00,'2026-09-28','sent','Electrical supplies and equipment installation materials.',1,'2026-09-13 17:20:02','2026-09-13 17:20:02'),(2,'QT-2026-0002',3,'MUNICIPALITY OF CALATRAVA','','','MUNICIPALITY OF CALATRAVA, Calatrava, Negros Occidental','CAPITAN SABI BRGY. ZONE 4 TALISAY CITY NEG. OCC.','Calatrava',0.00,12.00,0.00,0.00,'2026-10-13','sent','',1,'2026-09-13 18:02:47','2026-09-13 19:07:21');
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier`
--

LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
INSERT INTO `supplier` VALUES (1,'Justly Electrical Hardware Supplies','Robert Ramos','sales@justlyelectrical.com','09953508617','Capitan Sabi, Brgy. Zone 4','Talisay City','Philippines','active',NULL,'2026-09-13 18:43:39','2026-09-13 18:43:39'),(2,'SteelAsia Manufacturing Corp.','Maria Santos','orders@steelasia.com','09171234567','Bacolod Port Area','Bacolod City','Philippines','active',NULL,'2026-09-13 18:43:39','2026-09-13 18:43:39'),(3,'PhilMetal Products Inc.','David Tan','sales@philmetal.com.ph','09228889999','Mandaue Industrial Park','Cebu City','Philippines','active',NULL,'2026-09-13 18:43:39','2026-09-13 18:43:39'),(4,'Apex Galvanizing & Fasteners Ltd.','Elena Cruz','info@apexfas.ph','09185551234','Subic Bay Freeport Zone','Olongapo City','Philippines','active',NULL,'2026-09-13 18:43:39','2026-09-13 18:43:39');
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_orders`
--

DROP TABLE IF EXISTS `supplier_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `order_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_orders`
--

LOCK TABLES `supplier_orders` WRITE;
/*!40000 ALTER TABLE `supplier_orders` DISABLE KEYS */;
INSERT INTO `supplier_orders` VALUES (1,'SPO-2026-0001',2,'FRONTIER TOWER ASSOCIATES PHILIPPINES','2026-09-11',28500.00,'completed','HDG Round Bars and structural bolts restock','2026-09-13 18:43:39','2026-09-13 19:07:21'),(2,'SPO-2026-0002',6,'ISON TOWER','2026-09-12',19800.00,'processing','Machine bolts and carriage bolts batch replenish','2026-09-13 18:43:39','2026-09-13 19:07:21'),(3,'SPO-2026-0003',2,'FRONTIER TOWER ASSOCIATES PHILIPPINES','2026-09-13',8400.00,'pending','Procurement of specialized eye nuts and lag screws','2026-09-13 18:43:39','2026-09-13 19:07:21'),(4,'SPO-2026-0004',6,'ISON TOWER','2026-09-06',15200.00,'cancelled','Duplicate procurement order cancelled','2026-09-13 18:43:39','2026-09-13 19:07:21');
/*!40000 ALTER TABLE `supplier_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_info`
--

DROP TABLE IF EXISTS `system_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meta_field` varchar(100) NOT NULL,
  `meta_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `meta_field` (`meta_field`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_info`
--

LOCK TABLES `system_info` WRITE;
/*!40000 ALTER TABLE `system_info` DISABLE KEYS */;
INSERT INTO `system_info` VALUES (1,'name','Sales and Procurement Management System','2026-09-13 17:19:08','2026-09-13 18:39:28'),(2,'short_name','JESS','2026-09-13 17:19:08','2026-09-13 18:00:16'),(3,'company_name','JUSTLY ELECTRICAL SUPPLIES AND SERVICES','2026-09-13 17:19:08','2026-09-13 18:00:16'),(4,'company_phone','09953508617','2026-09-13 17:19:08','2026-09-13 18:41:57'),(5,'company_email','contact@justlyelectrical.com','2026-09-13 17:19:08','2026-09-13 17:19:08'),(6,'company_address','CAPITAN SABI BRGY. ZONE 4 TALISAY CITY NEG. OCC.','2026-09-13 17:19:08','2026-09-13 18:00:16'),(7,'latitude','10.738962','2026-09-13 17:19:08','2026-09-13 17:19:08'),(8,'longitude','122.984021','2026-09-13 17:19:08','2026-09-13 17:19:08'),(9,'logo','uploads/1789324917_6aa6ee7535d57.jpg','2026-09-13 17:19:08','2026-09-13 18:41:57'),(10,'cover','','2026-09-13 17:19:08','2026-09-13 17:19:08');
/*!40000 ALTER TABLE `system_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('admin') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@justlyelectrical.com','$2y$10$vPJFM.qNZvxKgNz0f7oroOQXB6ZDleru8tJvw8JDDiCvqAi5GzMtO',NULL,'System Administrator',NULL,'admin','active','2026-09-13 17:10:29');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_change_logs`
--

DROP TABLE IF EXISTS `password_change_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_change_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_change_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-14  3:14:36
