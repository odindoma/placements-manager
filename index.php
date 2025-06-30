<?php
// Загружаем переменные окружения в самом начале
require_once 'includes/EnvLoader.php';

try {
    EnvLoader::load();
} catch (Exception $e) {
    die("Ошибка загрузки конфигурации: " . $e->getMessage());
}

// Настройки приложения из .env
ini_set('display_errors', EnvLoader::get('APP_DEBUG', 'false') === 'true' ? 1 : 0);
date_default_timezone_set(EnvLoader::get('APP_TIMEZONE', 'UTC'));

// Подключаем остальные файлы
require_once 'includes/Database.php';

// Инициализация базы данных
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die("Ошибка инициализации: " . $e->getMessage());
}

// Определение текущей страницы
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Список доступных страниц
$pages = [
    'home' => 'Главная',
    'accounts' => 'Управление аккаунтами',
    'scripts' => 'Управление скриптами',
    'add_placements' => 'Добавить данные о плейсментах',
    'view_placements' => 'Просмотр данных',
    'batch_edit' => 'Массовое редактирование'
];

// Проверка существования страницы
if (!array_key_exists($page, $pages)) {
    $page = 'home';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Ads - Управление плейсментами</title>
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Google Ads - Управление плейсментами</h1>
            <nav>
                <ul class="nav-menu">
                    <?php foreach ($pages as $pageKey => $pageTitle): ?>
                        <li>
                            <a href="?page=<?php echo $pageKey; ?>" 
                               class="<?php echo $page === $pageKey ? 'active' : ''; ?>">
                                <?php echo $pageTitle; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </header>

        <main>
            <?php
            // Подключение соответствующей страницы
            $pageFile = "pages/{$page}.php";
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                include 'pages/home.php';
            }
            ?>
        </main>

        <footer>
            <p>&copy; 2025 Google Ads Placements Manager</p>
        </footer>
    </div>

    <script src="js/main.js"></script>
</body>
</html>

