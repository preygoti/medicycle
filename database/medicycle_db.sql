-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
-- Host: localhost    Database: medicycle_db
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
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-box-medical',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES 
(1,'Personal Protective Equipment','Gloves, face shields, surgical masks, gowns, and head covers','fa-head-side-mask','active',NOW()),
(2,'Wound Care & Dressings','Sterile bandages, gauze swabs, adhesive dressings, and crepe rolls','fa-bandage','active',NOW()),
(3,'Diagnostic Consumables','Test strips, disposable probe covers, lancets, and specimen containers','fa-vial','active',NOW()),
(4,'Sterilization & Antiseptics','Alcohol prep pads, sterile wraps, indicators, and surface prep wipes','fa-pump-medical','active',NOW()),
(5,'Non-Drug Administration','Sterile capped syringes, IV cannulas, infusion sets, and tubing','fa-syringe','active',NOW()),
(6,'First Aid & Emergency Kits','Unopened trauma dressings, splints, thermal blankets, and triangular bandages','fa-kit-medical','active',NOW()),
(7,'Orthopedic & Mobility Aids','Arm slings, cervical collars, crutch pads, and elastic limb supports','fa-crutch','active',NOW());
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deliveries`
--

DROP TABLE IF EXISTS `deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `delivery_partner_id` int(11) DEFAULT NULL,
  `pickup_location` text NOT NULL,
  `delivery_location` text NOT NULL,
  `pickup_status` enum('Pending','Picked Up') DEFAULT 'Pending',
  `delivery_status` enum('Assigned','Accepted','Picked Up','In Transit','Delivered','Cancelled') DEFAULT 'Assigned',
  `tracking_notes` text DEFAULT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_delivery_request` (`request_id`),
  KEY `idx_delivery_status` (`delivery_status`),
  KEY `idx_delivery_partner` (`delivery_partner_id`),
  CONSTRAINT `fk_delivery_partner` FOREIGN KEY (`delivery_partner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deliveries` (Clean Demonstration State)
--

LOCK TABLES `deliveries` WRITE;
/*!40000 ALTER TABLE `deliveries` DISABLE KEYS */;
/*!40000 ALTER TABLE `deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_otps`
--

DROP TABLE IF EXISTS `email_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `otp_type` enum('registration','password_reset') NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`email`,`otp_type`,`is_used`),
  KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_otps` (Clean Demonstration State)
--

LOCK TABLES `email_otps` WRITE;
/*!40000 ALTER TABLE `email_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `impact_metrics`
--

DROP TABLE IF EXISTS `impact_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `impact_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `quantity_redistributed` int(11) NOT NULL,
  `estimated_waste_avoided` decimal(10,2) NOT NULL COMMENT 'kg of medical packaging and consumable waste averted',
  `estimated_value_saved` decimal(10,2) NOT NULL COMMENT 'estimated monetary value in INR',
  `organizations_helped` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_impact_request` (`request_id`),
  CONSTRAINT `fk_impact_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `impact_metrics` (Clean Demonstration State)
--

LOCK TABLES `impact_metrics` WRITE;
/*!40000 ALTER TABLE `impact_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `impact_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_supplies`
--

DROP TABLE IF EXISTS `medical_supplies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_supplies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `supply_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `listing_type` enum('regular','surplus','disposal') NOT NULL DEFAULT 'regular',
  `condition_status` enum('New / Unopened','Sterile Sealed','Surplus Stock') NOT NULL,
  `packaging_status` enum('Original Factory Seal','Tamper Evident Packaging','Intact Outer Box') NOT NULL,
  `disposal_reason` varchar(255) DEFAULT NULL,
  `disposal_method` varchar(255) DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `storage_requirements` varchar(150) DEFAULT 'Room Temperature',
  `location` varchar(150) NOT NULL,
  `city` varchar(100) NOT NULL,
  `availability_date` date DEFAULT NULL,
  `minimum_request_quantity` int(11) DEFAULT 1,
  `status` varchar(50) NOT NULL DEFAULT 'Available',
  `priority_score` int(11) DEFAULT 50,
  `priority_level` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `admin_notes` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_supply_supplier` (`supplier_id`),
  KEY `fk_supply_category` (`category_id`),
  KEY `idx_supply_status` (`status`),
  KEY `idx_supply_city` (`city`),
  KEY `idx_supply_expiry` (`expiry_date`),
  KEY `idx_supply_priority` (`priority_score`),
  KEY `idx_supply_listing_type` (`listing_type`),
  CONSTRAINT `fk_supply_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_supply_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_supplies`
--

LOCK TABLES `medical_supplies` WRITE;
/*!40000 ALTER TABLE `medical_supplies` DISABLE KEYS */;
INSERT INTO `medical_supplies` VALUES 
(1,2,1,'3-Ply Surgical Earloop Face Masks','BFE >= 98% splash resistant triple-layer surgical masks, hypoallergenic with soft elastic ear loops.',500,'Boxes (50 pcs)','surplus','New / Unopened','Original Factory Seal',NULL,NULL,'2028-10-09','LOT-MSK-4402','Room Temperature (15-25°C)','Central Pharmacy Store, Room 102','Surat',NULL,1,'Available',75,'High',NULL,NULL,NOW(),NOW()),
(2,2,1,'Sterile Nitrile Examination Gloves (Medium)','Powder-free medical examination gloves, textured fingertips, latex-free, non-sterile disposable.',300,'Boxes (100 pcs)','surplus','New / Unopened','Original Factory Seal',NULL,NULL,'2028-06-15','LOT-GLV-8821','Cool & Dry away from direct sunlight','Central Pharmacy Store, Room 102','Surat',NULL,1,'Available',70,'High',NULL,NULL,NOW(),NOW()),
(3,2,2,'Sterile Gauze Swabs 10x10cm (12 Ply)','Pure 100% bleached cotton gauze swabs, gamma irradiated sterile packs of 25 pieces.',400,'Packs (25 pcs)','surplus','Sterile Sealed','Tamper Evident Packaging',NULL,NULL,'2028-04-20','LOT-GZ-2210','Standard Room Temperature','Inpatient Ward Store','Surat',NULL,1,'Available',65,'Medium',NULL,NULL,NOW(),NOW()),
(4,2,5,'Disposable Capped Syringes 5ml (Sterile)','Non-drug administration syringes with luer slip nozzle, individually blister packed and ethylene oxide sterilized.',600,'Units','surplus','Sterile Sealed','Original Factory Seal',NULL,NULL,'2028-12-31','LOT-SYR-9041','Room Temperature','Dispensary Wing B','Surat',NULL,1,'Available',65,'Medium',NULL,NULL,NOW(),NOW()),
(5,4,2,'Elastic Crepe Bandages 10cm x 4m','High-stretch supportive compression bandages for orthopedic joint immobilization and wound retention.',200,'Rolls','surplus','New / Unopened','Intact Outer Box',NULL,NULL,'2029-01-15','LOT-CR-3104','Dry Storage','Warehouse Block C','Surat',NULL,1,'Available',60,'Medium',NULL,NULL,NOW(),NOW()),
(6,4,4,'Alcohol Prep Swabs (70% Isopropyl)','Sterile 2-ply non-woven skin cleansing prep pads saturated with 70% v/v Isopropyl alcohol.',800,'Boxes (100 pcs)','surplus','New / Unopened','Original Factory Seal',NULL,NULL,'2028-11-30','LOT-APS-1092','Cool Dry Place below 30°C','Warehouse Block A','Surat',NULL,1,'Available',70,'High',NULL,NULL,NOW(),NOW());
/*!40000 ALTER TABLE `medical_supplies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES 
(1,2,'Welcome to MediCycle!','Your hospital donor profile (City Care Hospital) is active. You have 4 surplus batches listed and ready for requisition.','supplier/inventory.php',0,NOW()),
(2,3,'Welcome to MediCycle!','Your clinic profile (Hope Community Clinic) is verified. You can browse the supply catalog and post clinical needs.','catalog.php',0,NOW()),
(3,4,'Welcome to MediCycle!','Your distributor profile (Prey Medical Distributors) is verified and ready for surplus declaration.','supplier/inventory.php',0,NOW()),
(4,5,'Welcome to MediCycle!','Your clinic profile (SCET Community Health Center) is verified. Welcome to the redistribution network!','catalog.php',0,NOW());
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `organization_name` varchar(150) NOT NULL,
  `organization_type` varchar(100) NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(20) NOT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verification_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_org_user` (`user_id`),
  KEY `idx_org_city` (`city`),
  KEY `idx_org_status` (`verification_status`),
  CONSTRAINT `fk_org_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES 
(1,2,'City Care Multi-Specialty Hospital','Hospital','HOSP-SURAT-2026-88','Ring Road, Near Civil Hospital Gate 2','Surat','Gujarat','395002','verified',NULL,NOW(),NOW()),
(2,3,'Hope Community Healthcare Dispensary','Charitable Trust / NGO','NGO-SURAT-2026-42','45, Adajan Main Road, Opp. Community Hall','Surat','Gujarat','395009','verified',NULL,NOW(),NOW()),
(3,4,'Prey Medical Surgical Distributors','Pharmacy Supplier','LIC-MED-2026-01','Ghoti Faliya, Katargam Main Road','Surat','Gujarat','395004','verified',NULL,NOW(),NOW()),
(4,5,'SCET Community Health Outreach Center','Charitable Clinic / Dispensary','SCET-HLTH-2026','SCET Campus, Dr. R.K. Desai Marg, Athwalines','Surat','Gujarat','395001','verified',NULL,NOW(),NOW());
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supply_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `requested_quantity` int(11) NOT NULL,
  `purpose` text NOT NULL,
  `urgency` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `needed_by_date` date DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled','Ready for Handover','In Transit','Completed','Accepted','Collected') DEFAULT 'Pending',
  `supplier_remarks` text DEFAULT NULL,
  `handover_code` varchar(20) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_request_supply` (`supply_id`),
  KEY `fk_request_user` (`requester_id`),
  KEY `idx_request_status` (`status`),
  KEY `idx_request_handover` (`handover_code`),
  CONSTRAINT `fk_request_supply` FOREIGN KEY (`supply_id`) REFERENCES `medical_supplies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_request_user` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requests` (Clean Demonstration State)
--

LOCK TABLES `requests` WRITE;
/*!40000 ALTER TABLE `requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requirements`
--

DROP TABLE IF EXISTS `requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `supply_name` varchar(150) NOT NULL,
  `required_quantity` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'Units',
  `urgency` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `required_by` date NOT NULL,
  `city` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Fulfilled','Cancelled') DEFAULT 'Active',
  `fulfilled_by_supplier_id` int(11) DEFAULT NULL,
  `handover_code` varchar(30) DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_req_org` (`organization_id`),
  KEY `fk_req_category` (`category_id`),
  KEY `idx_req_status` (`status`),
  KEY `idx_req_urgency` (`urgency`),
  KEY `idx_req_city` (`city`),
  CONSTRAINT `fk_req_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_req_org` FOREIGN KEY (`organization_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requirements`
--

LOCK TABLES `requirements` WRITE;
/*!40000 ALTER TABLE `requirements` DISABLE KEYS */;
INSERT INTO `requirements` VALUES 
(1,3,2,'Sterile Surgical Gauze Pads (10x10cm)',100,'Packs (25 pcs)','High',DATE_ADD(CURDATE(), INTERVAL 30 DAY),'Surat','Urgent requirement for free rural outpatient screening and minor wound dressing camp.','Active',NULL,NULL,NULL,NOW(),NOW());
/*!40000 ALTER TABLE `requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES 
(1,'platform_name','MediCycle','Application Name',NOW()),
(2,'contact_email','support@medicycle.org','Platform Contact Email',NOW()),
(3,'min_expiry_days','30','Minimum shelf life in days required for donation',NOW()),
(4,'enable_registration','true','Allow public account registration',NOW()),
(5,'maintenance_mode','false','Platform maintenance toggle',NOW());
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('admin','supplier','ngo','delivery') NOT NULL,
  `email_verified` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','suspended','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_role` (`role`),
  KEY `idx_user_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES 
(1,'System Administrator','admin@medicycle.org','$2y$10$emW0aIE2Dx2UgmnjdINcr.JuskFGHk.NFml2Mzi356rWUz.aH5z8m','+91 9800000001','admin',1,'active',NOW(),NOW()),
(2,'City Care Hospital (Supplier)','hospital@care.org','$2y$10$emW0aIE2Dx2UgmnjdINcr.JuskFGHk.NFml2Mzi356rWUz.aH5z8m','+91 9825012345','supplier',1,'active',NOW(),NOW()),
(3,'Hope Community Clinic (NGO)','clinic@ngo.org','$2y$10$emW0aIE2Dx2UgmnjdINcr.JuskFGHk.NFml2Mzi356rWUz.aH5z8m','+91 9825067890','ngo',1,'active',NOW(),NOW()),
(4,'Prey Goti (Supplier)','preygoti11@gmail.com','$2y$10$emW0aIE2Dx2UgmnjdINcr.JuskFGHk.NFml2Mzi356rWUz.aH5z8m','+91 9510114943','supplier',1,'active',NOW(),NOW()),
(5,'Prey Goti (SCET NGO Clinic)','preygoti.co24d2@scet.ac.in','$2y$10$emW0aIE2Dx2UgmnjdINcr.JuskFGHk.NFml2Mzi356rWUz.aH5z8m','+91 9510114943','ngo',1,'active',NOW(),NOW());
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-27 10:15:00
