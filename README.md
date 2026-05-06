# Strategic CRM for Professional Services

A database-driven CRM web application built for small consulting firms — centralizing client data, tracking leads, and managing consultant workloads through a clean PHP + MySQL backend.

---

## The Problem

Small consulting firms lack the CRM infrastructure that larger organizations take for granted:
- Client data scattered across emails, spreadsheets, and individual memories
- No real-time visibility into consultant workload, causing overbooking and missed opportunities

---

## Features

### Lead Health Scoring
Priority Score = ( Deal Value ÷ 10,000 ) × Recency Weight

| Recency | Weight | Status |
|---|---|---|
| 0–7 days | 1.00 | 🔴 Hot |
| 8–14 days | 0.75 | 🟠 Warm |
| 15–30 days | 0.50 | 🔵 Cool |
| 31+ days | 0.25 | ⚪ Stale |

Scores auto-recalculate on every new interaction — no manual updates needed.

### Dynamic Capacity Guardrail
Each consultant can handle a maximum of 3 active projects. The system queries current workload via SQL COUNT and blocks new assignments when the limit is reached, displaying a clear status label (Available / Warning / Full).

### Security
- MySQLi prepared statements — SQL injection proof
- Bcrypt password hashing — no plain-text passwords stored
- Session-based authentication guarding every page
- XSS prevention via `htmlspecialchars()` on all output

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3 |
| Backend | PHP |
| Database | MySQL |
| Local Server | XAMPP (Apache + MySQL) |

---

## Database Schema

```
consultants   → id, full_name, email, specialization, max_capacity
clients       → id, company_name, contact_person, industry
leads         → id, client_id★, consultant_id★, deal_value, status, priority_score
interactions  → id, lead_id★, consultant_id★, client_id★, notes, date
```
★ Foreign Key | 4 foreign keys total | ON DELETE CASCADE / SET NULL enforced

---

## Project Structure

```
Strategic_CRM/
│
├── index.php            # Dashboard — KPIs, pipeline value, summary
├── clients.php          # Client CRUD
├── consultants.php      # Consultant CRUD + workload capacity bar
├── leads.php            # Lead CRUD + priority score + health label
├── interactions.php     # Interaction logging → triggers score recalculation
├── helpers.php          # Shared utility functions
│
├── includes/
│   ├── scoring.php      # Lead Health Score algorithm
│   └── capacity.php     # Capacity Guardrail logic
│
├── config/              # Database connection
├── css/                 # Styling
└── sql/                 # Database schema file
```

---

## How to Run

**Prerequisites:** XAMPP installed on your machine.

1. Clone the repo:
```bash
git clone https://github.com/emeringrace/Strategic_CRM.git
```

2. Move the folder to your XAMPP htdocs directory:
```
C:/xampp/htdocs/Strategic_CRM/
```

3. Start **Apache** and **MySQL** in XAMPP Control Panel.

4. Set up the database:
   - Go to `http://localhost/phpmyadmin`
   - Create a new database (e.g., `strategic_crm`)
   - Import the SQL file from the `sql/` folder

5. Open the app:
```
http://localhost/Strategic_CRM/
```

---

## Team

| Name | Role |
|---|---|
| Emerin Grace Roy | PHP Backend Developer — business logic, scoring algorithm, capacity guardrail, security |
| Juee Mahajan | Database Design Lead — ERD, 3NF normalisation, schema architecture |
| Manas Jagtap | Frontend UI/UX — dashboard design, form validation, responsive layout |

---

*IT252M — Database Management Systems | NITK Surathkal | April 2026*
