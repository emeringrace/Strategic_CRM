-- ============================================================
--  CRM Database Setup — IT252M Project
--  Run this in phpMyAdmin (Import tab) before using the app
-- ============================================================

CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_db;

-- ── 1. Consultants ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS consultants (
    consultant_id   INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    phone           VARCHAR(20),
    active_projects INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 2. Clients ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
    client_id       INT AUTO_INCREMENT PRIMARY KEY,
    company_name    VARCHAR(150) NOT NULL,
    contact_person  VARCHAR(100),
    email           VARCHAR(100),
    phone           VARCHAR(20),
    industry        VARCHAR(100),
    consultant_id   INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_consultant
        FOREIGN KEY (consultant_id) REFERENCES consultants(consultant_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ── 3. Leads ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    lead_id                 INT AUTO_INCREMENT PRIMARY KEY,
    client_id               INT NOT NULL,
    consultant_id           INT DEFAULT NULL,
    status                  ENUM('New','Contacted','Qualified','Closed') NOT NULL DEFAULT 'New',
    deal_value              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    priority_score          DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    num_interactions        INT NOT NULL DEFAULT 0,
    last_interaction_date   DATE DEFAULT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lead_client
        FOREIGN KEY (client_id) REFERENCES clients(client_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lead_consultant
        FOREIGN KEY (consultant_id) REFERENCES consultants(consultant_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ── 4. Interactions ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS interactions (
    interaction_id  INT AUTO_INCREMENT PRIMARY KEY,
    lead_id         INT NOT NULL,
    client_id       INT NOT NULL,
    consultant_id   INT DEFAULT NULL,
    remarks         TEXT,
    interaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inter_lead
        FOREIGN KEY (lead_id) REFERENCES leads(lead_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inter_client
        FOREIGN KEY (client_id) REFERENCES clients(client_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inter_consultant
        FOREIGN KEY (consultant_id) REFERENCES consultants(consultant_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ── Sample Data ────────────────────────────────────────────
INSERT INTO consultants (name, email, phone) VALUES
('Anita Sharma',  'anita@crm.com',  '+91 98765 43210'),
('Rohan Mehta',   'rohan@crm.com',  '+91 87654 32109'),
('Priya Nair',    'priya@crm.com',  '+91 76543 21098');

INSERT INTO clients (company_name, contact_person, email, phone, industry, consultant_id) VALUES
('TechCorp Solutions',  'Vikram Gupta',   'vikram@techcorp.com',   '+91 90000 11111', 'Technology',   1),
('BuildRight Infra',    'Sanya Kapoor',   'sanya@buildright.com',  '+91 90000 22222', 'Construction', 2),
('MediHealth Pvt Ltd',  'Dr. Arjun Rao',  'arjun@medihealth.com',  '+91 90000 33333', 'Healthcare',   1),
('FinEdge Capital',     'Meena Joshi',    'meena@finedge.com',     '+91 90000 44444', 'Finance',      3);

INSERT INTO leads (client_id, consultant_id, status, deal_value, num_interactions, last_interaction_date, priority_score) VALUES
(1, 1, 'Qualified', 500000.00, 3, CURDATE(),                            85.00),
(2, 2, 'Contacted', 250000.00, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 42.50),
(3, 1, 'New',       750000.00, 0, NULL,                                  37.50),
(4, 3, 'Qualified', 1200000.00, 5, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 95.00);

INSERT INTO interactions (lead_id, client_id, consultant_id, remarks, interaction_date) VALUES
(1, 1, 1, 'Initial discovery call. Client is interested in a 6-month engagement.', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 1, 1, 'Sent proposal document. Awaiting sign-off from their finance team.',     DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1, 1, 1, 'Follow-up call — they want to negotiate pricing. Meeting set for Friday.', NOW()),
(2, 2, 2, 'First contact made via email. Scheduled a demo call.',                   DATE_SUB(NOW(), INTERVAL 10 DAY)),
(4, 4, 3, 'Strong interest shown. Presented ROI analysis.',                          DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Keep active_projects count accurate after inserts
UPDATE consultants c
SET active_projects = (
    SELECT COUNT(*) FROM leads l
    WHERE l.consultant_id = c.consultant_id AND l.status != 'Closed'
);
