# MediCycle — Comprehensive Feature Verification Checklist
**Course:** Web Technologies (BTCO13502) | **Class:** B.Tech III, SEM V, Div-II (ODD 2026–27)  
**Project:** MediCycle — Smart Medical Supply Redistribution Management System  
**Institution:** Sarvajanik College of Engineering and Technology (SCET)

---

## Verification Matrix Summary

| Classification Category | Count | Status Description |
|---|:---:|---|
| **Implemented and Verified** | **28** | Features confirmed working by direct inspection of PHP source, SQL schema, or live HTTP testing. |
| **Partially Implemented** | **4** | Features where core logic exists, but specific constraints (e.g. rate-limiting, SQL unique index) are enforced in PHP rather than the DB engine. |
| **Not Implemented** | **2** | Features historically mentioned in early plans but absent from the actual codebase (e.g., active courier registration). |
| **Cannot Verify from Available Files** | **1** | External production SMTP server credentials (safely masked in `.env`). |

---

## Detailed Feature Verification Breakdown

### 1. User Authentication, Security & Session Management

| Feature / Subsystem | Verification Status | Code File / Evidence | Notes & Audit Remarks |
|---|:---:|---|---|
| Supplier & NGO Role Registration | **Implemented and Verified** | `register.php` (lines 37–39) | Role selector strictly accepts `supplier` and `ngo`. |
| Delivery Partner Registration | **Not Implemented** | `register.php` | Delivery role is omitted from registration input validation. |
| Password Hashing (bcrypt) | **Implemented and Verified** | `register.php` (line 102), `login.php` (line 41) | Uses `password_hash($pw, PASSWORD_BCRYPT)` and `password_verify()`. |
| 6-Digit Email OTP Generation | **Implemented and Verified** | `includes/mail.php` (lines 14–16) | Cryptographically secure `random_int(100000, 999999)`. |
| OTP Hashing in Database | **Implemented and Verified** | `includes/mail.php` (line 50) | Stored as bcrypt hash in `email_otps.otp_hash`. Plain text is never persisted. |
| OTP Expiration (10 minutes) | **Implemented and Verified** | `includes/mail.php` (line 54) | Enforced via `DATE_ADD(NOW(), INTERVAL 10 MINUTE)`. |
| OTP Resend Cooldown (45s) | **Implemented and Verified** | `includes/mail.php` (lines 25–41) | Evaluated using `TIMESTAMPDIFF(SECOND, created_at, NOW())`. |
| OTP Maximum Attempt Threshold | **Implemented and Verified** | `includes/mail.php` (lines 99–108) | Locks and invalidates OTP after 5 failed verification attempts. |
| Secure Cookie Session Flags | **Implemented and Verified** | `includes/session.php` (lines 8–10) | `session.cookie_httponly = 1`, `session.cookie_samesite = 'Lax'`. |
| Inactivity Session Timeout | **Implemented and Verified** | `includes/session.php` (lines 15–22) | Destroys session after 7200 seconds (2 hours) of user inactivity. |
| Session ID Regeneration | **Implemented and Verified** | `login.php` (line 54) | Calls `session_regenerate_id(true)` upon successful credential authentication. |
| CSRF Token Protection | **Implemented and Verified** | `includes/session.php`, all POST forms | Per-session 64-char hex token verified via `verify_csrf_token()`. |
| Multi-Account Switching | **Implemented and Verified** | `includes/session.php` (lines 24–62) | Allows concurrent supplier and NGO sessions in separate tabs. |
| Production SMTP Delivery | **Cannot Verify from Available Files** | `includes/mail.php`, `config/config.php` | Production SMTP credentials are read from `.env` environment variables. |

---

### 2. Healthcare Supplier Module

| Feature / Subsystem | Verification Status | Code File / Evidence | Notes & Audit Remarks |
|---|:---:|---|---|
| Surplus Declaration Form | **Implemented and Verified** | `supplier/add-supply.php` | Captures supply name, category, quantity, unit, batch, expiry, condition, location. |
| Past-Date Expiry Validation | **Implemented and Verified** | `supplier/add-supply.php` | Rejects any expiration date earlier than or equal to current date. |
| Dynamic Priority Score Formula | **Implemented and Verified** | `includes/functions.php` (lines 145–195) | Formula: Baseline 40 + Expiry (up to 45) + Condition (up to 15) + Volume (5) = 1–100. |
| Batch Inventory CRUD | **Implemented and Verified** | `supplier/inventory.php`, `edit-supply.php` | Full Insert, Read, Update, and Soft-Withdrawal implemented. |
| Expiry Color Highlighting | **Implemented and Verified** | `supplier/inventory.php` | Visual badges: Green (>90d), Amber (30–90d), Red (<30d), Dark (Expired). |
| CSV Spreadsheet Bulk Ingestion | **Implemented and Verified** | `supplier/bulk-import.php` | Uploads and parses 11 required columns with row-by-row error reporting. |
| Requisition Review & Approval | **Implemented and Verified** | `supplier/requests.php` | Supplier approves or declines incoming NGO requests with remarks. |
| Stock Auto-Deduction | **Implemented and Verified** | `supplier/requests.php` | Deducts stock inside transaction on approval; sets status to Reserved/Transferred. |
| Requisition Fulfill Clinical Need | **Implemented and Verified** | `supplier/requests.php` (lines 35–100) | Directly fulfills open clinical requirements broadcast by NGOs. |

---

### 3. NGO & Charitable Clinic Module

| Feature / Subsystem | Verification Status | Code File / Evidence | Notes & Audit Remarks |
|---|:---:|---|---|
| Real-time Catalog Multi-Filtering | **Implemented and Verified** | `catalog.php`, `assets/js/main.js` | Filters dynamically by keyword, category, location, urgency without full reload. |
| Requisition Form Submission | **Implemented and Verified** | `catalog.php`, `ngo/search-supplies.php` | Validates requested quantity against available stock and captures clinic purpose. |
| Clinical Needs Broadcast Board | **Implemented and Verified** | `ngo/post-requirement.php`, `requirements.php` | Public board where clinics post unmet needs for suppliers to fulfill. |
| Edit & Withdraw Requirements | **Implemented and Verified** | `ngo/my-requirements.php`, `edit-requirement.php` | Allows clinic to modify or close unfulfilled clinical need broadcasts. |
| Requisition History & Tracking | **Implemented and Verified** | `ngo/my-requests.php`, `request-details.php` | Real-time tracking of pending, approved, and completed requests. |
| Environmental Impact Analytics | **Implemented and Verified** | `ngo/impact.php` | Displays total medical waste averted (kg) and financial value saved (INR). |

---

### 4. Handover Protocol & Security Terminal

| Feature / Subsystem | Verification Status | Code File / Evidence | Notes & Audit Remarks |
|---|:---:|---|---|
| 6-Digit Handover Code Generation | **Implemented and Verified** | `includes/functions.php` (line 253) | Generates `random_int(100000, 999999)` upon requisition approval. |
| Plaintext Code Storage | **Partially Implemented** | `requests.handover_code` | Stored in plain text (not hashed) to allow visual display on recipient passes. |
| Digital Collection Pass | **Implemented and Verified** | `ngo/code-pass.php`, `ngo/request-details.php` | Clean pass showing 6-digit digits, donor address, phone, and batch data. |
| Single-Page Clean Print Preview | **Implemented and Verified** | `assets/css/style.css`, `assets/js/main.js` | Dedicated `@media print` rule rendering isolated card without truncation. |
| Dynamic Handshake QR Payload | **Implemented and Verified** | `includes/functions.php` (lines 260–263) | Encodes `MEDICYCLE-HANDSHAKE:REQ-{id}:CODE-{code}` via SVG API. |
| Camera & File QR Scanner | **Implemented and Verified** | `includes/verify-handover-view.php` | Integrated `html5-qrcode` engine for camera scanning and image upload. |
| Row-Locking Concurrency Control | **Implemented and Verified** | `includes/functions.php` (line 657) | Employs `SELECT ... FOR UPDATE` inside `beginTransaction()` to prevent double-claim. |
| Single-Use Enforcement | **Implemented and Verified** | `includes/functions.php` (lines 631–639) | Rejects previously completed codes with warning banner. |
| Handover PIN Attempt Rate-Limiting | **Not Implemented** | `includes/functions.php` | Handover PIN does not lock out after N attempts (unlike Email OTP). Disclosed in limitations. |

---

### 5. Real-Time Notification & AJAX Engine

| Feature / Subsystem | Verification Status | Code File / Evidence | Notes & Audit Remarks |
|---|:---:|---|---|
| Background Event Poller | **Implemented and Verified** | `api/poll_status.php`, `assets/js/main.js` | Polls every 10 seconds while tab is active; updates unread badge counter. |
| Tab Visibility Throttling | **Implemented and Verified** | `assets/js/main.js` (line 687) | Pauses polling when `document.visibilityState !== 'visible'`. |
| Session Lock Release | **Implemented and Verified** | `api/poll_status.php` (line 20) | Calls `session_write_close()` so polling never blocks concurrent page clicks. |

---

### 6. Relational Database Architecture

| Table Name | Verification Status | Engine / Schema Reality | Foreign Key Constraints & Indexes |
|---|:---:|---|---|
| `users` | **Implemented and Verified** | InnoDB, 3NF | Primary key `id`. Unique key on `email`. Role index on `role`. |
| `organizations` | **Partially Implemented** | InnoDB, 3NF | FK `user_id -> users(id)` with CASCADE. Note: `user_id` index is regular `KEY`, not `UNIQUE KEY` in SQL, but enforced 1:1 in PHP. |
| `categories` | **Implemented and Verified** | InnoDB, 3NF | Primary key `id`. Unique key on `category_name`. Controlled non-drug list. |
| `medical_supplies` | **Implemented and Verified** | InnoDB, 3NF | FKs: `supplier_id -> users(id)` (CASCADE), `category_id -> categories(id)` (RESTRICT). |
| `requests` | **Implemented and Verified** | InnoDB, 3NF | FKs: `supply_id -> medical_supplies(id)` (CASCADE), `requester_id -> users(id)` (CASCADE). |
| `requirements` | **Implemented and Verified** | InnoDB, 3NF | FKs: `organization_id -> users(id)` (CASCADE), `category_id -> categories(id)`. |
| `impact_metrics` | **Partially Implemented** | InnoDB, 3NF | FK `request_id -> requests(id)` with CASCADE. Note: `request_id` index is regular `KEY`, not `UNIQUE KEY` in SQL schema. |
| `notifications` | **Implemented and Verified** | InnoDB, 3NF | FK `user_id -> users(id)` with CASCADE. Composite index on `(user_id, is_read)`. |
| `email_otps` | **Partially Implemented** | InnoDB, 3NF | Primary key `id`. `user_id` column exists as INT, but has NO foreign key constraint in MySQL dump. Cleaned up in PHP. |
| `deliveries` (Legacy) | **Implemented and Verified** | InnoDB, 3NF | Retained in schema for backward compatibility. FKs: `request_id -> requests(id)` (CASCADE), `delivery_partner_id -> users(id)` (SET NULL). |
| `system_settings` (Unused) | **Implemented and Verified** | InnoDB, 3NF | Present in SQL seed with 5 configuration rows, but not queried by application PHP code. |
