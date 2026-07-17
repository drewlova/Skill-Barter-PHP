-- Run this only if you already imported skillswap_schema.sql before this update.
-- It adds the milestone_updates table, which backs the new "post an update"
-- feature (text notes + an optional attached file) on active sessions.
-- (Skip this if you're importing skillswap_schema.sql fresh — it's already included.)

USE skillswap;

CREATE TABLE IF NOT EXISTS milestone_updates (
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
