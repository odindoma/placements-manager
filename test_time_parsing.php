<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/Database.php';

echo "Тестирование функции парсинга времени...\n\n";

try {
    $db = new Database();
    
    // Тестовые строки времени
    $testStrings = [
        "Started Jun 25, 2025 4:50:53 AM, ended Jun 26, 2025 4:51:02 AM",
        "Started Jun 25, 2025 4:50:53 AM, ended Jun 26, 2025 4:51:04 AM",
        "Started Dec 1, 2024 10:30:15 PM, ended Dec 1, 2024 10:31:22 PM"
    ];
    
    $timezone = 'GMT+03:00'; // Московское время
    
    foreach ($testStrings as $i => $testString) {
        echo "Тест " . ($i + 1) . ":\n";
        echo "Входная строка: " . $testString . "\n";
        
        try {
            $result = $db->convertToGMT($testString, $timezone);
            echo "Результат: " . $result . " (GMT)\n";
            echo "✅ Успешно!\n\n";
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "Тестирование завершено.\n";
    
} catch (Exception $e) {
    echo "Общая ошибка: " . $e->getMessage() . "\n";
}
?>

