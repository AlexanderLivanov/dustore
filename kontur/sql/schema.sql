-- ============================================================
-- K.O.N.T.U.R. — Схема базы данных
-- MySQL / MariaDB
-- ============================================================
-- Создание: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS kontur
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kontur;

-- Порядок DROP важен из-за внешних ключей (сначала зависимые)
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS users;

-- ── ПОЛЬЗОВАТЕЛИ ─────────────────────────────────────────
CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(24)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  -- Роль хранится ТОЛЬКО в БД. Клиент не может её менять.
  role           ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
  -- Два независимых счётчика для двух веток званий
  subs_articles  INT UNSIGNED NOT NULL DEFAULT 0,
  subs_fan       INT UNSIGNED NOT NULL DEFAULT 0,
  faction        VARCHAR(20)  NOT NULL DEFAULT 'none',
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── РАЗДЕЛЫ (сайдбар) ────────────────────────────────────
CREATE TABLE sections (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name      VARCHAR(64)  NOT NULL,
  kind      ENUM('main','fan') NOT NULL DEFAULT 'main',
  position  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── СТАТЬИ / ТВОРЧЕСТВО ──────────────────────────────────
CREATE TABLE articles (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  tag         VARCHAR(8)   NOT NULL,          -- ОБЪ, СУЩ, ЭКСП, ИСТ, ФАН
  tc          VARCHAR(8)   NOT NULL DEFAULT '', -- obj, sus, exp, his, fan (для CSS)
  body        MEDIUMTEXT   NOT NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  author_id   INT UNSIGNED NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_tag (tag),
  KEY idx_author (author_id),
  CONSTRAINT fk_article_author
    FOREIGN KEY (author_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ОЦЕНКИ ───────────────────────────────────────────────
CREATE TABLE ratings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,      -- 1..5
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- Один пользователь = одна оценка на статью (при повторе — UPDATE)
  UNIQUE KEY uq_article_user (article_id, user_id),
  CONSTRAINT fk_rating_article
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rating_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- СИД-ДАННЫЕ
-- ============================================================

-- Разделы
INSERT INTO sections (name, kind, position) VALUES
  ('История',      'main', 1),
  ('Структура',    'main', 2),
  ('Объекты',      'main', 3),
  ('Сущности',     'main', 4),
  ('Эксперименты', 'main', 5),
  ('Персонал',     'main', 6),
  ('Документы',    'main', 7),
  ('Арты',         'fan',  1),
  ('Фанфики',      'fan',  2),
  ('Исследования', 'fan',  3),
  ('Прочее',       'fan',  4);

-- Пользователи.
-- Пароли по умолчанию: admin/admin123, moder/moder123, archivist/user123, liminal_ch/user123
-- ВНИМАНИЕ: обязательно смените пароли в продакшене!
INSERT INTO users (username, password_hash, role, subs_articles, subs_fan, faction) VALUES
  ('admin',      '$2y$10$H5Sq2hkLHG9yJ91kRMEyEeCioiUjCsBLZfAzLu9sOogmknk3/Cgsy', 'admin',     12, 4, 'kontrol'),
  ('moder',      '$2y$10$UtkFj09YC.OoyoXAKpaq4.HlsMAW4p5cvXQxtvyeeHtKGwmnyzGGi', 'moderator', 8,  1, 'none'),
  ('archivist',  '$2y$10$doMP70z3u4Al5TwZGA2INOHIi/imSC25j6ub65FwyLonmIuOt.UgS', 'user',      6,  0, 'none'),
  ('liminal_ch', '$2y$10$doMP70z3u4Al5TwZGA2INOHIi/imSC25j6ub65FwyLonmIuOt.UgS', 'user',      1,  5, 'volk');

-- Статьи (author_id ссылается на users выше)
INSERT INTO articles (title, tag, tc, body, status, author_id, created_at) VALUES
(
  'Объект №102 — Лифтовая шахта (тип «Мимик-вход»)', 'ОБЪ', 'obj',
  '== ОБЪЕКТ №102 ==\nКЛАССИФИКАЦИЯ: Аномальная локация / Тип «Мимик-вход»\nУГРОЗА: Высокая\n\n--- ОПИСАНИЕ ---\nЛифтовая шахта в корпусе Б сектора 4. При детальном осмотре выявлены аномальные свойства: шахта меняет внутреннюю геометрию в зависимости от количества наблюдателей.\n\n--- МЕРЫ БЕЗОПАСНОСТИ ---\nДоступ ограничен. Обязательное сопровождение Б-3. Видеонаблюдение 24/7.',
  'approved', 3, '1993-04-14 10:00:00'
),
(
  'Каналотварь — Обновление классификации угрозы', 'СУЩ', 'sus',
  '== СУЩНОСТЬ: КАНАЛОТВАРЬ ==\nУРОВЕНЬ УГРОЗЫ: КРИТИЧЕСКИЙ\n\n--- МОРФОЛОГИЯ ---\nСущество полностью чёрного цвета. Передвигается на четырёх конечностях. Аномально длинные когти, третий или четвёртый глаз.\n\n--- ПРОТОКОЛ КОНТАКТА ---\nНе вступать в контакт без снаряжения класса Σ.',
  'approved', 3, '1995-03-22 14:30:00'
),
(
  'Эксперимент О-41/Δ — Диетические тесты', 'ЭКСП', 'exp',
  '== ЭКСПЕРИМЕНТ О-41/Δ ==\nРУКОВОДИТЕЛЬ: д-р Крылова Н.В.\n\n--- ЦЕЛЬ ---\nОпределить, можно ли замедлить распространение грибка О-41 путём изменения питания заражённых субъектов.\n\n[ДАННЫЕ ГРУППЫ 3 ИЗЪЯТЫ ПО ПРИКАЗУ КОМИТЕТА]',
  'approved', 1, '1989-11-09 09:00:00'
),
(
  'Реформа организации 1996 — ликвидация отдела ЗК', 'ИСТ', 'his',
  '== РЕФОРМА 1996 ГОДА ==\n\n--- СУТЬ РЕФОРМЫ ---\nОтдел ЗК (Защита Контура) ликвидирован. Классификация объектов пересмотрена. Сеть агентов сокращена с 240 до 41 человека.',
  'approved', 1, '1996-01-01 00:00:00'
),
(
  '[Арт] Мимик второго порядка у лифта', 'ФАН', 'fan',
  '== ФАН-АРТ ==\nАВТОР: liminal_ch\n\nЦифровая иллюстрация Мимика II в момент «замирания» у дверей лифта объекта №102. Пиксель-арт, 128×128, палитра 16 цветов.',
  'approved', 4, '2026-04-28 12:00:00'
),
(
  '[Фанфик] Доброволец в зелёном', 'ФАН', 'fan',
  '== ФАНФИК: ДОБРОВОЛЕЦ В ЗЕЛЁНОМ ==\nАВТОР: liminal_ch\n\nМеня попросили прийти в зелёном. Не объяснили зачем. На третий день еда стала другой на вкус. На пятнадцатый — услышал голос.',
  'approved', 4, '2026-04-27 18:00:00'
),
(
  '[Статья] Споровый синдром — стадии заражения', 'ИСТ', 'his',
  '== СПОРОВЫЙ СИНДРОМ: СТАДИИ ЗАРАЖЕНИЯ ==\n\n--- СТАДИЯ 1 ---\nЛёгкое ухудшение обоняния. Повышенный аппетит.\n\n--- СТАДИЯ 2 ---\nПоявление «голоса». Изменение пищевых предпочтений.',
  'pending', 3, '2026-04-29 15:00:00'
);
