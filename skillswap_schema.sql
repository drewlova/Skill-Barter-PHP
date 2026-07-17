-- ============================================================
-- SkillSwap Database Schema
-- For use with XAMPP (MySQL / MariaDB via phpMyAdmin)
-- ============================================================

CREATE DATABASE IF NOT EXISTS skillswap
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE skillswap;

-- ------------------------------------------------------------
-- USERS
-- Replaces $_SESSION['registered_users']
-- ------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('Member','Moderator') NOT NULL DEFAULT 'Member',
    bio             TEXT,
    skills_offer    VARCHAR(255)    DEFAULT 'None',
    skills_need     VARCHAR(255)    DEFAULT 'None',
    trust_score     DECIMAL(3,2)    NOT NULL DEFAULT 5.00,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LISTINGS
-- Replaces $_SESSION['db_listings']
-- ------------------------------------------------------------
CREATE TABLE listings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    title           VARCHAR(200)    NOT NULL,
    category        VARCHAR(50)     NOT NULL DEFAULT 'tech',
    skills_offer    VARCHAR(255)    NOT NULL,
    skills_need     VARCHAR(255)    NOT NULL,
    description     TEXT,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- EXCHANGE SESSIONS
-- Replaces $_SESSION['db_sessions']
-- Two users paired up, with a milestone stepper (0-3) and status
-- ------------------------------------------------------------
CREATE TABLE exchange_sessions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id      INT UNSIGNED    NULL,
    requester_id    INT UNSIGNED    NOT NULL,
    provider_id     INT UNSIGNED    NOT NULL,
    title           VARCHAR(200)    NOT NULL,
    status          ENUM('Pending','Active','Declined','Completed') NOT NULL DEFAULT 'Pending',
    milestone       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_listing
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_sessions_requester
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sessions_provider
        FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- Replaces $_SESSION['db_notifications']
-- user_id NULL = system-wide notification
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,
    text            VARCHAR(255)    NOT NULL,
    type            ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REPORTS
-- Replaces $_SESSION['db_reports']
-- ------------------------------------------------------------
CREATE TABLE reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id     INT UNSIGNED    NOT NULL,
    reported_id     INT UNSIGNED    NOT NULL,
    reason          TEXT            NOT NULL,
    status          VARCHAR(100)    NOT NULL DEFAULT 'Open',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_reporter
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reported
        FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REVIEWS
-- New table backing the "Leave a Review" modal in the UI.
-- One review per session per reviewer.
-- ------------------------------------------------------------
CREATE TABLE reviews (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED    NOT NULL,
    reviewer_id     INT UNSIGNED    NOT NULL,
    reviewee_id     INT UNSIGNED    NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         TEXT,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_session
        FOREIGN KEY (session_id) REFERENCES exchange_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewee
        FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT uq_review_per_session UNIQUE (session_id, reviewer_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MILESTONE UPDATES
-- Progress notes (and an optional attached file) that either side
-- of an active exchange session can post — screenshots, drafts,
-- updated files, or just a text check-in. One row per post.
-- ------------------------------------------------------------
CREATE TABLE milestone_updates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED    NOT NULL,
    user_id         INT UNSIGNED    NOT NULL,
    milestone_step  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    note            TEXT,
    file_path       VARCHAR(255)    NULL,
    file_name       VARCHAR(255)    NULL,
    file_size       INT UNSIGNED    NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mu_session
        FOREIGN KEY (session_id) REFERENCES exchange_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_mu_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA — mirrors the placeholder data currently hardcoded
-- in backend.php, so the app looks the same on first run.
-- Default password for seeded accounts: "password123"
-- ============================================================

INSERT INTO users (name, email, password_hash, role, bio, skills_offer, skills_need, trust_score) VALUES
('Aiah Santos',  'aiah@example.com',   '$2y$10$examplehashexamplehashexamplehashexampleha', 'Member',    'Physics enthusiast and tutor.',        'Physics Tutoring',  'Web Design',      4.90),
('Marcus Lim',   'marcus@example.com', '$2y$10$examplehashexamplehashexamplehashexampleha', 'Member',    'Debugs Python for fun.',               'Python Debugging',  'Graphic Design',  4.60),
('Trisha Gomez', 'trisha@example.com', '$2y$10$examplehashexamplehashexamplehashexampleha', 'Moderator', 'Keeps the community honest.',          'Community Mgmt',    'None',            5.00),
('Ron Pedrialva','ron@example.com',    '$2y$10$examplehashexamplehashexamplehashexampleha', 'Member',    'CS student who loves building clean UIs.', 'UI Design',      'Python Tutoring', 5.00);

INSERT INTO listings (user_id, title, category, skills_offer, skills_need, description) VALUES
(1, 'Physics Tutor — Coulomb''s Law & RC Circuits', 'science', 'Physics Tutoring', 'Web Design',
   'Providing comprehensive milestone review sessions for physics final exam preparation.'),
(2, 'Data Preprocessing using Pandas Framework', 'tech', 'Python Debugging', 'Graphic Design',
   'Fixing runtime constraints, syntax bugs, and indentation errors in automated scripts.');

INSERT INTO exchange_sessions (listing_id, requester_id, provider_id, title, status, milestone) VALUES
(1, 4, 1, 'Physics Tutoring ↔ Web Design', 'Active', 2);

INSERT INTO notifications (user_id, text, type) VALUES
(NULL, 'System online. Authentication required.', 'info');

INSERT INTO reports (reporter_id, reported_id, reason, status) VALUES
(3, 2, 'Non-delivery of promised milestone hours.', 'Open');
