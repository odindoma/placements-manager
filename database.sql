-- Создание базы данных для управления Google Ads плейсментами
-- Используется SQLite для простоты

-- Таблица аккаунтов Google Ads
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'GMT+00:00',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица скриптов
CREATE TABLE IF NOT EXISTS scripts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица кампаний и выключенных плейсментов
CREATE TABLE IF NOT EXISTS placement_exclusions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    script_id INTEGER NOT NULL,
    campaign_name VARCHAR(500) NOT NULL,
    placement_url VARCHAR(500) NOT NULL,
    excluded_at_gmt DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (script_id) REFERENCES scripts(id) ON DELETE CASCADE
);

-- Индексы для оптимизации запросов
CREATE INDEX IF NOT EXISTS idx_placement_exclusions_account ON placement_exclusions(account_id);
CREATE INDEX IF NOT EXISTS idx_placement_exclusions_script ON placement_exclusions(script_id);
CREATE INDEX IF NOT EXISTS idx_placement_exclusions_date ON placement_exclusions(excluded_at_gmt);

-- Вставка примерных временных зон
INSERT OR IGNORE INTO accounts (id, name, timezone) VALUES 
(1, 'Пример аккаунта', 'GMT+00:00');

INSERT OR IGNORE INTO scripts (id, name, description) VALUES 
(1, 'Пример скрипта', 'Описание правил работы скрипта');

