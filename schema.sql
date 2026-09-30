CREATE DATABASE IF NOT EXISTS strahlemaennkes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE strahlemaennkes;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(190) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('member','spiess','admin') NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE fines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status ENUM('open','paid','cancelled') NOT NULL DEFAULT 'open',
    occurred_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fines_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_fines_created_by FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE drinks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE drink_rounds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opened_by BIGINT UNSIGNED NOT NULL,
    status ENUM('open','ordered','closed') NOT NULL DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ordered_at DATETIME NULL,
    closed_at DATETIME NULL,
    CONSTRAINT fk_round_opened_by FOREIGN KEY (opened_by) REFERENCES users(id)
);

CREATE TABLE drink_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    drink_id INT UNSIGNED NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
    note VARCHAR(180) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_round_user (round_id, user_id),
    CONSTRAINT fk_request_round FOREIGN KEY (round_id) REFERENCES drink_rounds(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_request_drink FOREIGN KEY (drink_id) REFERENCES drinks(id)
);

CREATE TABLE chronicle_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year SMALLINT UNSIGNED NOT NULL UNIQUE,
    zugkoenig VARCHAR(180) NULL,
    zugfuehrer VARCHAR(180) NULL,
    kassierer VARCHAR(180) NULL,
    schriftfuehrer VARCHAR(180) NULL,
    erster_offizier VARCHAR(180) NULL,
    zweiter_offizier VARCHAR(180) NULL,
    notes TEXT NULL,
    published TINYINT(1) NOT NULL DEFAULT 1
);

-- Erweiterungsreserve: Termine und Stammtischplanung folgen als eigene Tabellen/Module.
