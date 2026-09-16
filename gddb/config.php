<?php
// Подключение к БД. Под XAMPP по умолчанию root без пароля.
const DB_DSN  = 'mysql:host=127.0.0.1;dbname=gddb;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = '';

// Базовый URL приложения (как оно лежит в htdocs).
const BASE = '/gddb';

// Шаблон новой заметки.
const NOTE_TEMPLATE = "## Что это, если объяснять с нуля\n\n> Пиши так, будто объясняешь человеку, который про это никогда не слышал.\n\n## Как это работает\n\n## Зачем это нужно дальше\n\n## Источники\n";
