<?php
/**
 * Скрипт миграции данных с SQLite на MySQL
 */

require_once 'includes/Database.php';

class MigrationScript {
    private $sqliteDb;
    private $mysqlDb;
    
    public function __construct() {
        // Подключение к SQLite (старая БД)
        try {
            $this->sqliteDb = new PDO("sqlite:placements.db");
            $this->sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Ошибка подключения к SQLite: " . $e->getMessage());
        }
        
        // Подключение к MySQL (новая БД)
        $this->mysqlDb = new Database();
    }
    
    public function migrate() {
        echo "Начало миграции...\n";
        
        try {
            $this->mysqlDb->beginTransaction();
            
            // Миграция аккаунтов
            $this->migrateAccounts();
            
            // Миграция скриптов
            $this->migrateScripts();
            
            // Миграция исключений плейсментов
            $this->migratePlacementExclusions();
            
            $this->mysqlDb->commit();
            echo "Миграция завершена успешно!\n";
            
        } catch (Exception $e) {
            $this->mysqlDb->rollback();
            echo "Ошибка миграции: " . $e->getMessage() . "\n";
        }
    }
    
    private function migrateAccounts() {
        echo "Миграция аккаунтов...\n";
        
        $accounts = $this->sqliteDb->query("SELECT * FROM accounts")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($accounts as $account) {
            $this->mysqlDb->query(
                "INSERT INTO accounts (id, name, timezone, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), timezone = VALUES(timezone)",
                [$account['id'], $account['name'], $account['timezone'], $account['created_at']]
            );
        }
        
        echo "Перенесено аккаунтов: " . count($accounts) . "\n";
    }
    
    private function migrateScripts() {
        echo "Миграция скриптов...\n";
        
        $scripts = $this->sqliteDb->query("SELECT * FROM scripts")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($scripts as $script) {
            $this->mysqlDb->query(
                "INSERT INTO scripts (id, name, description, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)",
                [$script['id'], $script['name'], $script['description'], $script['created_at']]
            );
        }
        
        echo "Перенесено скриптов: " . count($scripts) . "\n";
    }
    
    private function migratePlacementExclusions() {
        echo "Миграция исключений плейсментов...\n";
        
        $exclusions = $this->sqliteDb->query("SELECT * FROM placement_exclusions")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($exclusions as $exclusion) {
            $this->mysqlDb->query(
                "INSERT INTO placement_exclusions (id, account_id, script_id, campaign_name, placement_url, excluded_at_gmt, batch_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP",
                [
                    $exclusion['id'],
                    $exclusion['account_id'],
                    $exclusion['script_id'],
                    $exclusion['campaign_name'],
                    $exclusion['placement_url'],
                    $exclusion['excluded_at_gmt'],
                    $exclusion['batch_id'],
                    $exclusion['created_at']
                ]
            );
        }
        
        echo "Перенесено исключений: " . count($exclusions) . "\n";
    }
}

// Запуск миграции
$migration = new MigrationScript();
$migration->migrate();