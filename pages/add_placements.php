<?php
// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_placements') {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $scriptId = (int)($_POST['script_id'] ?? 0);
            $placementsData = trim($_POST['placements_data'] ?? '');
            $timeData = trim($_POST['time_data'] ?? '');
            
            // Валидация
            if (!$accountId) {
                throw new Exception('Выберите аккаунт');
            }
            
            if (!$scriptId) {
                throw new Exception('Выберите скрипт');
            }
            
            if (empty($placementsData)) {
                throw new Exception('Введите данные о плейсментах');
            }
            
            if (empty($timeData)) {
                throw new Exception('Введите время выполнения');
            }
            
            // Получение информации об аккаунте для конвертации времени
            $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$accountId]);
            if (!$account) {
                throw new Exception('Аккаунт не найден');
            }
            
            // Конвертация времени в GMT
            $excludedAtGmt = $db->convertToGMT($timeData, $account['timezone']);
            
            // Парсинг данных о плейсментах
            $lines = explode("\n", $placementsData);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines, function($line) { return !empty($line); });
            
            $placements = [];
            $lineCount = count($lines);
            
            for ($i = 0; $i < $lineCount; $i += 4) {
                if ($i + 3 < $lineCount) {
                    $campaignName = $lines[$i];
                    $action = $lines[$i + 1];
                    $placementUrl = $lines[$i + 2];
                    $status = $lines[$i + 3];
                    
                    if ($action === 'Placement excluded' && $status === 'Successful') {
                        $placements[] = [
                            'campaign' => $campaignName,
                            'placement' => $placementUrl
                        ];
                    }
                }
            }
            
            if (empty($placements)) {
                throw new Exception('Не найдено успешно исключенных плейсментов в предоставленных данных');
            }
            
            // Сохранение данных в базу с проверкой на дубли
            $savedCount = 0;
            $duplicateCount = 0;
            
            // Генерируем batch_id для всех записей этой пачки
            $batchId = $db->generateBatchId();
            
            foreach ($placements as $placement) {
                $isAdded = $db->addPlacementExclusionSafe(
                    $accountId,
                    $scriptId,
                    $placement['campaign'],
                    $placement['placement'],
                    $excludedAtGmt,
                    $batchId
                );
                
                if ($isAdded) {
                    $savedCount++;
                } else {
                    $duplicateCount++;
                }
            }
            
            // Формирование сообщения о результате
            $messages = [];
            if ($savedCount > 0) {
                $messages[] = "Успешно добавлено {$savedCount} новых исключений плейсментов";
            }
            if ($duplicateCount > 0) {
                $messages[] = "{$duplicateCount} записей были проигнорированы как дубли";
            }
            
            $successMessage = implode('. ', $messages);
            
            // Очистка формы после успешного добавления
            $_POST = [];
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Получение списков для выпадающих меню
$accounts = $db->getAccounts();
$scripts = $db->getScripts();
?>

<div class="page-title">Добавить данные о плейсментах</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if (empty($accounts)): ?>
    <div class="alert alert-info">
        Сначала необходимо <a href="?page=accounts">добавить аккаунты</a> для работы с системой.
    </div>
<?php elseif (empty($scripts)): ?>
    <div class="alert alert-info">
        Сначала необходимо <a href="?page=scripts">добавить скрипты</a> для работы с системой.
    </div>
<?php else: ?>

<div class="form-container">
    <h3>Добавление новых данных</h3>
    
    <form method="POST" data-autosave id="placements-form">
        <input type="hidden" name="action" value="add_placements">
        
        <div class="form-group">
            <label for="account_id">Аккаунт Google Ads *</label>
            <select id="account_id" name="account_id" required>
                <option value="">Выберите аккаунт</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?php echo $account['id']; ?>" 
                            <?php echo (isset($_POST['account_id']) && $_POST['account_id'] == $account['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($account['name']); ?> (<?php echo $account['timezone']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="script_id">Скрипт *</label>
            <select id="script_id" name="script_id" required>
                <option value="">Выберите скрипт</option>
                <?php foreach ($scripts as $script): ?>
                    <option value="<?php echo $script['id']; ?>" 
                            <?php echo (isset($_POST['script_id']) && $_POST['script_id'] == $script['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($script['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="time_data">Время выполнения скрипта *</label>
            <input type="text" 
                   id="time_data" 
                   name="time_data" 
                   required 
                   value="<?php echo htmlspecialchars($_POST['time_data'] ?? ''); ?>"
                   placeholder="Started Jun 25, 2025 4:50:53 AM, ended Jun 25, 2025 4:51:04 AM">
            <small style="color: #666; font-size: 0.9em;">
                Вставьте полную строку времени из отчета скрипта. Система автоматически извлечет время окончания и конвертирует его в GMT.
            </small>
        </div>
        
        <div class="form-group">
            <label for="placements_data">Данные о плейсментах *</label>
            <textarea id="placements_data" 
                      name="placements_data" 
                      required 
                      rows="15"
                      placeholder="FR-EXC-Display-M-location de voiture à tenerife-04.04
Placement excluded
modabright.net
Successful
FR-EXC-Display-M-location de voiture à tenerife-04.04
Placement excluded
mojok.co
Successful"><?php echo htmlspecialchars($_POST['placements_data'] ?? ''); ?></textarea>
            <small style="color: #666; font-size: 0.9em;">
                Вставьте данные из отчета скрипта. Формат: название кампании, действие, URL плейсмента, статус (по 4 строки на каждый исключенный плейсмент).
            </small>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-success">Добавить данные</button>
            <button type="button" class="btn btn-secondary" onclick="previewPlacements()">Предпросмотр</button>
        </div>
    </form>
</div>

<div id="preview-container" class="card hidden">
    <h3>Предпросмотр данных</h3>
    <div id="preview-content"></div>
</div>

<?php endif; ?>

<div class="card">
    <h3>Инструкция по добавлению данных</h3>
    <ol>
        <li><strong>Выберите аккаунт:</strong> Укажите аккаунт Google Ads, для которого выполнялся скрипт</li>
        <li><strong>Выберите скрипт:</strong> Укажите, какой именно скрипт исключил плейсменты</li>
        <li><strong>Время выполнения:</strong> Вставьте полную строку времени из отчета скрипта</li>
        <li><strong>Данные о плейсментах:</strong> Вставьте данные в формате:
            <ul>
                <li>Строка 1: Название кампании</li>
                <li>Строка 2: "Placement excluded"</li>
                <li>Строка 3: URL исключенного плейсмента</li>
                <li>Строка 4: "Successful"</li>
                <li>Повторите для каждого плейсмента</li>
            </ul>
        </li>
    </ol>
</div>

<div class="card">
    <h3>Пример данных</h3>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; font-family: monospace;">
        <strong>Время:</strong><br>
        Started Jun 25, 2025 4:50:53 AM, ended Jun 25, 2025 4:51:04 AM<br><br>
        
        <strong>Данные о плейсментах:</strong><br>
        FR-EXC-Display-M-location de voiture à tenerife-04.04<br>
        Placement excluded<br>
        modabright.net<br>
        Successful<br>
        FR-EXC-Display-M-location de voiture à tenerife-04.04<br>
        Placement excluded<br>
        mojok.co<br>
        Successful
    </div>
</div>

<script>
function previewPlacements() {
    const placementsData = document.getElementById('placements_data').value;
    const timeData = document.getElementById('time_data').value;
    const accountSelect = document.getElementById('account_id');
    const scriptSelect = document.getElementById('script_id');
    
    if (!placementsData.trim()) {
        alert('Введите данные о плейсментах для предпросмотра');
        return;
    }
    
    // Парсинг данных
    const lines = placementsData.split('\n').map(line => line.trim()).filter(line => line);
    const placements = [];
    
    for (let i = 0; i < lines.length; i += 4) {
        if (i + 3 < lines.length) {
            const campaignName = lines[i];
            const action = lines[i + 1];
            const placementUrl = lines[i + 2];
            const status = lines[i + 3];
            
            if (action === 'Placement excluded' && status === 'Successful') {
                placements.push({
                    campaign: campaignName,
                    placement: placementUrl
                });
            }
        }
    }
    
    // Отображение предпросмотра
    const previewContainer = document.getElementById('preview-container');
    const previewContent = document.getElementById('preview-content');
    
    let html = '';
    
    if (accountSelect.value) {
        html += `<p><strong>Аккаунт:</strong> ${accountSelect.options[accountSelect.selectedIndex].text}</p>`;
    }
    
    if (scriptSelect.value) {
        html += `<p><strong>Скрипт:</strong> ${scriptSelect.options[scriptSelect.selectedIndex].text}</p>`;
    }
    
    if (timeData) {
        html += `<p><strong>Время:</strong> ${timeData}</p>`;
    }
    
    html += `<p><strong>Найдено исключений:</strong> ${placements.length}</p>`;
    
    if (placements.length > 0) {
        html += '<div class="table-container"><table><thead><tr><th>Кампания</th><th>Плейсмент</th></tr></thead><tbody>';
        placements.forEach(placement => {
            html += `<tr><td>${placement.campaign}</td><td>${placement.placement}</td></tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html += '<p style="color: #ea4335;">Не найдено успешно исключенных плейсментов в предоставленных данных.</p>';
    }
    
    previewContent.innerHTML = html;
    previewContainer.classList.remove('hidden');
    
    // Прокрутка к предпросмотру
    previewContainer.scrollIntoView({ behavior: 'smooth' });
}

// Функциональность запоминания выбранных полей
document.addEventListener('DOMContentLoaded', function() {
    const accountSelect = document.getElementById('account_id');
    const scriptSelect = document.getElementById('script_id');
    
    // Восстановление сохраненных значений при загрузке страницы
    const savedAccountId = localStorage.getItem('placements_last_account_id');
    const savedScriptId = localStorage.getItem('placements_last_script_id');
    
    // Восстанавливаем аккаунт только если форма не была отправлена (нет выбранного значения)
    if (savedAccountId && !accountSelect.value) {
        // Проверяем, что сохраненное значение все еще существует в списке
        const accountOption = accountSelect.querySelector(`option[value="${savedAccountId}"]`);
        if (accountOption) {
            accountSelect.value = savedAccountId;
        }
    }
    
    // Восстанавливаем скрипт только если форма не была отправлена (нет выбранного значения)
    if (savedScriptId && !scriptSelect.value) {
        // Проверяем, что сохраненное значение все еще существует в списке
        const scriptOption = scriptSelect.querySelector(`option[value="${savedScriptId}"]`);
        if (scriptOption) {
            scriptSelect.value = savedScriptId;
        }
    }
    
    // Сохранение выбранных значений при изменении
    accountSelect.addEventListener('change', function() {
        if (this.value) {
            localStorage.setItem('placements_last_account_id', this.value);
        }
    });
    
    scriptSelect.addEventListener('change', function() {
        if (this.value) {
            localStorage.setItem('placements_last_script_id', this.value);
        }
    });
    
    // Очистка сохраненных значений при успешной отправке формы
    const form = document.getElementById('placements-form');
    if (form) {
        form.addEventListener('submit', function() {
            // Сохраняем текущие выбранные значения перед отправкой
            if (accountSelect.value) {
                localStorage.setItem('placements_last_account_id', accountSelect.value);
            }
            if (scriptSelect.value) {
                localStorage.setItem('placements_last_script_id', scriptSelect.value);
            }
        });
    }
});
</script>

