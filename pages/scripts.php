<?php
// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Название скрипта не может быть пустым');
                }
                
                $db->addScript($name, $description);
                $successMessage = 'Скрипт успешно добавлен';
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Название скрипта не может быть пустым');
                }
                
                $db->updateScript($id, $name, $description);
                $successMessage = 'Скрипт успешно обновлен';
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $db->deleteScript($id);
                $successMessage = 'Скрипт успешно удален';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Получение данных для редактирования
$editScript = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editScript = $db->fetchOne("SELECT * FROM scripts WHERE id = ?", [$editId]);
}

// Получение списка скриптов
$scripts = $db->getScripts();
?>

<div class="page-title">Управление скриптами</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="form-container">
    <h3><?php echo $editScript ? 'Редактировать скрипт' : 'Добавить новый скрипт'; ?></h3>
    
    <form method="POST" data-autosave id="script-form">
        <input type="hidden" name="action" value="<?php echo $editScript ? 'edit' : 'add'; ?>">
        <?php if ($editScript): ?>
            <input type="hidden" name="id" value="<?php echo $editScript['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="name">Название скрипта *</label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   required 
                   value="<?php echo htmlspecialchars($editScript['name'] ?? ''); ?>"
                   placeholder="Например: Скрипт исключения низкокачественных сайтов">
        </div>
        
        <div class="form-group">
            <label for="description">Описание правил работы скрипта</label>
            <textarea id="description" 
                      name="description" 
                      rows="4"
                      placeholder="Опишите, по каким правилам работает этот скрипт. Например: Исключает плейсменты с CTR менее 0.5% и конверсией менее 1%"><?php echo htmlspecialchars($editScript['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-success">
                <?php echo $editScript ? 'Обновить скрипт' : 'Добавить скрипт'; ?>
            </button>
            <?php if ($editScript): ?>
                <a href="?page=scripts" class="btn btn-secondary">Отмена</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($scripts)): ?>
<div class="card">
    <h3>Список скриптов</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scripts as $script): ?>
                <tr>
                    <td><?php echo $script['id']; ?></td>
                    <td><?php echo htmlspecialchars($script['name']); ?></td>
                    <td>
                        <?php 
                        $description = htmlspecialchars($script['description'] ?? '');
                        echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                        ?>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($script['created_at'])); ?></td>
                    <td>
                        <a href="?page=scripts&edit=<?php echo $script['id']; ?>" class="btn btn-secondary">Редактировать</a>
                        
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Вы уверены, что хотите удалить этот скрипт? Все связанные данные о плейсментах также будут удалены.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $script['id']; ?>">
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
    <h3>Нет скриптов</h3>
    <p>Пока не добавлено ни одного скрипта. Добавьте первый скрипт, используя форму выше.</p>
</div>
<?php endif; ?>

<div class="card">
    <h3>Примеры скриптов и их описаний</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px;">
            <h4>Скрипт по CTR</h4>
            <p><strong>Название:</strong> Исключение по низкому CTR</p>
            <p><strong>Описание:</strong> Исключает плейсменты с CTR менее 0.3% за последние 30 дней при минимум 1000 показов</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px;">
            <h4>Скрипт по конверсиям</h4>
            <p><strong>Название:</strong> Исключение по низкой конверсии</p>
            <p><strong>Описание:</strong> Исключает плейсменты с конверсией менее 1% за последние 14 дней при минимум 100 кликов</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px;">
            <h4>Скрипт по CPA</h4>
            <p><strong>Название:</strong> Исключение по высокой CPA</p>
            <p><strong>Описание:</strong> Исключает плейсменты с CPA выше целевой на 50% за последние 7 дней</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px;">
            <h4>Скрипт по качеству трафика</h4>
            <p><strong>Название:</strong> Исключение низкокачественного трафика</p>
            <p><strong>Описание:</strong> Исключает плейсменты с высоким показателем отказов (>80%) и низким временем на сайте (<30 сек)</p>
        </div>
    </div>
</div>

