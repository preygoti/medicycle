# MediCycle — Smart Medical Supply Redistribution Management System
### Harvest Ledger Healthcare Edition — Direct Supplier <-> NGO Redistribution Model

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB%2010.4-orange.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/Academic-OEP%20Project-green.svg)](#)

**MediCycle** is a full-stack Web Technology Open-Ended Project (OEP) engineered on the **Harvest Ledger direct redistribution model**. It connects healthcare suppliers (medical stores, hospitals, distributors) directly with verified recipient NGOs and charitable clinics to prevent usable non-drug healthcare supplies from being incinerated or dumped in landfills.

---

## 1. Core Model (Harvest Ledger Comparison)

| Dimension | Harvest Ledger | MediCycle (Healthcare Edition) |
|---|---|---|
| **Donor / Supplier** | Restaurant / Bakery / Grocery | Medical Store / Hospital / Medical Supplier |
| **Surplus Listed** | Food Surplus Lots | Non-Drug Medical Supply Surplus |
| **Recipient** | NGO / Food Bank | NGO / Charitable Community Clinic |
| **Request Model** | Food Request | Supply Requisition |
| **Fulfillment** | Direct Pickup / Collection | Direct Handover / Collection Pass |
| **Verification** | Pickup Code Verification | Handover Code Verification |
| **Impact Accounting** | Food Waste Diverted (kg) | Medical Waste Diverted (kg) & Funds Saved |

---

## 2. Safety & Scope Boundary (Strict Protocol)

> **SAFETY NOTICE:** To eliminate pharmaceutical abuse, drug diversion, and cold-chain liabilities, **MediCycle strictly redistributes eligible non-drug consumables and PPE only.**

* **Eligible Supplies:**
  * Examination & surgical gloves (unopened factory boxes)
  * Surgical masks, N95 respirators, and face shields
  * Sterile gauze swabs, crepe bandages, and dressing packs
  * Unopened first aid & minor trauma kits
  * Diagnostic consumables (non-drug test strips, lancets)
  * Sterile non-drug disposables (IV cannulas, capped infusion sets)
* **Strictly Prohibited:** Prescription medicines, scheduled drugs, biologics, blood products, opened packaging, and expired lots.

---

## 3. Technology Stack

* **Backend:** PHP 8.2 (Vanilla, modular MVC-inspired layout, PDO prepared statements, session protection)
* **Database:** MySQL / MariaDB (`medicycle_db`, 3NF relational schema, foreign keys, transactions)
* **Frontend:** HTML5, CSS3, JavaScript (Vanilla ES6), Bootstrap 5.3
* **Charts & Analytics:** Chart.js 4.4
* **Icons & Fonts:** FontAwesome 6, Inter Google Font

---

## 4. Key Features & OEP Requirements Satisfied

1. **Two Core Operational Roles:**
   * **Supplier:** Lists surplus inventory, tracks lots, reviews requests, issues Handover Codes, marks packages ready, and completes collections.
   * **NGO / Clinic:** Searches and filters surplus batches, receives algorithmic smart match scores, posts community requirements, requests supplies, and confirms physical receipt.
2. **Complete CRUD Operations:**
   * **INSERT:** Add surplus supplies, submit collection requests, post clinical needs, register organizations.
   * **READ:** Filterable catalog, inventory dashboard, transaction history, real-time metrics.
   * **UPDATE:** Edit supplies, modify posted requirements, approve requests, issue Handover Codes, verify completions.
   * **DELETE:** Delete listed supplies, withdraw requirements, clean up inventory lots with confirmation.
3. **Advanced Search & Multi-criteria Filtering:** Keyword, category, city/location, and priority filters.
4. **Smart Matchmaking Engine (Algorithmic AI Module):** Computes a 0–100 match percentage between clinic needs and available lots based on category alignment, geographic distance, and urgency.
5. **Direct Handover Flow:** Eliminates admin bottlenecks and courier dependencies. Generates unique Handover Verification Codes for verified physical collections.
6. **Redistribution Impact Metrics:** Real-time calculation of landfill packaging waste diverted (kg) and community funds saved (₹).

---

## 5. End-to-End Workflow

```
Supplier Registers & Logs In
       │
       ▼
Supplier Lists Surplus Consumables (INSERT into medical_supplies)
       │
       ▼
NGO Discovers Supply & Submits Requisition (INSERT into requests with Preferred Date)
       │
       ▼
Supplier Accepts Request (UPDATE requests, decrements inventory, generates Handover Code)
       │
       ▼
Supplier Marks "Ready for Handover" (Packages prepared for collection)
       │
       ▼
NGO Arrives & Presents Handover Code (Direct physical collection)
       │
       ▼
NGO / Supplier Confirms Receipt (UPDATE requests to Completed)
       │
       ▼
Transaction Completed & Impact Metrics Recorded (INSERT/UPDATE impact_metrics)
```

---

## 6. Demo Accounts (College Evaluation)

| Role | Demo Email | Password | Organization |
|---|---|---|---|
| **Supplier** | `apollo.supplies@medicycle.org` | `Supplier@123` | Apollo Health Supplies (Ahmedabad) |
| **Supplier** | `metro.pharma@medicycle.org` | `Supplier@123` | Metro Healthcare Distribution |
| **NGO / Clinic** | `hope.clinic@medicycle.org` | `Ngo@123` | Hope Community Health Clinic (Ahmedabad) |
| **NGO / Clinic** | `care.foundation@medicycle.org` | `Ngo@123` | Care & Cure Rural Mission |

*Quick-fill demo buttons are provided directly on the Login page for instant one-click evaluator login.*

---

## 7. Running the Project Locally

```bash
# 1. Start Apache & MySQL in XAMPP
# 2. Database medicycle_db is located at database/medicycle_db.sql
# 3. Access the application in browser:
http://127.0.0.1:8000/
# or via XAMPP web root:
http://localhost/medicycle/

# 4. Run Automated End-to-End Test Suite:
php tests/test_workflow.php
```
