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

// Получение списков для фильтров
$accounts = $db->getAccounts();
$scripts = $db->getScripts();

// Получение списка кампаний
$campaignsSql = "
    SELECT DISTINCT campaign_name, COUNT(*) as count
    FROM placement_exclusions 
    GROUP BY campaign_name 
    ORDER BY campaign_name ASC
";
$campaigns = $db->fetchAll($campaignsSql);

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
                <label for="campaign">Кампания</label>
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
                <label for="placement">Плейсмент</label>
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
                <label for="date_from">Дата с</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">Дата по</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
            </div>
            
            <div class="filter-group">
                <label for="limit">Лимит записей</label>
                <select id="limit" name="limit">
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                    <option value="1000" <?php echo $limit == 1000 ? 'selected' : ''; ?>>1000</option>
                    <option value="5000" <?php echo $limit == 5000 ? 'selected' : ''; ?>>5000</option>
                    <option value="0" <?php echo $limit == 0 ? 'selected' : ''; ?>>Без лимита</option>
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
            <div class="campaign-header" onclick="toggleCampaign('<?php echo md5($campaignName); ?>')">
                <div class="campaign-info">
                    <h3>
                        <span class="toggle-icon">▼</span>
                        📊 <?php echo htmlspecialchars($campaignName); ?>
                    </h3>
                    <span class="campaign-stats">
                        Исключений: <?php echo $campaignData['total_exclusions']; ?> | 
                        Скриптов: <?php echo count($campaignData['scripts']); ?>
                    </span>
                </div>
            </div>
            
            <div class="campaign-content" id="campaign-<?php echo md5($campaignName); ?>">
                <?php foreach ($campaignData['scripts'] as $scriptData): ?>
                    <div class="script-group">
                        <div class="script-header" onclick="toggleScript('<?php echo md5($campaignName . $scriptData['script_id']); ?>')">
                            <div class="script-info">
                                <h4>
                                    <span class="toggle-icon">▼</span>
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
                        
                        <div class="script-content" id="script-<?php echo md5($campaignName . $scriptData['script_id']); ?>">
                            <div class="placements-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th width="20%">Дата/время (GMT)</th>
                                            <th width="50%">Плейсмент</th>
                                            <th width="20%">Аккаунт</th>
                                            <th width="10%">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scriptData['placements'] as $placement): ?>
                                            <tr>
                                                <td>
                                                    <div class="datetime">
                                                        <?php echo date('d.m.Y', strtotime($placement['excluded_at_gmt'])); ?>
                                                    </div>
                                                    <div class="time">
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
                                                    <div class="account-info">
                                                        <?php echo htmlspecialchars($placement['account_name']); ?>
                                                        <small><?php echo htmlspecialchars($placement['account_timezone']); ?></small>
                                                    </div>
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
    cursor: pointer;
    user-select: none;
    transition: background 0.3s ease;
}

.campaign-header:hover {
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

/* Адаптивность */
@media (max-width: 768px) {
    .campaign-header, .script-header {
        padding: 0.75rem;
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
}
</style>

<script>
// Данные для модального окна
const exclusionsData = <?php echo json_encode($exclusions); ?>;

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
    document.querySelectorAll('.campaign-content, .script-content').forEach(content => {
        content.classList.remove('collapsed');
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.remove('rotated');
    });
}

function collapseAll() {
    document.querySelectorAll('.campaign-content, .script-content').forEach(content => {
        content.classList.add('collapsed');
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.add('rotated');
    });
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

// Закрытие модального окна
document.getElementById('details-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDetails();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDetails();
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
</script>
