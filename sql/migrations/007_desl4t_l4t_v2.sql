-- 007_desl4t_l4t_v2.sql — L4T: навыки, опыт, титры, рекомендации, QR, мероприятия,
-- знакомства, очередь джема, сохранённые поиски.
-- Накатывать в базу `desl4t` ПОСЛЕ 006_desl4t.sql. Всё аддитивно.
-- Код устойчив к отсутствию этих таблиц: блоки просто не показываются.
USE desl4t;
SET NAMES utf8mb4;   -- без этого консольный клиент в latin1 зальёт кириллицу кракозябрами

-- ═════════════════════════════ НАВЫКИ ═════════════════════════════
-- Справочник вместо свободного текста: без него ни подбора, ни ленты «Для тебя».
-- grp — крупная группа ролей для балансировки команд: code | art | design | audio | prod
CREATE TABLE IF NOT EXISTS skills (
    id      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug    VARCHAR(32)  NOT NULL UNIQUE,
    name    VARCHAR(48)  NOT NULL,
    kind    VARCHAR(8)   NOT NULL,     -- role | engine | tool
    grp     VARCHAR(8)   NOT NULL,
    aliases VARCHAR(255) NULL,         -- через запятую, для распознавания в тексте заявок
    sort    SMALLINT     NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_skills (
    user_id  INT               NOT NULL,
    skill_id SMALLINT UNSIGNED NOT NULL,
    level    TINYINT           NOT NULL DEFAULT 2,   -- 1 учусь, 2 уверенно, 3 эксперт
    PRIMARY KEY (user_id, skill_id),
    KEY idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bid_skills (
    bid_id   INT               NOT NULL,
    skill_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (bid_id, skill_id),
    KEY idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO skills (slug, name, kind, grp, aliases, sort) VALUES
('programmer',     'Программист',          'role', 'code',   'программист,разработчик,developer,coder,dev', 10),
('gameplay-prog',  'Геймплей-программист', 'role', 'code',   'геймплей,gameplay', 11),
('backend',        'Бэкенд / сеть',        'role', 'code',   'бэкенд,backend,сервер,мультиплеер,netcode', 12),
('tech-artist',    'Технический художник', 'role', 'art',    'техарт,tech art,шейдер,shader', 20),
('2d-artist',      '2D-художник',          'role', 'art',    '2d,иллюстратор,художник', 21),
('3d-artist',      '3D-художник',          'role', 'art',    '3d,моделлер,модельер,3д', 22),
('pixel-artist',   'Pixel-art',            'role', 'art',    'pixel,пиксель,пиксельный', 23),
('animator',       'Аниматор',             'role', 'art',    'анимац,animator,риггер,rig', 24),
('concept-artist', 'Концепт-художник',     'role', 'art',    'концепт,concept', 25),
('ui-artist',      'UI/UX',                'role', 'design', 'ui,ux,интерфейс', 30),
('game-designer',  'Геймдизайнер',         'role', 'design', 'геймдизайн,game design,gd', 31),
('level-designer', 'Левел-дизайнер',       'role', 'design', 'левел,level', 32),
('narrative',      'Нарративщик / сценарист','role','design','сценари,нарратив,writer,писатель,текст', 33),
('composer',       'Композитор',           'role', 'audio',  'композитор,музык,composer,саундтрек', 40),
('sound-designer', 'Звукорежиссёр',        'role', 'audio',  'звук,sound,sfx,саунд', 41),
('producer',       'Продюсер / PM',        'role', 'prod',   'продюсер,менеджер,pm,producer', 50),
('qa',             'Тестировщик',          'role', 'prod',   'тестир,qa,тестер', 51),
('marketing',      'Маркетинг',            'role', 'prod',   'маркетин,smm,продвижен', 52),
('community',      'Комьюнити',            'role', 'prod',   'комьюнити,community,модератор', 53),
('unity',          'Unity',                'engine','code',  'unity,юнити', 60),
('unreal',         'Unreal Engine',        'engine','code',  'unreal,ue4,ue5,анрил', 61),
('godot',          'Godot',                'engine','code',  'godot,годот', 62),
('gamemaker',      'GameMaker',            'engine','code',  'gamemaker,gms', 63),
('defold',         'Defold',               'engine','code',  'defold', 64),
('construct',      'Construct',            'engine','code',  'construct', 65),
('renpy',          'Ren''Py',              'engine','code',  'renpy,ren''py,визуальная новелла', 66),
('csharp',         'C#',                   'tool', 'code',   'c#,csharp', 70),
('cpp',            'C++',                  'tool', 'code',   'c++,cpp', 71),
('gdscript',       'GDScript',             'tool', 'code',   'gdscript', 72),
('lua',            'Lua',                  'tool', 'code',   'lua', 73),
('js-ts',          'JavaScript / TS',      'tool', 'code',   'javascript,typescript,js,ts,html5', 74),
('python',         'Python',               'tool', 'code',   'python,питон', 75),
('blender',        'Blender',              'tool', 'art',    'blender,блендер', 80),
('maya',           'Maya / 3ds Max',       'tool', 'art',    'maya,3ds max,3dsmax', 81),
('zbrush',         'ZBrush',               'tool', 'art',    'zbrush,скульпт', 82),
('substance',      'Substance',            'tool', 'art',    'substance,текстур', 83),
('photoshop',      'Photoshop',            'tool', 'art',    'photoshop,фотошоп', 84),
('aseprite',       'Aseprite',             'tool', 'art',    'aseprite', 85),
('spine',          'Spine',                'tool', 'art',    'spine', 86),
('figma',          'Figma',                'tool', 'design', 'figma,фигма', 87),
('fmod',           'FMOD / Wwise',         'tool', 'audio',  'fmod,wwise', 88),
('daw',            'DAW (FL, Reaper, Ableton)', 'tool', 'audio', 'fl studio,reaper,ableton,cubase', 89);


-- ═════════════════════════════ ПРОФИЛЬ ═════════════════════════════
-- Новые поля витрины. ALTER без IF NOT EXISTS (MySQL 8 его не знает):
-- если колонка уже есть, строка упадёт с 1060. При повторном прогоне запускай
-- `mysql --force desl4t < 007_desl4t_l4t_v2.sql` — ошибки 1060 просто пропустятся.
ALTER TABLE profiles ADD COLUMN work_modes  VARCHAR(64)  NULL;   -- paid,share,jam,free
ALTER TABLE profiles ADD COLUMN rate        VARCHAR(60)  NULL;
ALTER TABLE profiles ADD COLUMN tz          SMALLINT     NULL;   -- смещение от UTC в минутах
ALTER TABLE profiles ADD COLUMN manual      TEXT         NULL;   -- «как со мной работать»
ALTER TABLE profiles ADD COLUMN avail_until DATE         NULL;   -- статус «ищу» протухает сам

-- Решение по отклику: когда владелец заявки ответил. Нужно для «обычно
-- отвечает за N часов» и GPI-метрики «время до команды».
ALTER TABLE responds ADD COLUMN decided_at DATETIME NULL;


-- ═════════════════════════════ ОПЫТ И ТИТРЫ ═════════════════════════════
-- studio_id — студия Dustore. Тогда запись ждёт подтверждения её владельца
-- (или подтверждена сразу, если человек есть в staff — verified_by='staff').
CREATE TABLE IF NOT EXISTS work_experience (
    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    studio_id   INT          NULL,
    org_name    VARCHAR(120) NOT NULL,
    title       VARCHAR(120) NOT NULL,
    start_ym    CHAR(7)      NULL,            -- 2024-03
    end_ym      CHAR(7)      NULL,            -- NULL = по сей день
    description VARCHAR(1000) NULL,
    status      VARCHAR(10)  NOT NULL DEFAULT 'self',   -- self | pending | verified | rejected
    verified_by VARCHAR(16)  NULL,            -- owner | staff
    verified_at DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_studio (studio_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Титры: «роль в конкретной игре/проекте». Часть собирается сама из данных
-- (команды джемов, студии, принятые отклики), часть — вручную.
CREATE TABLE IF NOT EXISTS credits (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    game_id    INT          NULL,
    title      VARCHAR(160) NOT NULL,
    role       VARCHAR(80)  NULL,
    year       SMALLINT     NULL,
    source     VARCHAR(8)   NOT NULL DEFAULT 'manual',   -- manual | jam | studio | l4t
    ref_id     INT          NOT NULL DEFAULT 0,
    verified   TINYINT      NOT NULL DEFAULT 0,
    hidden     TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto (user_id, source, ref_id),
    KEY idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═════════════════════════════ РЕКОМЕНДАЦИИ ═════════════════════════════
-- Только от тех, с кем реально работал: контекст проверяется при записи.
-- again — «поработал бы снова», приватно; наружу только агрегат от 3 ответов.
CREATE TABLE IF NOT EXISTS recommendations (
    id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    author_id     INT          NOT NULL,
    target_id     INT          NOT NULL,
    context_type  VARCHAR(8)   NOT NULL,      -- team | studio | l4t
    context_id    INT          NOT NULL,
    context_label VARCHAR(160) NOT NULL,
    skills        VARCHAR(80)  NULL,          -- до 2 slug через запятую
    text          VARCHAR(1500) NOT NULL,
    again         TINYINT      NOT NULL DEFAULT 1,
    hidden        TINYINT      NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pair (author_id, target_id),
    KEY idx_target (target_id, hidden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═════════════════════════════ QR, РЕЗЮМЕ, ЗНАКОМСТВА ═════════════════════════════
CREATE TABLE IF NOT EXISTS share_links (
    id           INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT         NOT NULL,
    token        CHAR(16)    NOT NULL UNIQUE,
    label        VARCHAR(80) NOT NULL,
    views        INT         NOT NULL DEFAULT 0,
    last_view_at DATETIME    NULL,
    revoked_at   DATETIME    NULL,
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
    owner_id   INT          NOT NULL,
    contact_id INT          NOT NULL,
    event_id   INT          NULL,
    note       VARCHAR(200) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (owner_id, contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS l4t_events (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    host_id    INT          NOT NULL,
    title      VARCHAR(120) NOT NULL,
    place      VARCHAR(120) NULL,
    starts_at  DATETIME     NOT NULL,
    ends_at    DATETIME     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_host (host_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_checkins (
    event_id   INT      NOT NULL,
    user_id    INT      NOT NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, user_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═════════════════════════════ ДЖЕМ: ОЧЕРЕДЬ И АВТОСБОРКА ═════════════════════════════
CREATE TABLE IF NOT EXISTS jam_queue (
    sprint_id  INT         NOT NULL,
    user_id    INT         NOT NULL,
    grp        VARCHAR(8)  NOT NULL,        -- code | art | design | audio | prod
    skill      VARCHAR(32) NULL,
    tz         SMALLINT    NULL,
    note       VARCHAR(200) NULL,
    team_id    INT         NULL,            -- заполняется при сборке
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sprint_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═════════════════════════════ СОХРАНЁННЫЕ ПОИСКИ ═════════════════════════════
CREATE TABLE IF NOT EXISTS saved_searches (
    id         INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT         NOT NULL,
    skill      VARCHAR(32) NULL,
    q          VARCHAR(80) NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_skill (skill)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- кого уже присылали по поиску — чтобы не спамить одним и тем же
CREATE TABLE IF NOT EXISTS saved_search_hits (
    search_id INT      NOT NULL,
    user_id   INT      NOT NULL,
    hit_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (search_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
