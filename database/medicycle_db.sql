-- MediCycle Database Schema (Harvest Ledger Model: Supplier <-> NGO Direct Redistribution)
-- Smart Medical Supply Redistribution Management System

DROP DATABASE IF EXISTS medicycle_db;
CREATE DATABASE medicycle_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medicycle_db;

-- 1. USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('supplier', 'ngo') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_status (status)
) ENGINE=InnoDB;

-- 2. ORGANIZATIONS TABLE
CREATE TABLE organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_name VARCHAR(150) NOT NULL,
    organization_type VARCHAR(50) NOT NULL,
    license_number VARCHAR(100) DEFAULT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(20) NOT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'verified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_org_city (city)
) ENGINE=InnoDB;

-- 3. CATEGORIES TABLE
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fa-box-medical',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. MEDICAL SUPPLIES TABLE (Surplus items listed by Suppliers)
CREATE TABLE medical_supplies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    category_id INT NOT NULL,
    supply_name VARCHAR(150) NOT NULL,
    description TEXT,
    quantity INT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    condition_status ENUM('New / Unopened', 'Sterile Sealed', 'Surplus Stock') NOT NULL,
    packaging_status ENUM('Original Factory Seal', 'Tamper Evident Packaging', 'Intact Outer Box') NOT NULL,
    expiry_date DATE NOT NULL,
    batch_number VARCHAR(100) DEFAULT NULL,
    storage_requirements VARCHAR(150) DEFAULT 'Room Temperature',
    location VARCHAR(150) NOT NULL,
    availability_date DATE DEFAULT NULL,
    minimum_request_quantity INT DEFAULT 1,
    status ENUM('Available', 'Reserved', 'Handed Over', 'Completed', 'Cancelled') DEFAULT 'Available',
    priority_score INT DEFAULT 50,
    priority_level ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_supply_supplier FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_supply_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_supply_status (status),
    INDEX idx_supply_expiry (expiry_date),
    INDEX idx_supply_priority (priority_score)
) ENGINE=InnoDB;

-- 5. REQUIREMENTS TABLE (Posted by NGOs / Clinics)
CREATE TABLE requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    supply_name VARCHAR(150) NOT NULL,
    category_id INT NOT NULL,
    required_quantity INT NOT NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'Units',
    urgency ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    required_by DATE NOT NULL,
    city VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('Active', 'Fulfilled', 'Cancelled') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_req_org FOREIGN KEY (organization_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_req_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_req_status (status),
    INDEX idx_req_urgency (urgency)
) ENGINE=InnoDB;

-- 6. REQUESTS TABLE (NGOs requesting surplus from Suppliers)
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supply_id INT NOT NULL,
    requester_id INT NOT NULL,
    requested_quantity INT NOT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    urgency ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    preferred_collection_date DATE DEFAULT NULL,
    message TEXT,
    smart_match_score INT DEFAULT NULL,
    status ENUM('Pending', 'Accepted', 'Ready for Handover', 'Collected', 'Received', 'Completed', 'Rejected') DEFAULT 'Pending',
    supplier_remarks TEXT DEFAULT NULL,
    handover_code VARCHAR(30) DEFAULT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    ready_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_request_supply FOREIGN KEY (supply_id) REFERENCES medical_supplies(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_user FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_request_status (status)
) ENGINE=InnoDB;

-- 7. NOTIFICATIONS TABLE
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- 8. IMPACT METRICS TABLE
CREATE TABLE impact_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    quantity_redistributed INT NOT NULL,
    estimated_waste_avoided DECIMAL(10,2) NOT NULL COMMENT 'kg of medical consumable packaging waste averted',
    estimated_value_saved DECIMAL(10,2) NOT NULL COMMENT 'estimated monetary value in INR',
    organizations_helped INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_impact_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. SYSTEM SETTINGS TABLE
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================================
-- SEED DATA
-- Passwords:
-- Supplier: Supplier@123 ($2y$10$0Xq8QET2celjAVdaNsDYluwKoby4OTpVPJ4xsfwPTHj6fmm671/oK)
-- NGO:      Ngo@123      ($2y$10$MK2NsW/vSGqqtu4JIQ.H0OzOxgq76rWK2mt/Vdp6IkdKC6S1ytC0m)
-- =========================================================================

INSERT INTO system_settings (setting_key, setting_value) VALUES
('platform_name', 'MediCycle'),
('support_email', 'support@medicycle.org'),
('waste_factor_kg_per_unit', '0.12'),
('avg_value_inr_per_unit', '35.00');

-- INSERT USERS
INSERT INTO users (id, name, email, password, phone, role, status, created_at) VALUES
(1, 'Apollo Central Hospital', 'apollo.supplies@medicycle.org', '$2y$10$0Xq8QET2celjAVdaNsDYluwKoby4OTpVPJ4xsfwPTHj6fmm671/oK', '+91 9811223344', 'supplier', 'active', '2026-08-05 11:30:00'),
(2, 'Metro Health Consortium', 'metro.pharma@medicycle.org', '$2y$10$0Xq8QET2celjAVdaNsDYluwKoby4OTpVPJ4xsfwPTHj6fmm671/oK', '+91 9822334455', 'supplier', 'active', '2026-08-10 14:15:00'),
(3, 'Hope Rural Health Mission', 'hope.clinic@medicycle.org', '$2y$10$MK2NsW/vSGqqtu4JIQ.H0OzOxgq76rWK2mt/Vdp6IkdKC6S1ytC0m', '+91 9833445566', 'ngo', 'active', '2026-08-12 09:45:00'),
(4, 'Care & Cure Community Clinic', 'care.foundation@medicycle.org', '$2y$10$MK2NsW/vSGqqtu4JIQ.H0OzOxgq76rWK2mt/Vdp6IkdKC6S1ytC0m', '+91 9844556677', 'ngo', 'active', '2026-08-15 16:20:00');

-- INSERT ORGANIZATIONS
INSERT INTO organizations (id, user_id, organization_name, organization_type, license_number, address, city, state, pincode, verification_status, created_at) VALUES
(1, 1, 'Apollo Central Hospital', 'Hospital / Medical Center', 'HOSP-GJ-2024-8891', '101 Medical Enclave, Ring Road', 'Ahmedabad', 'Gujarat', '380015', 'verified', '2026-08-05 11:30:00'),
(2, 2, 'Metro Health Consortium', 'Healthcare Network & Store', 'DIST-MH-2023-4512', '45 Healthcare Boulevard, Andheri East', 'Mumbai', 'Maharashtra', '400069', 'verified', '2026-08-10 14:15:00'),
(3, 3, 'Hope Rural Health Mission', 'Charitable NGO Clinic', 'NGO-REG-2021-0092', 'Village Community Center, Post Bag 12', 'Vadodara', 'Gujarat', '390001', 'verified', '2026-08-12 09:45:00'),
(4, 4, 'Care & Cure Community Clinic', 'Free Community Clinic', 'CLIN-KA-2022-7714', '78 Sunshine Colony, Indiranagar', 'Bengaluru', 'Karnataka', '560038', 'verified', '2026-08-15 16:20:00');

-- INSERT CATEGORIES
INSERT INTO categories (id, category_name, description, icon) VALUES
(1, 'Personal Protective Equipment', 'Gloves, face shields, surgical masks, gowns, and head covers', 'fa-head-side-mask'),
(2, 'Wound Care & Dressings', 'Sterile bandages, gauze swabs, adhesive dressings, and crepe rolls', 'fa-bandage'),
(3, 'Diagnostic Consumables', 'Test strips, disposable probe covers, lancets, and specimen containers', 'fa-vial'),
(4, 'Sterilization & Antiseptics', 'Alcohol prep pads, sterile wraps, indicators, and surface prep wipes', 'fa-pump-medical'),
(5, 'Non-Drug Administration', 'Sterile capped syringes, IV cannulas, infusion sets, and tubing', 'fa-syringe'),
(6, 'First Aid & Emergency Kits', 'Unopened trauma dressings, splints, thermal blankets, and triangular bandages', 'fa-kit-medical'),
(7, 'Orthopedic & Mobility Aids', 'Arm slings, cervical collars, crutch pads, and elastic limb supports', 'fa-crutch');

-- INSERT MEDICAL SUPPLIES
INSERT INTO medical_supplies (id, supplier_id, category_id, supply_name, description, quantity, unit, condition_status, packaging_status, expiry_date, batch_number, storage_requirements, location, availability_date, minimum_request_quantity, status, priority_score, priority_level, created_at) VALUES
(1, 1, 1, 'Nitrile Medical Examination Gloves (M)', 'Powder-free, blue nitrile gloves. Hypoallergenic, non-sterile disposable box.', 450, 'Boxes (100 pcs)', 'New / Unopened', 'Original Factory Seal', '2027-05-30', 'NG-2024-098', 'Cool Dry Place (15-25°C)', 'Ahmedabad', '2026-10-01', 10, 'Available', 75, 'High', '2026-09-01 10:00:00'),
(2, 1, 2, 'Sterile Gauze Swabs 10x10cm (Pack of 50)', '100% cotton, 8-ply absorbent sterile gauze pads. Indelible packaging.', 300, 'Packs', 'Sterile Sealed', 'Tamper Evident Packaging', '2027-08-15', 'GS-2024-112', 'Dry Place', 'Ahmedabad', '2026-10-01', 5, 'Available', 68, 'Medium', '2026-09-02 11:30:00'),
(3, 1, 2, 'Elastic Crepe Bandages (15cm x 4m)', 'Heavy duty stretch crepe bandages for sprain and dressing retention.', 200, 'Rolls', 'New / Unopened', 'Original Factory Seal', '2028-01-20', 'CB-2024-301', 'Room Temperature', 'Ahmedabad', '2026-10-01', 10, 'Available', 52, 'Medium', '2026-09-03 14:00:00'),
(4, 2, 1, 'N95 Surgical Respirator Masks', 'Fluid resistant particulate respirators, NIOSH certified specification.', 600, 'Boxes (20 pcs)', 'New / Unopened', 'Original Factory Seal', '2027-04-10', 'MS-2024-554', 'Room Temperature', 'Mumbai', '2026-10-01', 10, 'Available', 82, 'High', '2026-09-05 09:15:00'),
(5, 2, 4, 'Alcohol Prep Pads (Box of 200)', '70% Isopropyl Alcohol saturated non-woven prep pads. Individually wrapped.', 500, 'Boxes', 'Sterile Sealed', 'Tamper Evident Packaging', '2027-02-28', 'AP-2024-880', 'Away from direct heat', 'Mumbai', '2026-10-01', 10, 'Available', 70, 'High', '2026-09-07 16:45:00'),
(6, 1, 5, 'IV Cannula 20G with Injection Port', 'Sterile single-use catheter with radiopaque stripes and flashback chamber.', 350, 'Units', 'Sterile Sealed', 'Original Factory Seal', '2027-11-30', 'IV-2024-419', 'Controlled Temperature (20-25°C)', 'Ahmedabad', '2026-10-01', 20, 'Available', 65, 'Medium', '2026-09-10 13:20:00'),
(7, 2, 6, 'First Aid Triangular Bandages (100% Cotton)', 'Large non-sterile triangular bandages for arm slings and immobilization.', 120, 'Packs (10 pcs)', 'Surplus Stock', 'Intact Outer Box', '2028-06-30', 'TB-2024-210', 'Room Temperature', 'Mumbai', '2026-10-01', 5, 'Available', 45, 'Low', '2026-09-12 15:00:00'),
(8, 1, 7, 'Adjustable Universal Arm Slings', 'Breathable mesh material with padded shoulder support for rehabilitation.', 80, 'Units', 'New / Unopened', 'Intact Outer Box', '2029-12-31', 'AS-2024-118', 'Room Temperature', 'Ahmedabad', '2026-10-01', 5, 'Available', 40, 'Low', '2026-09-15 10:30:00');

-- INSERT REQUIREMENTS
INSERT INTO requirements (id, organization_id, supply_name, category_id, required_quantity, unit, urgency, required_by, city, description, status, created_at) VALUES
(1, 3, 'Nitrile Examination Gloves (M)', 1, 100, 'Boxes (100 pcs)', 'High', '2026-10-25', 'Vadodara', 'Urgently needed for weekly mobile primary medical checkup camp in tribal villages.', 'Active', '2026-09-20 10:00:00'),
(2, 3, 'Sterile Gauze Swabs & Bandages', 2, 150, 'Packs', 'Medium', '2026-11-05', 'Vadodara', 'Basic wound dressing materials for rural outpatient health post.', 'Active', '2026-09-22 14:15:00'),
(3, 4, 'N95 Respirators & Protective Gowns', 1, 80, 'Boxes (20 pcs)', 'Critical', '2026-10-18', 'Bengaluru', 'Respiratory epidemic preparedness for urban slum outreach dispensary.', 'Active', '2026-09-25 11:20:00'),
(4, 4, 'Alcohol Prep Pads & Disinfectants', 4, 100, 'Boxes', 'Medium', '2026-11-10', 'Bengaluru', 'Essential for daily routine immunization and diagnostic tests.', 'Active', '2026-09-28 09:30:00');

-- INSERT REQUESTS WITH HARVEST-LEDGER HANDOVER STATUSES
-- Request 1: Completed
INSERT INTO requests (id, supply_id, requester_id, requested_quantity, purpose, urgency, preferred_collection_date, message, smart_match_score, status, supplier_remarks, handover_code, requested_at, approved_at, ready_at, completed_at) VALUES
(1, 1, 3, 50, 'Rural Health Camp', 'High', '2026-09-17', 'Requesting 50 boxes of nitrile examination gloves for maternal and child healthcare camps.', 92, 'Completed', 'Approved with priority. Handover successfully completed.', 'HO-7721', '2026-09-15 10:30:00', '2026-09-16 09:00:00', '2026-09-17 10:00:00', '2026-09-18 16:30:00');

-- Request 2: Ready for Handover
INSERT INTO requests (id, supply_id, requester_id, requested_quantity, purpose, urgency, preferred_collection_date, message, smart_match_score, status, supplier_remarks, handover_code, requested_at, approved_at, ready_at, completed_at) VALUES
(2, 4, 4, 40, 'Slum Outreach Clinic', 'Critical', '2026-10-05', 'Urgent need for N95 masks for healthcare staff working in vulnerable areas.', 88, 'Ready for Handover', 'Boxes sanitized and labeled. Ready for pickup at gate 2 dispatch desk.', 'HO-3984', '2026-09-28 11:00:00', '2026-09-29 10:00:00', '2026-09-30 09:30:00', NULL);

-- Request 3: Pending
INSERT INTO requests (id, supply_id, requester_id, requested_quantity, purpose, urgency, preferred_collection_date, message, smart_match_score, status, supplier_remarks, handover_code, requested_at, approved_at, ready_at, completed_at) VALUES
(3, 2, 3, 30, 'Outpatient Wound Care', 'Medium', '2026-10-12', 'Replenishing dressing consumables for post-operative dressing care in mobile clinic.', 75, 'Pending', NULL, 'HO-9104', '2026-10-02 14:45:00', NULL, NULL, NULL);

-- INSERT IMPACT METRICS
INSERT INTO impact_metrics (id, request_id, quantity_redistributed, estimated_waste_avoided, estimated_value_saved, organizations_helped, created_at) VALUES
(1, 1, 50, 6.00, 1750.00, 1, '2026-09-18 16:35:00');

-- INSERT NOTIFICATIONS
INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES
(1, 'New Request Received', 'Hope Rural Health Mission requested 30 packs of Sterile Gauze Swabs.', 'supplier/requests.php', 0, '2026-10-02 14:46:00'),
(3, 'Handover Ready', 'Your request for 50 boxes of Nitrile Gloves was completed and verified!', 'ngo/my-requests.php', 1, '2026-09-18 16:32:00'),
(4, 'Supplies Ready for Handover', 'Metro Health Consortium marked your N95 mask request as Ready for Handover. Pickup Code: HO-3984.', 'ngo/my-requests.php', 0, '2026-09-30 09:35:00');
