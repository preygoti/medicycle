# ACADEMIC OPEN ENDED PROJECT (OEP) REPORT CONTENT

## COURSE: WEB TECHNOLOGY
### PROJECT TITLE: MEDICYCLE — SMART MEDICAL SUPPLY REDISTRIBUTION MANAGEMENT SYSTEM

---

# 1. TITLE PAGE

**PROJECT TITLE:**  
MediCycle: Smart Medical Supply Redistribution Management System

**ACADEMIC DOMAIN:**  
Web Technology / Healthcare Informatics & Sustainable Logistics

**PROJECT TYPE:**  
Open Ended Project (OEP)

**SUBMITTED BY:**  
* Student 1 (Enrollment No: _____________) — Authentication, Sessions & Security
* Student 2 (Enrollment No: _____________) — Frontend UI/UX, Bootstrap & Visualizations
* Student 3 (Enrollment No: _____________) — MySQL Database Schema & Full CRUD Engine
* Student 4 (Enrollment No: _____________) — Direct Handover Pipeline & Collection Workflow
* Student 5 (Enrollment No: _____________) — Smart Matching Algorithm, Priority Engine & Impact Analytics

**FACULTY GUIDE / MENTOR:**  
Prof. ____________________________  
Department of Computer Engineering / Information Technology  
Academic Year: 2026–2027

---

# 2. CERTIFICATE

This is to certify that the project entitled **"MediCycle — Smart Medical Supply Redistribution Management System"** submitted by the group of students listed above is a bonafide work carried out under our supervision and guidance in partial fulfillment of the requirements for the course **Web Technology (WT)**.

Internal Guide: ________________________  
Head of Department: ____________________  
Date: ________________________  
College Seal: [ Place Seal Here ]

---

# 3. DECLARATION

We hereby declare that this project report entitled **"MediCycle — Smart Medical Supply Redistribution Management System"** is our original work created as part of the curriculum requirements. All technical implementations including PHP scripts, MySQL databases, frontend designs, and test suites have been developed and tested by our group.

Signed:  
1. ______________________  
2. ______________________  
3. ______________________  
4. ______________________  
5. ______________________  

---

# 4. ACKNOWLEDGEMENT

We express our gratitude to our institution, Head of Department, and Course Instructor for providing the laboratory facilities and academic guidance to execute this Open Ended Project. We are thankful to our peers and healthcare logistics advisors for their valuable feedback regarding medical supply verification protocols and non-drug consumables safety scope.

---

# 5. ABSTRACT

Medical waste disposal and chronic clinical resource shortages represent dual challenges in contemporary healthcare systems. Hospitals routinely discard substantial volumes of unexpired, completely sterile, unopened healthcare consumables due to batch overstock, storage reconfigurations, or minor packaging surplus. Concurrently, community outpatient clinics, charitable dispensaries, and disaster relief non-governmental organizations (NGOs) struggle with shortages of essential non-drug supplies such as examination gloves, surgical masks, sterile gauze swabs, bandages, and protective gowns.

**MediCycle** is a web-based, full-stack application developed using PHP 8.2, MySQL, HTML5, CSS3, JavaScript, and Bootstrap 5.3. It establishes a closed-loop platform connecting verified donor healthcare institutions with licensed recipient charitable clinics and volunteer logistics couriers. The system incorporates strict medical safety guardrails (excluding prescription pharmaceuticals to eliminate drug diversion risks), an automated priority scoring algorithm, and a multi-factor recommendation engine that pairs clinic demand with surplus lots. The platform implements complete Create-Read-Update-Delete (CRUD) capabilities, session-based Role-Based Access Control (RBAC), and quantified ecological accounting (solid packaging waste mass averted and financial value preserved).

---

# 6. INTRODUCTION

The modern medical supply chain operates on a linear consumption model: manufacturing, procurement, single-site inventory storage, and waste disposal. When surplus consumables approach 3 to 12 months before expiration, institutions frequently write off and incinerate these items rather than reallocate them.

MediCycle introduces a circular logistics framework designed specifically for eligible, sterile, unexpired non-drug medical consumables. By maintaining verified organizational identity, transparent batch metadata (lot identifiers, packaging integrity, and storage guidelines), and synchronized delivery tracking, MediCycle transforms landfill waste into clinical care.

---

# 7. PROBLEM STATEMENT

1. **Premature Consumable Incineration:** Millions of metric tons of sterile non-hazardous plastics and paper dressings are incinerated annually due to localized overstocking.
2. **Resource Inequity in Primary Healthcare:** Free community dispensaries and rural outreach camps frequently experience acute supply shortages for basic routine dressings.
3. **Lack of Verified Traceability:** Informal donations lack chain-of-custody verification, resulting in donor liability concerns and recipient safety uncertainty.
4. **Administrative Fragmentation:** Absence of centralized platforms with automated compatibility matching between donor surplus and clinic deficits.

---

# 8. EXISTING SYSTEM

Existing medical donation practices rely predominantly on ad-hoc phone calls, unverified social media requests, or periodic physical donation drives.

### Drawbacks of Existing System:
* Manual coordination and high communication overhead.
* Lack of packaging seal verification and expiration oversight.
* Absence of real-time shipment milestone tracking.
* No algorithmic compatibility scoring between donor items and clinic requests.
* Zero accountability regarding ecological impact metrics.

---

# 9. PROPOSED SYSTEM (MEDICYCLE DIRECT REDISTRIBUTION ARCHITECTURE)

MediCycle implements a decentralized direct redistribution architecture designed specifically for medical supply conservation.

### Key Innovations:
* **Direct Supplier-to-NGO Network:** Medical stores and hospitals list surplus consumables directly for charitable clinics and NGOs without administrative bottlenecks.
* **Non-Drug Safety Scope Guard:** Strict protocol restricting platform inventory to eligible, unopened, unexpired PPE and sterile consumables.
* **Smart Matching Algorithm:** Multi-factor scoring engine evaluating category alignment, keyword similarity, geographic proximity, quantity capacity, and urgency.
* **Direct Handover Pass:** Unique verification codes (`HAND-XXXXXX`) issued upon supplier acceptance, enabling verified physical collections.
* **Impact Footprint Ledger:** Automatic calculation of packaging waste averted (kg) and community funding conserved.

---

# 10. OBJECTIVES

1. Implement a complete, production-grade web application using **PHP, MySQL, HTML, CSS/Bootstrap, and JavaScript**.
2. Satisfy all course OEP parameters: robust CRUD, multi-criteria search, role-based session authorization, client-server validation, and responsive UI.
3. Enforce strict medical consumables eligibility safeguards.
4. Provide an intelligent decision-support module (Priority Scoring and Recommendation Matchmaking).
5. Deliver an intuitive direct redistribution portal for Healthcare Suppliers and Recipient NGOs.

---

# 11. SCOPE

### In-Scope (Eligible Healthcare Consumables):
* Examination and surgical gloves (unopened boxes)
* Surgical masks, N95 respirators, face shields, and protective eye gear
* Sterile gauze swabs, crepe bandages, surgical dressings, and adhesive rolls
* Unopened sterile suture removal kits and non-drug procedure packs
* Diagnostic non-drug consumables (test strips, lancets, probe covers)
* Disposable surgical gowns, aprons, and head coverings
* Capped, unopened administration disposables (needle-free IV cannulas, infusion sets)

### Strictly Out-of-Scope (Prohibited):
* All prescription medications, tablets, syrups, and injectables
* Narcotics, controlled substances, and scheduled drugs
* Vaccines, blood products, and temperature-sensitive biologics
* Compromised, opened, expired, or unsealed consumable lots

---

# 12. FUNCTIONAL REQUIREMENTS

* **FR-1 Authentication:** Secure user registration, password hashing (`password_hash` with bcrypt), session validation, and CSRF token verification.
* **FR-2 Organization Registration:** Direct organization onboarding for healthcare facilities, pharmacies, and charitable clinics.
* **FR-3 Supply Inventory Management:** Full CRUD operations for suppliers to register, edit, search, and delete consumable lots.
* **FR-4 Clinical Requisition Broadcasting:** NGO module to broadcast item deficits with urgency levels and required-by deadlines.
* **FR-5 Requisition & Handover Workflow:** Mechanism for NGOs to request available lots and for suppliers to review, approve, and reserve stock.
* **FR-6 Handover Code Generation:** Auto-generation of secure Handover Verification Codes upon supplier acceptance.
* **FR-7 Physical Collection Confirmation:** Recipient or supplier code verification completing the transaction and updating live impact counters.
* **FR-8 Analytics & Impact Reporting:** Visual Chart.js charts and metrics for environmental packaging diversion and clinic savings.

---

# 13. NON-FUNCTIONAL REQUIREMENTS

* **Security:** Prepared statements via PDO protecting against SQL Injection; strict HTML entity escaping (`htmlspecialchars`) preventing Cross-Site Scripting (XSS); CSRF token validation on all POST requests.
* **Usability & Responsiveness:** Clean Bootstrap 5.3 interface rendering properly on mobile, tablet, and desktop devices.
* **Reliability:** Relational foreign key integrity and ACID-compliant transaction blocks (`beginTransaction`, `commit`, `rollBack`).
* **Performance:** Indexed database lookup columns (`role`, `status`, `expiry_date`, `priority_score`).

---

# 14. TECHNOLOGY USED

* **Programming Language:** PHP 8.2 (Vanilla, object-oriented PDO)
* **Database Management System:** MySQL / MariaDB 10.4
* **Markup & Styling:** HTML5, CSS3, Bootstrap 5.3
* **Scripting Language:** JavaScript (ES6)
* **Data Visualization:** Chart.js 4.4
* **Local Server Environment:** XAMPP (Apache 2.4, MariaDB 10.4, PHP 8.2)

---

# 15. SYSTEM ARCHITECTURE

```
+-------------------------------------------------------------+
|                      Client Web Browser                     |
|           (HTML5 + CSS3 + Bootstrap 5 + Vanilla JS)         |
+------------------------------+------------------------------+
                               |
                        HTTP / HTTPS Request
                               |
+------------------------------v------------------------------+
|                     Web Server (Apache)                     |
|       Routing, .htaccess rules, Static Asset Delivery       |
+------------------------------+------------------------------+
                               |
                        PHP 8.2 Runtime
                               |
+------------------------------v------------------------------+
|                    Application Logic Tier                   |
|  - Session & Auth Guards (includes/auth.php, session.php)    |
|  - Smart Matching Engine & Priority Scoring (functions.php) |
|  - Portal Controllers (Admin, Supplier, NGO, Delivery)      |
+------------------------------+------------------------------+
                               |
                       PDO Prepared SQL
                               |
+------------------------------v------------------------------+
|                   MySQL Database (Storage)                  |
|    users | organizations | categories | medical_supplies    |
|   requirements | requests | deliveries | impact_metrics     |
+-------------------------------------------------------------+
```

---

# 16. USE CASE DIAGRAM DESCRIPTION

* **Actor 1: System Admin**
  * Use Cases: Login, Manage Users, Verify Organizations, Moderate Supplies, Manage Categories, View System Audit Reports, Configure System Constants.
* **Actor 2: Healthcare Supplier (Hospital / Clinic)**
  * Use Cases: Register Facility, Add Medical Supply, Edit/Delete Supply, Search Inventory, Review Incoming Requests, Approve Requisition, Track Dispatched Transfers, View Environmental Impact.
* **Actor 3: Recipient NGO / Rural Clinic**
  * Use Cases: Register NGO, Search Available Supplies, View Smart Match Recommendations, Post Clinical Requirement, Submit Requisition, Track Delivery, Confirm Receipt.
* **Actor 4: Logistics Partner / Volunteer Courier**
  * Use Cases: View Assigned Deliveries, Accept Shipment, Update Milestone Status (Picked Up / In Transit / Delivered), Record Delivery Handover.

---

# 17. DATA FLOW DIAGRAMS (DFD)

### Level 0 DFD (Context Diagram):
* **External Entities:** Supplier, NGO, Delivery Partner, Admin.
* **Central Process:** MediCycle Redistribution Platform (0.0).
* **Data Flows:**
  * Supplier inputs inventory details; receives requisition alerts.
  * NGO submits clinical requirements and requests; receives approved supplies.
  * Delivery partner receives routing instructions; submits milestone timestamps.
  * Admin verifies facilities; receives platform telemetry.

### Level 1 DFD:
* **Process 1.0:** Authentication & Organization Verification.
* **Process 2.0:** Supply Inventory Management & Priority Scoring.
* **Process 3.0:** Matchmaking & Clinical Requisition Processing.
* **Process 4.0:** Logistics Dispatch & Handover Tracking.
* **Process 5.0:** Impact Accounting & Report Aggregation.

### Level 2 DFD (Requisition & Delivery Pipeline):
* Sub-process 3.1: NGO queries inventory; algorithm computes compatibility score.
* Sub-process 3.2: Requisition created in database; stock flagged as pending.
* Sub-process 3.3: Supplier approves; inventory deducted; shipment assigned.
* Sub-process 4.1: Courier records pickup; status transitions to In Transit.
* Sub-process 4.2: NGO inspects seal; confirms handover; impact metric calculated.

---

# 18. ENTITY-RELATIONSHIP (ER) DIAGRAM DESCRIPTION

* **users (1) ──── (1) organizations** (`user_id` foreign key with CASCADE delete)
* **users (1) ──── (N) medical_supplies** (`supplier_id` foreign key)
* **categories (1) ──── (N) medical_supplies** (`category_id` foreign key with RESTRICT delete)
* **users (1) ──── (N) requirements** (`organization_id` foreign key)
* **categories (1) ──── (N) requirements** (`category_id` foreign key)
* **medical_supplies (1) ──── (N) requests** (`supply_id` foreign key)
* **users (1) ──── (N) requests** (`requester_id` foreign key)
* **requests (1) ──── (1) deliveries** (`request_id` unique foreign key)
* **users (1) ──── (N) deliveries** (`delivery_partner_id` foreign key with SET NULL delete)
* **requests (1) ──── (1) impact_metrics** (`request_id` foreign key)
* **users (1) ──── (N) notifications** (`user_id` foreign key)

---

# 19. DATABASE SCHEMA TABLES

1. `users`: Stores login credentials, roles (`admin`, `supplier`, `ngo`, `delivery`), status, and contact numbers.
2. `organizations`: Stores facility name, entity type, official medical/NGO registration license, address, city, state, pincode, and verification status (`pending`, `verified`, `rejected`).
3. `categories`: Taxonomy of eligible consumables (PPE, Wound Care, Diagnostic Consumables, Non-Drug Administration, First Aid Kits, Orthopedic Supports).
4. `medical_supplies`: Inventory records detailing supply name, quantity, unit, condition status, packaging seal status, expiry date, batch number, pickup city, priority score, priority level, and listing status (`Available`, `Reserved`, `Transferred`, `Pending`, `Rejected`).
5. `requirements`: Clinical demand records detailing item needed, quantity, clinical urgency (`Low`, `Medium`, `High`, `Critical`), required-by date, destination city, and clinical purpose.
6. `requests`: Formal requisition submissions linking a supply item to a requesting clinic with purpose description, requested quantity, and approval status (`Pending`, `Approved`, `Rejected`, `Completed`).
7. `deliveries`: Courier waybills with unique tracking numbers, pickup/destination addresses, contacts, timestamps (`assigned_at`, `accepted_at`, `picked_up_at`, `delivered_at`), and status (`Assigned`, `Accepted`, `Picked Up`, `In Transit`, `Delivered`).
8. `notifications`: Real-time system messages detailing notifications and read status.
9. `impact_metrics`: Ecological and social accounting storing redistributed quantities, estimated solid waste diverted (kg), and monetary savings (INR).
10. `system_settings`: Platform metadata and mathematical multiplier constants.

---

# 20. MODULE DESCRIPTIONS

* **Module 1: Authentication & Authorization:** Session initiation, inactivity timeouts (2 hours), CSRF protection, and role permission validation preventing horizontal and vertical privilege escalation.
* **Module 2: Healthcare Supplier Portal:** Inventory dashboard, consumable registration form with live validation, lot modification, incoming requisition review modal, and transfer history.
* **Module 3: Recipient Clinic / NGO Portal:** Searchable supply catalog with dynamic multi-criteria SQL filtering, clinical need broadcast form, and receipt confirmation handler.
* **Module 4: Medical Logistics Portal:** Waybill inspection, milestone transitions (Pickup/Transit/Delivery), and driver notes logging.
* **Module 5: Smart Algorithm & Decision Support Module:**
  * **Priority Score:** Evaluates shelf-life decay buffer, sterile seal integrity, and volume demand to assign a 0–100 urgency score.
  * **Matchmaking Algorithm:** Correlates 5 weighted factors (Category, Item Keywords, City Proximity, Quantity Fulfillment, Clinical Urgency) generating human-readable explanations.
* **Module 6: Administrator Governance & Analytics:** Organization credential verification, supply moderation, user suspension, category taxonomy editing, and printable environmental audit reports.

---

# 21. SCREENSHOTS PLACEHOLDERS FOR FINAL OEP REPORT

* *Figure 1:* MediCycle Public Homepage & Live Network Impact Counters (`index.php`)
* *Figure 2:* User Sign In Page with Demo Quick-Fill Buttons (`login.php`)
* *Figure 3:* Healthcare Organization Multi-Step Registration (`register.php`)
* *Figure 4:* Supplier Dashboard & Inventory Metrics (`supplier/dashboard.php`)
* *Figure 5:* Supplier Add Medical Consumable Form with Validation (`supplier/add-supply.php`)
* *Figure 6:* Supplier Inventory Table with Search, Filter & Delete Modal (`supplier/inventory.php`)
* *Figure 7:* Supplier Incoming Requisition Review & Approval Modal (`supplier/requests.php`)
* *Figure 8:* NGO Available Supplies Search & AI Match Badges (`ngo/search-supplies.php`)
* *Figure 9:* NGO Supply Requisition Submission Screen (`ngo/supply-details.php`)
* *Figure 10:* NGO Clinical Requirement Broadcasting Form (`ngo/post-requirement.php`)
* *Figure 11:* NGO Requisition Tracking with Confirm Receipt Button (`ngo/my-requests.php`)
* *Figure 12:* Logistics Partner Shipment Milestones Update (`delivery/update-status.php`)
* *Figure 13:* Administrator Master Dashboard with Chart.js Charts (`admin/dashboard.php`)
* *Figure 14:* Administrator Organization Verification Queue (`admin/organizations.php`)
* *Figure 15:* Administrator Supply Demand vs Deficit Analytics (`admin/analytics.php`)
* *Figure 16:* Printable Formal Redistribution Audit Report (`admin/reports.php`)

---

# 22. TESTING METHODOLOGY & RESULTS

A comprehensive testing suite was executed using both unit test cases and an automated end-to-end integration test harness (`tests/test_workflow.php`).

### Test Suite Execution Summary:
* **Syntax Integrity:** 100% of project PHP files evaluated with `php -l`. Zero syntax or parse errors.
* **Authentication Testing:** Verified valid logins with password hashing verification; verified that incorrect passwords and malformed emails were rejected.
* **Access Control Guard Testing:** Verified that unauthenticated users attempting to access `/admin/*`, `/supplier/*`, `/ngo/*`, or `/delivery/*` are redirected to `login.php`. Verified that non-admin sessions attempting admin routes are blocked.
* **CRUD Verification:** Verified INSERT of new supply and requirement records; verified UPDATE of lot metadata and status milestones; verified DELETE with foreign key cascade handling.
* **Search & Filter Testing:** Verified multi-field SQL prepared queries combining keyword strings, category IDs, and location values.
* **Automated Integration Test Results:** **22 Assertions Evaluated, 22 Passed (0 Failures).**

---

# 23. RESULTS & SYSTEM OUTPUT

* Successfully created a functional platform connecting 4 distinct healthcare stakeholders.
* Tested redistribution cycle: 15 boxes of nitrile gloves requested, approved by Apollo Central Hospital, assigned tracking `MC-DEL-2026-XXXX`, transitioned to delivery, and receipt-confirmed by Hope Rural Health Mission.
* Database updated automatically: stock decreased, delivery status marked Delivered, impact metric logged (1.8 kg packaging waste averted, ₹525 saved).

---

# 24. ADVANTAGES

1. **Environmental Sustainability:** Directly reduces medical landfill waste and incineration emissions.
2. **Clinical Relief:** Delivers sterile wound care and protective gear to resource-constrained clinics at zero purchase cost.
3. **Traceability & Safety:** Full audit trail with packaging seal checks and license verification.
4. **Algorithmic Efficiency:** Smart matchmaking eliminates manual searching for matching donors.
5. **Academic Rigor:** Built purely on native, understandable PHP, MySQL, and modern Bootstrap without reliance on bloated frameworks.

---

# 25. LIMITATIONS

1. **Physical Logistics Capacity:** Relies on volunteer or partner courier availability for long-distance transfers.
2. **Manual Visual Inspection:** Initial packaging integrity relies on the donor hospital's reporting prior to courier collection.
3. **Non-Drug Boundary:** Cannot assist facilities seeking prescription medication redistribution due to regulatory barriers.

---

# 26. FUTURE ENHANCEMENTS

1. **Barcode / QR Code Scanning:** Integration of mobile camera scanning for instant lot barcode verification during courier pickup.
2. **GPS Real-Time Geolocation:** Live map tracking of volunteer courier vehicles between hospital and clinic.
3. **Automated OCR License Parsing:** Using document AI to extract medical registration numbers from uploaded certificate images.
4. **SMS / WhatsApp Gateway:** Automated dispatch alerts via Twilio or WhatsApp Business API.

---

# 27. CONCLUSION

The **MediCycle** project demonstrates that sustainable medical redistribution can be implemented using standard web technologies. By pairing a normalized MySQL relational architecture and modern PHP 8.2 backend with an intuitive Bootstrap 5 user experience, the system addresses an urgent real-world problem. All requirements of the Web Technology Open-Ended Project (OEP) curriculum have been met and verified through automated end-to-end integration testing.

---

# 28. REFERENCES

1. World Health Organization (WHO), "Safe management of wastes from health-care activities", 2nd Edition.
2. PHP Group, "PHP 8.2 Documentation & Secure Session Handling Guide", https://www.php.net/docs.php.
3. MySQL Reference Manual, "InnoDB Storage Engine & Foreign Key Constraints", https://dev.mysql.com/doc/.
4. Bootstrap Team, "Bootstrap 5.3 Documentation & Component Library", https://getbootstrap.com/docs/5.3/.
5. Chart.js Documentation, "Flexible JavaScript Charting for Designers & Developers", https://www.chartjs.org/docs/.

---

# 29. TEAM MEMBER CONTRIBUTIONS

| Member | Assigned Project Role | Core Module Responsibilities |
|---|---|---|
| **Member 1** | Backend & Security Architect | Database schema design, PDO connection configuration, session security management, CSRF validation, bcrypt password hashing, and authentication guards. |
| **Member 2** | Frontend & UI/UX Architect | Responsive design system, custom CSS healthcare styling, global template layouts, client-side validation scripts, and public informational pages. |
| **Member 3** | Database & Inventory Engineer | Supplier inventory management subsystem, CRUD implementation (INSERT, UPDATE, DELETE), multi-criteria database search queries, and lot metadata tracking. |
| **Member 4** | Workflow & Logistics Engineer | NGO clinical requirement broadcasting, requisition submission pipeline, logistics courier status transitions, and receipt confirmation handling. |
| **Member 5** | Governance & Intelligence Lead | Administrator master dashboards, smart matchmaking recommendation algorithm, priority scoring calculator, Chart.js analytics, and end-to-end test suite. |
