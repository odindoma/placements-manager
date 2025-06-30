<?php
require_once __DIR__ . '/EnvLoader.php';

class Database {
    private $pdo;
    private static $instance = null;
    
    public function __construct() {
        // Загружаем .env файл
        EnvLoader::load();
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $host = EnvLoader::get('DB_HOST', 'localhost');
            $port = EnvLoader::get('DB_PORT', '3306');
            $dbname = EnvLoader::get('DB_NAME');
            $username = EnvLoader::get('DB_USER');
            $password = EnvLoader::get('DB_PASSWORD');
            $charset = EnvLoader::get('DB_CHARSET', 'utf8mb4');
            
            if (!$dbname || !$username) {
                throw new Exception('Database credentials not found in .env file');
            }
            
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
            ];
            
            $this->pdo = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Ошибка подключения к базе данных");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Выполнение запроса
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Ошибка выполнения запроса: " . $e->getMessage());
        }
    }
    
    /**
     * Получение всех записей
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Получение одной записи
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Вставка записи и возврат ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Транзакции
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Получение всех аккаунтов
     */
    public function getAccounts() {
        return $this->fetchAll("SELECT * FROM accounts ORDER BY name");
    }
    
    /**
     * Добавление аккаунта
     */
    public function addAccount($name, $timezone) {
        return $this->insert(
            "INSERT INTO accounts (name, timezone) VALUES (?, ?)",
            [$name, $timezone]
        );
    }
    
    /**
     * Обновление аккаунта
     */
    public function updateAccount($id, $name, $timezone) {
        $this->query(
            "UPDATE accounts SET name = ?, timezone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$name, $timezone, $id]
        );
    }
    
    /**
     * Удаление аккаунта
     */
    public function deleteAccount($id) {
        $this->query("DELETE FROM accounts WHERE id = ?", [$id]);
    }
    
    /**
     * Получение всех скриптов
     */
    public function getScripts() {
        return $this->fetchAll("SELECT * FROM scripts ORDER BY name");
    }
    
    /**
     * Добавление скрипта
     */
    public function addScript($name, $description) {
        return $this->insert(
            "INSERT INTO scripts (name, description) VALUES (?, ?)",
            [$name, $description]
        );
    }
    
    /**
     * Обновление скрипта
     */
    public function updateScript($id, $name, $description) {
        $this->query(
            "UPDATE scripts SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$name, $description, $id]
        );
    }
    
    /**
     * Удаление скрипта
     */
    public function deleteScript($id) {
        $this->query("DELETE FROM scripts WHERE id = ?", [$id]);
    }
    
    /**
     * Добавление исключения плейсмента
     */
    public function addPlacementExclusion($accountId, $scriptId, $campaignName, $placementUrl, $excludedAtGmt, $batchId) {
        return $this->insert(
            "INSERT INTO placement_exclusions (account_id, script_id, campaign_name, placement_url, excluded_at_gmt, batch_id) VALUES (?, ?, ?, ?, ?, ?)",
            [$accountId, $scriptId, $campaignName, $placementUrl, $excludedAtGmt, $batchId]
        );
    }
    
    /**
     * Проверка существования дубля плейсмента
     */
    public function isDuplicatePlacement($accountId, $scriptId, $campaignName, $placementUrl) {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM placement_exclusions WHERE account_id = ? AND script_id = ? AND campaign_name = ? AND placement_url = ?",
            [$accountId, $scriptId, $campaignName, $placementUrl]
        );
        return $result['count'] > 0;
    }
    
    /**
     * Добавление исключения плейсмента с проверкой на дубли
     */
    public function addPlacementExclusionSafe($accountId, $scriptId, $campaignName, $placementUrl, $excludedAtGmt, $batchId) {
        if ($this->isDuplicatePlacement($accountId, $scriptId, $campaignName, $placementUrl)) {
            return false; // Дубль найден, не добавляем
        }
        
        $this->addPlacementExclusion($accountId, $scriptId, $campaignName, $placementUrl, $excludedAtGmt, $batchId);
        return true; // Успешно добавлено
    }
    
    /**
     * Получение исключений плейсментов с фильтрацией и пагинацией
     */
    public function getPlacementExclusions($accountId = null, $scriptId = null, $limit = 1000, $offset = 0) {
        $sql = "
            SELECT 
                pe.*,
                a.name as account_name,
                a.timezone as account_timezone,
                s.name as script_name,
                s.description as script_description
            FROM placement_exclusions pe
            JOIN accounts a ON pe.account_id = a.id
            JOIN scripts s ON pe.script_id = s.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($accountId) {
            $sql .= " AND pe.account_id = ?";
            $params[] = $accountId;
        }
        
        if ($scriptId) {
            $sql .= " AND pe.script_id = ?";
            $params[] = $scriptId;
        }
        
        $sql .= " ORDER BY pe.excluded_at_gmt DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Улучшенная конвертация времени из локального часового пояса в GMT
     */
    public function convertToGMT($dateString, $timezone) {
        try {
            // Парсим строку вида "Started Jun 25, 2025 4:50:53 AM, ended Jun 25, 2025 4:51:04 AM"
            preg_match('/ended\s+(.+)$/i', $dateString, $matches);
            if (!$matches) {
                throw new Exception("Не удалось найти время окончания в строке: " . $dateString);
            }
            
            $endedTime = trim($matches[1]);
            
            // Создаем карту часовых поясов
            $timezoneMap = [
                'GMT+00:00' => 'UTC',
                'GMT+01:00' => 'Europe/London',
                'GMT+02:00' => 'Europe/Berlin',
                'GMT+03:00' => 'Europe/Moscow',
                'GMT+04:00' => 'Asia/Dubai',
                'GMT+05:00' => 'Asia/Karachi',
                'GMT+06:00' => 'Asia/Almaty',
                'GMT+07:00' => 'Asia/Bangkok',
                'GMT+08:00' => 'Asia/Shanghai',
                'GMT+09:00' => 'Asia/Tokyo',
                'GMT+10:00' => 'Australia/Sydney',
                'GMT-05:00' => 'America/New_York',
                'GMT-06:00' => 'America/Chicago',
                'GMT-07:00' => 'America/Denver',
                'GMT-08:00' => 'America/Los_Angeles'
            ];
            
            $tz = isset($timezoneMap[$timezone]) ? $timezoneMap[$timezone] : 'UTC';
            
            // Пробуем различные форматы времени
            $formats = [
                'M j, Y g:i:s A',  // Jun 25, 2025 4:50:53 AM
                'M d, Y g:i:s A',  // Jun 25, 2025 4:50:53 AM (с ведущим нулем)
                'F j, Y g:i:s A',  // June 25, 2025 4:50:53 AM
                'M j, Y H:i:s',    // Jun 25, 2025 16:50:53
                'Y-m-d H:i:s',     // 2025-06-25 16:50:53
            ];
            
            $date = null;
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $endedTime, new DateTimeZone($tz));
                if ($date !== false) {
                    break;
                }
            }
            
            if ($date === false || $date === null) {
                throw new Exception("Не удалось распарсить время: '" . $endedTime . "'");
            }
            
            // Конвертируем в GMT для MySQL
            $date->setTimezone(new DateTimeZone('UTC'));
            
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new Exception("Ошибка конвертации времени: " . $e->getMessage());
        }
    }

    /**
     * Генерация уникального batch_id
     */
    public function generateBatchId() {
        return 'batch_' . date('Y_m_d_H_i_s') . '_' . uniqid();
    }
    
    /**
     * Получение записей по batch_id
     */
    public function getRecordsByBatchId($batchId) {
        return $this->fetchAll("
            SELECT 
                pe.*,
                a.name as account_name,
                a.timezone as account_timezone,
                s.name as script_name,
                s.description as script_description
            FROM placement_exclusions pe
            JOIN accounts a ON pe.account_id = a.id
            JOIN scripts s ON pe.script_id = s.id
            WHERE pe.batch_id = ?
            ORDER BY pe.created_at
        ", [$batchId]);
    }
    
    /**
     * Массовое обновление аккаунта для записей по batch_id
     */
    public function updateBatchAccount($batchId, $newAccountId) {
        $this->query(
            "UPDATE placement_exclusions SET account_id = ?, updated_at = CURRENT_TIMESTAMP WHERE batch_id = ?",
            [$newAccountId, $batchId]
        );
    }
    
    /**
     * Массовое обновление скрипта для записей по batch_id
     */
    public function updateBatchScript($batchId, $newScriptId) {
        $this->query(
            "UPDATE placement_exclusions SET script_id = ?, updated_at = CURRENT_TIMESTAMP WHERE batch_id = ?",
            [$newScriptId, $batchId]
        );
    }
    
    /**
     * Получение списка batch_id с информацией о количестве записей (альтернативный вариант)
     */
    public function getBatchList($limit = 10000) {
        return $this->fetchAll("
            SELECT 
                batch_summary.batch_id,
                batch_summary.record_count,
                batch_summary.first_created,
                batch_summary.last_created,
                a.name as account_name,
                s.name as script_name
            FROM (
                SELECT 
                    batch_id,
                    account_id,
                    script_id,
                    COUNT(*) as record_count,
                    MIN(created_at) as first_created,
                    MAX(created_at) as last_created
                FROM placement_exclusions 
                WHERE batch_id IS NOT NULL AND batch_id != ''
                GROUP BY batch_id, account_id, script_id
            ) batch_summary
            JOIN accounts a ON batch_summary.account_id = a.id
            JOIN scripts s ON batch_summary.script_id = s.id
            ORDER BY batch_summary.first_created DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Получение статистики
     */
    public function getStats() {
        $stats = [];
        
        $stats['total_accounts'] = $this->fetchOne("SELECT COUNT(*) as count FROM accounts")['count'];
        $stats['total_scripts'] = $this->fetchOne("SELECT COUNT(*) as count FROM scripts")['count'];
        $stats['total_exclusions'] = $this->fetchOne("SELECT COUNT(*) as count FROM placement_exclusions")['count'];
        $stats['today_exclusions'] = $this->fetchOne("SELECT COUNT(*) as count FROM placement_exclusions WHERE DATE(created_at) = CURDATE()")['count'];
        
        return $stats;
    }
}