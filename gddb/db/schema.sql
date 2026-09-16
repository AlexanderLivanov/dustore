-- GDDB — личная база знаний. Схема.
-- Импорт: phpMyAdmin → Import, либо
--   mysql -u root < db/schema.sql
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS gddb
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gddb;

DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS revisions;
DROP TABLE IF EXISTS article_links;
DROP TABLE IF EXISTS article_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS sections;

-- ОСЬ 1: разделы. Где заметка живёт. Одна заметка — один раздел.
CREATE TABLE sections (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  slug      VARCHAR(80)  NOT NULL,
  title     VARCHAR(120) NOT NULL,
  position  SMALLINT     NOT NULL DEFAULT 0,
  UNIQUE KEY uq_section_slug (slug),
  KEY k_parent (parent_id),
  CONSTRAINT fk_section_parent FOREIGN KEY (parent_id)
    REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заметки.
-- status:     stub — упомянута, но не написана; draft — пишется; published — готова.
-- confidence: 0 не задано, 1 пересказал, 2 отвечу на вопросы, 3 объясню с нуля.
CREATE TABLE articles (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(200) NOT NULL,
  section_id INT UNSIGNED NULL,
  summary    VARCHAR(400) NOT NULL DEFAULT '',
  body_md    MEDIUMTEXT   NOT NULL,
  status     ENUM('stub','draft','published') NOT NULL DEFAULT 'draft',
  confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_article_slug (slug),
  KEY k_section (section_id),
  KEY k_status (status),
  CONSTRAINT fk_article_section FOREIGN KEY (section_id)
    REFERENCES sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ОСЬ 2: теги. По каким срезам заметка всплывает. Плоский словарь.
CREATE TABLE tags (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug  VARCHAR(60)  NOT NULL,
  title VARCHAR(80)  NOT NULL,
  UNIQUE KEY uq_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_tags (
  article_id INT UNSIGNED NOT NULL,
  tag_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, tag_id),
  KEY k_tag (tag_id),
  CONSTRAINT fk_at_article FOREIGN KEY (article_id)
    REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_at_tag FOREIGN KEY (tag_id)
    REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ОСЬ 3: граф. Направление: from_id --type--> to_id
--   (logic-gate, prerequisite, transistor) = "транзистор нужен, чтобы понять вентиль"
-- auto = 1 — связь вытащена из [[двойных скобок]] в тексте, пересобирается при сохранении.
-- auto = 0 — поставлена руками в панели редактора, её никто не тронет.
CREATE TABLE article_links (
  from_id INT UNSIGNED NOT NULL,
  to_id   INT UNSIGNED NOT NULL,
  type    ENUM('prerequisite','explains','used_by','related') NOT NULL DEFAULT 'related',
  auto    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (from_id, to_id, type),
  KEY k_to (to_id),
  CONSTRAINT fk_link_from FOREIGN KEY (from_id)
    REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_link_to FOREIGN KEY (to_id)
    REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- История объяснений. Смысл проекта: сравнить, как ты объяснял это полгода назад.
CREATE TABLE revisions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  title      VARCHAR(200) NOT NULL,
  body_md    MEDIUMTEXT   NOT NULL,
  note       VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_article_time (article_id, created_at),
  CONSTRAINT fk_rev_article FOREIGN KEY (article_id)
    REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Дыры в понимании как сущность, а не абзац в конце текста.
CREATE TABLE questions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  text       VARCHAR(500) NOT NULL,
  answer_md  TEXT NULL,
  status     ENUM('open','answered') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_article (article_id),
  KEY k_status (status),
  CONSTRAINT fk_q_article FOREIGN KEY (article_id)
    REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
