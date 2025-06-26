<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Тестирование подключения к базе данных...\n";

try {
    require_once 'includes/Database.php';
    echo "Класс Database загружен успешно\n";
    
    $db = new Database();
    echo "Объект Database создан успешно\n";
    
    $accounts = $db->getAccounts();
    echo "Количество аккаунтов: " . count($accounts) . "\n";
    
    $scripts = $db->getScripts();
    echo "Количество скриптов: " . count($scripts) . "\n";
    
    echo "Тест завершен успешно\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Трассировка: " . $e->getTraceAsString() . "\n";
}
?>

