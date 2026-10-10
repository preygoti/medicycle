# MediCycle — Smart Medical Supply Redistribution Management System
### Direct Healthcare Supplier <-> Recipient NGO/Clinic Redistribution Model

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB%2010.4-orange.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/Academic-OEP%20Project-green.svg)](#)

**MediCycle** is a full-stack Web Technology Open-Ended Project (OEP) engineered as a decentralized medical supply redistribution platform. It connects healthcare suppliers (medical stores, hospitals, surgical distributors) directly with verified recipient NGOs and charitable clinics to prevent usable non-drug healthcare consumables from being incinerated or dumped in landfills.

---

## 1. Core Redistribution Architecture (4 Distinct Roles)

MediCycle operates on a multi-tier redistribution ecosystem across 4 distinct user roles:

| Role | Target Persona | Key Capabilities in MediCycle |
|---|---|---|
| **Admin** | System Administrator / Regulatory Authority | Verify organizations, moderate supply listings, manage user directory, audit logistics consignments, system settings & macro reports |
| **Healthcare Supplier** | Hospitals, Medical Distributors, Surgical Stores | Post surplus unexpired consumables, manage batches, approve requisitions, monitor transfers, view donor impact stats |
| **NGO / Clinic** | Charitable Clinics, Non-Profit Healthcare Centers | Search available consumables, smart match recommendation scores, post clinical requirements, request batches, confirm receipt |
| **Delivery Partner** | Logistics Volunteers, Fleet Couriers | View assigned consignments, accept pickup runs, update transit milestones (Pickup $\to$ In Transit $\to$ Delivered), track delivery logs |

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

1. **Four Active Operational Roles:** Complete dedicated dashboards and workflows for Admin, Supplier, NGO/Clinic, and Delivery Partner.
2. **Complete CRUD Operations:**
   * **INSERT:** Add surplus supplies, submit collection requests, post clinical needs, register organizations, create categories.
   * **READ:** Filterable catalog, inventory dashboard, transaction history, audit trails, real-time metrics.
   * **UPDATE:** Edit supplies, modify posted requirements, approve requests, update delivery milestones, toggle verifications.
   * **DELETE:** Delete listed supplies, withdraw requirements, remove categories with dependency checks.
3. **Advanced Search & Multi-criteria Filtering:** Keyword, category, city/location, and priority filters across supplies, organizations, and users.
4. **Smart Matchmaking Engine (Algorithmic AI Module):** Computes a 0–100 match percentage between clinic needs and available lots based on category alignment, geographic distance, and urgency.
5. **End-to-End Delivery & Fulfillment Pipeline:** Auto-generates delivery dispatch records upon supplier approval, allowing couriers/volunteers to accept, update pickup, transit, and delivery states.
6. **Redistribution Impact Metrics:** Real-time calculation of landfill packaging waste diverted (kg) and community funds saved (₹).

---

## 5. End-to-End Workflow

```
Supplier Registers
       │
       ▼
Admin Verifies Organization
       │
       ▼
Supplier Lists Eligible Medical Consumables (INSERT into medical_supplies)
       │
       ▼
Admin Verifies / Approves Listing (Status -> Available)
       │
       ▼
NGO Searches Supplies & Submits Requisition (INSERT into requests)
       │
       ▼
Supplier Reviews & Approves Request (UPDATE requests -> Approved)
       │
       ▼
Delivery Consignment Auto-Assigned (INSERT into deliveries)
       │
       ▼
Delivery Partner Accepts & Updates Milestones (Assigned -> Picked Up -> In Transit -> Delivered)
       │
       ▼
NGO / Clinic Confirms Physical Receipt (UPDATE requests -> Completed)
       │
       ▼
Impact Statistics Automatically Recorded (INSERT/UPDATE impact_metrics)
```

---

## 6. College OEP Team Members (Group of 5)

| Member | Role in Project | Module Responsibilities |
|---|---|---|
| **Student 1 (Lead)** | System Architect & Backend Lead | Database Schema design, Core Auth (`auth.php`, `session.php`), PDO DB Engine, Deployment |
| **Student 2** | Admin Portal & Compliance | Admin Dashboard, Organization Verification, User Moderation, Audit & Analytics |
| **Student 3** | Healthcare Supplier Portal | Supply Inventory CRUD, Batch Management, Request Processing, Transfers Tracking |
| **Student 4** | NGO / Clinic Portal | Catalog Search & Filtering, Clinical Requirements CRUD, Request Workflow, Receipt Confirmation |
| **Student 5** | Logistics & Smart Algorithm | Delivery Partner Portal, Consignment Tracking, Smart Matchmaking Engine (`smart_matching.php`) |

---

## 7. Demo Accounts (College Evaluation)

| Role | Demo Email | Password | Organization / Notes |
|---|---|---|---|
| **Administrator** | `admin@medicycle.org` | `Admin@123` | System Administrator Portal |
| **Supplier** | `apollo.supplies@medicycle.org` | `Supplier@123` | Apollo Health Supplies (Ahmedabad) |
| **NGO / Clinic** | `hope.clinic@medicycle.org` | `Ngo@123` | Hope Community Health Clinic (Ahmedabad) |
| **Delivery Partner** | `delivery@medicycle.org` | `Delivery@123` | MediCycle Fast Logistics Partner |

*Quick-fill demo buttons are provided directly on the Login page for instant one-click evaluator login across all 4 roles.*

---

## 8. Running the Project Locally

```bash
# 1. Start Apache & MySQL in XAMPP
# 2. Database medicycle_db is located at database/medicycle_db.sql
# 3. Access the web installer (if setting up fresh):
http://127.0.0.1:8000/setup.php

# 4. Access the application in browser:
http://127.0.0.1:8000/
# or via XAMPP web root:
http://localhost/medicycle/

# 5. Run Full 30-Point Integration Test Suite:
php tests/test_full_system.php
```

