<?php
/**
 * Тест подключения к MySQL
 */

require_once 'includes/Database.php';

try {
    $db = new Database();
    echo "✅ Подключение к MySQL успешно!\n";
    
    // Тест базовых операций
    $stats = $db->getStats();
    echo "📊 Статистика базы данных:\n";
    echo "- Аккаунтов: " . $stats['total_accounts'] . "\n";
    echo "- Скриптов: " . $stats['total_scripts'] . "\n";
    echo "- Исключений: " . $stats['total_exclusions'] . "\n";
    echo "- Сегодня добавлено: " . $stats['today_exclusions'] . "\n";
    
    // Тест транзакций
    $db->beginTransaction();
    $testAccountId = $db->addAccount('Тестовый аккаунт', 'GMT+03:00');
    echo "✅ Транзакция: добавлен аккаунт с ID $testAccountId\n";
    $db->rollback();
    echo "✅ Транзакция: откат выполнен\n";
    
    echo "\n🎉 Все тесты пройдены успешно!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}