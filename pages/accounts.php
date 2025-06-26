<?php
// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $timezone = $_POST['timezone'] ?? 'GMT+00:00';
                
                if (empty($name)) {
                    throw new Exception('Название аккаунта не может быть пустым');
                }
                
                $db->addAccount($name, $timezone);
                $successMessage = 'Аккаунт успешно добавлен';
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $timezone = $_POST['timezone'] ?? 'GMT+00:00';
                
                if (empty($name)) {
                    throw new Exception('Название аккаунта не может быть пустым');
                }
                
                $db->updateAccount($id, $name, $timezone);
                $successMessage = 'Аккаунт успешно обновлен';
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $db->deleteAccount($id);
                $successMessage = 'Аккаунт успешно удален';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Получение данных для редактирования
$editAccount = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editAccount = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$editId]);
}

// Получение списка аккаунтов
$accounts = $db->getAccounts();

// Список временных зон
$timezones = [
    'GMT+00:00' => '(GMT +00:00) GMT',
    'GMT+01:00' => '(GMT +01:00) Europe/London',
    'GMT+02:00' => '(GMT +02:00) Europe/Berlin',
    'GMT+03:00' => '(GMT +03:00) Europe/Moscow',
    'GMT+04:00' => '(GMT +04:00) Asia/Dubai',
    'GMT+05:00' => '(GMT +05:00) Asia/Karachi',
    'GMT+06:00' => '(GMT +06:00) Asia/Almaty',
    'GMT+07:00' => '(GMT +07:00) Asia/Bangkok',
    'GMT+08:00' => '(GMT +08:00) Asia/Shanghai',
    'GMT+09:00' => '(GMT +09:00) Asia/Tokyo',
    'GMT+10:00' => '(GMT +10:00) Australia/Sydney',
    'GMT-05:00' => '(GMT -05:00) America/New_York',
    'GMT-06:00' => '(GMT -06:00) America/Chicago',
    'GMT-07:00' => '(GMT -07:00) America/Denver',
    'GMT-08:00' => '(GMT -08:00) America/Los_Angeles'
];
?>

<div class="page-title">Управление аккаунтами Google Ads</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="form-container">
    <h3><?php echo $editAccount ? 'Редактировать аккаунт' : 'Добавить новый аккаунт'; ?></h3>
    
    <form method="POST" data-autosave id="account-form">
        <input type="hidden" name="action" value="<?php echo $editAccount ? 'edit' : 'add'; ?>">
        <?php if ($editAccount): ?>
            <input type="hidden" name="id" value="<?php echo $editAccount['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="name">Название аккаунта *</label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   required 
                   value="<?php echo htmlspecialchars($editAccount['name'] ?? ''); ?>"
                   placeholder="Например: Основной аккаунт RU">
        </div>
        
        <div class="form-group">
            <label for="timezone">Временная зона *</label>
            <select id="timezone" name="timezone" required>
                <?php foreach ($timezones as $value => $label): ?>
                    <option value="<?php echo $value; ?>" 
                            <?php echo ($editAccount && $editAccount['timezone'] === $value) ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-success">
                <?php echo $editAccount ? 'Обновить аккаунт' : 'Добавить аккаунт'; ?>
            </button>
            <?php if ($editAccount): ?>
                <a href="?page=accounts" class="btn btn-secondary">Отмена</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($accounts)): ?>
<div class="card">
    <h3>Список аккаунтов</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Временная зона</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><?php echo $account['id']; ?></td>
                    <td><?php echo htmlspecialchars($account['name']); ?></td>
                    <td><?php echo htmlspecialchars($timezones[$account['timezone']] ?? $account['timezone']); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($account['created_at'])); ?></td>
                    <td>
                        <a href="?page=accounts&edit=<?php echo $account['id']; ?>" class="btn btn-secondary">Редактировать</a>
                        
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Вы уверены, что хотите удалить этот аккаунт? Все связанные данные о плейсментах также будут удалены.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                            <button type="submit" class="btn btn-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <h3>Нет аккаунтов</h3>
    <p>Пока не добавлено ни одного аккаунта Google Ads. Добавьте первый аккаунт, используя форму выше.</p>
</div>
<?php endif; ?>

<div class="card">
    <h3>Справка по временным зонам</h3>
    <p>Выберите временную зону, соответствующую настройкам вашего аккаунта Google Ads. Это важно для корректного преобразования времени исключения плейсментов в GMT.</p>
    <p><strong>Важно:</strong> Время в отчетах скриптов должно соответствовать временной зоне аккаунта.</p>
</div>

