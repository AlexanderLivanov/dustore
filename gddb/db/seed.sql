-- GDDB — первые 10 узлов: от транзистора до запуска программы.
-- Запускать ПОСЛЕ schema.sql.
SET NAMES utf8mb4;
USE gddb;

-- Разделы -------------------------------------------------------------
INSERT INTO sections (slug, title, parent_id, position) VALUES
  ('computer-science', 'Computer Science', NULL, 1),
  ('game-development', 'Game Development', NULL, 2);

INSERT INTO sections (slug, title, parent_id, position)
SELECT x.slug, x.title, p.id, x.pos FROM (
  SELECT 'hardware'              AS slug, 'Железо и электроника'        AS title, 1 AS pos UNION ALL
  SELECT 'digital-logic',               'Цифровая логика',                   2 UNION ALL
  SELECT 'data-representation',         'Представление данных',              3 UNION ALL
  SELECT 'computer-architecture',       'Архитектура компьютера',            4 UNION ALL
  SELECT 'systems-software',            'Системное ПО',                      5
) x JOIN sections p ON p.slug = 'computer-science';

-- Теги ----------------------------------------------------------------
INSERT INTO tags (slug, title) VALUES
  ('computer-science',      'computer-science'),
  ('computer-architecture', 'computer-architecture'),
  ('digital-logic',         'digital-logic'),
  ('electronics',           'electronics'),
  ('mathematics',           'mathematics'),
  ('programming',           'programming'),
  ('operating-systems',     'operating-systems'),
  ('networks',              'networks'),
  ('compilers',             'compilers'),
  ('distributed-systems',   'distributed-systems');

-- Шаблон заметки ------------------------------------------------------
SET @tpl = '## Что это, если объяснять с нуля

> Пиши так, будто объясняешь человеку, который про это никогда не слышал.

## Как это работает

## Зачем это нужно дальше

## Источники
';

-- Заметки -------------------------------------------------------------
INSERT INTO articles (slug, title, section_id, summary, body_md, status)
SELECT x.slug, x.title, s.id, x.summary, @tpl, 'stub' FROM (
  SELECT 'transistors' AS slug, 'Транзисторы' AS title, 'hardware' AS sec,
         'Как физический компонент может работать переключателем и почему из этого получается логика' AS summary UNION ALL
  SELECT 'logic-gates', 'Логические вентили', 'digital-logic',
         'NOT, AND, OR, NAND, NOR, XOR и как из простых вентилей собираются сложные схемы' UNION ALL
  SELECT 'boolean-algebra', 'Булева алгебра', 'digital-logic',
         'Логические выражения, законы Де Моргана, упрощение логики' UNION ALL
  SELECT 'combinational-logic', 'Комбинационная логика', 'digital-logic',
         'Сумматор, мультиплексор, декодер, компаратор и ALU из одних вентилей' UNION ALL
  SELECT 'sequential-logic', 'Последовательная логика', 'digital-logic',
         'Почему компьютеру нужна память: latch, flip-flop, регистры' UNION ALL
  SELECT 'binary-arithmetic', 'Двоичная арифметика и представление данных', 'data-representation',
         'Binary, hex, signed/unsigned, дополнительный код, переполнение' UNION ALL
  SELECT 'memory-and-addressing', 'Память и адресация', 'computer-architecture',
         'RAM, ячейки, адреса, шины — как процессор читает и записывает данные' UNION ALL
  SELECT 'cpu-datapath', 'Цифровая схема процессора', 'computer-architecture',
         'ALU, регистры, control unit, такт, instruction cycle' UNION ALL
  SELECT 'isa', 'Машинные инструкции и ISA', 'computer-architecture',
         'Instruction, opcode, операнды, режимы адресации и связь с ассемблером' UNION ALL
  SELECT 'how-a-program-runs', 'Как работает программа на компьютере', 'systems-software',
         'Исходник, компилятор, машинный код, исполняемый файл, загрузка, выполнение'
) x JOIN sections s ON s.slug = x.sec;

-- Теги заметок --------------------------------------------------------
INSERT INTO article_tags (article_id, tag_id)
SELECT a.id, t.id FROM (
  SELECT 'transistors' AS a, 'electronics' AS t UNION ALL
  SELECT 'transistors', 'computer-science' UNION ALL
  SELECT 'logic-gates', 'digital-logic' UNION ALL
  SELECT 'logic-gates', 'electronics' UNION ALL
  SELECT 'logic-gates', 'computer-science' UNION ALL
  SELECT 'boolean-algebra', 'digital-logic' UNION ALL
  SELECT 'boolean-algebra', 'mathematics' UNION ALL
  SELECT 'boolean-algebra', 'computer-science' UNION ALL
  SELECT 'combinational-logic', 'digital-logic' UNION ALL
  SELECT 'combinational-logic', 'computer-architecture' UNION ALL
  SELECT 'sequential-logic', 'digital-logic' UNION ALL
  SELECT 'sequential-logic', 'computer-architecture' UNION ALL
  SELECT 'binary-arithmetic', 'mathematics' UNION ALL
  SELECT 'binary-arithmetic', 'computer-architecture' UNION ALL
  SELECT 'memory-and-addressing', 'computer-architecture' UNION ALL
  SELECT 'cpu-datapath', 'computer-architecture' UNION ALL
  SELECT 'cpu-datapath', 'digital-logic' UNION ALL
  SELECT 'isa', 'computer-architecture' UNION ALL
  SELECT 'isa', 'programming' UNION ALL
  SELECT 'how-a-program-runs', 'programming' UNION ALL
  SELECT 'how-a-program-runs', 'compilers' UNION ALL
  SELECT 'how-a-program-runs', 'operating-systems'
) x JOIN articles a ON a.slug = x.a JOIN tags t ON t.slug = x.t;

-- Граф: from --prerequisite--> to  ("чтобы понять from, нужен to")
INSERT INTO article_links (from_id, to_id, type, auto)
SELECT f.id, t.id, x.type, 0 FROM (
  SELECT 'logic-gates' AS f, 'transistors' AS t, 'prerequisite' AS type UNION ALL
  SELECT 'boolean-algebra', 'logic-gates', 'prerequisite' UNION ALL
  SELECT 'combinational-logic', 'logic-gates', 'prerequisite' UNION ALL
  SELECT 'combinational-logic', 'boolean-algebra', 'prerequisite' UNION ALL
  SELECT 'sequential-logic', 'combinational-logic', 'prerequisite' UNION ALL
  SELECT 'binary-arithmetic', 'boolean-algebra', 'prerequisite' UNION ALL
  SELECT 'memory-and-addressing', 'sequential-logic', 'prerequisite' UNION ALL
  SELECT 'cpu-datapath', 'combinational-logic', 'prerequisite' UNION ALL
  SELECT 'cpu-datapath', 'sequential-logic', 'prerequisite' UNION ALL
  SELECT 'cpu-datapath', 'binary-arithmetic', 'prerequisite' UNION ALL
  SELECT 'cpu-datapath', 'memory-and-addressing', 'prerequisite' UNION ALL
  SELECT 'isa', 'cpu-datapath', 'prerequisite' UNION ALL
  SELECT 'how-a-program-runs', 'isa', 'prerequisite' UNION ALL
  SELECT 'how-a-program-runs', 'memory-and-addressing', 'prerequisite' UNION ALL
  SELECT 'boolean-algebra', 'logic-gates', 'explains' UNION ALL
  SELECT 'boolean-algebra', 'combinational-logic', 'explains'
) x JOIN articles f ON f.slug = x.f JOIN articles t ON t.slug = x.t;

-- Первый открытый вопрос к каждой теме: то, ради чего её читаешь.
INSERT INTO questions (article_id, text)
SELECT a.id, x.q FROM (
  SELECT 'transistors' AS a, 'Почему именно транзистор может работать как переключатель?' AS q UNION ALL
  SELECT 'logic-gates', 'Почему NAND называют универсальным вентилем?' UNION ALL
  SELECT 'boolean-algebra', 'Зачем упрощать логическое выражение, если схема и так работает?' UNION ALL
  SELECT 'combinational-logic', 'Откуда берётся задержка в сумматоре и почему она ограничивает частоту?' UNION ALL
  SELECT 'sequential-logic', 'Как из схемы, у которой нет памяти, вообще получается память?' UNION ALL
  SELECT 'binary-arithmetic', 'Почему выбрали дополнительный код, а не знак-величину?' UNION ALL
  SELECT 'memory-and-addressing', 'Что физически происходит, когда процессор выставляет адрес на шину?' UNION ALL
  SELECT 'cpu-datapath', 'Зачем нужен тактовый сигнал — почему не считать сразу, как только готово?' UNION ALL
  SELECT 'isa', 'Почему ISA — это контракт, а не реализация?' UNION ALL
  SELECT 'how-a-program-runs', 'Кто и в какой момент превращает адреса в тексте программы в реальные адреса в памяти?'
) x JOIN articles a ON a.slug = x.a;
