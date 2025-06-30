Также вот краткая версия для быстрого копирования в GitHub:

**README.md для репозитория:**

```markdown
# Google Ads Placements Manager

Веб-приложение для управления исключениями плейсментов в Google Ads с поддержкой MySQL.

## Установка

1. **Клонирование:** `git clone https://github.com/odindoma/placements-manager.git`
2. **База данных:** Создайте MySQL базу и выполните `database_mysql.sql`
3. **Конфигурация:** `cp .env.example .env` и отредактируйте настройки БД
4. **Запуск:** `php -S localhost:8000`

## Требования

- PHP 7.4+ (PDO MySQL)
- MySQL 5.7+ / MariaDB 10.2+

## Возможности

- Управление Google Ads аккаунтами и скриптами
- Импорт и анализ данных плейсментов
- Пакетная обработка и статистика
- Современный веб-интерфейс

## Миграция с SQLite

Используйте `php migrate_to_mysql.php` для переноса данных.