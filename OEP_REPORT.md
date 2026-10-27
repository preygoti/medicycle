# SARVAJANIK COLLEGE OF ENGINEERING AND TECHNOLOGY
## COMPUTER ENGINEERING DEPARTMENT
### B. Tech III, SEM V, Div- II (ODD 2026-27)
### Subject Name: Web Technologies (BTCO13502)

---

# MEDICYCLE
### Smart Medical Supply Redistribution Management System
**Decentralized Healthcare Surplus Recovery & Requisition Platform**

---

### **Academic Submission Details**
* **Project Type:** Open Ended Problem (OEP)
* **Group No.:** 7
* **Technology Stack:** PHP · MySQL · HTML5 · CSS3 (Bootstrap 5.3) · JavaScript (ES6)
* **Evaluation / Submission Period:** **26/10/2026 to 30/10/2026**
* **Live Production URL:** [http://medicycle.infinityfreeapp.com](http://medicycle.infinityfreeapp.com)
* **GitHub Repository:** [https://github.com/preygoti/medicycle](https://github.com/preygoti/medicycle)

---

## Group Members & Work Division (Group 7)

| Enrollment No. | Student Name | Assigned Role | Primary Module Responsibilities |
|---|---|---|---|
| **ET24BTCO145** | **RUDRA BHUVA** | System Architect & Backend Lead | Core Database Schema (`medicycle_db.sql`), Secure Session Management (`session.php`), Authentication Framework (`auth.php`), Production Cloud Deployment |
| **ET24BTCO151** | **YAGNEN GHODADARA** | Frontend & UI/UX Designer | Responsive Bootstrap 5.3 UI System, Global Theme CSS (`style.css`), Accessible Mobile Navigation, Frosted Glass Modal Pop-ups |
| **ET24BTCO154** | **RASES GORASIYA** | Healthcare Supplier Module Lead | Supplier Inventory CRUD (`inventory.php`, `add-supply.php`, `edit-supply.php`), Batch Management, Requisition Approval Workflow |
| **ET24BTCO155** | **UTSAV GORASIYA** | NGO / Clinic Module Lead | Supply Catalog Search & Multi-criteria Filtering (`catalog.php`), Clinical Requirements Broadcast CRUD (`post-requirement.php`), Request Tracking |
| **ET24BTCO156** | **PREY GOTI** | Logistics & Security Specialist | Delivery Partner Transit Pipeline (`update-status.php`), Secure Handshake Verification Terminal (QR & 6-Digit PIN), Real-Time Polling Engine (`main.js`) |

---

## 1. Abstract & Executive Summary

Every year, tons of sterile, unexpired, non-drug healthcare consumables—such as surgical gloves, N95 respirators, sterile gauze swabs, and disposable syringes—are discarded or incinerated due to institutional overstocking and administrative surplus. Simultaneously, charitable clinics, community outreach shelters, and rural health posts face severe inventory deficits.

**MediCycle** is an open-ended web application engineered to bridge this critical gap through a decentralized medical supply redistribution platform. Developed strictly using **PHP, MySQL, HTML5, CSS3/Bootstrap, and JavaScript**, MediCycle models the entire lifecycle of medical supply redistribution: from initial surplus declaration and clinic requisition to courier transit and mutual physical handover verification.

---

## 2. Fulfillment of OEP Guidelines Checklist

| No. | OEP Guideline Requirement | Project Implementation Status | Proof in Codebase |
|---|---|:---:|---|
| **1** | **Group of 5 Students** | **SATISFIED** | Structured modular distribution across 5 specific functional domains (Lead, Frontend, Supplier, NGO, Logistics). |
| **2** | **PHP-MySQL-HTML-CSS (Bootstrap)-JS Coding** | **SATISFIED** | • **PHP:** Modular procedural/MVC backend with PDO prepared statements.<br>• **MySQL:** 8-table normalized relational database with foreign keys & indexes.<br>• **HTML5:** Semantic HTML across all layouts.<br>• **CSS (Bootstrap 5.3):** Custom responsive styles in `style.css`.<br>• **JavaScript:** Client-side validation, live status polling, QR generator, dynamic print handling in `main.js`. |
| **3** | **Database Part with INSERT, UPDATE, DELETE, SEARCH** | **SATISFIED** | • **INSERT:** Post supplies, submit requests, broadcast clinical needs, register accounts.<br>• **UPDATE:** Edit supplies, approve requisitions, update transit milestones, complete physical handover.<br>• **DELETE:** Remove supplies, withdraw needs, permanent account deletion.<br>• **SEARCH:** Real-time multi-filter catalog by keyword, category, urgency, location. |
| **4** | **Proper CSS Design & Field Validation** | **SATISFIED** | • **Design:** Clean healthcare teal theme, responsive cards, glassmorphic centered modals, no screen clipping.<br>• **Validation:** Dual-layer client-side Bootstrap validation (positive quantities, future expiry, valid phone, password matching) + strict server-side sanitization. |
| **5** | **Session Management** | **SATISFIED** | Secure session handling with HTTP-only cookies, role-based page protection, CSRF validation tokens, multi-account switching, and safe logout destroying session cookies. |
| **6** | **Whole Working Application (Not 1-2 forms)** | **SATISFIED** | Comprehensive application consisting of **85+ files**, including complete portal dashboards for 3 distinct roles, unified catalog, notification bell, verification terminals, and reports. |
| **7** | **All Pages & Forms Interlinked** | **SATISFIED** | Cohesive navigation via global header, horizontal mobile app bar, desktop sidebars, breadcrumbs, action triggers, and quick notification deep links. No orphan pages. |
| **8** | **OEP Report Attached** | **SATISFIED** | Full academic report detailing architectural diagrams, database schema, module descriptions, and test logs. |

---

## 3. Scope & Healthcare Safety Protocol

To eliminate drug diversion, substance abuse, and regulatory cold-chain liabilities, **MediCycle strictly enforces a non-drug consumable boundary**:

* **Eligible Redistribution Items:**
  * Personal Protective Equipment (unopened boxes of nitrile gloves, surgical masks, N95 respirators, face shields, gowns)
  * Wound care & sterile dressings (gauze swabs, compression bandages, sterile surgical drapes)
  * Sterile non-drug disposables (disposable syringes, IV cannulas, infusion tubing)
  * Diagnostic disposables (non-pharmaceutical test strips, lancets, urine collection cups)
* **Strictly Prohibited:** Prescription pharmaceuticals, narcotics, vaccines, refrigerated biologics, controlled substances, opened packaging, and expired lots.

---

## 4. System Architecture & Technical Stack

```
┌────────────────────────────────────────────────────────────────────────┐
│                        CLIENT / BROWSER TIER                           │
│  HTML5 · CSS3 (Bootstrap 5.3 + style.css) · Vanilla JavaScript (ES6)   │
│  Chart.js Analytics · QR Code Generator · Print Preview Styles         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTP / HTTPS Requests
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        APPLICATION SERVER (PHP)                        │
│  • Session & Role-Based Guard (includes/auth.php, includes/session.php) │
│  • Input Validation & CSRF Protection (includes/functions.php)        │
│  • RESTful Background Event Poller (api/poll_status.php)               │
│  • Role Portals: Supplier (/supplier), NGO (/ngo), Courier (/delivery) │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ PDO Prepared Statements
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         DATABASE TIER (MySQL)                          │
│  medicycle_db (Users, Organizations, Medical Supplies, Categories,    │
│               Requests, Requirements, Deliveries, Notifications)       │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Relational Database Design (Schema & Entities)

The database `medicycle_db` is designed in Third Normal Form (3NF) to ensure referential integrity, eliminate duplicate storage, and maintain relational constraints.

```
       ┌──────────────────┐               ┌──────────────────┐
       │      users       │ 1           1 │  organizations   │
       │──────────────────│───────────────│──────────────────│
       │ id (PK)          │               │ id (PK)          │
       │ email (UQ)       │               │ user_id (FK)     │
       │ role             │               │ organization_name│
       └────────┬─────────┘               └──────────────────┘
                │ 1
                │
                ├─────────────────────────────────────────┐
                │ 1                                       │ 1
                ▼                                         ▼
       ┌──────────────────┐                      ┌──────────────────┐
       │ medical_supplies │                      │   requirements   │
       │──────────────────│                      │──────────────────│
       │ id (PK)          │                      │ id (PK)          │
       │ supplier_id (FK) │                      │ organization_id  │
       │ category_id (FK) │                      │ supply_name      │
       │ quantity         │                      │ required_quantity│
       │ status           │                      │ handover_code    │
       └────────┬─────────┘                      └──────────────────┘
                │ 1
                │
                ▼
       ┌──────────────────┐
       │     requests     │
       │──────────────────│
       │ id (PK)          │
       │ supply_id (FK)   │
       │ requester_id(FK) │
       │ requested_qty    │
       │ handover_code    │
       │ status           │
       └────────┬─────────┘
                │ 1
                ▼
       ┌──────────────────┐
       │    deliveries    │
       │──────────────────│
       │ id (PK)          │
       │ request_id (FK)  │
       │ delivery_partner │
       │ delivery_status  │
       └──────────────────┘
```

### Table Dictionary
1. **`users`**: Core user authentication details (id, name, email, password hash, phone, role [supplier, ngo, delivery], status).
2. **`organizations`**: Associated facility profile details (user_id, organization_name, license_number, address, city).
3. **`categories`**: Standard medical supply taxonomy (PPE, Wound Care, Disposables, Diagnostic, Surgical).
4. **`medical_supplies`**: Donated supply inventory (supplier_id, category_id, supply_name, batch_number, quantity, unit, expiry_date, status).
5. **`requests`**: Surplus requisitions requested by clinics from suppliers (supply_id, requester_id, requested_quantity, handover_code, status).
6. **`requirements`**: Broadcasted clinical needs posted by clinics looking for donors (organization_id, supply_name, required_quantity, urgency, status).
7. **`deliveries`**: Logistics consignments (request_id, delivery_partner_id, delivery_status, pickup_status, tracking_notes).
8. **`notifications`**: Real-time alerts delivered to users (user_id, title, message, link, is_read, created_at).

---

## 6. Functional Modules & User Workflows

### Module A: Authentication, Security & Session Management
* **Dual Security:** Passwords hashed with `PASSWORD_BCRYPT`. Sessions use strict cookie lifetime, HttpOnly, and SameSite parameters.
* **Role Verification:** Strict authorization guards prevent unauthorized role access (e.g., an NGO cannot access supplier inventory endpoints).
* **Cross-Site Request Forgery (CSRF):** Unique cryptographically secure token checked on every state-mutating POST action.
* **Multi-Account Switching:** Allows seamless switching between test accounts without repetitive login forms.

### Module B: Healthcare Supplier Portal
* **Inventory Control:** Add, edit, adjust, and withdraw listings of unexpired surplus medical consumables.
* **Incoming Requisition Review:** Accept or decline clinic requests with custom handover pickup instructions.
* **Broadcast Fulfillment:** Browse posted urgent requirements from local clinics and fulfill them with 1-click allocation.
* **Donor Impact Tracking:** Automatically displays package waste avoided (kg) and monetary value saved for community care.

### Module C: NGO & Charitable Clinic Portal
* **Live Surplus Catalog:** Filter available healthcare consumables by keyword, category, city/location, and quantity.
* **Urgent Requirements Broadcast:** Post clinical shortages so nearby suppliers and hospitals can review and fulfill them.
* **Requisition Tracking:** Monitor request approval milestones with live status pills.
* **Secure Collection Pass:** View dynamic QR codes and 6-digit handshake PINs to present at physical collection points.

### Module D: Delivery Partner & Logistics Pipeline
* **Dispatch Job Board:** Review open delivery jobs, pickup locations, and clinic destination addresses.
* **Milestone Progression:** Advance consignments through 4 lifecycle phases: `Assigned` $\to$ `Accepted` $\to$ `Picked Up` $\to$ `Delivered`.
* **Real-Time Transit Logging:** Add courier tracking notes and route observations.

### Module E: Dual-Handshake Physical Verification Terminal
* **Security Principle:** Release of medical supplies requires mutual physical verification to eliminate fraud and phantom collections.
* **Dual-Channel Verification:**
  1. High-resolution machine-readable QR Code.
  2. 6-Digit Confidential Handshake PIN.
* **Single-Sheet Pass Printing:** Custom CSS `@media print` engine isolates the pass card onto exactly **1 physical page** with zero clutter.
* **HTTP-Compliant Clipboard Copy:** Uses resilient fallback logic to ensure PIN copy notifications work across all devices and hosting protocols.

---

## 7. Sample Verification & Test Scenarios

| Test Case ID | Test Objective | Input Data | Expected Output | Actual Result |
|---|---|---|---|:---:|
| **TC-01** | Supplier lists surplus supplies | Name: "Nitrile Gloves", Qty: 50, Expiry: 2028-05-01 | Record inserted into `medical_supplies` with status `Available`. | **PASS** |
| **TC-02** | Form validation on expired supply | Expiry: 2023-01-01 (Past Date) | Submission blocked with message: *"Expiry date must be in the future."* | **PASS** |
| **TC-03** | Clinic submits surplus requisition | Request 20 boxes of Nitrile Gloves | Record created in `requests` table with status `Pending`. Supplier receives real-time alert. | **PASS** |
| **TC-04** | Supplier accepts requisition | Click *"Accept"* button in Supplier Requests | Status changes to `Accepted`; 6-digit handover PIN and QR pass generated. | **PASS** |
| **TC-05** | Courier accepts delivery run | Action: *"Accept Job"* | Delivery assigned to courier; status updated to `Accepted`. | **PASS** |
| **TC-06** | Physical handshake verification | Enter 6-digit PIN into Handover Terminal | Verification successful; request updated to `Completed`; impact metrics incremented. | **PASS** |
| **TC-07** | Pass card printing verification | Click *"Print Pass"* button | Browser print preview renders 100% full pass card on exactly **1 physical page**. | **PASS** |
| **TC-08** | Mobile responsive navigation | Open site on viewport width 375px (iPhone) | Horizontal scrolling navigation bar operates cleanly; pop-ups open centered with blurred backdrop. | **PASS** |

---

## 8. Deployment & Demonstration Links

* **Live Cloud Deployment (InfinityFree):** [http://medicycle.infinityfreeapp.com](http://medicycle.infinityfreeapp.com)
* **GitHub Repository:** [https://github.com/preygoti/medicycle](https://github.com/preygoti/medicycle)
* **Default Database Schema:** `database/medicycle_db.sql`
* **Test Evaluator Credentials:**
  * **Healthcare Supplier:** `apollo.supplies@medicycle.org` / `Supplier@123`
  * **NGO / Clinic:** `hope.clinic@medicycle.org` / `Ngo@123`
  * **Courier / Delivery:** `delivery@medicycle.org` / `Delivery@123`

---

## 9. Conclusion

The **MediCycle** project successfully addresses all requirements set forth in the Open-Ended Project (OEP) syllabus. By developing a full-stack, cohesive system using PHP, MySQL, HTML5, Bootstrap, and JavaScript, the project demonstrates real-world software engineering principles, robust database normalization, defense-in-depth web security, and high-fidelity responsive user interface design.
