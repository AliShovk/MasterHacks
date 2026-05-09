# План миграции MasterHacks.ru с облачного VPS на обычный VPS

## 📋 Обзор проекта
- **Тип**: TikTok-стиль видеоплатформа
- **Язык**: PHP 7.4+
- **БД**: MySQL/MariaDB
- **Веб-сервер**: Apache
- **Интеграция**: Telegram бот
- **Медиа**: ~60 видео файлов (~200MB)

## 🎯 Цели миграции
1. Перенести все файлы проекта
2. Мигрировать базу данных
3. Настроить веб-сервер
4. Обновить конфигурацию
5. Настроить Telegram бот
6. Проверить работоспособность

## 📦 Что нужно перенести

### 1. Файлы проекта
```
/
├── *.php (все PHP файлы)
├── config/ (конфигурации)
├── api/ (API endpoints)
├── admin/ (админ панель)
├── media/ (видео файлы ~200MB)
├── data/ (кэш и комментарии)
├── sql/ (дампы БД)
└── .htaccess (настройки Apache)
```

### 2. База данных
- База: `masterhacks`
- Таблицы: `authors`, `videos`, `subscriptions`, `comments`
- Дамп: `sql/masterhacks_fixed.sql`

### 3. Конфигурации
- **БД**: localhost/masterhacks/masterhacks/g7azGQhhri_4-M
- **Telegram**: Token `8213110831:AAGDdP7RQVLvV8NG0ouuQWZ_IBR5p9e-MfY`
- **Admin**: ID `5405885462`

## 🔄 Пошаговый план миграции

### Этап 1: Подготовка нового VPS
1. Установить LAMP stack:
   ```bash
   sudo apt update
   sudo apt install apache2 php7.4 mysql-server php-mysql
   ```
2. Установить необходимые PHP модули:
   ```bash
   sudo apt install php-curl php-json php-mbstring php-xml
   ```
3. Настроить Apache для .htaccess
4. Создать базу данных `masterhacks`

### Этап 2: Копирование файлов
1. Архивировать все файлы проекта:
   ```bash
   tar -czf masterhacks_backup.tar.gz /path/to/project
   ```
2. Перенести архив на новый VPS
3. Распаковать в `/var/www/masterhacks.ru/`
4. Установить правильные права:
   ```bash
   sudo chown -R www-data:www-data /var/www/masterhacks.ru
   sudo chmod -R 755 /var/www/masterhacks.ru
   ```

### Этап 3: Миграция базы данных
1. Экспортировать базу с текущего VPS:
   ```bash
   mysqldump -u masterhacks -p masterhacks > masterhacks_export.sql
   ```
2. Импортировать на новом VPS:
   ```bash
   mysql -u root -p masterhacks < masterhacks_export.sql
   ```
3. Создать пользователя БД:
   ```sql
   CREATE USER 'masterhacks'@'localhost' IDENTIFIED BY 'g7azGQhhri_4-M';
   GRANT ALL PRIVILEGES ON masterhacks.* TO 'masterhacks'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Этап 4: Настройка конфигураций
1. Обновить `config/database.php` если нужно
2. Настроить Apache virtual host:
   ```apache
   <VirtualHost *:80>
       ServerName masterhacks.ru
       DocumentRoot /var/www/masterhacks.ru
       AllowOverride All
   </VirtualHost>
   ```
3. Включить mod_rewrite:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

### Этап 5: Настройка Telegram бота
1. Обновить webhook URL:
   ```php
   // В set_webhook.php обновить URL
   $webhook_url = 'https://masterhacks.ru/telegram_bot.php';
   ```
2. Проверить работу бота
3. Убедиться что webhook установлен правильно

### Этап 6: Тестирование
1. Проверить главную страницу
2. Тестировать загрузку видео через бота
3. Проверить админ панель
4. Тестировать комментарии и лайки

## ⚠️ Важные моменты

### Безопасность
1. Изменить пароль БД после миграции
2. Обновить ADMIN_PANEL_KEY
3. Настроить HTTPS (Let's Encrypt)
4. Ограничить доступ к админ панели

### Производительность
1. Настроить кэширование в Apache
2. Оптимизировать MySQL
3. Настроить CDN для медиа файлов (опционально)

### Резервное копирование
1. Создать бэкап перед миграцией
2. Настроить регулярные бэкапы БД
3. Бэкап медиа файлов

## 🚀 После миграции
1. Мониторинг производительности
2. Логирование ошибок
3. Тестирование нагрузки
4. Обновление DNS если меняется IP

## 📞 Контакты для поддержки
- Telegram бот: @mastershacks_bot
- Админ: 5405885462

---
*План создан: 6 марта 2026*
*Проект: MasterHacks.ru*
