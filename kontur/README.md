# К.О.Н.Т.У.Р. — Архивная База Данных (Full-Stack)

Веб-приложение вымышленной советской организации по контролю аномалий.
Стек: **PHP 8 + Apache + MariaDB/MySQL**, фронтенд — vanilla JS (без фреймворков).

---

## Архитектура

```
kontur/
├── public/              ← DocumentRoot Apache (ТОЛЬКО это доступно снаружи)
│   ├── index.html
│   ├── .htaccess
│   ├── css/             ← 5 модулей стилей
│   └── js/              ← модули: api, state, auth, articles,
│                          moderation, modal, editor, ui, window-manager,
│                          toast, loader, context-menu
├── api/                 ← PHP backend (ВНЕ docroot — недоступен напрямую)
│   ├── config.php       ← настройки БД (секреты!)
│   ├── db.php           ← PDO-подключение
│   ├── helpers.php      ← JSON-ответы, валидация, конфиг
│   ├── auth.php         ← сессии, RBAC (проверка ролей)
│   ├── index.php        ← front controller (роутер)
│   └── routes/          ← auth.php, articles.php, sections.php
├── sql/schema.sql       ← схема БД + сид-данные
└── README.md
```

**Ключевой принцип безопасности:** роль пользователя хранится и проверяется
только на сервере. Клиент не может её подделать.

---

## Установка

### 1. База данных

```bash
mysql -u root -p < sql/schema.sql
```

Создайте отдельного пользователя БД (не используйте root в приложении):

```sql
CREATE USER 'kontur_app'@'localhost' IDENTIFIED BY 'ваш_надёжный_пароль';
GRANT SELECT, INSERT, UPDATE, DELETE ON kontur.* TO 'kontur_app'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Конфигурация

Отредактируйте `api/config.php` — впишите данные пользователя БД:

```php
'user' => 'kontur_app',
'pass' => 'ваш_надёжный_пароль',
```

В продакшене поставьте `'debug' => false`.

### 3. Apache

Настройте виртуальный хост так, чтобы:
- **DocumentRoot** указывал на `public/`
- Папка `api/` была доступна как `/api` через Alias, но лежала вне docroot

```apache
<VirtualHost *:80>
    ServerName kontur.local
    DocumentRoot /path/to/kontur/public

    Alias /api /path/to/kontur/api
    <Directory /path/to/kontur/api>
        Require all granted
        # Разрешаем выполнение только index.php как точки входа
        <FilesMatch "^(?!index\.php).*\.php$">
            Require all denied
        </FilesMatch>
    </Directory>

    <Directory /path/to/kontur/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Роутинг `/api/*` → `api/index.php` настраивается через RewriteRule.
Добавьте в конфиг Apache (или в `api/.htaccess`, если api под docroot):

```apache
RewriteEngine On
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [QSA,L]
```

Включите `mod_rewrite`:
```bash
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

### 4. Проверка

Откройте `http://kontur.local/`. Должен появиться экран входа.

---

## Тестовые учётные записи

| Логин       | Пароль    | Роль        |
|-------------|-----------|-------------|
| admin       | admin123  | Администратор |
| moder       | moder123  | Модератор   |
| archivist   | user123   | Участник    |
| liminal_ch  | user123   | Участник    |

**Смените эти пароли перед публикацией!**

---

## API (кратко)

| Метод | Путь | Доступ | Описание |
|-------|------|--------|----------|
| POST | `/api/auth/register` | все | регистрация |
| POST | `/api/auth/login` | все | вход |
| POST | `/api/auth/logout` | все | выход |
| GET | `/api/auth/me` | все | текущий профиль |
| GET | `/api/articles?status=&tag=` | все* | список |
| GET | `/api/articles/{id}` | все* | одна статья с телом |
| POST | `/api/articles` | user+ | создать (→ pending) |
| PATCH | `/api/articles/{id}` | mod+ | редактировать |
| DELETE | `/api/articles/{id}` | admin | удалить |
| POST | `/api/articles/{id}/moderate` | mod+ | approve/reject |
| POST | `/api/articles/{id}/rate` | user+ | оценить 1–5 |
| GET | `/api/sections` | все | разделы |
| POST | `/api/sections` | admin | добавить раздел |
| DELETE | `/api/sections/{id}` | admin | удалить раздел |

\* — гость видит только `approved`; pending/rejected требуют роли модератора.

---

## Возможности

- Win95-эстетика: boot-экран входа, окна с drag&drop, taskbar, контекстные меню
- Роли: гость / участник / модератор / администратор (RBAC на сервере)
- Две ветки званий: за статьи и за творчество (отображаются обе)
- Модерация: очередь → approve/reject → архив отклонённых (для админа)
- Оценки статей (одна на пользователя, UPSERT)
- Редактор с разметкой и превью
- Поиск, фуллскрин (F11), задел под систему фракций

---

## Безопасность: что заложено

- **Пароли:** `password_hash()` (bcrypt), никогда не хранятся в открытом виде
- **SQL-инъекции:** PDO prepared statements везде, эмуляция отключена
- **Сессии:** httponly-cookie, `session_regenerate_id()` при логине
- **RBAC:** роль из БД, проверяется на каждом запросе
- **User enumeration:** одинаковая ошибка для неверного логина и пароля
- **Файлы конфигурации** вне docroot

### Что стоит добавить для боевого продакшена

- HTTPS + `secure` флаг на cookie
- CSRF-токены для мутирующих запросов
- Rate limiting на логин/регистрацию
- Логирование действий модерации
