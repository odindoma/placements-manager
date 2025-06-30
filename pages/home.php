<?php
// Получение статистики
$accountsCount = count($db->getAccounts());
$scriptsCount = count($db->getScripts());

// Получение последних исключений плейсментов
$recentExclusions = $db->getPlacementExclusions(null, null, 10);
?>

<div class="page-title">Главная страница</div>

<div class="card">
    <h3>Добро пожаловать в систему управления плейсментами Google Ads</h3>
    <p>Эта система позволяет отслеживать и анализировать исключения плейсментов, выполненные различными скриптами в ваших рекламных аккаунтах Google Ads.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card">
        <h3>📊 Статистика</h3>
        <p><strong>Аккаунтов:</strong> <?php echo $accountsCount; ?></p>
        <p><strong>Скриптов:</strong> <?php echo $scriptsCount; ?></p>
        <p><strong>Всего исключений:</strong> <?php echo count($db->getPlacementExclusions()); ?></p>
    </div>
    
    <div class="card">
        <h3>🚀 Быстрые действия</h3>
        <a href="?page=add_placements" class="btn btn-success">Добавить данные</a>
        <a href="?page=view_placements" class="btn">Просмотр данных</a>
        <a href="?page=batch_edit" class="btn btn-warning">Массовое редактирование</a>
    </div>
    
    <div class="card">
        <h3>⚙️ Настройки</h3>
        <a href="?page=accounts" class="btn btn-secondary">Управление аккаунтами</a>
        <a href="?page=scripts" class="btn btn-secondary">Управление скриптами</a>
    </div>
</div>

<?php if (!empty($recentExclusions)): ?>
<div class="card">
    <h3>Последние исключения плейсментов</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Дата/время (GMT)</th>
                    <th>Аккаунт</th>
                    <th>Скрипт</th>
                    <th>Кампания</th>
                    <th>Плейсмент</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentExclusions as $exclusion): ?>
                <tr>
                    <td><?php echo date('d.m.Y H:i', strtotime($exclusion['excluded_at_gmt'])); ?></td>
                    <td><?php echo htmlspecialchars($exclusion['account_name']); ?></td>
                    <td><?php echo htmlspecialchars($exclusion['script_name']); ?></td>
                    <td><?php echo htmlspecialchars($exclusion['campaign_name']); ?></td>
                    <td><?php echo htmlspecialchars($exclusion['placement_url']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="text-center">
        <a href="?page=view_placements" class="btn">Посмотреть все данные</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <h3>Нет данных</h3>
    <p>Пока не добавлено ни одного исключения плейсментов.</p>
    <p>Начните с настройки аккаунтов и скриптов, а затем добавьте данные о плейсментах.</p>
    <div class="mt-2">
        <a href="?page=accounts" class="btn">Настроить аккаунты</a>
        <a href="?page=scripts" class="btn">Настроить скрипты</a>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3>Инструкция по использованию</h3>
    <ol>
        <li><strong>Настройте аккаунты:</strong> Добавьте ваши Google Ads аккаунты с указанием временной зоны</li>
        <li><strong>Настройте скрипты:</strong> Добавьте информацию о скриптах, которые исключают плейсменты</li>
        <li><strong>Добавляйте данные:</strong> Регулярно добавляйте данные об исключенных плейсментах</li>
        <li><strong>Анализируйте:</strong> Используйте страницу просмотра для анализа эффективности скриптов</li>
    </ol>
</div>

