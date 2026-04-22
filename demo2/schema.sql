-- demo2/schema.sql
-- Import this file into your selected database in phpMyAdmin.

CREATE TABLE IF NOT EXISTS guestbook_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author VARCHAR(80) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_guestbook_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO guestbook_entries (author, message) VALUES
('Admin', 'Vitej v demo2! Tohle je prvni zaznam z DDL.'),
('Tester', 'V phpMyAdmin importuj schema.sql a pak zkousni vlozit dalsi zpravu z webu.');
