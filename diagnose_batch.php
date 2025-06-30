<?php
require_once 'includes/EnvLoader.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    echo "<h1>Диагностика данных для массового редактирования</h1>";
    
    // 1. Проверяем общее количество записей
    $totalRecords = $db->fetchOne("SELECT COUNT(*) as count FROM placement_exclusions");
    echo "<h2>1. Общая статистика</h2>";
    echo "<p>Всего записей в placement_exclusions: <strong>{$totalRecords['count']}</strong></p>";
    
    // 2. Проверяем batch_id
    echo "<h2>2. Анализ batch_id</h2>";
    
    // Количество уникальных batch_id
    $uniqueBatches = $db->fetchOne("SELECT COUNT(DISTINCT batch_id) as count FROM placement_exclusions");
    echo "<p>Уникальных batch_id: <strong>{$uniqueBatches['count']}</strong></p>";
    
    // Записи без batch_id или с пустым batch_id
    $emptyBatches = $db->fetchOne("SELECT COUNT(*) as count FROM placement_exclusions WHERE batch_id IS NULL OR batch_id = ''");
    echo "<p>Записей без batch_id: <strong>{$emptyBatches['count']}</strong></p>";
    
    // 3. Показываем первые 10 записей
    echo "<h2>3. Первые 10 записей</h2>";
    $sampleRecords = $db->fetchAll("SELECT id, batch_id, account_id, script_id, created_at FROM placement_exclusions ORDER BY id LIMIT 10");
    
    if (!empty($sampleRecords)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Batch ID</th><th>Account ID</th><th>Script ID</th><th>Created At</th></tr>";
        foreach ($sampleRecords as $record) {
            echo "<tr>";
            echo "<td>{$record['id']}</td>";
            echo "<td>" . htmlspecialchars($record['batch_id']) . "</td>";
            echo "<td>{$record['account_id']}</td>";
            echo "<td>{$record['script_id']}</td>";
            echo "<td>{$record['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Тестируем оригинальный SQL запрос getBatchList()
    echo "<h2>4. Тестируем SQL запрос getBatchList()</h2>";
    
    $sql = "
        SELECT 
            pe.batch_id,
            COUNT(*) as record_count,
            MIN(pe.created_at) as first_created,
            MAX(pe.created_at) as last_created,
            a.name as account_name,
            s.name as script_name
        FROM placement_exclusions pe
        JOIN accounts a ON pe.account_id = a.id
        JOIN scripts s ON pe.script_id = s.id
        GROUP BY pe.batch_id, pe.account_id, pe.script_id
        ORDER BY pe.created_at DESC
        LIMIT 10
    ";
    
    try {
        $batchList = $db->fetchAll($sql);
        echo "<p>Результатов SQL запроса: <strong>" . count($batchList) . "</strong></p>";
        
        if (!empty($batchList)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Batch ID</th><th>Count</th><th>Account</th><th>Script</th><th>Created</th></tr>";
            foreach ($batchList as $batch) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars(substr($batch['batch_id'], 0, 30)) . "...</td>";
                echo "<td>{$batch['record_count']}</td>";
                echo "<td>" . htmlspecialchars($batch['account_name']) . "</td>";
                echo "<td>" . htmlspecialchars($batch['script_name']) . "</td>";
                echo "<td>{$batch['first_created']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>SQL запрос не вернул результатов!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Ошибка SQL запроса: " . $e->getMessage() . "</p>";
    }
    
    // 5. Упрощенный SQL запрос без JOIN
    echo "<h2>5. Упрощенный запрос без JOIN</h2>";
    
    $simpleSql = "
        SELECT 
            batch_id,
            COUNT(*) as record_count,
            MIN(created_at) as first_created,
            MAX(created_at) as last_created
        FROM placement_exclusions 
        WHERE batch_id IS NOT NULL AND batch_id != ''
        GROUP BY batch_id
        ORDER BY first_created DESC
        LIMIT 10
    ";
    
    try {
        $simpleBatchList = $db->fetchAll($simpleSql);
        echo "<p>Результатов упрощенного запроса: <strong>" . count($simpleBatchList) . "</strong></p>";
        
        if (!empty($simpleBatchList)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Batch ID</th><th>Count</th><th>First Created</th><th>Last Created</th></tr>";
            foreach ($simpleBatchList as $batch) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars(substr($batch['batch_id'], 0, 50)) . "</td>";
                echo "<td>{$batch['record_count']}</td>";
                echo "<td>{$batch['first_created']}</td>";
                echo "<td>{$batch['last_created']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Ошибка упрощенного запроса: " . $e->getMessage() . "</p>";
    }
    
    // 6. Проверяем существование аккаунтов и скриптов
    echo "<h2>6. Проверка связанных таблиц</h2>";
    $accountsCount = $db->fetchOne("SELECT COUNT(*) as count FROM accounts");
    $scriptsCount = $db->fetchOne("SELECT COUNT(*) as count FROM scripts");
    echo "<p>Аккаунтов в базе: <strong>{$accountsCount['count']}</strong></p>";
    echo "<p>Скриптов в базе: <strong>{$scriptsCount['count']}</strong></p>";
    
    // 7. Проверим, есть ли данные со сломанными связями
    echo "<h2>7. Проверка целостности данных</h2>";
    $orphanRecords = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM placement_exclusions pe 
        LEFT JOIN accounts a ON pe.account_id = a.id 
        LEFT JOIN scripts s ON pe.script_id = s.id 
        WHERE a.id IS NULL OR s.id IS NULL
    ");
    echo "<p>Записей с несуществующими account_id или script_id: <strong>{$orphanRecords['count']}</strong></p>";
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Ошибка подключения к базе данных</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
