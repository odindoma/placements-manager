/**
 * Основные JavaScript функции для Google Ads Placements Manager
 */

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

/**
 * Инициализация страницы
 */
function initializePage() {
    // Инициализация форм
    initializeForms();
    
    // Инициализация модальных окон
    initializeModals();
    
    // Автосохранение данных форм
    initializeAutoSave();
}

/**
 * Инициализация форм
 */
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Добавление обработчика отправки
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                return false;
            }
            
            // Показать индикатор загрузки
            showLoading(this);
        });
        
        // Автоматическое изменение размера textarea
        const textareas = form.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', autoResizeTextarea);
        });
    });
}

/**
 * Валидация формы
 */
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    // Удаление предыдущих ошибок
    clearFormErrors(form);
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'Это поле обязательно для заполнения');
            isValid = false;
        }
    });
    
    // Специальная валидация для email
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            showFieldError(field, 'Введите корректный email адрес');
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Проверка валидности email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Показать ошибку поля
 */
function showFieldError(field, message) {
    field.classList.add('error');
    
    // Создание элемента ошибки
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    
    // Вставка после поля
    field.parentNode.insertBefore(errorElement, field.nextSibling);
}

/**
 * Очистка ошибок формы
 */
function clearFormErrors(form) {
    // Удаление классов ошибок
    const errorFields = form.querySelectorAll('.error');
    errorFields.forEach(field => field.classList.remove('error'));
    
    // Удаление сообщений об ошибках
    const errorMessages = form.querySelectorAll('.field-error');
    errorMessages.forEach(message => message.remove());
}

/**
 * Показать индикатор загрузки
 */
function showLoading(element) {
    element.classList.add('loading');
    
    const submitButton = element.querySelector('button[type="submit"], input[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.dataset.originalText = submitButton.textContent;
        submitButton.textContent = 'Загрузка...';
    }
}

/**
 * Скрыть индикатор загрузки
 */
function hideLoading(element) {
    element.classList.remove('loading');
    
    const submitButton = element.querySelector('button[type="submit"], input[type="submit"]');
    if (submitButton) {
        submitButton.disabled = false;
        if (submitButton.dataset.originalText) {
            submitButton.textContent = submitButton.dataset.originalText;
        }
    }
}

/**
 * Автоматическое изменение размера textarea
 */
function autoResizeTextarea(e) {
    const textarea = e.target;
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

/**
 * Инициализация модальных окон
 */
function initializeModals() {
    // Обработчики для кнопок удаления
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.dataset.message || 'Вы уверены, что хотите удалить этот элемент?';
            if (confirm(message)) {
                window.location.href = this.href;
            }
        });
    });
}

/**
 * Автосохранение данных форм
 */
function initializeAutoSave() {
    const forms = document.querySelectorAll('form[data-autosave]');
    
    forms.forEach(form => {
        const formId = form.id || 'default-form';
        
        // Загрузка сохраненных данных
        loadFormData(form, formId);
        
        // Сохранение при изменении
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                saveFormData(form, formId);
            });
        });
        
        // Очистка при успешной отправке
        form.addEventListener('submit', function() {
            clearSavedFormData(formId);
        });
    });
}

/**
 * Сохранение данных формы в localStorage
 */
function saveFormData(form, formId) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    localStorage.setItem('form_' + formId, JSON.stringify(data));
}

/**
 * Загрузка данных формы из localStorage
 */
function loadFormData(form, formId) {
    const savedData = localStorage.getItem('form_' + formId);
    
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            
            for (let [key, value] of Object.entries(data)) {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = value;
                    
                    // Автоматическое изменение размера для textarea
                    if (field.tagName === 'TEXTAREA') {
                        autoResizeTextarea({ target: field });
                    }
                }
            }
        } catch (e) {
            console.error('Ошибка загрузки сохраненных данных формы:', e);
        }
    }
}

/**
 * Очистка сохраненных данных формы
 */
function clearSavedFormData(formId) {
    localStorage.removeItem('form_' + formId);
}

/**
 * Показать уведомление
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    
    // Вставка в начало main
    const main = document.querySelector('main');
    main.insertBefore(notification, main.firstChild);
    
    // Автоматическое скрытие через 5 секунд
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

/**
 * Парсинг данных о плейсментах
 */
function parsePlacementData(text) {
    const lines = text.split('\n').map(line => line.trim()).filter(line => line);
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
    
    return placements;
}

/**
 * Форматирование даты для отображения
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('ru-RU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Экспорт данных в CSV
 */
function exportToCSV(data, filename) {
    const csvContent = "data:text/csv;charset=utf-8," 
        + data.map(row => row.join(",")).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

