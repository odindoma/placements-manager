<?php
// Получение параметров фильтрации
$filterAccountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : null;
$filterScriptId = isset($_GET['script_id']) ? (int)$_GET['script_id'] : null;
$filterCampaign = isset($_GET['campaign']) ? trim($_GET['campaign']) : '';
$filterPlacement = isset($_GET['placement']) ? trim($_GET['placement']) : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;

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

if ($filterCampaign) {
    $sql .= " AND pe.campaign_name LIKE ?";
    $params[] = '%' . $filterCampaign . '%';
}

if ($filterPlacement) {
    $sql .= " AND pe.placement_url LIKE ?";
    $params[] = '%' . $filterPlacement . '%';
}

if ($filterDateFrom) {
    $sql .= " AND DATE(pe.excluded_at_gmt) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $sql .= " AND DATE(pe.excluded_at_gmt) <= ?";
    $params[] = $filterDateTo;
}

// Сортировка для группировки: кампания, скрипт, затем дата по убыванию
$sql .= " ORDER BY pe.campaign_name ASC, s.name ASC, pe.excluded_at_gmt DESC";

if ($limit > 0) {
    $sql .= " LIMIT ?";
    $params[] = $limit;
}

$exclusions = $db->fetchAll($sql, $params);

// Подсчет общего количества записей (без лимита)
$countSql = str_replace(
    "SELECT pe.*, a.name as account_name, a.timezone as account_timezone, s.name as script_name, s.description as script_description", 
    "SELECT COUNT(*) as total_count", 
    $sql
);
$countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
$countSql = preg_replace('/LIMIT.*$/', '', $countSql);
$countParams = array_slice($params, 0, -1); // Убираем последний параметр (limit)
$countResult = $db->fetchOne($countSql, $countParams);
$totalRecords = $countResult['total_count'] ?? 0;

// Получение списков для фильтров с подсчетом исключений
$accountsSql = "
    SELECT 
        a.id,
        a.name,
        a.timezone,
        COALESCE(pe_count.exclusions_count, 0) as exclusions_count
    FROM accounts a
    LEFT JOIN (
        SELECT 
            account_id, 
            COUNT(*) as exclusions_count
        FROM placement_exclusions
        GROUP BY account_id
    ) pe_count ON a.id = pe_count.account_id
    ORDER BY a.name
";
$accounts = $db->fetchAll($accountsSql);
// Получение списка скриптов с учетом фильтров
$scriptsSql = "
    SELECT 
        s.id,
        s.name,
        s.description,
        COALESCE(pe_count.exclusions_count, 0) as exclusions_count
    FROM scripts s
    LEFT JOIN (
        SELECT 
            script_id, 
            COUNT(*) as exclusions_count
        FROM placement_exclusions pe
        WHERE 1=1
";

$scriptsParams = [];

// Применяем те же фильтры, что и для основного запроса (кроме script_id)
if ($filterAccountId) {
    $scriptsSql .= " AND pe.account_id = ?";
    $scriptsParams[] = $filterAccountId;
}

if ($filterCampaign) {
    $scriptsSql .= " AND pe.campaign_name LIKE ?";
    $scriptsParams[] = '%' . $filterCampaign . '%';
}

if ($filterPlacement) {
    $scriptsSql .= " AND pe.placement_url LIKE ?";
    $scriptsParams[] = '%' . $filterPlacement . '%';
}

if ($filterDateFrom) {
    $scriptsSql .= " AND DATE(pe.excluded_at_gmt) >= ?";
    $scriptsParams[] = $filterDateFrom;
}

if ($filterDateTo) {
    $scriptsSql .= " AND DATE(pe.excluded_at_gmt) <= ?";
    $scriptsParams[] = $filterDateTo;
}

$scriptsSql .= "
        GROUP BY script_id
    ) pe_count ON s.id = pe_count.script_id
    ORDER BY s.name
";

$scripts = $db->fetchAll($scriptsSql, $scriptsParams);

// Получение списка кампаний с учетом фильтров
$campaignsSql = "
    SELECT DISTINCT campaign_name, COUNT(*) as count
    FROM placement_exclusions pe
    WHERE 1=1
";

$campaignsParams = [];

if ($filterAccountId) {
    $campaignsSql .= " AND pe.account_id = ?";
    $campaignsParams[] = $filterAccountId;
}

if ($filterScriptId) {
    $campaignsSql .= " AND pe.script_id = ?";
    $campaignsParams[] = $filterScriptId;
}

if ($filterPlacement) {
    $campaignsSql .= " AND pe.placement_url LIKE ?";
    $campaignsParams[] = '%' . $filterPlacement . '%';
}

if ($filterDateFrom) {
    $campaignsSql .= " AND DATE(pe.excluded_at_gmt) >= ?";
    $campaignsParams[] = $filterDateFrom;
}

if ($filterDateTo) {
    $campaignsSql .= " AND DATE(pe.excluded_at_gmt) <= ?";
    $campaignsParams[] = $filterDateTo;
}

$campaignsSql .= " GROUP BY campaign_name ORDER BY campaign_name ASC";
$campaigns = $db->fetchAll($campaignsSql, $campaignsParams);

// Получение списка плейсментов с учетом фильтров
$placementsSql = "
    SELECT DISTINCT placement_url, COUNT(*) as count
    FROM placement_exclusions pe
    WHERE 1=1
";

$placementsParams = [];

if ($filterAccountId) {
    $placementsSql .= " AND pe.account_id = ?";
    $placementsParams[] = $filterAccountId;
}

if ($filterScriptId) {
    $placementsSql .= " AND pe.script_id = ?";
    $placementsParams[] = $filterScriptId;
}

if ($filterCampaign) {
    $placementsSql .= " AND pe.campaign_name LIKE ?";
    $placementsParams[] = '%' . $filterCampaign . '%';
}

if ($filterDateFrom) {
    $placementsSql .= " AND DATE(pe.excluded_at_gmt) >= ?";
    $placementsParams[] = $filterDateFrom;
}

if ($filterDateTo) {
    $placementsSql .= " AND DATE(pe.excluded_at_gmt) <= ?";
    $placementsParams[] = $filterDateTo;
}

$placementsSql .= " GROUP BY placement_url ORDER BY count DESC, placement_url ASC LIMIT 100";
$placements = $db->fetchAll($placementsSql, $placementsParams);

// Получение списка плейсментов
$placementsSql = "
    SELECT DISTINCT placement_url, COUNT(*) as count
    FROM placement_exclusions 
    GROUP BY placement_url 
    ORDER BY count DESC, placement_url ASC
    LIMIT 100
";
$placements = $db->fetchAll($placementsSql);

// Группировка данных для отображения
$groupedData = [];
foreach ($exclusions as $exclusion) {
    $campaignName = $exclusion['campaign_name'];
    $scriptName = $exclusion['script_name'];
    $scriptId = $exclusion['script_id'];
    
    if (!isset($groupedData[$campaignName])) {
        $groupedData[$campaignName] = [
            'campaign_name' => $campaignName,
            'account_name' => $exclusion['account_name'],
            'account_timezone' => $exclusion['account_timezone'],
            'scripts' => [],
            'total_exclusions' => 0
        ];
    }
    
    $scriptKey = $scriptId . '_' . $scriptName;
    if (!isset($groupedData[$campaignName]['scripts'][$scriptKey])) {
        $groupedData[$campaignName]['scripts'][$scriptKey] = [
            'script_id' => $scriptId,
            'script_name' => $scriptName,
            'script_description' => $exclusion['script_description'],
            'placements' => [],
            'total_exclusions' => 0
        ];
    }
    
    $groupedData[$campaignName]['scripts'][$scriptKey]['placements'][] = $exclusion;
    $groupedData[$campaignName]['scripts'][$scriptKey]['total_exclusions']++;
    $groupedData[$campaignName]['total_exclusions']++;
}

// Сортировка кампаний по количеству исключений (по убыванию)
uasort($groupedData, function($a, $b) {
    return $b['total_exclusions'] - $a['total_exclusions'];
});

// Обработка экспорта в CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="placements_exclusions_grouped_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Заголовки CSV
    fputcsv($output, [
        'Кампания',
        'Скрипт',
        'Плейсмент',
        'Дата исключения (GMT)',
        'Аккаунт',
        'Временная зона',
        'Batch ID'
    ]);
    
    // Данные
    foreach ($exclusions as $row) {
        fputcsv($output, [
            $row['campaign_name'],
            $row['script_name'],
            $row['placement_url'],
            $row['excluded_at_gmt'],
            $row['account_name'],
            $row['account_timezone'],
            $row['batch_id']
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
                <label for="account_id">🏢 Аккаунт</label>
                <select id="account_id" name="account_id">
                    <option value="">Все аккаунты</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>" 
                                <?php echo $filterAccountId == $account['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['name']); ?> (<?php echo $account['exclusions_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="script_id">🔧 Скрипт</label>
                <select id="script_id" name="script_id">
                    <option value="">Все скрипты</option>
                    <?php foreach ($scripts as $script): ?>
                        <option value="<?php echo $script['id']; ?>" 
                                <?php echo $filterScriptId == $script['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($script['name']); ?> (<?php echo $script['exclusions_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="campaign">📊 Кампания</label>
                <input type="text" id="campaign" name="campaign" 
                       value="<?php echo htmlspecialchars($filterCampaign); ?>" 
                       placeholder="Поиск по кампании" list="campaigns-list">
                <datalist id="campaigns-list">
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo htmlspecialchars($campaign['campaign_name']); ?>">
                            <?php echo htmlspecialchars($campaign['campaign_name']) . ' (' . $campaign['count'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="filter-group">
                <label for="placement">🌐 Плейсмент</label>
                <input type="text" id="placement" name="placement" 
                       value="<?php echo htmlspecialchars($filterPlacement); ?>" 
                       placeholder="Поиск по URL" list="placements-list">
                <datalist id="placements-list">
                    <?php foreach ($placements as $placement): ?>
                        <option value="<?php echo htmlspecialchars($placement['placement_url']); ?>">
                            <?php echo htmlspecialchars($placement['placement_url']) . ' (' . $placement['count'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="filter-group">
                <label for="date_from">📅 Дата с</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">📅 Дата по</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
            </div>
            
            <div class="filter-group">
                <label for="limit">🔢 Лимит записей</label>
                <select id="limit" name="limit">
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                    <option value="1000" <?php echo $limit == 1000 ? 'selected' : ''; ?>>1000</option>
                    <option value="5000" <?php echo $limit == 5000 ? 'selected' : ''; ?>>5000</option>
                    <option value="0" <?php echo $limit == 0 ? 'selected' : ''; ?>>Без лимита</option>
                </select>
            </div>
            
            <div class="filter-buttons-compact">
                <button type="submit" class="btn">🔍 Применить</button>
                <a href="?page=view_placements" class="btn btn-secondary">🔄 Сбросить</a>
            </div>
        </div>
    </form>
</div>

<!-- Статистика и экспорт -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3>Результаты поиска</h3>
            <p>
                Найдено записей: <strong><?php echo count($exclusions); ?></strong>
                <?php if ($limit > 0 && $totalRecords > $limit): ?>
                    из <strong><?php echo $totalRecords; ?></strong> (показаны первые <?php echo $limit; ?>)
                <?php endif; ?>
            </p>
            <p>Кампаний: <strong><?php echo count($groupedData); ?></strong></p>
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" class="btn btn-secondary" onclick="expandAll()">Развернуть все</button>
            <button type="button" class="btn btn-secondary" onclick="collapseAll()">Свернуть все</button>
            <?php if (!empty($exclusions)): ?>
                <a href="<?php echo '?' . http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="btn btn-success">Экспорт в CSV</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($groupedData)): ?>
<!-- Группированные данные -->
<div class="grouped-data">
    <?php foreach ($groupedData as $campaignName => $campaignData): ?>
        <div class="campaign-group">
            <div class="campaign-header">
                <div class="campaign-info" onclick="toggleCampaign('<?php echo md5($campaignName); ?>')">
                    <h3>
                        <span class="toggle-icon rotated">▼</span>
                        📊 <?php echo htmlspecialchars($campaignName); ?>
                        <button type="button" class="btn-copy" 
                                onclick="event.stopPropagation(); copyCampaignName('<?php echo addslashes($campaignName); ?>')"
                                title="Копировать название кампании">
                            📋
                        </button>
                    </h3>
                    <span class="campaign-stats">
                        Исключений: <?php echo $campaignData['total_exclusions']; ?> | 
                        Скриптов: <?php echo count($campaignData['scripts']); ?> | 
                        Аккаунт: <?php echo htmlspecialchars($campaignData['account_name']); ?>
                    </span>
                </div>
                <div class="campaign-actions">
                    <button type="button" class="btn btn-chart" 
                            onclick="showCampaignCharts('<?php echo addslashes($campaignName); ?>')">
                        📈 Графики
                    </button>
                </div>
            </div>
            
            <div class="campaign-content collapsed" id="campaign-<?php echo md5($campaignName); ?>">
                <?php foreach ($campaignData['scripts'] as $scriptData): ?>
                    <div class="script-group">
                        <div class="script-header" onclick="toggleScript('<?php echo md5($campaignName . $scriptData['script_id']); ?>')">
                            <div class="script-info">
                                <h4>
                                    <span class="toggle-icon rotated">▼</span>
                                    🔧 <?php echo htmlspecialchars($scriptData['script_name']); ?>
                                </h4>
                                <span class="script-stats">
                                    Исключений: <?php echo $scriptData['total_exclusions']; ?>
                                </span>
                                <?php if ($scriptData['script_description']): ?>
                                    <div class="script-description">
                                        <?php echo htmlspecialchars($scriptData['script_description']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
<div class="script-content collapsed" id="script-<?php echo md5($campaignName . $scriptData['script_id']); ?>">
    <div class="placements-container">
        <?php
        // Группируем плейсменты по дням
        $placementsByDay = [];
        foreach ($scriptData['placements'] as $placement) {
            $date = date('Y-m-d', strtotime($placement['excluded_at_gmt']));
            $dateFormatted = date('d.m.Y', strtotime($placement['excluded_at_gmt']));
            
            if (!isset($placementsByDay[$date])) {
                $placementsByDay[$date] = [
                    'date' => $date,
                    'formatted_date' => $dateFormatted,
                    'placements' => [],
                    'count' => 0
                ];
            }
            
            $placementsByDay[$date]['placements'][] = $placement;
            $placementsByDay[$date]['count']++;
        }
        
        // Сортируем дни по убыванию (новые сверху)
        krsort($placementsByDay);
        ?>
        
        <?php foreach ($placementsByDay as $dayData): ?>
            <div class="day-group">
                <div class="day-header" onclick="toggleDay('<?php echo md5($campaignName . $scriptData['script_id'] . $dayData['date']); ?>')">
                    <div class="day-info">
                        <h5>
                            <span class="toggle-icon rotated">▼</span>
                            📅 <?php echo $dayData['formatted_date']; ?>
                        </h5>
                        <span class="day-stats">
                            Исключений: <?php echo $dayData['count']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="day-content collapsed" id="day-<?php echo md5($campaignName . $scriptData['script_id'] . $dayData['date']); ?>">
                    <div class="placements-table">
                        <table>
                            <thead>
                                <tr>
                                    <th width="15%">Время (GMT)</th>
                                    <th width="75%">Плейсмент</th>
                                    <th width="10%">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dayData['placements'] as $placement): ?>
                                    <tr>
                                        <td>
                                            <div class="time-only">
                                                <?php echo date('H:i:s', strtotime($placement['excluded_at_gmt'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="http://<?php echo htmlspecialchars($placement['placement_url']); ?>" 
                                               target="_blank" 
                                               class="placement-link">
                                                <?php echo htmlspecialchars($placement['placement_url']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <button class="btn btn-secondary btn-sm" 
                                                    onclick="showDetails(<?php echo $placement['id']; ?>)">
                                                Детали
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

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

<!-- Модальное окно для графиков -->
<div id="charts-modal" class="modal hidden">
    <div class="modal-content charts-modal-content">
        <div class="modal-header">
            <h3 id="charts-title">Аналитика кампании</h3>
            <button class="modal-close" onclick="hideCharts()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="charts-loading" class="text-center" style="padding: 2rem;">
                <div class="loading-spinner"></div>
                <p>Загрузка данных для графиков...</p>
            </div>
            <div id="charts-content" style="display: none;">
                <div class="charts-info" id="charts-info">
                    <!-- Информация о периоде -->
                </div>
                <div class="charts-container" id="charts-container">
                    <!-- Графики будут добавлены сюда -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Подключение Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>

<style>
/* Стили для группированного отображения */
.grouped-data {
    margin-top: 1rem;
}

.campaign-group {
    margin-bottom: 1.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.campaign-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.campaign-info {
    flex: 1;
    cursor: pointer;
    user-select: none;
    transition: opacity 0.3s ease;
}

.campaign-info:hover {
    opacity: 0.9;
}

.campaign-info h3 {
    margin: 0;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.campaign-stats {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.25rem;
    display: block;
}

.campaign-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-chart {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-chart:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
}

.campaign-content {
    background: #f8f9fa;
    display: block;
}

.campaign-content.collapsed {
    display: none;
}

.script-group {
    margin: 0.5rem;
    border: 1px solid #d0d0d0;
    border-radius: 6px;
    overflow: hidden;
}

.script-header {
    background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
    color: white;
    padding: 0.75rem;
    cursor: pointer;
    user-select: none;
    transition: background 0.3s ease;
}

.script-header:hover {
    opacity: 0.9;
}

.script-info h4 {
    margin: 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.script-stats {
    font-size: 0.8rem;
    opacity: 0.9;
    margin-top: 0.25rem;
    display: block;
}

.script-description {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-top: 0.25rem;
    font-style: italic;
}

.script-content {
    background: white;
    display: block;
}

.script-content.collapsed {
    display: none;
}

.placements-table {
    padding: 0;
}

.placements-table table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.placements-table th,
.placements-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.placements-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.placements-table tr:hover {
    background-color: #f8f9fa;
}

.datetime {
    font-weight: 600;
    color: #495057;
}

.time {
    font-size: 0.85rem;
    color: #6c757d;
}

.placement-link {
    color: #4285f4;
    text-decoration: none;
    word-break: break-all;
}

.placement-link:hover {
    text-decoration: underline;
}

.account-info {
    display: flex;
    flex-direction: column;
}

.account-info small {
    color: #6c757d;
    font-size: 0.8rem;
}

.toggle-icon {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}

.toggle-icon.rotated {
    transform: rotate(-90deg);
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

/* Модальные окна */
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

.modal.hidden {
    display: none !important;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.charts-modal-content {
    max-width: 1200px;
    width: 95%;
    max-height: 90vh;
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

/* Стили для графиков */
.charts-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.charts-container {
    display: grid;
    gap: 2rem;
}

.chart-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1rem;
}

.chart-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-canvas {
    position: relative;
    height: 300px;
    width: 100%;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.text-center {
    text-align: center;
}

/* Адаптивность */
@media (max-width: 768px) {
    .campaign-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .campaign-info h3 {
        font-size: 1rem;
    }
    
    .script-info h4 {
        font-size: 0.9rem;
    }
    
    .placements-table th,
    .placements-table td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    
    .charts-modal-content {
        width: 98%;
        max-height: 95vh;
    }
    
    .chart-canvas {
        height: 250px;
    }
}
.btn-copy {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    margin-left: 0.5rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
}

.btn-copy:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: scale(1.05);
}

.btn-copy:active {
    transform: scale(0.95);
}

.copy-success {
    background: rgba(40, 167, 69, 0.8) !important;
    border-color: rgba(40, 167, 69, 1) !important;
}

/* Уведомление о копировании */
.copy-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    z-index: 2000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.copy-notification.show {
    transform: translateX(0);
}
</style>

<script>
// Данные для модального окна
const exclusionsData = <?php echo json_encode($exclusions); ?>;
const groupedDataForCharts = <?php echo json_encode($groupedData); ?>;

// Функции для сворачивания/разворачивания
function toggleCampaign(campaignId) {
    const content = document.getElementById('campaign-' + campaignId);
    const icon = content.previousElementSibling.querySelector('.toggle-icon');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        icon.classList.remove('rotated');
    } else {
        content.classList.add('collapsed');
        icon.classList.add('rotated');
    }
}

function toggleScript(scriptId) {
    const content = document.getElementById('script-' + scriptId);
    const icon = content.previousElementSibling.querySelector('.toggle-icon');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        icon.classList.remove('rotated');
    } else {
        content.classList.add('collapsed');
        icon.classList.add('rotated');
    }
}

function expandAll() {
    document.querySelectorAll('.campaign-content, .script-content, .day-content').forEach(content => {
        content.classList.remove('collapsed');
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.remove('rotated');
    });
}

function collapseAll() {
    document.querySelectorAll('.campaign-content, .script-content, .day-content').forEach(content => {
        content.classList.add('collapsed');
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.add('rotated');
    });
}

// Функция для сворачивания/разворачивания дней
function toggleDay(dayId) {
    const content = document.getElementById('day-' + dayId);
    const icon = content.previousElementSibling.querySelector('.toggle-icon');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        icon.classList.remove('rotated');
    } else {
        content.classList.add('collapsed');
        icon.classList.add('rotated');
    }
}


// Показ деталей
function showDetails(exclusionId) {
    const exclusion = exclusionsData.find(e => e.id == exclusionId);
    
    if (!exclusion) {
        alert('Данные не найдены');
        return;
    }
    
    const gmtTime = new Date(exclusion.excluded_at_gmt + ' UTC');
    const createdTime = new Date(exclusion.created_at);
    
    const detailsHtml = `
        <div style="display: grid; gap: 1rem;">
            <div><strong>ID записи:</strong> ${exclusion.id}</div>
            <div><strong>Кампания:</strong> ${exclusion.campaign_name}</div>
            <div><strong>Аккаунт:</strong> ${exclusion.account_name} (${exclusion.account_timezone})</div>
            <div>
                <strong>Скрипт:</strong> ${exclusion.script_name}
                ${exclusion.script_description ? '<br><small style="color: #666;">' + exclusion.script_description + '</small>' : ''}
            </div>
            <div>
                <strong>Исключенный плейсмент:</strong> 
                <a href="http://${exclusion.placement_url}" target="_blank" style="color: #4285f4; word-break: break-all;">
                    ${exclusion.placement_url}
                </a>
            </div>
            <div><strong>Время исключения (GMT):</strong> ${gmtTime.toLocaleString('ru-RU')}</div>
            <div><strong>Batch ID:</strong> <code>${exclusion.batch_id}</code></div>
            <div><strong>Запись добавлена:</strong> ${createdTime.toLocaleString('ru-RU')}</div>
        </div>
    `;
    
    document.getElementById('details-content').innerHTML = detailsHtml;
    document.getElementById('details-modal').classList.remove('hidden');
}

function hideDetails() {
    document.getElementById('details-modal').classList.add('hidden');
}

// Функции для работы с графиками
let currentCharts = [];

function showCampaignCharts(campaignName) {
    // Показываем модальное окно с загрузкой
    document.getElementById('charts-title').textContent = `Аналитика: ${campaignName}`;
    document.getElementById('charts-loading').style.display = 'block';
    document.getElementById('charts-content').style.display = 'none';
    document.getElementById('charts-modal').classList.remove('hidden');
    
    // Очищаем предыдущие графики
    clearCharts();
    
    // Симулируем загрузку и создаем графики
    setTimeout(() => {
        createCampaignCharts(campaignName);
    }, 500);
}

function hideCharts() {
    document.getElementById('charts-modal').classList.add('hidden');
    clearCharts();
}

function clearCharts() {
    // Уничтожаем существующие графики
    currentCharts.forEach(chart => {
        if (chart) chart.destroy();
    });
    currentCharts = [];
}

function createCampaignCharts(campaignName) {
    // Получаем данные для кампании
    const campaignData = exclusionsData.filter(exclusion => exclusion.campaign_name === campaignName);
    
    if (!campaignData.length) {
        document.getElementById('charts-loading').style.display = 'none';
        document.getElementById('charts-content').innerHTML = '<p>Нет данных для отображения графиков.</p>';
        document.getElementById('charts-content').style.display = 'block';
        return;
    }
    
    // Анализируем временной диапазон
    const dates = campaignData.map(item => new Date(item.excluded_at_gmt));
    const minDate = new Date(Math.min(...dates));
    const maxDate = new Date(Math.max(...dates));
    
    // Группируем данные по скриптам и дням
    const scriptData = {};
    campaignData.forEach(exclusion => {
        const scriptName = exclusion.script_name;
        const date = exclusion.excluded_at_gmt.split(' ')[0]; // Получаем только дату без времени
        
        if (!scriptData[scriptName]) {
            scriptData[scriptName] = {};
        }
        
        if (!scriptData[scriptName][date]) {
            scriptData[scriptName][date] = 0;
        }
        
        scriptData[scriptName][date]++;
    });
    
    // Создаем информационный блок
    const totalExclusions = campaignData.length;
    const uniqueScripts = Object.keys(scriptData).length;
    const daysDifference = Math.ceil((maxDate - minDate) / (1000 * 60 * 60 * 24)) + 1;
    
    const infoHtml = `
        <div>
            <h4>📊 Сводка по кампании: ${campaignName}</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="background: white; padding: 1rem; border-radius: 6px; border: 1px solid #e0e0e0;">
                    <div style="font-size: 0.9rem; color: #6c757d;">Период</div>
                    <div style="font-weight: 600;">${minDate.toLocaleDateString('ru-RU')} - ${maxDate.toLocaleDateString('ru-RU')}</div>
                    <div style="font-size: 0.8rem; color: #6c757d;">${daysDifference} дн.</div>
                </div>
                <div style="background: white; padding: 1rem; border-radius: 6px; border: 1px solid #e0e0e0;">
                    <div style="font-size: 0.9rem; color: #6c757d;">Всего исключений</div>
                    <div style="font-weight: 600; font-size: 1.5rem; color: #dc3545;">${totalExclusions}</div>
                </div>
                <div style="background: white; padding: 1rem; border-radius: 6px; border: 1px solid #e0e0e0;">
                    <div style="font-size: 0.9rem; color: #6c757d;">Активных скриптов</div>
                    <div style="font-weight: 600; font-size: 1.5rem; color: #0984e3;">${uniqueScripts}</div>
                </div>
                <div style="background: white; padding: 1rem; border-radius: 6px; border: 1px solid #e0e0e0;">
                    <div style="font-size: 0.9rem; color: #6c757d;">Среднее в день</div>
                    <div style="font-weight: 600; font-size: 1.5rem; color: #00b894;">${Math.round(totalExclusions / daysDifference)}</div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('charts-info').innerHTML = infoHtml;
    
    // Создаем контейнер для графиков
    const chartsContainer = document.getElementById('charts-container');
    chartsContainer.innerHTML = '';
    
    // Генерируем цвета для скриптов
    const colors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c',
        '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
        '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3',
        '#d299c2', '#fef9d7', '#dee2ff', '#fdf2e9'
    ];
    
    // Создаем объединенный график для всех скриптов
    const combinedChartContainer = document.createElement('div');
    combinedChartContainer.className = 'chart-item';
    combinedChartContainer.innerHTML = `
        <h5 class="chart-title">📈 Общая динамика исключений по дням</h5>
        <div class="chart-canvas">
            <canvas id="combined-chart"></canvas>
        </div>
    `;
    chartsContainer.appendChild(combinedChartContainer);
    
    // Подготавливаем данные для объединенного графика
    const allDates = new Set();
    Object.values(scriptData).forEach(dates => {
        Object.keys(dates).forEach(date => allDates.add(date));
    });
    
    const sortedDates = Array.from(allDates).sort();
    const datasets = [];
    
    Object.keys(scriptData).forEach((scriptName, index) => {
        const data = sortedDates.map(date => scriptData[scriptName][date] || 0);
        datasets.push({
            label: scriptName,
            data: data,
            borderColor: colors[index % colors.length],
            backgroundColor: colors[index % colors.length] + '20',
            fill: false,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6
        });
    });
    
    // Создаем объединенный график
    const combinedCtx = document.getElementById('combined-chart').getContext('2d');
    const combinedChart = new Chart(combinedCtx, {
        type: 'line',
        data: {
            labels: sortedDates.map(date => new Date(date).toLocaleDateString('ru-RU')),
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return 'Дата: ' + context[0].label;
                        },
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y} исключений`;
                        },
                        footer: function(context) {
                            const total = context.reduce((sum, item) => sum + item.parsed.y, 0);
                            return `Всего за день: ${total} исключений`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Количество исключений'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Дата'
                    }
                }
            }
        }
    });
    
    currentCharts.push(combinedChart);
    
    // Создаем отдельные графики для каждого скрипта (если скриптов больше 1)
    if (Object.keys(scriptData).length > 1) {
        Object.keys(scriptData).forEach((scriptName, index) => {
            const scriptChartContainer = document.createElement('div');
            scriptChartContainer.className = 'chart-item';
            const chartId = `script-chart-${index}`;
            scriptChartContainer.innerHTML = `
                <h5 class="chart-title">🔧 ${scriptName}</h5>
                <div class="chart-canvas">
                    <canvas id="${chartId}"></canvas>
                </div>
            `;
            chartsContainer.appendChild(scriptChartContainer);
            
            // Подготавливаем данные для скрипта
            const scriptDates = Object.keys(scriptData[scriptName]).sort();
            const scriptValues = scriptDates.map(date => scriptData[scriptName][date]);
            
            // Создаем график для скрипта
            const scriptCtx = document.getElementById(chartId).getContext('2d');
            const scriptChart = new Chart(scriptCtx, {
                type: 'bar',
                data: {
                    labels: scriptDates.map(date => new Date(date).toLocaleDateString('ru-RU')),
                    datasets: [{
                        label: 'Исключения',
                        data: scriptValues,
                        backgroundColor: colors[index % colors.length] + '60',
                        borderColor: colors[index % colors.length],
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return 'Дата: ' + context[0].label;
                                },
                                label: function(context) {
                                    return `Исключений: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Количество исключений'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Дата'
                            }
                        }
                    }
                }
            });
            
            currentCharts.push(scriptChart);
        });
    }
    
    // Скрываем загрузку и показываем графики
    document.getElementById('charts-loading').style.display = 'none';
    document.getElementById('charts-content').style.display = 'block';
}

// Закрытие модальных окон
document.getElementById('details-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDetails();
    }
});

document.getElementById('charts-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCharts();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDetails();
        hideCharts();
    }
});

// Сохранение состояния фильтров
document.addEventListener('DOMContentLoaded', function() {
    const limitSelect = document.getElementById('limit');
    const savedLimit = localStorage.getItem('placements_limit');
    
    if (savedLimit && !limitSelect.value) {
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('limit')) {
            limitSelect.value = savedLimit;
        }
    }
    
    limitSelect.addEventListener('change', function() {
        localStorage.setItem('placements_limit', this.value);
    });
});

// Функция копирования названия кампании
function copyCampaignName(campaignName) {
    // Используем современный Clipboard API если доступен
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(campaignName).then(() => {
            showCopyNotification('Название кампании скопировано!');
        }).catch(err => {
            console.error('Ошибка копирования: ', err);
            fallbackCopyTextToClipboard(campaignName);
        });
    } else {
        // Fallback для старых браузеров
        fallbackCopyTextToClipboard(campaignName);
    }
}

// Fallback метод копирования для старых браузеров
function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopyNotification('Название кампании скопировано!');
        } else {
            showCopyNotification('Не удалось скопировать', 'error');
        }
    } catch (err) {
        console.error('Fallback: Ошибка копирования', err);
        showCopyNotification('Ошибка копирования', 'error');
    }
    
    document.body.removeChild(textArea);
}

// Показ уведомления о копировании
function showCopyNotification(message, type = 'success') {
    // Удаляем предыдущее уведомление если есть
    const existingNotification = document.querySelector('.copy-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = 'copy-notification';
    notification.textContent = message;
    
    if (type === 'error') {
        notification.style.background = '#dc3545';
    }
    
    document.body.appendChild(notification);
    
    // Показываем уведомление
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Скрываем через 2 секунды
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 2000);
}
</script>