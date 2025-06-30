-- Создание базы данных для управления Google Ads плейсментами
-- MySQL версия

CREATE DATABASE IF NOT EXISTS placements_manager 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE placements_manager;

-- Таблица аккаунтов Google Ads
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'GMT+00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_accounts_name (name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Таблица скриптов
CREATE TABLE IF NOT EXISTS scripts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_scripts_name (name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Таблица кампаний и выключенных плейсментов
CREATE TABLE IF NOT EXISTS placement_exclusions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    script_id INT NOT NULL,
    campaign_name VARCHAR(500) NOT NULL,
    placement_url VARCHAR(500) NOT NULL,
    excluded_at_gmt DATETIME NOT NULL,
    batch_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (script_id) REFERENCES scripts(id) ON DELETE CASCADE,
    
    INDEX idx_placement_exclusions_account (account_id),
    INDEX idx_placement_exclusions_script (script_id),
    INDEX idx_placement_exclusions_date (excluded_at_gmt),
    INDEX idx_placement_exclusions_batch (batch_id),
    INDEX idx_placement_exclusions_url (placement_url),
    INDEX idx_placement_exclusions_campaign (campaign_name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Вставка примерных данных
INSERT IGNORE INTO accounts (id, name, timezone) VALUES 
(1, 'Пример аккаунта', 'GMT+00:00');

INSERT IGNORE INTO scripts (id, name, description) VALUES 
(1, 'Пример скрипта', 'Описание правил работы скрипта');