<?php
/**
 * Класс для работы с базой данных SQLite
 */
class Database {
    private $pdo;
    private $dbPath;
    
    public function __construct($dbPath = 'placements.db') {
        $this->dbPath = $dbPath;
        $this->connect();
        $this->initializeDatabase();
    }
    
    /**
     * Подключение к базе данных
     */
    private function connect() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    /**
     * Инициализация базы данных из SQL файла
     */
    private function initializeDatabase() {
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $this->pdo->exec($sql);
        }
    }
    
    /**
     * Получение объекта PDO
     */
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
            "UPDATE accounts SET name = ?, timezone = ? WHERE id = ?",
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
            "UPDATE scripts SET name = ?, description = ? WHERE id = ?",
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
    public function addPlacementExclusion($accountId, $scriptId, $campaignName, $placementUrl, $excludedAtGmt) {
        return $this->insert(
            "INSERT INTO placement_exclusions (account_id, script_id, campaign_name, placement_url, excluded_at_gmt) VALUES (?, ?, ?, ?, ?)",
            [$accountId, $scriptId, $campaignName, $placementUrl, $excludedAtGmt]
        );
    }
    
    /**
     * Получение исключений плейсментов с фильтрацией
     */
    public function getPlacementExclusions($accountId = null, $scriptId = null, $limit = 1000) {
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
        
        $sql .= " ORDER BY pe.excluded_at_gmt DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Конвертация времени из локального часового пояса в GMT
     */
    public function convertToGMT($dateString, $timezone) {
        try {
            // Парсим строку вида "Started Jun 25, 2025 4:50:53 AM, ended Jun 25, 2025 4:51:04 AM"
            preg_match('/ended\s+(.+)$/i', $dateString, $matches);
            if (!$matches) {
                throw new Exception("Не удалось найти время окончания");
            }
            
            $endedTime = trim($matches[1]);
            
            // Создаем объект DateTime с учетом часового пояса
            $timezoneMap = [
                'GMT+00:00' => 'UTC',
                'GMT+01:00' => 'Europe/London'
            ];
            
            $tz = isset($timezoneMap[$timezone]) ? $timezoneMap[$timezone] : 'UTC';
            $date = new DateTime($endedTime, new DateTimeZone($tz));
            
            // Конвертируем в GMT
            $date->setTimezone(new DateTimeZone('UTC'));
            
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new Exception("Ошибка конвертации времени: " . $e->getMessage());
        }
    }
}
?>

