# MediCycle — Final Academic Report Audit & Verification Report
**Course:** Web Technologies (BTCO13502) — B.Tech Computer Engineering (SEM V, Div-II)  
**Institution:** Sarvajanik College of Engineering and Technology (SCET), Surat  
**Academic Term:** Odd Semester 2026–2027  
**Group No.:** 7  
**Project Title:** MediCycle — Smart Medical Supply Redistribution Management System  
**Audit Date:** October 22, 2026  
**Auditor:** Senior System Architect, Database Engineer & Academic Documentation Reviewer

---

## 1. Executive Summary of Audit

An exhaustive, line-by-line inspection of the actual implementation in the project repository (`medicycle`) was conducted. The project was verified against all architectural claims, security implementations, database schemas (`database/medicycle_db.sql`), user role definitions, and workflow controllers.

### Key Audit Findings:
1. **User Role Architecture (CRITICAL):**
   - **Audited Truth:** The live application supports exactly **TWO primary registration roles**: **Healthcare Supplier** (`supplier`) and **NGO / Charitable Clinic** (`ngo`).
   - **Code Verification:** In [`register.php`](file:///C:/Users/ventura/.gemini/antigravity/scratch/medicycle/register.php#L37-L39), the registration form controller strictly validates:
     ```php
     if (empty($formData['role']) || !in_array($formData['role'], ['supplier', 'ngo'])) {
         $errors[] = 'Please select whether you represent a Supplier or an NGO/Clinic.';
     }
     ```
   - **Correction Made:** All references suggesting that a courier or delivery partner can register, log in, or manage deliveries via an active third role were removed from the primary workflow descriptions. A dedicated legacy database note explains the `deliveries` table and `delivery_partner_id` columns present in the SQL schema.

2. **Handover Protocol:**
   - **Audited Truth:** Handover is a direct, mutual physical transfer between the Healthcare Supplier and the NGO/Clinic recipient.
   - **Code Verification:** When a supplier approves a requisition or fulfills a broadcast clinical requirement, a 6-digit `handover_code` is generated (`random_int(100000, 999999)`). The NGO views and prints their **Secure Collection Pass** with this PIN and an encoded QR code (`ngo/code-pass.php`, `ngo/request-details.php`). The supplier verifies and redeems this code in [`supplier/verify-handover.php`](file:///C:/Users/ventura/.gemini/antigravity/scratch/medicycle/supplier/verify-handover.php) via camera scan, image upload, or manual PIN entry, which locks the row (`SELECT ... FOR UPDATE`), completes the request, deducts stock, records environmental impact, and triggers mutual notifications.

3. **Database Schema & Table Count Consistency:**
   - **Audited Truth:** The database dump (`database/medicycle_db.sql`) contains exactly **11 tables**:
     - **9 Active Core Tables:** `users`, `organizations`, `categories`, `medical_supplies`, `requests`, `requirements`, `impact_metrics`, `notifications`, `email_otps`.
     - **1 Legacy Table:** `deliveries` (historically tracked multi-stage courier transit; retained in schema with foreign keys).
     - **1 Unused Configuration Table:** `system_settings` (present in database export with 5 seed rows, but unreferenced across all application PHP source files).
   - **Correction Made:** The report now maintains a unified, exact 11-table count across the database overview, normalization analysis, foreign key documentation, ER diagram, and table summary.

---

## 2. Detailed Technical Claims Audit

| Module / Claim | Status in Codebase | Evidence in Source Files | Corrections Applied to Report |
|---|:---:|---|---|
| **Role-based Registration** | **Verified** | `register.php`: only `'supplier'` and `'ngo'` allowed in role dropdown. | Removed all courier registration claims. |
| **Password Security** | **Verified** | `register.php`: `password_hash($password, PASSWORD_BCRYPT)`. `login.php`: `password_verify()`. | Confirmed bcrypt hashing with standard salt. |
| **Email OTP 2FA** | **Verified** | `includes/mail.php`: 6-digit random code stored as bcrypt hash in `email_otps.otp_hash`. 10-minute validity (`DATE_ADD(NOW(), INTERVAL 10 MINUTE)`), 45-second resend cooldown, 5-attempt brute-force threshold. | Corrected validity from 5 min to 10 min; noted 5-attempt account lock. |
| **Session Security** | **Verified** | `includes/session.php`: `session.cookie_httponly=1`, `session.cookie_samesite='Lax'`. 2-hour inactivity timeout (`time() - $_SESSION['last_activity'] > 7200`). Session ID regeneration (`session_regenerate_id(true)`) on login. | Accurately documented session configuration. |
| **CSRF Protection** | **Verified** | `includes/session.php`: `csrf_token()`, `csrf_field()`. `verify_csrf_token()` checked on all mutating POST routes. | Confirmed per-session cryptographic CSRF token. |
| **Supply Priority Scoring** | **Verified** | `includes/functions.php`: `calculate_supply_priority_score()`. Baseline = 40; Expiry <=90d (+45), <=180d (+35), <=365d (+20), >365d (+10); Condition New (+15), Sterile Sealed (+10); Volume >300 (+5). Bounded 1–100. | Replaced generic descriptions with the exact mathematical formula. |
| **Batch Expiry Validation** | **Verified** | `supplier/add-supply.php`: Rejects dates <= today. Warning banners on inventory table for approaching expiry. | Documented server-side and client-side date checks. |
| **CSV Bulk Ingestion** | **Verified** | `supplier/bulk-import.php`: Validates 11 specific columns (`supply_name`, `category_name`, `quantity`, `unit`, `condition_status`, `packaging_status`, `expiry_date`, `batch_number`, `storage_requirements`, `location`, `description`). Rejects invalid dates, calculates priority, logs row-by-row errors. | Fully documented CSV template and validation loop. |
| **Live Status Polling** | **Verified** | `api/poll_status.php`: Polled every 10 seconds via `assets/js/main.js` only while `document.visibilityState === 'visible'`. Releases session lock (`session_write_close()`) to avoid blocking navigation. | Corrected claim from WebSockets to AJAX polling every 10s. |
| **Handover PIN Storage** | **Verified** | `requests.handover_code`: Stored in **plain text** (`VARCHAR(10)`/`VARCHAR(20)`), NOT hashed. | Corrected security claim: OTP is hashed, but handover PIN is stored in plain text. |
| **Handover Rate Limiting** | **Not Implemented** | `verify_and_complete_handover()` checks code existence and single-use status, but does NOT enforce a 5-attempt rate limit lock on PIN entry. | Identified as a known security limitation in Section 11. |
| **QR Code Generation** | **Verified** | `includes/functions.php`: `get_handshake_qr_url()` encodes `MEDICYCLE-HANDSHAKE:REQ-{id}:CODE-{code}` via SVG API. Decoded in `verify-handover-view.php` via `html5-qrcode`. | Verified camera scan, file upload, and manual PIN entry. |
| **Database Foreign Keys** | **Verified** | `database/medicycle_db.sql`: 8 foreign key constraints with `ON DELETE CASCADE`, 1 with `ON DELETE SET NULL`. `email_otps.user_id` has NO foreign key. | Documented exact MySQL constraints. |
| **Uniqueness of Keys** | **Clarified** | `organizations.user_id` and `impact_metrics.request_id` have standard non-unique `KEY` indexes in MySQL, but are treated as 1:1 in application logic. | Clarified distinction between SQL schema enforcement and application logic. |

---

## 3. Discrepancies Resolved Between SQL Dump & Application Code

1. **Table Count Mismatch:**
   - Prior report versions mentioned 8 or 10 tables inconsistently.
   - The verified database dump `medicycle_db.sql` defines exactly **11 tables**. The report now explicitly enumerates all 11 tables, grouping them into 9 active tables, 1 legacy table (`deliveries`), and 1 unused table (`system_settings`).

2. **Foreign Key on `email_otps`:**
   - Some documentation claimed `email_otps.user_id` is a foreign key with cascade delete.
   - The SQL schema inspection confirms `user_id int(11) DEFAULT NULL` is indexed only via composite index `idx_otp_lookup (email, otp_type, is_used)`. There is no SQL foreign key constraint; orphan cleanup is handled by PHP application routines.

3. **Courier Role in Seed Data:**
   - In `users` table definition, the column enum is `enum('admin','supplier','ngo','delivery')`. Seed data contains an old courier record.
   - However, in `register.php`, `delivery` is disabled, and the primary application flow operates strictly peer-to-peer (Supplier to NGO). The report explains this clearly as historical/extensible schema scaffolding.

---

## 4. Final Document Assets Generated

The following production files have been compiled into `MediCycle_Report_Final`:
- **`MediCycle_OEP_Final_Report.docx`**: Complete, formal 13-section academic report with embedded high-resolution figures, formatted tables, and IEEE references.
- **`MediCycle_ER_Diagram.png`**: High-resolution 300 DPI diagram showing all 11 tables, primary keys, foreign keys, and cardinalities.
- **`MediCycle_Verification_Checklist.md`**: Feature-by-feature verification matrix.
