<?php
// Получение параметров фильтрации
$filterAccountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : null;
$filterScriptId = isset($_GET['script_id']) ? (int)$_GET['script_id'] : null;
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

// Получение данных с учетом фильтров
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

if ($filterAccountId) {
    $sql .= " AND pe.account_id = ?";
    $params[] = $filterAccountId;
}

if ($filterScriptId) {
    $sql .= " AND pe.script_id = ?";
    $params[] = $filterScriptId;
}

if ($filterDateFrom) {
    $sql .= " AND DATE(pe.excluded_at_gmt) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $sql .= " AND DATE(pe.excluded_at_gmt) <= ?";
    $params[] = $filterDateTo;
}

// Подсчет общего количества записей
$countSql = str_replace("SELECT pe.*, a.name as account_name, a.timezone as account_timezone, s.name as script_name, s.description as script_description", "SELECT COUNT(*) as total_count", $sql);
$countResult = $db->fetchOne($countSql, $params);
$totalRecords = $countResult['total_count'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

// Добавление сортировки и лимита
$sql .= " ORDER BY pe.excluded_at_gmt DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$exclusions = $db->fetchAll($sql, $params);

// Получение списков для фильтров
$accounts = $db->getAccounts();
$scripts = $db->getScripts();

// Обработка экспорта в CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Получение всех данных без лимита для экспорта
    $exportSql = str_replace(" LIMIT ? OFFSET ?", "", $sql);
    array_pop($params); // Удаляем limit
    array_pop($params); // Удаляем offset
    
    $exportData = $db->fetchAll($exportSql, $params);
    
    // Генерация CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="placements_exclusions_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Заголовки CSV
    fputcsv($output, [
        'ID',
        'Дата/время (GMT)',
        'Аккаунт',
        'Временная зона аккаунта',
        'Скрипт',
        'Кампания',
        'Плейсмент',
        'Дата создания записи'
    ]);
    
    // Данные
    foreach ($exportData as $row) {
        fputcsv($output, [
            $row['id'],
            $row['excluded_at_gmt'],
            $row['account_name'],
            $row['account_timezone'],
            $row['script_name'],
            $row['campaign_name'],
            $row['placement_url'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<div class="page-title">Просмотр данных о плейсментах</div>

<!-- Фильтры -->
<div class="filters">
    <form method="GET" id="filters-form">
        <input type="hidden" name="page" value="view_placements">
        
        <div class="filters-row">
            <div class="filter-group">
                <label for="account_id">Аккаунт</label>
                <select id="account_id" name="account_id">
                    <option value="">Все аккаунты</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>" 
                                <?php echo $filterAccountId == $account['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="script_id">Скрипт</label>
                <select id="script_id" name="script_id">
                    <option value="">Все скрипты</option>
                    <?php foreach ($scripts as $script): ?>
                        <option value="<?php echo $script['id']; ?>" 
                                <?php echo $filterScriptId == $script['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($script['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from">Дата с</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">Дата по</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
            </div>
            
            <div class="filter-group">
                <label for="limit">Записей на странице</label>
                <select id="limit" name="limit">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn">Применить фильтры</button>
                <a href="?page=view_placements" class="btn btn-secondary">Сбросить</a>
            </div>
        </div>
    </form>
</div>

<!-- Статистика и экспорт -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3>Результаты поиска</h3>
            <p>Найдено записей: <strong><?php echo $totalRecords; ?></strong></p>
            <?php if ($totalPages > 1): ?>
                <p>Страница <?php echo $page; ?> из <?php echo $totalPages; ?></p>
            <?php endif; ?>
        </div>
        
        <div>
            <?php if (!empty($exclusions)): ?>
                <a href="<?php echo '?' . http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="btn btn-success">Экспорт в CSV</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($exclusions)): ?>
<!-- Таблица данных -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Дата/время (GMT)</th>
                <th>Аккаунт</th>
                <th>Скрипт</th>
                <th>Кампания</th>
                <th>Плейсмент</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($exclusions as $exclusion): ?>
            <tr>
                <td>
                    <?php echo date('d.m.Y H:i', strtotime($exclusion['excluded_at_gmt'])); ?>
                    <br>
                    <small style="color: #666;">
                        <?php 
                        // Показать время в часовом поясе аккаунта
                        $localTime = new DateTime($exclusion['excluded_at_gmt'], new DateTimeZone('UTC'));
                        $accountTz = $exclusion['account_timezone'];
                        $timezoneMap = [
                            'GMT+00:00' => 'UTC',
                            'GMT+01:00' => 'Europe/London'
                        ];
                        $tz = isset($timezoneMap[$accountTz]) ? $timezoneMap[$accountTz] : 'UTC';
                        $localTime->setTimezone(new DateTimeZone($tz));
                        echo $localTime->format('d.m.Y H:i') . ' (' . $accountTz . ')';
                        ?>
                    </small>
                </td>
                <td><?php echo htmlspecialchars($exclusion['account_name']); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($exclusion['script_name']); ?></strong>
                    <?php if ($exclusion['script_description']): ?>
                        <br>
                        <small style="color: #666;">
                            <?php 
                            $desc = htmlspecialchars($exclusion['script_description']);
                            echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                            ?>
                        </small>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($exclusion['campaign_name']); ?></td>
                <td>
                    <a href="http://<?php echo htmlspecialchars($exclusion['placement_url']); ?>" 
                       target="_blank" 
                       style="color: #4285f4; text-decoration: none;">
                        <?php echo htmlspecialchars($exclusion['placement_url']); ?>
                    </a>
                </td>
                <td>
                    <button class="btn btn-secondary" onclick="showDetails(<?php echo $exclusion['id']; ?>)">
                        Детали
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Пагинация -->
<?php if ($totalPages > 1): ?>
<div class="text-center mt-3">
    <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
        <?php if ($page > 1): ?>
            <a href="<?php echo '?' . http_build_query(array_merge($_GET, ['p' => $page - 1])); ?>" 
               class="btn btn-secondary">← Предыдущая</a>
        <?php endif; ?>
        
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        for ($i = $startPage; $i <= $endPage; $i++):
        ?>
            <a href="<?php echo '?' . http_build_query(array_merge($_GET, ['p' => $i])); ?>" 
               class="btn <?php echo $i == $page ? 'btn-success' : 'btn-secondary'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo '?' . http_build_query(array_merge($_GET, ['p' => $page + 1])); ?>" 
               class="btn btn-secondary">Следующая →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card">
    <h3>Нет данных</h3>
    <p>По заданным фильтрам не найдено ни одной записи об исключении плейсментов.</p>
    <div class="mt-2">
        <a href="?page=view_placements" class="btn btn-secondary">Сбросить фильтры</a>
        <a href="?page=add_placements" class="btn">Добавить данные</a>
    </div>
</div>
<?php endif; ?>

<!-- Модальное окно для деталей -->
<div id="details-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Детали исключения плейсмента</h3>
            <button class="modal-close" onclick="hideDetails()">&times;</button>
        </div>
        <div id="details-content" class="modal-body">
            <!-- Содержимое будет загружено через JavaScript -->
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.modal-body {
    padding: 1rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-close:hover {
    color: #333;
}
</style>

<script>
function showDetails(exclusionId) {
    // Найти данные исключения в текущих результатах
    const exclusions = <?php echo json_encode($exclusions); ?>;
    const exclusion = exclusions.find(e => e.id == exclusionId);
    
    if (!exclusion) {
        alert('Данные не найдены');
        return;
    }
    
    // Форматирование времени
    const gmtTime = new Date(exclusion.excluded_at_gmt + ' UTC');
    const createdTime = new Date(exclusion.created_at);
    
    const detailsHtml = `
        <div style="display: grid; gap: 1rem;">
            <div>
                <strong>ID записи:</strong> ${exclusion.id}
            </div>
            <div>
                <strong>Аккаунт:</strong> ${exclusion.account_name} (${exclusion.account_timezone})
            </div>
            <div>
                <strong>Скрипт:</strong> ${exclusion.script_name}
                ${exclusion.script_description ? '<br><small style="color: #666;">' + exclusion.script_description + '</small>' : ''}
            </div>
            <div>
                <strong>Кампания:</strong> ${exclusion.campaign_name}
            </div>
            <div>
                <strong>Исключенный плейсмент:</strong> 
                <a href="http://${exclusion.placement_url}" target="_blank" style="color: #4285f4;">
                    ${exclusion.placement_url}
                </a>
            </div>
            <div>
                <strong>Время исключения (GMT):</strong> ${gmtTime.toLocaleString('ru-RU')}
            </div>
            <div>
                <strong>Запись добавлена:</strong> ${createdTime.toLocaleString('ru-RU')}
            </div>
        </div>
    `;
    
    document.getElementById('details-content').innerHTML = detailsHtml;
    document.getElementById('details-modal').classList.remove('hidden');
}

function hideDetails() {
    document.getElementById('details-modal').classList.add('hidden');
}

// Закрытие модального окна по клику вне его
document.getElementById('details-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDetails();
    }
});

// Закрытие модального окна по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDetails();
    }
});
</script>

