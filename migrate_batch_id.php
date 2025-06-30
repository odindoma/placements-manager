<?php
/**
 * Скрипт миграции для добавления колонки batch_id
 * Запускается один раз для обновления существующей базы данных
 */

require_once 'includes/Database.php';

try {
    $db = new Database();
    
    echo "Начинаем миграцию базы данных...\n";
    
    // Проверяем, существует ли уже колонка batch_id
    $columns = $db->fetchAll("PRAGMA table_info(placement_exclusions)");
    $batchIdExists = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'batch_id') {
            $batchIdExists = true;
            break;
        }
    }
    
    if (!$batchIdExists) {
        echo "Добавляем колонку batch_id...\n";
        
        // Добавляем колонку batch_id
        $db->query("ALTER TABLE placement_exclusions ADD COLUMN batch_id VARCHAR(50)");
        
        // Обновляем существующие записи, группируя их по времени создания
        echo "Обновляем существующие записи...\n";
        
        // Получаем все записи, сгруппированные по времени создания (в пределах 1 минуты)
        $existingRecords = $db->fetchAll("
            SELECT id, created_at 
            FROM placement_exclusions 
            WHERE batch_id IS NULL 
            ORDER BY created_at
        ");
        
        if (!empty($existingRecords)) {
            $currentBatchId = null;
            $lastTimestamp = null;
            
            foreach ($existingRecords as $record) {
                $currentTimestamp = strtotime($record['created_at']);
                
                // Если прошло больше 1 минуты с последней записи, создаем новый batch_id
                if ($lastTimestamp === null || ($currentTimestamp - $lastTimestamp) > 60) {
                    $currentBatchId = 'batch_' . date('Y_m_d_H_i_s', $currentTimestamp) . '_' . uniqid();
                }
                
                // Обновляем запись с batch_id
                $db->query("UPDATE placement_exclusions SET batch_id = ? WHERE id = ?", 
                          [$currentBatchId, $record['id']]);
                
                $lastTimestamp = $currentTimestamp;
            }
        }
        
        // Делаем колонку обязательной (в SQLite это делается через пересоздание таблицы)
        echo "Применяем ограничения...\n";
        
        // Создаем индекс для batch_id
        $db->query("CREATE INDEX IF NOT EXISTS idx_placement_exclusions_batch ON placement_exclusions(batch_id)");
        
        echo "Миграция успешно завершена!\n";
    } else {
        echo "Колонка batch_id уже существует. Миграция не требуется.\n";
    }
    
    // Проверяем результат
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM placement_exclusions WHERE batch_id IS NOT NULL");
    echo "Записей с batch_id: " . $count['count'] . "\n";
    
} catch (Exception $e) {
    echo "Ошибка миграции: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Миграция завершена успешно!\n";
?>

