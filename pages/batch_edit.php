<?php
// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_batch') {
            $batchId = trim($_POST['batch_id'] ?? '');
            $newAccountId = (int)($_POST['new_account_id'] ?? 0);
            $newScriptId = (int)($_POST['new_script_id'] ?? 0);
            
            // Валидация
            if (empty($batchId)) {
                throw new Exception('Не указан batch_id');
            }
            
            if (!$newAccountId && !$newScriptId) {
                throw new Exception('Выберите новый аккаунт или скрипт для обновления');
            }
            
            // Получаем записи для проверки существования batch_id
            $existingRecords = $db->getRecordsByBatchId($batchId);
            if (empty($existingRecords)) {
                throw new Exception('Записи с указанным batch_id не найдены');
            }
            
            $updatedCount = count($existingRecords);
            $updateMessages = [];
            
            // Обновляем аккаунт если указан
            if ($newAccountId) {
                $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$newAccountId]);
                if (!$account) {
                    throw new Exception('Выбранный аккаунт не найден');
                }
                $db->updateBatchAccount($batchId, $newAccountId);
                $updateMessages[] = "аккаунт изменен на '{$account['name']}'";
            }
            
            // Обновляем скрипт если указан
            if ($newScriptId) {
                $script = $db->fetchOne("SELECT * FROM scripts WHERE id = ?", [$newScriptId]);
                if (!$script) {
                    throw new Exception('Выбранный скрипт не найден');
                }
                $db->updateBatchScript($batchId, $newScriptId);
                $updateMessages[] = "скрипт изменен на '{$script['name']}'";
            }
            
            $successMessage = "Успешно обновлено {$updatedCount} записей: " . implode(', ', $updateMessages);
            
            // Очистка формы после успешного обновления
            $_POST = [];
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Получение списков для выпадающих меню
$accounts = $db->getAccounts();
$scripts = $db->getScripts();
$batchList = $db->getBatchList(50); // Последние 50 пачек
?>

<div class="page-title">Массовое редактирование</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="card">
    <h3>Информация</h3>
    <p>Эта функция позволяет массово изменить аккаунт или скрипт для группы записей, которые были добавлены одновременно.</p>
    <p><strong>Принцип работы:</strong> Когда вы добавляете данные о плейсментах, все записи получают одинаковый batch_id. 
    Это позволяет редактировать их все сразу, если была допущена ошибка в выборе аккаунта или скрипта.</p>
</div>

<?php if (empty($accounts) || empty($scripts)): ?>
    <div class="alert alert-info">
        Для работы с массовым редактированием необходимо иметь 
        <a href="?page=accounts">аккаунты</a> и <a href="?page=scripts">скрипты</a> в системе.
    </div>
<?php elseif (empty($batchList)): ?>
    <div class="alert alert-info">
        Нет данных для массового редактирования. Сначала <a href="?page=add_placements">добавьте данные о плейсментах</a>.
    </div>
<?php else: ?>

<div class="form-container">
    <h3>Выбор пачки для редактирования</h3>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Batch ID</th>
                    <th>Количество записей</th>
                    <th>Текущий аккаунт</th>
                    <th>Текущий скрипт</th>
                    <th>Дата добавления</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batchList as $batch): ?>
                    <tr>
                        <td>
                            <code style="font-size: 0.8em;"><?php echo htmlspecialchars(substr($batch['batch_id'], 0, 20)) . '...'; ?></code>
                        </td>
                        <td><?php echo $batch['record_count']; ?></td>
                        <td><?php echo htmlspecialchars($batch['account_name']); ?></td>
                        <td><?php echo htmlspecialchars($batch['script_name']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($batch['first_created'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="openEditModal('<?php echo htmlspecialchars($batch['batch_id']); ?>', 
                                                          '<?php echo htmlspecialchars($batch['account_name']); ?>', 
                                                          '<?php echo htmlspecialchars($batch['script_name']); ?>', 
                                                          <?php echo $batch['record_count']; ?>)">
                                Редактировать
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно для редактирования -->
<div id="edit-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Массовое редактирование</h3>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="batch-info" class="alert alert-info"></div>
            
            <form method="POST" id="batch-edit-form">
                <input type="hidden" name="action" value="update_batch">
                <input type="hidden" name="batch_id" id="edit-batch-id">
                
                <div class="form-group">
                    <label for="new_account_id">Новый аккаунт (оставьте пустым, если не нужно менять)</label>
                    <select id="new_account_id" name="new_account_id">
                        <option value="">-- Не менять аккаунт --</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['name']); ?> (<?php echo $account['timezone']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_script_id">Новый скрипт (оставьте пустым, если не нужно менять)</label>
                    <select id="new_script_id" name="new_script_id">
                        <option value="">-- Не менять скрипт --</option>
                        <?php foreach ($scripts as $script): ?>
                            <option value="<?php echo $script['id']; ?>">
                                <?php echo htmlspecialchars($script['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-success">Применить изменения</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Отмена</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
function openEditModal(batchId, currentAccount, currentScript, recordCount) {
    const modal = document.getElementById('edit-modal');
    const batchInfo = document.getElementById('batch-info');
    const batchIdInput = document.getElementById('edit-batch-id');
    
    // Заполняем информацию о пачке
    batchInfo.innerHTML = `
        <strong>Редактирование пачки:</strong><br>
        Количество записей: ${recordCount}<br>
        Текущий аккаунт: ${currentAccount}<br>
        Текущий скрипт: ${currentScript}
    `;
    
    // Устанавливаем batch_id
    batchIdInput.value = batchId;
    
    // Сбрасываем форму
    document.getElementById('new_account_id').value = '';
    document.getElementById('new_script_id').value = '';
    
    // Показываем модальное окно
    modal.classList.remove('hidden');
}

function closeEditModal() {
    const modal = document.getElementById('edit-modal');
    modal.classList.add('hidden');
}

// Закрытие модального окна при клике вне его
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Закрытие модального окна при нажатии Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

// Валидация формы
document.getElementById('batch-edit-form').addEventListener('submit', function(e) {
    const accountSelect = document.getElementById('new_account_id');
    const scriptSelect = document.getElementById('new_script_id');
    
    if (!accountSelect.value && !scriptSelect.value) {
        e.preventDefault();
        alert('Выберите новый аккаунт или скрипт для обновления');
        return false;
    }
    
    const confirmMessage = 'Вы уверены, что хотите применить изменения? Это действие нельзя отменить.';
    if (!confirm(confirmMessage)) {
        e.preventDefault();
        return false;
    }
});
</script>


